<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SplFileInfo;
use Throwable;

class LogEntryReader
{
    private const string ENTRY_START_PATTERN = '/^\[[^\]]+\]\s.+?\.[A-Z]+:\s/';

    private const int MAX_ENTRY_CONTENT_BYTES = 64 * 1024;

    private const int READ_BUFFER_BYTES = 8192;

    private const string TRUNCATION_MARKER = '[Entry content truncated after 64 KB to protect memory.]';

    public function __construct(
        private readonly ?string $logDirectory = null,
    ) {}

    /**
     * @return array{
     *     files: array<int, array{name: string, size: int, modified_at: string|null, entries_count: int}>,
     *     entries: array<int, array{
     *         file_name: string,
     *         channel: string|null,
     *         level: string|null,
     *         summary: string,
     *         content: string,
     *         timestamp: string|null
     *     }>,
     *     total: int
     * }
     */
    public function paginate(int $page, int $perPage): array
    {
        if (! File::isDirectory($this->directory())) {
            return [
                'files' => [],
                'entries' => [],
                'total' => 0,
            ];
        }

        $entries = [];
        $files = [];
        $totalEntries = 0;
        $entriesToSkip = max(0, ($page - 1) * $perPage);
        $entriesToTake = max(0, $perPage);

        /** @var array<int, SplFileInfo> $logFiles */
        $logFiles = collect(File::files($this->directory()))
            ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'log')
            ->sortByDesc(fn (SplFileInfo $file): int => $file->getMTime())
            ->values()
            ->all();

        /**
         * @var array<int, array{
         *     file: SplFileInfo,
         *     modified_at: CarbonImmutable,
         *     size: int,
         *     entries_count: int
         * }> $indexedFiles
         */
        $indexedFiles = [];

        foreach ($logFiles as $file) {
            $modifiedAt = CarbonImmutable::createFromTimestamp($file->getMTime());
            $size = $file->getSize();
            $entriesCount = $this->countEntries($file->getPathname(), $size);

            $totalEntries += $entriesCount;

            $files[] = [
                'name' => $file->getFilename(),
                'size' => $size,
                'modified_at' => $modifiedAt->toIso8601String(),
                'entries_count' => $entriesCount,
            ];

            $indexedFiles[] = [
                'file' => $file,
                'modified_at' => $modifiedAt,
                'size' => $size,
                'entries_count' => $entriesCount,
            ];
        }

        foreach ($indexedFiles as $indexedFile) {
            $entriesCount = $indexedFile['entries_count'];

            if ($entriesToTake <= 0 || $entriesCount === 0) {
                continue;
            }

            if ($entriesToSkip >= $entriesCount) {
                $entriesToSkip -= $entriesCount;

                continue;
            }

            $entriesFromFile = min($entriesToTake, $entriesCount - $entriesToSkip);
            $lastEntryIndex = $entriesCount - $entriesToSkip;
            $firstEntryIndex = $lastEntryIndex - $entriesFromFile;
            $chunksForPage = $this->entryChunksInRange(
                $indexedFile['file']->getPathname(),
                $indexedFile['size'],
                $firstEntryIndex,
                $lastEntryIndex,
            );
            $entriesToSkip = 0;

            foreach ($chunksForPage as $chunk) {
                $entries[] = $this->parseEntry(
                    $chunk,
                    $indexedFile['file']->getFilename(),
                    $indexedFile['modified_at'],
                );
            }

            $entriesToTake -= count($chunksForPage);
        }

        return [
            'files' => $files,
            'entries' => $entries,
            'total' => $totalEntries,
        ];
    }

    private function directory(): string
    {
        return $this->logDirectory ?? storage_path('logs');
    }

    /**
     * @return Generator<int, string>
     */
    private function fileSegments(string $path, int $size): Generator
    {
        if ($size <= 0) {
            return;
        }

        $stream = @fopen($path, 'rb');

        if ($stream === false) {
            return;
        }

        $remainingBytes = $size;

        try {
            while ($remainingBytes > 0) {
                $readLength = min(self::READ_BUFFER_BYTES, $remainingBytes + 1);
                $segment = fgets($stream, $readLength);

                if ($segment === false) {
                    break;
                }

                $remainingBytes -= mb_strlen($segment, '8bit');

                yield $segment;
            }
        } finally {
            fclose($stream);
        }
    }

    private function countEntries(string $path, int $size): int
    {
        $entriesCount = 0;
        $hasCurrentEntry = false;
        $atLineStart = true;

        foreach ($this->fileSegments($path, $size) as $segment) {
            if ($atLineStart && $this->startsEntry($segment) && $hasCurrentEntry) {
                $entriesCount++;
                $hasCurrentEntry = false;
            }

            if (trim($segment) !== '') {
                $hasCurrentEntry = true;
            }

            $atLineStart = Str::endsWith($segment, "\n");
        }

        return $entriesCount + (int) $hasCurrentEntry;
    }

    /**
     * @return array<int, string>
     */
    private function entryChunksInRange(
        string $path,
        int $size,
        int $firstEntryIndex,
        int $lastEntryIndex,
    ): array {
        $chunks = [];
        $currentEntryIndex = null;
        $currentContent = '';
        $currentEntryTruncated = false;
        $atLineStart = true;

        foreach ($this->fileSegments($path, $size) as $segment) {
            if ($atLineStart && $this->startsEntry($segment) && $currentEntryIndex !== null) {
                $this->storeEntryChunk(
                    $chunks,
                    $currentEntryIndex,
                    $firstEntryIndex,
                    $lastEntryIndex,
                    $currentContent,
                    $currentEntryTruncated,
                );

                $currentEntryIndex++;
                $currentContent = '';
                $currentEntryTruncated = false;
            }

            if ($currentEntryIndex === null && trim($segment) !== '') {
                $currentEntryIndex = 0;
            }

            if (
                $currentEntryIndex !== null
                && $currentEntryIndex >= $firstEntryIndex
                && $currentEntryIndex < $lastEntryIndex
            ) {
                $this->appendEntryContent($currentContent, $segment, $currentEntryTruncated);
            }

            $atLineStart = Str::endsWith($segment, "\n");
        }

        if ($currentEntryIndex !== null) {
            $this->storeEntryChunk(
                $chunks,
                $currentEntryIndex,
                $firstEntryIndex,
                $lastEntryIndex,
                $currentContent,
                $currentEntryTruncated,
            );
        }

        return array_values(array_reverse($chunks));
    }

    private function startsEntry(string $segment): bool
    {
        return preg_match(self::ENTRY_START_PATTERN, $segment) === 1;
    }

    private function appendEntryContent(string &$content, string $segment, bool &$truncated): void
    {
        if ($truncated) {
            return;
        }

        $remainingBytes = self::MAX_ENTRY_CONTENT_BYTES - mb_strlen($content, '8bit');
        $segmentBytes = mb_strlen($segment, '8bit');

        if ($remainingBytes <= 0) {
            $truncated = true;

            return;
        }

        if ($segmentBytes <= $remainingBytes) {
            $content .= $segment;

            return;
        }

        $content .= mb_strcut($segment, 0, $remainingBytes, 'UTF-8');
        $truncated = true;
    }

    /**
     * @param  array<int, string>  $chunks
     */
    private function storeEntryChunk(
        array &$chunks,
        int $entryIndex,
        int $firstEntryIndex,
        int $lastEntryIndex,
        string $content,
        bool $truncated,
    ): void {
        if ($entryIndex < $firstEntryIndex || $entryIndex >= $lastEntryIndex) {
            return;
        }

        $content = trim($content);

        if ($truncated) {
            $content = rtrim($content).PHP_EOL.self::TRUNCATION_MARKER;
        }

        if ($content !== '') {
            $chunks[] = $content;
        }
    }

    /**
     * @return array{
     *     file_name: string,
     *     channel: string|null,
     *     level: string|null,
     *     summary: string,
     *     content: string,
     *     timestamp: string|null
     * }
     */
    private function parseEntry(
        string $content,
        string $fileName,
        CarbonImmutable $modifiedAt,
    ): array {
        $channel = null;
        $level = null;
        $timestamp = null;
        $summary = Str::limit(Str::squish(Str::before($content, PHP_EOL)), 180);

        if (
            preg_match(
                '/^\[(?<timestamp>[^\]]+)\]\s(?<channel>.+?)\.(?<level>[A-Z]+):\s?(?<message>.*)$/s',
                $content,
                $matches,
            ) === 1
        ) {
            $timestamp = $this->parseTimestamp($matches['timestamp']);
            $channel = trim($matches['channel']);
            $level = Str::lower(trim($matches['level']));
            $message = trim($matches['message']);
            $summary = Str::limit(
                Str::squish(Str::before($message, PHP_EOL) ?: $message),
                180,
            );
        }

        return [
            'file_name' => $fileName,
            'channel' => $channel,
            'level' => $level,
            'summary' => $summary,
            'content' => $content,
            'timestamp' => ($timestamp ?? $modifiedAt)->toIso8601String(),
        ];
    }

    private function parseTimestamp(string $timestamp): ?CarbonImmutable
    {
        try {
            return CarbonImmutable::parse($timestamp);
        } catch (Throwable) {
            return null;
        }
    }
}

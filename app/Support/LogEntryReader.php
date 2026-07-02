<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use SplFileInfo;
use Throwable;

class LogEntryReader
{
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
        $entriesToTake = $perPage;

        /** @var array<int, SplFileInfo> $logFiles */
        $logFiles = collect(File::files($this->directory()))
            ->filter(fn (SplFileInfo $file): bool => $file->getExtension() === 'log')
            ->sortByDesc(fn (SplFileInfo $file): int => $file->getMTime())
            ->values()
            ->all();

        foreach ($logFiles as $file) {
            $modifiedAt = CarbonImmutable::createFromTimestamp($file->getMTime());
            $content = trim((string) File::get($file->getPathname()));
            $chunks = $this->entryChunks($content);
            $entriesCount = count($chunks);

            $totalEntries += $entriesCount;

            $files[] = [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'modified_at' => $modifiedAt->toIso8601String(),
                'entries_count' => $entriesCount,
            ];

            if ($entriesToTake <= 0 || $entriesCount === 0) {
                continue;
            }

            if ($entriesToSkip >= $entriesCount) {
                $entriesToSkip -= $entriesCount;

                continue;
            }

            $chunksForPage = array_slice($chunks, $entriesToSkip, $entriesToTake);
            $entriesToSkip = 0;

            foreach ($chunksForPage as $chunk) {
                $entries[] = $this->parseEntry(
                    $chunk,
                    $file->getFilename(),
                    $modifiedAt,
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
     * @return array<int, string>
     */
    private function entryChunks(string $content): array
    {
        if ($content === '') {
            return [];
        }

        $chunks = preg_split(
            '/(?=^\[[^\]]+\]\s.+?\.[A-Z]+:\s)/m',
            $content,
            -1,
            PREG_SPLIT_NO_EMPTY,
        );

        if (! is_array($chunks) || $chunks === []) {
            $chunks = [$content];
        }

        $chunks = array_values(array_filter(
            array_map(static fn (string $chunk): string => trim($chunk), $chunks),
            static fn (string $chunk): bool => $chunk !== '',
        ));

        return array_values(array_reverse($chunks));
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

<?php

namespace App\Support;

use App\Models\ReferenceDirectory;
use App\Models\ReferenceDirectoryRecord;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReferenceDirectoryCsvService
{
    /**
     * @return array<int, string>
     */
    public function headers(ReferenceDirectory $referenceDirectory): array
    {
        return collect($referenceDirectory->columnDefinitions())
            ->pluck('key')
            ->values()
            ->all();
    }

    public function downloadRecords(
        ReferenceDirectory $referenceDirectory,
        string $fileName,
        string $delimiter = ';',
    ): StreamedResponse {
        $headers = $this->headers($referenceDirectory);
        $records = $referenceDirectory->records()->get();

        return response()->streamDownload(function () use ($headers, $records, $delimiter): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers, $delimiter);

            foreach ($records as $record) {
                fputcsv($output, $this->recordRow($headers, $record), $delimiter);
            }

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadTemplate(
        ReferenceDirectory $referenceDirectory,
        string $fileName,
        string $delimiter = ';',
    ): StreamedResponse {
        $headers = $this->headers($referenceDirectory);
        $sampleRow = collect($referenceDirectory->columnDefinitions())
            ->map(fn (array $column): string => $this->sampleValueForColumn($column))
            ->values()
            ->all();

        return response()->streamDownload(function () use ($headers, $sampleRow, $delimiter): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $headers, $delimiter);
            fputcsv($output, $sampleRow, $delimiter);
            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(
        ReferenceDirectory $referenceDirectory,
        UploadedFile $file,
        ?User $actor = null,
        string $delimiter = ';',
    ): int {
        $rows = $this->parseRows($referenceDirectory, $file, $delimiter);

        foreach ($rows as $row) {
            $referenceDirectory->records()->create([
                'values' => $row,
                'created_by_user_id' => $actor?->id,
                'updated_by_user_id' => $actor?->id,
            ]);
        }

        return count($rows);
    }

    public static function normalizeDelimiter(mixed $delimiter): ?string
    {
        if (! is_string($delimiter)) {
            return ';';
        }

        $normalized = trim($delimiter);

        if ($normalized === '') {
            return ';';
        }

        if (in_array(Str::lower($normalized), ['\\t', 'tab'], true)) {
            return "\t";
        }

        return mb_strlen($normalized) === 1 ? $normalized : null;
    }

    /**
     * @return array<int, array<string, bool|float|int|string|null>>
     */
    private function parseRows(
        ReferenceDirectory $referenceDirectory,
        UploadedFile $file,
        string $delimiter,
    ): array {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => __('ui.directories.csv_import_invalid_file'),
            ]);
        }

        $header = fgetcsv($handle, 0, $delimiter);

        if ($header === false) {
            fclose($handle);

            throw ValidationException::withMessages([
                'file' => __('ui.directories.csv_import_empty'),
            ]);
        }

        $headers = array_map(
            fn (mixed $value): string => $this->normalizeHeaderValue($value),
            $header,
        );

        $expectedHeaders = $this->headers($referenceDirectory);
        $missingHeaders = array_values(array_diff($expectedHeaders, $headers));

        if ($missingHeaders !== []) {
            fclose($handle);

            throw ValidationException::withMessages([
                'file' => __('ui.directories.csv_import_missing_headers', [
                    'columns' => implode(', ', $missingHeaders),
                ]),
            ]);
        }

        $rows = [];
        $rowNumber = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $rowNumber++;

            if ($this->rowIsEmpty($row)) {
                continue;
            }

            $mappedRow = [];

            foreach ($headers as $index => $columnKey) {
                $mappedRow[$columnKey] = isset($row[$index]) && is_string($row[$index])
                    ? trim($row[$index])
                    : '';
            }

            $normalizedRow = $referenceDirectory->normalizedRecordValues($mappedRow);
            $this->validateRow($referenceDirectory, $mappedRow, $normalizedRow, $rowNumber);
            $rows[] = $normalizedRow;
        }

        fclose($handle);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => __('ui.directories.csv_import_empty'),
            ]);
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $rawValues
     * @param  array<string, bool|float|int|string|null>  $normalizedValues
     */
    private function validateRow(
        ReferenceDirectory $referenceDirectory,
        array $rawValues,
        array $normalizedValues,
        int $rowNumber,
    ): void {
        foreach ($referenceDirectory->columnDefinitions() as $column) {
            $rawValue = $rawValues[$column['key']] ?? '';
            $normalizedValue = $normalizedValues[$column['key']] ?? null;
            $hasValue = ! ($normalizedValue === null || (is_string($normalizedValue) && trim($normalizedValue) === ''));

            if ($column['is_required'] && ! $hasValue) {
                $this->throwRowValidationError($rowNumber, __('ui.directories.validation_value_required', [
                    'field' => $column['label'],
                ]));
            }

            if (! $hasValue) {
                continue;
            }

            if ($column['type'] === ReferenceDirectory::FIELD_TYPE_NUMBER && ! is_numeric($rawValue)) {
                $this->throwRowValidationError($rowNumber, __('ui.directories.validation_value_number', [
                    'field' => $column['label'],
                ]));
            }

            if ($column['type'] === ReferenceDirectory::FIELD_TYPE_DATE && strtotime($rawValue) === false) {
                $this->throwRowValidationError($rowNumber, __('ui.directories.validation_value_date', [
                    'field' => $column['label'],
                ]));
            }

            if ($column['type'] === ReferenceDirectory::FIELD_TYPE_BOOLEAN && ! $this->isBooleanLike($rawValue)) {
                $this->throwRowValidationError($rowNumber, __('ui.directories.validation_value_boolean', [
                    'field' => $column['label'],
                ]));
            }
        }
    }

    private function throwRowValidationError(int $rowNumber, string $message): never
    {
        throw ValidationException::withMessages([
            'file' => __('ui.directories.csv_import_row_error', [
                'row' => $rowNumber,
                'message' => $message,
            ]),
        ]);
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        return collect($row)->every(fn (mixed $value): bool => ! is_string($value) || trim($value) === '');
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<int, bool|float|int|string|null>
     */
    private function recordRow(array $headers, ReferenceDirectoryRecord $record): array
    {
        return collect($headers)
            ->map(fn (string $header): bool|float|int|string|null => $this->exportValue($record->values[$header] ?? null))
            ->values()
            ->all();
    }

    private function exportValue(mixed $value): bool|float|int|string|null
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return $value;
    }

    /**
     * @param  array{key: string, label: string, type: string, is_required: bool}  $column
     */
    private function sampleValueForColumn(array $column): string
    {
        return match ($column['type']) {
            ReferenceDirectory::FIELD_TYPE_NUMBER => '123',
            ReferenceDirectory::FIELD_TYPE_DATE => '2026-01-15',
            ReferenceDirectory::FIELD_TYPE_BOOLEAN => 'true',
            default => __('ui.directories.csv_template_sample_value'),
        };
    }

    private function normalizeHeaderValue(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim((string) preg_replace('/^\xEF\xBB\xBF/u', '', $value));
    }

    private function isBooleanLike(mixed $value): bool
    {
        if (is_bool($value)) {
            return true;
        }

        if (is_numeric($value)) {
            return in_array((int) $value, [0, 1], true);
        }

        if (! is_string($value)) {
            return false;
        }

        return in_array(Str::lower(trim($value)), ['0', '1', 'true', 'false', 'yes', 'no', 'on', 'off'], true);
    }
}

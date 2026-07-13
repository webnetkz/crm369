<?php

namespace App\Support;

use App\Models\EquipmentItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquipmentCsvService
{
    /**
     * @var array<int, string>
     */
    private const array HEADERS = [
        'name',
        'qr_code',
        'status',
        'responsible_user_email',
        'issued_to_user_email',
    ];

    /**
     * @param  Collection<int, EquipmentItem>  $items
     */
    public function download(Collection $items, string $fileName, string $delimiter = ';'): StreamedResponse
    {
        return response()->streamDownload(function () use ($items, $delimiter): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, self::HEADERS, $delimiter);

            foreach ($items as $item) {
                fputcsv($output, [
                    $item->name,
                    $item->qr_code,
                    $item->status,
                    $item->responsibleUser?->email ?? '',
                    $item->issuedToUser?->email ?? '',
                ], $delimiter);
            }

            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function downloadTemplate(string $fileName, string $delimiter = ';'): StreamedResponse
    {
        return response()->streamDownload(function () use ($delimiter): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, self::HEADERS, $delimiter);
            fputcsv($output, [
                'Lenovo ThinkPad X1',
                'EQ-LENOVO-001',
                EquipmentItem::STATUS_ON_BALANCE,
                'responsible@example.com',
                '',
            ], $delimiter);
            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(UploadedFile $file, User $actor, string $delimiter = ';'): int
    {
        $rows = $this->parseRows($file, $delimiter);

        foreach ($rows as $row) {
            $equipmentItem = $row['qr_code'] !== null
                ? EquipmentItem::query()->firstOrNew(['qr_code' => $row['qr_code']])
                : new EquipmentItem;

            $equipmentItem->forceFill([
                'name' => $row['name'],
                'qr_code' => $row['qr_code'] ?? EquipmentItem::generateQrCode(),
                'status' => $row['status'],
                'responsible_user_id' => $row['responsible_user_id'],
                'issued_to_user_id' => $row['issued_to_user_id'],
                'created_by_user_id' => $equipmentItem->exists ? $equipmentItem->created_by_user_id : $actor->id,
                'updated_by_user_id' => $actor->id,
            ])->save();
        }

        return count($rows);
    }

    /**
     * @return array<int, array{
     *     name: string,
     *     qr_code: ?string,
     *     status: string,
     *     responsible_user_id: int|null,
     *     issued_to_user_id: int|null
     * }>
     */
    private function parseRows(UploadedFile $file, string $delimiter): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => __('ui.equipment.csv_import_invalid_file'),
            ]);
        }

        $header = fgetcsv($handle, 0, $delimiter);

        if ($header === false) {
            fclose($handle);

            throw ValidationException::withMessages([
                'file' => __('ui.equipment.csv_import_empty'),
            ]);
        }

        $normalizedHeader = array_map(fn (mixed $value): string => $this->normalizeHeaderValue($value), $header);
        $missingHeaders = array_values(array_diff(self::HEADERS, $normalizedHeader));

        if ($missingHeaders !== []) {
            fclose($handle);

            throw ValidationException::withMessages([
                'file' => __('ui.equipment.csv_import_missing_headers', ['columns' => implode(', ', $missingHeaders)]),
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

            foreach ($normalizedHeader as $index => $column) {
                $mappedRow[$column] = isset($row[$index]) && is_string($row[$index])
                    ? trim($row[$index])
                    : '';
            }

            $rows[] = $this->validateRow($mappedRow, $rowNumber);
        }

        fclose($handle);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => __('ui.equipment.csv_import_empty'),
            ]);
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @return array{
     *     name: string,
     *     qr_code: ?string,
     *     status: string,
     *     responsible_user_id: int|null,
     *     issued_to_user_id: int|null
     * }
     */
    private function validateRow(array $row, int $rowNumber): array
    {
        $name = trim($row['name']);

        if ($name === '') {
            $this->throwRowError($rowNumber, __('validation.required', ['attribute' => 'name']));
        }

        $status = trim($row['status']) !== '' ? trim($row['status']) : EquipmentItem::STATUS_ON_BALANCE;

        if (! in_array($status, EquipmentItem::availableStatuses(), true)) {
            $this->throwRowError($rowNumber, __('validation.in', ['attribute' => 'status']));
        }

        $responsibleUserId = $this->resolveUserId($row['responsible_user_email'], $rowNumber, 'responsible_user_email');
        $issuedToUserId = $this->resolveUserId($row['issued_to_user_email'], $rowNumber, 'issued_to_user_email');

        if ($status === EquipmentItem::STATUS_ISSUED && $issuedToUserId === null) {
            $this->throwRowError($rowNumber, __('ui.equipment.validation_issued_to_user_required'));
        }

        if ($status !== EquipmentItem::STATUS_ISSUED && $issuedToUserId !== null) {
            $this->throwRowError($rowNumber, __('ui.equipment.validation_issued_to_user_forbidden'));
        }

        $qrCode = trim($row['qr_code']);

        return [
            'name' => Str::limit($name, 255, ''),
            'qr_code' => $qrCode !== '' ? Str::limit($qrCode, 64, '') : null,
            'status' => $status,
            'responsible_user_id' => $responsibleUserId,
            'issued_to_user_id' => $issuedToUserId,
        ];
    }

    private function resolveUserId(string $email, int $rowNumber, string $field): ?int
    {
        $normalized = Str::lower(trim($email));

        if ($normalized === '') {
            return null;
        }

        $userId = User::query()
            ->where('email', $normalized)
            ->value('id');

        if (! is_int($userId)) {
            $this->throwRowError($rowNumber, __('ui.equipment.csv_import_unknown_user', ['field' => $field, 'email' => $normalized]));
        }

        return $userId;
    }

    private function normalizeHeaderValue(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return Str::of($value)
            ->replace("\xEF\xBB\xBF", '')
            ->trim()
            ->lower()
            ->value();
    }

    /**
     * @param  array<int, mixed>  $row
     */
    private function rowIsEmpty(array $row): bool
    {
        return collect($row)->every(fn (mixed $value): bool => trim((string) $value) === '');
    }

    private function throwRowError(int $rowNumber, string $message): never
    {
        throw ValidationException::withMessages([
            'file' => __('ui.equipment.csv_import_row_error', [
                'row' => $rowNumber,
                'message' => $message,
            ]),
        ]);
    }
}

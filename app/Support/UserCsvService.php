<?php

namespace App\Support;

use App\Models\PortalSetting;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserCsvService
{
    /**
     * @var array<int, string>
     */
    private const array HEADERS = [
        'name',
        'last_name',
        'middle_name',
        'email',
        'phone',
        'position',
        'manager_email',
        'group_name',
        'is_active',
        'email_verified',
    ];

    /**
     * @param  Collection<int, User>  $users
     */
    public function download(Collection $users, string $fileName, string $delimiter = ';'): StreamedResponse
    {
        return response()->streamDownload(function () use ($users, $delimiter): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, self::HEADERS, $delimiter);

            foreach ($users as $user) {
                fputcsv($output, [
                    $user->name,
                    $user->last_name ?? '',
                    $user->middle_name ?? '',
                    $user->email,
                    $user->phone ?? '',
                    $user->position ?? '',
                    $user->manager?->email ?? '',
                    $user->group?->name ?? '',
                    $user->is_active ? 'true' : 'false',
                    $user->email_verified_at !== null ? 'true' : 'false',
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
                'Aruzhan',
                'Sarsenova',
                '',
                'aruzhan@example.com',
                '+77011234567',
                'Manager',
                '',
                '',
                'true',
                'true',
            ], $delimiter);
            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(UploadedFile $file, string $delimiter = ';'): int
    {
        $rows = $this->parseRows($file, $delimiter);

        return DB::transaction(function () use ($rows): int {
            foreach ($rows as $row) {
                $user = User::query()->firstOrNew([
                    'email' => $row['email'],
                ]);

                if (! $user->exists) {
                    $user->forceFill([
                        'language' => PortalSetting::current()->defaultLanguage(),
                        'has_selected_language' => false,
                        'password' => Str::password(24),
                    ]);
                }

                $user->forceFill([
                    'name' => $row['name'],
                    'last_name' => $row['last_name'],
                    'middle_name' => $row['middle_name'],
                    'phone' => $row['phone'],
                    'position' => $row['position'],
                    'manager_id' => $row['manager_id'],
                    'user_group_id' => $row['group_id'],
                    'is_active' => $row['is_active'],
                    'deactivated_at' => $row['is_active'] ? null : now(),
                    'email_verified_at' => $row['email_verified'] ? ($user->email_verified_at ?? now()) : null,
                ])->save();
            }

            return count($rows);
        });
    }

    /**
     * @return array<int, array{
     *     name: string,
     *     last_name: ?string,
     *     middle_name: ?string,
     *     email: string,
     *     phone: ?string,
     *     position: ?string,
     *     manager_id: int|null,
     *     group_id: int|null,
     *     is_active: bool,
     *     email_verified: bool
     * }>
     */
    private function parseRows(UploadedFile $file, string $delimiter): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => __('ui.admin.csv_import_invalid_file'),
            ]);
        }

        $header = fgetcsv($handle, 0, $delimiter);

        if ($header === false) {
            fclose($handle);

            throw ValidationException::withMessages([
                'file' => __('ui.admin.csv_import_empty'),
            ]);
        }

        $normalizedHeader = array_map(fn (mixed $value): string => $this->normalizeHeaderValue($value), $header);
        $missingHeaders = array_values(array_diff(self::HEADERS, $normalizedHeader));

        if ($missingHeaders !== []) {
            fclose($handle);

            throw ValidationException::withMessages([
                'file' => __('ui.admin.csv_import_missing_headers', ['columns' => implode(', ', $missingHeaders)]),
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
                'file' => __('ui.admin.csv_import_empty'),
            ]);
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @return array{
     *     name: string,
     *     last_name: ?string,
     *     middle_name: ?string,
     *     email: string,
     *     phone: ?string,
     *     position: ?string,
     *     manager_id: int|null,
     *     group_id: int|null,
     *     is_active: bool,
     *     email_verified: bool
     * }
     */
    private function validateRow(array $row, int $rowNumber): array
    {
        $name = trim($row['name']);
        $email = Str::lower(trim($row['email']));

        if ($name === '') {
            $this->throwRowError($rowNumber, __('validation.required', ['attribute' => 'name']));
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->throwRowError($rowNumber, __('validation.email', ['attribute' => 'email']));
        }

        $managerEmail = Str::lower(trim($row['manager_email']));
        $managerId = null;

        if ($managerEmail !== '') {
            if ($managerEmail === $email) {
                $this->throwRowError($rowNumber, __('ui.admin.manager_cycle_error'));
            }

            $managerId = User::query()
                ->where('email', $managerEmail)
                ->value('id');

            if (! is_int($managerId)) {
                $this->throwRowError($rowNumber, __('ui.admin.csv_import_unknown_manager', ['email' => $managerEmail]));
            }
        }

        $groupName = trim($row['group_name']);
        $groupId = null;

        if ($groupName !== '') {
            $groupId = UserGroup::query()
                ->where('name', $groupName)
                ->value('id');

            if (! is_int($groupId)) {
                $this->throwRowError($rowNumber, __('ui.admin.csv_import_unknown_group', ['group' => $groupName]));
            }
        }

        return [
            'name' => Str::limit($name, 255, ''),
            'last_name' => $this->nullableString($row['last_name']),
            'middle_name' => $this->nullableString($row['middle_name']),
            'email' => $email,
            'phone' => $this->normalizePhone($row['phone']),
            'position' => $this->nullableString($row['position']),
            'manager_id' => $managerId,
            'group_id' => $groupId,
            'is_active' => $this->parseBoolean($row['is_active'], $rowNumber, 'is_active'),
            'email_verified' => $this->parseBoolean($row['email_verified'], $rowNumber, 'email_verified'),
        ];
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

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== ''
            ? Str::limit(trim($value), 255, '')
            : null;
    }

    private function normalizePhone(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return null;
        }

        if ($digits[0] === '8') {
            $digits = '7'.substr($digits, 1);
        } elseif ($digits[0] !== '7') {
            $digits = '7'.$digits;
        }

        $digits = substr($digits, 0, 11);

        return strlen($digits) === 11 ? '+'.$digits : null;
    }

    private function parseBoolean(string $value, int $rowNumber, string $field): bool
    {
        $normalized = Str::lower(trim($value));

        return match ($normalized) {
            '', '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => $this->throwBooleanRowError($rowNumber, $field),
        };
    }

    private function throwBooleanRowError(int $rowNumber, string $field): never
    {
        $this->throwRowError($rowNumber, __('validation.boolean', ['attribute' => $field]));
    }

    private function throwRowError(int $rowNumber, string $message): never
    {
        throw ValidationException::withMessages([
            'file' => __('ui.admin.csv_import_row_error', [
                'row' => $rowNumber,
                'message' => $message,
            ]),
        ]);
    }
}

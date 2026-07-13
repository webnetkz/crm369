<?php

namespace App\Support;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactCsvService
{
    /**
     * @var array<int, string>
     */
    private const array HEADERS = [
        'type',
        'name',
        'contact_person',
        'position',
        'email',
        'phone',
        'is_blacklisted',
        'notes',
        'iin',
        'bin',
        'legal_address',
        'actual_address',
        'bank_name',
        'bank_bik',
        'iban',
        'kbe',
    ];

    /**
     * @param  Collection<int, Contact>  $contacts
     */
    public function download(Collection $contacts, string $fileName, string $delimiter = ';'): StreamedResponse
    {
        return response()->streamDownload(function () use ($contacts, $delimiter): void {
            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, self::HEADERS, $delimiter);

            foreach ($contacts as $contact) {
                $requisites = is_array($contact->company_requisites) ? $contact->company_requisites : [];

                fputcsv($output, [
                    $contact->type,
                    $contact->name,
                    $contact->contact_person ?? '',
                    $contact->position ?? '',
                    $contact->email ?? '',
                    $contact->phone ?? '',
                    $contact->is_blacklisted ? 'true' : 'false',
                    $contact->notes ?? '',
                    $requisites['iin'] ?? '',
                    $requisites['bin'] ?? '',
                    $requisites['legal_address'] ?? '',
                    $requisites['actual_address'] ?? '',
                    $requisites['bank_name'] ?? '',
                    $requisites['bank_bik'] ?? '',
                    $requisites['iban'] ?? '',
                    $requisites['kbe'] ?? '',
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
                Contact::TYPE_COMPANY,
                'Example LLC',
                'Dana Manager',
                'Director',
                'company@example.com',
                '+77011234567',
                'false',
                'Main partner',
                '',
                '123456789012',
                'Almaty, Abay 10',
                'Almaty, Satpayev 11',
                'Kaspi Bank',
                'CASPKZKA',
                'KZ123456789012345678',
                '17',
            ], $delimiter);
            fclose($output);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function import(UploadedFile $file, User $actor, string $delimiter = ';'): int
    {
        $rows = $this->parseRows($file, $actor, $delimiter);

        foreach ($rows as $row) {
            Contact::query()->create([
                ...$row,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);
        }

        return count($rows);
    }

    /**
     * @return array<int, array{
     *     type: string,
     *     name: string,
     *     contact_person: ?string,
     *     position: ?string,
     *     email: ?string,
     *     phone: ?string,
     *     notes: ?string,
     *     is_blacklisted: bool,
     *     company_requisites: array<string, string|null>|null
     * }>
     */
    private function parseRows(UploadedFile $file, User $actor, string $delimiter): array
    {
        $handle = fopen($file->getRealPath(), 'rb');

        if ($handle === false) {
            throw ValidationException::withMessages([
                'file' => __('ui.contacts.csv_import_invalid_file'),
            ]);
        }

        $header = fgetcsv($handle, 0, $delimiter);

        if ($header === false) {
            fclose($handle);

            throw ValidationException::withMessages([
                'file' => __('ui.contacts.csv_import_empty'),
            ]);
        }

        $normalizedHeader = array_map(fn (mixed $value): string => $this->normalizeHeaderValue($value), $header);
        $missingHeaders = array_values(array_diff(self::HEADERS, $normalizedHeader));

        if ($missingHeaders !== []) {
            fclose($handle);

            throw ValidationException::withMessages([
                'file' => __('ui.contacts.csv_import_missing_headers', ['columns' => implode(', ', $missingHeaders)]),
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

            $rows[] = $this->validateRow($mappedRow, $actor, $rowNumber);
        }

        fclose($handle);

        if ($rows === []) {
            throw ValidationException::withMessages([
                'file' => __('ui.contacts.csv_import_empty'),
            ]);
        }

        return $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @return array{
     *     type: string,
     *     name: string,
     *     contact_person: ?string,
     *     position: ?string,
     *     email: ?string,
     *     phone: ?string,
     *     notes: ?string,
     *     is_blacklisted: bool,
     *     company_requisites: array<string, string|null>|null
     * }
     */
    private function validateRow(array $row, User $actor, int $rowNumber): array
    {
        $type = trim($row['type']);

        if (! in_array($type, Contact::availableTypes(), true)) {
            $this->throwRowError($rowNumber, __('validation.in', ['attribute' => 'type']));
        }

        if (! $actor->canAccessContactType($type)) {
            $this->throwRowError($rowNumber, __('auth.forbidden'));
        }

        $name = trim($row['name']);

        if ($name === '') {
            $this->throwRowError($rowNumber, __('validation.required', ['attribute' => 'name']));
        }

        $email = $this->nullableString($row['email']);

        if ($email !== null && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->throwRowError($rowNumber, __('validation.email', ['attribute' => 'email']));
        }

        $companyRequisites = [
            'iin' => $type === Contact::TYPE_PERSON ? $this->nullableString($row['iin']) : null,
            'bin' => $type === Contact::TYPE_COMPANY ? $this->nullableString($row['bin']) : null,
            'legal_address' => $type === Contact::TYPE_COMPANY ? $this->nullableString($row['legal_address'], 500) : null,
            'actual_address' => $type === Contact::TYPE_COMPANY ? $this->nullableString($row['actual_address'], 500) : null,
            'bank_name' => $type === Contact::TYPE_COMPANY ? $this->nullableString($row['bank_name']) : null,
            'bank_bik' => $type === Contact::TYPE_COMPANY ? $this->nullableString($row['bank_bik'], 32) : null,
            'iban' => $type === Contact::TYPE_COMPANY ? $this->nullableString($row['iban'], 34) : null,
            'kbe' => $type === Contact::TYPE_COMPANY ? $this->nullableString($row['kbe'], 16) : null,
        ];

        if ($companyRequisites['iin'] !== null && ! preg_match('/^\d{12}$/', $companyRequisites['iin'])) {
            $this->throwRowError($rowNumber, __('ui.contacts.iin_validation'));
        }

        if ($companyRequisites['bin'] !== null && ! preg_match('/^\d{12}$/', $companyRequisites['bin'])) {
            $this->throwRowError($rowNumber, __('ui.contacts.bin_validation'));
        }

        if (
            $companyRequisites['iin'] !== null
            && Contact::query()
                ->where('type', Contact::TYPE_PERSON)
                ->where('company_requisites->iin', $companyRequisites['iin'])
                ->exists()
        ) {
            $this->throwRowError($rowNumber, __('ui.contacts.iin_unique'));
        }

        if (
            $companyRequisites['bin'] !== null
            && Contact::query()
                ->where('type', Contact::TYPE_COMPANY)
                ->where('company_requisites->bin', $companyRequisites['bin'])
                ->exists()
        ) {
            $this->throwRowError($rowNumber, __('ui.contacts.bin_unique'));
        }

        return [
            'type' => $type,
            'name' => Str::limit($name, 255, ''),
            'contact_person' => $this->nullableString($row['contact_person']),
            'position' => $this->nullableString($row['position']),
            'email' => $email,
            'phone' => $this->nullableString($row['phone']),
            'notes' => $this->nullableString($row['notes'], 10000),
            'is_blacklisted' => $this->parseBoolean($row['is_blacklisted'], $rowNumber),
            'company_requisites' => collect($companyRequisites)->filter(fn (mixed $value): bool => $value !== null)->isEmpty()
                ? null
                : $companyRequisites,
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

    private function nullableString(mixed $value, int $limit = 255): ?string
    {
        return is_string($value) && trim($value) !== ''
            ? Str::limit(trim($value), $limit, '')
            : null;
    }

    private function parseBoolean(string $value, int $rowNumber): bool
    {
        $normalized = Str::lower(trim($value));

        return match ($normalized) {
            '', '0', 'false', 'no', 'off' => false,
            '1', 'true', 'yes', 'on' => true,
            default => $this->throwBooleanRowError($rowNumber),
        };
    }

    private function throwBooleanRowError(int $rowNumber): never
    {
        $this->throwRowError($rowNumber, __('validation.boolean', ['attribute' => 'is_blacklisted']));
    }

    private function throwRowError(int $rowNumber, string $message): never
    {
        throw ValidationException::withMessages([
            'file' => __('ui.contacts.csv_import_row_error', [
                'row' => $rowNumber,
                'message' => $message,
            ]),
        ]);
    }
}

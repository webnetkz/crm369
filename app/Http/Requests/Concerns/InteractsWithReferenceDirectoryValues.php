<?php

namespace App\Http\Requests\Concerns;

use App\Models\ReferenceDirectory;
use App\Models\ReferenceDirectoryRecord;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

trait InteractsWithReferenceDirectoryValues
{
    protected function normalizeValuesForValidation(): array
    {
        return $this->resolvedReferenceDirectory()?->normalizedRecordValues($this->input('values')) ?? [];
    }

    /**
     * @return array<string, bool|float|int|string|null>
     */
    protected function recordValuesPayload(): array
    {
        return $this->resolvedReferenceDirectory()?->normalizedRecordValues($this->validated('values', [])) ?? [];
    }

    protected function resolvedReferenceDirectory(): ?ReferenceDirectory
    {
        $directory = $this->route('referenceDirectory');

        if ($directory instanceof ReferenceDirectory) {
            return $directory;
        }

        $record = $this->resolvedReferenceDirectoryRecord();

        return $record?->relationLoaded('directory') ? $record->directory : $record?->directory()->first();
    }

    protected function resolvedReferenceDirectoryRecord(): ?ReferenceDirectoryRecord
    {
        $record = $this->route('referenceDirectoryRecord');

        return $record instanceof ReferenceDirectoryRecord ? $record : null;
    }

    protected function validateReferenceDirectoryValues(Validator $validator): void
    {
        $directory = $this->resolvedReferenceDirectory();

        if (! $directory) {
            return;
        }

        $values = $this->validated('values', []);

        foreach ($directory->columnDefinitions() as $column) {
            $value = $values[$column['key']] ?? null;
            $hasValue = ! ($value === null || (is_string($value) && trim($value) === ''));

            if ($column['is_required'] && ! $hasValue) {
                $validator->errors()->add(
                    "values.{$column['key']}",
                    __('ui.directories.validation_value_required', ['field' => $column['label']]),
                );

                continue;
            }

            if (! $hasValue) {
                continue;
            }

            if ($column['type'] === ReferenceDirectory::FIELD_TYPE_NUMBER && ! is_numeric($value)) {
                $validator->errors()->add(
                    "values.{$column['key']}",
                    __('ui.directories.validation_value_number', ['field' => $column['label']]),
                );
            }

            if ($column['type'] === ReferenceDirectory::FIELD_TYPE_DATE && ! $this->isDateString($value)) {
                $validator->errors()->add(
                    "values.{$column['key']}",
                    __('ui.directories.validation_value_date', ['field' => $column['label']]),
                );
            }

            if ($column['type'] === ReferenceDirectory::FIELD_TYPE_BOOLEAN && ! $this->isBooleanLike($value)) {
                $validator->errors()->add(
                    "values.{$column['key']}",
                    __('ui.directories.validation_value_boolean', ['field' => $column['label']]),
                );
            }
        }
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

    private function isDateString(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        return strtotime($value) !== false;
    }
}

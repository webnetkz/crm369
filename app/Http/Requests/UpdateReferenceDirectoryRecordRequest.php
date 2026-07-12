<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\InteractsWithReferenceDirectoryValues;
use App\Models\PortalWebhook;
use App\Models\ReferenceDirectory;
use App\Models\ReferenceDirectoryRecord;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateReferenceDirectoryRecordRequest extends FormRequest
{
    use InteractsWithReferenceDirectoryValues;

    public function authorize(): bool
    {
        if ($this->route('portalWebhook') instanceof PortalWebhook) {
            return $this->route('referenceDirectory') instanceof ReferenceDirectory
                && $this->route('referenceDirectoryRecord') instanceof ReferenceDirectoryRecord;
        }

        return ($this->route('referenceDirectory') instanceof ReferenceDirectory)
            && ($this->route('referenceDirectoryRecord') instanceof ReferenceDirectoryRecord)
            && ($this->user()?->canManageDirectories() ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'values' => $this->normalizeValuesForValidation(),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'values' => ['nullable', 'array'],
        ];
    }

    public function after(): array
    {
        return [fn (Validator $validator): mixed => $this->validateReferenceDirectoryValues($validator)];
    }

    /**
     * @return array{values: array<string, bool|float|int|string|null>}
     */
    public function recordPayload(): array
    {
        return [
            'values' => $this->recordValuesPayload(),
        ];
    }
}

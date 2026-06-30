<?php

namespace App\Http\Requests;

use App\Models\FileDirectory;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFileDirectoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', Rule::exists(FileDirectory::class, 'id')],
            'name' => [
                'required',
                'string',
                'max:255',
                'regex:/^[^\/\\\\]+$/',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }

                    $query = FileDirectory::query()
                        ->when(
                            $this->integer('parent_id') > 0,
                            fn ($directoryQuery) => $directoryQuery->where('parent_id', $this->integer('parent_id')),
                            fn ($directoryQuery) => $directoryQuery->whereNull('parent_id'),
                        )
                        ->whereRaw('lower(name) = ?', [mb_strtolower(trim($value))]);

                    if ($query->exists()) {
                        $fail(__('validation.unique', ['attribute' => $attribute]));
                    }
                },
            ],
        ];
    }

    public function parentDirectory(): ?FileDirectory
    {
        $parentId = $this->validated('parent_id');

        if (! is_numeric($parentId)) {
            return null;
        }

        return FileDirectory::query()->find((int) $parentId);
    }

    public function directoryName(): string
    {
        return trim((string) $this->validated('name'));
    }
}

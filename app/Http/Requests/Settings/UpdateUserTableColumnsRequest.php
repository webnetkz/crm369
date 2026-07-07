<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class UpdateUserTableColumnsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canViewUsers() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'visible_columns' => ['present', 'array'],
            'visible_columns.*' => [
                'string',
                Rule::in(User::VISIBLE_USER_TABLE_OPTIONAL_COLUMNS),
            ],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function visibleColumns(): array
    {
        /** @var Collection<int, string> $allowedColumns */
        $allowedColumns = collect(User::VISIBLE_USER_TABLE_OPTIONAL_COLUMNS);

        return $allowedColumns
            ->filter(fn (string $column): bool => in_array($column, $this->validated('visible_columns', []), true))
            ->values()
            ->all();
    }
}

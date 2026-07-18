<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class TestOneCConnectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-one-c') ?? false;
    }

    /**
     * @return array<string, never>
     */
    public function rules(): array
    {
        return [];
    }
}

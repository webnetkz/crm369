<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class TwoFactorChallengeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'challenge' => is_string($this->input('challenge')) ? trim($this->input('challenge')) : $this->input('challenge'),
            'code' => $this->nullableString('code'),
            'recovery_code' => $this->nullableString('recovery_code'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'challenge' => ['required', 'string', 'size:80'],
            'code' => ['nullable', 'required_without:recovery_code', 'string', 'size:6'],
            'recovery_code' => ['nullable', 'required_without:code', 'string', 'max:100'],
        ];
    }

    public function challenge(): string
    {
        return (string) $this->validated('challenge');
    }

    public function code(): ?string
    {
        return $this->validated('code');
    }

    public function recoveryCode(): ?string
    {
        return $this->validated('recovery_code');
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}

<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => is_string($this->input('email')) ? mb_strtolower(trim($this->input('email'))) : $this->input('email'),
            'device_id' => is_string($this->input('device_id')) ? trim($this->input('device_id')) : $this->input('device_id'),
            'device_name' => $this->nullableString('device_name'),
            'app_version' => $this->nullableString('app_version'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:4096'],
            'device_id' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._:-]+$/'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'app_version' => ['nullable', 'string', 'max:40'],
        ];
    }

    public function email(): string
    {
        return (string) $this->validated('email');
    }

    public function password(): string
    {
        return (string) $this->validated('password');
    }

    public function deviceId(): string
    {
        return (string) $this->validated('device_id');
    }

    /**
     * @return array{device_id: string, device_name: string|null, app_version: string|null}
     */
    public function deviceContext(): array
    {
        return [
            'device_id' => $this->deviceId(),
            'device_name' => $this->validated('device_name'),
            'app_version' => $this->validated('app_version'),
        ];
    }

    private function nullableString(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}

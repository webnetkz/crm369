<?php

namespace App\Http\Requests;

use App\Models\PortalWebhook;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreTsdQrScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        if ($this->route('portalWebhook') instanceof PortalWebhook) {
            return true;
        }

        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'qr_code' => $this->normalizeRequiredString($this->input('qr_code')),
            'device_name' => $this->normalizeNullableString($this->input('device_name')),
            'location' => $this->normalizeNullableString($this->input('location')),
            'context' => $this->normalizeNullableString($this->input('context')),
            'payload' => is_array($this->input('payload')) ? $this->input('payload') : null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'qr_code' => ['required', 'string', 'max:2048'],
            'device_name' => ['nullable', 'string', 'max:120'],
            'location' => ['nullable', 'string', 'max:120'],
            'context' => ['nullable', 'string', 'max:255'],
            'payload' => ['nullable', 'array'],
            'scanned_at' => ['nullable', 'date'],
            'source' => ['sometimes', Rule::in(['web', 'api', 'webhook'])],
        ];
    }

    /**
     * @return array{
     *     qr_code: string,
     *     device_name: ?string,
     *     location: ?string,
     *     context: ?string,
     *     payload: array<string, mixed>|null,
     *     scanned_at: string|null
     * }
     */
    public function scanPayload(): array
    {
        return [
            'qr_code' => $this->validated('qr_code'),
            'device_name' => $this->validated('device_name'),
            'location' => $this->validated('location'),
            'context' => $this->validated('context'),
            'payload' => $this->validated('payload'),
            'scanned_at' => $this->validated('scanned_at'),
        ];
    }

    public function scannedAt(): ?Carbon
    {
        $scannedAt = $this->validated('scanned_at');

        if (! is_string($scannedAt) || trim($scannedAt) === '') {
            return null;
        }

        return Carbon::parse($scannedAt);
    }

    private function normalizeRequiredString(mixed $value): mixed
    {
        return is_string($value) ? trim($value) : $value;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}

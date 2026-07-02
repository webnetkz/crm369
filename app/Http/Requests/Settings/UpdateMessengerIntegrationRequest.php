<?php

namespace App\Http\Requests\Settings;

use App\Models\MessengerIntegration;
use App\Models\MessengerIntegrationGroupAccess;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMessengerIntegrationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $settings = $this->input('settings');

        if (! is_array($settings)) {
            return;
        }

        $this->merge([
            'settings' => collect($settings)
                ->map(function (mixed $value): mixed {
                    if (! is_string($value)) {
                        return $value;
                    }

                    $trimmed = trim($value);

                    return $trimmed !== '' ? $trimmed : null;
                })
                ->all(),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function telephonyResponsibleModes(): array
    {
        return [
            'call_receiver',
            'last_contact_owner',
            'round_robin_queue',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function telephonyMissedCallModes(): array
    {
        return [
            'notify_only',
            'create_activity',
            'create_contact_and_activity',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function telephonyRecordingModes(): array
    {
        return [
            'disabled',
            'incoming_only',
            'outgoing_only',
            'all_calls',
        ];
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('manage-messenger-integrations') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'settings' => ['sometimes', 'array'],
            'settings.api_url' => ['nullable', 'string', 'max:255'],
            'settings.channel_id' => ['nullable', 'string', 'max:255'],
            'settings.phone_number' => ['nullable', 'string', 'max:255'],
            'settings.api_token' => ['nullable', 'string', 'max:2048'],
            'settings.provider_name' => ['nullable', 'string', 'max:255'],
            'settings.account_id' => ['nullable', 'string', 'max:255'],
            'settings.extension_number' => ['nullable', 'string', 'max:255'],
            'settings.bot_username' => ['nullable', 'string', 'max:255'],
            'settings.bot_token' => ['nullable', 'string', 'max:2048'],
            'settings.webhook_secret' => ['nullable', 'string', 'max:2048'],
            'settings.webhook_url' => ['nullable', 'string', 'max:2048'],
            'settings.default_line' => ['nullable', 'string', 'max:255'],
            'settings.outbound_caller_id' => ['nullable', 'string', 'max:255'],
            'settings.responsible_mode' => ['nullable', 'string', Rule::in($this->telephonyResponsibleModes())],
            'settings.missed_call_mode' => ['nullable', 'string', Rule::in($this->telephonyMissedCallModes())],
            'settings.recording_mode' => ['nullable', 'string', Rule::in($this->telephonyRecordingModes())],
            'settings.create_contact_for_unknown_calls' => ['sometimes', 'boolean'],
            'settings.create_activity_for_missed_calls' => ['sometimes', 'boolean'],
            'settings.log_incoming_calls' => ['sometimes', 'boolean'],
            'settings.log_outgoing_calls' => ['sometimes', 'boolean'],
            'group_accesses' => ['required', 'array'],
            'group_accesses.*.user_group_id' => ['required', 'integer', Rule::exists('user_groups', 'id')],
            'group_accesses.*.access_level' => ['required', 'string', Rule::in(MessengerIntegrationGroupAccess::assignableAccessLevels())],
        ];
    }

    /**
     * @return array<string, bool|string|null>
     */
    public function settings(MessengerIntegration $messengerIntegration): array
    {
        $settings = collect($this->validated('settings', []));

        return collect(array_keys(MessengerIntegration::defaultSettingsForDriver($messengerIntegration->driver)))
            ->mapWithKeys(function (string $key) use ($settings, $messengerIntegration): array {
                $value = $settings->get($key);
                $defaultValue = MessengerIntegration::defaultSettingsForDriver($messengerIntegration->driver)[$key] ?? null;

                if (is_bool($defaultValue)) {
                    return [$key => (bool) $value];
                }

                $trimmed = is_string($value) ? trim($value) : null;

                return [$key => $trimmed !== '' ? $trimmed : null];
            })
            ->filter(fn (mixed $value): bool => $value !== null && $value !== false)
            ->all();
    }

    /**
     * @return array<int, array{user_group_id: int, access_level: string}>
     */
    public function groupAccesses(): array
    {
        return collect($this->validated('group_accesses', []))
            ->map(function (mixed $row): ?array {
                if (! is_array($row)) {
                    return null;
                }

                $userGroupId = (int) ($row['user_group_id'] ?? 0);
                $accessLevel = $row['access_level'] ?? null;

                if ($userGroupId <= 0 || ! is_string($accessLevel)) {
                    return null;
                }

                return [
                    'user_group_id' => $userGroupId,
                    'access_level' => $accessLevel,
                ];
            })
            ->filter()
            ->unique(fn (array $row): int => $row['user_group_id'])
            ->values()
            ->all();
    }
}

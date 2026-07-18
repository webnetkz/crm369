<?php

namespace App\Http\Requests\Settings;

use App\Models\OneCIntegration;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateOneCIntegrationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->trimmedString('name'),
            'base_url' => $this->nullableTrimmedString('base_url'),
            'api_path' => $this->normalizedApiPath(),
            'username' => $this->nullableTrimmedString('username'),
            'password' => $this->nullableTrimmedString('password'),
            'token' => $this->nullableTrimmedString('token'),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()?->can('manage-one-c') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'product' => ['required', 'string', Rule::in(OneCIntegration::products())],
            'transport' => ['required', 'string', Rule::in(OneCIntegration::transports())],
            'is_enabled' => ['required', 'boolean'],
            'base_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'api_path' => ['required', 'string', 'max:1024', 'starts_with:/'],
            'auth_type' => ['required', 'string', Rule::in(OneCIntegration::authTypes())],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:2048'],
            'token' => ['nullable', 'string', 'max:4096'],
            'verify_tls' => ['required', 'boolean'],
            'connect_timeout_seconds' => ['required', 'integer', 'min:1', 'max:30'],
            'request_timeout_seconds' => ['required', 'integer', 'min:5', 'max:300', 'gte:connect_timeout_seconds'],
            'import_enabled' => ['required', 'boolean'],
            'export_enabled' => ['required', 'boolean'],
            'schedule_enabled' => ['required', 'boolean'],
            'sync_interval_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'sync_window_start' => ['nullable', 'date_format:H:i'],
            'sync_window_end' => ['nullable', 'date_format:H:i'],
            'batch_size' => ['required', 'integer', 'min:10', 'max:1000'],
            'default_sync_mode' => ['required', 'string', Rule::in(OneCIntegration::syncModes())],
            'conflict_strategy' => ['required', 'string', Rule::in(OneCIntegration::conflictStrategies())],
            'stop_on_error' => ['required', 'boolean'],
            'dry_run' => ['required', 'boolean'],
            'entities' => ['required', 'array'],
            'entities.*.enabled' => ['required', 'boolean'],
            'entities.*.direction' => ['required', 'string', Rule::in(OneCIntegration::directions())],
            'entities.*.source_of_truth' => ['required', 'string', Rule::in(OneCIntegration::sourcesOfTruth())],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateBaseUrl($validator);
                $this->validateEntityDirections($validator);

                if (! $this->boolean('is_enabled')) {
                    return;
                }

                $this->validateEnabledConfiguration($validator);
            },
        ];
    }

    /**
     * @return array<string, array{enabled: bool, direction: string, source_of_truth: string}>
     */
    public function entities(): array
    {
        return OneCIntegration::normalizeEntities($this->validated('entities'));
    }

    private function validateBaseUrl(Validator $validator): void
    {
        $baseUrl = $this->input('base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            return;
        }

        if (parse_url($baseUrl, PHP_URL_USER) !== null || parse_url($baseUrl, PHP_URL_PASS) !== null) {
            $validator->errors()->add('base_url', __('ui.one_c.validation.credentials_in_url'));
        }
    }

    private function validateEntityDirections(Validator $validator): void
    {
        $entities = $this->input('entities');

        if (! is_array($entities)) {
            return;
        }

        foreach (OneCIntegration::entityDefinitions() as $key => $definition) {
            $direction = is_array($entities[$key] ?? null)
                ? ($entities[$key]['direction'] ?? null)
                : null;

            if (is_string($direction) && ! in_array($direction, $definition['directions'], true)) {
                $validator->errors()->add("entities.{$key}.direction", __('ui.one_c.validation.unsupported_direction'));
            }
        }
    }

    private function validateEnabledConfiguration(Validator $validator): void
    {
        if (! is_string($this->input('base_url')) || $this->input('base_url') === '') {
            $validator->errors()->add('base_url', __('ui.one_c.validation.base_url_required'));
        }

        if (! $this->boolean('import_enabled') && ! $this->boolean('export_enabled')) {
            $validator->errors()->add('import_enabled', __('ui.one_c.validation.exchange_direction_required'));
        }

        $entities = OneCIntegration::normalizeEntities($this->input('entities'));

        if (! collect($entities)->contains(fn (array $entity): bool => $entity['enabled'])) {
            $validator->errors()->add('entities', __('ui.one_c.validation.entity_required'));
        }

        $integration = $this->route('oneCIntegration');

        if (! $integration instanceof OneCIntegration) {
            return;
        }

        if ($this->input('auth_type') === OneCIntegration::AUTH_BASIC) {
            if (! is_string($this->input('username')) || $this->input('username') === '') {
                $validator->errors()->add('username', __('ui.one_c.validation.username_required'));
            }

            $hasPassword = is_string($this->input('password')) && $this->input('password') !== '';

            if (! $hasPassword && ($integration->auth_type !== OneCIntegration::AUTH_BASIC || ! $integration->password)) {
                $validator->errors()->add('password', __('ui.one_c.validation.password_required'));
            }
        }

        if ($this->input('auth_type') === OneCIntegration::AUTH_BEARER) {
            $hasToken = is_string($this->input('token')) && $this->input('token') !== '';

            if (! $hasToken && ($integration->auth_type !== OneCIntegration::AUTH_BEARER || ! $integration->token)) {
                $validator->errors()->add('token', __('ui.one_c.validation.token_required'));
            }
        }
    }

    private function trimmedString(string $key): mixed
    {
        $value = $this->input($key);

        return is_string($value) ? trim($value) : $value;
    }

    private function nullableTrimmedString(string $key): mixed
    {
        $value = $this->trimmedString($key);

        return $value === '' ? null : $value;
    }

    private function normalizedApiPath(): mixed
    {
        $path = $this->trimmedString('api_path');

        if (! is_string($path) || $path === '') {
            return $path;
        }

        return '/'.ltrim($path, '/');
    }
}

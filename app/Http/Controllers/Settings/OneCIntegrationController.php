<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreOneCIntegrationRequest;
use App\Http\Requests\Settings\TestOneCConnectionRequest;
use App\Http\Requests\Settings\UpdateOneCIntegrationRequest;
use App\Models\OneCIntegration;
use App\Support\OneCConnectionTester;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OneCIntegrationController extends Controller
{
    public function __construct(private OneCConnectionTester $connectionTester) {}

    public function edit(Request $request): Response
    {
        Gate::authorize('manage-one-c');

        $integrations = OneCIntegration::query()
            ->with('updater:id,name,email')
            ->latest('id')
            ->get();

        $selectedIntegrationId = $request->integer('integration');

        if (! $integrations->contains('id', $selectedIntegrationId)) {
            $selectedIntegrationId = $integrations->first()?->id;
        }

        return Inertia::render('settings/OneC', [
            'integrations' => $integrations
                ->map(fn (OneCIntegration $integration): array => $this->serializeIntegration($integration))
                ->values(),
            'selectedIntegrationId' => $selectedIntegrationId,
            'productOptions' => collect(OneCIntegration::products())
                ->map(fn (string $product): array => [
                    'value' => $product,
                    'label' => __("ui.one_c.products.{$product}"),
                    'description' => __("ui.one_c.products.{$product}_description"),
                ]),
            'transportOptions' => collect(OneCIntegration::transports())
                ->map(fn (string $transport): array => [
                    'value' => $transport,
                    'label' => __("ui.one_c.transports.{$transport}"),
                    'description' => __("ui.one_c.transports.{$transport}_description"),
                ]),
            'entityDefinitions' => collect(OneCIntegration::entityDefinitions())
                ->map(fn (array $definition, string $key): array => [
                    'key' => $key,
                    'label' => __($definition['label_key']),
                    'description' => __($definition['description_key']),
                    'directions' => $definition['directions'],
                ])
                ->values(),
        ]);
    }

    public function store(StoreOneCIntegrationRequest $request): RedirectResponse
    {
        $integration = OneCIntegration::query()->create([
            ...$request->validated(),
            'entities' => OneCIntegration::normalizeEntities([]),
            'updated_by_user_id' => $request->user()?->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.one_c.created_success'),
        ]);

        return to_route('settings.one-c.edit', ['integration' => $integration->id]);
    }

    public function update(
        UpdateOneCIntegrationRequest $request,
        OneCIntegration $oneCIntegration,
    ): RedirectResponse {
        $wasEnabled = $oneCIntegration->is_enabled;
        $originalAuthType = $oneCIntegration->auth_type;
        $attributes = $request->safe()->except(['password', 'token', 'entities']);
        $attributes['entities'] = $request->entities();
        $attributes['updated_by_user_id'] = $request->user()?->id;

        $this->applyCredentials($request, $attributes, $originalAuthType);
        $oneCIntegration->fill($attributes);

        if ($this->connectionConfigurationChanged($oneCIntegration)) {
            $oneCIntegration->fill([
                'last_tested_at' => null,
                'last_test_succeeded' => null,
                'last_test_duration_ms' => null,
                'last_test_message' => null,
            ]);
        }

        if (! $wasEnabled && $oneCIntegration->is_enabled) {
            $oneCIntegration->enabled_at = now();
            $oneCIntegration->disabled_at = null;
        }

        if ($wasEnabled && ! $oneCIntegration->is_enabled) {
            $oneCIntegration->disabled_at = now();
        }

        $oneCIntegration->save();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.one_c.updated_success'),
        ]);

        return back();
    }

    public function test(
        TestOneCConnectionRequest $request,
        OneCIntegration $oneCIntegration,
    ): RedirectResponse {
        $result = $this->connectionTester->test($oneCIntegration);

        $oneCIntegration->update([
            'last_tested_at' => now(),
            'last_test_succeeded' => $result['succeeded'],
            'last_test_duration_ms' => $result['duration_ms'],
            'last_test_message' => $result['message'],
            'updated_by_user_id' => $request->user()?->id,
        ]);

        Inertia::flash('toast', [
            'type' => $result['succeeded'] ? 'success' : 'error',
            'message' => $result['message'],
        ]);

        return back();
    }

    public function destroy(OneCIntegration $oneCIntegration): RedirectResponse
    {
        Gate::authorize('manage-one-c');

        if ($oneCIntegration->is_enabled) {
            return back()->withErrors([
                'delete' => __('ui.one_c.validation.disable_before_delete'),
            ]);
        }

        $oneCIntegration->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.one_c.deleted_success'),
        ]);

        return to_route('settings.one-c.edit');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function applyCredentials(
        UpdateOneCIntegrationRequest $request,
        array &$attributes,
        string $originalAuthType,
    ): void {
        $authType = $request->validated('auth_type');

        if ($authType === OneCIntegration::AUTH_BASIC) {
            $attributes['token'] = null;

            if ($request->filled('password')) {
                $attributes['password'] = $request->validated('password');
            } elseif ($originalAuthType !== OneCIntegration::AUTH_BASIC) {
                $attributes['password'] = null;
            }

            return;
        }

        $attributes['username'] = null;
        $attributes['password'] = null;

        if ($authType === OneCIntegration::AUTH_BEARER) {
            if ($request->filled('token')) {
                $attributes['token'] = $request->validated('token');
            } elseif ($originalAuthType !== OneCIntegration::AUTH_BEARER) {
                $attributes['token'] = null;
            }

            return;
        }

        $attributes['token'] = null;
    }

    private function connectionConfigurationChanged(OneCIntegration $integration): bool
    {
        return $integration->isDirty([
            'base_url',
            'api_path',
            'transport',
            'auth_type',
            'username',
            'password',
            'token',
            'verify_tls',
            'connect_timeout_seconds',
            'request_timeout_seconds',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeIntegration(OneCIntegration $integration): array
    {
        return [
            'id' => $integration->id,
            'name' => $integration->name,
            'product' => $integration->product,
            'transport' => $integration->transport,
            'is_enabled' => $integration->is_enabled,
            'base_url' => $integration->base_url,
            'api_path' => $integration->api_path,
            'auth_type' => $integration->auth_type,
            'username' => $integration->username,
            'password_configured' => is_string($integration->password) && $integration->password !== '',
            'token_configured' => is_string($integration->token) && $integration->token !== '',
            'verify_tls' => $integration->verify_tls,
            'connect_timeout_seconds' => $integration->connect_timeout_seconds,
            'request_timeout_seconds' => $integration->request_timeout_seconds,
            'import_enabled' => $integration->import_enabled,
            'export_enabled' => $integration->export_enabled,
            'schedule_enabled' => $integration->schedule_enabled,
            'sync_interval_minutes' => $integration->sync_interval_minutes,
            'sync_window_start' => $integration->sync_window_start,
            'sync_window_end' => $integration->sync_window_end,
            'batch_size' => $integration->batch_size,
            'default_sync_mode' => $integration->default_sync_mode,
            'conflict_strategy' => $integration->conflict_strategy,
            'stop_on_error' => $integration->stop_on_error,
            'dry_run' => $integration->dry_run,
            'entities' => $integration->normalizedEntities(),
            'enabled_at' => $integration->enabled_at?->toISOString(),
            'disabled_at' => $integration->disabled_at?->toISOString(),
            'last_tested_at' => $integration->last_tested_at?->toISOString(),
            'last_test_succeeded' => $integration->last_test_succeeded,
            'last_test_duration_ms' => $integration->last_test_duration_ms,
            'last_test_message' => $integration->last_test_message,
            'last_sync_at' => $integration->last_sync_at?->toISOString(),
            'last_successful_sync_at' => $integration->last_successful_sync_at?->toISOString(),
            'last_sync_status' => $integration->last_sync_status,
            'last_sync_message' => $integration->last_sync_message,
            'updated_at' => $integration->updated_at?->toISOString(),
            'updated_by' => $integration->updater
                ? [
                    'id' => $integration->updater->id,
                    'name' => $integration->updater->name,
                    'email' => $integration->updater->email,
                ]
                : null,
        ];
    }
}

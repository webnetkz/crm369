<?php

namespace Database\Factories;

use App\Models\OneCIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OneCIntegration>
 */
class OneCIntegrationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Основная база 1С',
            'product' => OneCIntegration::PRODUCT_ENTERPRISE_MANAGEMENT,
            'transport' => OneCIntegration::TRANSPORT_ODATA,
            'is_enabled' => false,
            'base_url' => 'https://one-c.example.test',
            'api_path' => '/odata/standard.odata',
            'auth_type' => OneCIntegration::AUTH_BASIC,
            'username' => 'crm-exchange',
            'password' => 'secret-password',
            'token' => null,
            'verify_tls' => true,
            'connect_timeout_seconds' => 5,
            'request_timeout_seconds' => 30,
            'import_enabled' => true,
            'export_enabled' => false,
            'schedule_enabled' => false,
            'sync_interval_minutes' => 60,
            'batch_size' => 100,
            'default_sync_mode' => OneCIntegration::SYNC_MODE_INCREMENTAL,
            'conflict_strategy' => OneCIntegration::CONFLICT_ONE_C_WINS,
            'stop_on_error' => true,
            'dry_run' => false,
            'entities' => OneCIntegration::normalizeEntities([
                'counterparties' => [
                    'enabled' => true,
                    'direction' => OneCIntegration::DIRECTION_IMPORT,
                    'source_of_truth' => OneCIntegration::SOURCE_ONE_C,
                ],
            ]),
            'updated_by_user_id' => null,
        ];
    }

    public function enabled(): static
    {
        return $this->state(fn (): array => [
            'is_enabled' => true,
            'enabled_at' => now(),
        ]);
    }

    public function bearer(): static
    {
        return $this->state(fn (): array => [
            'auth_type' => OneCIntegration::AUTH_BEARER,
            'username' => null,
            'password' => null,
            'token' => 'secret-token',
        ]);
    }
}

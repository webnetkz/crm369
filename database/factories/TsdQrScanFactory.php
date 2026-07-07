<?php

namespace Database\Factories;

use App\Models\PortalWebhook;
use App\Models\TsdQrScan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TsdQrScan>
 */
class TsdQrScanFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'qr_code' => sprintf(
                'QR-%s-%s',
                fake()->bothify('??##'),
                fake()->numerify('######')
            ),
            'normalized_qr_code' => fake()->bothify('QR??########'),
            'source' => fake()->randomElement(TsdQrScan::availableSources()),
            'device_name' => fake()->randomElement(['TSD-01', 'TSD-02', 'TSD-03']),
            'location' => fake()->randomElement(['Main warehouse', 'Packing zone', 'Dispatch area']),
            'context' => fake()->randomElement(['acceptance', 'picking', 'inventory', 'shipping']),
            'payload' => [
                'batch' => fake()->bothify('BATCH-####'),
                'quantity' => fake()->numberBetween(1, 25),
            ],
            'scanned_at' => now()->subMinutes(fake()->numberBetween(0, 180)),
            'scanned_by_user_id' => User::factory(),
            'portal_webhook_id' => null,
        ];
    }

    public function viaWebhook(): self
    {
        return $this->state(fn (): array => [
            'source' => TsdQrScan::SOURCE_WEBHOOK,
            'scanned_by_user_id' => null,
            'portal_webhook_id' => PortalWebhook::factory(),
        ]);
    }
}

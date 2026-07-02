<?php

namespace Database\Factories;

use App\Models\MessengerIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MessengerIntegration>
 */
class MessengerIntegrationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $driver = fake()->randomElement(MessengerIntegration::drivers());

        return [
            'driver' => $driver,
            'name' => MessengerIntegration::defaultNameForDriver($driver),
            'is_active' => false,
            'settings' => MessengerIntegration::defaultSettingsForDriver($driver),
            'updated_by_user_id' => null,
        ];
    }

    public function whatsappBusiness(): static
    {
        return $this->state(fn (): array => [
            'driver' => MessengerIntegration::DRIVER_WHATSAPP_BUSINESS,
            'name' => MessengerIntegration::defaultNameForDriver(MessengerIntegration::DRIVER_WHATSAPP_BUSINESS),
            'settings' => MessengerIntegration::defaultSettingsForDriver(MessengerIntegration::DRIVER_WHATSAPP_BUSINESS),
        ]);
    }

    public function telegram(): static
    {
        return $this->state(fn (): array => [
            'driver' => MessengerIntegration::DRIVER_TELEGRAM,
            'name' => MessengerIntegration::defaultNameForDriver(MessengerIntegration::DRIVER_TELEGRAM),
            'settings' => MessengerIntegration::defaultSettingsForDriver(MessengerIntegration::DRIVER_TELEGRAM),
        ]);
    }

    public function telephony(): static
    {
        return $this->state(fn (): array => [
            'driver' => MessengerIntegration::DRIVER_TELEPHONY,
            'name' => MessengerIntegration::defaultNameForDriver(MessengerIntegration::DRIVER_TELEPHONY),
            'settings' => MessengerIntegration::defaultSettingsForDriver(MessengerIntegration::DRIVER_TELEPHONY),
        ]);
    }
}

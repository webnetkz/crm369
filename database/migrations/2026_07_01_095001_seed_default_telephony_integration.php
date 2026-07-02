<?php

use App\Models\MessengerIntegration;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        MessengerIntegration::query()->firstOrCreate(
            ['driver' => MessengerIntegration::DRIVER_TELEPHONY],
            [
                'name' => MessengerIntegration::defaultNameForDriver(MessengerIntegration::DRIVER_TELEPHONY),
                'is_active' => false,
                'settings' => [],
            ],
        );
    }

    public function down(): void
    {
        MessengerIntegration::query()
            ->where('driver', MessengerIntegration::DRIVER_TELEPHONY)
            ->delete();
    }
};

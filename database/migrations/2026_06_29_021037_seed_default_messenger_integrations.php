<?php

use App\Models\MessengerIntegration;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        MessengerIntegration::query()->firstOrCreate(
            ['driver' => MessengerIntegration::DRIVER_WHATSAPP_BUSINESS],
            [
                'name' => 'WhatsApp Business',
                'is_active' => false,
                'settings' => [],
            ],
        );

        MessengerIntegration::query()->firstOrCreate(
            ['driver' => MessengerIntegration::DRIVER_TELEGRAM],
            [
                'name' => 'Telegram',
                'is_active' => false,
                'settings' => [],
            ],
        );
    }

    public function down(): void
    {
        MessengerIntegration::query()
            ->whereIn('driver', MessengerIntegration::drivers())
            ->delete();
    }
};

<?php

namespace Database\Seeders;

use App\Models\EquipmentItem;
use App\Models\EquipmentItemHistory;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BulkEquipmentInventorySeeder extends Seeder
{
    use WithoutModelEvents;

    private const int TOTAL_ITEMS = 2000;

    private const int ON_BALANCE_ITEMS = 300;

    private const int ISSUED_ITEMS = self::TOTAL_ITEMS - self::ON_BALANCE_ITEMS;

    /**
     * @var array<int, array{type: string, models: array<int, string>}>
     */
    private const array DEVICE_CATALOG = [
        [
            'type' => 'Телефон',
            'models' => ['Samsung Galaxy A55', 'iPhone 13', 'Xiaomi Redmi Note 13', 'Nokia XR21'],
        ],
        [
            'type' => 'Мышь',
            'models' => ['Logitech M240', 'HP 150', 'Lenovo Go Wireless', 'A4Tech FStyler'],
        ],
        [
            'type' => 'Клавиатура',
            'models' => ['Logitech K270', 'Keychron C3 Pro', 'HP 230', 'Redragon Kumara'],
        ],
        [
            'type' => 'Монитор',
            'models' => ['LG 24MR400', 'Dell P2422H', 'Samsung S24C', 'AOC 24B2XH'],
        ],
        [
            'type' => 'Принтер',
            'models' => ['HP LaserJet Pro M404', 'Brother HL-L2371DN', 'Epson L3250', 'Xerox B230'],
        ],
        [
            'type' => 'ТСД сканер',
            'models' => ['Zebra TC22', 'Urovo DT50', 'Honeywell EDA52', 'Point Mobile PM67'],
        ],
        [
            'type' => 'Ноутбук',
            'models' => ['Lenovo ThinkPad E14', 'HP ProBook 450', 'Dell Latitude 5440', 'Asus ExpertBook B1'],
        ],
        [
            'type' => 'Планшет',
            'models' => ['Samsung Galaxy Tab A9', 'Apple iPad 10', 'Lenovo Tab M10', 'Huawei MatePad SE'],
        ],
        [
            'type' => 'Гарнитура',
            'models' => ['Jabra Evolve2 30', 'Logitech H390', 'Poly Blackwire 3220', 'EPOS PC 8'],
        ],
        [
            'type' => 'Веб-камера',
            'models' => ['Logitech C920', 'A4Tech PK-940HA', 'HP 320 FHD', 'Xiaomi Imilab W90'],
        ],
        [
            'type' => 'Док-станция',
            'models' => ['Dell WD19S', 'Lenovo USB-C Dock', 'Ugreen Revodok', 'HP USB-C G5'],
        ],
        [
            'type' => 'Сетевое устройство',
            'models' => ['MikroTik hAP ax2', 'TP-Link TL-SG108', 'Ubiquiti U6 Lite', 'Keenetic Hopper'],
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $activeUsers = $this->activeUsers();
        $creator = $activeUsers->first();

        if (! $creator instanceof User) {
            return;
        }

        $historyTableExists = Schema::hasTable('equipment_item_histories');

        DB::transaction(function () use ($activeUsers, $creator, $historyTableExists): void {
            for ($index = 0; $index < self::TOTAL_ITEMS; $index++) {
                $isIssued = $index < self::ISSUED_ITEMS;
                $assignedUser = $isIssued
                    ? $activeUsers->get($index % $activeUsers->count())
                    : null;
                $timestamp = now()->subDays(90)->addMinutes($index);

                $equipmentItem = EquipmentItem::query()->create([
                    'name' => $this->deviceName($index),
                    'qr_code' => EquipmentItem::generateQrCode(),
                    'status' => $isIssued ? EquipmentItem::STATUS_ISSUED : EquipmentItem::STATUS_ON_BALANCE,
                    'issued_to_user_id' => $assignedUser?->id,
                    'responsible_user_id' => $assignedUser?->id,
                    'created_by_user_id' => $creator->id,
                    'updated_by_user_id' => $creator->id,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);

                if (! $historyTableExists) {
                    continue;
                }

                EquipmentItemHistory::query()->create([
                    'equipment_item_id' => $equipmentItem->id,
                    'event_type' => EquipmentItemHistory::EVENT_CREATED,
                    'source' => EquipmentItemHistory::SOURCE_CSV,
                    'actor_user_id' => $creator->id,
                    'changes' => [
                        'name' => ['from' => null, 'to' => $equipmentItem->name],
                        'qr_code' => ['from' => null, 'to' => $equipmentItem->qr_code],
                        'status' => ['from' => null, 'to' => $equipmentItem->status],
                        'responsible_user' => ['from' => null, 'to' => $this->serializeUser($assignedUser)],
                        'issued_to_user' => ['from' => null, 'to' => $this->serializeUser($assignedUser)],
                    ],
                    'snapshot' => [
                        'name' => $equipmentItem->name,
                        'qr_code' => $equipmentItem->qr_code,
                        'status' => $equipmentItem->status,
                        'responsible_user' => $this->serializeUser($assignedUser),
                        'issued_to_user' => $this->serializeUser($assignedUser),
                    ],
                    'changed_at' => $timestamp,
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ]);
            }
        });
    }

    /**
     * @return Collection<int, User>
     */
    private function activeUsers(): Collection
    {
        $activeUsers = User::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name', 'last_name', 'email']);

        if ($activeUsers->isNotEmpty()) {
            return $activeUsers->values();
        }

        return User::factory()
            ->count(25)
            ->create()
            ->sortBy('id')
            ->values();
    }

    private function deviceName(int $index): string
    {
        $catalog = self::DEVICE_CATALOG[$index % count(self::DEVICE_CATALOG)];
        $models = $catalog['models'];
        $model = $models[intdiv($index, count(self::DEVICE_CATALOG)) % count($models)];

        return sprintf('%s %s %04d', $catalog['type'], $model, $index + 1);
    }

    /**
     * @return array{id: int, name: string, last_name: string|null, email: string}|null
     */
    private function serializeUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'last_name' => $user->last_name,
            'email' => $user->email,
        ];
    }
}

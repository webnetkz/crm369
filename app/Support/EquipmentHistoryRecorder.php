<?php

namespace App\Support;

use App\Models\EquipmentItem;
use App\Models\EquipmentItemHistory;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class EquipmentHistoryRecorder
{
    /**
     * @return array{
     *     name: string,
     *     qr_code: string,
     *     status: string,
     *     responsible_user: array{id: int, name: string, last_name: string|null, email: string}|null,
     *     issued_to_user: array{id: int, name: string, last_name: string|null, email: string}|null
     * }
     */
    public function snapshot(EquipmentItem $equipmentItem): array
    {
        $equipmentItem->loadMissing([
            'responsibleUser:id,name,last_name,email',
            'issuedToUser:id,name,last_name,email',
        ]);

        return [
            'name' => $equipmentItem->name,
            'qr_code' => $equipmentItem->qr_code,
            'status' => $equipmentItem->status,
            'responsible_user' => $this->serializeUser($equipmentItem->responsibleUser),
            'issued_to_user' => $this->serializeUser($equipmentItem->issuedToUser),
        ];
    }

    public function recordCreated(
        EquipmentItem $equipmentItem,
        string $source,
        ?int $actorUserId = null,
    ): EquipmentItemHistory {
        $snapshot = $this->snapshot($equipmentItem);
        $changes = collect($snapshot)
            ->mapWithKeys(fn (mixed $value, string $field): array => [
                $field => ['from' => null, 'to' => $value],
            ])
            ->all();

        return $this->createHistory(
            equipmentItem: $equipmentItem,
            eventType: EquipmentItemHistory::EVENT_CREATED,
            source: $source,
            actorUserId: $actorUserId,
            changes: $changes,
            snapshot: $snapshot,
            changedAt: $equipmentItem->created_at?->toDateTimeString(),
        );
    }

    /**
     * @param  array{
     *     name: string,
     *     qr_code: string,
     *     status: string,
     *     responsible_user: array{id: int, name: string, last_name: string|null, email: string}|null,
     *     issued_to_user: array{id: int, name: string, last_name: string|null, email: string}|null
     * }  $before
     */
    public function recordUpdated(
        EquipmentItem $equipmentItem,
        array $before,
        string $source,
        ?int $actorUserId = null,
    ): ?EquipmentItemHistory {
        $after = $this->snapshot($equipmentItem);
        $changes = [];

        foreach ($after as $field => $value) {
            if (($before[$field] ?? null) === $value) {
                continue;
            }

            $changes[$field] = [
                'from' => $before[$field] ?? null,
                'to' => $value,
            ];
        }

        if ($changes === []) {
            return null;
        }

        return $this->createHistory(
            equipmentItem: $equipmentItem,
            eventType: EquipmentItemHistory::EVENT_UPDATED,
            source: $source,
            actorUserId: $actorUserId,
            changes: $changes,
            snapshot: $after,
            changedAt: $equipmentItem->updated_at?->toDateTimeString(),
        );
    }

    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     * @param  array{
     *     name: string,
     *     qr_code: string,
     *     status: string,
     *     responsible_user: array{id: int, name: string, last_name: string|null, email: string}|null,
     *     issued_to_user: array{id: int, name: string, last_name: string|null, email: string}|null
     * }  $snapshot
     */
    private function createHistory(
        EquipmentItem $equipmentItem,
        string $eventType,
        string $source,
        ?int $actorUserId,
        array $changes,
        array $snapshot,
        ?string $changedAt,
    ): EquipmentItemHistory {
        if (! $this->historyTableExists()) {
            return new EquipmentItemHistory([
                'equipment_item_id' => $equipmentItem->id,
                'event_type' => $eventType,
                'source' => $source,
                'actor_user_id' => $actorUserId,
                'changes' => $changes,
                'snapshot' => $snapshot,
                'changed_at' => $changedAt ?? now()->toDateTimeString(),
            ]);
        }

        return EquipmentItemHistory::query()->create([
            'equipment_item_id' => $equipmentItem->id,
            'event_type' => $eventType,
            'source' => $source,
            'actor_user_id' => $actorUserId,
            'changes' => $changes,
            'snapshot' => $snapshot,
            'changed_at' => $changedAt ?? now()->toDateTimeString(),
        ]);
    }

    private function historyTableExists(): bool
    {
        return Schema::hasTable('equipment_item_histories');
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

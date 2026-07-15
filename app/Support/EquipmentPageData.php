<?php

namespace App\Support;

use App\Models\EquipmentItem;
use App\Models\EquipmentItemHistory;
use App\Models\TsdQrScan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class EquipmentPageData
{
    private const int PER_PAGE = 50;

    /**
     * @return array<string, mixed>
     */
    public function build(
        User $viewer,
        ?int $activeEquipmentItemId = null,
        string $search = '',
        ?string $activeDialog = null,
        string $status = '',
    ): array {
        $search = trim($search);
        $historyTableExists = Schema::hasTable('equipment_item_histories');
        $filteredEquipmentQuery = $this->filteredEquipmentQuery($search, $status);
        $stats = (clone $filteredEquipmentQuery)
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as on_balance', [
                EquipmentItem::STATUS_ON_BALANCE,
            ])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as issued', [
                EquipmentItem::STATUS_ISSUED,
            ])
            ->selectRaw('sum(case when status in (?, ?) then 1 else 0 end) as maintenance', [
                EquipmentItem::STATUS_MAINTENANCE,
                EquipmentItem::STATUS_REPAIR,
            ])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as written_off', [
                EquipmentItem::STATUS_WRITTEN_OFF,
            ])
            ->first();

        $equipmentItems = (clone $filteredEquipmentQuery)
            ->with(array_filter([
                'issuedToUser:id,name,last_name,email',
                'responsibleUser:id,name,last_name,email',
            ]))
            ->ordered()
            ->paginate(self::PER_PAGE)
            ->withQueryString();
        $serializedEquipmentItems = $equipmentItems
            ->getCollection()
            ->map(function (EquipmentItem $equipmentItem): array {
                return $this->serializeEquipmentItemSummary($equipmentItem);
            })
            ->values();
        $equipmentItems->setCollection($serializedEquipmentItems);
        $activeEquipmentItem = $this->activeEquipmentItem(
            $activeEquipmentItemId,
            $historyTableExists,
        );

        return [
            'equipmentItems' => PaginationData::from($equipmentItems),
            'activeEquipmentItem' => is_array($activeEquipmentItem)
                ? $activeEquipmentItem
                : null,
            'activeDialog' => $activeDialog,
            'availableUsers' => User::query()
                ->select(['id', 'name', 'last_name', 'email'])
                ->where('is_active', true)
                ->orderBy('name')
                ->orderBy('last_name')
                ->get()
                ->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'last_name' => $user->last_name,
                    'email' => $user->email,
                ])
                ->values()
                ->all(),
            'statusOptions' => collect(EquipmentItem::statusDefinitions())
                ->map(fn (array $definition, string $status): array => [
                    'value' => $status,
                    'label' => __($definition['label_key']),
                    'description' => __($definition['description_key']),
                ])
                ->values()
                ->all(),
            'filters' => [
                'search' => $search,
                'status' => $status,
            ],
            'perPageOptions' => [self::PER_PAGE],
            'stats' => [
                'total' => (int) ($stats?->total ?? 0),
                'on_balance' => (int) ($stats?->on_balance ?? 0),
                'issued' => (int) ($stats?->issued ?? 0),
                'maintenance' => (int) ($stats?->maintenance ?? 0),
                'written_off' => (int) ($stats?->written_off ?? 0),
            ],
            'viewer' => [
                'id' => $viewer->id,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activeEquipmentItem(?int $activeEquipmentItemId, bool $historyTableExists): ?array
    {
        if ($activeEquipmentItemId === null) {
            return null;
        }

        $equipmentItem = EquipmentItem::query()
            ->with(array_filter([
                'issuedToUser:id,name,last_name,email',
                'responsibleUser:id,name,last_name,email',
                $historyTableExists ? 'historyEntries.actor:id,name,last_name,email' : null,
            ]))
            ->find($activeEquipmentItemId);

        if (! $equipmentItem instanceof EquipmentItem) {
            return null;
        }

        $scans = TsdQrScan::query()
            ->with(['scannedBy:id,name,last_name,email', 'portalWebhook:id,name'])
            ->where('normalized_qr_code', EquipmentItem::normalizeQrCode($equipmentItem->qr_code))
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->get();

        return $this->serializeEquipmentItem(
            $equipmentItem,
            $scans,
            $historyTableExists,
        );
    }

    /**
     * @return Builder<EquipmentItem>
     */
    private function filteredEquipmentQuery(string $search, string $status): Builder
    {
        return EquipmentItem::query()
            ->when($status !== '', function (Builder $query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $equipmentQuery) use ($search): void {
                    $equipmentQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('qr_code', 'like', "%{$search}%")
                        ->orWhereHas('responsibleUser', function (Builder $userQuery) use ($search): void {
                            $userQuery->where(function (Builder $nestedUserQuery) use ($search): void {
                                $nestedUserQuery
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                        })
                        ->orWhereHas('issuedToUser', function (Builder $userQuery) use ($search): void {
                            $userQuery->where(function (Builder $nestedUserQuery) use ($search): void {
                                $nestedUserQuery
                                    ->where('name', 'like', "%{$search}%")
                                    ->orWhere('last_name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%");
                            });
                        });
                });
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEquipmentItem(
        EquipmentItem $equipmentItem,
        Collection $scans,
        bool $historyTableExists,
    ): array {
        return [
            'id' => $equipmentItem->id,
            'name' => $equipmentItem->name,
            'qr_code' => $equipmentItem->qr_code,
            'qr_code_svg_data_uri' => $equipmentItem->qrCodeSvgDataUri(),
            'status' => $equipmentItem->status,
            'status_label' => __(
                EquipmentItem::statusDefinitions()[$equipmentItem->status]['label_key']
                    ?? 'ui.equipment.statuses.on_balance'
            ),
            'issued_to_user' => $equipmentItem->issuedToUser
                ? [
                    'id' => $equipmentItem->issuedToUser->id,
                    'name' => $equipmentItem->issuedToUser->name,
                    'last_name' => $equipmentItem->issuedToUser->last_name,
                    'email' => $equipmentItem->issuedToUser->email,
                ]
                : null,
            'responsible_user' => $equipmentItem->responsibleUser
                ? [
                    'id' => $equipmentItem->responsibleUser->id,
                    'name' => $equipmentItem->responsibleUser->name,
                    'last_name' => $equipmentItem->responsibleUser->last_name,
                    'email' => $equipmentItem->responsibleUser->email,
                ]
                : null,
            'updated_at' => $equipmentItem->updated_at?->toISOString(),
            'history_entries' => $historyTableExists
                ? $this->serializeHistoryEntries($equipmentItem, $scans)
                : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEquipmentItemSummary(EquipmentItem $equipmentItem): array
    {
        return [
            'id' => $equipmentItem->id,
            'name' => $equipmentItem->name,
            'qr_code' => $equipmentItem->qr_code,
            'qr_code_svg_data_uri' => null,
            'status' => $equipmentItem->status,
            'status_label' => __(
                EquipmentItem::statusDefinitions()[$equipmentItem->status]['label_key']
                    ?? 'ui.equipment.statuses.on_balance'
            ),
            'issued_to_user' => $equipmentItem->issuedToUser
                ? [
                    'id' => $equipmentItem->issuedToUser->id,
                    'name' => $equipmentItem->issuedToUser->name,
                    'last_name' => $equipmentItem->issuedToUser->last_name,
                    'email' => $equipmentItem->issuedToUser->email,
                ]
                : null,
            'responsible_user' => $equipmentItem->responsibleUser
                ? [
                    'id' => $equipmentItem->responsibleUser->id,
                    'name' => $equipmentItem->responsibleUser->name,
                    'last_name' => $equipmentItem->responsibleUser->last_name,
                    'email' => $equipmentItem->responsibleUser->email,
                ]
                : null,
            'updated_at' => $equipmentItem->updated_at?->toISOString(),
            'history_entries' => [],
        ];
    }

    /**
     * @param  Collection<int, TsdQrScan>  $scans
     * @return array<int, array<string, mixed>>
     */
    private function serializeHistoryEntries(EquipmentItem $equipmentItem, Collection $scans): array
    {
        $changeEntries = $equipmentItem->historyEntries->map(
            fn (EquipmentItemHistory $historyEntry): array => $this->serializeChangeHistoryEntry($historyEntry)
        );
        $scanEntries = $scans->map(
            fn (TsdQrScan $scan): array => $this->serializeScanHistoryEntry($scan)
        );

        return $changeEntries
            ->concat($scanEntries)
            ->sortByDesc('happened_at')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeChangeHistoryEntry(EquipmentItemHistory $historyEntry): array
    {
        return [
            'id' => 'change-'.$historyEntry->id,
            'kind' => 'change',
            'event_label' => $historyEntry->eventTypeLabel(),
            'source_label' => $historyEntry->sourceLabel(),
            'happened_at' => $historyEntry->changed_at?->toISOString(),
            'actor' => $historyEntry->actor
                ? [
                    'id' => $historyEntry->actor->id,
                    'name' => $historyEntry->actor->name,
                    'last_name' => $historyEntry->actor->last_name,
                    'email' => $historyEntry->actor->email,
                ]
                : null,
            'changes' => collect($historyEntry->changes ?? [])
                ->map(function (mixed $change, string $field): ?array {
                    if (! is_array($change) || ! array_key_exists('to', $change)) {
                        return null;
                    }

                    return [
                        'field' => $field,
                        'label' => $this->historyFieldLabel($field),
                        'from' => $this->historyValueLabel($field, $change['from'] ?? null),
                        'to' => $this->historyValueLabel($field, $change['to'] ?? null),
                    ];
                })
                ->filter()
                ->values()
                ->all(),
            'location' => null,
            'context' => null,
            'device_name' => null,
            'payload_preview' => null,
            'webhook_name' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeScanHistoryEntry(TsdQrScan $scan): array
    {
        return [
            'id' => 'scan-'.$scan->id,
            'kind' => 'scan',
            'event_label' => __('ui.equipment.history_scan_event'),
            'source_label' => $scan->sourceLabel(),
            'happened_at' => $scan->scanned_at?->toISOString(),
            'actor' => $scan->scannedBy
                ? [
                    'id' => $scan->scannedBy->id,
                    'name' => $scan->scannedBy->name,
                    'last_name' => $scan->scannedBy->last_name,
                    'email' => $scan->scannedBy->email,
                ]
                : null,
            'changes' => [],
            'location' => $scan->location,
            'context' => $scan->context,
            'device_name' => $scan->device_name,
            'payload_preview' => is_array($scan->payload)
                ? json_encode($scan->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
                : null,
            'webhook_name' => $scan->portalWebhook?->name,
        ];
    }

    private function historyFieldLabel(string $field): string
    {
        return match ($field) {
            'qr_code' => __('ui.equipment.qr_code'),
            'status' => __('ui.equipment.status'),
            'responsible_user' => __('ui.equipment.responsible_user'),
            'issued_to_user' => __('ui.equipment.issued_to_user'),
            default => __('ui.equipment.name'),
        };
    }

    private function historyValueLabel(string $field, mixed $value): ?string
    {
        return match ($field) {
            'status' => $this->statusValueLabel($value),
            'responsible_user' => $this->historyUserValueLabel($value, __('ui.equipment.not_assigned')),
            'issued_to_user' => $this->historyUserValueLabel($value, __('ui.equipment.not_issued')),
            default => is_string($value) && trim($value) !== '' ? $value : null,
        };
    }

    private function statusValueLabel(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return __(
            EquipmentItem::statusDefinitions()[$value]['label_key']
                ?? 'ui.equipment.statuses.on_balance'
        );
    }

    private function historyUserValueLabel(mixed $value, string $emptyLabel): string
    {
        if (! is_array($value)) {
            return $emptyLabel;
        }

        $name = collect([
            $value['name'] ?? null,
            $value['last_name'] ?? null,
        ])
            ->filter(fn (mixed $part): bool => is_string($part) && $part !== '')
            ->join(' ');

        if ($name !== '') {
            return $name;
        }

        return is_string($value['email'] ?? null) && $value['email'] !== ''
            ? $value['email']
            : $emptyLabel;
    }
}

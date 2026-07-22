<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEquipmentItemRequest;
use App\Http\Requests\UpdateEquipmentItemRequest;
use App\Http\Resources\ApiEquipmentResource;
use App\Models\EquipmentItem;
use App\Models\EquipmentItemHistory;
use App\Models\PortalWebhook;
use App\Support\EquipmentAssignmentNotifier;
use App\Support\EquipmentHistoryRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalWebhookEquipmentController extends Controller
{
    public function index(Request $request, PortalWebhook $portalWebhook): JsonResponse
    {
        $equipmentItems = EquipmentItem::query()
            ->with($this->equipmentRelations())
            ->ordered()
            ->get();

        return response()->json([
            'webhook' => $this->webhookPayload($portalWebhook),
            'data' => $equipmentItems
                ->map(fn (EquipmentItem $equipmentItem): array => (new ApiEquipmentResource($equipmentItem))->resolve())
                ->values()
                ->all(),
            'status_options' => $this->statusOptions(),
        ]);
    }

    public function show(Request $request, PortalWebhook $portalWebhook, EquipmentItem $equipmentItem): JsonResponse
    {
        return response()->json([
            'webhook' => $this->webhookPayload($portalWebhook),
            'data' => (new ApiEquipmentResource($equipmentItem->load($this->equipmentRelations())))->resolve(),
        ]);
    }

    public function store(
        StoreEquipmentItemRequest $request,
        PortalWebhook $portalWebhook,
        EquipmentHistoryRecorder $historyRecorder,
        EquipmentAssignmentNotifier $assignmentNotifier,
    ): JsonResponse {
        $actorId = $portalWebhook->created_by_user_id;
        abort_unless($actorId !== null, 422, 'Webhook creator is required.');

        $equipmentItem = EquipmentItem::query()->create([
            ...$request->payload(),
            'created_by_user_id' => $actorId,
            'updated_by_user_id' => $actorId,
        ]);

        $historyRecorder->recordCreated($equipmentItem, EquipmentItemHistory::SOURCE_WEBHOOK, $actorId);
        $assignmentNotifier->sendForAssignmentChanges($equipmentItem);

        return response()->json([
            'webhook' => $this->webhookPayload($portalWebhook),
            'message' => __('ui.equipment.created_success'),
            'data' => (new ApiEquipmentResource($equipmentItem->load($this->equipmentRelations())))->resolve(),
        ], 201);
    }

    public function update(
        UpdateEquipmentItemRequest $request,
        PortalWebhook $portalWebhook,
        EquipmentItem $equipmentItem,
        EquipmentHistoryRecorder $historyRecorder,
        EquipmentAssignmentNotifier $assignmentNotifier,
    ): JsonResponse {
        $actorId = $portalWebhook->created_by_user_id;
        abort_unless($actorId !== null, 422, 'Webhook creator is required.');

        $previousIssuedToUserId = $equipmentItem->issued_to_user_id;
        $previousResponsibleUserId = $equipmentItem->responsible_user_id;
        $before = $historyRecorder->snapshot($equipmentItem);

        $equipmentItem->update([
            ...$request->payload(),
            'updated_by_user_id' => $actorId,
        ]);

        $equipmentItem->refresh();

        $historyRecorder->recordUpdated(
            $equipmentItem,
            $before,
            EquipmentItemHistory::SOURCE_WEBHOOK,
            $actorId,
        );
        $assignmentNotifier->sendForAssignmentChanges(
            $equipmentItem,
            $previousIssuedToUserId,
            $previousResponsibleUserId,
        );

        return response()->json([
            'webhook' => $this->webhookPayload($portalWebhook),
            'message' => __('ui.equipment.updated_success'),
            'data' => (new ApiEquipmentResource($equipmentItem->fresh()->load($this->equipmentRelations())))->resolve(),
        ]);
    }

    /**
     * @return array{id: int, name: string}
     */
    private function webhookPayload(PortalWebhook $portalWebhook): array
    {
        return [
            'id' => $portalWebhook->id,
            'name' => $portalWebhook->name,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function equipmentRelations(): array
    {
        return [
            'issuedToUser:id,name,last_name,email',
            'responsibleUser:id,name,last_name,email',
            'createdByUser:id,name,last_name,email',
            'updatedByUser:id,name,last_name,email',
        ];
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    private function statusOptions(): array
    {
        return collect(EquipmentItem::statusDefinitions())
            ->map(fn (array $definition, string $status): array => [
                'value' => $status,
                'label' => __($definition['label_key']),
                'description' => __($definition['description_key']),
            ])
            ->values()
            ->all();
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEquipmentItemRequest;
use App\Http\Requests\UpdateEquipmentItemRequest;
use App\Http\Resources\ApiEquipmentResource;
use App\Models\EquipmentItem;
use App\Models\EquipmentItemHistory;
use App\Support\EquipmentHistoryRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $equipmentItems = EquipmentItem::query()
            ->with($this->equipmentRelations())
            ->ordered()
            ->get();

        return response()->json([
            'data' => $equipmentItems
                ->map(fn (EquipmentItem $equipmentItem): array => (new ApiEquipmentResource($equipmentItem))->resolve())
                ->values()
                ->all(),
            'status_options' => $this->statusOptions(),
        ]);
    }

    public function show(Request $request, EquipmentItem $equipmentItem): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        return response()->json([
            'data' => (new ApiEquipmentResource($equipmentItem->load($this->equipmentRelations())))->resolve(),
        ]);
    }

    public function store(
        StoreEquipmentItemRequest $request,
        EquipmentHistoryRecorder $historyRecorder,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $equipmentItem = EquipmentItem::query()->create([
            ...$request->payload(),
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);

        $historyRecorder->recordCreated($equipmentItem, EquipmentItemHistory::SOURCE_API, $user->id);

        return response()->json([
            'message' => __('ui.equipment.created_success'),
            'data' => (new ApiEquipmentResource($equipmentItem->load($this->equipmentRelations())))->resolve(),
        ], 201);
    }

    public function update(
        UpdateEquipmentItemRequest $request,
        EquipmentItem $equipmentItem,
        EquipmentHistoryRecorder $historyRecorder,
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $before = $historyRecorder->snapshot($equipmentItem);

        $equipmentItem->update([
            ...$request->payload(),
            'updated_by_user_id' => $user->id,
        ]);

        $equipmentItem->refresh();

        $historyRecorder->recordUpdated($equipmentItem, $before, EquipmentItemHistory::SOURCE_API, $user->id);

        return response()->json([
            'message' => __('ui.equipment.updated_success'),
            'data' => (new ApiEquipmentResource($equipmentItem->fresh()->load($this->equipmentRelations())))->resolve(),
        ]);
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

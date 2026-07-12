<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreReferenceDirectoryRecordRequest;
use App\Http\Requests\StoreReferenceDirectoryRequest;
use App\Http\Requests\UpdateReferenceDirectoryRecordRequest;
use App\Http\Requests\UpdateReferenceDirectoryRequest;
use App\Http\Resources\ApiReferenceDirectoryRecordResource;
use App\Http\Resources\ApiReferenceDirectoryResource;
use App\Models\PortalWebhook;
use App\Models\ReferenceDirectory;
use App\Models\ReferenceDirectoryRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PortalWebhookReferenceDirectoryController extends Controller
{
    public function index(Request $request, PortalWebhook $portalWebhook): JsonResponse
    {
        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'data' => ReferenceDirectory::query()
                ->withCount('records')
                ->orderBy('name')
                ->get()
                ->map(fn (ReferenceDirectory $directory): array => (new ApiReferenceDirectoryResource($directory))->resolve())
                ->values()
                ->all(),
        ]);
    }

    public function show(
        Request $request,
        PortalWebhook $portalWebhook,
        ReferenceDirectory $referenceDirectory,
    ): JsonResponse {
        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'data' => (new ApiReferenceDirectoryResource($referenceDirectory->load([
                'creator:id,name,last_name,email',
                'updater:id,name,last_name,email',
                'records.creator:id,name,last_name,email',
                'records.updater:id,name,last_name,email',
            ])->loadCount('records')))->resolve(),
        ]);
    }

    public function store(
        StoreReferenceDirectoryRequest $request,
        PortalWebhook $portalWebhook,
    ): JsonResponse {
        $referenceDirectory = ReferenceDirectory::query()->create($request->directoryPayload());

        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'message' => __('ui.directories.created_success'),
            'data' => (new ApiReferenceDirectoryResource($referenceDirectory->loadCount('records')))->resolve(),
        ], 201);
    }

    public function update(
        UpdateReferenceDirectoryRequest $request,
        PortalWebhook $portalWebhook,
        ReferenceDirectory $referenceDirectory,
    ): JsonResponse {
        $referenceDirectory->update($request->directoryPayload());

        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'message' => __('ui.directories.updated_success'),
            'data' => (new ApiReferenceDirectoryResource($referenceDirectory->fresh()->loadCount('records')))->resolve(),
        ]);
    }

    public function destroy(
        Request $request,
        PortalWebhook $portalWebhook,
        ReferenceDirectory $referenceDirectory,
    ): JsonResponse {
        $deletedId = $referenceDirectory->id;
        $referenceDirectory->delete();

        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'message' => __('ui.directories.deleted_success'),
            'data' => [
                'id' => $deletedId,
            ],
        ]);
    }

    public function storeRecord(
        StoreReferenceDirectoryRecordRequest $request,
        PortalWebhook $portalWebhook,
        ReferenceDirectory $referenceDirectory,
    ): JsonResponse {
        $record = $referenceDirectory->records()->create($request->recordPayload());

        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'message' => __('ui.directories.record_created_success'),
            'data' => (new ApiReferenceDirectoryRecordResource($record))->resolve(),
        ], 201);
    }

    public function updateRecord(
        UpdateReferenceDirectoryRecordRequest $request,
        PortalWebhook $portalWebhook,
        ReferenceDirectory $referenceDirectory,
        ReferenceDirectoryRecord $referenceDirectoryRecord,
    ): JsonResponse {
        $record = $this->resolveDirectoryRecord($referenceDirectory, $referenceDirectoryRecord);
        $record->update($request->recordPayload());

        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'message' => __('ui.directories.record_updated_success'),
            'data' => (new ApiReferenceDirectoryRecordResource($record->fresh()))->resolve(),
        ]);
    }

    public function destroyRecord(
        Request $request,
        PortalWebhook $portalWebhook,
        ReferenceDirectory $referenceDirectory,
        ReferenceDirectoryRecord $referenceDirectoryRecord,
    ): JsonResponse {
        $record = $this->resolveDirectoryRecord($referenceDirectory, $referenceDirectoryRecord);
        $deletedId = $record->id;
        $record->delete();

        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'message' => __('ui.directories.record_deleted_success'),
            'data' => [
                'id' => $deletedId,
            ],
        ]);
    }

    private function resolveDirectoryRecord(
        ReferenceDirectory $referenceDirectory,
        ReferenceDirectoryRecord $referenceDirectoryRecord,
    ): ReferenceDirectoryRecord {
        abort_unless($referenceDirectoryRecord->reference_directory_id === $referenceDirectory->id, 404);

        return $referenceDirectoryRecord;
    }
}

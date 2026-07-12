<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReferenceDirectoryRecordRequest;
use App\Http\Requests\StoreReferenceDirectoryRequest;
use App\Http\Requests\UpdateReferenceDirectoryRecordRequest;
use App\Http\Requests\UpdateReferenceDirectoryRequest;
use App\Http\Resources\ApiReferenceDirectoryRecordResource;
use App\Http\Resources\ApiReferenceDirectoryResource;
use App\Models\ReferenceDirectory;
use App\Models\ReferenceDirectoryRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferenceDirectoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->canAccessDirectories() ?? false, 403);

        return response()->json([
            'data' => ReferenceDirectory::query()
                ->withCount('records')
                ->orderBy('name')
                ->get()
                ->map(fn (ReferenceDirectory $directory): array => (new ApiReferenceDirectoryResource($directory))->resolve())
                ->values()
                ->all(),
        ]);
    }

    public function show(Request $request, ReferenceDirectory $referenceDirectory): JsonResponse
    {
        abort_unless($request->user()?->canAccessDirectories() ?? false, 403);

        return response()->json([
            'data' => (new ApiReferenceDirectoryResource($referenceDirectory->load([
                'creator:id,name,last_name,email',
                'updater:id,name,last_name,email',
                'records.creator:id,name,last_name,email',
                'records.updater:id,name,last_name,email',
            ])->loadCount('records')))->resolve(),
        ]);
    }

    public function store(StoreReferenceDirectoryRequest $request): JsonResponse
    {
        $user = $request->user();

        $referenceDirectory = ReferenceDirectory::query()->create([
            ...$request->directoryPayload(),
            'created_by_user_id' => $user?->id,
            'updated_by_user_id' => $user?->id,
        ]);

        return response()->json([
            'message' => __('ui.directories.created_success'),
            'data' => (new ApiReferenceDirectoryResource($referenceDirectory->loadCount('records')))->resolve(),
        ], 201);
    }

    public function update(
        UpdateReferenceDirectoryRequest $request,
        ReferenceDirectory $referenceDirectory,
    ): JsonResponse {
        $referenceDirectory->update([
            ...$request->directoryPayload(),
            'updated_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => __('ui.directories.updated_success'),
            'data' => (new ApiReferenceDirectoryResource($referenceDirectory->fresh()->loadCount('records')))->resolve(),
        ]);
    }

    public function destroy(Request $request, ReferenceDirectory $referenceDirectory): JsonResponse
    {
        abort_unless($request->user()?->canManageDirectories() ?? false, 403);

        $deletedId = $referenceDirectory->id;
        $referenceDirectory->delete();

        return response()->json([
            'message' => __('ui.directories.deleted_success'),
            'data' => [
                'id' => $deletedId,
            ],
        ]);
    }

    public function storeRecord(
        StoreReferenceDirectoryRecordRequest $request,
        ReferenceDirectory $referenceDirectory,
    ): JsonResponse {
        $user = $request->user();

        $record = $referenceDirectory->records()->create([
            ...$request->recordPayload(),
            'created_by_user_id' => $user?->id,
            'updated_by_user_id' => $user?->id,
        ]);

        return response()->json([
            'message' => __('ui.directories.record_created_success'),
            'data' => (new ApiReferenceDirectoryRecordResource($record))->resolve(),
        ], 201);
    }

    public function updateRecord(
        UpdateReferenceDirectoryRecordRequest $request,
        ReferenceDirectory $referenceDirectory,
        ReferenceDirectoryRecord $referenceDirectoryRecord,
    ): JsonResponse {
        $record = $this->resolveDirectoryRecord($referenceDirectory, $referenceDirectoryRecord);

        $record->update([
            ...$request->recordPayload(),
            'updated_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => __('ui.directories.record_updated_success'),
            'data' => (new ApiReferenceDirectoryRecordResource($record->fresh()))->resolve(),
        ]);
    }

    public function destroyRecord(
        Request $request,
        ReferenceDirectory $referenceDirectory,
        ReferenceDirectoryRecord $referenceDirectoryRecord,
    ): JsonResponse {
        abort_unless($request->user()?->canManageDirectories() ?? false, 403);

        $record = $this->resolveDirectoryRecord($referenceDirectory, $referenceDirectoryRecord);
        $deletedId = $record->id;
        $record->delete();

        return response()->json([
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

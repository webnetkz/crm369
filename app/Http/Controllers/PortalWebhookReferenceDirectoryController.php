<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportReferenceDirectoryRecordsRequest;
use App\Http\Requests\StoreReferenceDirectoryRecordRequest;
use App\Http\Requests\StoreReferenceDirectoryRequest;
use App\Http\Requests\UpdateReferenceDirectoryRecordRequest;
use App\Http\Requests\UpdateReferenceDirectoryRequest;
use App\Http\Resources\ApiReferenceDirectoryRecordResource;
use App\Http\Resources\ApiReferenceDirectoryResource;
use App\Models\PortalWebhook;
use App\Models\ReferenceDirectory;
use App\Models\ReferenceDirectoryRecord;
use App\Support\ReferenceDirectoryCsvService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function exportCsv(
        Request $request,
        PortalWebhook $portalWebhook,
        ReferenceDirectory $referenceDirectory,
        ReferenceDirectoryCsvService $referenceDirectoryCsvService,
    ): StreamedResponse {
        $this->ensureCsvExchangeEnabled($referenceDirectory);

        return $referenceDirectoryCsvService->downloadRecords(
            $referenceDirectory,
            $referenceDirectory->slug.'-records-'.now()->format('Y-m-d-H-i-s').'.csv',
            $this->resolveCsvDelimiter($request),
        );
    }

    public function downloadCsvTemplate(
        Request $request,
        PortalWebhook $portalWebhook,
        ReferenceDirectory $referenceDirectory,
        ReferenceDirectoryCsvService $referenceDirectoryCsvService,
    ): StreamedResponse {
        $this->ensureCsvExchangeEnabled($referenceDirectory);

        return $referenceDirectoryCsvService->downloadTemplate(
            $referenceDirectory,
            $referenceDirectory->slug.'-template-'.now()->format('Y-m-d-H-i-s').'.csv',
            $this->resolveCsvDelimiter($request),
        );
    }

    public function importCsv(
        ImportReferenceDirectoryRecordsRequest $request,
        PortalWebhook $portalWebhook,
        ReferenceDirectory $referenceDirectory,
        ReferenceDirectoryCsvService $referenceDirectoryCsvService,
    ): JsonResponse {
        $this->ensureCsvExchangeEnabled($referenceDirectory);

        $importedCount = $referenceDirectoryCsvService->import(
            $referenceDirectory,
            $request->uploadedFile(),
            null,
            $request->delimiter(),
        );

        return response()->json([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'message' => __('ui.directories.csv_import_success', ['count' => $importedCount]),
            'data' => [
                'imported_count' => $importedCount,
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

    private function resolveCsvDelimiter(Request $request): string
    {
        $request->validate([
            'delimiter' => ['nullable', 'string', 'max:10'],
        ]);

        $delimiter = ReferenceDirectoryCsvService::normalizeDelimiter($request->input('delimiter'));

        abort_if($delimiter === null, 422, __('ui.directories.csv_delimiter_invalid'));

        return $delimiter;
    }

    private function ensureCsvExchangeEnabled(ReferenceDirectory $referenceDirectory): void
    {
        abort_unless($referenceDirectory->csv_exchange_enabled, 403, __('ui.directories.csv_disabled'));
    }
}

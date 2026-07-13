<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportReferenceDirectoryRecordsRequest;
use App\Http\Requests\StoreReferenceDirectoryRecordRequest;
use App\Http\Requests\StoreReferenceDirectoryRequest;
use App\Http\Requests\UpdateReferenceDirectoryRecordRequest;
use App\Http\Requests\UpdateReferenceDirectoryRequest;
use App\Http\Resources\ApiReferenceDirectoryResource;
use App\Models\ReferenceDirectory;
use App\Models\ReferenceDirectoryRecord;
use App\Support\ReferenceDirectoryCsvService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReferenceDirectoryController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->canAccessDirectories() ?? false, 403);

        return Inertia::render('directories/Index', [
            'directories' => $this->directoriesPayload(),
            'activeDirectory' => null,
            'columnTypes' => $this->columnTypesPayload(),
            'can' => [
                'manageDirectories' => $request->user()?->canManageDirectories() ?? false,
            ],
        ]);
    }

    public function show(Request $request, ReferenceDirectory $referenceDirectory): Response
    {
        abort_unless($request->user()?->canAccessDirectories() ?? false, 403);

        return Inertia::render('directories/Index', [
            'directories' => $this->directoriesPayload(),
            'activeDirectory' => $this->directoryPayload($referenceDirectory),
            'columnTypes' => $this->columnTypesPayload(),
            'can' => [
                'manageDirectories' => $request->user()?->canManageDirectories() ?? false,
            ],
        ]);
    }

    public function store(StoreReferenceDirectoryRequest $request): RedirectResponse
    {
        $user = $request->user();

        $referenceDirectory = ReferenceDirectory::query()->create([
            ...$request->directoryPayload(),
            'created_by_user_id' => $user?->id,
            'updated_by_user_id' => $user?->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.directories.created_success'),
        ]);

        return to_route('directories.show', $referenceDirectory);
    }

    public function update(UpdateReferenceDirectoryRequest $request, ReferenceDirectory $referenceDirectory): RedirectResponse
    {
        $referenceDirectory->update([
            ...$request->directoryPayload(),
            'updated_by_user_id' => $request->user()?->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.directories.updated_success'),
        ]);

        return back();
    }

    public function destroy(Request $request, ReferenceDirectory $referenceDirectory): RedirectResponse
    {
        abort_unless($request->user()?->canManageDirectories() ?? false, 403);

        $referenceDirectory->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.directories.deleted_success'),
        ]);

        return to_route('directories.index');
    }

    public function storeRecord(
        StoreReferenceDirectoryRecordRequest $request,
        ReferenceDirectory $referenceDirectory,
    ): RedirectResponse {
        $user = $request->user();

        $referenceDirectory->records()->create([
            ...$request->recordPayload(),
            'created_by_user_id' => $user?->id,
            'updated_by_user_id' => $user?->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.directories.record_created_success'),
        ]);

        return back();
    }

    public function updateRecord(
        UpdateReferenceDirectoryRecordRequest $request,
        ReferenceDirectory $referenceDirectory,
        ReferenceDirectoryRecord $referenceDirectoryRecord,
    ): RedirectResponse {
        $record = $this->resolveDirectoryRecord($referenceDirectory, $referenceDirectoryRecord);

        $record->update([
            ...$request->recordPayload(),
            'updated_by_user_id' => $request->user()?->id,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.directories.record_updated_success'),
        ]);

        return back();
    }

    public function destroyRecord(
        Request $request,
        ReferenceDirectory $referenceDirectory,
        ReferenceDirectoryRecord $referenceDirectoryRecord,
    ): RedirectResponse {
        abort_unless($request->user()?->canManageDirectories() ?? false, 403);

        $record = $this->resolveDirectoryRecord($referenceDirectory, $referenceDirectoryRecord);
        $record->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.directories.record_deleted_success'),
        ]);

        return back();
    }

    public function exportCsv(
        Request $request,
        ReferenceDirectory $referenceDirectory,
        ReferenceDirectoryCsvService $referenceDirectoryCsvService,
    ): StreamedResponse {
        abort_unless($request->user()?->canAccessDirectories() ?? false, 403);
        $this->ensureCsvExchangeEnabled($referenceDirectory);

        return $referenceDirectoryCsvService->downloadRecords(
            $referenceDirectory,
            $referenceDirectory->slug.'-records-'.now()->format('Y-m-d-H-i-s').'.csv',
            $this->resolveCsvDelimiter($request),
        );
    }

    public function downloadCsvTemplate(
        Request $request,
        ReferenceDirectory $referenceDirectory,
        ReferenceDirectoryCsvService $referenceDirectoryCsvService,
    ): StreamedResponse {
        abort_unless($request->user()?->canAccessDirectories() ?? false, 403);
        $this->ensureCsvExchangeEnabled($referenceDirectory);

        return $referenceDirectoryCsvService->downloadTemplate(
            $referenceDirectory,
            $referenceDirectory->slug.'-template-'.now()->format('Y-m-d-H-i-s').'.csv',
            $this->resolveCsvDelimiter($request),
        );
    }

    public function importCsv(
        ImportReferenceDirectoryRecordsRequest $request,
        ReferenceDirectory $referenceDirectory,
        ReferenceDirectoryCsvService $referenceDirectoryCsvService,
    ): RedirectResponse {
        $this->ensureCsvExchangeEnabled($referenceDirectory);

        $importedCount = $referenceDirectoryCsvService->import(
            $referenceDirectory,
            $request->uploadedFile(),
            $request->user(),
            $request->delimiter(),
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.directories.csv_import_success', ['count' => $importedCount]),
        ]);

        return back();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function directoriesPayload(): array
    {
        return ReferenceDirectory::query()
            ->withCount('records')
            ->orderBy('name')
            ->get()
            ->map(fn (ReferenceDirectory $directory): array => (new ApiReferenceDirectoryResource($directory))->resolve())
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function columnTypesPayload(): array
    {
        return collect(ReferenceDirectory::availableColumnTypes())
            ->map(fn (string $type): array => [
                'value' => $type,
                'label' => __("ui.directories.column_type_{$type}"),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function directoryPayload(ReferenceDirectory $referenceDirectory): array
    {
        return (new ApiReferenceDirectoryResource($referenceDirectory->load([
            'creator:id,name,last_name,email',
            'updater:id,name,last_name,email',
            'records.creator:id,name,last_name,email',
            'records.updater:id,name,last_name,email',
        ])->loadCount('records')))->resolve();
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

        if ($delimiter === null) {
            throw ValidationException::withMessages([
                'delimiter' => __('ui.directories.csv_delimiter_invalid'),
            ]);
        }

        return $delimiter;
    }

    private function ensureCsvExchangeEnabled(ReferenceDirectory $referenceDirectory): void
    {
        abort_unless($referenceDirectory->csv_exchange_enabled, 403, __('ui.directories.csv_disabled'));
    }
}

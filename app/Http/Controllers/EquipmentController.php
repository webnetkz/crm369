<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportEquipmentItemsRequest;
use App\Http\Requests\StoreEquipmentItemRequest;
use App\Http\Requests\UpdateEquipmentItemRequest;
use App\Models\EquipmentItem;
use App\Models\EquipmentItemHistory;
use App\Support\CsvDelimiter;
use App\Support\EquipmentCsvService;
use App\Support\EquipmentHistoryRecorder;
use App\Support\EquipmentPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EquipmentController extends Controller
{
    public function index(Request $request, EquipmentPageData $pageData): Response
    {
        $activeDialog = in_array($request->string('dialog')->toString(), ['details', 'edit', 'history'], true)
            ? $request->string('dialog')->toString()
            : null;
        $status = $request->string('status')->toString();
        $activeStatus = in_array($status, EquipmentItem::availableStatuses(), true) ? $status : '';

        return Inertia::render('equipment/Index', $pageData->build(
            $request->user(),
            $request->integer('equipment') > 0 ? $request->integer('equipment') : null,
            trim((string) $request->input('search', '')),
            $activeDialog,
            $activeStatus,
        ));
    }

    public function store(
        StoreEquipmentItemRequest $request,
        EquipmentHistoryRecorder $historyRecorder,
    ): RedirectResponse {
        $equipmentItem = EquipmentItem::query()->create([
            ...$request->payload(),
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
        ]);

        $historyRecorder->recordCreated(
            $equipmentItem,
            source: EquipmentItemHistory::SOURCE_WEB,
            actorUserId: $request->user()->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.equipment.created_success')]);

        return back();
    }

    public function update(
        UpdateEquipmentItemRequest $request,
        EquipmentItem $equipmentItem,
        EquipmentHistoryRecorder $historyRecorder,
    ): RedirectResponse {
        $before = $historyRecorder->snapshot($equipmentItem);

        $equipmentItem->update([
            ...$request->payload(),
            'updated_by_user_id' => $request->user()->id,
        ]);

        $equipmentItem->refresh();

        $historyRecorder->recordUpdated(
            $equipmentItem,
            before: $before,
            source: EquipmentItemHistory::SOURCE_WEB,
            actorUserId: $request->user()->id,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.equipment.updated_success')]);

        return back();
    }

    public function exportCsv(Request $request, EquipmentCsvService $equipmentCsvService): StreamedResponse
    {
        $items = EquipmentItem::query()
            ->with([
                'issuedToUser:id,email',
                'responsibleUser:id,email',
            ])
            ->ordered()
            ->get();

        return $equipmentCsvService->download(
            $items,
            'equipment-'.now()->format('Y-m-d-H-i-s').'.csv',
            $this->resolveCsvDelimiter($request),
        );
    }

    public function downloadCsvTemplate(Request $request, EquipmentCsvService $equipmentCsvService): StreamedResponse
    {
        return $equipmentCsvService->downloadTemplate(
            'equipment-template-'.now()->format('Y-m-d-H-i-s').'.csv',
            $this->resolveCsvDelimiter($request),
        );
    }

    public function importCsv(
        ImportEquipmentItemsRequest $request,
        EquipmentCsvService $equipmentCsvService,
        EquipmentHistoryRecorder $historyRecorder,
    ): RedirectResponse {
        $importedCount = $equipmentCsvService->import(
            $request->uploadedFile(),
            $request->user(),
            $request->delimiter(),
            $historyRecorder,
        );

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('ui.equipment.csv_import_success', ['count' => $importedCount]),
        ]);

        return back();
    }

    private function resolveCsvDelimiter(Request $request): string
    {
        $request->validate([
            'delimiter' => ['nullable', 'string', 'max:10'],
        ]);

        $delimiter = CsvDelimiter::normalize($request->input('delimiter'));

        abort_if($delimiter === null, 422, __('ui.equipment.csv_delimiter_invalid'));

        return $delimiter;
    }
}

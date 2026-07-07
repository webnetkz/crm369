<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEquipmentItemRequest;
use App\Http\Requests\UpdateEquipmentItemRequest;
use App\Models\EquipmentItem;
use App\Support\EquipmentPageData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EquipmentController extends Controller
{
    public function index(Request $request, EquipmentPageData $pageData): Response
    {
        return Inertia::render('equipment/Index', $pageData->build($request->user()));
    }

    public function store(StoreEquipmentItemRequest $request): RedirectResponse
    {
        EquipmentItem::query()->create([
            ...$request->payload(),
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.equipment.created_success')]);

        return back();
    }

    public function update(UpdateEquipmentItemRequest $request, EquipmentItem $equipmentItem): RedirectResponse
    {
        $equipmentItem->update([
            ...$request->payload(),
            'updated_by_user_id' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.equipment.updated_success')]);

        return back();
    }
}

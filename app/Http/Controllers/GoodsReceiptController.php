<?php

namespace App\Http\Controllers;

use App\Actions\Procurement\RecordGoodsReceipt;
use App\Http\Requests\StoreGoodsReceiptRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class GoodsReceiptController extends Controller
{
    public function store(StoreGoodsReceiptRequest $request, RecordGoodsReceipt $recordGoodsReceipt): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $recordGoodsReceipt->handle($request->payload(), $request->itemPayloads(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.procurement.receipt_created_success')]);

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Procurement\CreatePurchaseOrder;
use App\Actions\Procurement\SendPurchaseOrder;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Models\PurchaseOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PurchaseOrderController extends Controller
{
    public function store(StorePurchaseOrderRequest $request, CreatePurchaseOrder $createPurchaseOrder): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $createPurchaseOrder->handle($request->payload(), $request->quotationIds(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.procurement.order_created_success')]);

        return back();
    }

    public function send(Request $request, PurchaseOrder $purchaseOrder, SendPurchaseOrder $sendPurchaseOrder): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user?->canManageProcurementOrders(), 403);

        $sendPurchaseOrder->handle($purchaseOrder, $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.procurement.order_sent_success')]);

        return back();
    }
}

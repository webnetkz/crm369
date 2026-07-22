<?php

namespace App\Http\Controllers;

use App\Actions\Procurement\RecordPurchaseReturn;
use App\Http\Requests\StorePurchaseReturnRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PurchaseReturnController extends Controller
{
    public function store(StorePurchaseReturnRequest $request, RecordPurchaseReturn $recordPurchaseReturn): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $recordPurchaseReturn->handle($request->payload(), $request->itemPayloads(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.procurement.return_created_success')]);

        return back();
    }
}

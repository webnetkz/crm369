<?php

namespace App\Http\Controllers;

use App\Actions\Procurement\CreatePurchaseRequest;
use App\Actions\Procurement\DecidePurchaseRequest;
use App\Http\Requests\DecidePurchaseRequestRequest;
use App\Http\Requests\StorePurchaseRequest;
use App\Models\PurchaseRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PurchaseRequestController extends Controller
{
    public function store(StorePurchaseRequest $request, CreatePurchaseRequest $createPurchaseRequest): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $createPurchaseRequest->handle($request->payload(), $request->itemPayloads(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.procurement.created_request_success')]);

        return back();
    }

    public function decide(
        DecidePurchaseRequestRequest $request,
        PurchaseRequest $purchaseRequest,
        DecidePurchaseRequest $decidePurchaseRequest,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $decidePurchaseRequest->handle(
            $purchaseRequest,
            $request->decision(),
            $request->rejectionReason(),
            $user,
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.procurement.decision_success')]);

        return back();
    }
}

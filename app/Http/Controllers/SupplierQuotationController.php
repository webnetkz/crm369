<?php

namespace App\Http\Controllers;

use App\Actions\Procurement\SaveSupplierQuotation;
use App\Http\Requests\StoreSupplierQuotationRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SupplierQuotationController extends Controller
{
    public function store(
        StoreSupplierQuotationRequest $request,
        SaveSupplierQuotation $saveSupplierQuotation,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $saveSupplierQuotation->handle($request->payload(), $user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.procurement.quotation_saved_success')]);

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        Supplier::query()->create([
            ...$request->payload(),
            'created_by_user_id' => $user->id,
            'updated_by_user_id' => $user->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.procurement.supplier_created_success')]);

        return back();
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        $supplier->update([
            ...$request->payload(),
            'updated_by_user_id' => $user->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('ui.procurement.supplier_updated_success')]);

        return back();
    }
}

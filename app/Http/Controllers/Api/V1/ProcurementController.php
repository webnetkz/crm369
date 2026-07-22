<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Procurement\CreatePurchaseOrder;
use App\Actions\Procurement\CreatePurchaseRequest;
use App\Actions\Procurement\DecidePurchaseRequest;
use App\Actions\Procurement\RecordGoodsReceipt;
use App\Actions\Procurement\RecordPurchaseReturn;
use App\Actions\Procurement\SaveSupplierQuotation;
use App\Actions\Procurement\SendPurchaseOrder;
use App\Http\Controllers\Controller;
use App\Http\Requests\DecidePurchaseRequestRequest;
use App\Http\Requests\StoreGoodsReceiptRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\StorePurchaseReturnRequest;
use App\Http\Requests\StoreSupplierQuotationRequest;
use App\Http\Requests\StoreSupplierRequest;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Support\ProcurementPageData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProcurementController extends Controller
{
    public function index(Request $request, ProcurementPageData $procurementPageData): JsonResponse
    {
        return response()->json([
            'data' => $procurementPageData->index($request->user()),
        ]);
    }

    public function storeSupplier(StoreSupplierRequest $request): JsonResponse
    {
        $supplier = Supplier::query()->create([
            ...$request->payload(),
            'created_by_user_id' => $request->user()?->id,
            'updated_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => __('ui.procurement.supplier_created_success'),
            'data' => $supplier,
        ], 201);
    }

    public function updateSupplier(StoreSupplierRequest $request, Supplier $supplier): JsonResponse
    {
        $supplier->update([
            ...$request->payload(),
            'updated_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => __('ui.procurement.supplier_updated_success'),
            'data' => $supplier->refresh(),
        ]);
    }

    public function storeRequest(StorePurchaseRequest $request, CreatePurchaseRequest $action): JsonResponse
    {
        $purchaseRequest = $action->handle($request->payload(), $request->itemPayloads(), $request->user());

        return response()->json([
            'message' => __('ui.procurement.created_request_success'),
            'data' => $purchaseRequest,
        ], 201);
    }

    public function decideRequest(
        DecidePurchaseRequestRequest $request,
        PurchaseRequest $purchaseRequest,
        DecidePurchaseRequest $action,
    ): JsonResponse {
        $purchaseRequest = $action->handle(
            $purchaseRequest,
            $request->decision(),
            $request->rejectionReason(),
            $request->user(),
        );

        return response()->json([
            'message' => __('ui.procurement.decision_success'),
            'data' => $purchaseRequest,
        ]);
    }

    public function storeQuotation(StoreSupplierQuotationRequest $request, SaveSupplierQuotation $action): JsonResponse
    {
        $quotation = $action->handle($request->payload(), $request->user());

        return response()->json([
            'message' => __('ui.procurement.quotation_saved_success'),
            'data' => $quotation,
        ], $quotation->wasRecentlyCreated ? 201 : 200);
    }

    public function storeOrder(StorePurchaseOrderRequest $request, CreatePurchaseOrder $action): JsonResponse
    {
        $purchaseOrder = $action->handle($request->payload(), $request->quotationIds(), $request->user());

        return response()->json([
            'message' => __('ui.procurement.order_created_success'),
            'data' => $purchaseOrder,
        ], 201);
    }

    public function sendOrder(Request $request, PurchaseOrder $purchaseOrder, SendPurchaseOrder $action): JsonResponse
    {
        $purchaseOrder = $action->handle($purchaseOrder, $request->user());

        return response()->json([
            'message' => __('ui.procurement.order_sent_success'),
            'data' => $purchaseOrder,
        ]);
    }

    public function storeReceipt(StoreGoodsReceiptRequest $request, RecordGoodsReceipt $action): JsonResponse
    {
        $receipt = $action->handle($request->payload(), $request->itemPayloads(), $request->user());

        return response()->json([
            'message' => __('ui.procurement.receipt_created_success'),
            'data' => $receipt,
        ], 201);
    }

    public function storeReturn(StorePurchaseReturnRequest $request, RecordPurchaseReturn $action): JsonResponse
    {
        $return = $action->handle($request->payload(), $request->itemPayloads(), $request->user());

        return response()->json([
            'message' => __('ui.procurement.return_created_success'),
            'data' => $return,
        ], 201);
    }
}

<?php

namespace App\Http\Controllers;

use App\Actions\Procurement\CreatePurchaseOrder;
use App\Actions\Procurement\CreatePurchaseRequest;
use App\Actions\Procurement\DecidePurchaseRequest;
use App\Actions\Procurement\RecordGoodsReceipt;
use App\Actions\Procurement\RecordPurchaseReturn;
use App\Actions\Procurement\SaveSupplierQuotation;
use App\Actions\Procurement\SendPurchaseOrder;
use App\Http\Requests\DecidePurchaseRequestRequest;
use App\Http\Requests\StoreGoodsReceiptRequest;
use App\Http\Requests\StorePurchaseOrderRequest;
use App\Http\Requests\StorePurchaseRequest;
use App\Http\Requests\StorePurchaseReturnRequest;
use App\Http\Requests\StoreSupplierQuotationRequest;
use App\Http\Requests\StoreSupplierRequest;
use App\Models\PortalWebhook;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequest;
use App\Models\Supplier;
use App\Support\ProcurementPageData;
use Illuminate\Http\JsonResponse;

class PortalWebhookProcurementController extends Controller
{
    public function index(PortalWebhook $portalWebhook, ProcurementPageData $procurementPageData): JsonResponse
    {
        return $this->response($portalWebhook, $procurementPageData->index(null));
    }

    public function storeSupplier(StoreSupplierRequest $request, PortalWebhook $portalWebhook): JsonResponse
    {
        $supplier = Supplier::query()->create([
            ...$request->payload(),
            'created_by_user_id' => $portalWebhook->created_by_user_id,
            'updated_by_user_id' => $portalWebhook->created_by_user_id,
        ]);

        return $this->response($portalWebhook, $supplier, __('ui.procurement.supplier_created_success'), 201);
    }

    public function updateSupplier(
        StoreSupplierRequest $request,
        PortalWebhook $portalWebhook,
        Supplier $supplier,
    ): JsonResponse {
        $supplier->update([
            ...$request->payload(),
            'updated_by_user_id' => $portalWebhook->created_by_user_id,
        ]);

        return $this->response($portalWebhook, $supplier->refresh(), __('ui.procurement.supplier_updated_success'));
    }

    public function storeRequest(
        StorePurchaseRequest $request,
        PortalWebhook $portalWebhook,
        CreatePurchaseRequest $action,
    ): JsonResponse {
        $purchaseRequest = $action->handle($request->payload(), $request->itemPayloads(), $portalWebhook->creator);

        return $this->response($portalWebhook, $purchaseRequest, __('ui.procurement.created_request_success'), 201);
    }

    public function decideRequest(
        DecidePurchaseRequestRequest $request,
        PortalWebhook $portalWebhook,
        PurchaseRequest $purchaseRequest,
        DecidePurchaseRequest $action,
    ): JsonResponse {
        $purchaseRequest = $action->handle(
            $purchaseRequest,
            $request->decision(),
            $request->rejectionReason(),
            $portalWebhook->creator,
        );

        return $this->response($portalWebhook, $purchaseRequest, __('ui.procurement.decision_success'));
    }

    public function storeQuotation(
        StoreSupplierQuotationRequest $request,
        PortalWebhook $portalWebhook,
        SaveSupplierQuotation $action,
    ): JsonResponse {
        $quotation = $action->handle($request->payload(), $portalWebhook->creator);

        return $this->response(
            $portalWebhook,
            $quotation,
            __('ui.procurement.quotation_saved_success'),
            $quotation->wasRecentlyCreated ? 201 : 200,
        );
    }

    public function storeOrder(
        StorePurchaseOrderRequest $request,
        PortalWebhook $portalWebhook,
        CreatePurchaseOrder $action,
    ): JsonResponse {
        $purchaseOrder = $action->handle($request->payload(), $request->quotationIds(), $portalWebhook->creator);

        return $this->response($portalWebhook, $purchaseOrder, __('ui.procurement.order_created_success'), 201);
    }

    public function sendOrder(
        PortalWebhook $portalWebhook,
        PurchaseOrder $purchaseOrder,
        SendPurchaseOrder $action,
    ): JsonResponse {
        $purchaseOrder = $action->handle($purchaseOrder, $portalWebhook->creator);

        return $this->response($portalWebhook, $purchaseOrder, __('ui.procurement.order_sent_success'));
    }

    public function storeReceipt(
        StoreGoodsReceiptRequest $request,
        PortalWebhook $portalWebhook,
        RecordGoodsReceipt $action,
    ): JsonResponse {
        $receipt = $action->handle($request->payload(), $request->itemPayloads(), $portalWebhook->creator);

        return $this->response($portalWebhook, $receipt, __('ui.procurement.receipt_created_success'), 201);
    }

    public function storeReturn(
        StorePurchaseReturnRequest $request,
        PortalWebhook $portalWebhook,
        RecordPurchaseReturn $action,
    ): JsonResponse {
        $return = $action->handle($request->payload(), $request->itemPayloads(), $portalWebhook->creator);

        return $this->response($portalWebhook, $return, __('ui.procurement.return_created_success'), 201);
    }

    private function response(
        PortalWebhook $portalWebhook,
        mixed $data,
        ?string $message = null,
        int $status = 200,
    ): JsonResponse {
        return response()->json(array_filter([
            'webhook' => [
                'id' => $portalWebhook->id,
                'name' => $portalWebhook->name,
            ],
            'message' => $message,
            'data' => $data,
        ], fn (mixed $value): bool => $value !== null), $status);
    }
}

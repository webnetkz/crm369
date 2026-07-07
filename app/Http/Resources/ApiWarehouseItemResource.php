<?php

namespace App\Http\Resources;

use App\Models\WarehouseItem;
use App\Support\QrCodeResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WarehouseItem */
class ApiWarehouseItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return app(QrCodeResolver::class)->warehouseItemPayload($this->resource);
    }
}

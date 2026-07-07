<?php

namespace App\Http\Resources;

use App\Models\TsdQrScan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin TsdQrScan */
class ApiTsdQrScanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'qr_code' => $this->qr_code,
            'normalized_qr_code' => $this->normalized_qr_code,
            'source' => $this->source,
            'source_label' => $this->sourceLabel(),
            'device_name' => $this->device_name,
            'location' => $this->location,
            'context' => $this->context,
            'payload' => $this->payload,
            'scanned_at' => $this->scanned_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'actor' => $this->scannedBy
                ? [
                    'id' => $this->scannedBy->id,
                    'name' => $this->scannedBy->name,
                    'last_name' => $this->scannedBy->last_name,
                    'email' => $this->scannedBy->email,
                ]
                : null,
            'webhook' => $this->portalWebhook
                ? [
                    'id' => $this->portalWebhook->id,
                    'name' => $this->portalWebhook->name,
                ]
                : null,
        ];
    }
}

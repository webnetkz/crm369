<?php

namespace App\Actions\Procurement;

use App\Models\PurchaseRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePurchaseRequest
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  array<int, array<string, mixed>>  $items
     */
    public function handle(array $payload, array $items, ?User $user): PurchaseRequest
    {
        return DB::transaction(function () use ($payload, $items, $user): PurchaseRequest {
            $purchaseRequest = PurchaseRequest::query()->create([
                ...$payload,
                'number' => $this->number(),
                'status' => PurchaseRequest::STATUS_PENDING_APPROVAL,
                'requested_by_user_id' => $user?->id,
                'submitted_at' => now(),
            ]);

            $purchaseRequest->items()->createMany($items);

            return $purchaseRequest->load('items');
        }, 3);
    }

    private function number(): string
    {
        return 'PR-'.now()->format('Ymd').'-'.Str::upper(Str::random(8));
    }
}

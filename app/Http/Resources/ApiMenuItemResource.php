<?php

namespace App\Http\Resources;

use App\Models\MenuItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiMenuItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MenuItem $item */
        $item = $this->resource;

        return [
            'id' => $item->id,
            'key' => $item->key,
            'type' => $item->type,
            'title' => $item->displayTitle(),
            'icon' => $item->icon,
            'url' => $item->url,
            'is_global' => $item->is_global,
            'opens_in_new_tab' => $item->opens_in_new_tab,
            'is_visible' => $item->is_visible,
            'sort_order' => $item->sort_order,
        ];
    }
}

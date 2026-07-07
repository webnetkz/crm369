<?php

namespace App\Http\Resources;

use App\Models\Warehouse;
use App\Models\WarehouseColumn;
use App\Models\WarehouseFloor;
use App\Models\WarehousePlace;
use App\Models\WarehouseRow;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Warehouse */
class ApiWarehouseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'area_sqm' => $this->area_sqm,
            'qr_code' => $this->qr_code,
            'row_count' => $this->rowCount(),
            'column_count' => $this->columnCount(),
            'floor_count' => $this->floorCount(),
            'place_count' => $this->placeCount(),
            'rows' => $this->relationLoaded('rows')
                ? $this->rows
                    ->map(fn (WarehouseRow $row): array => [
                        'id' => $row->id,
                        'name' => $row->name,
                        'qr_code' => $row->qr_code,
                        'sort_order' => $row->sort_order,
                        'column_count' => $row->columnCount(),
                        'floor_count' => $row->floorCount(),
                        'place_count' => $row->placeCount(),
                        'columns' => $row->relationLoaded('columns')
                            ? $row->columns
                                ->map(fn (WarehouseColumn $column): array => [
                                    'id' => $column->id,
                                    'name' => $column->name,
                                    'qr_code' => $column->qr_code,
                                    'sort_order' => $column->sort_order,
                                    'floor_count' => $column->floorCount(),
                                    'place_count' => $column->placeCount(),
                                    'floors' => $column->relationLoaded('floors')
                                        ? $column->floors
                                            ->map(fn (WarehouseFloor $floor): array => [
                                                'id' => $floor->id,
                                                'name' => $floor->name,
                                                'qr_code' => $floor->qr_code,
                                                'sort_order' => $floor->sort_order,
                                                'place_count' => $floor->placeCount(),
                                                'places' => $floor->relationLoaded('places')
                                                    ? $floor->places
                                                        ->map(fn (WarehousePlace $place): array => [
                                                            'id' => $place->id,
                                                            'name' => $place->name,
                                                            'qr_code' => $place->qr_code,
                                                            'sort_order' => $place->sort_order,
                                                        ])
                                                        ->values()
                                                        ->all()
                                                    : [],
                                            ])
                                            ->values()
                                            ->all()
                                        : [],
                                ])
                                ->values()
                                ->all()
                            : [],
                    ])
                    ->values()
                    ->all()
                : [],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'created_by' => $this->creator
                ? [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'last_name' => $this->creator->last_name,
                ]
                : null,
            'updated_by' => $this->updater
                ? [
                    'id' => $this->updater->id,
                    'name' => $this->updater->name,
                    'last_name' => $this->updater->last_name,
                ]
                : null,
        ];
    }
}

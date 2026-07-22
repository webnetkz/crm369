<?php

namespace App\Support;

use App\Models\Warehouse;
use App\Models\WarehouseColumn;
use App\Models\WarehouseFloor;
use App\Models\WarehouseItem;
use App\Models\WarehousePlace;
use App\Models\WarehouseRow;
use Illuminate\Database\Eloquent\Builder;

class QrCodeResolver
{
    private const int CONTENTS_LIMIT = 100;

    public function __construct(
        private TsdQrScanManager $tsdQrScanManager,
        private QrCodeSvgGenerator $qrCodeSvgGenerator,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(?string $qrCode): ?array
    {
        if (! is_string($qrCode) || trim($qrCode) === '') {
            return null;
        }

        $resolvedQrCode = trim($qrCode);
        $normalizedQrCode = $this->tsdQrScanManager->normalizeQrCode($resolvedQrCode);

        $warehouseItem = $this->findWarehouseItem($resolvedQrCode, $normalizedQrCode);

        if ($warehouseItem instanceof WarehouseItem) {
            return [
                'matched' => true,
                'input_qr_code' => $resolvedQrCode,
                'normalized_qr_code' => $normalizedQrCode,
                'entity_type' => 'item',
                'entity_type_label' => __('ui.warehouses.entity_item'),
                ...$this->warehouseItemPayload($warehouseItem),
                'qr_code_svg_data_uri' => $this->qrCodeSvgGenerator->dataUri($warehouseItem->qr_code),
                'contents' => [],
                'contents_truncated' => false,
            ];
        }

        $warehouse = $this->findWarehouse($resolvedQrCode, $normalizedQrCode);

        if ($warehouse instanceof Warehouse) {
            $itemCount = $warehouse->itemCount();
            $contents = $this->warehouseContents($warehouse);

            return [
                'matched' => true,
                'input_qr_code' => $resolvedQrCode,
                'normalized_qr_code' => $normalizedQrCode,
                'entity_type' => 'warehouse',
                'entity_type_label' => __('ui.warehouses.entity_warehouse'),
                'title' => $warehouse->name,
                'qr_code' => $warehouse->qr_code,
                'qr_code_svg_data_uri' => $this->qrCodeSvgGenerator->dataUri($warehouse->qr_code),
                'warehouse' => $this->warehouseNode($warehouse),
                'location' => $this->warehouseLocationPayload($warehouse),
                'details' => [
                    'area_sqm' => $warehouse->area_sqm,
                    'row_count' => $warehouse->rowCount(),
                    'column_count' => $warehouse->columnCount(),
                    'floor_count' => $warehouse->floorCount(),
                    'place_count' => $warehouse->placeCount(),
                    'item_count' => $itemCount,
                ],
                'contents' => $contents,
                'contents_truncated' => $itemCount > count($contents),
            ];
        }

        $row = $this->findRow($resolvedQrCode, $normalizedQrCode);

        if ($row instanceof WarehouseRow) {
            $location = $this->warehouseLocationPayload($row->warehouse, $row);
            $itemCount = $row->itemCount();
            $contents = $this->rowContents($row);

            return [
                'matched' => true,
                'input_qr_code' => $resolvedQrCode,
                'normalized_qr_code' => $normalizedQrCode,
                'entity_type' => 'row',
                'entity_type_label' => __('ui.warehouses.entity_row'),
                'title' => $row->name,
                'qr_code' => $row->qr_code,
                'qr_code_svg_data_uri' => $this->qrCodeSvgGenerator->dataUri($row->qr_code),
                'warehouse' => $this->warehouseNode($row->warehouse),
                'location' => $location,
                'details' => [
                    'column_count' => $row->columnCount(),
                    'floor_count' => $row->floorCount(),
                    'place_count' => $row->placeCount(),
                    'item_count' => $itemCount,
                ],
                'contents' => $contents,
                'contents_truncated' => $itemCount > count($contents),
            ];
        }

        $column = $this->findColumn($resolvedQrCode, $normalizedQrCode);

        if ($column instanceof WarehouseColumn) {
            $warehouse = $column->row->warehouse;
            $location = $this->warehouseLocationPayload($warehouse, $column->row, $column);
            $itemCount = $column->itemCount();
            $contents = $this->columnContents($column);

            return [
                'matched' => true,
                'input_qr_code' => $resolvedQrCode,
                'normalized_qr_code' => $normalizedQrCode,
                'entity_type' => 'column',
                'entity_type_label' => __('ui.warehouses.entity_column'),
                'title' => $column->name,
                'qr_code' => $column->qr_code,
                'qr_code_svg_data_uri' => $this->qrCodeSvgGenerator->dataUri($column->qr_code),
                'warehouse' => $this->warehouseNode($warehouse),
                'location' => $location,
                'details' => [
                    'floor_count' => $column->floorCount(),
                    'place_count' => $column->placeCount(),
                    'item_count' => $itemCount,
                ],
                'contents' => $contents,
                'contents_truncated' => $itemCount > count($contents),
            ];
        }

        $floor = $this->findFloor($resolvedQrCode, $normalizedQrCode);

        if ($floor instanceof WarehouseFloor) {
            $warehouse = $floor->column->row->warehouse;
            $location = $this->warehouseLocationPayload($warehouse, $floor->column->row, $floor->column, $floor);
            $itemCount = $floor->itemCount();
            $contents = $this->floorContents($floor);

            return [
                'matched' => true,
                'input_qr_code' => $resolvedQrCode,
                'normalized_qr_code' => $normalizedQrCode,
                'entity_type' => 'floor',
                'entity_type_label' => __('ui.warehouses.entity_floor'),
                'title' => $floor->name,
                'qr_code' => $floor->qr_code,
                'qr_code_svg_data_uri' => $this->qrCodeSvgGenerator->dataUri($floor->qr_code),
                'warehouse' => $this->warehouseNode($warehouse),
                'location' => $location,
                'details' => [
                    'place_count' => $floor->placeCount(),
                    'item_count' => $itemCount,
                ],
                'contents' => $contents,
                'contents_truncated' => $itemCount > count($contents),
            ];
        }

        $place = $this->findPlace($resolvedQrCode, $normalizedQrCode);

        if ($place instanceof WarehousePlace) {
            $warehouse = $place->floor->column->row->warehouse;
            $location = $this->warehouseLocationPayload($warehouse, $place->floor->column->row, $place->floor->column, $place->floor, $place);
            $itemCount = $place->itemCount();
            $contents = $this->placeContents($place);

            return [
                'matched' => true,
                'input_qr_code' => $resolvedQrCode,
                'normalized_qr_code' => $normalizedQrCode,
                'entity_type' => 'place',
                'entity_type_label' => __('ui.warehouses.entity_place'),
                'title' => $place->name,
                'qr_code' => $place->qr_code,
                'qr_code_svg_data_uri' => $this->qrCodeSvgGenerator->dataUri($place->qr_code),
                'warehouse' => $this->warehouseNode($warehouse),
                'location' => $location,
                'details' => [
                    'item_count' => $itemCount,
                ],
                'contents' => $contents,
                'contents_truncated' => $itemCount > count($contents),
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function warehouseItemPayload(WarehouseItem $warehouseItem): array
    {
        $warehouseItem->loadMissing('place.floor.column.row.warehouse');

        $place = $warehouseItem->place;
        $floor = $place->floor;
        $column = $floor->column;
        $row = $column->row;
        $warehouse = $row->warehouse;

        return [
            'id' => $warehouseItem->id,
            'title' => $warehouseItem->name,
            'name' => $warehouseItem->name,
            'sku' => $warehouseItem->sku,
            'qr_code' => $warehouseItem->qr_code,
            'quantity' => $warehouseItem->quantity,
            'notes' => $warehouseItem->notes,
            'warehouse' => $this->warehouseNode($warehouse),
            'place' => [
                'id' => $place->id,
                'name' => $place->name,
                'qr_code' => $place->qr_code,
            ],
            'location' => $this->warehouseLocationPayload($warehouse, $row, $column, $floor, $place),
            'details' => [
                'quantity' => $warehouseItem->quantity,
                'sku' => $warehouseItem->sku,
                'notes' => $warehouseItem->notes,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function warehouseLocationPayload(
        Warehouse $warehouse,
        ?WarehouseRow $row = null,
        ?WarehouseColumn $column = null,
        ?WarehouseFloor $floor = null,
        ?WarehousePlace $place = null,
    ): array {
        $segments = array_values(array_filter([
            $warehouse->name,
            $row?->name,
            $column?->name,
            $floor?->name,
            $place?->name,
        ]));

        return [
            'warehouse' => $warehouse->name,
            'row' => $row?->name,
            'column' => $column?->name,
            'floor' => $floor?->name,
            'place' => $place?->name,
            'path' => implode(' / ', $segments),
            'segments' => $segments,
        ];
    }

    private function findWarehouseItem(string $qrCode, string $normalizedQrCode): ?WarehouseItem
    {
        return $this->matchesQrCode(
            WarehouseItem::query()->with('place.floor.column.row.warehouse'),
            $qrCode,
            $normalizedQrCode,
        )->first();
    }

    private function findWarehouse(string $qrCode, string $normalizedQrCode): ?Warehouse
    {
        return $this->matchesQrCode(Warehouse::query(), $qrCode, $normalizedQrCode)
            ->first();
    }

    private function findRow(string $qrCode, string $normalizedQrCode): ?WarehouseRow
    {
        return $this->matchesQrCode(WarehouseRow::query()->with('warehouse'), $qrCode, $normalizedQrCode)
            ->first();
    }

    private function findColumn(string $qrCode, string $normalizedQrCode): ?WarehouseColumn
    {
        return $this->matchesQrCode(WarehouseColumn::query()->with('row.warehouse'), $qrCode, $normalizedQrCode)
            ->first();
    }

    private function findFloor(string $qrCode, string $normalizedQrCode): ?WarehouseFloor
    {
        return $this->matchesQrCode(WarehouseFloor::query()->with('column.row.warehouse'), $qrCode, $normalizedQrCode)
            ->first();
    }

    private function findPlace(string $qrCode, string $normalizedQrCode): ?WarehousePlace
    {
        return $this->matchesQrCode(WarehousePlace::query()->with('floor.column.row.warehouse'), $qrCode, $normalizedQrCode)
            ->first();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function warehouseContents(Warehouse $warehouse): array
    {
        return $this->serializeContents(
            $this->warehouseItemsQuery()
                ->whereIn(
                    'warehouse_place_id',
                    WarehousePlace::query()
                        ->whereIn(
                            'warehouse_floor_id',
                            WarehouseFloor::query()
                                ->whereIn(
                                    'warehouse_column_id',
                                    WarehouseColumn::query()
                                        ->whereIn('warehouse_row_id', $warehouse->rows()->select('id'))
                                        ->select('id'),
                                )
                                ->select('id'),
                        )
                        ->select('id'),
                )
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rowContents(WarehouseRow $row): array
    {
        return $this->serializeContents(
            $this->warehouseItemsQuery()
                ->whereIn(
                    'warehouse_place_id',
                    WarehousePlace::query()
                        ->whereIn(
                            'warehouse_floor_id',
                            WarehouseFloor::query()
                                ->whereIn('warehouse_column_id', $row->columns()->select('id'))
                                ->select('id'),
                        )
                        ->select('id'),
                )
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function columnContents(WarehouseColumn $column): array
    {
        return $this->serializeContents(
            $this->warehouseItemsQuery()
                ->whereIn(
                    'warehouse_place_id',
                    WarehousePlace::query()
                        ->whereIn('warehouse_floor_id', $column->floors()->select('id'))
                        ->select('id'),
                )
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function floorContents(WarehouseFloor $floor): array
    {
        return $this->serializeContents(
            $this->warehouseItemsQuery()
                ->whereIn('warehouse_place_id', $floor->places()->select('id'))
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function placeContents(WarehousePlace $place): array
    {
        return $this->serializeContents(
            $this->warehouseItemsQuery()->whereBelongsTo($place, 'place')
        );
    }

    /**
     * @return Builder<WarehouseItem>
     */
    private function warehouseItemsQuery(): Builder
    {
        return WarehouseItem::query()->with('place.floor.column.row.warehouse');
    }

    /**
     * @param  Builder<WarehouseItem>  $query
     * @return array<int, array<string, mixed>>
     */
    private function serializeContents(Builder $query): array
    {
        return $query
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::CONTENTS_LIMIT)
            ->get()
            ->map(fn (WarehouseItem $warehouseItem): array => $this->warehouseItemContentPayload($warehouseItem))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function warehouseItemContentPayload(WarehouseItem $warehouseItem): array
    {
        $place = $warehouseItem->place;
        $floor = $place->floor;
        $column = $floor->column;
        $row = $column->row;
        $warehouse = $row->warehouse;

        return [
            'id' => $warehouseItem->id,
            'name' => $warehouseItem->name,
            'sku' => $warehouseItem->sku,
            'quantity' => $warehouseItem->quantity,
            'qr_code' => $warehouseItem->qr_code,
            'location' => $this->warehouseLocationPayload($warehouse, $row, $column, $floor, $place),
        ];
    }

    /**
     * @param  Builder<Warehouse|WarehouseRow|WarehouseColumn|WarehouseFloor|WarehousePlace|WarehouseItem>  $query
     * @return Builder<Warehouse|WarehouseRow|WarehouseColumn|WarehouseFloor|WarehousePlace|WarehouseItem>
     */
    private function matchesQrCode(Builder $query, string $qrCode, string $normalizedQrCode): Builder
    {
        return $query->where(function (Builder $builder) use ($qrCode, $normalizedQrCode): void {
            $builder
                ->where('qr_code', $qrCode)
                ->orWhereRaw("REPLACE(UPPER(qr_code), ' ', '') = ?", [$normalizedQrCode]);
        });
    }

    /**
     * @return array{id: int, name: string, qr_code: string}
     */
    private function warehouseNode(Warehouse $warehouse): array
    {
        return [
            'id' => $warehouse->id,
            'name' => $warehouse->name,
            'qr_code' => $warehouse->qr_code,
        ];
    }
}

<script setup lang="ts">
import { Head, Link, router, setLayoutProps } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Boxes,
    Building2,
    Package,
    QrCode,
    Rows3,
} from '@lucide/vue';
import {
    computed,
    onBeforeUnmount,
    reactive,
    ref,
    watch,
    watchEffect,
} from 'vue';
import Heading from '@/components/Heading.vue';
import PaginationControls from '@/components/PaginationControls.vue';
import { useLanguage } from '@/composables/useLanguage';
import { fetchSameOriginJson } from '@/lib/sameOriginJson';
import {
    index as warehousesIndex,
    show as showWarehouse,
} from '@/routes/warehouses';
import { show as showWarehouseFloor } from '@/routes/warehouses/floors';
import type { PaginatedCollection } from '@/types/ui';

type WarehouseUser = {
    id: number;
    name: string;
    last_name: string | null;
} | null;

type WarehouseSummary = {
    id: number;
    name: string;
    area_sqm: number;
    qr_code: string;
    row_count: number;
    column_count: number;
    floor_count: number;
    place_count: number;
    item_count: number;
    created_by: WarehouseUser;
    updated_by: WarehouseUser;
    created_at: string | null;
    updated_at: string | null;
};

type MapItem = {
    id: number;
    name: string;
    sku: string | null;
    quantity: number;
};

type MapPlace = {
    id: number;
    name: string;
    item_count: number;
    items: MapItem[];
};

type MapFloor = {
    id: number;
    name: string;
    place_count: number;
    item_count: number;
    places: MapPlace[];
};

type MapColumn = {
    id: number;
    name: string;
    floor_count: number;
    place_count: number;
    item_count: number;
    floors: MapFloor[];
};

type MapRow = {
    id: number;
    name: string;
    column_count: number;
    floor_count: number;
    place_count: number;
    item_count: number;
    columns: MapColumn[];
};

type WarehouseMap = {
    rows: MapRow[];
};

type InventoryQrItem = {
    id: number;
    name: string;
    sku: string | null;
    qr_code: string;
    quantity: number;
    location: {
        path: string;
    };
};

const props = defineProps<{
    warehouse: WarehouseSummary;
    map: WarehouseMap;
    inventoryQrCodes: PaginatedCollection<InventoryQrItem>;
    inventoryPerPageOptions: number[];
    filters: {
        items_per_page: number;
    };
}>();

const { language, t } = useLanguage();

const selectedColumnId = ref<number | null>(
    props.map.rows[0]?.columns[0]?.id ?? null,
);
const selectedFloorId = ref<number | null>(
    props.map.rows[0]?.columns[0]?.floors[0]?.id ?? null,
);
const loadedFloorPlaces = reactive(new Map<number, MapPlace[]>());
const loadingFloorId = ref<number | null>(null);
const failedFloorId = ref<number | null>(null);
let floorAbortController: AbortController | null = null;

const formatNumber = (value: number): string => {
    return new Intl.NumberFormat(language.value === 'ru' ? 'ru-RU' : 'en-US', {
        maximumFractionDigits: 2,
    }).format(value);
};

const selectedColumn = computed<MapColumn | null>(() => {
    for (const row of props.map.rows) {
        const match = row.columns.find(
            (column) => column.id === selectedColumnId.value,
        );

        if (match) {
            return match;
        }
    }

    return null;
});

const selectedRow = computed<MapRow | null>(() => {
    return (
        props.map.rows.find((row) =>
            row.columns.some((column) => column.id === selectedColumnId.value),
        ) ?? null
    );
});

const selectedFloor = computed<MapFloor | null>(() => {
    const floor =
        selectedColumn.value?.floors.find(
            (floor) => floor.id === selectedFloorId.value,
        ) ?? null;

    if (!floor) {
        return null;
    }

    return {
        ...floor,
        places: loadedFloorPlaces.get(floor.id) ?? floor.places,
    };
});

const selectedFloorIsLoading = computed(
    () => loadingFloorId.value === selectedFloorId.value,
);
const selectedFloorFailed = computed(
    () => failedFloorId.value === selectedFloorId.value,
);

const loadFloorPlaces = async (
    floorId: number,
    force = false,
): Promise<void> => {
    if (!force && loadedFloorPlaces.has(floorId)) {
        return;
    }

    floorAbortController?.abort();

    const requestController = new AbortController();

    floorAbortController = requestController;
    loadingFloorId.value = floorId;
    failedFloorId.value = null;

    try {
        const response = await fetchSameOriginJson<{
            data: {
                id: number;
                places: MapPlace[];
            };
        }>(
            showWarehouseFloor.url({
                warehouse: props.warehouse.id,
                warehouseFloor: floorId,
            }),
            {
                method: 'GET',
                signal: requestController.signal,
            },
        );

        loadedFloorPlaces.set(response.data.id, response.data.places);
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        console.error(error);
        failedFloorId.value = floorId;
    } finally {
        if (floorAbortController === requestController) {
            floorAbortController = null;
            loadingFloorId.value = null;
        }
    }
};

watchEffect(() => {
    if (!selectedColumn.value && props.map.rows[0]?.columns[0]) {
        selectedColumnId.value = props.map.rows[0].columns[0].id;
    }
});

watch(
    selectedFloorId,
    (floorId) => {
        if (floorId !== null) {
            void loadFloorPlaces(floorId);
        }
    },
    { immediate: true },
);

watchEffect(() => {
    const floors = selectedColumn.value?.floors ?? [];

    if (floors.length === 0) {
        selectedFloorId.value = null;

        return;
    }

    if (!floors.some((floor) => floor.id === selectedFloorId.value)) {
        selectedFloorId.value = floors[0].id;
    }
});

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.warehouses.title,
                href: warehousesIndex(),
            },
            {
                title: props.warehouse.name,
                href: showWarehouse.url(props.warehouse.id),
            },
        ],
    });
});

const updateInventoryPerPage = (value: number): void => {
    router.get(
        showWarehouse.url(props.warehouse.id),
        {
            items_per_page: value,
        },
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        },
    );
};

onBeforeUnmount(() => {
    floorAbortController?.abort();
});
</script>

<template>
    <div>
        <Head :title="`${props.warehouse.name} | ${t.warehouses.title}`" />

        <h1 class="sr-only">{{ props.warehouse.name }}</h1>

        <div class="space-y-8">
            <section
                class="overflow-hidden rounded-3xl border border-border bg-card"
            >
                <div
                    class="grid gap-6 border-b border-border bg-gradient-to-br from-primary/10 via-transparent to-transparent px-6 py-6 lg:grid-cols-[minmax(0,1fr)_320px]"
                >
                    <div class="space-y-4">
                        <Link
                            :href="warehousesIndex.url()"
                            class="inline-flex items-center gap-2 text-sm font-medium text-primary transition-opacity hover:opacity-80"
                        >
                            <ArrowLeft class="size-4" />
                            <span>{{ t.warehouses.back_to_list }}</span>
                        </Link>

                        <div
                            class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                        >
                            <Boxes class="size-4" />
                            {{ t.warehouses.detail_title }}
                        </div>

                        <div class="space-y-2">
                            <h2 class="text-2xl font-semibold tracking-tight">
                                {{ props.warehouse.name }}
                            </h2>
                            <p
                                class="max-w-3xl text-sm leading-6 text-muted-foreground"
                            >
                                {{ t.warehouses.detail_description }}
                            </p>
                        </div>

                        <div
                            class="rounded-2xl border border-border bg-background/80 px-4 py-3 text-sm"
                        >
                            <div class="text-muted-foreground">
                                {{ t.warehouses.warehouse_qr }}
                            </div>
                            <div
                                class="mt-1 font-mono font-semibold text-foreground"
                            >
                                {{ props.warehouse.qr_code }}
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                        <div
                            class="rounded-2xl border border-border bg-background/80 p-4"
                        >
                            <div
                                class="flex items-center gap-2 text-xs tracking-[0.18em] text-muted-foreground uppercase"
                            >
                                <Building2 class="size-4" />
                                {{ t.warehouses.metric_area }}
                            </div>
                            <div
                                class="mt-2 text-2xl font-semibold tracking-tight"
                            >
                                {{ formatNumber(props.warehouse.area_sqm) }} m²
                            </div>
                        </div>

                        <div
                            class="rounded-2xl border border-border bg-background/80 p-4"
                        >
                            <div
                                class="flex items-center gap-2 text-xs tracking-[0.18em] text-muted-foreground uppercase"
                            >
                                <Package class="size-4" />
                                {{ t.warehouses.metric_places }}
                            </div>
                            <div
                                class="mt-2 text-2xl font-semibold tracking-tight"
                            >
                                {{ formatNumber(props.warehouse.place_count) }}
                            </div>
                        </div>

                        <div
                            class="rounded-2xl border border-border bg-background/80 p-4"
                        >
                            <div
                                class="flex items-center gap-2 text-xs tracking-[0.18em] text-muted-foreground uppercase"
                            >
                                <QrCode class="size-4" />
                                {{ t.warehouses.metric_items }}
                            </div>
                            <div
                                class="mt-2 text-2xl font-semibold tracking-tight"
                            >
                                {{ formatNumber(props.warehouse.item_count) }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                <div class="rounded-3xl border border-border bg-card p-6">
                    <Heading
                        variant="small"
                        :title="t.warehouses.map_title"
                        :description="t.warehouses.map_description"
                    />

                    <div class="mt-6 space-y-6">
                        <article
                            v-for="row in props.map.rows"
                            :key="row.id"
                            class="rounded-2xl border border-border bg-background/70 p-4"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-3"
                            >
                                <div>
                                    <div
                                        class="flex items-center gap-2 font-medium"
                                    >
                                        <Rows3 class="size-4 text-primary" />
                                        <span>{{ row.name }}</span>
                                    </div>
                                    <p
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{ t.warehouses.map_rows_description }}
                                    </p>
                                </div>

                                <div class="flex flex-wrap gap-2 text-xs">
                                    <div
                                        class="rounded-full border border-border bg-card px-3 py-1.5"
                                    >
                                        {{ t.warehouses.metric_columns }}:
                                        {{ formatNumber(row.column_count) }}
                                    </div>
                                    <div
                                        class="rounded-full border border-border bg-card px-3 py-1.5"
                                    >
                                        {{ t.warehouses.metric_places }}:
                                        {{ formatNumber(row.place_count) }}
                                    </div>
                                    <div
                                        class="rounded-full border border-border bg-card px-3 py-1.5"
                                    >
                                        {{ t.warehouses.metric_items }}:
                                        {{ formatNumber(row.item_count) }}
                                    </div>
                                </div>
                            </div>

                            <div
                                class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                            >
                                <button
                                    v-for="column in row.columns"
                                    :key="column.id"
                                    type="button"
                                    class="rounded-2xl border px-4 py-4 text-left transition-colors"
                                    :class="
                                        column.id === selectedColumnId
                                            ? 'border-primary bg-primary/10'
                                            : 'border-border bg-card hover:bg-muted/40'
                                    "
                                    @click="selectedColumnId = column.id"
                                >
                                    <div class="font-medium">
                                        {{ column.name }}
                                    </div>
                                    <div
                                        class="mt-3 flex flex-wrap gap-2 text-xs text-muted-foreground"
                                    >
                                        <span
                                            >{{ t.warehouses.metric_floors }}:
                                            {{
                                                formatNumber(column.floor_count)
                                            }}</span
                                        >
                                        <span
                                            >{{ t.warehouses.metric_places }}:
                                            {{
                                                formatNumber(column.place_count)
                                            }}</span
                                        >
                                        <span
                                            >{{ t.warehouses.metric_items }}:
                                            {{
                                                formatNumber(column.item_count)
                                            }}</span
                                        >
                                    </div>
                                </button>
                            </div>
                        </article>
                    </div>
                </div>

                <aside class="rounded-3xl border border-border bg-card p-6">
                    <Heading
                        variant="small"
                        :title="t.warehouses.map_column_panel_title"
                        :description="
                            selectedRow
                                ? `${selectedRow.name}`
                                : t.warehouses.map_column_panel_description
                        "
                    />

                    <div v-if="selectedColumn" class="mt-6 space-y-4">
                        <div
                            class="rounded-2xl border border-border bg-background/70 p-4"
                        >
                            <div class="font-semibold">
                                {{ selectedColumn.name }}
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                <div
                                    class="rounded-full border border-border bg-card px-3 py-1.5"
                                >
                                    {{ t.warehouses.metric_floors }}:
                                    {{
                                        formatNumber(selectedColumn.floor_count)
                                    }}
                                </div>
                                <div
                                    class="rounded-full border border-border bg-card px-3 py-1.5"
                                >
                                    {{ t.warehouses.metric_places }}:
                                    {{
                                        formatNumber(selectedColumn.place_count)
                                    }}
                                </div>
                                <div
                                    class="rounded-full border border-border bg-card px-3 py-1.5"
                                >
                                    {{ t.warehouses.metric_items }}:
                                    {{
                                        formatNumber(selectedColumn.item_count)
                                    }}
                                </div>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <button
                                v-for="floor in selectedColumn.floors"
                                :key="floor.id"
                                type="button"
                                class="w-full rounded-2xl border px-4 py-4 text-left transition-colors"
                                :class="
                                    floor.id === selectedFloorId
                                        ? 'border-primary bg-primary/10'
                                        : 'border-border bg-background hover:bg-muted/40'
                                "
                                @click="selectedFloorId = floor.id"
                            >
                                <div class="font-medium">{{ floor.name }}</div>
                                <div
                                    class="mt-2 flex flex-wrap gap-2 text-xs text-muted-foreground"
                                >
                                    <span
                                        >{{ t.warehouses.metric_places }}:
                                        {{
                                            formatNumber(floor.place_count)
                                        }}</span
                                    >
                                    <span
                                        >{{ t.warehouses.metric_items }}:
                                        {{
                                            formatNumber(floor.item_count)
                                        }}</span
                                    >
                                </div>
                            </button>
                        </div>

                        <div v-if="selectedFloor" class="space-y-3">
                            <div class="text-sm font-medium">
                                {{ t.warehouses.map_floor_places }}
                            </div>

                            <div
                                v-if="selectedFloorIsLoading"
                                class="rounded-2xl border border-dashed border-border bg-background px-4 py-6 text-sm text-muted-foreground"
                            >
                                {{ t.common.loading }}
                            </div>

                            <div
                                v-else-if="selectedFloorFailed"
                                class="rounded-2xl border border-dashed border-border bg-background px-4 py-6 text-sm text-muted-foreground"
                            >
                                <div>{{ t.common.error }}</div>
                                <button
                                    type="button"
                                    class="mt-2 font-medium text-primary hover:underline"
                                    @click="
                                        loadFloorPlaces(selectedFloor.id, true)
                                    "
                                >
                                    {{ t.common.retry }}
                                </button>
                            </div>

                            <div
                                v-else-if="selectedFloor.places.length === 0"
                                class="rounded-2xl border border-dashed border-border bg-background px-4 py-6 text-sm text-muted-foreground"
                            >
                                {{ t.warehouses.map_floor_empty }}
                            </div>

                            <article
                                v-for="place in selectedFloorIsLoading ||
                                selectedFloorFailed
                                    ? []
                                    : selectedFloor.places"
                                :key="place.id"
                                class="rounded-2xl border border-border bg-background p-4"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <div class="font-medium">
                                        {{ place.name }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ t.warehouses.metric_items }}:
                                        {{ formatNumber(place.item_count) }}
                                    </div>
                                </div>

                                <div
                                    v-if="place.items.length > 0"
                                    class="mt-3 space-y-2"
                                >
                                    <div
                                        v-for="item in place.items"
                                        :key="item.id"
                                        class="rounded-xl border border-border bg-card px-3 py-3 text-sm"
                                    >
                                        <div class="font-medium">
                                            {{ item.name }}
                                        </div>
                                        <div
                                            class="mt-1 flex flex-wrap gap-2 text-xs text-muted-foreground"
                                        >
                                            <span v-if="item.sku"
                                                >{{ t.warehouses.sku }}:
                                                {{ item.sku }}</span
                                            >
                                            <span
                                                >{{ t.warehouses.quantity }}:
                                                {{ item.quantity }}</span
                                            >
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-else
                                    class="mt-3 rounded-xl border border-dashed border-border bg-card px-3 py-4 text-sm text-muted-foreground"
                                >
                                    {{ t.warehouses.map_place_empty }}
                                </div>
                            </article>
                        </div>

                        <div
                            v-else
                            class="rounded-2xl border border-dashed border-border bg-background px-4 py-6 text-sm text-muted-foreground"
                        >
                            {{ t.warehouses.map_select_floor }}
                        </div>
                    </div>

                    <div
                        v-else
                        class="mt-6 rounded-2xl border border-dashed border-border bg-background px-4 py-6 text-sm text-muted-foreground"
                    >
                        {{ t.warehouses.map_select_column }}
                    </div>
                </aside>
            </section>

            <section class="rounded-3xl border border-border bg-card p-6">
                <Heading
                    variant="small"
                    :title="t.warehouses.inventory_qr_title"
                    :description="t.warehouses.inventory_qr_description"
                />

                <div
                    v-if="props.inventoryQrCodes.data.length === 0"
                    class="mt-6 rounded-2xl border border-dashed border-border bg-background/70 px-6 py-10 text-center"
                >
                    <div class="text-lg font-semibold">
                        {{ t.warehouses.inventory_empty }}
                    </div>
                    <p class="mt-2 text-sm leading-6 text-muted-foreground">
                        {{ t.warehouses.inventory_empty_description }}
                    </p>
                </div>

                <div v-else class="mt-6 space-y-5">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <article
                            v-for="item in props.inventoryQrCodes.data"
                            :key="item.id"
                            class="rounded-2xl border border-border bg-background/70 p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="font-semibold">
                                        {{ item.name }}
                                    </div>
                                    <div
                                        class="mt-1 text-sm text-muted-foreground"
                                    >
                                        {{ t.warehouses.location_path }}:
                                        <span class="text-foreground">{{
                                            item.location.path
                                        }}</span>
                                    </div>
                                </div>

                                <div
                                    class="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                                >
                                    {{ t.warehouses.quantity }}:
                                    {{ item.quantity }}
                                </div>
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                                <div
                                    class="rounded-xl border border-border bg-card px-3 py-3 text-sm"
                                >
                                    <div class="text-muted-foreground">
                                        {{ t.warehouses.sku }}
                                    </div>
                                    <div class="mt-1 font-medium">
                                        {{ item.sku ?? '—' }}
                                    </div>
                                </div>
                                <div
                                    class="rounded-xl border border-border bg-card px-3 py-3 text-sm"
                                >
                                    <div class="text-muted-foreground">
                                        {{ t.warehouses.scan_matched_qr }}
                                    </div>
                                    <div class="mt-1 font-mono font-medium">
                                        {{ item.qr_code }}
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>

                    <PaginationControls
                        :pagination="props.inventoryQrCodes"
                        :per-page-options="props.inventoryPerPageOptions"
                        @update:per-page="updateInventoryPerPage"
                    />
                </div>
            </section>
        </div>
    </div>
</template>

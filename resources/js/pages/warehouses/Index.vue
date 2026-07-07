<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { Boxes, Building2, Package, Plus, QrCode, Rows3 } from '@lucide/vue';
import { computed, ref, watchEffect } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import {
    index as warehousesIndex,
    store as storeWarehouse,
} from '@/routes/warehouses';

type WarehousePlace = {
    id: number;
    name: string;
    qr_code: string;
    sort_order: number;
};

type WarehouseFloor = {
    id: number;
    name: string;
    qr_code: string;
    sort_order: number;
    place_count: number;
    places: WarehousePlace[];
};

type WarehouseColumn = {
    id: number;
    name: string;
    qr_code: string;
    sort_order: number;
    floor_count: number;
    place_count: number;
    floors: WarehouseFloor[];
};

type WarehouseRow = {
    id: number;
    name: string;
    qr_code: string;
    sort_order: number;
    column_count: number;
    floor_count: number;
    place_count: number;
    columns: WarehouseColumn[];
};

type WarehouseRecord = {
    id: number;
    name: string;
    area_sqm: number;
    qr_code: string;
    row_count: number;
    column_count: number;
    floor_count: number;
    place_count: number;
    rows: WarehouseRow[];
};

type Summary = {
    warehouse_count: number;
    total_area_sqm: number;
    row_count: number;
    column_count: number;
    floor_count: number;
    place_count: number;
    qr_code_count: number;
};

type WarehousePayload = {
    name: string;
    area_sqm: number;
    rows: Array<{
        name: string;
        columns: Array<{
            name: string;
            floors: Array<{
                name: string;
                places: Array<{
                    name: string;
                }>;
            }>;
        }>;
    }>;
};

const props = defineProps<{
    warehouses: WarehouseRecord[];
    summary: Summary;
}>();

const { language, t } = useLanguage();

const dialogOpen = ref(false);

const form = useForm({
    name: '',
    area_sqm: 0,
    rows_count: 1,
    columns_per_row: 1,
    floors_per_column: 1,
    places_per_floor: 1,
});

const formatNumber = (value: number): string => {
    return new Intl.NumberFormat(language.value === 'ru' ? 'ru-RU' : 'en-US', {
        maximumFractionDigits: 2,
    }).format(value);
};

const normalizePositiveCount = (value: number): number => {
    if (! Number.isFinite(value)) {
        return 1;
    }

    return Math.max(1, Math.floor(value));
};

const summaryCards = computed(() => [
    {
        label: t.value.warehouses.metric_warehouses,
        value: formatNumber(props.summary.warehouse_count),
        icon: Building2,
    },
    {
        label: t.value.warehouses.metric_area,
        value: `${formatNumber(props.summary.total_area_sqm)} m²`,
        icon: Boxes,
    },
    {
        label: t.value.warehouses.metric_places,
        value: formatNumber(props.summary.place_count),
        icon: Package,
    },
    {
        label: t.value.warehouses.metric_qr_codes,
        value: formatNumber(props.summary.qr_code_count),
        icon: QrCode,
    },
]);

const placesPreview = computed(() => {
    return normalizePositiveCount(form.rows_count)
        * normalizePositiveCount(form.columns_per_row)
        * normalizePositiveCount(form.floors_per_column)
        * normalizePositiveCount(form.places_per_floor);
});

const qrCodesPreview = computed(() => {
    return 1
        + normalizePositiveCount(form.rows_count)
        + (normalizePositiveCount(form.rows_count) * normalizePositiveCount(form.columns_per_row))
        + (normalizePositiveCount(form.rows_count) * normalizePositiveCount(form.columns_per_row) * normalizePositiveCount(form.floors_per_column))
        + placesPreview.value;
});

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.warehouses.title,
                href: warehousesIndex(),
            },
        ],
    });
});

const resetForm = (): void => {
    form.name = '';
    form.area_sqm = 0;
    form.rows_count = 1;
    form.columns_per_row = 1;
    form.floors_per_column = 1;
    form.places_per_floor = 1;
    form.clearErrors();
};

const closeDialog = (): void => {
    dialogOpen.value = false;
    resetForm();
};

const openCreateDialog = (): void => {
    resetForm();
    dialogOpen.value = true;
};

const buildWarehousePayload = (): WarehousePayload => {
    const rowsCount = normalizePositiveCount(form.rows_count);
    const columnsPerRow = normalizePositiveCount(form.columns_per_row);
    const floorsPerColumn = normalizePositiveCount(form.floors_per_column);
    const placesPerFloor = normalizePositiveCount(form.places_per_floor);

    return {
        name: form.name,
        area_sqm: Number(form.area_sqm),
        rows: Array.from({ length: rowsCount }, (_, rowIndex) => ({
            name: `Ряд ${String.fromCharCode(65 + rowIndex)}`,
            columns: Array.from({ length: columnsPerRow }, (_, columnIndex) => ({
                name: `Колонка ${String(columnIndex + 1).padStart(2, '0')}`,
                floors: Array.from({ length: floorsPerColumn }, (_, floorIndex) => ({
                    name: `Этаж ${floorIndex + 1}`,
                    places: Array.from({ length: placesPerFloor }, (_, placeIndex) => ({
                        name: `${String.fromCharCode(65 + rowIndex)}-${String(columnIndex + 1).padStart(2, '0')}-${floorIndex + 1}-${String(placeIndex + 1).padStart(3, '0')}`,
                    })),
                })),
            })),
        })),
    };
};

const submitForm = (): void => {
    form
        .transform(() => buildWarehousePayload())
        .post(storeWarehouse.url(), {
            preserveScroll: true,
            onSuccess: () => {
                closeDialog();
            },
        });
};
</script>

<template>
    <Head :title="t.warehouses.title" />

    <h1 class="sr-only">{{ t.warehouses.title }}</h1>

    <div class="space-y-8">
        <section class="overflow-hidden rounded-3xl border border-border bg-card">
            <div
                class="grid gap-6 border-b border-border bg-gradient-to-br from-primary/10 via-transparent to-transparent px-6 py-6 lg:grid-cols-[minmax(0,1fr)_320px]"
            >
                <div class="space-y-4">
                    <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                        <Boxes class="size-4" />
                        {{ t.warehouses.title }}
                    </div>

                    <div class="space-y-2">
                        <h2 class="text-2xl font-semibold tracking-tight">
                            {{ t.warehouses.hero_title }}
                        </h2>
                        <p class="max-w-3xl text-sm leading-6 text-muted-foreground">
                            {{ t.warehouses.hero_description }}
                        </p>
                    </div>

                    <Button type="button" class="gap-2" @click="openCreateDialog">
                        <Plus class="size-4" />
                        <span>{{ t.warehouses.create_warehouse }}</span>
                    </Button>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                    <div
                        v-for="card in summaryCards"
                        :key="card.label"
                        class="rounded-2xl border border-border bg-background/80 p-4"
                    >
                        <div class="flex items-center gap-2 text-xs uppercase tracking-[0.18em] text-muted-foreground">
                            <component :is="card.icon" class="size-4" />
                            {{ card.label }}
                        </div>
                        <div class="mt-2 text-2xl font-semibold tracking-tight">
                            {{ card.value }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-3xl border border-border bg-card p-6">
            <Heading
                variant="small"
                :title="t.warehouses.list_title"
                :description="t.warehouses.list_description"
            />

            <div
                v-if="warehouses.length === 0"
                class="mt-6 rounded-2xl border border-dashed border-border bg-background/70 px-6 py-10 text-center"
            >
                <div class="text-lg font-semibold">{{ t.warehouses.empty_title }}</div>
                <p class="mt-2 text-sm leading-6 text-muted-foreground">
                    {{ t.warehouses.empty_description }}
                </p>
            </div>

            <div v-else class="mt-6 space-y-6">
                <article
                    v-for="warehouse in warehouses"
                    :key="warehouse.id"
                    class="rounded-3xl border border-border bg-background/70 p-5"
                >
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2 text-sm font-medium text-primary">
                                <Building2 class="size-4" />
                                <span>{{ warehouse.name }}</span>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ t.warehouses.warehouse_qr }}:
                                <span class="font-mono text-foreground">{{ warehouse.qr_code }}</span>
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                            <div class="rounded-2xl border border-border bg-card px-4 py-3 text-sm">
                                <div class="text-muted-foreground">{{ t.warehouses.metric_area }}</div>
                                <div class="mt-1 font-semibold">{{ formatNumber(warehouse.area_sqm) }} m²</div>
                            </div>
                            <div class="rounded-2xl border border-border bg-card px-4 py-3 text-sm">
                                <div class="text-muted-foreground">{{ t.warehouses.metric_rows }}</div>
                                <div class="mt-1 font-semibold">{{ formatNumber(warehouse.row_count) }}</div>
                            </div>
                            <div class="rounded-2xl border border-border bg-card px-4 py-3 text-sm">
                                <div class="text-muted-foreground">{{ t.warehouses.metric_columns }}</div>
                                <div class="mt-1 font-semibold">{{ formatNumber(warehouse.column_count) }}</div>
                            </div>
                            <div class="rounded-2xl border border-border bg-card px-4 py-3 text-sm">
                                <div class="text-muted-foreground">{{ t.warehouses.metric_floors }}</div>
                                <div class="mt-1 font-semibold">{{ formatNumber(warehouse.floor_count) }}</div>
                            </div>
                            <div class="rounded-2xl border border-border bg-card px-4 py-3 text-sm">
                                <div class="text-muted-foreground">{{ t.warehouses.metric_places }}</div>
                                <div class="mt-1 font-semibold">{{ formatNumber(warehouse.place_count) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-5 space-y-4">
                        <section
                            v-for="row in warehouse.rows"
                            :key="row.id"
                            class="rounded-2xl border border-border bg-card/80 p-4"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2 font-medium">
                                        <Rows3 class="size-4 text-primary" />
                                        <span>{{ row.name }}</span>
                                    </div>
                                    <div class="mt-1 text-xs text-muted-foreground">
                                        {{ t.warehouses.row_qr }}:
                                        <span class="font-mono text-foreground">{{ row.qr_code }}</span>
                                    </div>
                                </div>

                                <div class="text-sm text-muted-foreground">
                                    {{ t.warehouses.metric_columns }}: {{ formatNumber(row.column_count) }} ·
                                    {{ t.warehouses.metric_floors }}: {{ formatNumber(row.floor_count) }} ·
                                    {{ t.warehouses.metric_places }}: {{ formatNumber(row.place_count) }}
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 xl:grid-cols-2">
                                <section
                                    v-for="column in row.columns"
                                    :key="column.id"
                                    class="rounded-2xl border border-border bg-background px-4 py-4"
                                >
                                    <div class="font-medium">{{ column.name }}</div>
                                    <div class="mt-1 text-xs text-muted-foreground">
                                        {{ t.warehouses.column_qr }}:
                                        <span class="font-mono text-foreground">{{ column.qr_code }}</span>
                                    </div>

                                    <div class="mt-3 space-y-3">
                                        <div
                                            v-for="floor in column.floors"
                                            :key="floor.id"
                                            class="rounded-xl border border-border bg-card px-3 py-3"
                                        >
                                            <div class="flex items-center justify-between gap-3">
                                                <div>
                                                    <div class="font-medium">{{ floor.name }}</div>
                                                    <div class="text-xs text-muted-foreground">
                                                        {{ t.warehouses.floor_qr }}:
                                                        <span class="font-mono text-foreground">{{ floor.qr_code }}</span>
                                                    </div>
                                                </div>

                                                <div class="text-xs text-muted-foreground">
                                                    {{ t.warehouses.metric_places }}:
                                                    {{ formatNumber(floor.place_count) }}
                                                </div>
                                            </div>

                                            <div class="mt-3 flex flex-wrap gap-2">
                                                <div
                                                    v-for="place in floor.places"
                                                    :key="place.id"
                                                    class="rounded-full border border-border bg-background px-3 py-1.5 text-xs"
                                                >
                                                    <div class="font-medium text-foreground">{{ place.name }}</div>
                                                    <div class="text-[11px] text-muted-foreground">
                                                        {{ t.warehouses.place_qr }}:
                                                        <span class="font-mono text-foreground">{{ place.qr_code }}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                            </div>
                        </section>
                    </div>
                </article>
            </div>
        </section>
    </div>

    <Dialog :open="dialogOpen" @update:open="(value) => (value ? (dialogOpen = true) : closeDialog())">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>{{ t.warehouses.create_warehouse }}</DialogTitle>
                <DialogDescription>
                    {{ t.warehouses.create_warehouse_description }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-5" @submit.prevent="submitForm">
                <div class="grid gap-5 md:grid-cols-2">
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="warehouse-name">{{ t.warehouses.name }}</Label>
                        <Input
                            id="warehouse-name"
                            v-model="form.name"
                            :placeholder="t.warehouses.name_placeholder"
                            autocomplete="off"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="warehouse-area">{{ t.warehouses.area_sqm }}</Label>
                        <Input
                            id="warehouse-area"
                            v-model.number="form.area_sqm"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                        <InputError :message="form.errors.area_sqm" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="warehouse-rows">{{ t.warehouses.rows_count }}</Label>
                        <Input
                            id="warehouse-rows"
                            v-model.number="form.rows_count"
                            type="number"
                            min="1"
                            step="1"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="warehouse-columns">{{ t.warehouses.columns_per_row }}</Label>
                        <Input
                            id="warehouse-columns"
                            v-model.number="form.columns_per_row"
                            type="number"
                            min="1"
                            step="1"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="warehouse-floors">{{ t.warehouses.floors_per_column }}</Label>
                        <Input
                            id="warehouse-floors"
                            v-model.number="form.floors_per_column"
                            type="number"
                            min="1"
                            step="1"
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="warehouse-places">{{ t.warehouses.places_per_floor }}</Label>
                        <Input
                            id="warehouse-places"
                            v-model.number="form.places_per_floor"
                            type="number"
                            min="1"
                            step="1"
                        />
                    </div>
                </div>

                <InputError :message="form.errors.rows" />

                <div class="grid gap-4 rounded-2xl border border-border bg-muted/20 p-4 md:grid-cols-2">
                    <div>
                        <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                            {{ t.warehouses.capacity_preview }}
                        </div>
                        <div class="mt-2 text-2xl font-semibold tracking-tight">
                            {{ formatNumber(placesPreview) }}
                        </div>
                    </div>

                    <div>
                        <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                            {{ t.warehouses.qr_preview }}
                        </div>
                        <div class="mt-2 text-2xl font-semibold tracking-tight">
                            {{ formatNumber(qrCodesPreview) }}
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="closeDialog">
                        {{ t.warehouses.cancel }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ t.warehouses.save }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

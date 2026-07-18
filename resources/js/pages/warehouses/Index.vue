<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    Boxes,
    Building2,
    Package,
    Plus,
    QrCode,
    ScanLine,
} from '@lucide/vue';
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
    scan as scanWarehouse,
    show as showWarehouse,
    store as storeWarehouse,
} from '@/routes/warehouses';

type WarehouseRecord = {
    id: number;
    name: string;
    area_sqm: number;
    qr_code: string;
    row_count: number;
    column_count: number;
    floor_count: number;
    place_count: number;
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

type ResolvedScanResult = {
    entity_type: string;
    entity_type_label: string;
    title: string;
    qr_code: string;
    location: {
        path: string;
    };
    warehouse: {
        name: string;
    };
    details: {
        quantity?: number | null;
        sku?: string | null;
    };
} | null;

type PageProps = {
    flash?: {
        warehouseScanResult?: ResolvedScanResult;
    };
};

const props = defineProps<{
    warehouses: WarehouseRecord[];
    summary: Summary;
}>();

const page = usePage<PageProps>();
const { language, t } = useLanguage();

const createDialogOpen = ref(false);
const scanDialogOpen = ref(false);

const createForm = useForm({
    name: '',
    area_sqm: 0,
    rows_count: 1,
    columns_per_row: 1,
    floors_per_column: 1,
    places_per_floor: 1,
});

const scanForm = useForm({
    qr_code: '',
});

const formatNumber = (value: number): string => {
    return new Intl.NumberFormat(language.value === 'ru' ? 'ru-RU' : 'en-US', {
        maximumFractionDigits: 2,
    }).format(value);
};

const normalizePositiveCount = (value: number): number => {
    if (!Number.isFinite(value)) {
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
    return (
        normalizePositiveCount(createForm.rows_count) *
        normalizePositiveCount(createForm.columns_per_row) *
        normalizePositiveCount(createForm.floors_per_column) *
        normalizePositiveCount(createForm.places_per_floor)
    );
});

const qrCodesPreview = computed(() => {
    return (
        1 +
        normalizePositiveCount(createForm.rows_count) +
        normalizePositiveCount(createForm.rows_count) *
            normalizePositiveCount(createForm.columns_per_row) +
        normalizePositiveCount(createForm.rows_count) *
            normalizePositiveCount(createForm.columns_per_row) *
            normalizePositiveCount(createForm.floors_per_column) +
        placesPreview.value
    );
});

const resolvedScanResult = computed<ResolvedScanResult>(() => {
    const flashFromPage = (
        page as typeof page & {
            flash?: { warehouseScanResult?: ResolvedScanResult };
        }
    ).flash?.warehouseScanResult;

    return flashFromPage ?? page.props.flash?.warehouseScanResult ?? null;
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

const resetCreateForm = (): void => {
    createForm.name = '';
    createForm.area_sqm = 0;
    createForm.rows_count = 1;
    createForm.columns_per_row = 1;
    createForm.floors_per_column = 1;
    createForm.places_per_floor = 1;
    createForm.clearErrors();
};

const closeCreateDialog = (): void => {
    createDialogOpen.value = false;
    resetCreateForm();
};

const openCreateDialog = (): void => {
    resetCreateForm();
    createDialogOpen.value = true;
};

const closeScanDialog = (): void => {
    scanDialogOpen.value = false;
    scanForm.reset();
    scanForm.clearErrors();
};

const openScanDialog = (): void => {
    scanForm.reset();
    scanForm.clearErrors();
    scanDialogOpen.value = true;
};

const buildWarehousePayload = (): WarehousePayload => {
    const rowsCount = normalizePositiveCount(createForm.rows_count);
    const columnsPerRow = normalizePositiveCount(createForm.columns_per_row);
    const floorsPerColumn = normalizePositiveCount(
        createForm.floors_per_column,
    );
    const placesPerFloor = normalizePositiveCount(createForm.places_per_floor);

    return {
        name: createForm.name,
        area_sqm: Number(createForm.area_sqm),
        rows: Array.from({ length: rowsCount }, (_, rowIndex) => ({
            name: `${t.value.warehouses.entity_row} ${String.fromCharCode(65 + rowIndex)}`,
            columns: Array.from(
                { length: columnsPerRow },
                (_, columnIndex) => ({
                    name: `${t.value.warehouses.entity_column} ${String(columnIndex + 1).padStart(2, '0')}`,
                    floors: Array.from(
                        { length: floorsPerColumn },
                        (_, floorIndex) => ({
                            name: `${t.value.warehouses.entity_floor} ${floorIndex + 1}`,
                            places: Array.from(
                                { length: placesPerFloor },
                                (_, placeIndex) => ({
                                    name: `${String.fromCharCode(65 + rowIndex)}-${String(columnIndex + 1).padStart(2, '0')}-${floorIndex + 1}-${String(placeIndex + 1).padStart(3, '0')}`,
                                }),
                            ),
                        }),
                    ),
                }),
            ),
        })),
    };
};

const submitCreateForm = (): void => {
    createForm
        .transform(() => buildWarehousePayload())
        .post(storeWarehouse.url(), {
            preserveScroll: true,
            onSuccess: () => {
                closeCreateDialog();
            },
        });
};

const submitScanForm = (): void => {
    scanForm.post(scanWarehouse.url(), {
        preserveScroll: true,
        onSuccess: () => {
            closeScanDialog();
        },
    });
};
</script>

<template>
    <div>
        <Head :title="t.warehouses.title" />

        <h1 class="sr-only">{{ t.warehouses.title }}</h1>

        <div class="space-y-8">
            <section
                class="overflow-hidden rounded-3xl border border-border bg-card"
            >
                <div
                    class="grid gap-6 border-b border-border bg-gradient-to-br from-primary/10 via-transparent to-transparent px-6 py-6 lg:grid-cols-[minmax(0,1fr)_320px]"
                >
                    <div class="space-y-4">
                        <div
                            class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                        >
                            <Boxes class="size-4" />
                            {{ t.warehouses.title }}
                        </div>

                        <div class="space-y-2">
                            <h2 class="text-2xl font-semibold tracking-tight">
                                {{ t.warehouses.hero_title }}
                            </h2>
                            <p
                                class="max-w-3xl text-sm leading-6 text-muted-foreground"
                            >
                                {{ t.warehouses.hero_description }}
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-3">
                            <Button
                                type="button"
                                class="gap-2"
                                @click="openCreateDialog"
                            >
                                <Plus class="size-4" />
                                <span>{{ t.warehouses.create_warehouse }}</span>
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                class="gap-2"
                                @click="openScanDialog"
                            >
                                <ScanLine class="size-4" />
                                <span>{{ t.warehouses.scan_open }}</span>
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-1">
                        <div
                            v-for="card in summaryCards"
                            :key="card.label"
                            class="rounded-2xl border border-border bg-background/80 p-4"
                        >
                            <div
                                class="flex items-center gap-2 text-xs tracking-[0.18em] text-muted-foreground uppercase"
                            >
                                <component :is="card.icon" class="size-4" />
                                {{ card.label }}
                            </div>
                            <div
                                class="mt-2 text-2xl font-semibold tracking-tight"
                            >
                                {{ card.value }}
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                <div class="rounded-3xl border border-border bg-card p-6">
                    <Heading
                        variant="small"
                        :title="t.warehouses.list_title"
                        :description="t.warehouses.list_description"
                    />

                    <div
                        v-if="warehouses.length === 0"
                        class="mt-6 rounded-2xl border border-dashed border-border bg-background/70 px-6 py-10 text-center"
                    >
                        <div class="text-lg font-semibold">
                            {{ t.warehouses.empty_title }}
                        </div>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            {{ t.warehouses.empty_description }}
                        </p>
                    </div>

                    <div v-else class="mt-6 grid gap-5 2xl:grid-cols-2">
                        <article
                            v-for="warehouse in warehouses"
                            :key="warehouse.id"
                            class="rounded-3xl border border-border bg-background/70 p-5"
                        >
                            <div
                                class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="min-w-0 space-y-2">
                                    <div
                                        class="flex items-center gap-2 text-sm font-medium text-primary"
                                    >
                                        <Building2 class="size-4" />
                                        <span>{{ warehouse.name }}</span>
                                    </div>
                                    <p
                                        class="text-sm break-all text-muted-foreground"
                                    >
                                        {{ t.warehouses.warehouse_qr }}:
                                        <span
                                            class="font-mono text-foreground"
                                            >{{ warehouse.qr_code }}</span
                                        >
                                    </p>
                                </div>

                                <Link
                                    :href="showWarehouse.url(warehouse.id)"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-4 py-2 text-sm font-medium text-primary transition-colors hover:bg-primary/15 sm:w-auto sm:shrink-0"
                                >
                                    <span>{{
                                        t.warehouses.open_warehouse
                                    }}</span>
                                    <ArrowRight class="size-4" />
                                </Link>
                            </div>

                            <div
                                class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5"
                            >
                                <div
                                    class="rounded-2xl border border-border bg-card px-4 py-3 text-sm"
                                >
                                    <div class="text-muted-foreground">
                                        {{ t.warehouses.metric_area }}
                                    </div>
                                    <div class="mt-1 font-semibold">
                                        {{ formatNumber(warehouse.area_sqm) }}
                                        m²
                                    </div>
                                </div>
                                <div
                                    class="rounded-2xl border border-border bg-card px-4 py-3 text-sm"
                                >
                                    <div class="text-muted-foreground">
                                        {{ t.warehouses.metric_rows }}
                                    </div>
                                    <div class="mt-1 font-semibold">
                                        {{ formatNumber(warehouse.row_count) }}
                                    </div>
                                </div>
                                <div
                                    class="rounded-2xl border border-border bg-card px-4 py-3 text-sm"
                                >
                                    <div class="text-muted-foreground">
                                        {{ t.warehouses.metric_columns }}
                                    </div>
                                    <div class="mt-1 font-semibold">
                                        {{
                                            formatNumber(warehouse.column_count)
                                        }}
                                    </div>
                                </div>
                                <div
                                    class="rounded-2xl border border-border bg-card px-4 py-3 text-sm"
                                >
                                    <div class="text-muted-foreground">
                                        {{ t.warehouses.metric_floors }}
                                    </div>
                                    <div class="mt-1 font-semibold">
                                        {{
                                            formatNumber(warehouse.floor_count)
                                        }}
                                    </div>
                                </div>
                                <div
                                    class="rounded-2xl border border-border bg-card px-4 py-3 text-sm"
                                >
                                    <div class="text-muted-foreground">
                                        {{ t.warehouses.metric_places }}
                                    </div>
                                    <div class="mt-1 font-semibold">
                                        {{
                                            formatNumber(warehouse.place_count)
                                        }}
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>

                <aside class="rounded-3xl border border-border bg-card p-6">
                    <Heading
                        variant="small"
                        :title="t.warehouses.scan_result_title"
                        :description="t.warehouses.scan_result_description"
                    />

                    <div
                        v-if="resolvedScanResult"
                        class="mt-6 space-y-4 rounded-2xl border border-border bg-background/70 p-4"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <div>
                                <div
                                    class="text-xs tracking-[0.18em] text-muted-foreground uppercase"
                                >
                                    {{ t.warehouses.scan_entity }}
                                </div>
                                <div class="mt-1 font-semibold">
                                    {{ resolvedScanResult.entity_type_label }}
                                </div>
                            </div>
                            <div
                                class="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                            >
                                {{ resolvedScanResult.warehouse.name }}
                            </div>
                        </div>

                        <div
                            class="rounded-2xl border border-border bg-card p-4"
                        >
                            <div class="font-semibold">
                                {{ resolvedScanResult.title }}
                            </div>
                            <div class="mt-2 text-sm text-muted-foreground">
                                {{ t.warehouses.scan_matched_qr }}:
                                <span class="font-mono text-foreground">{{
                                    resolvedScanResult.qr_code
                                }}</span>
                            </div>
                            <div class="mt-2 text-sm text-muted-foreground">
                                {{ t.warehouses.scan_location }}:
                                <span class="text-foreground">{{
                                    resolvedScanResult.location.path
                                }}</span>
                            </div>
                            <div
                                v-if="
                                    resolvedScanResult.details?.sku ||
                                    resolvedScanResult.details?.quantity
                                "
                                class="mt-3 flex flex-wrap gap-2 text-xs"
                            >
                                <div
                                    v-if="resolvedScanResult.details?.sku"
                                    class="rounded-full border border-border bg-background px-3 py-1.5"
                                >
                                    {{ t.warehouses.sku }}:
                                    {{ resolvedScanResult.details.sku }}
                                </div>
                                <div
                                    v-if="resolvedScanResult.details?.quantity"
                                    class="rounded-full border border-border bg-background px-3 py-1.5"
                                >
                                    {{ t.warehouses.quantity }}:
                                    {{ resolvedScanResult.details.quantity }}
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        v-else
                        class="mt-6 rounded-2xl border border-dashed border-border bg-background/70 px-5 py-8 text-center"
                    >
                        <div class="text-base font-semibold">
                            {{ t.warehouses.scan_result_empty }}
                        </div>
                        <p class="mt-2 text-sm leading-6 text-muted-foreground">
                            {{ t.warehouses.scan_result_empty_description }}
                        </p>
                    </div>
                </aside>
            </section>
        </div>

        <Dialog
            :open="createDialogOpen"
            @update:open="
                (value) =>
                    value ? (createDialogOpen = true) : closeCreateDialog()
            "
        >
            <DialogContent class="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{
                        t.warehouses.create_warehouse
                    }}</DialogTitle>
                    <DialogDescription>
                        {{ t.warehouses.create_warehouse_description }}
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-5" @submit.prevent="submitCreateForm">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="grid gap-2 md:col-span-2">
                            <Label for="warehouse-name">{{
                                t.warehouses.name
                            }}</Label>
                            <Input
                                id="warehouse-name"
                                v-model="createForm.name"
                                :placeholder="t.warehouses.name_placeholder"
                                autocomplete="off"
                            />
                            <InputError :message="createForm.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="warehouse-area">{{
                                t.warehouses.area_sqm
                            }}</Label>
                            <Input
                                id="warehouse-area"
                                v-model.number="createForm.area_sqm"
                                type="number"
                                min="0"
                                step="0.01"
                            />
                            <InputError :message="createForm.errors.area_sqm" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="warehouse-rows">{{
                                t.warehouses.rows_count
                            }}</Label>
                            <Input
                                id="warehouse-rows"
                                v-model.number="createForm.rows_count"
                                type="number"
                                min="1"
                                step="1"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="warehouse-columns">{{
                                t.warehouses.columns_per_row
                            }}</Label>
                            <Input
                                id="warehouse-columns"
                                v-model.number="createForm.columns_per_row"
                                type="number"
                                min="1"
                                step="1"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="warehouse-floors">{{
                                t.warehouses.floors_per_column
                            }}</Label>
                            <Input
                                id="warehouse-floors"
                                v-model.number="createForm.floors_per_column"
                                type="number"
                                min="1"
                                step="1"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="warehouse-places">{{
                                t.warehouses.places_per_floor
                            }}</Label>
                            <Input
                                id="warehouse-places"
                                v-model.number="createForm.places_per_floor"
                                type="number"
                                min="1"
                                step="1"
                            />
                        </div>
                    </div>

                    <div
                        class="grid gap-4 rounded-2xl border border-border bg-muted/20 p-4 md:grid-cols-2"
                    >
                        <div>
                            <div
                                class="text-xs tracking-[0.18em] text-muted-foreground uppercase"
                            >
                                {{ t.warehouses.capacity_preview }}
                            </div>
                            <div
                                class="mt-2 text-2xl font-semibold tracking-tight"
                            >
                                {{ formatNumber(placesPreview) }}
                            </div>
                        </div>

                        <div>
                            <div
                                class="text-xs tracking-[0.18em] text-muted-foreground uppercase"
                            >
                                {{ t.warehouses.qr_preview }}
                            </div>
                            <div
                                class="mt-2 text-2xl font-semibold tracking-tight"
                            >
                                {{ formatNumber(qrCodesPreview) }}
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeCreateDialog"
                        >
                            {{ t.warehouses.cancel }}
                        </Button>
                        <Button type="submit" :disabled="createForm.processing">
                            {{ t.warehouses.save }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="scanDialogOpen"
            @update:open="
                (value) => (value ? (scanDialogOpen = true) : closeScanDialog())
            "
        >
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ t.warehouses.scan_title }}</DialogTitle>
                    <DialogDescription>
                        {{ t.warehouses.scan_description }}
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-5" @submit.prevent="submitScanForm">
                    <div class="grid gap-2">
                        <Label for="warehouse-scan-qr">{{
                            t.tsd.qr_code
                        }}</Label>
                        <Input
                            id="warehouse-scan-qr"
                            v-model="scanForm.qr_code"
                            :placeholder="t.tsd.qr_code_placeholder"
                            autocomplete="off"
                        />
                        <InputError :message="scanForm.errors.qr_code" />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeScanDialog"
                        >
                            {{ t.warehouses.cancel }}
                        </Button>
                        <Button type="submit" :disabled="scanForm.processing">
                            {{ t.warehouses.scan_submit }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>

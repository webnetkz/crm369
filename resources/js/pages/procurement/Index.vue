<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    ArrowDownToLine,
    ArrowUpFromLine,
    BadgeDollarSign,
    Check,
    ClipboardCheck,
    ClipboardList,
    Factory,
    PackageCheck,
    Pencil,
    Plus,
    Send,
    ShoppingCart,
    Store,
    Truck,
    X,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { computed, ref, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import { store as storeGoodsReceipt } from '@/actions/App/Http/Controllers/GoodsReceiptController';
import {
    send as sendPurchaseOrder,
    store as storePurchaseOrder,
} from '@/actions/App/Http/Controllers/PurchaseOrderController';
import {
    decide as decidePurchaseRequest,
    store as storePurchaseRequest,
} from '@/actions/App/Http/Controllers/PurchaseRequestController';
import { store as storePurchaseReturn } from '@/actions/App/Http/Controllers/PurchaseReturnController';
import {
    store as storeSupplier,
    update as updateSupplier,
} from '@/actions/App/Http/Controllers/SupplierController';
import { store as storeSupplierQuotation } from '@/actions/App/Http/Controllers/SupplierQuotationController';
import { index as procurementIndex } from '@/routes/procurement';

type TabKey =
    | 'overview'
    | 'requests'
    | 'suppliers'
    | 'price_comparison'
    | 'orders'
    | 'receipts'
    | 'returns';

type Summary = {
    pending_approvals: number;
    active_orders: number;
    month_receipts: Record<string, number>;
    potential_savings: Record<string, number>;
};

type Supplier = {
    id: number;
    contact_id: number | null;
    name: string;
    bin: string | null;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
    currency: string;
    payment_terms_days: number;
    lead_time_days: number;
    rating: number | null;
    is_active: boolean;
    notes: string | null;
    quotation_count: number;
    order_count: number;
};

type Quotation = {
    id: number;
    supplier_id: number;
    supplier_name: string;
    supplier_rating: number | null;
    unit_price: number;
    tax_percent: number;
    delivery_cost: number;
    landed_unit_price: number;
    landed_total: number;
    currency: string;
    quoted_at: string;
    valid_until: string | null;
    lead_time_days: number;
    notes: string | null;
};

type RequestItem = {
    id: number;
    item_name: string;
    sku: string | null;
    unit: string;
    quantity: number;
    target_unit_price: number;
    production_reference: string | null;
    warehouse_place_id: number | null;
    warehouse_item_id: number | null;
    warehouse_place: string | null;
    notes: string | null;
    best_quotation_id: number | null;
    quotations: Quotation[];
};

type PurchaseRequest = {
    id: number;
    number: string;
    title: string;
    status: string;
    needed_at: string | null;
    budget_amount: number;
    estimated_total: number;
    currency: string;
    justification: string | null;
    rejection_reason: string | null;
    requested_by: string | null;
    approved_by: string | null;
    submitted_at: string | null;
    approved_at: string | null;
    order: {
        id: number;
        number: string;
        status: string;
        total_amount: number;
    } | null;
    items: RequestItem[];
};

type OrderItem = {
    id: number;
    item_name: string;
    sku: string | null;
    unit: string;
    quantity: number;
    received_quantity: number;
    remaining_quantity: number;
    returned_quantity: number;
    returnable_quantity: number;
    unit_price: number;
    tax_percent: number;
    line_total: number;
    warehouse_place_id: number | null;
    warehouse_place: string | null;
    warehouse_item_id: number | null;
    warehouse_stock: number | null;
};

type PurchaseOrder = {
    id: number;
    number: string;
    purchase_request_id: number;
    request_number: string | null;
    request_title: string | null;
    supplier_id: number;
    supplier_name: string;
    status: string;
    currency: string;
    ordered_at: string;
    expected_at: string | null;
    sent_at: string | null;
    subtotal: number;
    tax_amount: number;
    delivery_amount: number;
    total_amount: number;
    notes: string | null;
    receipt_count: number;
    return_count: number;
    items: OrderItem[];
};

type ReceiptItem = {
    id: number;
    purchase_order_item_id: number;
    item_name: string;
    sku: string | null;
    unit: string;
    warehouse_item_id: number;
    warehouse_item_name: string;
    quantity: number;
    returned_quantity: number;
    returnable_quantity: number;
    unit_price: number;
    line_total: number;
};

type GoodsReceipt = {
    id: number;
    number: string;
    purchase_order_id: number;
    order_number: string;
    supplier_name: string;
    currency: string;
    status: string;
    received_at: string;
    received_by: string | null;
    external_reference: string | null;
    notes: string | null;
    total_amount: number;
    items: ReceiptItem[];
};

type PurchaseReturn = {
    id: number;
    number: string;
    purchase_order_id: number;
    order_number: string;
    supplier_name: string;
    currency: string;
    status: string;
    returned_at: string;
    created_by: string | null;
    reason: string;
    total_amount: number;
    items: Array<{
        id: number;
        item_name: string;
        sku: string | null;
        unit: string;
        quantity: number;
        unit_price: number;
        line_total: number;
        warehouse_stock: number;
    }>;
};

type WarehousePlace = {
    id: number;
    name: string;
    path: string;
};

type CompanyContact = {
    id: number;
    name: string;
    contact_person: string | null;
    email: string | null;
    phone: string | null;
};

const props = defineProps<{
    summary: Summary;
    suppliers: Supplier[];
    purchaseRequests: PurchaseRequest[];
    purchaseOrders: PurchaseOrder[];
    goodsReceipts: GoodsReceipt[];
    purchaseReturns: PurchaseReturn[];
    warehousePlaces: WarehousePlace[];
    warehouseItems: Array<{
        id: number;
        warehouse_place_id: number;
        name: string;
        sku: string | null;
        quantity: number;
    }>;
    companyContacts: CompanyContact[];
    can: {
        manage: boolean;
        approve_budget: boolean;
        manage_orders: boolean;
        receive_orders: boolean;
        return_goods: boolean;
    };
}>();

const { language, t } = useLanguage();
const activeTab = ref<TabKey>('overview');

const requestDialogOpen = ref(false);
const supplierDialogOpen = ref(false);
const quotationDialogOpen = ref(false);
const rejectionDialogOpen = ref(false);
const orderDialogOpen = ref(false);
const receiptDialogOpen = ref(false);
const returnDialogOpen = ref(false);

const selectedQuotationItem = ref<RequestItem | null>(null);
const selectedQuotationRequest = ref<PurchaseRequest | null>(null);
const selectedRejectionRequest = ref<PurchaseRequest | null>(null);
const editingSupplierId = ref<number | null>(null);

const today = (): string => new Date().toISOString().slice(0, 10);

const emptyRequestItem = () => ({
    item_name: '',
    sku: '',
    unit: 'pcs',
    quantity: 1,
    target_unit_price: 0,
    warehouse_place_id: null as number | null,
    warehouse_item_id: null as number | null,
    production_reference: '',
    notes: '',
});

const requestForm = useForm({
    title: '',
    needed_at: '',
    budget_amount: 0,
    currency: 'KZT',
    justification: '',
    items: [emptyRequestItem()],
});

const supplierForm = useForm({
    contact_id: null as number | null,
    name: '',
    bin: '',
    contact_person: '',
    email: '',
    phone: '',
    currency: 'KZT',
    payment_terms_days: 0,
    lead_time_days: 0,
    rating: 0,
    is_active: true,
    notes: '',
});

const quotationForm = useForm({
    purchase_request_item_id: null as number | null,
    supplier_id: null as number | null,
    unit_price: 0,
    currency: 'KZT',
    tax_percent: 12,
    delivery_cost: 0,
    quoted_at: today(),
    valid_until: '',
    lead_time_days: 0,
    notes: '',
});

const rejectionForm = useForm({
    decision: 'reject',
    rejection_reason: '',
});

const orderForm = useForm({
    purchase_request_id: null as number | null,
    supplier_id: null as number | null,
    quotation_ids: [] as number[],
    ordered_at: today(),
    expected_at: '',
    notes: '',
});

const receiptForm = useForm({
    purchase_order_id: null as number | null,
    received_at: today(),
    external_reference: '',
    notes: '',
    items: [] as Array<{
        purchase_order_item_id: number;
        warehouse_place_id: number | null;
        quantity: number;
        max_quantity: number;
        item_name: string;
    }>,
});

const returnForm = useForm({
    purchase_order_id: null as number | null,
    returned_at: today(),
    reason: '',
    items: [] as Array<{
        goods_receipt_item_id: number;
        quantity: number;
        max_quantity: number;
        item_name: string;
    }>,
});

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.procurement.title,
                href: procurementIndex(),
            },
        ],
    });
});

const formatNumber = (value: number, maximumFractionDigits = 2): string => {
    return new Intl.NumberFormat(language.value === 'ru' ? 'ru-RU' : 'en-US', {
        maximumFractionDigits,
    }).format(value);
};

const formatCurrency = (value: number, currency = 'KZT'): string => {
    try {
        return new Intl.NumberFormat(
            language.value === 'ru' ? 'ru-RU' : 'en-US',
            {
                style: 'currency',
                currency,
                maximumFractionDigits: 2,
            },
        ).format(value);
    } catch {
        return `${formatNumber(value)} ${currency}`;
    }
};

const formatCurrencyMap = (values: Record<string, number>): string => {
    const entries = Object.entries(values);

    if (entries.length === 0) {
        return formatCurrency(0);
    }

    return entries
        .map(([currency, value]) => formatCurrency(value, currency))
        .join(' · ');
};

const formatDate = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        language.value === 'ru' ? 'ru-RU' : 'en-US',
        { dateStyle: 'medium' },
    ).format(new Date(value));
};

const statusLabel = (status: string): string => {
    return t.value.procurement.statuses[status] ?? status;
};

const statusClass = (status: string): string => {
    if (['approved', 'received', 'posted'].includes(status)) {
        return 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300';
    }

    if (['rejected', 'cancelled'].includes(status)) {
        return 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300';
    }

    if (['pending_approval', 'partially_received'].includes(status)) {
        return 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300';
    }

    return 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300';
};

const tabs = computed<Array<{ key: TabKey; label: string; icon: LucideIcon }>>(
    () => [
        {
            key: 'overview',
            label: t.value.procurement.overview,
            icon: ShoppingCart,
        },
        {
            key: 'requests',
            label: t.value.procurement.requests,
            icon: ClipboardList,
        },
        { key: 'suppliers', label: t.value.procurement.suppliers, icon: Store },
        {
            key: 'price_comparison',
            label: t.value.procurement.price_comparison,
            icon: BadgeDollarSign,
        },
        { key: 'orders', label: t.value.procurement.orders, icon: Truck },
        {
            key: 'receipts',
            label: t.value.procurement.receipts,
            icon: ArrowDownToLine,
        },
        {
            key: 'returns',
            label: t.value.procurement.returns,
            icon: ArrowUpFromLine,
        },
    ],
);

const summaryCards = computed(() => [
    {
        label: t.value.procurement.pending_approvals,
        value: formatNumber(props.summary.pending_approvals, 0),
        icon: ClipboardCheck,
    },
    {
        label: t.value.procurement.active_orders,
        value: formatNumber(props.summary.active_orders, 0),
        icon: Truck,
    },
    {
        label: t.value.procurement.month_receipts,
        value: formatCurrencyMap(props.summary.month_receipts),
        icon: PackageCheck,
    },
    {
        label: t.value.procurement.potential_savings,
        value: formatCurrencyMap(props.summary.potential_savings),
        icon: BadgeDollarSign,
    },
]);

const requestsReadyForOrder = computed(() => {
    return props.purchaseRequests.filter(
        (request) => request.status === 'approved' && !request.order,
    );
});

const supplierOptionsForOrder = computed(() => {
    const request = props.purchaseRequests.find(
        (item) => item.id === orderForm.purchase_request_id,
    );

    if (!request) {
        return [];
    }

    return props.suppliers.filter(
        (supplier) =>
            supplier.is_active &&
            request.items.every((item) =>
                item.quotations.some(
                    (quotation) => quotation.supplier_id === supplier.id,
                ),
            ),
    );
});

const syncOrderQuotations = (): void => {
    const request = props.purchaseRequests.find(
        (item) => item.id === orderForm.purchase_request_id,
    );

    if (!request || !orderForm.supplier_id) {
        orderForm.quotation_ids = [];
        return;
    }

    orderForm.quotation_ids = request.items
        .map(
            (item) =>
                item.quotations.find(
                    (quotation) =>
                        quotation.supplier_id === orderForm.supplier_id,
                )?.id,
        )
        .filter((id): id is number => id !== undefined);
};

const quotationTotalForOrder = computed(() => {
    const request = props.purchaseRequests.find(
        (item) => item.id === orderForm.purchase_request_id,
    );

    if (!request) {
        return 0;
    }

    return request.items.reduce((total, item) => {
        const quotation = item.quotations.find((candidate) =>
            orderForm.quotation_ids.includes(candidate.id),
        );

        return total + (quotation?.landed_total ?? 0);
    }, 0);
});

const openQuotationDialog = (
    request: PurchaseRequest,
    item: RequestItem,
): void => {
    selectedQuotationRequest.value = request;
    selectedQuotationItem.value = item;
    quotationForm.reset();
    quotationForm.clearErrors();
    quotationForm.purchase_request_item_id = item.id;
    quotationForm.currency = request.currency;
    quotationForm.supplier_id =
        props.suppliers.find((supplier) => supplier.is_active)?.id ?? null;
    quotationForm.quoted_at = today();
    quotationDialogOpen.value = true;
};

const openSupplierDialog = (supplier?: Supplier): void => {
    supplierForm.reset();
    supplierForm.clearErrors();
    editingSupplierId.value = supplier?.id ?? null;

    if (supplier) {
        supplierForm.contact_id = supplier.contact_id;
        supplierForm.name = supplier.name;
        supplierForm.bin = supplier.bin ?? '';
        supplierForm.contact_person = supplier.contact_person ?? '';
        supplierForm.email = supplier.email ?? '';
        supplierForm.phone = supplier.phone ?? '';
        supplierForm.currency = supplier.currency;
        supplierForm.payment_terms_days = supplier.payment_terms_days;
        supplierForm.lead_time_days = supplier.lead_time_days;
        supplierForm.rating = supplier.rating ?? 0;
        supplierForm.is_active = supplier.is_active;
        supplierForm.notes = supplier.notes ?? '';
    }

    supplierDialogOpen.value = true;
};

const openRejectionDialog = (request: PurchaseRequest): void => {
    selectedRejectionRequest.value = request;
    rejectionForm.reset();
    rejectionForm.clearErrors();
    rejectionDialogOpen.value = true;
};

const approveRequest = (request: PurchaseRequest): void => {
    router.patch(
        decidePurchaseRequest.url(request.id),
        { decision: 'approve', rejection_reason: null },
        { preserveScroll: true },
    );
};

const openOrderDialog = (request?: PurchaseRequest): void => {
    const target = request ?? requestsReadyForOrder.value[0];
    orderForm.reset();
    orderForm.clearErrors();
    orderForm.purchase_request_id = target?.id ?? null;
    orderForm.ordered_at = today();
    const firstSupplier = supplierOptionsForOrder.value[0];
    orderForm.supplier_id = firstSupplier?.id ?? null;
    syncOrderQuotations();
    orderDialogOpen.value = true;
};

const changeOrderRequest = (): void => {
    orderForm.supplier_id = supplierOptionsForOrder.value[0]?.id ?? null;
    syncOrderQuotations();
};

const openReceiptDialog = (order: PurchaseOrder): void => {
    receiptForm.reset();
    receiptForm.clearErrors();
    receiptForm.purchase_order_id = order.id;
    receiptForm.received_at = today();
    receiptForm.items = order.items
        .filter((item) => item.remaining_quantity > 0)
        .map((item) => ({
            purchase_order_item_id: item.id,
            warehouse_place_id:
                item.warehouse_place_id ?? props.warehousePlaces[0]?.id ?? null,
            quantity: item.remaining_quantity,
            max_quantity: item.remaining_quantity,
            item_name: item.item_name,
        }));
    receiptDialogOpen.value = true;
};

const openReturnDialog = (receipt: GoodsReceipt): void => {
    returnForm.reset();
    returnForm.clearErrors();
    returnForm.purchase_order_id = receipt.purchase_order_id;
    returnForm.returned_at = today();
    returnForm.items = receipt.items
        .filter((item) => item.returnable_quantity > 0)
        .map((item) => ({
            goods_receipt_item_id: item.id,
            quantity: item.returnable_quantity,
            max_quantity: item.returnable_quantity,
            item_name: item.item_name,
        }));
    returnDialogOpen.value = true;
};

const submitRequest = (): void => {
    requestForm.post(storePurchaseRequest.url(), {
        preserveScroll: true,
        onSuccess: () => {
            requestDialogOpen.value = false;
            requestForm.reset();
            requestForm.items = [emptyRequestItem()];
            activeTab.value = 'requests';
        },
    });
};

const submitSupplier = (): void => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            supplierDialogOpen.value = false;
            supplierForm.reset();
            editingSupplierId.value = null;
            activeTab.value = 'suppliers';
        },
    };

    if (editingSupplierId.value) {
        supplierForm.patch(
            updateSupplier.url(editingSupplierId.value),
            options,
        );
        return;
    }

    supplierForm.post(storeSupplier.url(), options);
};

const submitQuotation = (): void => {
    quotationForm.post(storeSupplierQuotation.url(), {
        preserveScroll: true,
        onSuccess: () => {
            quotationDialogOpen.value = false;
            quotationForm.reset();
            activeTab.value = 'price_comparison';
        },
    });
};

const submitRejection = (): void => {
    if (!selectedRejectionRequest.value) {
        return;
    }

    rejectionForm.patch(
        decidePurchaseRequest.url(selectedRejectionRequest.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                rejectionDialogOpen.value = false;
                rejectionForm.reset();
            },
        },
    );
};

const submitOrder = (): void => {
    orderForm.post(storePurchaseOrder.url(), {
        preserveScroll: true,
        onSuccess: () => {
            orderDialogOpen.value = false;
            orderForm.reset();
            activeTab.value = 'orders';
        },
    });
};

const markOrderSent = (order: PurchaseOrder): void => {
    router.patch(sendPurchaseOrder.url(order.id), {}, { preserveScroll: true });
};

const submitReceipt = (): void => {
    receiptForm
        .transform(({ items, ...data }) => ({
            ...data,
            items: items.map(
                ({ max_quantity: _max, item_name: _name, ...item }) => item,
            ),
        }))
        .post(storeGoodsReceipt.url(), {
            preserveScroll: true,
            onSuccess: () => {
                receiptDialogOpen.value = false;
                receiptForm.reset();
                activeTab.value = 'receipts';
            },
        });
};

const submitReturn = (): void => {
    returnForm
        .transform(({ items, ...data }) => ({
            ...data,
            items: items.map(
                ({ max_quantity: _max, item_name: _name, ...item }) => item,
            ),
        }))
        .post(storePurchaseReturn.url(), {
            preserveScroll: true,
            onSuccess: () => {
                returnDialogOpen.value = false;
                returnForm.reset();
                activeTab.value = 'returns';
            },
        });
};
</script>

<template>
    <div>
        <Head :title="t.procurement.title" />

        <div class="space-y-6">
            <section
                class="overflow-hidden rounded-3xl border border-border bg-card shadow-sm"
            >
                <div
                    class="grid gap-6 bg-gradient-to-br from-primary/15 via-background to-emerald-500/10 px-6 py-7 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center"
                >
                    <div class="max-w-3xl space-y-3">
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1 text-xs font-semibold tracking-[0.16em] text-primary uppercase"
                        >
                            <ShoppingCart class="size-3.5" />
                            {{ t.procurement.eyebrow }}
                        </div>
                        <h1
                            class="text-3xl font-bold tracking-tight text-foreground sm:text-4xl"
                        >
                            {{ t.procurement.title }}
                        </h1>
                        <p
                            class="max-w-2xl text-sm leading-6 text-muted-foreground sm:text-base"
                        >
                            {{ t.procurement.description }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2 lg:justify-end">
                        <Button
                            v-if="can.manage"
                            variant="outline"
                            class="rounded-xl bg-background/80"
                            @click="openSupplierDialog()"
                        >
                            <Store class="size-4" />
                            {{ t.procurement.new_supplier }}
                        </Button>
                        <Button
                            class="rounded-xl"
                            @click="requestDialogOpen = true"
                        >
                            <Plus class="size-4" />
                            {{ t.procurement.new_request }}
                        </Button>
                    </div>
                </div>

                <div
                    class="grid gap-px bg-border sm:grid-cols-2 xl:grid-cols-4"
                >
                    <div
                        v-for="card in summaryCards"
                        :key="card.label"
                        class="flex items-center gap-4 bg-card px-5 py-4"
                    >
                        <span
                            class="rounded-2xl bg-primary/10 p-3 text-primary"
                        >
                            <component :is="card.icon" class="size-5" />
                        </span>
                        <div class="min-w-0">
                            <p
                                class="truncate text-xs font-medium text-muted-foreground"
                            >
                                {{ card.label }}
                            </p>
                            <p
                                class="mt-1 truncate text-xl font-bold text-foreground"
                            >
                                {{ card.value }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <nav
                class="flex gap-1 overflow-x-auto rounded-2xl border border-border bg-card p-1.5"
                :aria-label="t.procurement.title"
            >
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    class="inline-flex shrink-0 items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium transition"
                    :class="
                        activeTab === tab.key
                            ? 'bg-primary text-primary-foreground shadow-sm'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                    @click="activeTab = tab.key"
                >
                    <component :is="tab.icon" class="size-4" />
                    {{ tab.label }}
                </button>
            </nav>

            <div v-if="activeTab === 'overview'" class="space-y-6">
                <div class="grid gap-4 lg:grid-cols-2">
                    <article
                        class="rounded-2xl border border-border bg-card p-6 shadow-sm"
                    >
                        <div class="flex items-start gap-4">
                            <span
                                class="rounded-2xl bg-blue-500/10 p-3 text-blue-600"
                            >
                                <PackageCheck class="size-6" />
                            </span>
                            <div>
                                <h2 class="font-semibold text-foreground">
                                    {{ t.procurement.stock_integration }}
                                </h2>
                                <p
                                    class="mt-2 text-sm leading-6 text-muted-foreground"
                                >
                                    {{
                                        t.procurement
                                            .stock_integration_description
                                    }}
                                </p>
                            </div>
                        </div>
                    </article>
                    <article
                        class="rounded-2xl border border-border bg-card p-6 shadow-sm"
                    >
                        <div class="flex items-start gap-4">
                            <span
                                class="rounded-2xl bg-amber-500/10 p-3 text-amber-600"
                            >
                                <Factory class="size-6" />
                            </span>
                            <div>
                                <h2 class="font-semibold text-foreground">
                                    {{ t.procurement.production_integration }}
                                </h2>
                                <p
                                    class="mt-2 text-sm leading-6 text-muted-foreground"
                                >
                                    {{
                                        t.procurement
                                            .production_integration_description
                                    }}
                                </p>
                            </div>
                        </div>
                    </article>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <section
                        class="rounded-2xl border border-border bg-card p-5"
                    >
                        <div
                            class="mb-4 flex items-center justify-between gap-3"
                        >
                            <h2 class="font-semibold text-foreground">
                                {{ t.procurement.requests }}
                            </h2>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="activeTab = 'requests'"
                            >
                                {{ t.procurement.details }}
                            </Button>
                        </div>
                        <div class="space-y-3">
                            <article
                                v-for="request in purchaseRequests.slice(0, 5)"
                                :key="request.id"
                                class="flex items-center justify-between gap-3 rounded-xl border border-border p-3"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-sm font-semibold text-foreground"
                                    >
                                        {{ request.number }} ·
                                        {{ request.title }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{
                                            formatCurrency(
                                                request.budget_amount,
                                                request.currency,
                                            )
                                        }}
                                    </p>
                                </div>
                                <Badge
                                    variant="outline"
                                    :class="statusClass(request.status)"
                                >
                                    {{ statusLabel(request.status) }}
                                </Badge>
                            </article>
                            <p
                                v-if="purchaseRequests.length === 0"
                                class="py-8 text-center text-sm text-muted-foreground"
                            >
                                {{ t.procurement.empty_requests }}
                            </p>
                        </div>
                    </section>

                    <section
                        class="rounded-2xl border border-border bg-card p-5"
                    >
                        <div
                            class="mb-4 flex items-center justify-between gap-3"
                        >
                            <h2 class="font-semibold text-foreground">
                                {{ t.procurement.orders }}
                            </h2>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="activeTab = 'orders'"
                            >
                                {{ t.procurement.details }}
                            </Button>
                        </div>
                        <div class="space-y-3">
                            <article
                                v-for="order in purchaseOrders.slice(0, 5)"
                                :key="order.id"
                                class="flex items-center justify-between gap-3 rounded-xl border border-border p-3"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="truncate text-sm font-semibold text-foreground"
                                    >
                                        {{ order.number }} ·
                                        {{ order.supplier_name }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{
                                            formatCurrency(
                                                order.total_amount,
                                                order.currency,
                                            )
                                        }}
                                    </p>
                                </div>
                                <Badge
                                    variant="outline"
                                    :class="statusClass(order.status)"
                                >
                                    {{ statusLabel(order.status) }}
                                </Badge>
                            </article>
                            <p
                                v-if="purchaseOrders.length === 0"
                                class="py-8 text-center text-sm text-muted-foreground"
                            >
                                {{ t.procurement.empty_orders }}
                            </p>
                        </div>
                    </section>
                </div>
            </div>

            <section v-else-if="activeTab === 'requests'" class="space-y-4">
                <article
                    v-for="request in purchaseRequests"
                    :key="request.id"
                    class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-bold text-foreground">
                                    {{ request.number }} · {{ request.title }}
                                </h2>
                                <Badge
                                    variant="outline"
                                    :class="statusClass(request.status)"
                                >
                                    {{ statusLabel(request.status) }}
                                </Badge>
                            </div>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ t.procurement.requested_by }}:
                                {{ request.requested_by ?? '—' }} ·
                                {{ t.procurement.needed_at }}:
                                {{ formatDate(request.needed_at) }}
                            </p>
                            <p
                                v-if="request.justification"
                                class="mt-2 text-sm text-foreground/80"
                            >
                                {{ request.justification }}
                            </p>
                            <p
                                v-if="request.rejection_reason"
                                class="mt-2 text-sm text-red-600"
                            >
                                {{ t.procurement.rejection_reason }}:
                                {{ request.rejection_reason }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <template
                                v-if="
                                    can.approve_budget &&
                                    request.status === 'pending_approval'
                                "
                            >
                                <Button
                                    size="sm"
                                    @click="approveRequest(request)"
                                >
                                    <Check class="size-4" />
                                    {{ t.procurement.approve }}
                                </Button>
                                <Button
                                    size="sm"
                                    variant="destructive"
                                    @click="openRejectionDialog(request)"
                                >
                                    <X class="size-4" />
                                    {{ t.procurement.reject }}
                                </Button>
                            </template>
                            <Button
                                v-if="
                                    can.manage_orders &&
                                    request.status === 'approved' &&
                                    !request.order
                                "
                                size="sm"
                                variant="outline"
                                @click="openOrderDialog(request)"
                            >
                                <Truck class="size-4" />
                                {{ t.procurement.create_order }}
                            </Button>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl bg-muted/50 p-3">
                            <p class="text-xs text-muted-foreground">
                                {{ t.procurement.budget_amount }}
                            </p>
                            <p class="mt-1 font-semibold text-foreground">
                                {{
                                    formatCurrency(
                                        request.budget_amount,
                                        request.currency,
                                    )
                                }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-muted/50 p-3">
                            <p class="text-xs text-muted-foreground">
                                {{ t.procurement.amount }}
                            </p>
                            <p class="mt-1 font-semibold text-foreground">
                                {{
                                    formatCurrency(
                                        request.estimated_total,
                                        request.currency,
                                    )
                                }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-muted/50 p-3">
                            <p class="text-xs text-muted-foreground">
                                {{ t.procurement.items }}
                            </p>
                            <p class="mt-1 font-semibold text-foreground">
                                {{ request.items.length }}
                            </p>
                        </div>
                    </div>

                    <div
                        class="mt-4 overflow-x-auto rounded-xl border border-border"
                    >
                        <table class="w-full min-w-[720px] text-sm">
                            <thead
                                class="bg-muted/60 text-left text-xs text-muted-foreground"
                            >
                                <tr>
                                    <th class="px-4 py-3 font-medium">
                                        {{ t.procurement.item_name }}
                                    </th>
                                    <th class="px-4 py-3 font-medium">
                                        {{ t.procurement.quantity }}
                                    </th>
                                    <th class="px-4 py-3 font-medium">
                                        {{ t.procurement.target_unit_price }}
                                    </th>
                                    <th class="px-4 py-3 font-medium">
                                        {{ t.procurement.production_reference }}
                                    </th>
                                    <th class="px-4 py-3 font-medium">
                                        {{ t.procurement.warehouse_place }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-border">
                                <tr
                                    v-for="item in request.items"
                                    :key="item.id"
                                >
                                    <td
                                        class="px-4 py-3 font-medium text-foreground"
                                    >
                                        {{ item.item_name }}
                                        <span
                                            v-if="item.sku"
                                            class="block text-xs text-muted-foreground"
                                        >
                                            {{ item.sku }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ item.quantity }} {{ item.unit }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{
                                            formatCurrency(
                                                item.target_unit_price,
                                                request.currency,
                                            )
                                        }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ item.production_reference ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        {{ item.warehouse_place ?? '—' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </article>

                <div
                    v-if="purchaseRequests.length === 0"
                    class="rounded-2xl border border-dashed border-border bg-card py-16 text-center text-sm text-muted-foreground"
                >
                    {{ t.procurement.empty_requests }}
                </div>
            </section>

            <section
                v-else-if="activeTab === 'suppliers'"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
            >
                <article
                    v-for="supplier in suppliers"
                    :key="supplier.id"
                    class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h2 class="truncate font-bold text-foreground">
                                {{ supplier.name }}
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ t.procurement.bin }}:
                                {{ supplier.bin ?? '—' }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Badge
                                :variant="
                                    supplier.is_active ? 'default' : 'secondary'
                                "
                            >
                                {{
                                    supplier.is_active
                                        ? t.procurement.is_active
                                        : statusLabel('cancelled')
                                }}
                            </Badge>
                            <Button
                                v-if="can.manage"
                                size="icon"
                                variant="ghost"
                                @click="openSupplierDialog(supplier)"
                            >
                                <Pencil class="size-4" />
                            </Button>
                        </div>
                    </div>
                    <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                {{ t.procurement.contact_person }}
                            </dt>
                            <dd class="mt-1 text-foreground">
                                {{ supplier.contact_person ?? '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                {{ t.procurement.rating }}
                            </dt>
                            <dd class="mt-1 text-foreground">
                                {{ supplier.rating ?? '—' }} / 5
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                {{ t.procurement.lead_time_days }}
                            </dt>
                            <dd class="mt-1 text-foreground">
                                {{ supplier.lead_time_days }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                {{ t.procurement.payment_terms_days }}
                            </dt>
                            <dd class="mt-1 text-foreground">
                                {{ supplier.payment_terms_days }}
                            </dd>
                        </div>
                    </dl>
                    <div
                        class="mt-5 flex items-center justify-between border-t border-border pt-4 text-xs text-muted-foreground"
                    >
                        <span
                            >{{ t.procurement.price_comparison }}:
                            {{ supplier.quotation_count }}</span
                        >
                        <span
                            >{{ t.procurement.orders }}:
                            {{ supplier.order_count }}</span
                        >
                    </div>
                </article>

                <div
                    v-if="suppliers.length === 0"
                    class="col-span-full rounded-2xl border border-dashed border-border bg-card py-16 text-center text-sm text-muted-foreground"
                >
                    {{ t.procurement.empty_suppliers }}
                </div>
            </section>

            <section
                v-else-if="activeTab === 'price_comparison'"
                class="space-y-5"
            >
                <div
                    class="rounded-2xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200"
                >
                    {{ t.procurement.quote_matrix_hint }}
                </div>

                <template v-for="request in purchaseRequests" :key="request.id">
                    <article
                        v-for="item in request.items"
                        :key="item.id"
                        class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                    >
                        <div
                            class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                        >
                            <div>
                                <p class="text-xs font-medium text-primary">
                                    {{ request.number }} · {{ request.title }}
                                </p>
                                <h2
                                    class="mt-1 text-lg font-bold text-foreground"
                                >
                                    {{ item.item_name }} · {{ item.quantity }}
                                    {{ item.unit }}
                                </h2>
                                <p class="mt-1 text-sm text-muted-foreground">
                                    {{ t.procurement.target_unit_price }}:
                                    {{
                                        formatCurrency(
                                            item.target_unit_price,
                                            request.currency,
                                        )
                                    }}
                                </p>
                            </div>
                            <Button
                                v-if="can.manage"
                                size="sm"
                                variant="outline"
                                @click="openQuotationDialog(request, item)"
                            >
                                <Plus class="size-4" />
                                {{ t.procurement.add_quote }}
                            </Button>
                        </div>

                        <div
                            v-if="item.quotations.length"
                            class="mt-4 overflow-x-auto rounded-xl border border-border"
                        >
                            <table class="w-full min-w-[760px] text-sm">
                                <thead
                                    class="bg-muted/60 text-left text-xs text-muted-foreground"
                                >
                                    <tr>
                                        <th class="px-4 py-3 font-medium">
                                            {{ t.procurement.supplier }}
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            {{ t.procurement.unit_price }}
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            {{ t.procurement.tax_percent }}
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            {{ t.procurement.delivery_cost }}
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            {{
                                                t.procurement.landed_unit_price
                                            }}
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            {{ t.procurement.total }}
                                        </th>
                                        <th class="px-4 py-3 font-medium">
                                            {{ t.procurement.lead_time_days }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    <tr
                                        v-for="quotation in item.quotations"
                                        :key="quotation.id"
                                        :class="
                                            quotation.id ===
                                            item.best_quotation_id
                                                ? 'bg-emerald-500/5'
                                                : ''
                                        "
                                    >
                                        <td
                                            class="px-4 py-3 font-semibold text-foreground"
                                        >
                                            <span
                                                class="inline-flex items-center gap-2"
                                            >
                                                {{ quotation.supplier_name }}
                                                <Badge
                                                    v-if="
                                                        quotation.id ===
                                                        item.best_quotation_id
                                                    "
                                                    class="bg-emerald-600"
                                                >
                                                    <Check class="size-3" />
                                                    Best
                                                </Badge>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            {{
                                                formatCurrency(
                                                    quotation.unit_price,
                                                    quotation.currency,
                                                )
                                            }}
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ quotation.tax_percent }}%
                                        </td>
                                        <td class="px-4 py-3">
                                            {{
                                                formatCurrency(
                                                    quotation.delivery_cost,
                                                    quotation.currency,
                                                )
                                            }}
                                        </td>
                                        <td class="px-4 py-3 font-medium">
                                            {{
                                                formatCurrency(
                                                    quotation.landed_unit_price,
                                                    quotation.currency,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-4 py-3 font-bold text-foreground"
                                        >
                                            {{
                                                formatCurrency(
                                                    quotation.landed_total,
                                                    quotation.currency,
                                                )
                                            }}
                                        </td>
                                        <td class="px-4 py-3">
                                            {{ quotation.lead_time_days }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p
                            v-else
                            class="mt-4 rounded-xl bg-muted/50 py-8 text-center text-sm text-muted-foreground"
                        >
                            {{ t.procurement.empty_quotes }}
                        </p>
                    </article>
                </template>

                <div
                    v-if="purchaseRequests.length === 0"
                    class="rounded-2xl border border-dashed border-border bg-card py-16 text-center text-sm text-muted-foreground"
                >
                    {{ t.procurement.empty_quotes }}
                </div>
            </section>

            <section v-else-if="activeTab === 'orders'" class="space-y-4">
                <div v-if="can.manage_orders" class="flex justify-end">
                    <Button
                        :disabled="requestsReadyForOrder.length === 0"
                        @click="openOrderDialog()"
                    >
                        <Plus class="size-4" />
                        {{ t.procurement.create_order }}
                    </Button>
                </div>

                <article
                    v-for="order in purchaseOrders"
                    :key="order.id"
                    class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-bold text-foreground">
                                    {{ order.number }} ·
                                    {{ order.supplier_name }}
                                </h2>
                                <Badge
                                    variant="outline"
                                    :class="statusClass(order.status)"
                                >
                                    {{ statusLabel(order.status) }}
                                </Badge>
                            </div>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ order.request_number }} ·
                                {{ order.request_title }} ·
                                {{ formatDate(order.ordered_at) }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="
                                    can.manage_orders &&
                                    order.status === 'draft'
                                "
                                size="sm"
                                @click="markOrderSent(order)"
                            >
                                <Send class="size-4" />
                                {{ t.procurement.send_order }}
                            </Button>
                            <Button
                                v-if="
                                    can.receive_orders &&
                                    ['sent', 'partially_received'].includes(
                                        order.status,
                                    ) &&
                                    order.items.some(
                                        (item) => item.remaining_quantity > 0,
                                    )
                                "
                                size="sm"
                                variant="outline"
                                :disabled="warehousePlaces.length === 0"
                                @click="openReceiptDialog(order)"
                            >
                                <ArrowDownToLine class="size-4" />
                                {{ t.procurement.record_receipt }}
                            </Button>
                        </div>
                    </div>

                    <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-xl bg-muted/50 p-3">
                            <p class="text-xs text-muted-foreground">
                                {{ t.procurement.amount }}
                            </p>
                            <p class="mt-1 font-bold text-foreground">
                                {{
                                    formatCurrency(
                                        order.total_amount,
                                        order.currency,
                                    )
                                }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-muted/50 p-3">
                            <p class="text-xs text-muted-foreground">
                                {{ t.procurement.expected_at }}
                            </p>
                            <p class="mt-1 font-semibold text-foreground">
                                {{ formatDate(order.expected_at) }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-muted/50 p-3">
                            <p class="text-xs text-muted-foreground">
                                {{ t.procurement.receipts }}
                            </p>
                            <p class="mt-1 font-semibold text-foreground">
                                {{ order.receipt_count }}
                            </p>
                        </div>
                        <div class="rounded-xl bg-muted/50 p-3">
                            <p class="text-xs text-muted-foreground">
                                {{ t.procurement.returns }}
                            </p>
                            <p class="mt-1 font-semibold text-foreground">
                                {{ order.return_count }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2">
                        <div
                            v-for="item in order.items"
                            :key="item.id"
                            class="grid gap-2 rounded-xl border border-border p-3 text-sm sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center"
                        >
                            <div>
                                <p class="font-semibold text-foreground">
                                    {{ item.item_name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ item.warehouse_place ?? '—' }}
                                </p>
                            </div>
                            <p class="text-muted-foreground">
                                {{ item.received_quantity }} /
                                {{ item.quantity }} {{ item.unit }}
                            </p>
                            <p class="font-semibold text-foreground">
                                {{
                                    formatCurrency(
                                        item.line_total,
                                        order.currency,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                </article>

                <div
                    v-if="purchaseOrders.length === 0"
                    class="rounded-2xl border border-dashed border-border bg-card py-16 text-center text-sm text-muted-foreground"
                >
                    {{ t.procurement.empty_orders }}
                </div>
            </section>

            <section v-else-if="activeTab === 'receipts'" class="space-y-4">
                <article
                    v-for="receipt in goodsReceipts"
                    :key="receipt.id"
                    class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-bold text-foreground">
                                    {{ receipt.number }} ·
                                    {{ receipt.supplier_name }}
                                </h2>
                                <Badge
                                    variant="outline"
                                    :class="statusClass(receipt.status)"
                                >
                                    {{ statusLabel(receipt.status) }}
                                </Badge>
                            </div>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ receipt.order_number }} ·
                                {{ formatDate(receipt.received_at) }} ·
                                {{ receipt.received_by ?? '—' }}
                            </p>
                            <p
                                v-if="receipt.external_reference"
                                class="mt-1 text-xs text-muted-foreground"
                            >
                                {{ t.procurement.external_reference }}:
                                {{ receipt.external_reference }}
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <p class="font-bold text-foreground">
                                {{
                                    formatCurrency(
                                        receipt.total_amount,
                                        receipt.currency,
                                    )
                                }}
                            </p>
                            <Button
                                v-if="
                                    can.return_goods &&
                                    receipt.items.some(
                                        (item) => item.returnable_quantity > 0,
                                    )
                                "
                                size="sm"
                                variant="outline"
                                @click="openReturnDialog(receipt)"
                            >
                                <ArrowUpFromLine class="size-4" />
                                {{ t.procurement.record_return }}
                            </Button>
                        </div>
                    </div>
                    <div class="mt-4 grid gap-2 md:grid-cols-2">
                        <div
                            v-for="item in receipt.items"
                            :key="item.id"
                            class="rounded-xl border border-border p-3 text-sm"
                        >
                            <div class="flex justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-foreground">
                                        {{ item.item_name }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ item.warehouse_item_name }}
                                    </p>
                                </div>
                                <p>{{ item.quantity }} {{ item.unit }}</p>
                            </div>
                        </div>
                    </div>
                </article>

                <div
                    v-if="goodsReceipts.length === 0"
                    class="rounded-2xl border border-dashed border-border bg-card py-16 text-center text-sm text-muted-foreground"
                >
                    {{ t.procurement.empty_receipts }}
                </div>
            </section>

            <section v-else-if="activeTab === 'returns'" class="space-y-4">
                <article
                    v-for="purchaseReturn in purchaseReturns"
                    :key="purchaseReturn.id"
                    class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                >
                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-bold text-foreground">
                                    {{ purchaseReturn.number }} ·
                                    {{ purchaseReturn.supplier_name }}
                                </h2>
                                <Badge
                                    variant="outline"
                                    :class="statusClass(purchaseReturn.status)"
                                >
                                    {{ statusLabel(purchaseReturn.status) }}
                                </Badge>
                            </div>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ purchaseReturn.order_number }} ·
                                {{ formatDate(purchaseReturn.returned_at) }} ·
                                {{ purchaseReturn.created_by ?? '—' }}
                            </p>
                            <p class="mt-2 text-sm text-foreground/80">
                                {{ t.procurement.reason }}:
                                {{ purchaseReturn.reason }}
                            </p>
                        </div>
                        <p class="font-bold text-foreground">
                            {{
                                formatCurrency(
                                    purchaseReturn.total_amount,
                                    purchaseReturn.currency,
                                )
                            }}
                        </p>
                    </div>
                    <div class="mt-4 flex flex-wrap gap-2">
                        <span
                            v-for="item in purchaseReturn.items"
                            :key="item.id"
                            class="rounded-full bg-muted px-3 py-1.5 text-xs text-muted-foreground"
                        >
                            {{ item.item_name }} · {{ item.quantity }}
                            {{ item.unit }}
                        </span>
                    </div>
                </article>

                <div
                    v-if="purchaseReturns.length === 0"
                    class="rounded-2xl border border-dashed border-border bg-card py-16 text-center text-sm text-muted-foreground"
                >
                    {{ t.procurement.empty_returns }}
                </div>
            </section>
        </div>

        <Dialog v-model:open="requestDialogOpen">
            <DialogContent class="max-h-[90vh] max-w-4xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{ t.procurement.new_request }}</DialogTitle>
                    <DialogDescription>{{
                        t.procurement.description
                    }}</DialogDescription>
                </DialogHeader>

                <form class="space-y-5" @submit.prevent="submitRequest">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="request-title">{{
                                t.procurement.item_name
                            }}</Label>
                            <Input
                                id="request-title"
                                v-model="requestForm.title"
                                required
                            />
                            <InputError :message="requestForm.errors.title" />
                        </div>
                        <div class="space-y-2">
                            <Label for="request-date">{{
                                t.procurement.needed_at
                            }}</Label>
                            <Input
                                id="request-date"
                                v-model="requestForm.needed_at"
                                type="date"
                            />
                            <InputError
                                :message="requestForm.errors.needed_at"
                            />
                        </div>
                        <div class="grid grid-cols-[minmax(0,1fr)_100px] gap-2">
                            <div class="space-y-2">
                                <Label for="request-budget">{{
                                    t.procurement.budget_amount
                                }}</Label>
                                <Input
                                    id="request-budget"
                                    v-model.number="requestForm.budget_amount"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    required
                                />
                            </div>
                            <div class="space-y-2">
                                <Label for="request-currency">{{
                                    t.procurement.currency
                                }}</Label>
                                <select
                                    id="request-currency"
                                    v-model="requestForm.currency"
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option
                                        v-for="currency in [
                                            'KZT',
                                            'USD',
                                            'EUR',
                                            'RUB',
                                        ]"
                                        :key="currency"
                                        :value="currency"
                                    >
                                        {{ currency }}
                                    </option>
                                </select>
                            </div>
                            <InputError
                                class="col-span-2"
                                :message="requestForm.errors.budget_amount"
                            />
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="request-justification">{{
                                t.procurement.justification
                            }}</Label>
                            <textarea
                                id="request-justification"
                                v-model="requestForm.justification"
                                rows="3"
                                class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                            <InputError
                                :message="requestForm.errors.justification"
                            />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center justify-between">
                            <h3 class="font-semibold text-foreground">
                                {{ t.procurement.items }}
                            </h3>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="
                                    requestForm.items.push(emptyRequestItem())
                                "
                            >
                                <Plus class="size-4" />
                                {{ t.procurement.add_item }}
                            </Button>
                        </div>
                        <div
                            v-for="(item, index) in requestForm.items"
                            :key="index"
                            class="space-y-3 rounded-2xl border border-border p-4"
                        >
                            <div class="flex items-center justify-between">
                                <p
                                    class="text-sm font-semibold text-foreground"
                                >
                                    #{{ index + 1 }}
                                </p>
                                <Button
                                    v-if="requestForm.items.length > 1"
                                    type="button"
                                    size="icon"
                                    variant="ghost"
                                    @click="requestForm.items.splice(index, 1)"
                                >
                                    <X class="size-4" />
                                </Button>
                            </div>
                            <div
                                class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                            >
                                <div class="space-y-2 sm:col-span-2">
                                    <Label :for="`item-name-${index}`">{{
                                        t.procurement.item_name
                                    }}</Label>
                                    <Input
                                        :id="`item-name-${index}`"
                                        v-model="item.item_name"
                                        required
                                    />
                                    <InputError
                                        :message="
                                            requestForm.errors[
                                                `items.${index}.item_name`
                                            ]
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label :for="`item-sku-${index}`">{{
                                        t.procurement.sku
                                    }}</Label>
                                    <Input
                                        :id="`item-sku-${index}`"
                                        v-model="item.sku"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label :for="`item-unit-${index}`">{{
                                        t.procurement.unit
                                    }}</Label>
                                    <Input
                                        :id="`item-unit-${index}`"
                                        v-model="item.unit"
                                        required
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label :for="`item-quantity-${index}`">{{
                                        t.procurement.quantity
                                    }}</Label>
                                    <Input
                                        :id="`item-quantity-${index}`"
                                        v-model.number="item.quantity"
                                        type="number"
                                        min="1"
                                        step="1"
                                        required
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label :for="`item-price-${index}`">{{
                                        t.procurement.target_unit_price
                                    }}</Label>
                                    <Input
                                        :id="`item-price-${index}`"
                                        v-model.number="item.target_unit_price"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        required
                                    />
                                </div>
                                <div class="space-y-2 sm:col-span-2">
                                    <Label :for="`item-production-${index}`">{{
                                        t.procurement.production_reference
                                    }}</Label>
                                    <Input
                                        :id="`item-production-${index}`"
                                        v-model="item.production_reference"
                                    />
                                </div>
                                <div class="space-y-2 sm:col-span-2">
                                    <Label :for="`item-place-${index}`">{{
                                        t.procurement.warehouse_place
                                    }}</Label>
                                    <select
                                        :id="`item-place-${index}`"
                                        v-model.number="item.warehouse_place_id"
                                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        <option :value="null">
                                            {{ t.procurement.select_place }}
                                        </option>
                                        <option
                                            v-for="place in warehousePlaces"
                                            :key="place.id"
                                            :value="place.id"
                                        >
                                            {{ place.path }}
                                        </option>
                                    </select>
                                </div>
                                <div class="space-y-2 sm:col-span-2">
                                    <Label :for="`item-stock-${index}`">{{
                                        t.procurement.warehouse_item
                                    }}</Label>
                                    <select
                                        :id="`item-stock-${index}`"
                                        v-model.number="item.warehouse_item_id"
                                        class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        <option :value="null">—</option>
                                        <option
                                            v-for="warehouseItem in warehouseItems"
                                            :key="warehouseItem.id"
                                            :value="warehouseItem.id"
                                        >
                                            {{ warehouseItem.name }} ·
                                            {{ warehouseItem.quantity }}
                                        </option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="requestDialogOpen = false"
                            >{{ t.procurement.cancel }}</Button
                        >
                        <Button
                            type="submit"
                            :disabled="requestForm.processing"
                            >{{ t.procurement.save }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="supplierDialogOpen">
            <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            editingSupplierId
                                ? t.procurement.edit_supplier
                                : t.procurement.new_supplier
                        }}
                    </DialogTitle>
                    <DialogDescription>{{
                        t.procurement.suppliers
                    }}</DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitSupplier">
                    <div class="space-y-2">
                        <Label for="supplier-contact">{{
                            t.procurement.supplier
                        }}</Label>
                        <select
                            id="supplier-contact"
                            v-model.number="supplierForm.contact_id"
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                        >
                            <option :value="null">—</option>
                            <option
                                v-for="contact in companyContacts"
                                :key="contact.id"
                                :value="contact.id"
                            >
                                {{ contact.name }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="supplier-name">{{
                                t.procurement.item_name
                            }}</Label>
                            <Input
                                id="supplier-name"
                                v-model="supplierForm.name"
                                required
                            />
                            <InputError :message="supplierForm.errors.name" />
                        </div>
                        <div class="space-y-2">
                            <Label for="supplier-bin">{{
                                t.procurement.bin
                            }}</Label>
                            <Input
                                id="supplier-bin"
                                v-model="supplierForm.bin"
                                maxlength="12"
                            />
                            <InputError :message="supplierForm.errors.bin" />
                        </div>
                        <div class="space-y-2">
                            <Label for="supplier-person">{{
                                t.procurement.contact_person
                            }}</Label>
                            <Input
                                id="supplier-person"
                                v-model="supplierForm.contact_person"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="supplier-email">{{
                                t.procurement.email
                            }}</Label>
                            <Input
                                id="supplier-email"
                                v-model="supplierForm.email"
                                type="email"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="supplier-phone">{{
                                t.procurement.phone
                            }}</Label>
                            <Input
                                id="supplier-phone"
                                v-model="supplierForm.phone"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="supplier-payment">{{
                                t.procurement.payment_terms_days
                            }}</Label>
                            <Input
                                id="supplier-payment"
                                v-model.number="supplierForm.payment_terms_days"
                                type="number"
                                min="0"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="supplier-lead">{{
                                t.procurement.lead_time_days
                            }}</Label>
                            <Input
                                id="supplier-lead"
                                v-model.number="supplierForm.lead_time_days"
                                type="number"
                                min="0"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="supplier-rating">{{
                                t.procurement.rating
                            }}</Label>
                            <Input
                                id="supplier-rating"
                                v-model.number="supplierForm.rating"
                                type="number"
                                min="0"
                                max="5"
                                step="0.1"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="supplier-currency">{{
                                t.procurement.currency
                            }}</Label>
                            <select
                                id="supplier-currency"
                                v-model="supplierForm.currency"
                                class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option
                                    v-for="currency in [
                                        'KZT',
                                        'USD',
                                        'EUR',
                                        'RUB',
                                    ]"
                                    :key="currency"
                                    :value="currency"
                                >
                                    {{ currency }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <label
                        class="flex items-center gap-2 text-sm text-foreground"
                    >
                        <input
                            v-model="supplierForm.is_active"
                            type="checkbox"
                            class="size-4 rounded border-input"
                        />
                        {{ t.procurement.is_active }}
                    </label>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="supplierDialogOpen = false"
                            >{{ t.procurement.cancel }}</Button
                        >
                        <Button
                            type="submit"
                            :disabled="supplierForm.processing"
                            >{{ t.procurement.save }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="quotationDialogOpen">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ t.procurement.add_quote }}</DialogTitle>
                    <DialogDescription>
                        {{ selectedQuotationRequest?.number }} ·
                        {{ selectedQuotationItem?.item_name }}
                    </DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitQuotation">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2 sm:col-span-2">
                            <Label for="quote-supplier">{{
                                t.procurement.supplier
                            }}</Label>
                            <select
                                id="quote-supplier"
                                v-model.number="quotationForm.supplier_id"
                                required
                                class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option :value="null" disabled>
                                    {{ t.procurement.select_supplier }}
                                </option>
                                <option
                                    v-for="supplier in suppliers.filter(
                                        (item) => item.is_active,
                                    )"
                                    :key="supplier.id"
                                    :value="supplier.id"
                                >
                                    {{ supplier.name }}
                                </option>
                            </select>
                            <InputError
                                :message="quotationForm.errors.supplier_id"
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="quote-price">{{
                                t.procurement.unit_price
                            }}</Label>
                            <Input
                                id="quote-price"
                                v-model.number="quotationForm.unit_price"
                                type="number"
                                min="0"
                                step="0.01"
                                required
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="quote-tax">{{
                                t.procurement.tax_percent
                            }}</Label>
                            <Input
                                id="quote-tax"
                                v-model.number="quotationForm.tax_percent"
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                required
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="quote-delivery">{{
                                t.procurement.delivery_cost
                            }}</Label>
                            <Input
                                id="quote-delivery"
                                v-model.number="quotationForm.delivery_cost"
                                type="number"
                                min="0"
                                step="0.01"
                                required
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="quote-lead">{{
                                t.procurement.lead_time_days
                            }}</Label>
                            <Input
                                id="quote-lead"
                                v-model.number="quotationForm.lead_time_days"
                                type="number"
                                min="0"
                                required
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="quote-date">{{
                                t.procurement.quoted_at
                            }}</Label>
                            <Input
                                id="quote-date"
                                v-model="quotationForm.quoted_at"
                                type="date"
                                required
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="quote-valid">{{
                                t.procurement.valid_until
                            }}</Label>
                            <Input
                                id="quote-valid"
                                v-model="quotationForm.valid_until"
                                type="date"
                            />
                        </div>
                    </div>
                    <InputError
                        :message="quotationForm.errors.purchase_request_item_id"
                    />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="quotationDialogOpen = false"
                            >{{ t.procurement.cancel }}</Button
                        >
                        <Button
                            type="submit"
                            :disabled="quotationForm.processing"
                            >{{ t.procurement.save }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="rejectionDialogOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ t.procurement.reject }}</DialogTitle>
                    <DialogDescription
                        >{{ selectedRejectionRequest?.number }} ·
                        {{ selectedRejectionRequest?.title }}</DialogDescription
                    >
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitRejection">
                    <div class="space-y-2">
                        <Label for="rejection-reason">{{
                            t.procurement.rejection_reason
                        }}</Label>
                        <textarea
                            id="rejection-reason"
                            v-model="rejectionForm.rejection_reason"
                            rows="4"
                            required
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                        <InputError
                            :message="rejectionForm.errors.rejection_reason"
                        />
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="rejectionDialogOpen = false"
                            >{{ t.procurement.cancel }}</Button
                        >
                        <Button
                            type="submit"
                            variant="destructive"
                            :disabled="rejectionForm.processing"
                            >{{ t.procurement.reject }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="orderDialogOpen">
            <DialogContent class="max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{{ t.procurement.create_order }}</DialogTitle>
                    <DialogDescription>{{
                        t.procurement.price_comparison
                    }}</DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitOrder">
                    <div class="space-y-2">
                        <Label for="order-request">{{
                            t.procurement.request
                        }}</Label>
                        <select
                            id="order-request"
                            v-model.number="orderForm.purchase_request_id"
                            required
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            @change="changeOrderRequest"
                        >
                            <option :value="null" disabled>
                                {{ t.procurement.select_request }}
                            </option>
                            <option
                                v-for="request in requestsReadyForOrder"
                                :key="request.id"
                                :value="request.id"
                            >
                                {{ request.number }} · {{ request.title }}
                            </option>
                        </select>
                        <InputError
                            :message="orderForm.errors.purchase_request_id"
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="order-supplier">{{
                            t.procurement.supplier
                        }}</Label>
                        <select
                            id="order-supplier"
                            v-model.number="orderForm.supplier_id"
                            required
                            class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                            @change="syncOrderQuotations"
                        >
                            <option :value="null" disabled>
                                {{ t.procurement.select_supplier }}
                            </option>
                            <option
                                v-for="supplier in supplierOptionsForOrder"
                                :key="supplier.id"
                                :value="supplier.id"
                            >
                                {{ supplier.name }}
                            </option>
                        </select>
                        <InputError :message="orderForm.errors.supplier_id" />
                        <InputError :message="orderForm.errors.quotation_ids" />
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="order-date">{{
                                t.procurement.ordered_at
                            }}</Label>
                            <Input
                                id="order-date"
                                v-model="orderForm.ordered_at"
                                type="date"
                                required
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="order-expected">{{
                                t.procurement.expected_at
                            }}</Label>
                            <Input
                                id="order-expected"
                                v-model="orderForm.expected_at"
                                type="date"
                            />
                        </div>
                    </div>
                    <div class="rounded-xl bg-muted p-4">
                        <p class="text-xs text-muted-foreground">
                            {{ t.procurement.total }}
                        </p>
                        <p class="mt-1 text-xl font-bold text-foreground">
                            {{
                                formatCurrency(
                                    quotationTotalForOrder,
                                    purchaseRequests.find(
                                        (request) =>
                                            request.id ===
                                            orderForm.purchase_request_id,
                                    )?.currency ?? 'KZT',
                                )
                            }}
                        </p>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="orderDialogOpen = false"
                            >{{ t.procurement.cancel }}</Button
                        >
                        <Button
                            type="submit"
                            :disabled="
                                orderForm.processing ||
                                orderForm.quotation_ids.length === 0
                            "
                            >{{ t.procurement.create_order }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="receiptDialogOpen">
            <DialogContent class="max-h-[90vh] max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{
                        t.procurement.record_receipt
                    }}</DialogTitle>
                    <DialogDescription>{{
                        t.procurement.stock_integration_description
                    }}</DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitReceipt">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="receipt-date">{{
                                t.procurement.received_at
                            }}</Label>
                            <Input
                                id="receipt-date"
                                v-model="receiptForm.received_at"
                                type="date"
                                required
                            />
                        </div>
                        <div class="space-y-2">
                            <Label for="receipt-reference">{{
                                t.procurement.external_reference
                            }}</Label>
                            <Input
                                id="receipt-reference"
                                v-model="receiptForm.external_reference"
                            />
                        </div>
                    </div>
                    <div class="space-y-3">
                        <div
                            v-for="(item, index) in receiptForm.items"
                            :key="item.purchase_order_item_id"
                            class="grid gap-3 rounded-xl border border-border p-4 sm:grid-cols-[minmax(0,1fr)_140px_auto] sm:items-end"
                        >
                            <div class="space-y-2">
                                <Label :for="`receipt-place-${index}`"
                                    >{{ item.item_name }} ·
                                    {{ t.procurement.warehouse_place }}</Label
                                >
                                <select
                                    :id="`receipt-place-${index}`"
                                    v-model.number="item.warehouse_place_id"
                                    required
                                    class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option :value="null" disabled>
                                        {{ t.procurement.select_place }}
                                    </option>
                                    <option
                                        v-for="place in warehousePlaces"
                                        :key="place.id"
                                        :value="place.id"
                                    >
                                        {{ place.path }}
                                    </option>
                                </select>
                                <InputError
                                    :message="
                                        receiptForm.errors[
                                            `items.${index}.warehouse_place_id`
                                        ]
                                    "
                                />
                            </div>
                            <Button
                                v-if="receiptForm.items.length > 1"
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="mb-0.5"
                                @click="receiptForm.items.splice(index, 1)"
                            >
                                <X class="size-4" />
                            </Button>
                            <div class="space-y-2">
                                <Label :for="`receipt-quantity-${index}`">{{
                                    t.procurement.quantity
                                }}</Label>
                                <Input
                                    :id="`receipt-quantity-${index}`"
                                    v-model.number="item.quantity"
                                    type="number"
                                    min="1"
                                    :max="item.max_quantity"
                                    required
                                />
                            </div>
                        </div>
                    </div>
                    <InputError :message="receiptForm.errors.items" />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="receiptDialogOpen = false"
                            >{{ t.procurement.cancel }}</Button
                        >
                        <Button
                            type="submit"
                            :disabled="receiptForm.processing"
                            >{{ t.procurement.record_receipt }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog v-model:open="returnDialogOpen">
            <DialogContent class="max-h-[90vh] max-w-2xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{ t.procurement.record_return }}</DialogTitle>
                    <DialogDescription>{{
                        t.procurement.stock_integration_description
                    }}</DialogDescription>
                </DialogHeader>
                <form class="space-y-4" @submit.prevent="submitReturn">
                    <div class="space-y-2">
                        <Label for="return-date">{{
                            t.procurement.returned_at
                        }}</Label>
                        <Input
                            id="return-date"
                            v-model="returnForm.returned_at"
                            type="date"
                            required
                        />
                    </div>
                    <div class="space-y-2">
                        <Label for="return-reason">{{
                            t.procurement.reason
                        }}</Label>
                        <textarea
                            id="return-reason"
                            v-model="returnForm.reason"
                            rows="3"
                            required
                            class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        />
                        <InputError :message="returnForm.errors.reason" />
                    </div>
                    <div class="space-y-3">
                        <div
                            v-for="(item, index) in returnForm.items"
                            :key="item.goods_receipt_item_id"
                            class="grid gap-3 rounded-xl border border-border p-4 sm:grid-cols-[minmax(0,1fr)_140px_auto] sm:items-end"
                        >
                            <p
                                class="pb-2 text-sm font-semibold text-foreground"
                            >
                                {{ item.item_name }}
                            </p>
                            <div class="space-y-2">
                                <Label :for="`return-quantity-${index}`">{{
                                    t.procurement.quantity
                                }}</Label>
                                <Input
                                    :id="`return-quantity-${index}`"
                                    v-model.number="item.quantity"
                                    type="number"
                                    min="1"
                                    :max="item.max_quantity"
                                    required
                                />
                            </div>
                            <Button
                                v-if="returnForm.items.length > 1"
                                type="button"
                                size="icon"
                                variant="ghost"
                                class="mb-0.5"
                                @click="returnForm.items.splice(index, 1)"
                            >
                                <X class="size-4" />
                            </Button>
                        </div>
                    </div>
                    <InputError :message="returnForm.errors.items" />
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="returnDialogOpen = false"
                            >{{ t.procurement.cancel }}</Button
                        >
                        <Button
                            type="submit"
                            :disabled="returnForm.processing"
                            >{{ t.procurement.record_return }}</Button
                        >
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>

<script setup lang="ts">
import {
    Head,
    Link,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/vue3';
import {
    CircleDollarSign,
    GripVertical,
    LayoutGrid,
    PencilLine,
    Plus,
    ShieldCheck,
    Trash2,
    UsersRound,
    Workflow,
} from '@lucide/vue';
import { computed, ref, watchEffect } from 'vue';
import {
    destroy as destroyFunnel,
    destroyDeal,
    destroyStage,
    index,
    moveDeal,
    show,
    store,
    storeDeal,
    storeStage,
    update,
    updateDeal,
    updateStage,
} from '@/actions/App/Http/Controllers/CrmFunnelController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useLanguage } from '@/composables/useLanguage';

type SummaryUser = {
    id: number;
    name: string;
    last_name: string | null;
    email: string;
};

type GroupOption = {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    users_count: number;
};

type FunnelFieldDefinition = {
    key: string;
    label: string;
    type: 'text' | 'textarea' | 'number' | 'date';
    is_required: boolean;
};

type FunnelStage = {
    id: number;
    name: string;
    color: string | null;
    type: 'open' | 'won' | 'lost';
    sort_order: number;
    deals_count: number;
    deals: FunnelDeal[];
};

type FunnelDeal = {
    id: number;
    crm_funnel_stage_id: number;
    title: string;
    company_name: string | null;
    contact_name: string | null;
    contact_phone: string | null;
    contact_email: string | null;
    amount: number | null;
    currency: string | null;
    expected_close_at: string | null;
    description: string | null;
    custom_fields: Record<string, string | null>;
    sort_order: number;
    responsible_user: SummaryUser | null;
    updated_at: string | null;
};

type FunnelSummary = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    color: string | null;
    is_active: boolean;
    stages_count: number;
    deals_count: number;
    deals_amount_sum: number;
};

type ActiveFunnel = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    color: string | null;
    is_active: boolean;
    deal_fields: FunnelFieldDefinition[];
    group_ids: number[];
    groups: Array<{ id: number; name: string }>;
    stages: FunnelStage[];
    stats: {
        deals_count: number;
        active_deals_count: number;
        won_deals_count: number;
        lost_deals_count: number;
        amount_sum: number;
    };
};

type Option = {
    value: string;
    label: string;
};

type CustomFieldDraft = {
    key: string;
    label: string;
    type: 'text' | 'textarea' | 'number' | 'date';
    is_required: boolean;
};

type PageProps = {
    errors?: Record<string, string>;
};

const props = defineProps<{
    funnels: FunnelSummary[];
    activeFunnel: ActiveFunnel | null;
    availableUsers: SummaryUser[];
    availableGroups: GroupOption[];
    can: {
        manageFunnels: boolean;
        createDeals: boolean;
    };
    funnelOptions: {
        stageTypes: Option[];
        fieldTypes: Option[];
    };
}>();

const page = usePage<PageProps>();
const { language, t } = useLanguage();

const textareaClass =
    'min-h-[120px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';
const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';

const funnelSheetOpen = ref(false);
const stageSheetOpen = ref(false);
const dealSheetOpen = ref(false);

const funnelFormMode = ref<'create' | 'edit'>('create');
const stageFormMode = ref<'create' | 'edit'>('create');
const dealFormMode = ref<'create' | 'edit'>('create');

const editingStageId = ref<number | null>(null);
const editingDealId = ref<number | null>(null);
const draggedDealId = ref<number | null>(null);

const funnelForm = useForm({
    name: '',
    slug: '',
    description: '',
    color: '#2563eb',
    is_active: true,
    group_ids: [] as number[],
    deal_fields: [] as CustomFieldDraft[],
});

const stageForm = useForm({
    name: '',
    color: '#64748b',
    type: 'open',
    sort_order: 0,
});

const dealForm = useForm({
    crm_funnel_stage_id: '' as number | string,
    title: '',
    company_name: '',
    contact_name: '',
    contact_phone: '',
    contact_email: '',
    amount: '',
    currency: 'USD',
    expected_close_at: '',
    responsible_user_id: '' as number | string,
    description: '',
    custom_fields: {} as Record<string, string | null>,
    sort_order: 0,
});

const canManageFunnels = computed(() => props.can.manageFunnels);

const statsCards = computed(() => {
    if (!props.activeFunnel) {
        return [];
    }

    return [
        {
            key: 'deals',
            label: t.value.funnels.stats_deals,
            value: props.activeFunnel.stats.deals_count,
        },
        {
            key: 'active',
            label: t.value.funnels.stats_active_deals,
            value: props.activeFunnel.stats.active_deals_count,
        },
        {
            key: 'won',
            label: t.value.funnels.stats_won_deals,
            value: props.activeFunnel.stats.won_deals_count,
        },
        {
            key: 'lost',
            label: t.value.funnels.stats_lost_deals,
            value: props.activeFunnel.stats.lost_deals_count,
        },
    ];
});

watchEffect(() => {
    const breadcrumbs = [
        {
            title: t.value.funnels.title,
            href: index(),
        },
    ];

    if (props.activeFunnel) {
        breadcrumbs.push({
            title: props.activeFunnel.name,
            href: show(props.activeFunnel.id),
        });
    }

    setLayoutProps({ breadcrumbs });
});

const fullName = (user: SummaryUser | null): string => {
    if (!user) {
        return t.value.funnels.no_responsible_user;
    }

    return [user.name, user.last_name].filter(Boolean).join(' ');
};

const formatDate = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        language.value === 'ru' ? 'ru-RU' : 'en-US',
        {
            dateStyle: 'medium',
        },
    ).format(new Date(value));
};

const formatMoney = (amount: number, currency: string | null): string => {
    if (!currency) {
        return new Intl.NumberFormat(
            language.value === 'ru' ? 'ru-RU' : 'en-US',
        ).format(amount);
    }

    try {
        return new Intl.NumberFormat(
            language.value === 'ru' ? 'ru-RU' : 'en-US',
            {
                style: 'currency',
                currency,
                maximumFractionDigits: 2,
            },
        ).format(amount);
    } catch {
        return `${amount} ${currency}`;
    }
};

const stageToneClass = (stageType: FunnelStage['type']): string => {
    return {
        open: 'bg-slate-500/10 text-slate-700 dark:text-slate-300',
        won: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        lost: 'bg-rose-500/10 text-rose-700 dark:text-rose-300',
    }[stageType];
};

const newField = (): CustomFieldDraft => ({
    key: '',
    label: '',
    type: 'text',
    is_required: false,
});

const resetFunnelForm = (): void => {
    funnelForm.reset();
    funnelForm.clearErrors();
    funnelForm.color = '#2563eb';
    funnelForm.is_active = true;
    funnelForm.group_ids = [];
    funnelForm.deal_fields = [];
};

const openCreateFunnel = (): void => {
    funnelFormMode.value = 'create';
    resetFunnelForm();
    funnelSheetOpen.value = true;
};

const openEditFunnel = (): void => {
    if (!props.activeFunnel) {
        return;
    }

    funnelFormMode.value = 'edit';
    funnelForm.clearErrors();
    funnelForm.name = props.activeFunnel.name;
    funnelForm.slug = props.activeFunnel.slug;
    funnelForm.description = props.activeFunnel.description ?? '';
    funnelForm.color = props.activeFunnel.color ?? '#2563eb';
    funnelForm.is_active = props.activeFunnel.is_active;
    funnelForm.group_ids = [...props.activeFunnel.group_ids];
    funnelForm.deal_fields = props.activeFunnel.deal_fields.map((field) => ({
        ...field,
    }));
    funnelSheetOpen.value = true;
};

const addCustomField = (): void => {
    funnelForm.deal_fields = [...funnelForm.deal_fields, newField()];
};

const removeCustomField = (index: number): void => {
    funnelForm.deal_fields = funnelForm.deal_fields.filter(
        (_, fieldIndex) => fieldIndex !== index,
    );
};

const toggleGroup = (
    groupId: number,
    checked: boolean | 'indeterminate',
): void => {
    const groupIds = new Set(funnelForm.group_ids);

    if (checked === true) {
        groupIds.add(groupId);
    } else {
        groupIds.delete(groupId);
    }

    funnelForm.group_ids = [...groupIds];
};

const submitFunnel = (): void => {
    funnelForm.transform((data) => ({
        ...data,
        group_ids: [...data.group_ids],
        deal_fields: data.deal_fields.map((field) => ({
            ...field,
            is_required: Boolean(field.is_required),
        })),
    }));

    if (funnelFormMode.value === 'create') {
        funnelForm.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                funnelSheetOpen.value = false;
                resetFunnelForm();
            },
        });

        return;
    }

    if (!props.activeFunnel) {
        return;
    }

    funnelForm.patch(update.url({ crmFunnel: props.activeFunnel.id }), {
        preserveScroll: true,
        onSuccess: () => {
            funnelSheetOpen.value = false;
        },
    });
};

const resetStageForm = (): void => {
    stageForm.reset();
    stageForm.clearErrors();
    stageForm.name = '';
    stageForm.color = '#64748b';
    stageForm.type = 'open';
    stageForm.sort_order = props.activeFunnel?.stages.length ?? 0;
    editingStageId.value = null;
};

const openCreateStage = (): void => {
    if (!props.activeFunnel) {
        return;
    }

    stageFormMode.value = 'create';
    resetStageForm();
    stageSheetOpen.value = true;
};

const openEditStage = (stage: FunnelStage): void => {
    stageFormMode.value = 'edit';
    stageForm.clearErrors();
    editingStageId.value = stage.id;
    stageForm.name = stage.name;
    stageForm.color = stage.color ?? '#64748b';
    stageForm.type = stage.type;
    stageForm.sort_order = stage.sort_order;
    stageSheetOpen.value = true;
};

const submitStage = (): void => {
    if (!props.activeFunnel) {
        return;
    }

    if (stageFormMode.value === 'create') {
        stageForm.post(storeStage.url({ crmFunnel: props.activeFunnel.id }), {
            preserveScroll: true,
            onSuccess: () => {
                stageSheetOpen.value = false;
                resetStageForm();
            },
        });

        return;
    }

    if (!editingStageId.value) {
        return;
    }

    stageForm.patch(
        updateStage.url({
            crmFunnel: props.activeFunnel.id,
            crmFunnelStage: editingStageId.value,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                stageSheetOpen.value = false;
            },
        },
    );
};

const hydrateCustomFieldValues = (
    definitions: FunnelFieldDefinition[],
    currentValues: Record<string, string | null> = {},
): Record<string, string | null> => {
    return Object.fromEntries(
        definitions.map((field) => [field.key, currentValues[field.key] ?? '']),
    );
};

const resetDealForm = (): void => {
    dealForm.reset();
    dealForm.clearErrors();
    dealForm.crm_funnel_stage_id = props.activeFunnel?.stages[0]?.id ?? '';
    dealForm.title = '';
    dealForm.company_name = '';
    dealForm.contact_name = '';
    dealForm.contact_phone = '';
    dealForm.contact_email = '';
    dealForm.amount = '';
    dealForm.currency = 'USD';
    dealForm.expected_close_at = '';
    dealForm.responsible_user_id = '';
    dealForm.description = '';
    dealForm.custom_fields = hydrateCustomFieldValues(
        props.activeFunnel?.deal_fields ?? [],
    );
    dealForm.sort_order = 0;
    editingDealId.value = null;
};

const openCreateDeal = (stageId?: number): void => {
    if (!props.activeFunnel) {
        return;
    }

    dealFormMode.value = 'create';
    resetDealForm();
    dealForm.crm_funnel_stage_id =
        stageId ?? props.activeFunnel.stages[0]?.id ?? '';
    dealSheetOpen.value = true;
};

const openEditDeal = (deal: FunnelDeal): void => {
    dealFormMode.value = 'edit';
    dealForm.clearErrors();
    editingDealId.value = deal.id;
    dealForm.crm_funnel_stage_id = deal.crm_funnel_stage_id;
    dealForm.title = deal.title;
    dealForm.company_name = deal.company_name ?? '';
    dealForm.contact_name = deal.contact_name ?? '';
    dealForm.contact_phone = deal.contact_phone ?? '';
    dealForm.contact_email = deal.contact_email ?? '';
    dealForm.amount = deal.amount !== null ? String(deal.amount) : '';
    dealForm.currency = deal.currency ?? 'USD';
    dealForm.expected_close_at = deal.expected_close_at ?? '';
    dealForm.responsible_user_id = deal.responsible_user?.id ?? '';
    dealForm.description = deal.description ?? '';
    dealForm.custom_fields = hydrateCustomFieldValues(
        props.activeFunnel?.deal_fields ?? [],
        deal.custom_fields,
    );
    dealForm.sort_order = deal.sort_order;
    dealSheetOpen.value = true;
};

const submitDeal = (): void => {
    if (!props.activeFunnel) {
        return;
    }

    dealForm.transform((data) => ({
        ...data,
        responsible_user_id:
            data.responsible_user_id === ''
                ? null
                : Number(data.responsible_user_id),
        crm_funnel_stage_id: Number(data.crm_funnel_stage_id),
        amount: data.amount.trim() === '' ? null : data.amount,
        expected_close_at:
            data.expected_close_at.trim() === ''
                ? null
                : data.expected_close_at,
        custom_fields: { ...data.custom_fields },
    }));

    if (dealFormMode.value === 'create') {
        dealForm.post(storeDeal.url({ crmFunnel: props.activeFunnel.id }), {
            preserveScroll: true,
            onSuccess: () => {
                dealSheetOpen.value = false;
                resetDealForm();
            },
        });

        return;
    }

    if (!editingDealId.value) {
        return;
    }

    dealForm.patch(
        updateDeal.url({
            crmFunnel: props.activeFunnel.id,
            crmDeal: editingDealId.value,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                dealSheetOpen.value = false;
            },
        },
    );
};

const fieldInputType = (type: FunnelFieldDefinition['type']): string => {
    return type === 'number' ? 'number' : type === 'date' ? 'date' : 'text';
};

const startDraggingDeal = (dealId: number): void => {
    draggedDealId.value = dealId;
};

const moveDraggedDeal = (stageId: number): void => {
    if (!props.activeFunnel || !draggedDealId.value) {
        return;
    }

    router.patch(
        moveDeal.url({
            crmFunnel: props.activeFunnel.id,
            crmDeal: draggedDealId.value,
        }),
        {
            crm_funnel_stage_id: stageId,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                draggedDealId.value = null;
            },
        },
    );
};

const removeFunnel = (): void => {
    if (!props.activeFunnel || !window.confirm(t.value.funnels.delete_funnel)) {
        return;
    }

    router.delete(destroyFunnel.url({ crmFunnel: props.activeFunnel.id }), {
        preserveScroll: true,
    });
};

const removeStage = (stage: FunnelStage): void => {
    if (!props.activeFunnel || !window.confirm(t.value.funnels.delete_stage)) {
        return;
    }

    router.delete(
        destroyStage.url({
            crmFunnel: props.activeFunnel.id,
            crmFunnelStage: stage.id,
        }),
        {
            preserveScroll: true,
        },
    );
};

const removeDeal = (dealId: number): void => {
    if (!props.activeFunnel || !window.confirm(t.value.funnels.delete_deal)) {
        return;
    }

    router.delete(
        destroyDeal.url({
            crmFunnel: props.activeFunnel.id,
            crmDeal: dealId,
        }),
        {
            preserveScroll: true,
            onSuccess: () => {
                dealSheetOpen.value = false;
            },
        },
    );
};
</script>

<template>
    <Head :title="t.funnels.title" />

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="t.funnels.title"
            :description="t.funnels.description"
        />

        <section
            v-if="props.funnels.length === 0"
            class="rounded-3xl border border-dashed border-border bg-card/70 p-8 text-center"
        >
            <div class="mx-auto flex max-w-xl flex-col items-center gap-3">
                <div class="rounded-2xl bg-primary/10 p-3 text-primary">
                    <Workflow class="size-7" />
                </div>
                <h2 class="text-xl font-semibold">
                    {{ t.funnels.no_funnels_title }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ t.funnels.no_funnels_description }}
                </p>
                <Button
                    v-if="canManageFunnels"
                    type="button"
                    class="mt-2"
                    @click="openCreateFunnel"
                >
                    <Plus class="mr-2 size-4" />
                    {{ t.funnels.create_funnel }}
                </Button>
            </div>
        </section>

        <template v-else>
            <div class="grid gap-6 xl:grid-cols-[300px_minmax(0,1fr)]">
                <aside
                    class="space-y-4 rounded-3xl border border-border bg-card/70 p-4"
                >
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="text-sm font-semibold">
                                {{ t.funnels.title }}
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ t.funnels.drag_hint }}
                            </p>
                        </div>

                        <Button
                            v-if="canManageFunnels"
                            type="button"
                            size="sm"
                            @click="openCreateFunnel"
                        >
                            <Plus class="mr-2 size-4" />
                            {{ t.funnels.create_funnel }}
                        </Button>
                    </div>

                    <div class="space-y-3">
                        <Link
                            v-for="funnel in props.funnels"
                            :key="funnel.id"
                            :href="show(funnel.id)"
                            class="block rounded-2xl border p-4 transition hover:border-primary/40 hover:bg-primary/5"
                            :class="
                                props.activeFunnel?.id === funnel.id
                                    ? 'border-primary/40 bg-primary/5'
                                    : 'border-border bg-background/60'
                            "
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="size-2.5 rounded-full"
                                            :style="{
                                                backgroundColor:
                                                    funnel.color ?? '#2563eb',
                                            }"
                                        />
                                        <div class="font-medium">
                                            {{ funnel.name }}
                                        </div>
                                    </div>
                                    <p
                                        v-if="funnel.description"
                                        class="line-clamp-2 text-xs text-muted-foreground"
                                    >
                                        {{ funnel.description }}
                                    </p>
                                </div>

                                <span
                                    class="rounded-full px-2.5 py-1 text-xs"
                                    :class="
                                        funnel.is_active
                                            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    {{ funnel.deals_count }}
                                </span>
                            </div>

                            <div
                                class="mt-4 grid grid-cols-2 gap-2 text-xs text-muted-foreground"
                            >
                                <div>
                                    <div>{{ t.funnels.stages }}</div>
                                    <div
                                        class="mt-1 text-sm font-medium text-foreground"
                                    >
                                        {{ funnel.stages_count }}
                                    </div>
                                </div>
                                <div>
                                    <div>{{ t.funnels.stats_amount_sum }}</div>
                                    <div
                                        class="mt-1 text-sm font-medium text-foreground"
                                    >
                                        {{
                                            formatMoney(
                                                funnel.deals_amount_sum,
                                                null,
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>
                        </Link>
                    </div>
                </aside>

                <div v-if="props.activeFunnel" class="space-y-6">
                    <section
                        class="rounded-3xl border border-border bg-card/70 p-5"
                    >
                        <div
                            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                        >
                            <div class="space-y-3">
                                <div class="flex items-center gap-3">
                                    <span
                                        class="size-3 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                props.activeFunnel.color ??
                                                '#2563eb',
                                        }"
                                    />
                                    <h2
                                        class="text-2xl font-semibold tracking-tight"
                                    >
                                        {{ props.activeFunnel.name }}
                                    </h2>
                                </div>

                                <p
                                    v-if="props.activeFunnel.description"
                                    class="max-w-3xl text-sm text-muted-foreground"
                                >
                                    {{ props.activeFunnel.description }}
                                </p>

                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-for="group in props.activeFunnel
                                            .groups"
                                        :key="group.id"
                                        class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs text-primary"
                                    >
                                        <UsersRound class="size-3.5" />
                                        {{ group.name }}
                                    </span>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Button
                                    v-if="props.can.createDeals"
                                    type="button"
                                    @click="openCreateDeal()"
                                >
                                    <Plus class="mr-2 size-4" />
                                    {{ t.funnels.create_deal }}
                                </Button>

                                <Button
                                    v-if="canManageFunnels"
                                    type="button"
                                    variant="outline"
                                    @click="openEditFunnel"
                                >
                                    <PencilLine class="mr-2 size-4" />
                                    {{ t.funnels.edit_funnel }}
                                </Button>

                                <Button
                                    v-if="canManageFunnels"
                                    type="button"
                                    variant="outline"
                                    @click="removeFunnel"
                                >
                                    <Trash2 class="mr-2 size-4" />
                                    {{ t.funnels.delete_funnel }}
                                </Button>
                            </div>
                        </div>

                        <div
                            class="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-5"
                        >
                            <div
                                v-for="card in statsCards"
                                :key="card.key"
                                class="rounded-2xl border border-border bg-background/80 p-4"
                            >
                                <div
                                    class="text-xs tracking-[0.2em] text-muted-foreground uppercase"
                                >
                                    {{ card.label }}
                                </div>
                                <div class="mt-2 text-2xl font-semibold">
                                    {{ card.value }}
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-border bg-background/80 p-4"
                            >
                                <div
                                    class="text-xs tracking-[0.2em] text-muted-foreground uppercase"
                                >
                                    {{ t.funnels.stats_amount_sum }}
                                </div>
                                <div class="mt-2 text-2xl font-semibold">
                                    {{
                                        formatMoney(
                                            props.activeFunnel.stats.amount_sum,
                                            null,
                                        )
                                    }}
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        class="rounded-3xl border border-border bg-card/70 p-5"
                    >
                        <div
                            class="mb-4 flex items-center justify-between gap-3"
                        >
                            <div>
                                <div class="text-base font-semibold">
                                    {{ t.funnels.title }}
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{ t.funnels.drag_hint }}
                                </p>
                            </div>
                        </div>

                        <div class="overflow-x-auto pb-2">
                            <div class="flex min-h-[30rem] gap-4">
                                <div
                                    v-for="stage in props.activeFunnel.stages"
                                    :key="stage.id"
                                    class="flex w-[320px] shrink-0 flex-col rounded-3xl border border-border bg-background/80"
                                    @dragover.prevent
                                    @drop.prevent="moveDraggedDeal(stage.id)"
                                >
                                    <div class="border-b border-border p-4">
                                        <div
                                            class="flex items-start justify-between gap-3"
                                        >
                                            <div class="space-y-2">
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <span
                                                        class="size-2.5 rounded-full"
                                                        :style="{
                                                            backgroundColor:
                                                                stage.color ??
                                                                '#64748b',
                                                        }"
                                                    />
                                                    <div class="font-medium">
                                                        {{ stage.name }}
                                                    </div>
                                                </div>

                                                <span
                                                    class="inline-flex rounded-full px-2.5 py-1 text-xs"
                                                    :class="
                                                        stageToneClass(
                                                            stage.type,
                                                        )
                                                    "
                                                >
                                                    {{
                                                        props.funnelOptions.stageTypes.find(
                                                            (option) =>
                                                                option.value ===
                                                                stage.type,
                                                        )?.label
                                                    }}
                                                </span>
                                            </div>

                                            <span
                                                class="rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground"
                                            >
                                                {{ stage.deals_count }}
                                            </span>
                                        </div>

                                        <Button
                                            v-if="props.can.createDeals"
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            class="mt-4 w-full justify-start"
                                            @click="openCreateDeal(stage.id)"
                                        >
                                            <Plus class="mr-2 size-4" />
                                            {{ t.funnels.create_deal }}
                                        </Button>
                                    </div>

                                    <div class="flex flex-1 flex-col gap-3 p-3">
                                        <button
                                            v-for="deal in stage.deals"
                                            :key="deal.id"
                                            type="button"
                                            draggable="true"
                                            class="rounded-2xl border border-border bg-card p-4 text-left transition hover:border-primary/40 hover:bg-primary/5"
                                            @dragstart="
                                                startDraggingDeal(deal.id)
                                            "
                                            @click="openEditDeal(deal)"
                                        >
                                            <div
                                                class="flex items-start justify-between gap-3"
                                            >
                                                <div class="space-y-1">
                                                    <div
                                                        class="leading-snug font-medium"
                                                    >
                                                        {{ deal.title }}
                                                    </div>
                                                    <div
                                                        v-if="deal.company_name"
                                                        class="text-sm text-muted-foreground"
                                                    >
                                                        {{ deal.company_name }}
                                                    </div>
                                                </div>

                                                <GripVertical
                                                    class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                                />
                                            </div>

                                            <div
                                                v-if="deal.amount !== null"
                                                class="mt-3 flex items-center gap-2 text-sm font-medium"
                                            >
                                                <CircleDollarSign
                                                    class="size-4 text-muted-foreground"
                                                />
                                                {{
                                                    formatMoney(
                                                        deal.amount,
                                                        deal.currency,
                                                    )
                                                }}
                                            </div>

                                            <div
                                                class="mt-3 space-y-1 text-xs text-muted-foreground"
                                            >
                                                <div>
                                                    {{
                                                        t.funnels
                                                            .responsible_user
                                                    }}:
                                                    {{
                                                        fullName(
                                                            deal.responsible_user,
                                                        )
                                                    }}
                                                </div>
                                                <div
                                                    v-if="
                                                        deal.expected_close_at
                                                    "
                                                >
                                                    {{
                                                        t.funnels
                                                            .expected_close_at
                                                    }}:
                                                    {{
                                                        formatDate(
                                                            deal.expected_close_at,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                        </button>

                                        <div
                                            v-if="stage.deals.length === 0"
                                            class="flex min-h-[140px] items-center justify-center rounded-2xl border border-dashed border-border px-4 text-center text-sm text-muted-foreground"
                                        >
                                            {{ t.funnels.no_stage_deals }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section
                        v-if="canManageFunnels"
                        class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]"
                    >
                        <div
                            class="rounded-3xl border border-border bg-card/70 p-5"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <div class="text-base font-semibold">
                                        {{ t.funnels.stages }}
                                    </div>
                                    <p class="text-sm text-muted-foreground">
                                        {{ t.funnels.stages_description }}
                                    </p>
                                </div>

                                <Button
                                    type="button"
                                    size="sm"
                                    @click="openCreateStage"
                                >
                                    <Plus class="mr-2 size-4" />
                                    {{ t.funnels.create_stage }}
                                </Button>
                            </div>

                            <InputError
                                v-if="page.props.errors?.stage"
                                class="mt-4"
                                :message="page.props.errors.stage"
                            />

                            <div class="mt-5 grid gap-3">
                                <div
                                    v-for="stage in props.activeFunnel.stages"
                                    :key="stage.id"
                                    class="flex flex-col gap-3 rounded-2xl border border-border bg-background/80 p-4 md:flex-row md:items-center md:justify-between"
                                >
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-2">
                                            <span
                                                class="size-2.5 rounded-full"
                                                :style="{
                                                    backgroundColor:
                                                        stage.color ??
                                                        '#64748b',
                                                }"
                                            />
                                            <div class="font-medium">
                                                {{ stage.name }}
                                            </div>
                                            <span
                                                class="rounded-full px-2.5 py-1 text-xs"
                                                :class="
                                                    stageToneClass(stage.type)
                                                "
                                            >
                                                {{
                                                    props.funnelOptions.stageTypes.find(
                                                        (option) =>
                                                            option.value ===
                                                            stage.type,
                                                    )?.label
                                                }}
                                            </span>
                                        </div>
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ t.funnels.stage_sort_order }}:
                                            {{ stage.sort_order }}
                                        </div>
                                    </div>

                                    <div class="flex gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="openEditStage(stage)"
                                        >
                                            <PencilLine class="mr-2 size-4" />
                                            {{ t.funnels.edit_stage }}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            @click="removeStage(stage)"
                                        >
                                            <Trash2 class="mr-2 size-4" />
                                            {{ t.funnels.delete_stage }}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div
                            class="space-y-6 rounded-3xl border border-border bg-card/70 p-5"
                        >
                            <div class="space-y-2">
                                <div
                                    class="flex items-center gap-2 text-base font-semibold"
                                >
                                    <ShieldCheck
                                        class="size-4 text-muted-foreground"
                                    />
                                    {{ t.funnels.access_groups }}
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{ t.funnels.access_groups_description }}
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="group in props.activeFunnel.groups"
                                    :key="group.id"
                                    class="rounded-full bg-primary/10 px-3 py-1 text-xs text-primary"
                                >
                                    {{ group.name }}
                                </span>
                            </div>

                            <div class="space-y-2">
                                <div
                                    class="flex items-center gap-2 text-base font-semibold"
                                >
                                    <LayoutGrid
                                        class="size-4 text-muted-foreground"
                                    />
                                    {{ t.funnels.custom_fields }}
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{ t.funnels.custom_fields_description }}
                                </p>
                            </div>

                            <div
                                class="rounded-2xl border border-border bg-background/80 p-4 text-sm text-muted-foreground"
                            >
                                <div class="font-medium text-foreground">
                                    {{ t.funnels.core_fields }}
                                </div>
                                <p class="mt-2">
                                    {{ t.funnels.core_fields_description }}
                                </p>
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="field in props.activeFunnel
                                        .deal_fields"
                                    :key="field.key"
                                    class="rounded-2xl border border-border bg-background/80 p-4"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div>
                                            <div class="font-medium">
                                                {{ field.label }}
                                            </div>
                                            <div
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ field.key }}
                                            </div>
                                        </div>
                                        <span
                                            class="rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground"
                                        >
                                            {{
                                                props.funnelOptions.fieldTypes.find(
                                                    (option) =>
                                                        option.value ===
                                                        field.type,
                                                )?.label
                                            }}
                                        </span>
                                    </div>
                                </div>

                                <div
                                    v-if="
                                        props.activeFunnel.deal_fields
                                            .length === 0
                                    "
                                    class="rounded-2xl border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground"
                                >
                                    {{ t.funnels.no_custom_fields }}
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </template>
    </div>

    <Sheet
        :open="funnelSheetOpen"
        @update:open="(value) => (funnelSheetOpen = value)"
    >
        <SheetContent class="w-full overflow-y-auto p-6 sm:max-w-2xl">
            <SheetHeader>
                <SheetTitle>
                    {{
                        funnelFormMode === 'create'
                            ? t.funnels.create_funnel
                            : t.funnels.edit_funnel
                    }}
                </SheetTitle>
                <SheetDescription>{{ t.funnels.description }}</SheetDescription>
            </SheetHeader>

            <form class="mt-6 space-y-6" @submit.prevent="submitFunnel">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="funnel-name">{{
                            t.funnels.funnel_name
                        }}</Label>
                        <Input id="funnel-name" v-model="funnelForm.name" />
                        <InputError :message="funnelForm.errors.name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="funnel-slug">{{
                            t.funnels.funnel_slug
                        }}</Label>
                        <Input id="funnel-slug" v-model="funnelForm.slug" />
                        <InputError :message="funnelForm.errors.slug" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_140px]">
                    <div class="space-y-2">
                        <Label for="funnel-description">{{
                            t.funnels.funnel_description
                        }}</Label>
                        <textarea
                            id="funnel-description"
                            v-model="funnelForm.description"
                            :class="textareaClass"
                        />
                        <InputError :message="funnelForm.errors.description" />
                    </div>

                    <div class="space-y-2">
                        <Label for="funnel-color">{{
                            t.funnels.funnel_color
                        }}</Label>
                        <Input
                            id="funnel-color"
                            v-model="funnelForm.color"
                            type="color"
                        />
                        <InputError :message="funnelForm.errors.color" />
                    </div>
                </div>

                <label
                    class="flex items-start gap-3 rounded-2xl border border-border bg-background/70 p-4"
                >
                    <Checkbox
                        :checked="funnelForm.is_active"
                        @update:checked="
                            (value) => (funnelForm.is_active = value === true)
                        "
                    />
                    <div class="space-y-1">
                        <div class="font-medium">
                            {{ t.funnels.funnel_active }}
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ t.funnels.access_groups_description }}
                        </p>
                    </div>
                </label>

                <section class="space-y-4">
                    <div>
                        <div class="font-medium">
                            {{ t.funnels.access_groups }}
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ t.funnels.access_groups_description }}
                        </p>
                    </div>

                    <div class="grid gap-3 md:grid-cols-2">
                        <label
                            v-for="group in props.availableGroups"
                            :key="group.id"
                            class="flex items-start gap-3 rounded-2xl border border-border bg-background/70 p-4"
                        >
                            <Checkbox
                                :checked="
                                    funnelForm.group_ids.includes(group.id)
                                "
                                @update:checked="
                                    (value) => toggleGroup(group.id, value)
                                "
                            />
                            <div class="space-y-1">
                                <div class="font-medium">
                                    {{ group.display_name }}
                                </div>
                                <p
                                    v-if="group.description"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ group.description }}
                                </p>
                            </div>
                        </label>
                    </div>
                </section>

                <section class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <div class="font-medium">
                                {{ t.funnels.custom_fields }}
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ t.funnels.custom_fields_description }}
                            </p>
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="addCustomField"
                        >
                            <Plus class="mr-2 size-4" />
                            {{ t.funnels.add_field }}
                        </Button>
                    </div>

                    <div
                        class="rounded-2xl border border-border bg-background/80 p-4 text-sm text-muted-foreground"
                    >
                        <div class="font-medium text-foreground">
                            {{ t.funnels.core_fields }}
                        </div>
                        <p class="mt-2">
                            {{ t.funnels.core_fields_description }}
                        </p>
                    </div>

                    <div class="space-y-4">
                        <div
                            v-for="(field, index) in funnelForm.deal_fields"
                            :key="`field-${index}`"
                            class="rounded-2xl border border-border bg-background/70 p-4"
                        >
                            <div
                                class="grid gap-4 md:grid-cols-[1fr_1fr_180px_auto]"
                            >
                                <div class="space-y-2">
                                    <Label>{{ t.funnels.field_key }}</Label>
                                    <Input v-model="field.key" />
                                    <InputError
                                        :message="
                                            funnelForm.errors[
                                                `deal_fields.${index}.key`
                                            ]
                                        "
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label>{{ t.funnels.field_label }}</Label>
                                    <Input v-model="field.label" />
                                    <InputError
                                        :message="
                                            funnelForm.errors[
                                                `deal_fields.${index}.label`
                                            ]
                                        "
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label>{{ t.funnels.field_type }}</Label>
                                    <select
                                        v-model="field.type"
                                        :class="selectClass"
                                    >
                                        <option
                                            v-for="option in props.funnelOptions
                                                .fieldTypes"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </div>

                                <div
                                    class="flex items-end justify-between gap-3"
                                >
                                    <label
                                        class="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            :checked="field.is_required"
                                            @update:checked="
                                                (value) =>
                                                    (field.is_required =
                                                        value === true)
                                            "
                                        />
                                        {{ t.funnels.field_required }}
                                    </label>

                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        @click="removeCustomField(index)"
                                    >
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="funnelForm.deal_fields.length === 0"
                            class="rounded-2xl border border-dashed border-border px-4 py-6 text-center text-sm text-muted-foreground"
                        >
                            {{ t.funnels.no_custom_fields }}
                        </div>
                    </div>
                </section>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="funnelForm.processing">
                        {{ t.common.save }}
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>

    <Sheet
        :open="stageSheetOpen"
        @update:open="(value) => (stageSheetOpen = value)"
    >
        <SheetContent class="w-full overflow-y-auto sm:max-w-xl">
            <SheetHeader>
                <SheetTitle>
                    {{
                        stageFormMode === 'create'
                            ? t.funnels.create_stage
                            : t.funnels.edit_stage
                    }}
                </SheetTitle>
                <SheetDescription>{{
                    t.funnels.stages_description
                }}</SheetDescription>
            </SheetHeader>

            <form class="mt-6 space-y-4" @submit.prevent="submitStage">
                <div class="space-y-2">
                    <Label for="stage-name">{{ t.funnels.stage_name }}</Label>
                    <Input id="stage-name" v-model="stageForm.name" />
                    <InputError :message="stageForm.errors.name" />
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="stage-color">{{
                            t.funnels.stage_color
                        }}</Label>
                        <Input
                            id="stage-color"
                            v-model="stageForm.color"
                            type="color"
                        />
                    </div>

                    <div class="space-y-2">
                        <Label for="stage-type">{{
                            t.funnels.stage_type
                        }}</Label>
                        <select
                            id="stage-type"
                            v-model="stageForm.type"
                            :class="selectClass"
                        >
                            <option
                                v-for="option in props.funnelOptions.stageTypes"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-2">
                        <Label for="stage-order">{{
                            t.funnels.stage_sort_order
                        }}</Label>
                        <Input
                            id="stage-order"
                            v-model="stageForm.sort_order"
                            type="number"
                            min="0"
                        />
                    </div>
                </div>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="stageForm.processing">
                        {{ t.common.save }}
                    </Button>
                </div>
            </form>
        </SheetContent>
    </Sheet>

    <Sheet
        :open="dealSheetOpen"
        @update:open="(value) => (dealSheetOpen = value)"
    >
        <SheetContent class="w-full overflow-y-auto sm:max-w-2xl">
            <SheetHeader>
                <SheetTitle>
                    {{
                        dealFormMode === 'create'
                            ? t.funnels.create_deal
                            : t.funnels.edit_deal
                    }}
                </SheetTitle>
                <SheetDescription>{{ t.funnels.description }}</SheetDescription>
            </SheetHeader>

            <form class="mt-6 space-y-5" @submit.prevent="submitDeal">
                <div class="grid gap-4 md:grid-cols-[minmax(0,1fr)_200px]">
                    <div class="space-y-2">
                        <Label for="deal-title">{{
                            t.funnels.deal_title
                        }}</Label>
                        <Input id="deal-title" v-model="dealForm.title" />
                        <InputError :message="dealForm.errors.title" />
                    </div>

                    <div class="space-y-2">
                        <Label for="deal-stage">{{
                            t.funnels.stage_name
                        }}</Label>
                        <select
                            id="deal-stage"
                            v-model="dealForm.crm_funnel_stage_id"
                            :class="selectClass"
                        >
                            <option
                                v-for="stage in props.activeFunnel?.stages ??
                                []"
                                :key="stage.id"
                                :value="stage.id"
                            >
                                {{ stage.name }}
                            </option>
                        </select>
                        <InputError
                            :message="dealForm.errors.crm_funnel_stage_id"
                        />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="deal-company">{{
                            t.funnels.company_name
                        }}</Label>
                        <Input
                            id="deal-company"
                            v-model="dealForm.company_name"
                        />
                        <InputError :message="dealForm.errors.company_name" />
                    </div>

                    <div class="space-y-2">
                        <Label for="deal-contact-name">{{
                            t.funnels.contact_name
                        }}</Label>
                        <Input
                            id="deal-contact-name"
                            v-model="dealForm.contact_name"
                        />
                        <InputError :message="dealForm.errors.contact_name" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="space-y-2">
                        <Label for="deal-contact-phone">{{
                            t.funnels.contact_phone
                        }}</Label>
                        <Input
                            id="deal-contact-phone"
                            v-model="dealForm.contact_phone"
                        />
                        <InputError :message="dealForm.errors.contact_phone" />
                    </div>

                    <div class="space-y-2">
                        <Label for="deal-contact-email">{{
                            t.funnels.contact_email
                        }}</Label>
                        <Input
                            id="deal-contact-email"
                            v-model="dealForm.contact_email"
                            type="email"
                        />
                        <InputError :message="dealForm.errors.contact_email" />
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-3">
                    <div class="space-y-2">
                        <Label for="deal-amount">{{ t.funnels.amount }}</Label>
                        <Input
                            id="deal-amount"
                            v-model="dealForm.amount"
                            type="number"
                            step="0.01"
                            min="0"
                        />
                        <InputError :message="dealForm.errors.amount" />
                    </div>

                    <div class="space-y-2">
                        <Label for="deal-currency">{{
                            t.funnels.currency
                        }}</Label>
                        <Input
                            id="deal-currency"
                            v-model="dealForm.currency"
                            maxlength="3"
                        />
                        <InputError :message="dealForm.errors.currency" />
                    </div>

                    <div class="space-y-2">
                        <Label for="deal-close">{{
                            t.funnels.expected_close_at
                        }}</Label>
                        <Input
                            id="deal-close"
                            v-model="dealForm.expected_close_at"
                            type="date"
                        />
                        <InputError
                            :message="dealForm.errors.expected_close_at"
                        />
                    </div>
                </div>

                <div class="space-y-2">
                    <Label for="deal-responsible">{{
                        t.funnels.responsible_user
                    }}</Label>
                    <select
                        id="deal-responsible"
                        v-model="dealForm.responsible_user_id"
                        :class="selectClass"
                    >
                        <option value="">
                            {{ t.funnels.no_responsible_user }}
                        </option>
                        <option
                            v-for="user in props.availableUsers"
                            :key="user.id"
                            :value="user.id"
                        >
                            {{ fullName(user) }} · {{ user.email }}
                        </option>
                    </select>
                    <InputError
                        :message="dealForm.errors.responsible_user_id"
                    />
                </div>

                <section
                    v-if="props.activeFunnel?.deal_fields.length"
                    class="space-y-4 rounded-2xl border border-border bg-background/70 p-4"
                >
                    <div>
                        <div class="font-medium">
                            {{ t.funnels.custom_fields }}
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ t.funnels.custom_fields_description }}
                        </p>
                    </div>

                    <div class="grid gap-4">
                        <div
                            v-for="field in props.activeFunnel.deal_fields"
                            :key="field.key"
                            class="space-y-2"
                        >
                            <Label :for="`custom-${field.key}`">
                                {{ field.label }}
                            </Label>

                            <textarea
                                v-if="field.type === 'textarea'"
                                :id="`custom-${field.key}`"
                                v-model="dealForm.custom_fields[field.key]"
                                :class="textareaClass"
                            />

                            <Input
                                v-else
                                :id="`custom-${field.key}`"
                                v-model="dealForm.custom_fields[field.key]"
                                :type="fieldInputType(field.type)"
                            />

                            <InputError
                                :message="
                                    dealForm.errors[
                                        `custom_fields.${field.key}`
                                    ]
                                "
                            />
                        </div>
                    </div>
                </section>

                <div class="space-y-2">
                    <Label for="deal-description">{{
                        t.funnels.deal_description
                    }}</Label>
                    <textarea
                        id="deal-description"
                        v-model="dealForm.description"
                        :class="textareaClass"
                    />
                    <InputError :message="dealForm.errors.description" />
                </div>

                <div class="flex items-center justify-between gap-3">
                    <Button
                        v-if="dealFormMode === 'edit'"
                        type="button"
                        variant="outline"
                        @click="editingDealId && removeDeal(editingDealId)"
                    >
                        <Trash2 class="mr-2 size-4" />
                        {{ t.funnels.delete_deal }}
                    </Button>

                    <div class="ml-auto">
                        <Button type="submit" :disabled="dealForm.processing">
                            {{ t.common.save }}
                        </Button>
                    </div>
                </div>
            </form>
        </SheetContent>
    </Sheet>
</template>

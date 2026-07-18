<script setup lang="ts">
import {
    ArrowDown,
    ArrowUp,
    Copy,
    Eye,
    EyeOff,
    GripVertical,
    LayoutDashboard,
    Plus,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useLanguage } from '@/composables/useLanguage';
import {
    cloneDashboardDefinition,
    cloneDashboardWidgets,
} from '@/lib/dashboardConfiguration';
import type {
    DashboardChartType,
    DashboardConfiguration,
    DashboardDefinition,
    DashboardWidget,
    DashboardWidgetId,
    DashboardWidgetSize,
} from '@/types/dashboard';

const props = defineProps<{
    open: boolean;
    processing: boolean;
    validationError?: string;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    save: [];
    reset: [];
}>();

const configuration = defineModel<DashboardConfiguration>({ required: true });
const { t } = useLanguage();
const localNotice = ref<string | null>(null);

const widgetChartTypes: Record<DashboardWidgetId, DashboardChartType[]> = {
    highlights: ['cards'],
    metrics: ['cards'],
    activity: ['area', 'line'],
    donuts: ['donut', 'progress'],
    bars: ['bars', 'progress'],
    radar: ['radar', 'progress'],
};

const defaultWidgets: DashboardWidget[] = [
    { id: 'highlights', visible: true, size: 'full', chartType: 'cards' },
    { id: 'metrics', visible: true, size: 'full', chartType: 'cards' },
    { id: 'activity', visible: true, size: 'full', chartType: 'area' },
    { id: 'donuts', visible: true, size: 'full', chartType: 'donut' },
    { id: 'bars', visible: true, size: 'wide', chartType: 'bars' },
    { id: 'radar', visible: true, size: 'standard', chartType: 'radar' },
];

const activeDashboard = computed(() => {
    return (
        configuration.value.dashboards.find(
            (dashboard) =>
                dashboard.id === configuration.value.activeDashboardId,
        ) ?? configuration.value.dashboards[0]
    );
});

const activeName = computed({
    get: () => activeDashboard.value?.name ?? '',
    set: (name: string) => updateActiveDashboard({ name }),
});

const activePeriod = computed({
    get: () => activeDashboard.value?.period ?? 7,
    set: (period: 7 | 14 | 30) => updateActiveDashboard({ period }),
});

const activeDensity = computed({
    get: () => activeDashboard.value?.density ?? 'comfortable',
    set: (density: 'comfortable' | 'compact') =>
        updateActiveDashboard({ density }),
});

const visibleWidgetCount = computed(() => {
    return (
        activeDashboard.value?.widgets.filter((widget) => widget.visible)
            .length ?? 0
    );
});

const canAddDashboard = computed(
    () => configuration.value.dashboards.length < 6,
);

const replaceDashboard = (nextDashboard: DashboardDefinition): void => {
    configuration.value = {
        ...configuration.value,
        dashboards: configuration.value.dashboards.map((dashboard) =>
            dashboard.id === nextDashboard.id ? nextDashboard : dashboard,
        ),
    };
};

const updateActiveDashboard = (
    changes: Partial<Omit<DashboardDefinition, 'id'>>,
): void => {
    if (!activeDashboard.value) {
        return;
    }

    replaceDashboard({ ...activeDashboard.value, ...changes });
    localNotice.value = null;
};

const selectDashboard = (id: string): void => {
    configuration.value = {
        ...configuration.value,
        activeDashboardId: id,
    };
    localNotice.value = null;
};

const newDashboardId = (): string => {
    return `dashboard_${Date.now().toString(36)}`;
};

const addDashboard = (): void => {
    if (!canAddDashboard.value) {
        localNotice.value = t.value.dashboard.customizer.limit;

        return;
    }

    const dashboard: DashboardDefinition = {
        id: newDashboardId(),
        name: t.value.dashboard.customizer.new_dashboard,
        period: 7,
        density: 'comfortable',
        widgets: cloneDashboardWidgets(defaultWidgets),
    };

    configuration.value = {
        ...configuration.value,
        activeDashboardId: dashboard.id,
        dashboards: [...configuration.value.dashboards, dashboard],
    };
    localNotice.value = null;
};

const duplicateDashboard = (): void => {
    if (!activeDashboard.value || !canAddDashboard.value) {
        localNotice.value = t.value.dashboard.customizer.limit;

        return;
    }

    const dashboard: DashboardDefinition = {
        ...cloneDashboardDefinition(activeDashboard.value),
        id: newDashboardId(),
        name: `${activeDashboard.value.name} — ${t.value.dashboard.customizer.copy_suffix}`,
    };

    configuration.value = {
        ...configuration.value,
        activeDashboardId: dashboard.id,
        dashboards: [...configuration.value.dashboards, dashboard],
    };
    localNotice.value = null;
};

const deleteDashboard = (): void => {
    if (!activeDashboard.value || configuration.value.dashboards.length === 1) {
        return;
    }

    const dashboards = configuration.value.dashboards.filter(
        (dashboard) => dashboard.id !== activeDashboard.value.id,
    );

    configuration.value = {
        ...configuration.value,
        activeDashboardId: dashboards[0].id,
        dashboards,
    };
    localNotice.value = null;
};

const updateWidget = (
    widgetId: DashboardWidgetId,
    changes: Partial<Omit<DashboardWidget, 'id'>>,
): void => {
    if (!activeDashboard.value) {
        return;
    }

    updateActiveDashboard({
        widgets: activeDashboard.value.widgets.map((widget) =>
            widget.id === widgetId ? { ...widget, ...changes } : widget,
        ),
    });
};

const toggleWidget = (widget: DashboardWidget): void => {
    if (widget.visible && visibleWidgetCount.value === 1) {
        localNotice.value = t.value.dashboard.customizer.last_visible;

        return;
    }

    updateWidget(widget.id, { visible: !widget.visible });
};

const moveWidget = (index: number, direction: -1 | 1): void => {
    if (!activeDashboard.value) {
        return;
    }

    const targetIndex = index + direction;

    if (
        targetIndex < 0 ||
        targetIndex >= activeDashboard.value.widgets.length
    ) {
        return;
    }

    const widgets = [...activeDashboard.value.widgets];
    [widgets[index], widgets[targetIndex]] = [
        widgets[targetIndex],
        widgets[index],
    ];
    updateActiveDashboard({ widgets });
};

const updateWidgetSize = (widgetId: DashboardWidgetId, event: Event): void => {
    const size = (event.target as HTMLSelectElement)
        .value as DashboardWidgetSize;
    updateWidget(widgetId, { size });
};

const updateWidgetChartType = (
    widgetId: DashboardWidgetId,
    event: Event,
): void => {
    const chartType = (event.target as HTMLSelectElement)
        .value as DashboardChartType;
    updateWidget(widgetId, { chartType });
};

const resetChanges = (): void => {
    localNotice.value = null;
    emit('reset');
};
</script>

<template>
    <Sheet :open="props.open" @update:open="emit('update:open', $event)">
        <SheetContent
            class="w-full gap-0 overflow-hidden border-border/80 bg-background/98 p-0 sm:max-w-2xl"
        >
            <SheetHeader class="border-b border-border/70 px-6 py-5 text-left">
                <div class="flex items-center gap-3 pr-8">
                    <span
                        class="flex size-11 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-sm"
                    >
                        <LayoutDashboard class="size-5" />
                    </span>
                    <div>
                        <SheetTitle class="text-xl">
                            {{ t.dashboard.customizer.title }}
                        </SheetTitle>
                        <SheetDescription class="mt-1 max-w-xl leading-5">
                            {{ t.dashboard.customizer.description }}
                        </SheetDescription>
                    </div>
                </div>
            </SheetHeader>

            <div class="flex-1 space-y-7 overflow-y-auto px-6 py-6">
                <section class="space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <Label class="text-sm font-semibold">
                            {{ t.dashboard.customizer.dashboards }}
                        </Label>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            class="gap-2 rounded-xl"
                            :disabled="!canAddDashboard"
                            @click="addDashboard"
                        >
                            <Plus class="size-4" />
                            {{ t.dashboard.customizer.add }}
                        </Button>
                    </div>

                    <div class="flex gap-2 overflow-x-auto pb-1">
                        <button
                            v-for="dashboard in configuration.dashboards"
                            :key="dashboard.id"
                            type="button"
                            class="shrink-0 rounded-xl border px-3.5 py-2 text-sm font-medium transition"
                            :class="
                                dashboard.id === configuration.activeDashboardId
                                    ? 'border-foreground bg-foreground text-background shadow-sm'
                                    : 'border-border/80 bg-card text-muted-foreground hover:border-foreground/30 hover:text-foreground'
                            "
                            @click="selectDashboard(dashboard.id)"
                        >
                            {{ dashboard.name }}
                        </button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-[1fr_auto]">
                        <div class="space-y-2">
                            <Label for="dashboard-name">
                                {{ t.dashboard.customizer.dashboard_name }}
                            </Label>
                            <Input
                                id="dashboard-name"
                                v-model="activeName"
                                maxlength="60"
                            />
                        </div>

                        <div class="flex items-end gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="rounded-xl"
                                :title="t.dashboard.customizer.duplicate"
                                :disabled="!canAddDashboard"
                                @click="duplicateDashboard"
                            >
                                <Copy class="size-4" />
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                class="rounded-xl text-destructive hover:text-destructive"
                                :title="t.dashboard.customizer.delete"
                                :disabled="
                                    configuration.dashboards.length === 1
                                "
                                @click="deleteDashboard"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </section>

                <section
                    class="grid gap-5 rounded-2xl border border-border/70 bg-muted/25 p-4 sm:grid-cols-2"
                >
                    <div class="space-y-3">
                        <div>
                            <Label class="font-semibold">
                                {{ t.dashboard.customizer.period }}
                            </Label>
                            <p
                                class="mt-1 text-xs leading-5 text-muted-foreground"
                            >
                                {{ t.dashboard.customizer.period_hint }}
                            </p>
                        </div>
                        <div class="grid grid-cols-3 gap-2">
                            <button
                                v-for="period in [7, 14, 30] as const"
                                :key="period"
                                type="button"
                                class="rounded-xl border px-3 py-2 text-sm font-semibold transition"
                                :class="
                                    activePeriod === period
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border bg-background hover:border-foreground/25'
                                "
                                @click="activePeriod = period"
                            >
                                {{ period }}
                            </button>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <Label class="font-semibold">
                            {{ t.dashboard.customizer.density }}
                        </Label>
                        <div class="grid gap-2">
                            <button
                                v-for="density in [
                                    'comfortable',
                                    'compact',
                                ] as const"
                                :key="density"
                                type="button"
                                class="rounded-xl border px-3 py-2 text-left text-sm font-medium transition"
                                :class="
                                    activeDensity === density
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border bg-background hover:border-foreground/25'
                                "
                                @click="activeDensity = density"
                            >
                                {{ t.dashboard.customizer[density] }}
                            </button>
                        </div>
                    </div>
                </section>

                <section class="space-y-3">
                    <div>
                        <Label class="text-sm font-semibold">
                            {{ t.dashboard.customizer.widgets }}
                        </Label>
                        <p class="mt-1 text-xs leading-5 text-muted-foreground">
                            {{ t.dashboard.customizer.widgets_hint }}
                        </p>
                    </div>

                    <div class="space-y-3">
                        <article
                            v-for="(
                                widget, index
                            ) in activeDashboard?.widgets ?? []"
                            :key="widget.id"
                            class="rounded-2xl border border-border/70 bg-card p-4 shadow-xs transition"
                            :class="{ 'opacity-65': !widget.visible }"
                        >
                            <div class="flex items-start gap-3">
                                <GripVertical
                                    class="mt-1 size-4 shrink-0 text-muted-foreground/60"
                                />
                                <div class="min-w-0 flex-1">
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div>
                                            <h3 class="text-sm font-semibold">
                                                {{
                                                    t.dashboard.widgets[
                                                        widget.id
                                                    ].title
                                                }}
                                            </h3>
                                            <p
                                                class="mt-1 text-xs leading-5 text-muted-foreground"
                                            >
                                                {{
                                                    t.dashboard.widgets[
                                                        widget.id
                                                    ].description
                                                }}
                                            </p>
                                        </div>

                                        <button
                                            type="button"
                                            class="inline-flex shrink-0 items-center gap-1.5 rounded-lg border border-border px-2.5 py-1.5 text-xs font-medium transition hover:bg-muted"
                                            @click="toggleWidget(widget)"
                                        >
                                            <Eye
                                                v-if="widget.visible"
                                                class="size-3.5"
                                            />
                                            <EyeOff v-else class="size-3.5" />
                                            {{
                                                widget.visible
                                                    ? t.dashboard.customizer
                                                          .visible
                                                    : t.dashboard.customizer
                                                          .hidden
                                            }}
                                        </button>
                                    </div>

                                    <div
                                        class="mt-4 grid gap-3 sm:grid-cols-[auto_1fr_1fr]"
                                    >
                                        <div class="flex gap-1">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                class="size-9 rounded-lg"
                                                :title="
                                                    t.dashboard.customizer
                                                        .move_up
                                                "
                                                :disabled="index === 0"
                                                @click="moveWidget(index, -1)"
                                            >
                                                <ArrowUp class="size-3.5" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="icon"
                                                class="size-9 rounded-lg"
                                                :title="
                                                    t.dashboard.customizer
                                                        .move_down
                                                "
                                                :disabled="
                                                    index ===
                                                    (activeDashboard?.widgets
                                                        .length ?? 0) -
                                                        1
                                                "
                                                @click="moveWidget(index, 1)"
                                            >
                                                <ArrowDown class="size-3.5" />
                                            </Button>
                                        </div>

                                        <label
                                            class="space-y-1.5 text-xs font-medium text-muted-foreground"
                                        >
                                            <span>{{
                                                t.dashboard.customizer.size
                                            }}</span>
                                            <select
                                                :value="widget.size"
                                                class="h-9 w-full rounded-lg border border-input bg-background px-2.5 text-sm text-foreground outline-none focus:border-ring focus:ring-2 focus:ring-ring/20"
                                                @change="
                                                    updateWidgetSize(
                                                        widget.id,
                                                        $event,
                                                    )
                                                "
                                            >
                                                <option
                                                    v-for="size in [
                                                        'standard',
                                                        'wide',
                                                        'full',
                                                    ] as const"
                                                    :key="size"
                                                    :value="size"
                                                >
                                                    {{
                                                        t.dashboard.sizes[size]
                                                    }}
                                                </option>
                                            </select>
                                        </label>

                                        <label
                                            class="space-y-1.5 text-xs font-medium text-muted-foreground"
                                        >
                                            <span>{{
                                                t.dashboard.customizer
                                                    .chart_type
                                            }}</span>
                                            <select
                                                :value="widget.chartType"
                                                class="h-9 w-full rounded-lg border border-input bg-background px-2.5 text-sm text-foreground outline-none focus:border-ring focus:ring-2 focus:ring-ring/20 disabled:opacity-60"
                                                :disabled="
                                                    widgetChartTypes[widget.id]
                                                        .length === 1
                                                "
                                                @change="
                                                    updateWidgetChartType(
                                                        widget.id,
                                                        $event,
                                                    )
                                                "
                                            >
                                                <option
                                                    v-for="chartType in widgetChartTypes[
                                                        widget.id
                                                    ]"
                                                    :key="chartType"
                                                    :value="chartType"
                                                >
                                                    {{
                                                        t.dashboard.chart_types[
                                                            chartType
                                                        ]
                                                    }}
                                                </option>
                                            </select>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>
                </section>

                <p
                    v-if="localNotice || props.validationError"
                    class="rounded-xl border border-destructive/25 bg-destructive/8 px-3 py-2 text-sm text-destructive"
                >
                    {{ localNotice || props.validationError }}
                </p>
            </div>

            <SheetFooter
                class="border-t border-border/70 bg-background/95 px-6 py-4 sm:justify-between"
            >
                <Button
                    type="button"
                    variant="ghost"
                    :disabled="props.processing"
                    @click="resetChanges"
                >
                    {{ t.dashboard.customizer.reset }}
                </Button>
                <Button
                    type="button"
                    class="min-w-44 rounded-xl"
                    :disabled="props.processing"
                    @click="emit('save')"
                >
                    {{
                        props.processing
                            ? t.dashboard.customizer.saving
                            : t.dashboard.customizer.save
                    }}
                </Button>
            </SheetFooter>
        </SheetContent>
    </Sheet>
</template>

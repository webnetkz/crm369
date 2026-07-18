<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    Activity,
    BadgeDollarSign,
    ClipboardList,
    ContactRound,
    FolderKanban,
    LayoutDashboard,
    LayoutTemplate,
    MessageSquareText,
    Plus,
    Settings2,
    Sparkles,
    UsersRound,
} from '@lucide/vue';
import { computed, ref, watch, watchEffect } from 'vue';
import { update } from '@/actions/App/Http/Controllers/DashboardConfigurationController';
import DashboardActivityChart from '@/components/dashboard/DashboardActivityChart.vue';
import DashboardCustomizer from '@/components/dashboard/DashboardCustomizer.vue';
import DashboardDonutCharts from '@/components/dashboard/DashboardDonutCharts.vue';
import DashboardModuleChart from '@/components/dashboard/DashboardModuleChart.vue';
import DashboardRadarChart from '@/components/dashboard/DashboardRadarChart.vue';
import { Button } from '@/components/ui/button';
import { useLanguage } from '@/composables/useLanguage';
import { cloneDashboardConfiguration } from '@/lib/dashboardConfiguration';
import { dashboard } from '@/routes';
import type {
    DashboardConfiguration,
    DashboardWidget,
    DashboardWidgetSize,
    DashboardStats,
} from '@/types/dashboard';

const props = defineProps<{
    dashboardStats: DashboardStats;
    dashboardConfiguration: DashboardConfiguration;
}>();

const { t } = useLanguage();
const settingsOpen = ref(false);

const form = useForm<{ configuration: DashboardConfiguration }>({
    configuration: cloneDashboardConfiguration(props.dashboardConfiguration),
});

const iconMap = {
    users: UsersRound,
    folder: FolderKanban,
    clipboard: ClipboardList,
    messages: MessageSquareText,
    layout: LayoutTemplate,
    contact: ContactRound,
    currency: BadgeDollarSign,
} as const;

const widgetSizeClasses: Record<DashboardWidgetSize, string> = {
    standard: 'col-span-12 lg:col-span-6 xl:col-span-4',
    wide: 'col-span-12 xl:col-span-8',
    full: 'col-span-12',
};

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.common.dashboard,
                href: dashboard(),
            },
        ],
    });
});

watch(
    () => props.dashboardConfiguration,
    (configuration) => {
        const defaults = cloneDashboardConfiguration(configuration);
        form.defaults({ configuration: defaults });
        form.configuration = cloneDashboardConfiguration(defaults);
    },
    { deep: true },
);

const activeDashboard = computed(() => {
    return (
        form.configuration.dashboards.find(
            (item) => item.id === form.configuration.activeDashboardId,
        ) ?? form.configuration.dashboards[0]
    );
});

const visibleWidgets = computed(() => {
    return (
        activeDashboard.value?.widgets.filter((widget) => widget.visible) ?? []
    );
});

const isCompact = computed(() => activeDashboard.value?.density === 'compact');

const validationError = computed(() => {
    const firstError = Object.values(form.errors)[0];

    return typeof firstError === 'string' ? firstError : undefined;
});

const cardIcon = (icon: string) => {
    return iconMap[icon as keyof typeof iconMap] ?? Activity;
};

const chartColor = (index: number): string => {
    return `var(--color-chart-${(index % 5) + 1})`;
};

const formatPeriod = (days: number): string => {
    return t.value.dashboard.period_badge.replace(':days', String(days));
};

const widgetClass = (widget: DashboardWidget): string => {
    return widgetSizeClasses[widget.size];
};

const submitConfiguration = (closeAfterSuccess: boolean): void => {
    form.submit(update(), {
        preserveScroll: true,
        onSuccess: () => {
            if (closeAfterSuccess) {
                settingsOpen.value = false;
            }
        },
    });
};

const selectDashboard = (id: string): void => {
    if (id === form.configuration.activeDashboardId || form.processing) {
        return;
    }

    form.configuration = {
        ...form.configuration,
        activeDashboardId: id,
    };
    submitConfiguration(false);
};

const openSettings = (): void => {
    form.configuration = cloneDashboardConfiguration(
        props.dashboardConfiguration,
    );
    form.clearErrors();
    settingsOpen.value = true;
};

const resetConfiguration = (): void => {
    form.reset();
    form.clearErrors();
};

const updateSettingsOpen = (open: boolean): void => {
    if (!open && !form.processing) {
        resetConfiguration();
    }

    settingsOpen.value = open;
};
</script>

<template>
    <Head :title="t.common.dashboard" />

    <div class="flex h-full flex-1 flex-col gap-5 overflow-x-hidden pb-6">
        <section
            class="relative isolate overflow-hidden rounded-[2rem] border border-white/30 bg-[linear-gradient(125deg,_#071a2b_0%,_#0d3b45_48%,_#0d5d59_100%)] px-5 py-6 text-white shadow-[0_28px_80px_-40px_rgba(8,47,73,0.8)] md:px-8 md:py-8 dark:border-white/10"
        >
            <div
                class="pointer-events-none absolute -top-32 -right-20 -z-10 size-96 rounded-full bg-cyan-300/15 blur-3xl"
            />
            <div
                class="pointer-events-none absolute -bottom-44 left-1/3 -z-10 size-96 rounded-full bg-emerald-300/15 blur-3xl"
            />
            <div
                class="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_15%_15%,_rgba(255,255,255,0.16),_transparent_24%)]"
            />

            <div
                class="flex flex-col gap-7 xl:flex-row xl:items-end xl:justify-between"
            >
                <div class="max-w-3xl space-y-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1.5 text-[11px] font-semibold tracking-[0.16em] text-cyan-50 uppercase backdrop-blur"
                        >
                            <span class="relative flex size-2">
                                <span
                                    class="absolute inline-flex size-full animate-ping rounded-full bg-emerald-300 opacity-75"
                                />
                                <span
                                    class="relative inline-flex size-2 rounded-full bg-emerald-300"
                                />
                            </span>
                            {{ t.dashboard.live_badge }}
                        </span>
                        <span
                            class="rounded-full border border-white/15 bg-white/8 px-3 py-1.5 text-[11px] font-semibold tracking-[0.12em] text-white/75 uppercase"
                        >
                            {{ formatPeriod(activeDashboard?.period ?? 7) }}
                        </span>
                    </div>

                    <div class="space-y-3">
                        <p
                            class="text-xs font-semibold tracking-[0.24em] text-cyan-100/75 uppercase"
                        >
                            {{ dashboardStats.eyebrow }}
                        </p>
                        <h1
                            class="text-3xl font-semibold tracking-[-0.035em] md:text-5xl"
                        >
                            {{ activeDashboard?.name ?? t.common.dashboard }}
                        </h1>
                        <p
                            class="max-w-2xl text-sm leading-6 text-cyan-50/70 md:text-base"
                        >
                            {{ dashboardStats.subtitle }}
                        </p>
                    </div>

                    <Button
                        type="button"
                        variant="secondary"
                        class="gap-2 rounded-xl border border-white/15 bg-white/12 text-white shadow-none hover:bg-white/20 hover:text-white"
                        @click="openSettings"
                    >
                        <Settings2 class="size-4" />
                        {{ t.dashboard.customize }}
                    </Button>
                </div>

                <div
                    v-if="
                        activeDashboard?.widgets.find(
                            (widget) => widget.id === 'highlights',
                        )?.visible
                    "
                    class="grid w-full gap-2.5 sm:grid-cols-2 xl:max-w-2xl"
                >
                    <article
                        v-for="(highlight, index) in dashboardStats.highlights"
                        :key="highlight.label"
                        class="group rounded-2xl border border-white/12 bg-white/8 p-4 backdrop-blur transition hover:bg-white/12"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p
                                    class="text-[10px] font-semibold tracking-[0.16em] text-cyan-50/60 uppercase"
                                >
                                    {{ highlight.label }}
                                </p>
                                <p
                                    class="mt-2 text-2xl font-semibold tracking-tight"
                                >
                                    {{ highlight.value }}
                                </p>
                            </div>
                            <span
                                class="mt-1 size-2.5 rounded-full"
                                :style="{ backgroundColor: chartColor(index) }"
                            />
                        </div>
                        <p class="mt-1 text-xs leading-5 text-cyan-50/55">
                            {{ highlight.helper }}
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <section
            class="flex flex-col gap-3 rounded-2xl border border-border/70 bg-card/85 p-2.5 shadow-sm backdrop-blur md:flex-row md:items-center md:justify-between"
        >
            <div class="flex min-w-0 items-center gap-2 overflow-x-auto">
                <span
                    class="hidden shrink-0 items-center gap-2 px-2 text-xs font-semibold tracking-[0.12em] text-muted-foreground uppercase lg:inline-flex"
                >
                    <LayoutDashboard class="size-4" />
                    {{ t.dashboard.dashboards_label }}
                </span>
                <button
                    v-for="item in form.configuration.dashboards"
                    :key="item.id"
                    type="button"
                    class="shrink-0 rounded-xl px-4 py-2 text-sm font-medium transition"
                    :class="
                        item.id === form.configuration.activeDashboardId
                            ? 'bg-foreground text-background shadow-sm'
                            : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                    "
                    :disabled="form.processing"
                    @click="selectDashboard(item.id)"
                >
                    {{ item.name }}
                </button>
            </div>

            <Button
                type="button"
                variant="ghost"
                size="sm"
                class="shrink-0 gap-2 rounded-xl"
                @click="openSettings"
            >
                <Plus class="size-4" />
                {{ t.dashboard.customizer.add }}
            </Button>
        </section>

        <section
            v-if="visibleWidgets.length > 0"
            class="grid grid-cols-12 gap-4"
        >
            <template v-for="widget in visibleWidgets" :key="widget.id">
                <div
                    v-if="widget.id === 'highlights'"
                    class="hidden"
                    aria-hidden="true"
                />

                <div
                    v-else-if="widget.id === 'metrics'"
                    :class="widgetClass(widget)"
                    data-dashboard-widget="metrics"
                >
                    <div
                        class="grid h-full gap-3 sm:grid-cols-2 xl:grid-cols-4"
                    >
                        <article
                            v-for="(card, index) in dashboardStats.cards"
                            :key="card.title"
                            class="group relative overflow-hidden rounded-[1.5rem] border border-border/70 bg-card/92 shadow-sm transition duration-300 hover:-translate-y-0.5 hover:shadow-md"
                            :class="isCompact ? 'p-4' : 'p-5'"
                        >
                            <div
                                class="absolute inset-x-0 top-0 h-1"
                                :style="{ backgroundColor: chartColor(index) }"
                            />
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p
                                        class="text-sm font-medium text-muted-foreground"
                                    >
                                        {{ card.title }}
                                    </p>
                                    <p
                                        class="mt-2 text-3xl font-semibold tracking-[-0.04em]"
                                    >
                                        {{ card.value }}
                                    </p>
                                </div>
                                <span
                                    class="flex size-10 items-center justify-center rounded-xl"
                                    :style="{
                                        color: chartColor(index),
                                        backgroundColor: `color-mix(in srgb, ${chartColor(index)} 13%, transparent)`,
                                    }"
                                >
                                    <component
                                        :is="cardIcon(card.icon)"
                                        class="size-5"
                                    />
                                </span>
                            </div>
                            <p
                                class="mt-4 text-xs leading-5 text-muted-foreground"
                            >
                                {{ card.helper }}
                            </p>
                        </article>
                    </div>
                </div>

                <div
                    v-else-if="widget.id === 'activity'"
                    :class="widgetClass(widget)"
                    data-dashboard-widget="activity"
                >
                    <DashboardActivityChart
                        :activity="dashboardStats.activity"
                        :chart-type="
                            widget.chartType === 'line' ? 'line' : 'area'
                        "
                        :compact="isCompact"
                    />
                </div>

                <div
                    v-else-if="
                        widget.id === 'donuts' &&
                        dashboardStats.donuts.length > 0
                    "
                    :class="widgetClass(widget)"
                    data-dashboard-widget="donuts"
                >
                    <DashboardDonutCharts
                        :charts="dashboardStats.donuts"
                        :chart-type="
                            widget.chartType === 'progress'
                                ? 'progress'
                                : 'donut'
                        "
                        :compact="isCompact"
                    />
                </div>

                <div
                    v-else-if="widget.id === 'bars'"
                    :class="widgetClass(widget)"
                    data-dashboard-widget="bars"
                >
                    <DashboardModuleChart
                        :title="dashboardStats.bars.title"
                        :subtitle="dashboardStats.bars.subtitle"
                        :items="dashboardStats.bars.items"
                        :chart-type="
                            widget.chartType === 'progress'
                                ? 'progress'
                                : 'bars'
                        "
                        :compact="isCompact"
                    />
                </div>

                <div
                    v-else-if="widget.id === 'radar'"
                    :class="widgetClass(widget)"
                    data-dashboard-widget="radar"
                >
                    <DashboardRadarChart
                        :title="dashboardStats.radar.title"
                        :subtitle="dashboardStats.radar.subtitle"
                        :items="dashboardStats.radar.items"
                        :chart-type="
                            widget.chartType === 'progress'
                                ? 'progress'
                                : 'radar'
                        "
                        :compact="isCompact"
                    />
                </div>
            </template>
        </section>

        <section
            v-else
            class="flex min-h-80 flex-col items-center justify-center rounded-[2rem] border border-dashed border-border bg-muted/20 p-8 text-center"
        >
            <span
                class="flex size-14 items-center justify-center rounded-2xl bg-muted text-muted-foreground"
            >
                <Sparkles class="size-6" />
            </span>
            <h2 class="mt-4 text-xl font-semibold">
                {{ t.dashboard.empty_title }}
            </h2>
            <p class="mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                {{ t.dashboard.empty_description }}
            </p>
            <Button
                type="button"
                class="mt-5 gap-2 rounded-xl"
                @click="openSettings"
            >
                <Settings2 class="size-4" />
                {{ t.dashboard.open_settings }}
            </Button>
        </section>
    </div>

    <DashboardCustomizer
        v-model="form.configuration"
        :open="settingsOpen"
        :processing="form.processing"
        :validation-error="validationError"
        @update:open="updateSettingsOpen"
        @reset="resetConfiguration"
        @save="submitConfiguration(true)"
    />
</template>

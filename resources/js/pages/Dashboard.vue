<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import {
    Activity,
    BadgeDollarSign,
    ClipboardList,
    ContactRound,
    FolderKanban,
    LayoutTemplate,
    MessageSquareText,
    UsersRound,
} from '@lucide/vue';
import { computed, watchEffect } from 'vue';
import { useLanguage } from '@/composables/useLanguage';
import { dashboard } from '@/routes';

type DashboardCard = {
    title: string;
    value: string;
    helper: string;
    icon: string;
};

type DashboardDonutSegment = {
    label: string;
    value: number;
    color: string;
};

type DashboardDonut = {
    title: string;
    subtitle: string;
    total: number;
    totalLabel: string;
    highlight: string;
    highlightLabel: string;
    segments: DashboardDonutSegment[];
};

type DashboardActivitySeries = {
    label: string;
    values: number[];
    color: string;
};

type DashboardActivity = {
    title: string;
    subtitle: string;
    labels: string[];
    series: DashboardActivitySeries[];
};

type DashboardBarItem = {
    label: string;
    value: number;
    color: string;
};

type DashboardRadarItem = {
    label: string;
    value: number;
    helper: string;
};

type DashboardHighlight = {
    label: string;
    value: string;
    helper: string;
};

type Props = {
    dashboardStats: {
        eyebrow: string;
        subtitle: string;
        cards: DashboardCard[];
        donuts: DashboardDonut[];
        activity: DashboardActivity;
        bars: {
            title: string;
            subtitle: string;
            items: DashboardBarItem[];
        };
        radar: {
            title: string;
            subtitle: string;
            items: DashboardRadarItem[];
        };
        highlights: DashboardHighlight[];
    };
};

const props = defineProps<Props>();
const { language, t } = useLanguage();

const iconMap = {
    users: UsersRound,
    folder: FolderKanban,
    clipboard: ClipboardList,
    messages: MessageSquareText,
    layout: LayoutTemplate,
    contact: ContactRound,
    currency: BadgeDollarSign,
} as const;

const donutRadius = 42;
const donutStroke = 12;
const donutCircumference = 2 * Math.PI * donutRadius;
const lineChartWidth = 640;
const lineChartHeight = 240;
const lineChartPaddingX = 18;
const lineChartPaddingY = 18;
const radarSize = 280;
const radarCenter = radarSize / 2;
const radarRadius = 92;

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

const cardIcon = (icon: string) => {
    return iconMap[icon as keyof typeof iconMap] ?? Activity;
};

const formatNumber = (value: number): string => {
    return new Intl.NumberFormat(
        language.value === 'ru' ? 'ru-RU' : 'en-US',
    ).format(value);
};

const donutSegments = (segments: DashboardDonutSegment[]) => {
    const total = segments.reduce((sum, segment) => sum + segment.value, 0);

    if (total === 0) {
        return [];
    }

    let offset = 0;

    return segments
        .filter((segment) => segment.value > 0)
        .map((segment) => {
            const size = (segment.value / total) * donutCircumference;
            const currentOffset = offset;

            offset += size;

            return {
                ...segment,
                dasharray: `${size} ${donutCircumference - size}`,
                dashoffset: -currentOffset,
            };
        });
};

const maxBarValue = computed(() => {
    const values = props.dashboardStats.bars.items.map((item) => item.value);

    return values.length > 0 ? Math.max(...values, 1) : 1;
});

const activityMax = computed(() => {
    const values = props.dashboardStats.activity.series.flatMap(
        (series) => series.values,
    );

    return values.length > 0 ? Math.max(...values, 1) : 1;
});

const xPosition = (index: number, total: number): number => {
    if (total <= 1) {
        return lineChartWidth / 2;
    }

    const usableWidth = lineChartWidth - lineChartPaddingX * 2;

    return lineChartPaddingX + (index / (total - 1)) * usableWidth;
};

const yPosition = (value: number): number => {
    const usableHeight = lineChartHeight - lineChartPaddingY * 2;

    return (
        lineChartHeight -
        lineChartPaddingY -
        (value / activityMax.value) * usableHeight
    );
};

const seriesPolyline = (values: number[]): string => {
    return values
        .map(
            (value, index) =>
                `${xPosition(index, values.length)},${yPosition(value)}`,
        )
        .join(' ');
};

const radarAxisPoint = (
    index: number,
    total: number,
    percentage: number,
): { x: number; y: number } => {
    const angle = -Math.PI / 2 + (Math.PI * 2 * index) / total;
    const distance = (percentage / 100) * radarRadius;

    return {
        x: radarCenter + Math.cos(angle) * distance,
        y: radarCenter + Math.sin(angle) * distance,
    };
};

const radarGrid = (percentage: number): string => {
    return props.dashboardStats.radar.items
        .map((_, index, items) => {
            const point = radarAxisPoint(index, items.length, percentage);

            return `${point.x},${point.y}`;
        })
        .join(' ');
};

const radarShape = computed(() => {
    return props.dashboardStats.radar.items
        .map((item, index, items) => {
            const point = radarAxisPoint(index, items.length, item.value);

            return `${point.x},${point.y}`;
        })
        .join(' ');
});
</script>

<template>
    <Head :title="t.common.dashboard" />

    <div
        class="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-[2rem] bg-[radial-gradient(circle_at_top_left,_rgba(255,255,255,0.9),_transparent_40%),linear-gradient(135deg,_rgba(14,165,233,0.09),_rgba(249,115,22,0.08)_55%,_rgba(34,197,94,0.08))] p-4 md:p-6"
    >
        <section
            class="overflow-hidden rounded-[2rem] border border-sidebar-border/70 bg-card/85 p-6 shadow-sm backdrop-blur dark:border-sidebar-border dark:bg-card/75"
        >
            <div
                class="flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between"
            >
                <div class="max-w-3xl space-y-3">
                    <p
                        class="inline-flex w-fit rounded-full border border-border/70 bg-background/80 px-3 py-1 text-[11px] font-semibold tracking-[0.24em] text-muted-foreground uppercase"
                    >
                        {{ dashboardStats.eyebrow }}
                    </p>

                    <div class="space-y-2">
                        <h1
                            class="text-3xl font-semibold tracking-tight md:text-4xl"
                        >
                            {{ t.common.dashboard }}
                        </h1>
                        <p
                            class="max-w-2xl text-sm leading-6 text-muted-foreground md:text-base"
                        >
                            {{ dashboardStats.subtitle }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:w-[32rem]">
                    <article
                        v-for="highlight in dashboardStats.highlights"
                        :key="highlight.label"
                        class="rounded-3xl border border-border/70 bg-background/80 p-4"
                    >
                        <p
                            class="text-xs font-medium tracking-[0.2em] text-muted-foreground uppercase"
                        >
                            {{ highlight.label }}
                        </p>
                        <p class="mt-3 text-2xl font-semibold tracking-tight">
                            {{ highlight.value }}
                        </p>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ highlight.helper }}
                        </p>
                    </article>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
            <article
                v-for="card in dashboardStats.cards"
                :key="card.title"
                class="relative overflow-hidden rounded-[1.75rem] border border-sidebar-border/70 bg-card/90 p-5 shadow-sm dark:border-sidebar-border"
            >
                <div
                    class="absolute top-0 right-0 h-24 w-24 rounded-full bg-[radial-gradient(circle,_rgba(255,255,255,0.4),_transparent_70%)]"
                />

                <div class="relative flex items-start justify-between gap-4">
                    <div class="space-y-2">
                        <p class="text-sm font-medium text-muted-foreground">
                            {{ card.title }}
                        </p>
                        <p class="text-3xl font-semibold tracking-tight">
                            {{ card.value }}
                        </p>
                    </div>

                    <div
                        class="flex size-11 items-center justify-center rounded-2xl border border-border/70 bg-background/80 text-foreground"
                    >
                        <component :is="cardIcon(card.icon)" class="size-5" />
                    </div>
                </div>

                <p
                    class="relative mt-5 text-sm leading-6 text-muted-foreground"
                >
                    {{ card.helper }}
                </p>
            </article>
        </section>

        <section
            class="rounded-[2rem] border border-sidebar-border/70 bg-card/90 p-5 shadow-sm dark:border-sidebar-border"
        >
            <div
                class="flex flex-col gap-2 md:flex-row md:items-end md:justify-between"
            >
                <div>
                    <h2 class="text-xl font-semibold tracking-tight">
                        {{ dashboardStats.activity.title }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ dashboardStats.activity.subtitle }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <div
                        v-for="series in dashboardStats.activity.series"
                        :key="series.label"
                        class="inline-flex items-center gap-2 rounded-full border border-border/70 bg-background/80 px-3 py-1.5 text-xs font-medium text-muted-foreground"
                    >
                        <span
                            class="size-2.5 rounded-full"
                            :style="{ backgroundColor: series.color }"
                        />
                        {{ series.label }}
                    </div>
                </div>
            </div>

            <div class="mt-6 overflow-x-auto">
                <div class="min-w-[680px]">
                    <svg
                        :viewBox="`0 0 ${lineChartWidth} ${lineChartHeight}`"
                        class="h-[18rem] w-full"
                        fill="none"
                    >
                        <line
                            v-for="level in 5"
                            :key="level"
                            :x1="lineChartPaddingX"
                            :x2="lineChartWidth - lineChartPaddingX"
                            :y1="
                                lineChartPaddingY +
                                ((lineChartHeight - lineChartPaddingY * 2) /
                                    4) *
                                    (level - 1)
                            "
                            :y2="
                                lineChartPaddingY +
                                ((lineChartHeight - lineChartPaddingY * 2) /
                                    4) *
                                    (level - 1)
                            "
                            stroke="currentColor"
                            class="text-border/70"
                            stroke-dasharray="5 7"
                        />

                        <polyline
                            v-for="series in dashboardStats.activity.series"
                            :key="series.label"
                            :points="seriesPolyline(series.values)"
                            :stroke="series.color"
                            stroke-width="3"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            class="drop-shadow-[0_4px_10px_rgba(0,0,0,0.08)]"
                        />

                        <g
                            v-for="series in dashboardStats.activity.series"
                            :key="`${series.label}-points`"
                        >
                            <circle
                                v-for="(value, index) in series.values"
                                :key="`${series.label}-${index}`"
                                :cx="xPosition(index, series.values.length)"
                                :cy="yPosition(value)"
                                r="4.5"
                                :fill="series.color"
                                class="stroke-background"
                                stroke-width="2"
                            />
                        </g>
                    </svg>

                    <div
                        class="mt-3 grid"
                        :style="{
                            gridTemplateColumns: `repeat(${Math.max(
                                dashboardStats.activity.labels.length,
                                1,
                            )}, minmax(0, 1fr))`,
                        }"
                    >
                        <div
                            v-for="label in dashboardStats.activity.labels"
                            :key="label"
                            class="text-center text-xs font-medium tracking-[0.16em] text-muted-foreground uppercase"
                        >
                            {{ label }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section
            v-if="dashboardStats.donuts.length > 0"
            class="grid gap-4 lg:grid-cols-2 2xl:grid-cols-3"
        >
            <article
                v-for="chart in dashboardStats.donuts"
                :key="chart.title"
                class="rounded-[2rem] border border-sidebar-border/70 bg-card/90 p-5 shadow-sm dark:border-sidebar-border"
            >
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold tracking-tight">
                            {{ chart.title }}
                        </h2>
                        <p class="mt-1 text-sm leading-6 text-muted-foreground">
                            {{ chart.subtitle }}
                        </p>
                    </div>

                    <div class="text-right">
                        <p class="text-2xl font-semibold tracking-tight">
                            {{ formatNumber(chart.total) }}
                        </p>
                        <p
                            class="text-xs tracking-[0.18em] text-muted-foreground uppercase"
                        >
                            {{ chart.totalLabel }}
                        </p>
                    </div>
                </div>

                <div class="mt-6 flex items-center gap-6">
                    <div class="relative shrink-0">
                        <svg
                            class="h-[8.5rem] w-[8.5rem] -rotate-90"
                            viewBox="0 0 108 108"
                            fill="none"
                        >
                            <circle
                                cx="54"
                                cy="54"
                                :r="donutRadius"
                                stroke="currentColor"
                                :stroke-width="donutStroke"
                                class="text-muted/55"
                            />

                            <circle
                                v-for="segment in donutSegments(chart.segments)"
                                :key="`${chart.title}-${segment.label}`"
                                cx="54"
                                cy="54"
                                :r="donutRadius"
                                :stroke="segment.color"
                                :stroke-width="donutStroke"
                                :stroke-dasharray="segment.dasharray"
                                :stroke-dashoffset="segment.dashoffset"
                                stroke-linecap="round"
                            />
                        </svg>

                        <div
                            class="absolute inset-0 flex flex-col items-center justify-center"
                        >
                            <p class="text-2xl font-semibold tracking-tight">
                                {{ chart.highlight }}
                            </p>
                            <p
                                class="text-center text-[11px] tracking-[0.18em] text-muted-foreground uppercase"
                            >
                                {{ chart.highlightLabel }}
                            </p>
                        </div>
                    </div>

                    <div class="flex-1 space-y-3">
                        <div
                            v-for="segment in chart.segments"
                            :key="`${chart.title}-${segment.label}-legend`"
                            class="rounded-2xl border border-border/70 bg-background/70 p-3"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div class="inline-flex items-center gap-2">
                                    <span
                                        class="size-2.5 rounded-full"
                                        :style="{
                                            backgroundColor: segment.color,
                                        }"
                                    />
                                    <span class="text-sm font-medium">
                                        {{ segment.label }}
                                    </span>
                                </div>

                                <span class="text-sm font-semibold">
                                    {{ formatNumber(segment.value) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <section class="grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
            <article
                class="rounded-[2rem] border border-sidebar-border/70 bg-card/90 p-5 shadow-sm dark:border-sidebar-border"
            >
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">
                        {{ dashboardStats.bars.title }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ dashboardStats.bars.subtitle }}
                    </p>
                </div>

                <div
                    class="mt-8 grid min-h-[20rem] items-end gap-4 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="item in dashboardStats.bars.items"
                        :key="item.label"
                        class="flex flex-col gap-3"
                    >
                        <div
                            class="flex min-h-[15rem] items-end rounded-[1.5rem] border border-border/70 bg-background/70 p-3"
                        >
                            <div
                                class="w-full rounded-[1rem]"
                                :style="{
                                    height: `${Math.max(
                                        18,
                                        (item.value / maxBarValue) * 220,
                                    )}px`,
                                    backgroundColor: item.color,
                                }"
                            />
                        </div>

                        <div class="space-y-1 text-center">
                            <p class="text-sm font-semibold">
                                {{ formatNumber(item.value) }}
                            </p>
                            <p
                                class="text-xs tracking-[0.16em] text-muted-foreground uppercase"
                            >
                                {{ item.label }}
                            </p>
                        </div>
                    </div>
                </div>
            </article>

            <article
                class="rounded-[2rem] border border-sidebar-border/70 bg-card/90 p-5 shadow-sm dark:border-sidebar-border"
            >
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">
                        {{ dashboardStats.radar.title }}
                    </h2>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ dashboardStats.radar.subtitle }}
                    </p>
                </div>

                <div class="mt-6 flex flex-col items-center gap-6">
                    <svg
                        :viewBox="`0 0 ${radarSize} ${radarSize}`"
                        class="h-[18rem] w-full max-w-[18rem]"
                        fill="none"
                    >
                        <polygon
                            v-for="level in [25, 50, 75, 100]"
                            :key="level"
                            :points="radarGrid(level)"
                            stroke="currentColor"
                            class="text-border/70"
                            stroke-width="1"
                            fill="none"
                        />

                        <line
                            v-for="(_, index) in dashboardStats.radar.items"
                            :key="`axis-${index}`"
                            :x1="radarCenter"
                            :y1="radarCenter"
                            :x2="
                                radarAxisPoint(
                                    index,
                                    dashboardStats.radar.items.length,
                                    100,
                                ).x
                            "
                            :y2="
                                radarAxisPoint(
                                    index,
                                    dashboardStats.radar.items.length,
                                    100,
                                ).y
                            "
                            stroke="currentColor"
                            class="text-border/70"
                            stroke-width="1"
                        />

                        <polygon
                            :points="radarShape"
                            fill="var(--color-chart-2)"
                            fill-opacity="0.18"
                            stroke="var(--color-chart-2)"
                            stroke-width="2.5"
                        />

                        <circle
                            v-for="(item, index) in dashboardStats.radar.items"
                            :key="`point-${item.label}`"
                            :cx="
                                radarAxisPoint(
                                    index,
                                    dashboardStats.radar.items.length,
                                    item.value,
                                ).x
                            "
                            :cy="
                                radarAxisPoint(
                                    index,
                                    dashboardStats.radar.items.length,
                                    item.value,
                                ).y
                            "
                            r="4"
                            fill="var(--color-chart-2)"
                            class="stroke-background"
                            stroke-width="2"
                        />
                    </svg>

                    <div class="grid w-full gap-3">
                        <div
                            v-for="item in dashboardStats.radar.items"
                            :key="item.label"
                            class="rounded-2xl border border-border/70 bg-background/70 p-3"
                        >
                            <div
                                class="flex items-center justify-between gap-3"
                            >
                                <div>
                                    <p class="text-sm font-medium">
                                        {{ item.label }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ item.helper }}
                                    </p>
                                </div>

                                <div class="text-right">
                                    <p class="text-sm font-semibold">
                                        {{ item.value }}%
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </article>
        </section>
    </div>
</template>

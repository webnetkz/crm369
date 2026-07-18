<script setup lang="ts">
import { computed } from 'vue';
import type { DashboardActivity } from '@/types/dashboard';

const props = defineProps<{
    activity: DashboardActivity;
    chartType: 'area' | 'line';
    compact: boolean;
}>();

const chartWidth = 720;
const chartHeight = 260;
const paddingX = 22;
const paddingY = 22;

const maximum = computed(() => {
    const values = props.activity.series.flatMap((series) => series.values);

    return values.length > 0 ? Math.max(...values, 1) : 1;
});

const minimumWidth = computed(() => {
    return `${Math.max(700, props.activity.labels.length * 44)}px`;
});

const labelStep = computed(() => {
    if (props.activity.labels.length > 20) {
        return 3;
    }

    return props.activity.labels.length > 10 ? 2 : 1;
});

const xPosition = (index: number, total: number): number => {
    if (total <= 1) {
        return chartWidth / 2;
    }

    return paddingX + (index / (total - 1)) * (chartWidth - paddingX * 2);
};

const yPosition = (value: number): number => {
    return (
        chartHeight -
        paddingY -
        (value / maximum.value) * (chartHeight - paddingY * 2)
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

const seriesAreaPath = (values: number[]): string => {
    if (values.length === 0) {
        return '';
    }

    const points = values
        .map(
            (value, index) =>
                `${xPosition(index, values.length)} ${yPosition(value)}`,
        )
        .join(' L ');
    const baseline = chartHeight - paddingY;

    return `M ${xPosition(0, values.length)} ${baseline} L ${points} L ${xPosition(values.length - 1, values.length)} ${baseline} Z`;
};
</script>

<template>
    <article
        class="h-full overflow-hidden rounded-[1.75rem] border border-border/70 bg-card/92 shadow-sm"
        :class="props.compact ? 'p-4' : 'p-5 md:p-6'"
    >
        <div
            class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between"
        >
            <div>
                <h2 class="text-lg font-semibold tracking-tight md:text-xl">
                    {{ props.activity.title }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ props.activity.subtitle }}
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <div
                    v-for="series in props.activity.series"
                    :key="series.label"
                    class="inline-flex items-center gap-2 rounded-full border border-border/70 bg-background/80 px-3 py-1.5 text-xs font-medium text-muted-foreground"
                >
                    <span
                        class="size-2.5 rounded-full shadow-sm"
                        :style="{ backgroundColor: series.color }"
                    />
                    {{ series.label }}
                </div>
            </div>
        </div>

        <div class="scrollbar-x-visible mt-5 overflow-x-auto pb-2">
            <div :style="{ minWidth: minimumWidth }">
                <svg
                    :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                    class="w-full"
                    :class="props.compact ? 'h-[15rem]' : 'h-[18rem]'"
                    fill="none"
                    role="img"
                    :aria-label="props.activity.title"
                >
                    <line
                        v-for="level in 5"
                        :key="level"
                        :x1="paddingX"
                        :x2="chartWidth - paddingX"
                        :y1="
                            paddingY +
                            ((chartHeight - paddingY * 2) / 4) * (level - 1)
                        "
                        :y2="
                            paddingY +
                            ((chartHeight - paddingY * 2) / 4) * (level - 1)
                        "
                        stroke="currentColor"
                        class="text-border/75"
                        stroke-dasharray="4 7"
                    />

                    <path
                        v-for="series in props.chartType === 'area'
                            ? props.activity.series
                            : []"
                        :key="`${series.label}-area`"
                        :d="seriesAreaPath(series.values)"
                        :fill="series.color"
                        opacity="0.1"
                    />

                    <polyline
                        v-for="series in props.activity.series"
                        :key="series.label"
                        :points="seriesPolyline(series.values)"
                        :stroke="series.color"
                        stroke-width="3"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        class="drop-shadow-[0_5px_12px_rgba(0,0,0,0.08)]"
                    />

                    <g
                        v-for="series in props.activity.series"
                        :key="`${series.label}-points`"
                    >
                        <circle
                            v-for="(value, index) in series.values"
                            :key="`${series.label}-${index}`"
                            :cx="xPosition(index, series.values.length)"
                            :cy="yPosition(value)"
                            r="4"
                            :fill="series.color"
                            class="stroke-background"
                            stroke-width="2"
                        >
                            <title>{{ series.label }}: {{ value }}</title>
                        </circle>
                    </g>
                </svg>

                <div
                    class="mt-2 grid"
                    :style="{
                        gridTemplateColumns: `repeat(${Math.max(props.activity.labels.length, 1)}, minmax(0, 1fr))`,
                    }"
                >
                    <div
                        v-for="(label, index) in props.activity.labels"
                        :key="`${label}-${index}`"
                        class="text-center text-[10px] font-medium tracking-[0.08em] text-muted-foreground uppercase"
                    >
                        <span
                            v-if="
                                index % labelStep === 0 ||
                                index === props.activity.labels.length - 1
                            "
                        >
                            {{ label }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </article>
</template>

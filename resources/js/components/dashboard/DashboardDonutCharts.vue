<script setup lang="ts">
import type { DashboardDonut, DashboardDonutSegment } from '@/types/dashboard';

const props = defineProps<{
    charts: DashboardDonut[];
    chartType: 'donut' | 'progress';
    compact: boolean;
}>();

const donutRadius = 42;
const donutStroke = 11;
const donutCircumference = 2 * Math.PI * donutRadius;

const formatNumber = (value: number): string => {
    return new Intl.NumberFormat().format(value);
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

const segmentPercentage = (chart: DashboardDonut, value: number): number => {
    const total = chart.segments.reduce(
        (sum, segment) => sum + segment.value,
        0,
    );

    return total > 0 ? (value / total) * 100 : 0;
};
</script>

<template>
    <div
        class="grid h-full gap-4"
        :class="props.charts.length > 1 ? '2xl:grid-cols-2' : ''"
    >
        <article
            v-for="chart in props.charts"
            :key="chart.title"
            class="rounded-[1.75rem] border border-border/70 bg-card/92 shadow-sm"
            :class="props.compact ? 'p-4' : 'p-5'"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2
                        class="text-base font-semibold tracking-tight md:text-lg"
                    >
                        {{ chart.title }}
                    </h2>
                    <p class="mt-1 text-sm leading-5 text-muted-foreground">
                        {{ chart.subtitle }}
                    </p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-2xl font-semibold tracking-tight">
                        {{ formatNumber(chart.total) }}
                    </p>
                    <p
                        class="text-[10px] tracking-[0.14em] text-muted-foreground uppercase"
                    >
                        {{ chart.totalLabel }}
                    </p>
                </div>
            </div>

            <div
                v-if="props.chartType === 'donut'"
                class="mt-6 flex flex-col gap-5 sm:flex-row sm:items-center"
            >
                <div class="relative mx-auto shrink-0 sm:mx-0">
                    <svg
                        class="size-32 -rotate-90"
                        viewBox="0 0 108 108"
                        fill="none"
                    >
                        <circle
                            cx="54"
                            cy="54"
                            :r="donutRadius"
                            stroke="currentColor"
                            :stroke-width="donutStroke"
                            class="text-muted/70"
                        />
                        <circle
                            v-for="segment in donutSegments(chart.segments)"
                            :key="segment.label"
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
                        <p class="text-xl font-semibold">
                            {{ chart.highlight }}
                        </p>
                        <p
                            class="max-w-20 text-center text-[9px] tracking-[0.12em] text-muted-foreground uppercase"
                        >
                            {{ chart.highlightLabel }}
                        </p>
                    </div>
                </div>

                <div class="min-w-0 flex-1 space-y-2.5">
                    <div
                        v-for="segment in chart.segments"
                        :key="segment.label"
                        class="flex items-center justify-between gap-3 rounded-xl border border-border/70 bg-background/70 px-3 py-2.5"
                    >
                        <span
                            class="inline-flex min-w-0 items-center gap-2 text-sm font-medium"
                        >
                            <span
                                class="size-2.5 shrink-0 rounded-full"
                                :style="{ backgroundColor: segment.color }"
                            />
                            <span class="truncate">{{ segment.label }}</span>
                        </span>
                        <span class="text-sm font-semibold">{{
                            formatNumber(segment.value)
                        }}</span>
                    </div>
                </div>
            </div>

            <div v-else class="mt-6 space-y-4">
                <div
                    v-for="segment in chart.segments"
                    :key="segment.label"
                    class="space-y-2"
                >
                    <div
                        class="flex items-center justify-between gap-3 text-sm"
                    >
                        <span class="font-medium">{{ segment.label }}</span>
                        <span class="font-semibold">{{
                            formatNumber(segment.value)
                        }}</span>
                    </div>
                    <div class="h-2.5 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full transition-[width] duration-500"
                            :style="{
                                width: `${segmentPercentage(chart, segment.value)}%`,
                                backgroundColor: segment.color,
                            }"
                        />
                    </div>
                </div>
            </div>
        </article>
    </div>
</template>

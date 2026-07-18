<script setup lang="ts">
import { computed } from 'vue';
import type { DashboardRadarItem } from '@/types/dashboard';

const props = defineProps<{
    title: string;
    subtitle: string;
    items: DashboardRadarItem[];
    chartType: 'radar' | 'progress';
    compact: boolean;
}>();

const size = 280;
const center = size / 2;
const radius = 92;

const axisPoint = (
    index: number,
    total: number,
    percentage: number,
): { x: number; y: number } => {
    const angle = -Math.PI / 2 + (Math.PI * 2 * index) / total;
    const distance = (percentage / 100) * radius;

    return {
        x: center + Math.cos(angle) * distance,
        y: center + Math.sin(angle) * distance,
    };
};

const grid = (percentage: number): string => {
    return props.items
        .map((_, index, items) => {
            const point = axisPoint(index, items.length, percentage);

            return `${point.x},${point.y}`;
        })
        .join(' ');
};

const shape = computed(() => {
    return props.items
        .map((item, index, items) => {
            const point = axisPoint(index, items.length, item.value);

            return `${point.x},${point.y}`;
        })
        .join(' ');
});
</script>

<template>
    <article
        class="h-full rounded-[1.75rem] border border-border/70 bg-card/92 shadow-sm"
        :class="props.compact ? 'p-4' : 'p-5 md:p-6'"
    >
        <div>
            <h2 class="text-lg font-semibold tracking-tight">
                {{ props.title }}
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
                {{ props.subtitle }}
            </p>
        </div>

        <div
            v-if="props.chartType === 'radar'"
            class="mt-5 flex flex-col items-center gap-5"
        >
            <svg
                :viewBox="`0 0 ${size} ${size}`"
                class="w-full max-w-72"
                :class="props.compact ? 'h-64' : 'h-72'"
                fill="none"
                role="img"
                :aria-label="props.title"
            >
                <polygon
                    v-for="level in [25, 50, 75, 100]"
                    :key="level"
                    :points="grid(level)"
                    stroke="currentColor"
                    class="text-border/80"
                    stroke-width="1"
                />
                <line
                    v-for="(_, index) in props.items"
                    :key="index"
                    :x1="center"
                    :y1="center"
                    :x2="axisPoint(index, props.items.length, 100).x"
                    :y2="axisPoint(index, props.items.length, 100).y"
                    stroke="currentColor"
                    class="text-border/80"
                />
                <polygon
                    :points="shape"
                    fill="var(--color-chart-2)"
                    fill-opacity="0.18"
                    stroke="var(--color-chart-2)"
                    stroke-width="3"
                    stroke-linejoin="round"
                />
                <circle
                    v-for="(item, index) in props.items"
                    :key="item.label"
                    :cx="axisPoint(index, props.items.length, item.value).x"
                    :cy="axisPoint(index, props.items.length, item.value).y"
                    r="4"
                    fill="var(--color-chart-2)"
                    class="stroke-background"
                    stroke-width="2"
                >
                    <title>{{ item.label }}: {{ item.value }}%</title>
                </circle>
            </svg>

            <div class="grid w-full gap-2 sm:grid-cols-2">
                <div
                    v-for="item in props.items"
                    :key="item.label"
                    class="rounded-xl border border-border/70 bg-background/65 px-3 py-2.5"
                >
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-medium">{{
                            item.label
                        }}</span>
                        <span class="text-xs font-semibold"
                            >{{ item.value }}%</span
                        >
                    </div>
                    <p class="mt-1 truncate text-[10px] text-muted-foreground">
                        {{ item.helper }}
                    </p>
                </div>
            </div>
        </div>

        <div v-else class="mt-6 space-y-4">
            <div
                v-for="item in props.items"
                :key="item.label"
                class="space-y-2"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium">{{ item.label }}</p>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ item.helper }}
                        </p>
                    </div>
                    <span class="text-sm font-semibold">{{ item.value }}%</span>
                </div>
                <div class="h-2.5 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full rounded-full bg-chart-2 transition-[width] duration-500"
                        :style="{ width: `${item.value}%` }"
                    />
                </div>
            </div>
        </div>
    </article>
</template>

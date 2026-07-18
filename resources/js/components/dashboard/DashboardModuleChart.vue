<script setup lang="ts">
import { computed } from 'vue';
import type { DashboardBarItem } from '@/types/dashboard';

const props = defineProps<{
    title: string;
    subtitle: string;
    items: DashboardBarItem[];
    chartType: 'bars' | 'progress';
    compact: boolean;
}>();

const maximum = computed(() => {
    const values = props.items.map((item) => item.value);

    return values.length > 0 ? Math.max(...values, 1) : 1;
});

const formatNumber = (value: number): string => {
    return new Intl.NumberFormat().format(value);
};
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
            v-if="props.chartType === 'bars'"
            class="mt-7 grid min-h-64 items-end gap-3"
            :style="{
                gridTemplateColumns: `repeat(${Math.max(props.items.length, 1)}, minmax(3rem, 1fr))`,
            }"
        >
            <div
                v-for="item in props.items"
                :key="item.label"
                class="flex min-w-0 flex-col items-center gap-3"
            >
                <div
                    class="flex h-48 w-full max-w-24 items-end rounded-2xl bg-muted/65 p-2"
                >
                    <div
                        class="w-full rounded-xl shadow-sm transition-[height] duration-500"
                        :style="{
                            height: `${Math.max(12, (item.value / maximum) * 176)}px`,
                            backgroundColor: item.color,
                        }"
                    />
                </div>
                <div class="min-w-0 text-center">
                    <p class="text-sm font-semibold">
                        {{ formatNumber(item.value) }}
                    </p>
                    <p
                        class="mt-1 truncate text-[10px] tracking-[0.1em] text-muted-foreground uppercase"
                    >
                        {{ item.label }}
                    </p>
                </div>
            </div>
        </div>

        <div v-else class="mt-7 space-y-5">
            <div
                v-for="item in props.items"
                :key="item.label"
                class="space-y-2"
            >
                <div class="flex items-center justify-between gap-3 text-sm">
                    <span class="font-medium">{{ item.label }}</span>
                    <span class="font-semibold">{{
                        formatNumber(item.value)
                    }}</span>
                </div>
                <div class="h-3 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full rounded-full transition-[width] duration-500"
                        :style="{
                            width: `${(item.value / maximum) * 100}%`,
                            backgroundColor: item.color,
                        }"
                    />
                </div>
            </div>
        </div>
    </article>
</template>

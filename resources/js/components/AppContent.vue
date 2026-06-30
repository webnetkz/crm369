<script setup lang="ts">
import { computed } from 'vue';
import { SidebarInset } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    variant?: AppVariant;
    class?: string;
};

const props = withDefaults(defineProps<Props>(), {
    variant: 'sidebar',
});
const className = computed(() => props.class);
const contentStyle = computed<Record<string, string>>(() => ({
    backgroundColor: 'var(--app-shell-content-background, var(--background))',
    backdropFilter: 'var(--app-shell-backdrop-filter, none)',
}));
</script>

<template>
    <SidebarInset
        v-if="props.variant === 'sidebar'"
        :class="['px-[10px] py-[5px]', className]"
        :style="contentStyle"
    >
        <div class="flex flex-1 flex-col pb-8">
            <slot />
        </div>
    </SidebarInset>
    <main
        v-else
        class="mx-auto flex h-full w-full max-w-7xl flex-1 flex-col gap-4 rounded-xl px-[10px] py-[5px]"
        :class="className"
        :style="contentStyle"
    >
        <div class="flex flex-1 flex-col pb-8">
            <slot />
        </div>
    </main>
</template>

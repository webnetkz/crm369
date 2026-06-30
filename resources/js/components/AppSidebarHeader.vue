<script setup lang="ts">
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import NotificationCenterSheet from '@/components/NotificationCenterSheet.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);
</script>

<template>
    <header
        class="sticky top-0 z-30 flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear [--app-sidebar-header-height:4rem] group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 group-has-data-[collapsible=icon]/sidebar-wrapper:[--app-sidebar-header-height:3rem] group-data-[has-background=true]/app-shell:bg-transparent group-data-[has-background=true]/app-shell:supports-[backdrop-filter]:bg-background/18 group-data-[has-background=true]/app-shell:supports-[backdrop-filter]:backdrop-blur-[var(--app-glass-blur)] md:px-4"
        :style="{
            backgroundColor:
                'var(--app-shell-surface-background, var(--background))',
            backdropFilter: 'var(--app-shell-backdrop-filter, none)',
        }"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="ml-auto flex items-center gap-2">
            <NotificationCenterSheet />
        </div>
    </header>
</template>

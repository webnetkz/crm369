<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import ChatDock from '@/components/ChatDock.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
    sidebarCollapsed?: boolean;
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
    sidebarCollapsed: false,
});
</script>

<template>
    <AppShell variant="sidebar" :sidebar-collapsed="sidebarCollapsed">
        <AppSidebar />
        <AppContent
            variant="sidebar"
            class="min-h-0 overflow-x-hidden overflow-y-auto"
        >
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <ChatDock />
        <Toaster />
    </AppShell>
</template>

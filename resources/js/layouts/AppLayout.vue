<script setup lang="ts">
import AppLayout from '@/layouts/app/AppSidebarLayout.vue';
import type { BreadcrumbItem } from '@/types';

const { breadcrumbs = [] } = defineProps<{
    breadcrumbs?: BreadcrumbItem[];
    sidebarCollapsed?: boolean;
}>();
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :sidebar-collapsed="sidebarCollapsed">
        <slot />
    </AppLayout>
</template>

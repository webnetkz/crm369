<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

const page = usePage();
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md text-sidebar-primary-foreground"
        :class="page.props.portal.logoUrl ? 'bg-white' : 'bg-sidebar-primary'"
    >
        <img
            v-if="page.props.portal.logoUrl"
            :src="page.props.portal.logoUrl"
            :alt="page.props.portal.companyName"
            class="size-6 object-contain"
        />
        <AppLogoIcon
            v-else
            class="size-5 fill-current text-white dark:text-black"
        />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">
            {{ page.props.portal.companyName }}
        </span>
    </div>
</template>

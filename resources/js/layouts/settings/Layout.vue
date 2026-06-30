<script setup lang="ts">
import SettingsTabs from '@/components/SettingsTabs.vue';
</script>

<template>
    <div class="min-h-full px-4 py-6 md:px-6 lg:px-8">
        <section class="w-full space-y-12">
            <div
                class="sticky top-[var(--app-sidebar-header-height,4rem)] z-20"
                :style="{
                    backgroundColor:
                        'var(--app-shell-surface-background, var(--background))',
                    backdropFilter: 'var(--app-shell-backdrop-filter, none)',
                }"
            >
                <SettingsTabs />
            </div>
            <slot />
        </section>
    </div>
</template>

<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, watch } from 'vue';
import { SidebarProvider } from '@/components/ui/sidebar';
import { useBackgroundPreview } from '@/composables/useBackgroundPreview';
import type { AppVariant } from '@/types';

type Props = {
    variant?: AppVariant;
};

withDefaults(defineProps<Props>(), {
    variant: 'sidebar',
});

const isOpen = usePage().props.sidebarOpen;
const page = usePage();
const { persisted, preview, setPersisted } = useBackgroundPreview();

const authBackgroundSettings = computed(() => {
    const user = page.props.auth.user;

    return {
        color:
            typeof user?.background_color === 'string' &&
            user.background_color !== ''
                ? user.background_color
                : null,
        image:
            typeof user?.background_image === 'string' &&
            user.background_image !== ''
                ? user.background_image
                : null,
        blur:
            typeof user?.background_blur === 'number'
                ? user.background_blur
                : 0,
    };
});

watch(authBackgroundSettings, (value) => {
    setPersisted(value);
}, { immediate: true });

const backgroundSettings = computed(() => {
    return preview.value ?? persisted.value ?? authBackgroundSettings.value;
});

const hasCustomBackground = computed(() => {
    return (
        backgroundSettings.value.color !== null ||
        backgroundSettings.value.image !== null
    );
});

const shellStyle = computed<Record<string, string>>(() => ({
    '--app-glass-blur': `${Math.round(backgroundSettings.value.blur * 0.18)}px`,
    '--app-shell-wrapper-background': hasCustomBackground.value
        ? 'transparent'
        : 'var(--sidebar-background)',
    '--app-shell-content-background': hasCustomBackground.value
        ? 'transparent'
        : 'var(--background)',
    '--app-shell-surface-background': 'var(--background)',
    '--app-shell-sidebar-background': hasCustomBackground.value
        ? 'color-mix(in srgb, var(--sidebar-background) 48%, transparent)'
        : 'var(--sidebar-background)',
    '--app-shell-backdrop-filter': hasCustomBackground.value
        ? `blur(${Math.round(backgroundSettings.value.blur * 0.18)}px)`
        : 'none',
    backgroundColor: backgroundSettings.value.color ?? 'var(--background)',
}));

const backgroundColorStyle = computed<Record<string, string>>(() => ({
    backgroundColor: backgroundSettings.value.color ?? 'transparent',
}));

const backgroundImageStyle = computed<Record<string, string>>(() => ({
    backgroundImage: `url("${backgroundSettings.value.image}")`,
    backgroundPosition: 'center',
    backgroundRepeat: 'no-repeat',
    backgroundSize: 'cover',
    filter: `blur(${Math.round(backgroundSettings.value.blur * 0.45)}px)`,
    transform: 'scale(1.08)',
}));

watch(backgroundSettings, (value) => {
    if (typeof document === 'undefined') {
        return;
    }

    const backgroundColor = value.color ?? 'var(--background)';

    document.documentElement.style.backgroundColor = backgroundColor;
    document.body.style.backgroundColor = backgroundColor;
}, { immediate: true });
</script>

<template>
    <div
        class="group/app-shell relative min-h-svh"
        :data-has-background="hasCustomBackground"
        :style="shellStyle"
    >
        <div
            v-if="hasCustomBackground"
            class="pointer-events-none absolute inset-0 overflow-hidden"
        >
            <div class="absolute inset-0" :style="backgroundColorStyle"></div>
            <div
                v-if="backgroundSettings.image"
                class="absolute inset-[-4%]"
                :style="backgroundImageStyle"
            ></div>
            <div class="absolute inset-0 bg-white/10 dark:bg-black/18"></div>
        </div>

        <div
            v-if="variant === 'header'"
            class="relative z-10 flex min-h-screen w-full flex-col"
        >
            <slot />
        </div>
        <SidebarProvider v-else :default-open="isOpen" class="relative z-10">
            <slot />
        </SidebarProvider>
    </div>
</template>

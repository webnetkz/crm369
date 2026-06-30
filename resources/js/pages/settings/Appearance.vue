<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ImagePlus, Sparkles, Trash2 } from '@lucide/vue';
import { computed, onBeforeUnmount, ref, watch, watchEffect } from 'vue';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import LocalizedFilePicker from '@/components/LocalizedFilePicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAppearance } from '@/composables/useAppearance';
import { useBackgroundPreview } from '@/composables/useBackgroundPreview';
import { useLanguage } from '@/composables/useLanguage';
import { edit, update } from '@/routes/appearance';
import type { AppearanceSettings } from '@/types';

const props = defineProps<{
    settings: AppearanceSettings;
}>();

const { resolvedAppearance } = useAppearance();
const { setPersisted, setPreview, clearPreview } = useBackgroundPreview();
const { t } = useLanguage();

const backgroundImageInputKey = ref(0);
const backgroundImagePreviewUrl = ref<string | null>(
    props.settings.background_image_url,
);

let previewObjectUrl: string | null = null;

const defaultBackgroundColor = computed(() => {
    return resolvedAppearance.value === 'dark' ? '#020817' : '#f8fafc';
});

type AppearanceFormData = {
    background_color: string;
    background_image: File | null;
    background_blur: number;
    remove_background_image: boolean;
};

const normalizeBackgroundColor = (value: string | null | undefined): string | null => {
    if (typeof value !== 'string') {
        return null;
    }

    const normalizedValue = value.trim();

    if (!/^#[0-9A-Fa-f]{6}$/.test(normalizedValue)) {
        return null;
    }

    return normalizedValue;
};

const buildFormDefaults = (settings: AppearanceSettings): AppearanceFormData => ({
    background_color:
        normalizeBackgroundColor(settings.background_color) ?? defaultBackgroundColor.value,
    background_image: null,
    background_blur: settings.background_blur,
    remove_background_image: false,
});

const form = useForm<AppearanceFormData>(buildFormDefaults(props.settings));

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.settings.appearance_settings,
                href: edit(),
            },
        ],
    });
});

const revokePreviewUrl = (): void => {
    if (previewObjectUrl) {
        URL.revokeObjectURL(previewObjectUrl);
        previewObjectUrl = null;
    }
};

watch(
    () => props.settings,
    (settings) => {
        const defaults = buildFormDefaults(settings);
        const persistedColor = normalizeBackgroundColor(settings.background_color);

        form.defaults(defaults);
        form.background_color = defaults.background_color;
        form.background_blur = defaults.background_blur;
        form.background_image = defaults.background_image;
        form.remove_background_image = defaults.remove_background_image;
        backgroundImagePreviewUrl.value = settings.background_image_url;
        backgroundImageInputKey.value += 1;

        setPersisted({
            color: persistedColor,
            image: settings.background_image_url,
            blur: settings.background_blur,
        });

        revokePreviewUrl();
    },
    { deep: true, immediate: true },
);

watch(defaultBackgroundColor, (value) => {
    if (props.settings.background_color === null && !form.isDirty) {
        form.background_color = value;
        form.defaults('background_color', value);
    }
});

onBeforeUnmount(() => {
    clearPreview();
    revokePreviewUrl();
});

const previewBackgroundStyle = computed<Record<string, string>>(() => ({
    backgroundColor: currentBackgroundColor.value,
}));

const previewImageStyle = computed<Record<string, string>>(() => ({
    backgroundImage: backgroundImagePreviewUrl.value
        ? `url("${backgroundImagePreviewUrl.value}")`
        : 'none',
    backgroundPosition: 'center',
    backgroundRepeat: 'no-repeat',
    backgroundSize: 'cover',
    filter: `blur(${Math.round(form.background_blur * 0.45)}px)`,
    transform: 'scale(1.08)',
}));

const glassPreviewStyle = computed<Record<string, string>>(() => ({
    backdropFilter: `blur(${Math.round(form.background_blur * 0.18)}px)`,
}));

const persistedBackgroundColor = computed(() => {
    return normalizeBackgroundColor(props.settings.background_color) ?? defaultBackgroundColor.value;
});

const currentBackgroundColor = computed(() => {
    return normalizeBackgroundColor(form.background_color) ?? persistedBackgroundColor.value;
});

const hasBackgroundChanges = computed(() => {
    return currentBackgroundColor.value !== persistedBackgroundColor.value
        || form.background_blur !== props.settings.background_blur
        || backgroundImagePreviewUrl.value !== props.settings.background_image_url;
});

watch(
    [currentBackgroundColor, backgroundImagePreviewUrl, () => form.background_blur, hasBackgroundChanges],
    () => {
        if (!hasBackgroundChanges.value) {
            clearPreview();

            return;
        }

        setPreview({
            color: currentBackgroundColor.value,
            image: backgroundImagePreviewUrl.value,
            blur: form.background_blur,
        });
    },
    { immediate: true },
);

const selectBackgroundImage = (file: File | null): void => {
    revokePreviewUrl();

    form.remove_background_image = false;

    if (!file) {
        backgroundImagePreviewUrl.value = props.settings.background_image_url;

        return;
    }

    previewObjectUrl = URL.createObjectURL(file);
    backgroundImagePreviewUrl.value = previewObjectUrl;
};

const clearBackgroundImage = (): void => {
    revokePreviewUrl();

    form.background_image = null;
    form.remove_background_image = true;
    backgroundImagePreviewUrl.value = null;
    backgroundImageInputKey.value += 1;
};

const submitBackground = (): void => {
    form.post(update.url(), {
        forceFormData: true,
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="t.settings.appearance_settings" />

    <h1 class="sr-only">{{ t.settings.appearance_settings }}</h1>

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="t.settings.appearance_settings"
            :description="t.settings.appearance_description"
        />

        <AppearanceTabs />

        <div class="h-px bg-border" />

        <div class="space-y-6">
            <Heading
                variant="small"
                :title="t.settings.personal_background"
                :description="t.settings.background_image_help"
            />

            <form
                class="grid gap-6 rounded-lg border border-border p-4 lg:grid-cols-[1.05fr_0.95fr]"
                @submit.prevent="submitBackground"
            >
                <div class="space-y-4">
                    <Label>{{ t.settings.background_preview }}</Label>

                    <div
                        class="relative min-h-72 overflow-hidden rounded-2xl border border-border bg-muted"
                    >
                        <div
                            class="absolute inset-0"
                            :style="previewBackgroundStyle"
                        ></div>
                        <div
                            class="absolute inset-[-4%]"
                            :style="previewImageStyle"
                        ></div>
                        <div
                            class="absolute inset-0 bg-white/15 dark:bg-black/25"
                        ></div>

                        <div
                            class="relative flex min-h-72 flex-col justify-between p-5"
                        >
                            <div class="flex items-center gap-2 text-sm font-medium text-white drop-shadow-sm">
                                <Sparkles class="size-4" />
                                {{ t.settings.appearance_settings }}
                            </div>

                            <div
                                class="max-w-sm rounded-2xl border border-white/35 bg-white/22 p-4 text-sm text-white shadow-xl"
                                :style="glassPreviewStyle"
                            >
                                <p class="font-medium">
                                    {{ t.settings.personal_background }}
                                </p>
                                <p class="mt-1 text-white/85">
                                    {{ t.settings.background_blur }}:
                                    {{ form.background_blur }}%
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-5">
                    <div class="grid gap-2">
                        <Label for="background_color">
                            {{ t.settings.background_color }}
                        </Label>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <Input
                                id="background_color"
                                v-model="form.background_color"
                                type="color"
                                class="h-12 w-full rounded-xl p-1 sm:w-18"
                            />
                            <Input
                                v-model="form.background_color"
                                type="text"
                                inputmode="text"
                                placeholder="#F8FAFC"
                                class="font-mono uppercase"
                            />
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ t.settings.background_color_help }}
                        </p>
                        <InputError :message="form.errors.background_color" />
                    </div>

                    <div class="grid gap-3">
                        <Label for="background_image">
                            {{ t.settings.background_image }}
                        </Label>

                        <div
                            class="flex min-h-36 items-center justify-center rounded-2xl border border-dashed border-border bg-muted/40"
                        >
                            <img
                                v-if="backgroundImagePreviewUrl"
                                :src="backgroundImagePreviewUrl"
                                :alt="t.settings.background_image"
                                class="h-full max-h-32 w-full rounded-xl object-cover p-2"
                            />
                            <div
                                v-else
                                class="flex flex-col items-center gap-2 text-sm text-muted-foreground"
                            >
                                <ImagePlus class="size-7" />
                                <span>{{ t.settings.background_image }}</span>
                            </div>
                        </div>

                        <LocalizedFilePicker
                            id="background_image"
                            :key="backgroundImageInputKey"
                            name="background_image"
                            v-model="form.background_image"
                            accept="image/png,image/jpeg,image/jpg,image/webp"
                            @change="selectBackgroundImage"
                        />
                        <p class="text-sm text-muted-foreground">
                            {{ t.settings.background_image_help }}
                        </p>
                        <InputError :message="form.errors.background_image" />

                        <div v-if="backgroundImagePreviewUrl" class="flex">
                            <Button
                                type="button"
                                variant="outline"
                                @click="clearBackgroundImage"
                            >
                                <Trash2 class="size-4" />
                                {{ t.settings.remove_background_image }}
                            </Button>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between gap-4">
                            <Label for="background_blur">
                                {{ t.settings.background_blur }}
                            </Label>
                            <span class="text-sm text-muted-foreground">
                                {{ form.background_blur }}%
                            </span>
                        </div>
                        <Input
                            id="background_blur"
                            v-model.number="form.background_blur"
                            type="range"
                            min="0"
                            max="100"
                            step="1"
                        />
                        <p class="text-sm text-muted-foreground">
                            {{ t.settings.background_blur_help }}
                        </p>
                        <InputError :message="form.errors.background_blur" />
                    </div>

                    <div
                        v-if="form.progress"
                        class="h-2 overflow-hidden rounded-full bg-muted"
                    >
                        <div
                            class="h-full bg-primary transition-all"
                            :style="{ width: `${form.progress.percentage}%` }"
                        ></div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button type="submit" :disabled="form.processing">
                            {{ t.common.save }}
                        </Button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>

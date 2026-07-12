<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { Upload } from '@lucide/vue';
import { watchEffect } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import LocalizedFilePicker from '@/components/LocalizedFilePicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import { edit, update } from '@/routes/settings/portal';
import type { Language } from '@/types';

type PortalSettings = {
    company_name: string;
    logo_url: string;
    default_language: Language;
};

const props = defineProps<{
    settings: PortalSettings;
}>();

const { t } = useLanguage();
const form = useForm({
    company_name: props.settings.company_name,
    logo: null as File | null,
    default_language: props.settings.default_language,
});

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.portal.title,
                href: edit(),
            },
        ],
    });
});

const submit = (): void => {
    form.post(update.url(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => form.reset('logo'),
    });
};
</script>

<template>
    <Head :title="t.portal.title" />

    <h1 class="sr-only">{{ t.portal.title }}</h1>

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="t.portal.title"
            :description="t.portal.description"
        />

        <form
            class="space-y-6 rounded-lg border border-border p-4"
            @submit.prevent="submit"
        >
            <div class="grid gap-2">
                <Label for="company_name">{{ t.portal.company_name }}</Label>
                <Input
                    id="company_name"
                    v-model="form.company_name"
                    name="company_name"
                    :placeholder="t.portal.company_name_placeholder"
                    autocomplete="organization"
                />
                <InputError :message="form.errors.company_name" />
            </div>

            <div class="grid gap-3">
                <Label for="logo">{{ t.portal.logo }}</Label>

                <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                    <div
                        class="flex size-20 items-center justify-center rounded-lg border border-dashed border-border"
                        :class="settings.logo_url ? 'bg-white' : 'bg-muted/40'"
                    >
                        <img
                            v-if="settings.logo_url"
                            :src="settings.logo_url"
                            :alt="settings.company_name"
                            class="max-h-16 max-w-16 object-contain"
                        />
                        <Upload v-else class="size-6 text-muted-foreground" />
                    </div>

                    <div class="grid flex-1 gap-2">
                        <LocalizedFilePicker
                            id="logo"
                            name="logo"
                            v-model="form.logo"
                            accept="image/png,image/jpeg,image/webp"
                        />
                        <p class="text-sm text-muted-foreground">
                            {{ t.portal.logo_help }}
                        </p>
                        <InputError :message="form.errors.logo" />
                    </div>
                </div>
            </div>

            <div class="grid gap-3">
                <Label>{{ t.portal.default_language }}</Label>

                <div
                    class="inline-flex w-fit gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800"
                >
                    <button
                        type="button"
                        @click="form.default_language = 'ru'"
                        :class="[
                            'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                            form.default_language === 'ru'
                                ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                                : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
                        ]"
                    >
                        <span class="text-sm">{{ t.settings.russian }}</span>
                    </button>

                    <button
                        type="button"
                        @click="form.default_language = 'en'"
                        :class="[
                            'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                            form.default_language === 'en'
                                ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                                : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
                        ]"
                    >
                        <span class="text-sm">{{ t.settings.english }}</span>
                    </button>
                </div>

                <p class="text-sm text-muted-foreground">
                    {{ t.portal.default_language_help }}
                </p>
                <InputError :message="form.errors.default_language" />
            </div>

            <Button type="submit" :disabled="form.processing">
                {{ t.common.save }}
            </Button>
        </form>
    </div>
</template>

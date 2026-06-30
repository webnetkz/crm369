<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { LayoutGrid, Power } from '@lucide/vue';
import { computed, watchEffect } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useLanguage } from '@/composables/useLanguage';
import { edit, update } from '@/routes/settings/modules';

type ModuleOption = {
    key: string;
    title: string;
    description: string;
    is_enabled: boolean;
};

const props = defineProps<{
    modules: ModuleOption[];
    disabledModules: string[];
}>();

const { t } = useLanguage();
const form = useForm({
    disabled_modules: [...props.disabledModules] as string[],
});

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.modules.title,
                href: edit(),
            },
        ],
    });
});

const enabledCount = computed(() => {
    return props.modules.length - form.disabled_modules.length;
});

const isDisabled = (key: string): boolean => {
    return form.disabled_modules.includes(key);
};

const toggleModule = (
    key: string,
    checked: boolean | 'indeterminate',
): void => {
    const disabledModules = new Set(form.disabled_modules);

    if (checked === true) {
        disabledModules.delete(key);
    } else {
        disabledModules.add(key);
    }

    form.disabled_modules = props.modules
        .map((module) => module.key)
        .filter((module) => disabledModules.has(module));
};

const submit = (): void => {
    form.patch(update.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.defaults({
                disabled_modules: [...form.disabled_modules],
            });
        },
    });
};
</script>

<template>
    <Head :title="t.modules.title" />

    <h1 class="sr-only">{{ t.modules.title }}</h1>

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="t.modules.title"
            :description="t.modules.description"
        />

        <div
            class="flex flex-col gap-4 rounded-xl border border-border bg-card/60 p-5 md:flex-row md:items-center md:justify-between"
        >
            <div class="space-y-2">
                <div class="flex items-center gap-2 text-sm font-medium">
                    <LayoutGrid class="size-4 text-muted-foreground" />
                    <span>{{
                        t.modules.enabled_summary
                            .replace(':enabled', String(enabledCount))
                            .replace(':total', String(modules.length))
                    }}</span>
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ t.modules.help }}
                </p>
            </div>

            <Button type="button" :disabled="form.processing" @click="submit">
                {{ t.common.save }}
            </Button>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid gap-4 lg:grid-cols-2">
                <div
                    v-for="module in modules"
                    :key="module.key"
                    class="rounded-xl border border-border bg-card p-5 shadow-xs transition-colors"
                    :class="
                        isDisabled(module.key)
                            ? 'border-border/80 opacity-80'
                            : 'border-primary/20 bg-primary/5'
                    "
                >
                    <div class="flex items-start gap-4">
                        <Checkbox
                            :checked="!isDisabled(module.key)"
                            :disabled="form.processing"
                            class="mt-1"
                            @update:checked="
                                (checked) => toggleModule(module.key, checked)
                            "
                        />

                        <div class="min-w-0 flex-1 space-y-2">
                            <div
                                class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <div class="flex items-center gap-2">
                                    <Power
                                        class="size-4 text-muted-foreground"
                                    />
                                    <h2 class="font-semibold text-foreground">
                                        {{ module.title }}
                                    </h2>
                                </div>

                                <span
                                    class="inline-flex w-fit rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="
                                        isDisabled(module.key)
                                            ? 'bg-muted text-muted-foreground'
                                            : 'bg-primary/10 text-primary'
                                    "
                                >
                                    {{
                                        isDisabled(module.key)
                                            ? t.modules.disabled
                                            : t.modules.enabled
                                    }}
                                </span>
                            </div>

                            <p class="text-sm leading-6 text-muted-foreground">
                                {{ module.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <InputError :message="form.errors.disabled_modules" />

            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    {{ t.common.save }}
                </Button>
            </div>
        </form>
    </div>
</template>

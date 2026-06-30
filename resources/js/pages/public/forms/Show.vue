<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ClipboardList, Send } from '@lucide/vue';
import { computed } from 'vue';
import { submit as submitPortalForm } from '@/actions/App/Http/Controllers/PublicPortalFormController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import type { PortalFormPublicItem } from '@/types/ui';

const props = defineProps<{
    form: PortalFormPublicItem;
}>();

const { t } = useLanguage();

const defaultValues = computed<Record<string, string>>(() => {
    return Object.fromEntries(props.form.fields.map((field) => [field.key, '']));
});

const form = useForm({
    values: defaultValues.value,
});

const fieldError = (key: string): string | undefined => {
    return form.errors[`values.${key}`];
};

const submit = (): void => {
    form.post(submitPortalForm.url(props.form.public_token), {
        preserveScroll: true,
        onSuccess: () => {
            form.values = defaultValues.value;
        },
    });
};
</script>

<template>
    <Head :title="props.form.name" />

    <div class="mx-auto flex min-h-screen w-full max-w-4xl items-center px-4 py-10 sm:px-6">
        <section class="w-full rounded-[2rem] border border-border bg-card p-6 shadow-sm sm:p-8">
            <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                <ClipboardList class="size-4" />
                {{ t.forms.public_page_title }}
            </div>

            <div class="mt-4">
                <Heading
                    variant="small"
                    :title="props.form.name"
                    :description="props.form.description || t.forms.public_page_description"
                />
            </div>

            <form class="mt-8 space-y-6" @submit.prevent="submit">
                <div
                    v-for="field in props.form.fields"
                    :key="field.id"
                    class="grid gap-2"
                >
                    <Label :for="field.key">
                        {{ field.label }}
                        <span v-if="field.is_required" class="text-primary">*</span>
                    </Label>

                    <textarea
                        v-if="field.type === 'textarea'"
                        :id="field.key"
                        v-model="form.values[field.key]"
                        rows="5"
                        class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                        :placeholder="field.placeholder || ''"
                    ></textarea>

                    <Input
                        v-else
                        :id="field.key"
                        v-model="form.values[field.key]"
                        :type="field.type === 'number' ? 'number' : field.type"
                        :placeholder="field.placeholder || ''"
                    />

                    <InputError :message="fieldError(field.key)" />
                </div>

                <Button type="submit" size="lg" :disabled="form.processing" class="w-full sm:w-auto">
                    <Send class="size-4" />
                    {{ t.forms.submit }}
                </Button>
            </form>
        </section>
    </div>
</template>

<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ClipboardList, Send } from '@lucide/vue';
import { computed, ref } from 'vue';
import { submit as submitPortalForm } from '@/actions/App/Http/Controllers/PublicPortalFormController';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import {
    buildPortalFormBadgeStyle,
    buildPortalFormButtonStyle,
    buildPortalFormCardStyle,
    buildPortalFormInputStyle,
    buildPortalFormMutedTextStyle,
    portalFormWidthClass,
} from '@/lib/portalFormStyles';
import type { PortalFormPublicItem } from '@/types/ui';

const props = defineProps<{
    form: PortalFormPublicItem;
}>();

const { t } = useLanguage();

const defaultValues = computed<Record<string, string>>(() => {
    return Object.fromEntries(props.form.fields.map((field) => [field.key, '']));
});

const submissionForm = useForm({
    values: defaultValues.value,
});

const formWidthClass = computed(() => {
    return portalFormWidthClass(props.form.style_settings.container_width);
});

const sectionStyle = computed(() => {
    return buildPortalFormCardStyle(props.form.style_settings);
});

const badgeStyle = computed(() => {
    return buildPortalFormBadgeStyle(props.form.style_settings);
});

const inputStyle = computed(() => {
    return buildPortalFormInputStyle(props.form.style_settings);
});

const buttonStyle = computed(() => {
    return buildPortalFormButtonStyle(props.form.style_settings);
});

const mutedTextStyle = computed(() => {
    return buildPortalFormMutedTextStyle(props.form.style_settings);
});

const submittedMessage = ref<string | null>(null);
const closeFallbackVisible = ref(false);

const fieldError = (key: string): string | undefined => {
    return submissionForm.errors[`values.${key}`];
};

const submit = (): void => {
    submissionForm.post(submitPortalForm.url(props.form.public_token), {
        preserveScroll: true,
        onSuccess: () => {
            const completionSettings = props.form.completion_settings;

            if (completionSettings.action === 'redirect' && completionSettings.redirect_url) {
                window.location.assign(completionSettings.redirect_url);

                return;
            }

            if (completionSettings.action === 'close') {
                window.close();
                closeFallbackVisible.value = true;

                return;
            }

            submittedMessage.value = completionSettings.success_message || t.value.forms.submitted_success;
            closeFallbackVisible.value = false;
            submissionForm.values = defaultValues.value;
        },
    });
};
</script>

<template>
    <Head :title="props.form.name" />

    <div class="mx-auto flex min-h-screen w-full items-center px-4 py-10 sm:px-6">
        <div class="mx-auto w-full" :class="formWidthClass">
            <section class="w-full border shadow-sm" :style="sectionStyle">
                <div
                    class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium"
                    :style="badgeStyle"
                >
                    <ClipboardList class="size-4" />
                    {{ t.forms.public_page_title }}
                </div>

                <div class="mt-4">
                    <h1 class="text-2xl font-semibold">
                        {{ props.form.name }}
                    </h1>
                    <p class="mt-2 text-sm" :style="mutedTextStyle">
                        {{ props.form.description || t.forms.public_page_description }}
                    </p>
                </div>

                <div
                    v-if="submittedMessage || closeFallbackVisible"
                    class="mt-8 space-y-4 rounded-2xl border px-5 py-6"
                    :style="inputStyle"
                >
                    <h2 class="text-lg font-semibold">
                        {{
                            closeFallbackVisible
                                ? t.forms.completion_close_title
                                : t.forms.submitted_success
                        }}
                    </h2>
                    <p class="text-sm whitespace-pre-wrap" :style="mutedTextStyle">
                        {{
                            closeFallbackVisible
                                ? t.forms.completion_close_fallback
                                : submittedMessage
                        }}
                    </p>
                </div>

                <form v-else class="mt-8 space-y-6" @submit.prevent="submit">
                    <div
                        v-for="field in props.form.fields"
                        :key="field.id"
                        class="grid gap-2"
                    >
                        <Label :for="field.key">
                            {{ field.label }}
                            <span v-if="field.is_required" class="opacity-75">*</span>
                        </Label>

                        <textarea
                            v-if="field.type === 'textarea'"
                            :id="field.key"
                            v-model="submissionForm.values[field.key]"
                            rows="5"
                            class="w-full border px-3 py-3 text-sm shadow-xs outline-none transition placeholder:opacity-70 focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            :style="inputStyle"
                            :placeholder="field.placeholder || ''"
                        ></textarea>

                        <Input
                            v-else
                            :id="field.key"
                            v-model="submissionForm.values[field.key]"
                            :type="field.type === 'number' ? 'number' : field.type"
                            :placeholder="field.placeholder || ''"
                            :style="inputStyle"
                            class="h-11 border px-3"
                        />

                        <InputError :message="fieldError(field.key)" />
                    </div>

                    <p class="text-sm" :style="mutedTextStyle">
                        {{ t.forms.public_page_description }}
                    </p>

                    <button
                        type="submit"
                        class="inline-flex h-11 w-full items-center justify-center gap-2 border px-4 text-sm font-medium shadow-xs transition sm:w-auto"
                        :style="buttonStyle"
                        :disabled="submissionForm.processing"
                    >
                        <Send class="size-4" />
                        {{ t.forms.submit }}
                    </button>
                </form>
            </section>
        </div>
    </div>
</template>

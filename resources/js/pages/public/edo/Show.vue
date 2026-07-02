<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Download, FileText, ShieldCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import type { EdoDocumentPublicItem } from '@/types/ui';

type NcaLayerBridgeResult = {
    signature: string;
    subject?: string;
    serialNumber?: string;
    algorithm?: string;
    metadata?: Record<string, unknown>;
};

type NcaLayerBridge = {
    signXml: (payload: {
        xml: string;
        title?: string;
    }) => Promise<NcaLayerBridgeResult>;
};

declare global {
    interface Window {
        CRM369_NCALayer?: NcaLayerBridge;
    }
}

const props = defineProps<{
    document: EdoDocumentPublicItem;
}>();

const { t } = useLanguage();
const ncaError = ref<string | null>(null);

const signatureForm = useForm({
    signature_payload: '',
    signature_subject: '',
    signature_serial_number: '',
    signature_algorithm: '',
    signed_payload_hash: props.document.signed_payload_hash,
});

const pageTitle = computed(() => {
    if (props.document.state === 'signed') {
        return t.value.edo.public.signed_title;
    }

    if (props.document.state === 'expired') {
        return t.value.edo.public.expired_title;
    }

    return t.value.edo.public.ready_title;
});

const pageDescription = computed(() => {
    if (props.document.state === 'signed') {
        return t.value.edo.public.signed_description;
    }

    if (props.document.state === 'expired') {
        return t.value.edo.public.expired_description;
    }

    return t.value.edo.public.ready_description;
});

const formatDate = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
};

const signWithNcaLayer = async (): Promise<void> => {
    ncaError.value = null;

    if (!window.CRM369_NCALayer?.signXml) {
        ncaError.value = t.value.edo.public.nca_unavailable;

        return;
    }

    try {
        const result = await window.CRM369_NCALayer.signXml({
            xml: props.document.sign_payload_xml,
            title: props.document.title,
        });

        signatureForm.signature_payload = result.signature;
        signatureForm.signature_subject = result.subject ?? '';
        signatureForm.signature_serial_number = result.serialNumber ?? '';
        signatureForm.signature_algorithm = result.algorithm ?? '';
    } catch (error) {
        ncaError.value = error instanceof Error ? error.message : t.value.edo.public.nca_unavailable;
    }
};

const submit = (): void => {
    if (!props.document.submit_url) {
        return;
    }

    signatureForm.post(props.document.submit_url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="props.document.title" />

    <div class="mx-auto flex min-h-screen w-full items-center px-4 py-10 sm:px-6">
        <div class="mx-auto w-full max-w-5xl space-y-6">
            <section class="rounded-3xl border border-border bg-card p-6">
                <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                    <ShieldCheck class="size-4" />
                    {{ t.edo.public.title }}
                </div>

                <div class="mt-4 space-y-2">
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ props.document.title }}
                    </h1>
                    <p class="text-sm leading-6 text-muted-foreground">
                        {{ pageDescription }}
                    </p>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                <div class="rounded-3xl border border-border bg-card p-6">
                    <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                        <FileText class="size-4" />
                        {{ pageTitle }}
                    </div>

                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <div class="rounded-2xl border border-border bg-background/70 p-4">
                            <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                {{ t.edo.reference_label }}
                            </div>
                            <div class="mt-1 font-medium">
                                {{ props.document.external_reference || '—' }}
                            </div>
                        </div>

                        <div class="rounded-2xl border border-border bg-background/70 p-4">
                            <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                {{ t.edo.counterparty_name_label }}
                            </div>
                            <div class="mt-1 font-medium">
                                {{ props.document.counterparty_name }}
                            </div>
                        </div>

                        <div class="rounded-2xl border border-border bg-background/70 p-4">
                            <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                {{ t.edo.counterparty_identifier_label }}
                            </div>
                            <div class="mt-1 font-medium">
                                {{ props.document.counterparty_identifier || '—' }}
                            </div>
                        </div>

                        <div class="rounded-2xl border border-border bg-background/70 p-4">
                            <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                {{ t.edo.public_link_expires_at }}
                            </div>
                            <div class="mt-1 font-medium">
                                {{ formatDate(props.document.public_link_expires_at) }}
                            </div>
                        </div>

                        <div class="rounded-2xl border border-border bg-background/70 p-4">
                            <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                {{ t.edo.signed_at }}
                            </div>
                            <div class="mt-1 font-medium">
                                {{ formatDate(props.document.signed_at) }}
                            </div>
                        </div>
                    </div>

                    <div v-if="props.document.document_file" class="mt-6 rounded-3xl border border-border bg-background/70 p-5">
                        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                            <div>
                                <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                    {{ t.edo.document_attached_label }}
                                </div>
                                <div class="mt-2 font-medium">{{ props.document.document_file.original_name }}</div>
                                <div class="mt-1 text-sm text-muted-foreground">
                                    {{ props.document.document_file.mime_type || 'file' }} • {{ props.document.document_file.size_bytes || 0 }} B
                                </div>
                            </div>

                            <a
                                v-if="props.document.document_file_download_url"
                                :href="props.document.document_file_download_url"
                                class="inline-flex h-11 items-center justify-center gap-2 rounded-md border border-input px-4 text-sm font-medium"
                            >
                                <Download class="size-4" />
                                {{ t.edo.document_download }}
                            </a>
                        </div>
                    </div>

                    <div v-if="props.document.document_source === 'text'" class="mt-6 rounded-3xl border border-border bg-background/70 p-5">
                        <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                            {{ t.edo.content_label }}
                        </div>
                        <div class="mt-4 whitespace-pre-wrap text-sm leading-7">
                            {{ props.document.content }}
                        </div>
                    </div>
                </div>

                <div class="rounded-3xl border border-border bg-card p-6">
                    <div class="space-y-4">
                        <Heading
                            variant="small"
                            :title="t.edo.public.sign_button"
                            :description="t.edo.public.description"
                        />

                        <div v-if="ncaError" class="rounded-2xl border border-amber-500/30 bg-amber-500/8 px-4 py-3 text-sm text-amber-800">
                            {{ ncaError }}
                        </div>

                        <div v-if="props.document.state === 'ready'" class="space-y-4">
                            <button
                                type="button"
                                class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-md border border-input px-4 text-sm font-medium"
                                @click="signWithNcaLayer"
                            >
                                <ShieldCheck class="size-4" />
                                {{ t.edo.public.sign_button }}
                            </button>

                            <form class="space-y-4" @submit.prevent="submit">
                                <div class="grid gap-2">
                                    <Label for="signature-payload">{{ t.edo.public.manual_signature_label }}</Label>
                                    <textarea
                                        id="signature-payload"
                                        v-model="signatureForm.signature_payload"
                                        rows="7"
                                        class="rounded-xl border border-input bg-transparent px-3 py-3 text-sm shadow-xs outline-none transition focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                        :placeholder="t.edo.public.manual_signature_placeholder"
                                    ></textarea>
                                    <InputError :message="signatureForm.errors.signature_payload" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="signature-subject">{{ t.edo.public.manual_subject_label }}</Label>
                                    <Input id="signature-subject" v-model="signatureForm.signature_subject" />
                                    <InputError :message="signatureForm.errors.signature_subject" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="signature-serial">{{ t.edo.public.manual_serial_label }}</Label>
                                    <Input id="signature-serial" v-model="signatureForm.signature_serial_number" />
                                    <InputError :message="signatureForm.errors.signature_serial_number" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="signature-algorithm">{{ t.edo.public.manual_algorithm_label }}</Label>
                                    <Input id="signature-algorithm" v-model="signatureForm.signature_algorithm" />
                                    <InputError :message="signatureForm.errors.signature_algorithm" />
                                </div>

                                <button
                                    type="submit"
                                    class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground"
                                    :disabled="signatureForm.processing"
                                >
                                    {{ t.edo.public.submit_button }}
                                </button>
                            </form>
                        </div>

                        <div v-else class="rounded-2xl border border-border bg-background/70 px-4 py-5 text-sm text-muted-foreground">
                            {{ pageDescription }}
                        </div>

                        <div v-if="props.document.signature_subject" class="rounded-2xl border border-border bg-background/70 px-4 py-5 text-sm">
                            <div class="space-y-2">
                                <div>
                                    <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                        {{ t.edo.signature_subject }}
                                    </div>
                                    <div class="mt-1 break-words font-medium">
                                        {{ props.document.signature_subject }}
                                    </div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                        {{ t.edo.signature_algorithm }}
                                    </div>
                                    <div class="mt-1 font-medium">
                                        {{ props.document.signature_algorithm || '—' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

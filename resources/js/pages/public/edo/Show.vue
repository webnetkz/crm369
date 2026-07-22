<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { Download, FileText, LoaderCircle, ShieldCheck } from '@lucide/vue';
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import { useLanguage } from '@/composables/useLanguage';
import { NcaLayerError, signXmlWithNcaLayer } from '@/lib/ncaLayer';
import type { EdoDocumentPublicItem } from '@/types/ui';

const props = defineProps<{
    document: EdoDocumentPublicItem;
}>();

const { language, t } = useLanguage();
const ncaError = ref<string | null>(null);
const signingWithNcaLayer = ref(false);

const signatureForm = useForm({
    signature_payload: '',
    signed_payload_hash: props.document.signed_payload_hash,
});

const pageDescription = computed(() => {
    if (props.document.state === 'signed') {
        return t.value.edo.public.signed_description;
    }

    if (props.document.state === 'expired') {
        return t.value.edo.public.expired_description;
    }

    return '';
});

const ncaLayerErrorMessage = (error: unknown): string => {
    if (!(error instanceof NcaLayerError)) {
        return t.value.edo.public.nca_signing_failed;
    }

    if (error.code === 'cancelled') {
        return t.value.edo.public.nca_cancelled;
    }

    if (error.code === 'connection') {
        return t.value.edo.public.nca_unavailable;
    }

    return t.value.edo.public.nca_signing_failed;
};

const signWithNcaLayer = async (): Promise<void> => {
    if (
        !props.document.submit_url ||
        signingWithNcaLayer.value ||
        signatureForm.processing
    ) {
        return;
    }

    ncaError.value = null;
    signatureForm.clearErrors();
    signingWithNcaLayer.value = true;

    try {
        signatureForm.signature_payload = await signXmlWithNcaLayer(
            props.document.sign_payload_xml,
            language.value,
        );
        signatureForm.post(props.document.submit_url, {
            preserveScroll: true,
            onError: () => {
                ncaError.value = t.value.edo.public.signature_submission_failed;
            },
            onFinish: () => {
                signingWithNcaLayer.value = false;
            },
        });
    } catch (error) {
        signingWithNcaLayer.value = false;
        ncaError.value = ncaLayerErrorMessage(error);
    }
};
</script>

<template>
    <Head :title="props.document.title" />

    <div
        class="flex min-h-screen w-full items-center bg-muted/30 px-4 py-10 sm:px-6"
    >
        <main
            class="mx-auto w-full max-w-3xl overflow-hidden rounded-3xl border border-border bg-card shadow-sm"
        >
            <header
                class="flex items-center gap-3 border-b border-border px-6 py-5 sm:px-8"
            >
                <div
                    class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary"
                >
                    <FileText class="size-5" />
                </div>
                <h1 class="text-xl font-semibold tracking-tight sm:text-2xl">
                    {{ props.document.title }}
                </h1>
            </header>

            <div class="space-y-6 p-6 sm:p-8">
                <a
                    v-if="
                        props.document.document_file &&
                        props.document.document_file_download_url
                    "
                    :href="props.document.document_file_download_url"
                    download
                    class="flex items-center justify-between gap-4 rounded-2xl border border-border bg-background px-5 py-4 font-medium transition-colors hover:bg-accent hover:text-accent-foreground"
                >
                    <span class="min-w-0 truncate">{{
                        props.document.document_file.original_name
                    }}</span>
                    <Download class="size-5 shrink-0" />
                </a>

                <article
                    v-else-if="props.document.document_source === 'text'"
                    class="text-base leading-8 whitespace-pre-wrap text-foreground"
                >
                    {{ props.document.content }}
                </article>

                <div
                    v-if="ncaError"
                    class="rounded-2xl border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive"
                >
                    {{ ncaError }}
                </div>

                <Button
                    v-if="props.document.state === 'ready'"
                    type="button"
                    size="lg"
                    class="h-12 w-full text-base"
                    :disabled="signingWithNcaLayer || signatureForm.processing"
                    @click="signWithNcaLayer"
                >
                    <LoaderCircle
                        v-if="signingWithNcaLayer || signatureForm.processing"
                        class="size-5 animate-spin"
                    />
                    <ShieldCheck v-else class="size-5" />
                    {{
                        signingWithNcaLayer || signatureForm.processing
                            ? t.edo.public.signing_progress
                            : t.edo.public.sign_button
                    }}
                </Button>

                <p
                    v-else
                    class="rounded-2xl bg-muted px-4 py-3 text-sm text-muted-foreground"
                >
                    {{ pageDescription }}
                </p>
            </div>
        </main>
    </div>
</template>

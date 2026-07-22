<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { Copy, Download, ExternalLink, FileText, PenLine, Plus, ShieldCheck, Trash2, Upload } from '@lucide/vue';
import { computed, ref, watch, watchEffect } from 'vue';
import { destroy as destroyEdoDocument, index as edoIndex, issuePublicLink, store as storeEdoDocument, update as updateEdoDocument } from '@/actions/App/Http/Controllers/EdoDocumentController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import type { EdoAvailableFileItem, EdoDocumentActiveItem, EdoDocumentListItem, EdoDocumentSource } from '@/types/ui';

const props = defineProps<{
    documents: EdoDocumentListItem[];
    activeDocument: EdoDocumentActiveItem | null;
    availableFiles: EdoAvailableFileItem[];
    defaults: {
        title: string;
        external_reference: string;
        counterparty_name: string;
        counterparty_identifier: string;
        document_source: EdoDocumentSource;
        selected_file_entry_id: number | null;
        content: string;
    };
}>();

const { t } = useLanguage();
const editorMode = ref<'create' | 'edit'>(props.activeDocument ? 'edit' : 'create');
const copiedLinkId = ref<number | null>(null);

const buildDefaults = (document: EdoDocumentActiveItem | null) => ({
    title: document?.title ?? props.defaults.title,
    external_reference: document?.external_reference ?? props.defaults.external_reference,
    counterparty_name: document?.counterparty_name ?? props.defaults.counterparty_name,
    counterparty_identifier: document?.counterparty_identifier ?? props.defaults.counterparty_identifier,
    document_source: document?.document_source ?? props.defaults.document_source,
    selected_file_entry_id: props.defaults.selected_file_entry_id,
    document_upload: null as File | null,
    content: document?.content ?? props.defaults.content,
});

const form = useForm(buildDefaults(props.activeDocument));

const activeDocumentStatusLabel = computed(() => {
    if (!props.activeDocument) {
        return null;
    }

    return t.value.edo.statuses[props.activeDocument.status];
});

const statusBadgeClass = (status: EdoDocumentListItem['status']): string => {
    if (status === 'signed') {
        return 'bg-emerald-500/10 text-emerald-700';
    }

    if (status === 'pending_signature') {
        return 'bg-amber-500/10 text-amber-700';
    }

    if (status === 'cancelled') {
        return 'bg-rose-500/10 text-rose-700';
    }

    return 'bg-muted text-muted-foreground';
};

const formatDate = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
};

const selectedFileEntryValue = computed({
    get: () => (form.selected_file_entry_id ? String(form.selected_file_entry_id) : ''),
    set: (value: string) => {
        form.selected_file_entry_id = value === '' ? null : Number(value);
    },
});

const selectedAvailableFile = computed(() => {
    if (!form.selected_file_entry_id) {
        return null;
    }

    return props.availableFiles.find((file) => file.id === form.selected_file_entry_id) ?? null;
});

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.edo.title,
                href: edoIndex(),
            },
        ],
    });
});

watch(
    () => props.activeDocument,
    (document) => {
        editorMode.value = document ? 'edit' : 'create';
        form.defaults(buildDefaults(document));
        form.reset();
        form.clearErrors();
    },
);

const startCreate = (): void => {
    editorMode.value = 'create';
    form.defaults(buildDefaults(null));
    form.reset();
    form.clearErrors();
    router.visit(edoIndex({ query: { create: true } }), {
        preserveScroll: true,
    });
};

const setDocumentSource = (source: EdoDocumentSource): void => {
    form.document_source = source;

    if (source !== 'upload') {
        form.document_upload = null;
    }

    if (source !== 'file_entry') {
        form.selected_file_entry_id = null;
    }
};

const onDocumentUploadChange = (event: Event): void => {
    const target = event.target as HTMLInputElement;
    form.document_upload = target.files?.[0] ?? null;
};

const submit = (): void => {
    if (editorMode.value === 'edit' && props.activeDocument) {
        form.transform((data) => ({
            ...data,
            _method: 'patch',
        })).post(updateEdoDocument.url(props.activeDocument.id), {
            preserveScroll: true,
            onFinish: () => form.transform((data) => data),
        });

        return;
    }

    form.post(storeEdoDocument.url(), {
        preserveScroll: true,
    });
};

const removeDocument = (): void => {
    if (!props.activeDocument || !confirm(props.activeDocument.title)) {
        return;
    }

    router.delete(destroyEdoDocument.url(props.activeDocument.id), {
        preserveScroll: true,
    });
};

const generateLink = (): void => {
    if (!props.activeDocument) {
        return;
    }

    router.post(issuePublicLink.url(props.activeDocument.id), {}, {
        preserveScroll: true,
    });
};

const copyLink = async (document: EdoDocumentListItem): Promise<void> => {
    if (!document.public_sign_url) {
        return;
    }

    await navigator.clipboard.writeText(document.public_sign_url);
    copiedLinkId.value = document.id;

    window.setTimeout(() => {
        if (copiedLinkId.value === document.id) {
            copiedLinkId.value = null;
        }
    }, 1500);
};
</script>

<template>
    <Head :title="t.edo.title" />

    <h1 class="sr-only">{{ t.edo.title }}</h1>

    <div class="space-y-8">
        <section class="rounded-3xl border border-border bg-card p-6">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="space-y-3">
                    <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                        <ShieldCheck class="size-4" />
                        {{ t.edo.eyebrow }}
                    </div>

                    <Heading variant="small" :title="t.edo.title" :description="t.edo.description" />
                </div>

                <Button type="button" class="gap-2" @click="startCreate">
                    <Plus class="size-4" />
                    {{ t.edo.create }}
                </Button>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-[340px_minmax(0,1fr)]">
            <div class="space-y-4">
                <Heading variant="small" :title="t.edo.list_title" :description="t.edo.list_description" />

                <div v-if="documents.length === 0" class="rounded-3xl border border-dashed border-border bg-card p-6 text-sm text-muted-foreground">
                    {{ t.edo.empty }}
                </div>

                <div v-else class="space-y-3">
                    <Link
                        v-for="document in documents"
                        :key="document.id"
                        :href="edoIndex({ query: { document: document.id } })"
                        class="block rounded-3xl border p-4 transition-colors"
                        :class="
                            document.id === activeDocument?.id
                                ? 'border-primary/30 bg-primary/5'
                                : 'border-border bg-card hover:border-primary/20 hover:bg-primary/3'
                        "
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 space-y-2">
                                <div class="flex items-center gap-2">
                                    <FileText class="size-4 text-muted-foreground" />
                                    <h2 class="truncate font-medium">{{ document.title }}</h2>
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{ document.counterparty_name }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ document.counterparty_identifier || '—' }}
                                </p>
                            </div>

                            <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="statusBadgeClass(document.status)">
                                {{ t.edo.statuses[document.status] }}
                            </span>
                        </div>
                    </Link>
                </div>
            </div>

            <div class="space-y-6">
                <section class="rounded-3xl border border-border bg-card p-6">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div class="space-y-2">
                            <Heading
                                variant="small"
                                :title="editorMode === 'edit' ? t.edo.editor_edit_title : t.edo.editor_create_title"
                                :description="t.edo.editor_description"
                            />
                            <p v-if="activeDocumentStatusLabel" class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium" :class="statusBadgeClass(activeDocument!.status)">
                                {{ activeDocumentStatusLabel }}
                            </p>
                        </div>

                        <div v-if="activeDocument" class="flex flex-wrap gap-2">
                            <a
                                v-if="activeDocument.document_file?.download_url"
                                :href="activeDocument.document_file.download_url"
                                class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-input px-4 text-sm font-medium"
                            >
                                <Download class="size-4" />
                                {{ t.edo.document_download }}
                            </a>

                            <Button
                                v-if="!activeDocument.signed_at"
                                type="button"
                                variant="outline"
                                class="gap-2"
                                @click="generateLink"
                            >
                                <PenLine class="size-4" />
                                {{ t.edo.create_public_link }}
                            </Button>

                            <Button type="button" variant="outline" class="gap-2 text-destructive" @click="removeDocument">
                                <Trash2 class="size-4" />
                                {{ t.edo.delete }}
                            </Button>
                        </div>
                    </div>

                    <form class="mt-6 space-y-5" @submit.prevent="submit">
                        <div class="grid gap-5 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label for="edo-title">{{ t.edo.title_label }}</Label>
                                <Input id="edo-title" v-model="form.title" :placeholder="t.edo.title_placeholder" />
                                <InputError :message="form.errors.title" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edo-reference">{{ t.edo.reference_label }}</Label>
                                <Input id="edo-reference" v-model="form.external_reference" :placeholder="t.edo.reference_placeholder" />
                                <InputError :message="form.errors.external_reference" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edo-counterparty-name">{{ t.edo.counterparty_name_label }}</Label>
                                <Input id="edo-counterparty-name" v-model="form.counterparty_name" :placeholder="t.edo.counterparty_name_placeholder" />
                                <InputError :message="form.errors.counterparty_name" />
                            </div>

                            <div class="grid gap-2">
                                <Label for="edo-counterparty-identifier">{{ t.edo.counterparty_identifier_label }}</Label>
                                <Input
                                    id="edo-counterparty-identifier"
                                    v-model="form.counterparty_identifier"
                                    :placeholder="t.edo.counterparty_identifier_placeholder"
                                    inputmode="numeric"
                                    maxlength="12"
                                />
                                <InputError :message="form.errors.counterparty_identifier" />
                            </div>
                        </div>

                        <div class="grid gap-3">
                            <Label>{{ t.edo.document_source_label }}</Label>
                            <div class="grid gap-3 md:grid-cols-3">
                                <button
                                    type="button"
                                    class="rounded-2xl border px-4 py-3 text-left text-sm transition-colors"
                                    :class="form.document_source === 'upload' ? 'border-primary bg-primary/5' : 'border-border bg-background/70'"
                                    @click="setDocumentSource('upload')"
                                >
                                    <div class="font-medium">{{ t.edo.document_source_upload }}</div>
                                    <div class="mt-1 text-xs text-muted-foreground">{{ t.edo.document_upload_hint }}</div>
                                </button>

                                <button
                                    type="button"
                                    class="rounded-2xl border px-4 py-3 text-left text-sm transition-colors"
                                    :class="form.document_source === 'file_entry' ? 'border-primary bg-primary/5' : 'border-border bg-background/70'"
                                    @click="setDocumentSource('file_entry')"
                                >
                                    <div class="font-medium">{{ t.edo.document_source_existing }}</div>
                                    <div class="mt-1 text-xs text-muted-foreground">
                                        {{ props.availableFiles.length }}
                                    </div>
                                </button>

                                <button
                                    type="button"
                                    class="rounded-2xl border px-4 py-3 text-left text-sm transition-colors"
                                    :class="form.document_source === 'text' ? 'border-primary bg-primary/5' : 'border-border bg-background/70'"
                                    @click="setDocumentSource('text')"
                                >
                                    <div class="font-medium">{{ t.edo.document_source_text }}</div>
                                    <div class="mt-1 text-xs text-muted-foreground">{{ t.edo.content_label }}</div>
                                </button>
                            </div>
                        </div>

                        <div v-if="form.document_source === 'upload'" class="grid gap-2">
                            <Label for="edo-document-upload">{{ t.edo.document_upload_label }}</Label>
                            <label
                                for="edo-document-upload"
                                class="flex cursor-pointer items-center gap-3 rounded-2xl border border-dashed border-border bg-background/70 px-4 py-4 text-sm"
                            >
                                <Upload class="size-4 text-muted-foreground" />
                                <span class="font-medium">
                                    {{ form.document_upload?.name || t.edo.document_upload_label }}
                                </span>
                            </label>
                            <input id="edo-document-upload" type="file" class="sr-only" @change="onDocumentUploadChange" />
                            <p class="text-xs text-muted-foreground">{{ t.edo.document_upload_hint }}</p>
                            <div v-if="form.progress" class="space-y-2">
                                <div class="flex items-center justify-between text-xs text-muted-foreground">
                                    <span>{{ t.edo.document_upload_label }}</span>
                                    <span>{{ Math.round(form.progress.percentage) }}%</span>
                                </div>
                                <div class="h-2 rounded-full bg-muted">
                                    <div class="h-2 rounded-full bg-primary transition-all" :style="{ width: `${form.progress.percentage}%` }"></div>
                                </div>
                            </div>
                            <InputError :message="form.errors.document_upload" />
                        </div>

                        <div v-if="form.document_source === 'file_entry'" class="grid gap-2">
                            <Label for="edo-file-entry">{{ t.edo.document_existing_label }}</Label>
                            <select
                                id="edo-file-entry"
                                v-model="selectedFileEntryValue"
                                class="h-11 rounded-xl border border-input bg-transparent px-3 text-sm shadow-xs outline-none transition focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            >
                                <option value="">{{ t.edo.document_existing_placeholder }}</option>
                                <option v-for="file in props.availableFiles" :key="file.id" :value="String(file.id)">
                                    {{ file.original_name }}{{ file.directory_name ? ` • ${file.directory_name}` : '' }}
                                </option>
                            </select>
                            <p v-if="selectedAvailableFile" class="text-xs text-muted-foreground">
                                {{ selectedAvailableFile.mime_type || 'file' }} • {{ selectedAvailableFile.size_bytes }} B
                            </p>
                            <InputError :message="form.errors.selected_file_entry_id" />
                        </div>

                        <div v-if="form.document_source === 'text'" class="grid gap-2">
                            <Label for="edo-content">{{ t.edo.content_label }}</Label>
                            <textarea
                                id="edo-content"
                                v-model="form.content"
                                rows="12"
                                class="min-h-56 rounded-xl border border-input bg-transparent px-3 py-3 text-sm shadow-xs outline-none transition focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                :placeholder="t.edo.content_placeholder"
                            ></textarea>
                            <InputError :message="form.errors.content" />
                        </div>

                        <div v-if="activeDocument?.document_file" class="rounded-2xl border border-border bg-background/70 p-4 text-sm">
                            <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                {{ t.edo.document_attached_label }}
                            </div>
                            <div class="mt-2 font-medium">{{ activeDocument.document_file.original_name }}</div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                {{ activeDocument.document_file.mime_type || 'file' }} • {{ activeDocument.document_file.size_bytes || 0 }} B
                            </div>
                        </div>

                        <Button type="submit" class="gap-2" :disabled="form.processing">
                            <PenLine class="size-4" />
                            {{ editorMode === 'edit' ? t.edo.save : t.edo.create }}
                        </Button>
                    </form>
                </section>

                <section v-if="activeDocument" class="rounded-3xl border border-border bg-card p-6">
                    <Heading variant="small" :title="t.edo.public_link" :description="t.edo.description" />

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div class="rounded-2xl border border-border bg-background/70 p-4">
                            <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                {{ t.edo.public_link }}
                            </div>
                            <p class="mt-3 break-all text-sm">
                                {{ activeDocument.public_sign_url || '—' }}
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <Button
                                    v-if="activeDocument.public_sign_url"
                                    type="button"
                                    variant="outline"
                                    class="gap-2"
                                    @click="copyLink(activeDocument)"
                                >
                                    <Copy class="size-4" />
                                    {{ copiedLinkId === activeDocument.id ? 'OK' : t.edo.copy_public_link }}
                                </Button>

                                <a
                                    v-if="activeDocument.public_sign_url"
                                    :href="activeDocument.public_sign_url"
                                    target="_blank"
                                    rel="noreferrer"
                                    class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-input px-4 text-sm font-medium"
                                >
                                    <ExternalLink class="size-4" />
                                    {{ t.edo.open_public_link }}
                                </a>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-border bg-background/70 p-4 text-sm">
                            <div class="space-y-3">
                                <div>
                                    <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                        {{ t.edo.public_link_expires_at }}
                                    </div>
                                    <div class="mt-1 font-medium">{{ formatDate(activeDocument.public_sign_expires_at) }}</div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                        {{ t.edo.signed_at }}
                                    </div>
                                    <div class="mt-1 font-medium">{{ formatDate(activeDocument.signed_at) }}</div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                        {{ t.edo.signature_subject }}
                                    </div>
                                    <div class="mt-1 break-words font-medium">{{ activeDocument.signature_subject || '—' }}</div>
                                </div>
                                <div>
                                    <div class="text-xs uppercase tracking-[0.18em] text-muted-foreground">
                                        {{ t.edo.signature_algorithm }}
                                    </div>
                                    <div class="mt-1 font-medium">{{ activeDocument.signature_algorithm || '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </section>
    </div>
</template>

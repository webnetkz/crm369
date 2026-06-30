<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ClipboardList, Copy, ExternalLink, MessageSquareMore, Plus, Save, Send, Trash2 } from '@lucide/vue';
import { computed, watchEffect } from 'vue';
import {
    destroy as destroyPortalForm,
    index as formsIndex,
    store as storePortalForm,
    update as updatePortalForm,
} from '@/actions/App/Http/Controllers/PortalFormController';
import { showWorkspaceTask } from '@/actions/App/Http/Controllers/ProjectController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import { index as chatsIndex } from '@/routes/chats';
import type {
    PortalFormActiveItem,
    PortalFormAvailableUser,
    PortalFormFieldItem,
    PortalFormFieldTypeOption,
    PortalFormListItem,
} from '@/types/ui';

type Props = {
    forms: PortalFormListItem[];
    activeForm: PortalFormActiveItem | null;
    availableUsers: PortalFormAvailableUser[];
    fieldTypes: PortalFormFieldTypeOption[];
    can: {
        create: boolean;
        manageActive: boolean;
    };
};

type EditableField = Omit<PortalFormFieldItem, 'key'> & {
    key: string | null;
};

const props = defineProps<Props>();
const { language, t } = useLanguage();

const blankField = (): EditableField => ({
    id: null,
    key: null,
    label: '',
    type: 'text',
    placeholder: '',
    is_required: true,
    sort_order: 0,
});

const editorMode = useForm({
    value: props.activeForm ? 'edit' as 'create' | 'edit' : 'create' as 'create' | 'edit',
});

const form = useForm({
    name: '',
    description: '',
    submission_mode: 'task' as 'task' | 'chat',
    target_user_id: null as number | null,
    is_active: true,
    fields: [blankField()] as EditableField[],
});

const applyActiveForm = (): void => {
    if (!props.activeForm) {
        editorMode.value = 'create';
        form.reset();
        form.name = '';
        form.description = '';
        form.submission_mode = 'task';
        form.target_user_id = null;
        form.is_active = true;
        form.fields = [blankField()];
        form.clearErrors();

        return;
    }

    editorMode.value = 'edit';
    form.name = props.activeForm.name;
    form.description = props.activeForm.description ?? '';
    form.submission_mode = props.activeForm.submission_mode;
    form.target_user_id = props.activeForm.target_user?.id ?? null;
    form.is_active = props.activeForm.is_active;
    form.fields = props.activeForm.fields.length > 0
        ? props.activeForm.fields.map((field) => ({ ...field }))
        : [blankField()];
    form.clearErrors();
};

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.forms.title,
                href: formsIndex(),
            },
        ],
    });
});

watchEffect(() => {
    applyActiveForm();
});

const submissionModeLabel = computed(() => {
    return form.submission_mode === 'task'
        ? t.value.forms.submission_mode_task
        : t.value.forms.submission_mode_chat;
});

const addField = (): void => {
    form.fields = [...form.fields, blankField()];
};

const removeField = (index: number): void => {
    if (form.fields.length === 1) {
        form.fields = [blankField()];

        return;
    }

    form.fields = form.fields.filter((_, fieldIndex) => fieldIndex !== index);
};

const openCreateForm = (): void => {
    editorMode.value = 'create';
    form.reset();
    form.name = '';
    form.description = '';
    form.submission_mode = 'task';
    form.target_user_id = null;
    form.is_active = true;
    form.fields = [blankField()];
    form.clearErrors();
};

const submit = (): void => {
    if (editorMode.value === 'edit' && props.activeForm) {
        form.patch(updatePortalForm.url(props.activeForm.id), {
            preserveScroll: true,
        });

        return;
    }

    form.post(storePortalForm.url(), {
        preserveScroll: true,
    });
};

const deleteActiveForm = (): void => {
    if (!props.activeForm || !window.confirm(t.value.forms.delete_form_confirm)) {
        return;
    }

    router.delete(destroyPortalForm.url(props.activeForm.id), {
        preserveScroll: true,
    });
};

const copyPublicLink = async (url: string): Promise<void> => {
    if (!navigator.clipboard) {
        return;
    }

    await navigator.clipboard.writeText(url);
};

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat(language.value === 'ru' ? 'ru-RU' : 'en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};
</script>

<template>
    <Head :title="t.forms.title" />

    <div class="grid gap-6 xl:grid-cols-[340px_minmax(0,1fr)]">
        <aside class="space-y-5 rounded-3xl border border-border bg-card p-5">
            <Heading
                variant="small"
                :title="t.forms.list_title"
                :description="t.forms.list_description"
            />

            <Button type="button" class="w-full" @click="openCreateForm">
                <Plus class="size-4" />
                {{ t.forms.new_form }}
            </Button>

            <div
                v-if="props.forms.length === 0"
                class="rounded-2xl border border-dashed border-border bg-muted/20 p-4 text-sm text-muted-foreground"
            >
                {{ t.forms.empty_description }}
            </div>

            <div v-else class="space-y-3">
                <Link
                    v-for="portalForm in props.forms"
                    :key="portalForm.id"
                    :href="formsIndex({ query: { form: portalForm.id } })"
                    class="block rounded-2xl border p-4 transition"
                    :class="
                        props.activeForm?.id === portalForm.id
                            ? 'border-primary/40 bg-primary/5'
                            : 'border-border bg-background/60 hover:border-primary/30 hover:bg-background'
                    "
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold">
                                {{ portalForm.name }}
                            </div>
                            <div class="mt-1 text-xs text-muted-foreground">
                                {{
                                    portalForm.submission_mode === 'task'
                                        ? t.forms.submission_mode_task
                                        : t.forms.submission_mode_chat
                                }}
                            </div>
                        </div>

                        <span
                            class="rounded-full px-2 py-1 text-[11px] font-medium"
                            :class="
                                portalForm.is_active
                                    ? 'bg-primary/10 text-primary'
                                    : 'bg-muted text-muted-foreground'
                            "
                        >
                            {{ portalForm.is_active ? t.forms.is_active : t.forms.inactive }}
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-3 gap-2 text-xs text-muted-foreground">
                        <div>{{ portalForm.fields_count }}</div>
                        <div>{{ portalForm.submissions_count }}</div>
                        <div class="truncate">
                            {{ portalForm.target_user_name }}
                        </div>
                    </div>
                </Link>
            </div>
        </aside>

        <div class="space-y-6">
            <section class="rounded-3xl border border-border bg-card p-6">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-3">
                        <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">
                            <ClipboardList class="size-4" />
                            {{ submissionModeLabel }}
                        </div>
                        <Heading
                            variant="small"
                            :title="editorMode.value === 'edit' ? t.forms.update_form : t.forms.create_form"
                            :description="t.forms.description"
                        />
                    </div>

                    <div v-if="props.activeForm" class="flex flex-wrap gap-2">
                        <Button type="button" variant="outline" @click="copyPublicLink(props.activeForm.public_url)">
                            <Copy class="size-4" />
                            {{ t.forms.copy_link }}
                        </Button>
                        <a :href="props.activeForm.public_url" target="_blank" rel="noreferrer">
                            <Button type="button" variant="outline">
                                <ExternalLink class="size-4" />
                                {{ t.forms.open_public_form }}
                            </Button>
                        </a>
                        <Button type="button" variant="destructive" @click="deleteActiveForm">
                            <Trash2 class="size-4" />
                            {{ t.forms.delete_form }}
                        </Button>
                    </div>
                </div>

                <form class="mt-6 space-y-6" @submit.prevent="submit">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="portal-form-name">{{ t.forms.form_name }}</Label>
                            <Input
                                id="portal-form-name"
                                v-model="form.name"
                                :placeholder="t.forms.form_name_placeholder"
                            />
                            <InputError :message="form.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="portal-form-target">{{ t.forms.target_user }}</Label>
                            <select
                                id="portal-form-target"
                                v-model="form.target_user_id"
                                class="h-11 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none transition focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            >
                                <option :value="null">{{ t.forms.target_user_placeholder }}</option>
                                <option
                                    v-for="availableUser in props.availableUsers"
                                    :key="availableUser.id"
                                    :value="availableUser.id"
                                >
                                    {{ availableUser.name }}
                                </option>
                            </select>
                            <InputError :message="form.errors.target_user_id" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="portal-form-description">{{ t.forms.form_description }}</Label>
                        <textarea
                            id="portal-form-description"
                            v-model="form.description"
                            rows="4"
                            class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none transition focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            :placeholder="t.forms.form_description_placeholder"
                        ></textarea>
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px]">
                        <div class="grid gap-2">
                            <Label for="portal-form-mode">{{ t.forms.submission_mode }}</Label>
                            <select
                                id="portal-form-mode"
                                v-model="form.submission_mode"
                                class="h-11 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none transition focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                            >
                                <option value="task">{{ t.forms.submission_mode_task }}</option>
                                <option value="chat">{{ t.forms.submission_mode_chat }}</option>
                            </select>
                            <InputError :message="form.errors.submission_mode" />
                        </div>

                        <div class="flex items-end">
                            <label class="flex items-center gap-2 text-sm">
                                <Checkbox
                                    :checked="form.is_active"
                                    @update:checked="
                                        (value: boolean | 'indeterminate') =>
                                            (form.is_active = value === true)
                                    "
                                />
                                <span>{{ t.forms.is_active }}</span>
                            </label>
                        </div>
                    </div>

                    <section class="space-y-4 rounded-2xl border border-border bg-background/60 p-5">
                        <div class="flex items-center justify-between gap-3">
                            <Heading
                                variant="small"
                                :title="t.forms.fields_title"
                                :description="t.forms.fields_description"
                            />

                            <Button type="button" variant="outline" size="sm" @click="addField">
                                <Plus class="size-4" />
                                {{ t.forms.add_field }}
                            </Button>
                        </div>

                        <div class="space-y-4">
                            <article
                                v-for="(field, index) in form.fields"
                                :key="field.id ?? `new-${index}`"
                                class="rounded-2xl border border-border bg-card p-4"
                            >
                                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px]">
                                    <div class="grid gap-2">
                                        <Label :for="`field-label-${index}`">{{ t.forms.field_label }}</Label>
                                        <Input
                                            :id="`field-label-${index}`"
                                            v-model="field.label"
                                            :placeholder="t.forms.field_label_placeholder"
                                        />
                                        <InputError :message="form.errors[`fields.${index}.label`]" />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label :for="`field-type-${index}`">{{ t.forms.field_type }}</Label>
                                        <select
                                            :id="`field-type-${index}`"
                                            v-model="field.type"
                                            class="h-11 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs outline-none transition focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]"
                                        >
                                            <option
                                                v-for="fieldType in props.fieldTypes"
                                                :key="fieldType.value"
                                                :value="fieldType.value"
                                            >
                                                {{ fieldType.label }}
                                            </option>
                                        </select>
                                        <InputError :message="form.errors[`fields.${index}.type`]" />
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end">
                                    <div class="grid gap-2">
                                        <Label :for="`field-placeholder-${index}`">{{ t.forms.field_placeholder }}</Label>
                                        <Input
                                            :id="`field-placeholder-${index}`"
                                            v-model="field.placeholder"
                                            :placeholder="t.forms.field_placeholder_placeholder"
                                        />
                                        <InputError :message="form.errors[`fields.${index}.placeholder`]" />
                                    </div>

                                    <div class="flex flex-wrap items-center gap-3">
                                        <label class="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                :checked="field.is_required"
                                                @update:checked="
                                                    (value: boolean | 'indeterminate') =>
                                                        (field.is_required = value === true)
                                                "
                                            />
                                            <span>{{ t.forms.field_required }}</span>
                                        </label>

                                        <Button type="button" variant="ghost" size="sm" @click="removeField(index)">
                                            <Trash2 class="size-4" />
                                            {{ t.forms.remove_field }}
                                        </Button>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </section>

                    <div class="flex flex-wrap gap-3">
                        <Button type="submit" :disabled="form.processing">
                            <Save class="size-4" />
                            {{ editorMode.value === 'edit' ? t.forms.update_form : t.forms.create_form }}
                        </Button>

                        <Button type="button" variant="outline" @click="openCreateForm">
                            {{ t.forms.new_form }}
                        </Button>
                    </div>
                </form>
            </section>

            <section v-if="props.activeForm" class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
                <div class="rounded-3xl border border-border bg-card p-6">
                    <Heading
                        variant="small"
                        :title="t.forms.submissions_title"
                        :description="t.forms.submissions_description"
                    />

                    <div
                        v-if="props.activeForm.submissions.length === 0"
                        class="mt-5 rounded-2xl border border-dashed border-border bg-muted/20 p-4 text-sm text-muted-foreground"
                    >
                        {{ t.forms.no_submissions }}
                    </div>

                    <div v-else class="mt-5 space-y-4">
                        <article
                            v-for="submission in props.activeForm.submissions"
                            :key="submission.id"
                            class="rounded-2xl border border-border bg-background/60 p-4"
                        >
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div>
                                    <div class="text-sm font-semibold">
                                        {{ formatDateTime(submission.created_at) }}
                                    </div>
                                    <div class="mt-1 text-xs text-muted-foreground">
                                        {{ submission.target_user_name }}
                                    </div>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <Link
                                        v-if="submission.project_task_id"
                                        :href="showWorkspaceTask(submission.project_task_id)"
                                    >
                                        <Button type="button" variant="outline" size="sm">
                                            <ClipboardList class="size-4" />
                                            {{ t.forms.delivery_task }}
                                        </Button>
                                    </Link>
                                    <Link
                                        v-if="submission.chat_conversation_id"
                                        :href="chatsIndex({ query: { conversation: submission.chat_conversation_id } })"
                                    >
                                        <Button type="button" variant="outline" size="sm">
                                            <MessageSquareMore class="size-4" />
                                            {{ t.forms.delivery_chat }}
                                        </Button>
                                    </Link>
                                </div>
                            </div>

                            <dl class="mt-4 space-y-3">
                                <div
                                    v-for="row in submission.payload"
                                    :key="`${submission.id}-${row.key}`"
                                    class="rounded-xl border border-border/80 bg-card px-3 py-2"
                                >
                                    <dt class="text-xs font-medium text-muted-foreground">
                                        {{ row.label }}
                                    </dt>
                                    <dd class="mt-1 whitespace-pre-wrap text-sm">
                                        {{ row.value || t.forms.empty_value }}
                                    </dd>
                                </div>
                            </dl>
                        </article>
                    </div>
                </div>

                <aside class="space-y-4 rounded-3xl border border-border bg-card p-6">
                    <Heading
                        variant="small"
                        :title="t.forms.public_link"
                        :description="t.forms.success_title"
                    />

                    <div class="rounded-2xl border border-border bg-background/60 p-4">
                        <div class="text-xs text-muted-foreground">{{ t.forms.owner }}</div>
                        <div class="mt-1 text-sm font-medium">{{ props.activeForm.owner?.name }}</div>
                    </div>

                    <div class="rounded-2xl border border-border bg-background/60 p-4">
                        <div class="text-xs text-muted-foreground">{{ t.forms.delivery_target }}</div>
                        <div class="mt-1 text-sm font-medium">{{ props.activeForm.target_user?.name }}</div>
                    </div>

                    <div class="rounded-2xl border border-border bg-background/60 p-4">
                        <div class="text-xs text-muted-foreground">{{ t.forms.public_link }}</div>
                        <div class="mt-2 break-all text-sm">{{ props.activeForm.public_url }}</div>
                        <div class="mt-4 flex flex-wrap gap-2">
                            <Button type="button" variant="outline" size="sm" @click="copyPublicLink(props.activeForm.public_url)">
                                <Copy class="size-4" />
                                {{ t.forms.copy_link }}
                            </Button>
                            <a :href="props.activeForm.public_url" target="_blank" rel="noreferrer">
                                <Button type="button" variant="outline" size="sm">
                                    <Send class="size-4" />
                                    {{ t.forms.open_public_form }}
                                </Button>
                            </a>
                        </div>
                    </div>
                </aside>
            </section>
        </div>
    </div>
</template>

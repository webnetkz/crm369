<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    ClipboardList,
    Copy,
    ExternalLink,
    MessageSquareMore,
    PencilLine,
    Plus,
    Save,
    Send,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch, watchEffect } from 'vue';
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useLanguage } from '@/composables/useLanguage';
import {
    buildPortalFormBadgeStyle,
    buildPortalFormButtonStyle,
    buildPortalFormCardStyle,
    buildPortalFormInputStyle,
    buildPortalFormMutedTextStyle,
    clonePortalFormStyleSettings,
    portalFormWidthClass,
} from '@/lib/portalFormStyles';
import { index as chatsIndex } from '@/routes/chats';
import type {
    PortalFormActiveItem,
    PortalFormCompletionActionOption,
    PortalFormCompletionSettings,
    PortalFormAvailableUser,
    PortalFormFieldItem,
    PortalFormFieldTypeOption,
    PortalFormListItem,
    PortalFormStyleSettings,
    PortalFormWidthOption,
} from '@/types/ui';

type Props = {
    forms: PortalFormListItem[];
    activeForm: PortalFormActiveItem | null;
    availableUsers: PortalFormAvailableUser[];
    fieldTypes: PortalFormFieldTypeOption[];
    formStyleDefaults: PortalFormStyleSettings;
    formWidthOptions: PortalFormWidthOption[];
    formCompletionDefaults: PortalFormCompletionSettings;
    formCompletionActionOptions: PortalFormCompletionActionOption[];
    can: {
        create: boolean;
        manageActive: boolean;
    };
};

type EditableField = Omit<PortalFormFieldItem, 'key' | 'placeholder'> & {
    key: string | null;
    placeholder: string;
};

type EditableCompletionSettings = Omit<
    PortalFormCompletionSettings,
    'success_message' | 'redirect_url'
> & {
    success_message: string;
    redirect_url: string;
};

type StyleColorKey =
    | 'background_color'
    | 'border_color'
    | 'text_color'
    | 'input_background_color'
    | 'input_border_color'
    | 'button_background_color'
    | 'button_text_color';

type StyleColorField = {
    key: StyleColorKey;
    inputId: string;
    label: string;
    placeholder: string;
};

type StyleColorSection = {
    key: 'surface' | 'inputs' | 'button';
    title: string;
    fields: StyleColorField[];
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

const blankStyleSettings = (): PortalFormStyleSettings => {
    return clonePortalFormStyleSettings(props.formStyleDefaults);
};

const blankCompletionSettings = (): EditableCompletionSettings => {
    return {
        action: props.formCompletionDefaults.action,
        success_message: props.formCompletionDefaults.success_message ?? '',
        redirect_url: props.formCompletionDefaults.redirect_url ?? '',
    };
};

const editableFieldFromItem = (
    field: PortalFormFieldItem | null = null,
): EditableField => ({
    id: field?.id ?? null,
    key: field?.key ?? null,
    label: field?.label ?? '',
    type: field?.type ?? 'text',
    placeholder: field?.placeholder ?? '',
    is_required: field?.is_required ?? true,
    sort_order: field?.sort_order ?? 0,
});

const buildFormDefaults = (
    activeForm: PortalFormActiveItem | null,
): {
    name: string;
    description: string;
    submission_mode: 'task' | 'chat';
    target_user_id: number | string;
    is_active: boolean;
    style_settings: PortalFormStyleSettings;
    completion_settings: EditableCompletionSettings;
    fields: EditableField[];
} => {
    if (!activeForm) {
        return {
            name: '',
            description: '',
            submission_mode: 'task',
            target_user_id: '',
            is_active: true,
            style_settings: blankStyleSettings(),
            completion_settings: blankCompletionSettings(),
            fields: [blankField()],
        };
    }

    return {
        name: activeForm.name,
        description: activeForm.description ?? '',
        submission_mode: activeForm.submission_mode,
        target_user_id: activeForm.target_user?.id ?? '',
        is_active: activeForm.is_active,
        style_settings: clonePortalFormStyleSettings(activeForm.style_settings),
        completion_settings: {
            action: activeForm.completion_settings.action,
            success_message:
                activeForm.completion_settings.success_message ?? '',
            redirect_url: activeForm.completion_settings.redirect_url ?? '',
        },
        fields:
            activeForm.fields.length > 0
                ? activeForm.fields.map((field) => editableFieldFromItem(field))
                : [blankField()],
    };
};

const editorMode = ref<'create' | 'edit'>(props.activeForm ? 'edit' : 'create');
const editorSheetOpen = ref(props.activeForm !== null);
const openingCreateForm = ref(false);
const form = useForm(buildFormDefaults(props.activeForm));

const syncEditorWithActiveForm = (
    activeForm: PortalFormActiveItem | null,
): void => {
    editorMode.value = activeForm ? 'edit' : 'create';
    form.defaults(buildFormDefaults(activeForm));
    form.reset();
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

watch(
    () => props.activeForm,
    (activeForm) => {
        syncEditorWithActiveForm(activeForm);

        if (activeForm) {
            editorSheetOpen.value = true;

            return;
        }

        if (!openingCreateForm.value) {
            editorSheetOpen.value = false;
        }
    },
    { immediate: true },
);

const submissionModeLabel = computed(() => {
    return form.submission_mode === 'task'
        ? t.value.forms.submission_mode_task
        : t.value.forms.submission_mode_chat;
});

const previewFields = computed(() => {
    return form.fields.slice(0, 3);
});

const previewHiddenFieldsCount = computed(() => {
    return Math.max(form.fields.length - previewFields.value.length, 0);
});

const previewWidthClass = computed(() => {
    return portalFormWidthClass(form.style_settings.container_width);
});

const previewCardStyle = computed(() => {
    return buildPortalFormCardStyle(form.style_settings);
});

const previewInputStyle = computed(() => {
    return buildPortalFormInputStyle(form.style_settings);
});

const previewButtonStyle = computed(() => {
    return buildPortalFormButtonStyle(form.style_settings);
});

const previewMutedTextStyle = computed(() => {
    return buildPortalFormMutedTextStyle(form.style_settings);
});

const previewBadgeStyle = computed(() => {
    return buildPortalFormBadgeStyle(form.style_settings);
});

const styleColorSections = computed<StyleColorSection[]>(() => {
    return [
        {
            key: 'surface',
            title: t.value.forms.style_section_surface,
            fields: [
                {
                    key: 'background_color',
                    inputId: 'portal-form-background-color',
                    label: t.value.forms.style_background_color,
                    placeholder: '#FFFFFF',
                },
                {
                    key: 'border_color',
                    inputId: 'portal-form-border-color',
                    label: t.value.forms.style_border_color,
                    placeholder: '#D4D7E1',
                },
                {
                    key: 'text_color',
                    inputId: 'portal-form-text-color',
                    label: t.value.forms.style_text_color,
                    placeholder: '#0F172A',
                },
            ],
        },
        {
            key: 'inputs',
            title: t.value.forms.style_section_inputs,
            fields: [
                {
                    key: 'input_background_color',
                    inputId: 'portal-form-input-background-color',
                    label: t.value.forms.style_input_background_color,
                    placeholder: '#FFFFFF',
                },
                {
                    key: 'input_border_color',
                    inputId: 'portal-form-input-border-color',
                    label: t.value.forms.style_input_border_color,
                    placeholder: '#CBD5E1',
                },
            ],
        },
        {
            key: 'button',
            title: t.value.forms.style_section_button,
            fields: [
                {
                    key: 'button_background_color',
                    inputId: 'portal-form-button-background-color',
                    label: t.value.forms.style_button_background_color,
                    placeholder: '#111827',
                },
                {
                    key: 'button_text_color',
                    inputId: 'portal-form-button-text-color',
                    label: t.value.forms.style_button_text_color,
                    placeholder: '#FFFFFF',
                },
            ],
        },
    ];
});

const isChecked = (
    value: boolean | 'indeterminate' | null | undefined,
): boolean => {
    return value === true;
};

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
    syncEditorWithActiveForm(null);
    editorSheetOpen.value = true;

    if (props.activeForm) {
        openingCreateForm.value = true;

        router.get(
            formsIndex.url(),
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
                onFinish: () => {
                    openingCreateForm.value = false;
                },
            },
        );

        return;
    }
};

const resetStyleSettings = (): void => {
    form.style_settings = blankStyleSettings();
};

const applySubmitTransform = (): void => {
    form.transform((data) => ({
        ...data,
        target_user_id:
            data.target_user_id === '' ? null : Number(data.target_user_id),
        style_settings: {
            container_width: data.style_settings.container_width,
            background_color: data.style_settings.background_color,
            border_color: data.style_settings.border_color,
            text_color: data.style_settings.text_color,
            input_background_color: data.style_settings.input_background_color,
            input_border_color: data.style_settings.input_border_color,
            button_background_color:
                data.style_settings.button_background_color,
            button_text_color: data.style_settings.button_text_color,
            border_radius: Number(data.style_settings.border_radius),
            padding: Number(data.style_settings.padding),
        },
        completion_settings: {
            action: data.completion_settings.action,
            success_message:
                data.completion_settings.success_message.trim() || null,
            redirect_url: data.completion_settings.redirect_url.trim() || null,
        },
        fields: data.fields.map((field, index) => ({
            id: field.id,
            label: field.label,
            type: field.type,
            placeholder: field.placeholder?.trim() ? field.placeholder : null,
            is_required: field.is_required,
            sort_order: (index + 1) * 10,
        })),
    }));
};

const openForm = (formId: number): void => {
    router.get(
        formsIndex.url({ query: { form: formId } }),
        {},
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
};

const handleEditorSheetOpenChange = (open: boolean): void => {
    if (open) {
        editorSheetOpen.value = true;

        return;
    }

    if (props.activeForm) {
        router.get(
            formsIndex.url(),
            {},
            {
                preserveScroll: true,
                preserveState: false,
                replace: true,
            },
        );

        return;
    }

    editorSheetOpen.value = false;
    syncEditorWithActiveForm(null);
};

const submit = (): void => {
    applySubmitTransform();

    if (editorMode.value === 'edit' && props.activeForm) {
        form.patch(updatePortalForm.url(props.activeForm.id), {
            preserveScroll: true,
            preserveState: 'errors',
        });

        return;
    }

    form.post(storePortalForm.url(), {
        preserveScroll: true,
        preserveState: 'errors',
    });
};

const deleteForm = (formId: number): void => {
    if (!window.confirm(t.value.forms.delete_form_confirm)) {
        return;
    }

    router.delete(destroyPortalForm.url(formId), {
        preserveScroll: true,
        preserveState: false,
    });
};

const deleteActiveForm = (): void => {
    if (!props.activeForm) {
        return;
    }

    deleteForm(props.activeForm.id);
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

    return new Intl.DateTimeFormat(
        language.value === 'ru' ? 'ru-RU' : 'en-US',
        {
            dateStyle: 'medium',
            timeStyle: 'short',
        },
    ).format(new Date(value));
};
</script>

<template>
    <Head :title="t.forms.title" />

    <div class="space-y-6">
        <section class="rounded-3xl border border-border bg-card p-6">
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <Heading
                    variant="small"
                    :title="t.forms.list_title"
                    :description="t.forms.list_description"
                />

                <Button type="button" @click="openCreateForm">
                    <Plus class="size-4" />
                    {{ t.forms.new_form }}
                </Button>
            </div>

            <div
                v-if="props.forms.length === 0"
                class="mt-6 rounded-2xl border border-dashed border-border bg-muted/20 p-5 text-sm text-muted-foreground"
            >
                {{ t.forms.empty_description }}
            </div>

            <div v-else class="mt-6 grid gap-4 xl:grid-cols-2 2xl:grid-cols-3">
                <article
                    v-for="portalForm in props.forms"
                    :key="portalForm.id"
                    class="rounded-2xl border p-5 transition"
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
                            {{
                                portalForm.is_active
                                    ? t.forms.is_active
                                    : t.forms.inactive
                            }}
                        </span>
                    </div>

                    <p
                        v-if="portalForm.description"
                        class="mt-3 line-clamp-2 text-sm text-muted-foreground"
                    >
                        {{ portalForm.description }}
                    </p>

                    <div
                        class="mt-4 grid grid-cols-3 gap-3 rounded-2xl border border-border/70 bg-card px-3 py-3 text-xs text-muted-foreground"
                    >
                        <div>
                            <div class="font-medium text-foreground">
                                {{ portalForm.fields_count }}
                            </div>
                            <div>{{ t.forms.fields_title }}</div>
                        </div>
                        <div>
                            <div class="font-medium text-foreground">
                                {{ portalForm.submissions_count }}
                            </div>
                            <div>{{ t.forms.submissions_title }}</div>
                        </div>
                        <div class="truncate">
                            <div class="font-medium text-foreground">
                                {{ portalForm.target_user_name || '—' }}
                            </div>
                            <div>{{ t.forms.delivery_target }}</div>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="copyPublicLink(portalForm.public_url)"
                        >
                            <Copy class="size-4" />
                            {{ t.forms.copy_link }}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="openForm(portalForm.id)"
                        >
                            <PencilLine class="size-4" />
                            {{ t.forms.edit_form }}
                        </Button>
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="deleteForm(portalForm.id)"
                        >
                            <Trash2 class="size-4" />
                            {{ t.forms.delete_form }}
                        </Button>
                    </div>
                </article>
            </div>
        </section>
    </div>

    <Sheet :open="editorSheetOpen" @update:open="handleEditorSheetOpenChange">
        <SheetContent
            side="right"
            class="w-full gap-0 p-0 sm:w-[92vw] sm:max-w-[92vw] xl:max-w-6xl"
        >
            <div class="h-full min-h-0 overflow-y-auto bg-background">
                <div class="mx-auto w-full max-w-6xl p-5 sm:p-8">
                    <div
                        class="rounded-3xl border border-border bg-card shadow-sm"
                    >
                        <SheetHeader
                            class="border-b border-border px-5 py-5 text-left sm:px-6"
                        >
                            <div
                                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                            >
                                <div class="space-y-3">
                                    <div
                                        class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                                    >
                                        <ClipboardList class="size-4" />
                                        {{ submissionModeLabel }}
                                    </div>

                                    <div>
                                        <SheetTitle>
                                            {{
                                                editorMode === 'edit'
                                                    ? t.forms.update_form
                                                    : t.forms.create_form
                                            }}
                                        </SheetTitle>
                                        <SheetDescription class="mt-1">
                                            {{ t.forms.description }}
                                        </SheetDescription>
                                    </div>
                                </div>

                                <div
                                    v-if="
                                        props.activeForm &&
                                        editorMode === 'edit'
                                    "
                                    class="flex flex-wrap gap-2"
                                >
                                    <Button
                                        type="button"
                                        variant="outline"
                                        @click="
                                            copyPublicLink(
                                                props.activeForm.public_url,
                                            )
                                        "
                                    >
                                        <Copy class="size-4" />
                                        {{ t.forms.copy_link }}
                                    </Button>
                                    <a
                                        :href="props.activeForm.public_url"
                                        target="_blank"
                                        rel="noreferrer"
                                    >
                                        <Button type="button" variant="outline">
                                            <ExternalLink class="size-4" />
                                            {{ t.forms.open_public_form }}
                                        </Button>
                                    </a>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        @click="deleteActiveForm"
                                    >
                                        <Trash2 class="size-4" />
                                        {{ t.forms.delete_form }}
                                    </Button>
                                </div>
                            </div>
                        </SheetHeader>

                        <div
                            class="grid gap-6 px-5 py-5 sm:px-6 sm:py-6 xl:grid-cols-[minmax(0,1fr)_360px]"
                        >
                            <div class="space-y-6">
                                <form
                                    class="space-y-6"
                                    @submit.prevent="submit"
                                >
                                    <section
                                        class="space-y-6 rounded-2xl border border-border bg-background/60 p-5"
                                    >
                                        <div class="grid gap-4 lg:grid-cols-2">
                                            <div class="grid gap-2">
                                                <Label for="portal-form-name">{{
                                                    t.forms.form_name
                                                }}</Label>
                                                <Input
                                                    id="portal-form-name"
                                                    v-model="form.name"
                                                    :placeholder="
                                                        t.forms
                                                            .form_name_placeholder
                                                    "
                                                />
                                                <InputError
                                                    :message="form.errors.name"
                                                />
                                            </div>

                                            <div class="grid gap-2">
                                                <Label
                                                    for="portal-form-target"
                                                    >{{
                                                        t.forms.target_user
                                                    }}</Label
                                                >
                                                <select
                                                    id="portal-form-target"
                                                    v-model="
                                                        form.target_user_id
                                                    "
                                                    class="h-11 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                >
                                                    <option value="">
                                                        {{
                                                            t.forms
                                                                .target_user_placeholder
                                                        }}
                                                    </option>
                                                    <option
                                                        v-for="availableUser in props.availableUsers"
                                                        :key="availableUser.id"
                                                        :value="
                                                            availableUser.id
                                                        "
                                                    >
                                                        {{ availableUser.name }}
                                                    </option>
                                                </select>
                                                <InputError
                                                    :message="
                                                        form.errors
                                                            .target_user_id
                                                    "
                                                />
                                            </div>
                                        </div>

                                        <div class="grid gap-2">
                                            <Label
                                                for="portal-form-description"
                                                >{{
                                                    t.forms.form_description
                                                }}</Label
                                            >
                                            <textarea
                                                id="portal-form-description"
                                                v-model="form.description"
                                                rows="4"
                                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                :placeholder="
                                                    t.forms
                                                        .form_description_placeholder
                                                "
                                            ></textarea>
                                            <InputError
                                                :message="
                                                    form.errors.description
                                                "
                                            />
                                        </div>

                                        <div
                                            class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px]"
                                        >
                                            <div class="grid gap-2">
                                                <Label for="portal-form-mode">{{
                                                    t.forms.submission_mode
                                                }}</Label>
                                                <select
                                                    id="portal-form-mode"
                                                    v-model="
                                                        form.submission_mode
                                                    "
                                                    class="h-11 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                >
                                                    <option value="task">
                                                        {{
                                                            t.forms
                                                                .submission_mode_task
                                                        }}
                                                    </option>
                                                    <option value="chat">
                                                        {{
                                                            t.forms
                                                                .submission_mode_chat
                                                        }}
                                                    </option>
                                                </select>
                                                <InputError
                                                    :message="
                                                        form.errors
                                                            .submission_mode
                                                    "
                                                />
                                            </div>

                                            <div class="flex items-end">
                                                <label
                                                    class="flex items-center gap-2 text-sm"
                                                >
                                                    <Checkbox
                                                        :checked="
                                                            form.is_active
                                                        "
                                                        @update:checked="
                                                            (value) =>
                                                                (form.is_active =
                                                                    isChecked(
                                                                        value,
                                                                    ))
                                                        "
                                                    />
                                                    <span>{{
                                                        t.forms.is_active
                                                    }}</span>
                                                </label>
                                            </div>
                                        </div>
                                    </section>

                                    <section
                                        class="space-y-4 rounded-2xl border border-border bg-background/60 p-5"
                                    >
                                        <Heading
                                            variant="small"
                                            :title="t.forms.completion_title"
                                            :description="
                                                t.forms.completion_description
                                            "
                                        />

                                        <div class="grid gap-4 lg:grid-cols-2">
                                            <div class="grid gap-2">
                                                <Label
                                                    for="portal-form-completion-action"
                                                >
                                                    {{
                                                        t.forms
                                                            .completion_action
                                                    }}
                                                </Label>
                                                <select
                                                    id="portal-form-completion-action"
                                                    v-model="
                                                        form.completion_settings
                                                            .action
                                                    "
                                                    class="h-11 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                >
                                                    <option
                                                        v-for="actionOption in props.formCompletionActionOptions"
                                                        :key="
                                                            actionOption.value
                                                        "
                                                        :value="
                                                            actionOption.value
                                                        "
                                                    >
                                                        {{ actionOption.label }}
                                                    </option>
                                                </select>
                                                <InputError
                                                    :message="
                                                        form.errors[
                                                            'completion_settings.action'
                                                        ]
                                                    "
                                                />
                                            </div>
                                        </div>

                                        <div
                                            v-if="
                                                form.completion_settings
                                                    .action === 'message'
                                            "
                                            class="grid gap-2"
                                        >
                                            <Label
                                                for="portal-form-success-message"
                                            >
                                                {{
                                                    t.forms
                                                        .completion_success_message
                                                }}
                                            </Label>
                                            <textarea
                                                id="portal-form-success-message"
                                                v-model="
                                                    form.completion_settings
                                                        .success_message
                                                "
                                                rows="4"
                                                class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                :placeholder="
                                                    t.forms
                                                        .completion_success_message_placeholder
                                                "
                                            ></textarea>
                                            <InputError
                                                :message="
                                                    form.errors[
                                                        'completion_settings.success_message'
                                                    ]
                                                "
                                            />
                                        </div>

                                        <div
                                            v-if="
                                                form.completion_settings
                                                    .action === 'redirect'
                                            "
                                            class="grid gap-2"
                                        >
                                            <Label
                                                for="portal-form-redirect-url"
                                            >
                                                {{
                                                    t.forms
                                                        .completion_redirect_url
                                                }}
                                            </Label>
                                            <Input
                                                id="portal-form-redirect-url"
                                                v-model="
                                                    form.completion_settings
                                                        .redirect_url
                                                "
                                                :placeholder="
                                                    t.forms
                                                        .completion_redirect_url_placeholder
                                                "
                                            />
                                            <p
                                                class="text-sm text-muted-foreground"
                                            >
                                                {{
                                                    t.forms
                                                        .completion_redirect_url_help
                                                }}
                                            </p>
                                            <InputError
                                                :message="
                                                    form.errors[
                                                        'completion_settings.redirect_url'
                                                    ]
                                                "
                                            />
                                        </div>

                                        <p
                                            v-if="
                                                form.completion_settings
                                                    .action === 'close'
                                            "
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ t.forms.completion_close_help }}
                                        </p>
                                    </section>

                                    <section
                                        class="space-y-6 rounded-2xl border border-border bg-background/60 p-5"
                                    >
                                        <div
                                            class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between"
                                        >
                                            <Heading
                                                variant="small"
                                                :title="t.forms.style_title"
                                                :description="
                                                    t.forms.style_description
                                                "
                                            />

                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                @click="resetStyleSettings"
                                            >
                                                {{ t.forms.style_reset }}
                                            </Button>
                                        </div>

                                        <section
                                            class="rounded-2xl border border-border bg-card p-4"
                                        >
                                            <div>
                                                <div
                                                    class="text-sm font-semibold"
                                                >
                                                    {{
                                                        t.forms
                                                            .style_section_layout
                                                    }}
                                                </div>
                                                <p
                                                    class="mt-1 text-sm text-muted-foreground"
                                                >
                                                    {{
                                                        t.forms
                                                            .style_section_layout_description
                                                    }}
                                                </p>
                                            </div>

                                            <div class="mt-4 grid gap-4">
                                                <div class="grid gap-2">
                                                    <Label>{{
                                                        t.forms.style_width
                                                    }}</Label>
                                                    <div
                                                        class="grid gap-2 sm:grid-cols-2 xl:grid-cols-4"
                                                    >
                                                        <button
                                                            v-for="widthOption in props.formWidthOptions"
                                                            :key="
                                                                widthOption.value
                                                            "
                                                            type="button"
                                                            class="rounded-2xl border px-4 py-3 text-left text-sm transition"
                                                            :class="
                                                                form
                                                                    .style_settings
                                                                    .container_width ===
                                                                widthOption.value
                                                                    ? 'border-primary bg-primary/8 text-primary shadow-xs'
                                                                    : 'border-border bg-background hover:border-primary/30 hover:bg-primary/5'
                                                            "
                                                            @click="
                                                                form.style_settings.container_width =
                                                                    widthOption.value
                                                            "
                                                        >
                                                            {{
                                                                widthOption.label
                                                            }}
                                                        </button>
                                                    </div>
                                                    <InputError
                                                        :message="
                                                            form.errors[
                                                                'style_settings.container_width'
                                                            ]
                                                        "
                                                    />
                                                </div>

                                                <div
                                                    class="grid gap-4 md:grid-cols-2"
                                                >
                                                    <div class="grid gap-2">
                                                        <div
                                                            class="flex items-center justify-between gap-3"
                                                        >
                                                            <Label
                                                                for="portal-form-radius"
                                                                >{{
                                                                    t.forms
                                                                        .style_border_radius
                                                                }}</Label
                                                            >
                                                            <span
                                                                class="text-sm text-muted-foreground"
                                                            >
                                                                {{
                                                                    form
                                                                        .style_settings
                                                                        .border_radius
                                                                }}px
                                                            </span>
                                                        </div>
                                                        <Input
                                                            id="portal-form-radius"
                                                            v-model.number="
                                                                form
                                                                    .style_settings
                                                                    .border_radius
                                                            "
                                                            type="range"
                                                            min="12"
                                                            max="32"
                                                            step="1"
                                                        />
                                                        <InputError
                                                            :message="
                                                                form.errors[
                                                                    'style_settings.border_radius'
                                                                ]
                                                            "
                                                        />
                                                    </div>

                                                    <div class="grid gap-2">
                                                        <div
                                                            class="flex items-center justify-between gap-3"
                                                        >
                                                            <Label
                                                                for="portal-form-padding"
                                                                >{{
                                                                    t.forms
                                                                        .style_padding
                                                                }}</Label
                                                            >
                                                            <span
                                                                class="text-sm text-muted-foreground"
                                                            >
                                                                {{
                                                                    form
                                                                        .style_settings
                                                                        .padding
                                                                }}px
                                                            </span>
                                                        </div>
                                                        <Input
                                                            id="portal-form-padding"
                                                            v-model.number="
                                                                form
                                                                    .style_settings
                                                                    .padding
                                                            "
                                                            type="range"
                                                            min="20"
                                                            max="48"
                                                            step="1"
                                                        />
                                                        <InputError
                                                            :message="
                                                                form.errors[
                                                                    'style_settings.padding'
                                                                ]
                                                            "
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </section>

                                        <div class="grid gap-4 xl:grid-cols-3">
                                            <section
                                                v-for="styleSection in styleColorSections"
                                                :key="styleSection.key"
                                                class="rounded-2xl border border-border bg-card p-4"
                                            >
                                                <div
                                                    class="text-sm font-semibold"
                                                >
                                                    {{ styleSection.title }}
                                                </div>

                                                <div class="mt-4 space-y-4">
                                                    <div
                                                        v-for="colorField in styleSection.fields"
                                                        :key="colorField.key"
                                                        class="grid gap-2"
                                                    >
                                                        <Label
                                                            :for="
                                                                colorField.inputId
                                                            "
                                                            >{{
                                                                colorField.label
                                                            }}</Label
                                                        >
                                                        <div
                                                            class="flex flex-col gap-3 sm:flex-row sm:items-center"
                                                        >
                                                            <Input
                                                                :id="
                                                                    colorField.inputId
                                                                "
                                                                v-model="
                                                                    form
                                                                        .style_settings[
                                                                        colorField
                                                                            .key
                                                                    ]
                                                                "
                                                                type="color"
                                                                class="h-12 w-full rounded-xl p-1 sm:w-18"
                                                            />
                                                            <Input
                                                                v-model="
                                                                    form
                                                                        .style_settings[
                                                                        colorField
                                                                            .key
                                                                    ]
                                                                "
                                                                type="text"
                                                                inputmode="text"
                                                                :placeholder="
                                                                    colorField.placeholder
                                                                "
                                                                class="font-mono uppercase"
                                                            />
                                                        </div>
                                                        <InputError
                                                            :message="
                                                                form.errors[
                                                                    `style_settings.${colorField.key}`
                                                                ]
                                                            "
                                                        />
                                                    </div>
                                                </div>
                                            </section>
                                        </div>
                                    </section>

                                    <section
                                        class="space-y-4 rounded-2xl border border-border bg-background/60 p-5"
                                    >
                                        <div
                                            class="flex items-center justify-between gap-3"
                                        >
                                            <Heading
                                                variant="small"
                                                :title="t.forms.fields_title"
                                                :description="
                                                    t.forms.fields_description
                                                "
                                            />

                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                @click="addField"
                                            >
                                                <Plus class="size-4" />
                                                {{ t.forms.add_field }}
                                            </Button>
                                        </div>

                                        <div class="space-y-4">
                                            <article
                                                v-for="(
                                                    field, index
                                                ) in form.fields"
                                                :key="
                                                    field.id ?? `new-${index}`
                                                "
                                                class="rounded-2xl border border-border bg-card p-4"
                                            >
                                                <div
                                                    class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_220px]"
                                                >
                                                    <div class="grid gap-2">
                                                        <Label
                                                            :for="`field-label-${index}`"
                                                            >{{
                                                                t.forms
                                                                    .field_label
                                                            }}</Label
                                                        >
                                                        <Input
                                                            :id="`field-label-${index}`"
                                                            v-model="
                                                                field.label
                                                            "
                                                            :placeholder="
                                                                t.forms
                                                                    .field_label_placeholder
                                                            "
                                                        />
                                                        <InputError
                                                            :message="
                                                                form.errors[
                                                                    `fields.${index}.label`
                                                                ]
                                                            "
                                                        />
                                                    </div>

                                                    <div class="grid gap-2">
                                                        <Label
                                                            :for="`field-type-${index}`"
                                                            >{{
                                                                t.forms
                                                                    .field_type
                                                            }}</Label
                                                        >
                                                        <select
                                                            :id="`field-type-${index}`"
                                                            v-model="field.type"
                                                            class="h-11 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                                        >
                                                            <option
                                                                v-for="fieldType in props.fieldTypes"
                                                                :key="
                                                                    fieldType.value
                                                                "
                                                                :value="
                                                                    fieldType.value
                                                                "
                                                            >
                                                                {{
                                                                    fieldType.label
                                                                }}
                                                            </option>
                                                        </select>
                                                        <InputError
                                                            :message="
                                                                form.errors[
                                                                    `fields.${index}.type`
                                                                ]
                                                            "
                                                        />
                                                    </div>
                                                </div>

                                                <div
                                                    class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-end"
                                                >
                                                    <div class="grid gap-2">
                                                        <Label
                                                            :for="`field-placeholder-${index}`"
                                                            >{{
                                                                t.forms
                                                                    .field_placeholder
                                                            }}</Label
                                                        >
                                                        <Input
                                                            :id="`field-placeholder-${index}`"
                                                            v-model="
                                                                field.placeholder
                                                            "
                                                            :placeholder="
                                                                t.forms
                                                                    .field_placeholder_placeholder
                                                            "
                                                        />
                                                        <InputError
                                                            :message="
                                                                form.errors[
                                                                    `fields.${index}.placeholder`
                                                                ]
                                                            "
                                                        />
                                                    </div>

                                                    <div
                                                        class="flex flex-wrap items-center gap-3"
                                                    >
                                                        <label
                                                            class="flex items-center gap-2 text-sm"
                                                        >
                                                            <Checkbox
                                                                :checked="
                                                                    field.is_required
                                                                "
                                                                @update:checked="
                                                                    (value) =>
                                                                        (field.is_required =
                                                                            isChecked(
                                                                                value,
                                                                            ))
                                                                "
                                                            />
                                                            <span>{{
                                                                t.forms
                                                                    .field_required
                                                            }}</span>
                                                        </label>

                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            @click="
                                                                removeField(
                                                                    index,
                                                                )
                                                            "
                                                        >
                                                            <Trash2
                                                                class="size-4"
                                                            />
                                                            {{
                                                                t.forms
                                                                    .remove_field
                                                            }}
                                                        </Button>
                                                    </div>
                                                </div>
                                            </article>
                                        </div>
                                    </section>

                                    <div
                                        class="flex flex-wrap justify-end gap-3"
                                    >
                                        <Button
                                            type="button"
                                            variant="outline"
                                            @click="
                                                handleEditorSheetOpenChange(
                                                    false,
                                                )
                                            "
                                        >
                                            {{ t.common.cancel }}
                                        </Button>

                                        <Button
                                            type="submit"
                                            :disabled="form.processing"
                                        >
                                            <Save class="size-4" />
                                            {{
                                                editorMode === 'edit'
                                                    ? t.forms.update_form
                                                    : t.forms.create_form
                                            }}
                                        </Button>
                                    </div>
                                </form>
                            </div>

                            <aside class="space-y-4">
                                <div
                                    class="rounded-2xl border border-border bg-card p-4"
                                >
                                    <div
                                        class="flex items-start justify-between gap-3"
                                    >
                                        <div>
                                            <Label>{{
                                                t.forms.style_preview
                                            }}</Label>
                                            <p
                                                class="mt-1 text-sm text-muted-foreground"
                                            >
                                                {{
                                                    t.forms
                                                        .style_preview_description
                                                }}
                                            </p>
                                        </div>
                                        <span
                                            class="rounded-full border border-border bg-background px-3 py-1 text-xs text-muted-foreground"
                                        >
                                            {{ submissionModeLabel }}
                                        </span>
                                    </div>

                                    <div
                                        class="mt-4 rounded-[2rem] border border-dashed border-border bg-muted/20 p-3 sm:p-5"
                                    >
                                        <div
                                            class="mx-auto transition-all duration-200"
                                            :class="previewWidthClass"
                                        >
                                            <section
                                                class="border shadow-sm transition-all duration-200"
                                                :style="previewCardStyle"
                                            >
                                                <div
                                                    class="inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-medium"
                                                    :style="previewBadgeStyle"
                                                >
                                                    <ClipboardList
                                                        class="size-4"
                                                    />
                                                    {{
                                                        t.forms
                                                            .public_page_title
                                                    }}
                                                </div>

                                                <div class="mt-4">
                                                    <h3
                                                        class="text-xl font-semibold"
                                                    >
                                                        {{
                                                            form.name ||
                                                            t.forms
                                                                .form_name_placeholder
                                                        }}
                                                    </h3>
                                                    <p
                                                        class="mt-2 text-sm"
                                                        :style="
                                                            previewMutedTextStyle
                                                        "
                                                    >
                                                        {{
                                                            form.description ||
                                                            t.forms
                                                                .public_page_description
                                                        }}
                                                    </p>
                                                </div>

                                                <div class="mt-6 space-y-4">
                                                    <div
                                                        v-for="(
                                                            field, index
                                                        ) in previewFields"
                                                        :key="
                                                            field.id ??
                                                            `preview-${index}`
                                                        "
                                                        class="space-y-2"
                                                    >
                                                        <div
                                                            class="text-sm font-medium"
                                                        >
                                                            {{
                                                                field.label ||
                                                                t.forms
                                                                    .field_label
                                                            }}
                                                            <span
                                                                v-if="
                                                                    field.is_required
                                                                "
                                                                class="opacity-75"
                                                            >
                                                                *
                                                            </span>
                                                        </div>

                                                        <textarea
                                                            v-if="
                                                                field.type ===
                                                                'textarea'
                                                            "
                                                            :value="
                                                                field.placeholder ||
                                                                t.forms
                                                                    .field_placeholder_placeholder
                                                            "
                                                            rows="4"
                                                            class="w-full border px-3 py-3 text-sm shadow-xs transition outline-none placeholder:opacity-70"
                                                            :style="
                                                                previewInputStyle
                                                            "
                                                            disabled
                                                        ></textarea>

                                                        <input
                                                            v-else
                                                            :value="
                                                                field.placeholder ||
                                                                t.forms
                                                                    .field_placeholder_placeholder
                                                            "
                                                            :type="
                                                                field.type ===
                                                                'number'
                                                                    ? 'text'
                                                                    : field.type
                                                            "
                                                            class="h-11 w-full border px-3 text-sm shadow-xs transition outline-none placeholder:opacity-70"
                                                            :style="
                                                                previewInputStyle
                                                            "
                                                            disabled
                                                        />
                                                    </div>

                                                    <div
                                                        v-if="
                                                            previewHiddenFieldsCount >
                                                            0
                                                        "
                                                        class="text-sm"
                                                        :style="
                                                            previewMutedTextStyle
                                                        "
                                                    >
                                                        {{
                                                            t.forms.style_more_fields.replace(
                                                                ':count',
                                                                String(
                                                                    previewHiddenFieldsCount,
                                                                ),
                                                            )
                                                        }}
                                                    </div>

                                                    <button
                                                        type="button"
                                                        class="inline-flex h-11 items-center justify-center gap-2 border px-4 text-sm font-medium shadow-xs transition"
                                                        :style="
                                                            previewButtonStyle
                                                        "
                                                    >
                                                        <Send class="size-4" />
                                                        {{ t.forms.submit }}
                                                    </button>
                                                </div>
                                            </section>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    class="grid gap-3 sm:grid-cols-3 xl:grid-cols-1"
                                >
                                    <div
                                        v-for="styleSection in styleColorSections"
                                        :key="`${styleSection.key}-swatch`"
                                        class="rounded-2xl border border-border bg-card p-4"
                                    >
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ styleSection.title }}
                                        </div>
                                        <div
                                            class="mt-3 flex items-center gap-2"
                                        >
                                            <span
                                                v-for="colorField in styleSection.fields"
                                                :key="`${styleSection.key}-${colorField.key}`"
                                                class="size-8 rounded-full border border-black/10 shadow-xs"
                                                :style="{
                                                    backgroundColor:
                                                        form.style_settings[
                                                            colorField.key
                                                        ],
                                                }"
                                            ></span>
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="
                                        props.activeForm &&
                                        editorMode === 'edit'
                                    "
                                    class="space-y-4"
                                >
                                    <div class="grid gap-3 sm:grid-cols-2">
                                        <div
                                            class="rounded-2xl border border-border bg-card p-4"
                                        >
                                            <div
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ t.forms.owner }}
                                            </div>
                                            <div
                                                class="mt-1 text-sm font-medium"
                                            >
                                                {{
                                                    props.activeForm.owner?.name
                                                }}
                                            </div>
                                        </div>

                                        <div
                                            class="rounded-2xl border border-border bg-card p-4"
                                        >
                                            <div
                                                class="text-xs text-muted-foreground"
                                            >
                                                {{ t.forms.delivery_target }}
                                            </div>
                                            <div
                                                class="mt-1 text-sm font-medium"
                                            >
                                                {{
                                                    props.activeForm.target_user
                                                        ?.name
                                                }}
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="rounded-2xl border border-border bg-card p-4"
                                    >
                                        <div
                                            class="text-xs text-muted-foreground"
                                        >
                                            {{ t.forms.public_link }}
                                        </div>
                                        <div class="mt-2 text-sm break-all">
                                            {{ props.activeForm.public_url }}
                                        </div>
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                @click="
                                                    copyPublicLink(
                                                        props.activeForm
                                                            .public_url,
                                                    )
                                                "
                                            >
                                                <Copy class="size-4" />
                                                {{ t.forms.copy_link }}
                                            </Button>
                                            <a
                                                :href="
                                                    props.activeForm.public_url
                                                "
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                >
                                                    <Send class="size-4" />
                                                    {{
                                                        t.forms.open_public_form
                                                    }}
                                                </Button>
                                            </a>
                                        </div>
                                    </div>

                                    <section
                                        class="rounded-2xl border border-border bg-card p-4"
                                    >
                                        <Heading
                                            variant="small"
                                            :title="t.forms.submissions_title"
                                            :description="
                                                t.forms.submissions_description
                                            "
                                        />

                                        <div
                                            v-if="
                                                props.activeForm.submissions
                                                    .length === 0
                                            "
                                            class="mt-4 rounded-2xl border border-dashed border-border bg-muted/20 p-4 text-sm text-muted-foreground"
                                        >
                                            {{ t.forms.no_submissions }}
                                        </div>

                                        <div v-else class="mt-4 space-y-4">
                                            <article
                                                v-for="submission in props
                                                    .activeForm.submissions"
                                                :key="submission.id"
                                                class="rounded-2xl border border-border bg-background/60 p-4"
                                            >
                                                <div
                                                    class="flex flex-col gap-3"
                                                >
                                                    <div>
                                                        <div
                                                            class="text-sm font-semibold"
                                                        >
                                                            {{
                                                                formatDateTime(
                                                                    submission.created_at,
                                                                )
                                                            }}
                                                        </div>
                                                        <div
                                                            class="mt-1 text-xs text-muted-foreground"
                                                        >
                                                            {{
                                                                submission.target_user_name
                                                            }}
                                                        </div>
                                                    </div>

                                                    <div
                                                        class="flex flex-wrap gap-2"
                                                    >
                                                        <Link
                                                            v-if="
                                                                submission.project_task_id
                                                            "
                                                            :href="
                                                                showWorkspaceTask(
                                                                    submission.project_task_id,
                                                                )
                                                            "
                                                        >
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                            >
                                                                <ClipboardList
                                                                    class="size-4"
                                                                />
                                                                {{
                                                                    t.forms
                                                                        .delivery_task
                                                                }}
                                                            </Button>
                                                        </Link>
                                                        <Link
                                                            v-if="
                                                                submission.chat_conversation_id
                                                            "
                                                            :href="
                                                                chatsIndex({
                                                                    query: {
                                                                        conversation:
                                                                            submission.chat_conversation_id,
                                                                    },
                                                                })
                                                            "
                                                        >
                                                            <Button
                                                                type="button"
                                                                variant="outline"
                                                                size="sm"
                                                            >
                                                                <MessageSquareMore
                                                                    class="size-4"
                                                                />
                                                                {{
                                                                    t.forms
                                                                        .delivery_chat
                                                                }}
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
                                                        <dt
                                                            class="text-xs font-medium text-muted-foreground"
                                                        >
                                                            {{ row.label }}
                                                        </dt>
                                                        <dd
                                                            class="mt-1 text-sm whitespace-pre-wrap"
                                                        >
                                                            {{
                                                                row.value ||
                                                                t.forms
                                                                    .empty_value
                                                            }}
                                                        </dd>
                                                    </div>
                                                </dl>
                                            </article>
                                        </div>
                                    </section>
                                </div>
                            </aside>
                        </div>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>

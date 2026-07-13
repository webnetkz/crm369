<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    BookOpenText,
    Database,
    Download,
    PencilLine,
    Plus,
    Rows3,
    Save,
    Trash2,
    Upload,
} from '@lucide/vue';
import { computed, ref, watchEffect } from 'vue';
import {
    destroy as destroyDirectory,
    destroyRecord,
    downloadCsvTemplate,
    exportCsv,
    importCsv,
    index as directoriesIndex,
    show as showDirectory,
    store as storeDirectory,
    storeRecord,
    update as updateDirectory,
    updateRecord,
} from '@/actions/App/Http/Controllers/ReferenceDirectoryController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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

type ColumnType = 'text' | 'textarea' | 'number' | 'date' | 'boolean';

type DirectoryColumn = {
    key: string;
    label: string;
    type: ColumnType;
    is_required: boolean;
};

type DirectoryPerson = {
    id: number;
    name: string;
    email: string;
} | null;

type DirectoryRecord = {
    id: number;
    values: Record<string, boolean | number | string | null>;
    created_at: string | null;
    updated_at: string | null;
    creator: DirectoryPerson;
    updater: DirectoryPerson;
};

type DirectorySummary = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    columns: DirectoryColumn[];
    records_count: number;
    csv_exchange_enabled: boolean;
};

type ActiveDirectory = DirectorySummary & {
    records: DirectoryRecord[];
    creator: DirectoryPerson;
    updater: DirectoryPerson;
};

type ColumnTypeOption = {
    value: ColumnType;
    label: string;
};

type EditableColumn = {
    key: string;
    label: string;
    type: ColumnType;
    is_required: boolean;
};

const props = defineProps<{
    directories: DirectorySummary[];
    activeDirectory: ActiveDirectory | null;
    columnTypes: ColumnTypeOption[];
    can: {
        manageDirectories: boolean;
    };
}>();

const { t } = useLanguage();

const textareaClass =
    'min-h-[120px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';
const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-xs outline-none ring-offset-background focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50';

const directorySheetOpen = ref(false);
const recordSheetOpen = ref(false);
const directoryMode = ref<'create' | 'edit'>('create');
const recordMode = ref<'create' | 'edit'>('create');
const editingRecordId = ref<number | null>(null);

const blankColumn = (): EditableColumn => ({
    key: '',
    label: '',
    type: 'text',
    is_required: false,
});

const directoryForm = useForm({
    name: '',
    slug: '',
    description: '',
    csv_exchange_enabled: true,
    columns: [blankColumn()] as EditableColumn[],
});

const recordForm = useForm({
    values: {} as Record<string, boolean | number | string | null>,
});
const csvImportForm = useForm({
    delimiter: ';',
    file: null as File | null,
});

const activeDirectoryColumns = computed<DirectoryColumn[]>(() => {
    return props.activeDirectory?.columns ?? [];
});
const isActiveDirectoryCsvEnabled = computed<boolean>(() => {
    return props.activeDirectory?.csv_exchange_enabled === true;
});

watchEffect(() => {
    const breadcrumbs = [
        {
            title: t.value.directories.title,
            href: directoriesIndex(),
        },
    ];

    if (props.activeDirectory) {
        breadcrumbs.push({
            title: props.activeDirectory.name,
            href: showDirectory(props.activeDirectory.id),
        });
    }

    setLayoutProps({ breadcrumbs });
});

const canManageDirectories = computed(() => props.can.manageDirectories);

const openCreateDirectory = (): void => {
    directoryMode.value = 'create';
    directoryForm.defaults({
        name: '',
        slug: '',
        description: '',
        csv_exchange_enabled: true,
        columns: [blankColumn()],
    });
    directoryForm.reset();
    directoryForm.clearErrors();
    directorySheetOpen.value = true;
};

const openEditDirectory = (): void => {
    if (!props.activeDirectory) {
        return;
    }

    directoryMode.value = 'edit';
    directoryForm.defaults({
        name: props.activeDirectory.name,
        slug: props.activeDirectory.slug,
        description: props.activeDirectory.description ?? '',
        csv_exchange_enabled: props.activeDirectory.csv_exchange_enabled,
        columns:
            props.activeDirectory.columns.length > 0
                ? props.activeDirectory.columns.map((column) => ({
                      key: column.key,
                      label: column.label,
                      type: column.type,
                      is_required: column.is_required,
                  }))
                : [blankColumn()],
    });
    directoryForm.reset();
    directoryForm.clearErrors();
    directorySheetOpen.value = true;
};

const addColumn = (): void => {
    directoryForm.columns = [...directoryForm.columns, blankColumn()];
};

const removeColumn = (index: number): void => {
    if (directoryForm.columns.length === 1) {
        directoryForm.columns = [blankColumn()];

        return;
    }

    directoryForm.columns = directoryForm.columns.filter(
        (_, columnIndex) => columnIndex !== index,
    );
};

const submitDirectory = (): void => {
    if (
        directoryMode.value === 'edit'
        && props.activeDirectory
    ) {
        directoryForm.patch(updateDirectory.url(props.activeDirectory.id), {
            preserveScroll: true,
            preserveState: 'errors',
            onSuccess: () => {
                directorySheetOpen.value = false;
            },
        });

        return;
    }

    directoryForm.post(storeDirectory.url(), {
        preserveScroll: true,
        preserveState: 'errors',
        onSuccess: () => {
            directorySheetOpen.value = false;
        },
    });
};

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return t.value.directories.never_updated;
    }

    return new Date(value).toLocaleString();
};

const formatRecordValue = (
    column: DirectoryColumn,
    value: boolean | number | string | null | undefined,
): string => {
    if (value === null || value === undefined || value === '') {
        return t.value.directories.value_empty;
    }

    if (column.type === 'boolean') {
        return value ? t.value.directories.boolean_true : t.value.directories.boolean_false;
    }

    return String(value);
};

const normalizeRecordDefaults = (
    directory: ActiveDirectory,
    record: DirectoryRecord | null = null,
): Record<string, boolean | number | string | null> => {
    return Object.fromEntries(
        directory.columns.map((column) => [
            column.key,
            normalizeRecordFieldValue(column, record?.values?.[column.key] ?? null),
        ]),
    );
};

const normalizeRecordFieldValue = (
    column: DirectoryColumn,
    value: boolean | number | string | null,
): boolean | number | string | null => {
    if (column.type === 'boolean') {
        return value === true;
    }

    if (column.type === 'number') {
        return value ?? '';
    }

    return value ?? '';
};

const normalizeRecordPayload = (
    values: Record<string, boolean | number | string | null>,
): Record<string, boolean | number | string | null> => {
    return Object.fromEntries(
        activeDirectoryColumns.value.map((column) => {
            const value = values[column.key];

            if (column.type === 'boolean') {
                return [column.key, value === true];
            }

            if (typeof value === 'string') {
                const normalized = value.trim();

                return [column.key, normalized === '' ? null : normalized];
            }

            return [column.key, value ?? null];
        }),
    );
};

const openCreateRecord = (): void => {
    if (!props.activeDirectory) {
        return;
    }

    recordMode.value = 'create';
    editingRecordId.value = null;
    recordForm.defaults({
        values: normalizeRecordDefaults(props.activeDirectory),
    });
    recordForm.reset();
    recordForm.clearErrors();
    recordSheetOpen.value = true;
};

const openEditRecord = (record: DirectoryRecord): void => {
    if (!props.activeDirectory) {
        return;
    }

    recordMode.value = 'edit';
    editingRecordId.value = record.id;
    recordForm.defaults({
        values: normalizeRecordDefaults(props.activeDirectory, record),
    });
    recordForm.reset();
    recordForm.clearErrors();
    recordSheetOpen.value = true;
};

const submitRecord = (): void => {
    if (!props.activeDirectory) {
        return;
    }

    recordForm.transform((data) => ({
        ...data,
        values: normalizeRecordPayload(data.values),
    }));

    if (recordMode.value === 'edit' && editingRecordId.value) {
        recordForm.patch(
            updateRecord.url(props.activeDirectory.id, editingRecordId.value),
            {
                preserveScroll: true,
                preserveState: 'errors',
                onSuccess: () => {
                    recordSheetOpen.value = false;
                },
            },
        );

        return;
    }

    recordForm.post(storeRecord.url(props.activeDirectory.id), {
        preserveScroll: true,
        preserveState: 'errors',
        onSuccess: () => {
            recordSheetOpen.value = false;
        },
    });
};

const deleteActiveDirectory = (): void => {
    if (!props.activeDirectory) {
        return;
    }

    if (!window.confirm(t.value.directories.delete_confirm)) {
        return;
    }

    router.delete(destroyDirectory.url(props.activeDirectory.id), {
        preserveScroll: true,
    });
};

const deleteDirectoryRecord = (recordId: number): void => {
    if (!props.activeDirectory) {
        return;
    }

    if (!window.confirm(t.value.directories.delete_record_confirm)) {
        return;
    }

    router.delete(destroyRecord.url(props.activeDirectory.id, recordId), {
        preserveScroll: true,
    });
};

const downloadDirectoryExport = (): void => {
    if (!props.activeDirectory) {
        return;
    }

    window.location.assign(
        exportCsv.url(props.activeDirectory.id, {
            delimiter: csvImportForm.delimiter,
        }),
    );
};

const downloadDirectoryTemplate = (): void => {
    if (!props.activeDirectory) {
        return;
    }

    window.location.assign(
        downloadCsvTemplate.url(props.activeDirectory.id, {
            delimiter: csvImportForm.delimiter,
        }),
    );
};

const handleCsvFileChange = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    csvImportForm.file = input.files?.[0] ?? null;
};

const submitCsvImport = (): void => {
    if (!props.activeDirectory || csvImportForm.file === null) {
        return;
    }

    csvImportForm.post(importCsv.url(props.activeDirectory.id), {
        preserveScroll: true,
        onSuccess: () => {
            csvImportForm.file = null;
        },
    });
};
</script>

<template>
    <Head :title="t.directories.title" />

    <h1 class="sr-only">{{ t.directories.title }}</h1>

    <div class="space-y-8">
        <div
            class="flex flex-col gap-4 rounded-3xl border border-border bg-card/70 p-6 shadow-xs lg:flex-row lg:items-end lg:justify-between"
        >
            <Heading
                variant="small"
                :title="t.directories.title"
                :description="t.directories.description"
            />

            <Button
                v-if="canManageDirectories"
                type="button"
                class="gap-2 self-start lg:self-auto"
                @click="openCreateDirectory"
            >
                <Plus class="size-4" />
                {{ t.directories.create_directory }}
            </Button>
        </div>

        <div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <aside class="space-y-4 rounded-3xl border border-border bg-card/60 p-5 shadow-xs">
                <div class="flex items-center gap-2 text-base font-semibold">
                    <BookOpenText class="size-5 text-muted-foreground" />
                    {{ t.directories.list_title }}
                </div>

                <p class="text-sm text-muted-foreground">
                    {{ t.directories.list_description }}
                </p>

                <div
                    v-if="props.directories.length === 0"
                    class="rounded-2xl border border-dashed border-border px-4 py-8 text-sm text-muted-foreground"
                >
                    {{ t.directories.empty }}
                </div>

                <div v-else class="space-y-3">
                    <Link
                        v-for="directory in props.directories"
                        :key="directory.id"
                        :href="showDirectory.url(directory.id)"
                        class="block rounded-2xl border px-4 py-4 transition-colors"
                        :class="
                            props.activeDirectory?.id === directory.id
                                ? 'border-primary/30 bg-primary/8'
                                : 'border-border bg-background/70 hover:border-border/80 hover:bg-muted/40'
                        "
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 space-y-1">
                                <div class="truncate font-medium">
                                    {{ directory.name }}
                                </div>
                                <div class="truncate text-xs text-muted-foreground">
                                    {{ directory.slug }}
                                </div>
                            </div>

                            <Badge variant="secondary">
                                {{
                                    t.directories.records_count.replace(
                                        ':count',
                                        String(directory.records_count),
                                    )
                                }}
                            </Badge>
                        </div>

                        <p
                            v-if="directory.description"
                            class="mt-3 line-clamp-2 text-sm text-muted-foreground"
                        >
                            {{ directory.description }}
                        </p>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <Badge
                                v-for="column in directory.columns.slice(0, 3)"
                                :key="`${directory.id}-${column.key}`"
                                variant="outline"
                            >
                                {{ column.label }}
                            </Badge>

                            <Badge
                                v-if="directory.columns.length > 3"
                                variant="outline"
                            >
                                +{{ directory.columns.length - 3 }}
                            </Badge>
                        </div>
                    </Link>
                </div>
            </aside>

            <section
                v-if="props.activeDirectory"
                class="space-y-6 rounded-3xl border border-border bg-card/60 p-6 shadow-xs"
            >
                <div
                    class="flex flex-col gap-4 rounded-2xl border border-border bg-background/70 p-5 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <Database class="size-5 text-muted-foreground" />
                            <h2 class="text-lg font-semibold">
                                {{ props.activeDirectory.name }}
                            </h2>
                            <Badge variant="secondary">
                                {{ props.activeDirectory.slug }}
                            </Badge>
                        </div>

                        <p
                            v-if="props.activeDirectory.description"
                            class="max-w-3xl text-sm text-muted-foreground"
                        >
                            {{ props.activeDirectory.description }}
                        </p>

                        <div class="grid gap-2 text-sm text-muted-foreground md:grid-cols-2">
                            <div>
                                {{ t.directories.columns_count.replace(':count', String(props.activeDirectory.columns.length)) }}
                            </div>
                            <div>
                                {{ t.directories.records_count.replace(':count', String(props.activeDirectory.records_count)) }}
                            </div>
                            <div>
                                {{ t.directories.created_by_label.replace(':name', props.activeDirectory.creator?.name ?? t.directories.unknown_author) }}
                            </div>
                            <div>
                                {{ t.directories.updated_at_label.replace(':date', formatDateTime(props.activeDirectory.updated_at)) }}
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="canManageDirectories"
                        class="flex flex-wrap gap-3"
                    >
                        <Button
                            type="button"
                            variant="outline"
                            class="gap-2"
                            @click="openEditDirectory"
                        >
                            <PencilLine class="size-4" />
                            {{ t.directories.edit_directory }}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            class="gap-2"
                            @click="deleteActiveDirectory"
                        >
                            <Trash2 class="size-4" />
                            {{ t.directories.delete }}
                        </Button>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="flex items-center gap-2 text-base font-semibold">
                        <Rows3 class="size-5 text-muted-foreground" />
                        {{ t.directories.columns_title }}
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div
                            v-for="column in props.activeDirectory.columns"
                            :key="column.key"
                            class="rounded-2xl border border-border bg-background/70 p-4"
                        >
                            <div class="flex items-center justify-between gap-3">
                                <div class="font-medium">{{ column.label }}</div>
                                <Badge variant="outline">
                                    {{ column.key }}
                                </Badge>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <Badge variant="secondary">
                                    {{ t.directories.type_label.replace(':type', props.columnTypes.find((option) => option.value === column.type)?.label ?? column.type) }}
                                </Badge>
                                <Badge
                                    :variant="
                                        column.is_required
                                            ? 'default'
                                            : 'outline'
                                    "
                                >
                                    {{
                                        column.is_required
                                            ? t.directories.required
                                            : t.directories.optional
                                    }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-border bg-background/70 p-5">
                    <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-2">
                            <div class="text-base font-semibold">
                                {{ t.directories.csv_title }}
                            </div>
                            <p class="max-w-2xl text-sm text-muted-foreground">
                                {{ t.directories.csv_description }}
                            </p>
                        </div>

                        <div
                            v-if="isActiveDirectoryCsvEnabled"
                            class="grid w-full gap-4 lg:max-w-xl"
                        >
                            <div class="grid gap-2">
                                <Label for="directory-csv-delimiter">
                                    {{ t.directories.csv_delimiter }}
                                </Label>
                                <Input
                                    id="directory-csv-delimiter"
                                    v-model="csvImportForm.delimiter"
                                    :placeholder="t.directories.csv_delimiter_placeholder"
                                    maxlength="10"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ t.directories.csv_delimiter_hint }}
                                </p>
                                <InputError :message="csvImportForm.errors.delimiter" />
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    class="gap-2"
                                    @click="downloadDirectoryExport"
                                >
                                    <Download class="size-4" />
                                    {{ t.directories.csv_export }}
                                </Button>

                                <Button
                                    v-if="canManageDirectories"
                                    type="button"
                                    variant="outline"
                                    class="gap-2"
                                    @click="downloadDirectoryTemplate"
                                >
                                    <Download class="size-4" />
                                    {{ t.directories.csv_download_template }}
                                </Button>
                            </div>

                            <form
                                v-if="canManageDirectories"
                                class="grid gap-3 rounded-2xl border border-dashed border-border p-4"
                                @submit.prevent="submitCsvImport"
                            >
                                <div class="grid gap-2">
                                    <Label for="directory-csv-file">
                                        {{ t.directories.csv_file }}
                                    </Label>
                                    <Input
                                        id="directory-csv-file"
                                        type="file"
                                        accept=".csv,text/csv,.txt"
                                        @change="handleCsvFileChange"
                                    />
                                    <InputError :message="csvImportForm.errors.file" />
                                </div>

                                <div class="flex justify-end">
                                    <Button
                                        type="submit"
                                        class="gap-2"
                                        :disabled="csvImportForm.processing || csvImportForm.file === null"
                                    >
                                        <Upload class="size-4" />
                                        {{ t.directories.csv_import }}
                                    </Button>
                                </div>
                            </form>
                        </div>

                        <div
                            v-else
                            class="w-full rounded-2xl border border-dashed border-border bg-muted/20 p-4 lg:max-w-xl"
                        >
                            <div class="text-sm font-medium">
                                {{ t.directories.csv_disabled }}
                            </div>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ t.directories.csv_disabled_description }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <div class="text-base font-semibold">
                                {{ t.directories.records_title }}
                            </div>
                            <p class="text-sm text-muted-foreground">
                                {{ t.directories.records_description }}
                            </p>
                        </div>

                        <Button
                            v-if="canManageDirectories"
                            type="button"
                            class="gap-2 self-start"
                            @click="openCreateRecord"
                        >
                            <Plus class="size-4" />
                            {{ t.directories.add_record }}
                        </Button>
                    </div>

                    <div
                        v-if="props.activeDirectory.records.length === 0"
                        class="rounded-2xl border border-dashed border-border px-4 py-8 text-sm text-muted-foreground"
                    >
                        {{ t.directories.records_empty }}
                    </div>

                    <div v-else class="grid gap-4">
                        <div
                            v-for="record in props.activeDirectory.records"
                            :key="record.id"
                            class="rounded-2xl border border-border bg-background/70 p-5"
                        >
                            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                <div class="space-y-1">
                                    <div class="font-medium">
                                        {{ t.directories.record_label.replace(':id', String(record.id)) }}
                                    </div>
                                    <div class="text-xs text-muted-foreground">
                                        {{ t.directories.updated_at_label.replace(':date', formatDateTime(record.updated_at)) }}
                                    </div>
                                </div>

                                <div
                                    v-if="canManageDirectories"
                                    class="flex flex-wrap gap-2"
                                >
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="gap-2"
                                        @click="openEditRecord(record)"
                                    >
                                        <PencilLine class="size-4" />
                                        {{ t.directories.edit_record }}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        class="gap-2"
                                        @click="deleteDirectoryRecord(record.id)"
                                    >
                                        <Trash2 class="size-4" />
                                        {{ t.directories.delete }}
                                    </Button>
                                </div>
                            </div>

                            <div class="mt-4 grid gap-4 lg:grid-cols-2">
                                <div
                                    v-for="column in props.activeDirectory.columns"
                                    :key="`${record.id}-${column.key}`"
                                    class="rounded-xl border border-border/80 bg-card/50 px-4 py-3"
                                >
                                    <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        {{ column.label }}
                                    </div>
                                    <div class="mt-1 text-sm text-foreground">
                                        {{ formatRecordValue(column, record.values[column.key]) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-else
                class="flex min-h-[26rem] items-center justify-center rounded-3xl border border-dashed border-border bg-card/50 p-8 text-center"
            >
                <div class="max-w-md space-y-3">
                    <div class="text-lg font-semibold">
                        {{ t.directories.empty_state_title }}
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ t.directories.empty_state_description }}
                    </p>
                    <Button
                        v-if="canManageDirectories"
                        type="button"
                        class="gap-2"
                        @click="openCreateDirectory"
                    >
                        <Plus class="size-4" />
                        {{ t.directories.create_directory }}
                    </Button>
                </div>
            </section>
        </div>

        <Sheet v-model:open="directorySheetOpen">
            <SheetContent class="w-full overflow-y-auto p-6 sm:max-w-3xl">
                <SheetHeader>
                    <SheetTitle>
                        {{
                            directoryMode === 'edit'
                                ? t.directories.edit_directory
                                : t.directories.create_directory
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        {{ t.directories.form_description }}
                    </SheetDescription>
                </SheetHeader>

                <form class="mt-6 space-y-6" @submit.prevent="submitDirectory">
                    <div class="grid gap-5 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="directory-name">
                                {{ t.directories.name }}
                            </Label>
                            <Input
                                id="directory-name"
                                v-model="directoryForm.name"
                                :placeholder="t.directories.name_placeholder"
                            />
                            <InputError :message="directoryForm.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="directory-slug">
                                {{ t.directories.slug }}
                            </Label>
                            <Input
                                id="directory-slug"
                                v-model="directoryForm.slug"
                                :placeholder="t.directories.slug_placeholder"
                            />
                            <InputError :message="directoryForm.errors.slug" />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <Label for="directory-description">
                            {{ t.directories.description_label }}
                        </Label>
                        <textarea
                            id="directory-description"
                            v-model="directoryForm.description"
                            :placeholder="t.directories.description_placeholder"
                            :class="textareaClass"
                        />
                        <InputError :message="directoryForm.errors.description" />
                    </div>

                    <div class="rounded-2xl border border-border bg-background/70 p-4">
                        <div class="flex items-start gap-3">
                            <Checkbox
                                id="directory-csv-exchange-enabled"
                                :model-value="directoryForm.csv_exchange_enabled"
                                @update:model-value="
                                    (value) =>
                                        (directoryForm.csv_exchange_enabled =
                                            value === true)
                                "
                            />
                            <div class="space-y-1">
                                <Label for="directory-csv-exchange-enabled">
                                    {{ t.directories.csv_exchange_enabled }}
                                </Label>
                                <p class="text-sm text-muted-foreground">
                                    {{ t.directories.csv_exchange_enabled_hint }}
                                </p>
                            </div>
                        </div>
                        <InputError
                            :message="directoryForm.errors.csv_exchange_enabled"
                        />
                    </div>

                    <div class="space-y-4">
                        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <div class="text-base font-semibold">
                                    {{ t.directories.columns_editor_title }}
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{ t.directories.columns_editor_description }}
                                </p>
                            </div>

                            <Button
                                type="button"
                                variant="outline"
                                class="gap-2 self-start"
                                @click="addColumn"
                            >
                                <Plus class="size-4" />
                                {{ t.directories.add_column }}
                            </Button>
                        </div>

                        <div class="space-y-4">
                            <div
                                v-for="(column, index) in directoryForm.columns"
                                :key="`directory-column-${index}`"
                                class="rounded-2xl border border-border bg-background/70 p-4"
                            >
                                <div class="grid gap-4 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)_12rem_auto] lg:items-end">
                                    <div class="grid gap-2">
                                        <Label>
                                            {{ t.directories.column_label }}
                                        </Label>
                                        <Input
                                            v-model="column.label"
                                            :placeholder="t.directories.column_label_placeholder"
                                        />
                                        <InputError
                                            :message="
                                                directoryForm.errors[
                                                    `columns.${index}.label`
                                                ]
                                            "
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label>
                                            {{ t.directories.column_key }}
                                        </Label>
                                        <Input
                                            v-model="column.key"
                                            :placeholder="t.directories.column_key_placeholder"
                                        />
                                        <InputError
                                            :message="
                                                directoryForm.errors[
                                                    `columns.${index}.key`
                                                ]
                                            "
                                        />
                                    </div>

                                    <div class="grid gap-2">
                                        <Label>
                                            {{ t.directories.field_type }}
                                        </Label>
                                        <select
                                            v-model="column.type"
                                            :class="selectClass"
                                        >
                                            <option
                                                v-for="option in props.columnTypes"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <InputError
                                            :message="
                                                directoryForm.errors[
                                                    `columns.${index}.type`
                                                ]
                                            "
                                        />
                                    </div>

                                    <div class="flex flex-col gap-3">
                                        <div class="flex items-center gap-3 rounded-xl border border-border px-3 py-2">
                                            <Checkbox
                                                :model-value="
                                                    column.is_required
                                                "
                                                @update:model-value="
                                                    (value) =>
                                                        (column.is_required =
                                                            value === true)
                                                "
                                            />
                                            <Label class="text-sm">
                                                {{ t.directories.required }}
                                            </Label>
                                        </div>

                                        <Button
                                            type="button"
                                            variant="outline"
                                            class="gap-2"
                                            @click="removeColumn(index)"
                                        >
                                            <Trash2 class="size-4" />
                                            {{ t.directories.remove_column }}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <InputError :message="directoryForm.errors.columns" />
                    </div>

                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            class="gap-2"
                            :disabled="directoryForm.processing"
                        >
                            <Save class="size-4" />
                            {{
                                directoryMode === 'edit'
                                    ? t.directories.update
                                    : t.directories.save
                            }}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>

        <Sheet v-model:open="recordSheetOpen">
            <SheetContent class="w-full overflow-y-auto p-6 sm:max-w-2xl">
                <SheetHeader>
                    <SheetTitle>
                        {{
                            recordMode === 'edit'
                                ? t.directories.edit_record
                                : t.directories.add_record
                        }}
                    </SheetTitle>
                    <SheetDescription>
                        {{ t.directories.record_form_description }}
                    </SheetDescription>
                </SheetHeader>

                <form
                    v-if="props.activeDirectory"
                    class="mt-6 space-y-5"
                    @submit.prevent="submitRecord"
                >
                    <div
                        v-for="column in props.activeDirectory.columns"
                        :key="`record-field-${column.key}`"
                        class="grid gap-2"
                    >
                        <Label :for="`record-${column.key}`">
                            {{ column.label }}
                        </Label>

                        <Checkbox
                            v-if="column.type === 'boolean'"
                            :id="`record-${column.key}`"
                            :model-value="
                                recordForm.values[column.key] === true
                            "
                            @update:model-value="
                                (value) =>
                                    (recordForm.values[column.key] =
                                        value === true)
                            "
                        />

                        <textarea
                            v-else-if="column.type === 'textarea'"
                            :id="`record-${column.key}`"
                            v-model="recordForm.values[column.key] as string"
                            :placeholder="column.label"
                            :class="textareaClass"
                        />

                        <Input
                            v-else
                            :id="`record-${column.key}`"
                            v-model="recordForm.values[column.key] as string | number | null"
                            :type="
                                column.type === 'number'
                                    ? 'number'
                                    : column.type === 'date'
                                      ? 'date'
                                      : 'text'
                            "
                            :placeholder="column.label"
                        />

                        <InputError
                            :message="
                                recordForm.errors[`values.${column.key}`]
                            "
                        />
                    </div>

                    <div class="flex justify-end">
                        <Button
                            type="submit"
                            class="gap-2"
                            :disabled="recordForm.processing"
                        >
                            <Save class="size-4" />
                            {{
                                recordMode === 'edit'
                                    ? t.directories.update
                                    : t.directories.save
                            }}
                        </Button>
                    </div>
                </form>
            </SheetContent>
        </Sheet>
    </div>
</template>

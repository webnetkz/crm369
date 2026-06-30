<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    ChevronRight,
    Download,
    FileText,
    FolderOpen,
    FolderPlus,
    HardDrive,
    LockKeyhole,
    Monitor,
    Shield,
    Trash2,
    Upload,
    Users,
} from '@lucide/vue';
import {
    computed,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
    watchEffect,
} from 'vue';
import {
    destroyDirectory,
    destroyEntry,
    destroyPermission,
    index as filesIndex,
    storeDirectory,
    storeEntry,
    storePermission,
} from '@/actions/App/Http/Controllers/FileController';
import FileTreeItem from '@/components/files/FileTreeItem.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import type {
    FileActiveDirectory,
    FileAvailableGroup,
    FileAvailableUser,
    FileDirectoryPermissionItem,
    FileEntryItem,
    FileTreeDirectory,
} from '@/types/ui';

type Props = {
    tree: FileTreeDirectory[];
    activeDirectory: FileActiveDirectory | null;
    availableUsers: FileAvailableUser[];
    availableGroups: FileAvailableGroup[];
    can: {
        createRoot: boolean;
    };
};

type ContextDirectoryTarget = {
    type: 'directory';
    id: number;
    name: string;
    canEdit: boolean;
    permissions: FileDirectoryPermissionItem[];
};

type ContextFileTarget = {
    type: 'file';
    id: number;
    name: string;
    canEdit: boolean;
    downloadUrl: string;
};

type ContextWorkspaceTarget = {
    type: 'workspace';
    directoryId: number | null;
    name: string;
    canEdit: boolean;
};

type ContextMenuTarget =
    ContextDirectoryTarget | ContextFileTarget | ContextWorkspaceTarget;

type DesktopDirectoryItem = {
    kind: 'directory';
    id: number;
    name: string;
    permissionLevel: 'read' | 'edit' | null;
    childrenCount: number;
    filesCount: number;
    target: ContextDirectoryTarget;
};

type DesktopFileItem = {
    kind: 'file';
    id: number;
    name: string;
    sizeBytes: number;
    extension: string | null;
    mimeType: string | null;
    ownerName: string | null;
    createdAt: string | null;
    downloadUrl: string;
    target: ContextFileTarget;
};

type DesktopItem = DesktopDirectoryItem | DesktopFileItem;

const props = defineProps<Props>();
const { language, t } = useLanguage();

const createDirectoryDialogOpen = ref(false);
const createFileDialogOpen = ref(false);
const permissionsDialogOpen = ref(false);
const selectedDesktopItem = ref<{
    kind: DesktopItem['kind'];
    id: number;
} | null>(null);
const contextMenu = ref<{
    open: boolean;
    x: number;
    y: number;
    target: ContextMenuTarget | null;
}>({
    open: false,
    x: 0,
    y: 0,
    target: null,
});
const selectedPermissionDirectoryId = ref<number | null>(
    props.activeDirectory?.id ?? null,
);
const dragDepth = ref(0);
const fileInput = ref<HTMLInputElement | null>(null);

const directoryForm = useForm({
    parent_id: null as number | null,
    name: '',
});

const createFileForm = useForm({
    directory_id: null as number | null,
    name: '',
});

const uploadForm = useForm({
    directory_id: null as number | null,
    file: null as File | null,
});

const permissionForm = useForm({
    access_level: 'read' as 'read' | 'edit',
    subject_type: 'user' as 'user' | 'group',
    user_id: null as number | null,
    user_group_id: null as number | null,
});

const canCreateInCurrentDirectory = computed(
    () => props.activeDirectory?.can_edit ?? false,
);

const targetParentDirectoryId = computed<number | null>(() => {
    return canCreateInCurrentDirectory.value
        ? (props.activeDirectory?.id ?? null)
        : null;
});

const currentItemCount = computed(() => {
    return (
        (props.activeDirectory?.children.length ?? 0) +
        (props.activeDirectory?.entries.length ?? 0)
    );
});

const isDropActive = computed(() => dragDepth.value > 0);

const uploadProgress = computed(() => uploadForm.progress?.percentage ?? 0);

const contextMenuStyle = computed<Record<string, string>>(() => ({
    left: `${contextMenu.value.x}px`,
    top: `${contextMenu.value.y}px`,
}));

const currentWorkspaceTarget = computed<ContextDirectoryTarget | null>(() => {
    if (!props.activeDirectory) {
        return null;
    }

    return {
        type: 'directory',
        id: props.activeDirectory.id,
        name: props.activeDirectory.name,
        canEdit: props.activeDirectory.can_edit,
        permissions: props.activeDirectory.permissions,
    };
});

const selectedPermissionDirectory = computed<ContextDirectoryTarget | null>(
    () => {
        const directoryId = selectedPermissionDirectoryId.value;

        if (directoryId === null) {
            return null;
        }

        if (props.activeDirectory?.id === directoryId) {
            return currentWorkspaceTarget.value;
        }

        const treeDirectory = findTreeDirectoryById(directoryId, props.tree);

        return treeDirectory ? directoryContextTarget(treeDirectory) : null;
    },
);

const selectedPermissionEntries = computed(() => {
    return selectedPermissionDirectory.value?.permissions ?? [];
});

const desktopItems = computed<DesktopItem[]>(() => {
    const directoryItems = (props.activeDirectory?.children ?? []).map(
        (directory) => ({
            kind: 'directory' as const,
            id: directory.id,
            name: directory.name,
            permissionLevel: directory.permission_level,
            childrenCount: directory.children_count,
            filesCount: directory.files_count,
            target: directoryContextTarget(directory),
        }),
    );

    const fileItems = (props.activeDirectory?.entries ?? []).map((entry) => ({
        kind: 'file' as const,
        id: entry.id,
        name: entry.original_name,
        sizeBytes: entry.size_bytes,
        extension: entry.extension,
        mimeType: entry.mime_type,
        ownerName: entry.owner_name,
        createdAt: entry.created_at,
        downloadUrl: entry.download_url,
        target: fileContextTarget(entry),
    }));

    return [...directoryItems, ...fileItems];
});

const selectedItemLabel = computed(() => {
    const selection = selectedDesktopItem.value;

    if (!selection) {
        return t.value.files.selection_none;
    }

    const item = desktopItems.value.find((desktopItem) => {
        return (
            desktopItem.kind === selection.kind &&
            desktopItem.id === selection.id
        );
    });

    if (!item) {
        return t.value.files.selection_none;
    }

    return item.kind === 'directory'
        ? `${t.value.files.folder_selected}: ${item.name}`
        : `${t.value.files.file_selected}: ${item.name}`;
});

const desktopStatusLabel = computed(() => {
    return props.activeDirectory?.can_edit
        ? t.value.files.desktop_ready
        : t.value.files.readonly_notice;
});

const permissionLabel = (accessLevel: 'read' | 'edit' | null): string => {
    return accessLevel === 'edit' ? t.value.files.edit : t.value.files.read;
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

const formatBytes = (size: number): string => {
    if (size < 1024) {
        return `${size} B`;
    }

    if (size < 1024 * 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
};

const fileTypeLabel = (
    entry: Pick<FileEntryItem, 'extension' | 'mime_type'>,
): string => {
    return entry.extension || entry.mime_type || 'file';
};

const directoryMetaLabel = (
    directory: Pick<FileTreeDirectory, 'files_count' | 'children_count'>,
): string => {
    return `${directory.files_count} / ${directory.children_count}`;
};

const desktopFileMeta = (item: DesktopFileItem): string => {
    return `${formatBytes(item.sizeBytes)} · ${fileTypeLabel({ extension: item.extension, mime_type: item.mimeType })}`;
};

const desktopFileDetails = (item: DesktopFileItem): string => {
    const parts = [item.ownerName, formatDateTime(item.createdAt)].filter(
        (value) => value && value !== '',
    );

    return parts.join(' · ');
};

const findTreeDirectoryById = (
    directoryId: number,
    directories: FileTreeDirectory[],
): FileTreeDirectory | null => {
    for (const directory of directories) {
        if (directory.id === directoryId) {
            return directory;
        }

        const nestedDirectory = findTreeDirectoryById(
            directoryId,
            directory.children,
        );

        if (nestedDirectory) {
            return nestedDirectory;
        }
    }

    return null;
};

const directoryContextTarget = (
    directory: Pick<
        FileTreeDirectory,
        'id' | 'name' | 'can_edit' | 'permissions'
    >,
): ContextDirectoryTarget => ({
    type: 'directory',
    id: directory.id,
    name: directory.name,
    canEdit: directory.can_edit,
    permissions: directory.permissions,
});

const fileContextTarget = (entry: FileEntryItem): ContextFileTarget => ({
    type: 'file',
    id: entry.id,
    name: entry.original_name,
    canEdit: props.activeDirectory?.can_edit ?? false,
    downloadUrl: entry.download_url,
});

const clearDesktopSelection = (): void => {
    selectedDesktopItem.value = null;
};

const selectDesktopItem = (item: DesktopItem): void => {
    selectedDesktopItem.value = {
        kind: item.kind,
        id: item.id,
    };
};

const isSelectedDesktopItem = (item: DesktopItem): boolean => {
    return (
        selectedDesktopItem.value?.kind === item.kind &&
        selectedDesktopItem.value.id === item.id
    );
};

const closeContextMenu = (): void => {
    contextMenu.value = {
        open: false,
        x: 0,
        y: 0,
        target: null,
    };
};

const contextMenuHeight = (target: ContextMenuTarget): number => {
    if (target.type === 'workspace') {
        return target.canEdit ? 212 : 88;
    }

    if (target.type === 'directory') {
        return target.canEdit ? 168 : 64;
    }

    return target.canEdit ? 112 : 64;
};

const openContextMenu = (
    event: MouseEvent,
    target: ContextMenuTarget,
): void => {
    event.stopPropagation();

    contextMenu.value = {
        open: true,
        x: Math.max(12, Math.min(event.clientX, window.innerWidth - 224 - 12)),
        y: Math.max(
            12,
            Math.min(
                event.clientY,
                window.innerHeight - contextMenuHeight(target) - 12,
            ),
        ),
        target,
    };
};

const openWorkspaceContextMenu = (event: MouseEvent): void => {
    if (!props.activeDirectory) {
        return;
    }

    clearDesktopSelection();

    openContextMenu(event, {
        type: 'workspace',
        directoryId: props.activeDirectory.id,
        name: props.activeDirectory.name,
        canEdit: props.activeDirectory.can_edit,
    });
};

const openDesktopItemContextMenu = (
    event: MouseEvent,
    item: DesktopItem,
): void => {
    selectDesktopItem(item);
    openContextMenu(event, item.target);
};

const activateDesktopItem = (item: DesktopItem): void => {
    selectDesktopItem(item);

    if (item.kind === 'directory') {
        visitDirectory(item.id);

        return;
    }

    downloadFile(item.downloadUrl);
};

const visitDirectory = (directoryId: number): void => {
    closeContextMenu();

    router.visit(filesIndex.url({ query: { directory: directoryId } }), {
        preserveScroll: true,
    });
};

const closeCreateDirectoryDialog = (): void => {
    createDirectoryDialogOpen.value = false;
    directoryForm.reset();
    directoryForm.clearErrors();
};

const openCreateDirectoryDialog = (): void => {
    closeContextMenu();
    directoryForm.reset();
    directoryForm.clearErrors();
    createDirectoryDialogOpen.value = true;
};

const closeCreateFileDialog = (): void => {
    createFileDialogOpen.value = false;
    createFileForm.reset();
    createFileForm.clearErrors();
};

const openCreateFileDialog = (): void => {
    if (!props.activeDirectory?.can_edit) {
        return;
    }

    closeContextMenu();
    createFileForm.reset();
    createFileForm.clearErrors();
    createFileForm.directory_id = props.activeDirectory.id;
    createFileDialogOpen.value = true;
};

const openPermissionDialog = (directory: ContextDirectoryTarget): void => {
    closeContextMenu();
    selectedPermissionDirectoryId.value = directory.id;
    permissionForm.reset();
    permissionForm.clearErrors();
    permissionForm.access_level = 'read';
    permissionForm.subject_type = 'user';
    permissionsDialogOpen.value = true;
};

const openCurrentDirectoryPermissions = (): void => {
    if (!currentWorkspaceTarget.value) {
        return;
    }

    openPermissionDialog(currentWorkspaceTarget.value);
};

const closePermissionDialog = (): void => {
    permissionsDialogOpen.value = false;
    permissionForm.reset();
    permissionForm.clearErrors();
    permissionForm.access_level = 'read';
    permissionForm.subject_type = 'user';
};

const submitDirectory = (): void => {
    directoryForm.parent_id = targetParentDirectoryId.value;

    if (directoryForm.parent_id === null && !props.can.createRoot) {
        return;
    }

    directoryForm.post(storeDirectory.url(), {
        preserveScroll: true,
        onSuccess: () => {
            closeCreateDirectoryDialog();
        },
    });
};

const submitFile = (): void => {
    if (!props.activeDirectory?.can_edit) {
        return;
    }

    createFileForm.directory_id = props.activeDirectory.id;

    createFileForm.post(storeEntry.url(), {
        preserveScroll: true,
        onSuccess: () => {
            closeCreateFileDialog();
        },
    });
};

const resetUploadState = (): void => {
    uploadForm.reset();
    uploadForm.clearErrors();
    uploadForm.directory_id = props.activeDirectory?.id ?? null;

    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const startUpload = (file: File | null): void => {
    if (!file || !props.activeDirectory || !props.activeDirectory.can_edit) {
        return;
    }

    closeContextMenu();
    uploadForm.directory_id = props.activeDirectory.id;
    uploadForm.file = file;

    uploadForm.post(storeEntry.url(), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            resetUploadState();
        },
    });
};

const onFileChange = (event: Event): void => {
    const target = event.target as HTMLInputElement | null;
    startUpload(target?.files?.[0] ?? null);
};

const openFilePicker = (): void => {
    if (!props.activeDirectory?.can_edit) {
        return;
    }

    closeContextMenu();
    fileInput.value?.click();
};

const handleDragEnter = (event: DragEvent): void => {
    if (
        !props.activeDirectory?.can_edit ||
        !event.dataTransfer?.types.includes('Files')
    ) {
        return;
    }

    dragDepth.value += 1;
};

const handleDragOver = (event: DragEvent): void => {
    if (!props.activeDirectory?.can_edit) {
        return;
    }

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'copy';
    }
};

const handleDragLeave = (event: DragEvent): void => {
    if (
        !props.activeDirectory?.can_edit ||
        !event.dataTransfer?.types.includes('Files')
    ) {
        return;
    }

    dragDepth.value = Math.max(0, dragDepth.value - 1);
};

const handleDrop = (event: DragEvent): void => {
    dragDepth.value = 0;

    if (!props.activeDirectory?.can_edit) {
        return;
    }

    startUpload(event.dataTransfer?.files?.[0] ?? null);
};

const resetDragState = (): void => {
    dragDepth.value = 0;
};

const submitPermission = (): void => {
    if (!selectedPermissionDirectory.value) {
        return;
    }

    permissionForm.user_id =
        permissionForm.subject_type === 'user' ? permissionForm.user_id : null;
    permissionForm.user_group_id =
        permissionForm.subject_type === 'group'
            ? permissionForm.user_group_id
            : null;

    permissionForm.post(
        storePermission.url(selectedPermissionDirectory.value.id),
        {
            preserveScroll: true,
            onSuccess: () => {
                permissionForm.reset();
                permissionForm.access_level = 'read';
                permissionForm.subject_type = 'user';
            },
        },
    );
};

const removeDirectory = (directoryId: number): void => {
    closeContextMenu();

    if (!window.confirm(t.value.files.delete_directory_confirm)) {
        return;
    }

    router.delete(destroyDirectory.url(directoryId), {
        preserveScroll: true,
    });
};

const removeFile = (fileEntryId: number): void => {
    closeContextMenu();

    if (!window.confirm(t.value.files.delete_file_confirm)) {
        return;
    }

    router.delete(destroyEntry.url(fileEntryId), {
        preserveScroll: true,
    });
};

const downloadFile = (downloadUrl: string): void => {
    closeContextMenu();
    window.location.assign(downloadUrl);
};

const removePermission = (permissionId: number): void => {
    if (!selectedPermissionDirectory.value) {
        return;
    }

    router.delete(
        destroyPermission.url({
            fileDirectory: selectedPermissionDirectory.value.id,
            fileDirectoryPermission: permissionId,
        }),
        {
            preserveScroll: true,
        },
    );
};

const handleGlobalClick = (event: MouseEvent): void => {
    const target = event.target as HTMLElement | null;

    if (target?.closest('[data-file-context-menu]')) {
        return;
    }

    closeContextMenu();
};

const handleWindowKeydown = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        closeContextMenu();
        resetDragState();
    }
};

watch(
    () => props.activeDirectory?.id,
    () => {
        closeContextMenu();
        clearDesktopSelection();
        closeCreateFileDialog();
        resetUploadState();
        selectedPermissionDirectoryId.value = props.activeDirectory?.id ?? null;
    },
    { immediate: true },
);

watchEffect(() => {
    const breadcrumbs = [
        {
            title: t.value.files.title,
            href: filesIndex(),
        },
    ];

    for (const crumb of props.activeDirectory?.breadcrumbs ?? []) {
        breadcrumbs.push({
            title: crumb.name,
            href: filesIndex({ query: { directory: crumb.id } }),
        });
    }

    setLayoutProps({ breadcrumbs });
});

onMounted(() => {
    window.addEventListener('click', handleGlobalClick);
    window.addEventListener('keydown', handleWindowKeydown);
    window.addEventListener('blur', closeContextMenu);
    window.addEventListener('dragend', resetDragState);
    window.addEventListener('drop', resetDragState);
});

onBeforeUnmount(() => {
    window.removeEventListener('click', handleGlobalClick);
    window.removeEventListener('keydown', handleWindowKeydown);
    window.removeEventListener('blur', closeContextMenu);
    window.removeEventListener('dragend', resetDragState);
    window.removeEventListener('drop', resetDragState);
});
</script>

<template>
    <Head :title="t.files.title" />

    <div class="grid gap-6 xl:grid-cols-[300px_minmax(0,1fr)]">
        <aside
            class="space-y-5 rounded-[30px] border border-border/70 bg-card/95 p-5 shadow-sm"
        >
            <div class="space-y-3">
                <Heading
                    variant="small"
                    :title="t.files.tree_title"
                    :description="t.files.tree_description"
                />

                <Button
                    v-if="props.can.createRoot || canCreateInCurrentDirectory"
                    type="button"
                    class="w-full justify-start rounded-2xl"
                    @click="openCreateDirectoryDialog"
                >
                    <FolderPlus class="size-4" />
                    {{ t.files.new_folder }}
                </Button>
            </div>

            <div
                v-if="props.tree.length === 0"
                class="rounded-2xl border border-dashed border-border bg-muted/20 p-4 text-sm text-muted-foreground"
            >
                {{ t.files.empty_description }}
            </div>

            <div
                v-else
                class="max-h-[calc(100vh-16rem)] space-y-2 overflow-y-auto pr-1"
            >
                <FileTreeItem
                    v-for="directory in props.tree"
                    :key="directory.id"
                    :item="directory"
                    :active-directory-id="props.activeDirectory?.id ?? null"
                    @delete="removeDirectory"
                />
            </div>
        </aside>

        <div class="space-y-6">
            <section
                v-if="props.activeDirectory"
                class="overflow-hidden rounded-[34px] border border-border/70 bg-card shadow-sm"
                @dragenter.prevent="handleDragEnter"
                @dragover.prevent="handleDragOver"
                @dragleave.prevent="handleDragLeave"
                @drop.prevent="handleDrop"
            >
                <div
                    class="border-b border-border/70 bg-background/80 px-5 py-4 backdrop-blur"
                >
                    <div
                        class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between"
                    >
                        <div class="space-y-3">
                            <div
                                class="flex flex-wrap items-center gap-2 text-xs text-muted-foreground"
                            >
                                <Link
                                    :href="filesIndex()"
                                    class="rounded-full px-2 py-1 transition hover:bg-muted hover:text-foreground"
                                >
                                    {{ t.files.title }}
                                </Link>

                                <template
                                    v-for="crumb in props.activeDirectory
                                        .breadcrumbs"
                                    :key="crumb.id"
                                >
                                    <ChevronRight class="size-3.5" />
                                    <Link
                                        :href="
                                            filesIndex({
                                                query: { directory: crumb.id },
                                            })
                                        "
                                        class="rounded-full px-2 py-1 transition hover:bg-muted hover:text-foreground"
                                    >
                                        {{ crumb.name }}
                                    </Link>
                                </template>
                            </div>

                            <div>
                                <div
                                    class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary"
                                >
                                    <HardDrive class="size-4" />
                                    {{ t.files.private_storage }}
                                </div>
                                <h1
                                    class="mt-3 text-2xl font-semibold tracking-tight"
                                >
                                    {{ props.activeDirectory.name }}
                                </h1>
                                <p
                                    class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground"
                                >
                                    {{ t.files.workspace_description }}
                                </p>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-3">
                            <input
                                ref="fileInput"
                                type="file"
                                class="hidden"
                                @change="onFileChange"
                            />

                            <Button
                                v-if="
                                    canCreateInCurrentDirectory ||
                                    props.can.createRoot
                                "
                                type="button"
                                variant="outline"
                                class="rounded-2xl"
                                @click="openCreateDirectoryDialog"
                            >
                                <FolderPlus class="size-4" />
                                {{ t.files.new_folder }}
                            </Button>

                            <Button
                                v-if="props.activeDirectory.can_edit"
                                type="button"
                                variant="outline"
                                class="rounded-2xl"
                                @click="openCreateFileDialog"
                            >
                                <FileText class="size-4" />
                                {{ t.files.new_file }}
                            </Button>

                            <Button
                                v-if="props.activeDirectory.can_edit"
                                type="button"
                                variant="outline"
                                class="rounded-2xl"
                                @click="openFilePicker"
                            >
                                <Upload class="size-4" />
                                {{ t.files.upload_choose }}
                            </Button>

                            <Button
                                v-if="props.activeDirectory.can_edit"
                                type="button"
                                class="rounded-2xl"
                                @click="openCurrentDirectoryPermissions"
                            >
                                <LockKeyhole class="size-4" />
                                {{ t.files.manage_access }}
                            </Button>
                        </div>
                    </div>
                </div>

                <div
                    class="relative min-h-[680px] overflow-hidden bg-[radial-gradient(circle_at_top_right,rgba(14,165,233,0.12),transparent_28%),radial-gradient(circle_at_left_center,rgba(34,197,94,0.10),transparent_24%),linear-gradient(180deg,rgba(255,255,255,0.96),rgba(244,247,251,0.98))] dark:bg-[radial-gradient(circle_at_top_right,rgba(14,165,233,0.18),transparent_30%),radial-gradient(circle_at_left_center,rgba(34,197,94,0.15),transparent_25%),linear-gradient(180deg,rgba(15,23,42,0.95),rgba(2,6,23,0.98))]"
                    @contextmenu.prevent="openWorkspaceContextMenu($event)"
                    @click="clearDesktopSelection"
                >
                    <div
                        class="absolute inset-0 bg-[linear-gradient(rgba(255,255,255,0.2)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.2)_1px,transparent_1px)] bg-[size:36px_36px] opacity-35 dark:opacity-10"
                    />

                    <div
                        class="relative grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_280px] xl:p-6"
                    >
                        <div
                            class="overflow-hidden rounded-[30px] border border-white/60 bg-background/40 shadow-[0_24px_60px_-32px_rgba(15,23,42,0.45)] backdrop-blur-md dark:border-white/10"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-4 border-b border-border/60 px-5 py-4"
                            >
                                <div class="space-y-2">
                                    <div
                                        class="inline-flex items-center gap-2 rounded-full border border-border/70 bg-background/80 px-3 py-1 text-xs font-medium"
                                    >
                                        <Monitor class="size-4 text-primary" />
                                        {{ t.files.desktop_title }}
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium">
                                            {{ t.files.desktop_description }}
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            {{ t.files.desktop_hint }}
                                        </p>
                                    </div>
                                </div>

                                <div
                                    class="text-right text-xs text-muted-foreground"
                                >
                                    <div>
                                        {{ currentItemCount }} ·
                                        {{
                                            t.files.workspace_items.toLowerCase()
                                        }}
                                    </div>
                                    <div class="mt-1">
                                        {{ t.files.right_click_hint }}
                                    </div>
                                </div>
                            </div>

                            <div
                                v-if="uploadForm.progress"
                                class="border-b border-border/60 px-5 py-4"
                            >
                                <div
                                    class="flex items-center justify-between gap-4 text-sm"
                                >
                                    <span class="font-medium">{{
                                        t.files.upload_progress
                                    }}</span>
                                    <span class="text-muted-foreground"
                                        >{{ Math.round(uploadProgress) }}%</span
                                    >
                                </div>
                                <div class="mt-3 h-2 rounded-full bg-muted">
                                    <div
                                        class="h-2 rounded-full bg-primary transition-all duration-200"
                                        :style="{ width: `${uploadProgress}%` }"
                                    />
                                </div>
                            </div>

                            <div class="px-5 pt-4">
                                <InputError :message="uploadForm.errors.file" />
                            </div>

                            <div class="relative min-h-[500px] p-5">
                                <div
                                    v-if="isDropActive"
                                    class="pointer-events-none absolute inset-5 z-10 flex items-center justify-center rounded-[26px] border-2 border-dashed border-primary bg-primary/10 backdrop-blur-sm"
                                >
                                    <div class="text-center">
                                        <Upload
                                            class="mx-auto size-8 text-primary"
                                        />
                                        <div
                                            class="mt-3 text-lg font-semibold text-foreground"
                                        >
                                            {{ t.files.upload_drop_title }}
                                        </div>
                                        <div
                                            class="mt-1 text-sm text-muted-foreground"
                                        >
                                            {{
                                                t.files.upload_drop_description
                                            }}
                                        </div>
                                    </div>
                                </div>

                                <div
                                    v-if="desktopItems.length === 0"
                                    class="flex min-h-[440px] flex-col items-center justify-center rounded-[26px] border border-dashed border-border/80 bg-background/35 px-6 text-center"
                                >
                                    <div
                                        class="flex size-16 items-center justify-center rounded-[22px] bg-primary/10 text-primary"
                                    >
                                        <FolderOpen class="size-7" />
                                    </div>
                                    <h2 class="mt-5 text-xl font-semibold">
                                        {{ t.files.empty_folder_title }}
                                    </h2>
                                    <p
                                        class="mt-3 max-w-2xl text-sm leading-6 text-muted-foreground"
                                    >
                                        {{ t.files.empty_folder_description }}
                                    </p>
                                    <div
                                        class="mt-6 flex flex-wrap justify-center gap-3"
                                    >
                                        <Button
                                            v-if="
                                                props.activeDirectory.can_edit
                                            "
                                            type="button"
                                            class="rounded-2xl"
                                            @click.stop="
                                                openCreateDirectoryDialog
                                            "
                                        >
                                            <FolderPlus class="size-4" />
                                            {{ t.files.new_folder }}
                                        </Button>
                                        <Button
                                            v-if="
                                                props.activeDirectory.can_edit
                                            "
                                            type="button"
                                            variant="outline"
                                            class="rounded-2xl"
                                            @click.stop="openCreateFileDialog"
                                        >
                                            <FileText class="size-4" />
                                            {{ t.files.new_file }}
                                        </Button>
                                    </div>
                                </div>

                                <div
                                    v-else
                                    class="grid auto-rows-[152px] grid-cols-[repeat(auto-fill,minmax(124px,1fr))] gap-4"
                                >
                                    <button
                                        v-for="item in desktopItems"
                                        :key="`${item.kind}-${item.id}`"
                                        type="button"
                                        class="group flex h-full flex-col items-center rounded-[24px] border border-transparent px-3 py-4 text-center transition hover:border-primary/20 hover:bg-background/55"
                                        :class="
                                            isSelectedDesktopItem(item)
                                                ? 'border-primary/30 bg-background/70 shadow-sm'
                                                : ''
                                        "
                                        @click.stop="selectDesktopItem(item)"
                                        @dblclick.stop="
                                            activateDesktopItem(item)
                                        "
                                        @contextmenu.prevent.stop="
                                            openDesktopItemContextMenu(
                                                $event,
                                                item,
                                            )
                                        "
                                    >
                                        <div
                                            class="flex size-16 items-center justify-center rounded-[22px] shadow-sm transition"
                                            :class="
                                                item.kind === 'directory'
                                                    ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-200'
                                                    : 'bg-sky-100 text-sky-700 dark:bg-sky-500/20 dark:text-sky-200'
                                            "
                                        >
                                            <FolderOpen
                                                v-if="item.kind === 'directory'"
                                                class="size-8"
                                            />
                                            <FileText v-else class="size-8" />
                                        </div>

                                        <div
                                            class="mt-4 line-clamp-2 text-sm leading-5 font-semibold"
                                        >
                                            {{ item.name }}
                                        </div>

                                        <div
                                            v-if="item.kind === 'directory'"
                                            class="mt-2 text-xs text-muted-foreground"
                                        >
                                            {{
                                                directoryMetaLabel({
                                                    files_count:
                                                        item.filesCount,
                                                    children_count:
                                                        item.childrenCount,
                                                })
                                            }}
                                        </div>
                                        <template v-else>
                                            <div
                                                class="mt-2 text-xs text-muted-foreground"
                                            >
                                                {{ desktopFileMeta(item) }}
                                            </div>
                                            <div
                                                v-if="desktopFileDetails(item)"
                                                class="mt-1 line-clamp-2 text-[11px] text-muted-foreground"
                                            >
                                                {{ desktopFileDetails(item) }}
                                            </div>
                                        </template>
                                    </button>
                                </div>
                            </div>

                            <div
                                class="flex flex-wrap items-center justify-between gap-3 border-t border-border/60 px-5 py-4 text-xs text-muted-foreground"
                            >
                                <div>{{ selectedItemLabel }}</div>
                                <div>{{ desktopStatusLabel }}</div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div
                                class="rounded-[28px] border border-border/70 bg-background/70 p-5 backdrop-blur-sm"
                            >
                                <div
                                    class="flex flex-wrap gap-2 text-sm text-muted-foreground"
                                >
                                    <div
                                        class="inline-flex items-center gap-2 rounded-full border border-border bg-background/80 px-3 py-1.5"
                                    >
                                        <FolderOpen class="size-4" />
                                        {{
                                            props.activeDirectory.children
                                                .length
                                        }}
                                        {{
                                            t.files.folders_title.toLowerCase()
                                        }}
                                    </div>
                                    <div
                                        class="inline-flex items-center gap-2 rounded-full border border-border bg-background/80 px-3 py-1.5"
                                    >
                                        <FileText class="size-4" />
                                        {{
                                            props.activeDirectory.entries.length
                                        }}
                                        {{ t.files.files_list.toLowerCase() }}
                                    </div>
                                    <div
                                        class="inline-flex items-center gap-2 rounded-full border border-border bg-background/80 px-3 py-1.5"
                                    >
                                        <Shield class="size-4" />
                                        {{
                                            permissionLabel(
                                                props.activeDirectory
                                                    .permission_level,
                                            )
                                        }}
                                    </div>
                                </div>

                                <div
                                    v-if="props.activeDirectory.owner"
                                    class="mt-4 rounded-2xl border border-border/70 bg-background/70 p-4"
                                >
                                    <div
                                        class="text-xs tracking-[0.16em] text-muted-foreground uppercase"
                                    >
                                        {{ t.files.owner }}
                                    </div>
                                    <div
                                        class="mt-2 flex items-center gap-2 text-sm font-medium"
                                    >
                                        <Users class="size-4 text-primary" />
                                        {{ props.activeDirectory.owner.name }}
                                    </div>
                                </div>

                                <div
                                    class="mt-4 rounded-2xl border border-dashed border-border/80 bg-background/55 p-4 text-sm text-muted-foreground"
                                >
                                    <div class="font-medium text-foreground">
                                        {{ t.files.upload_drop_title }}
                                    </div>
                                    <div class="mt-1">
                                        {{
                                            props.activeDirectory.can_edit
                                                ? t.files
                                                      .upload_drop_description
                                                : t.files.readonly_notice
                                        }}
                                    </div>
                                </div>
                            </div>

                            <div
                                class="rounded-[28px] border border-border/70 bg-background/70 p-5 backdrop-blur-sm"
                            >
                                <div
                                    class="text-xs tracking-[0.16em] text-muted-foreground uppercase"
                                >
                                    {{ t.files.permissions_title }}
                                </div>
                                <p
                                    class="mt-3 text-sm leading-6 text-muted-foreground"
                                >
                                    {{ t.files.manage_access_description }}
                                </p>
                                <Button
                                    v-if="props.activeDirectory.can_edit"
                                    type="button"
                                    class="mt-4 w-full rounded-2xl"
                                    @click="openCurrentDirectoryPermissions"
                                >
                                    <LockKeyhole class="size-4" />
                                    {{ t.files.manage_access }}
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section
                v-else
                class="rounded-[32px] border border-dashed border-border bg-card p-8 text-center"
            >
                <div
                    class="mx-auto flex size-14 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                >
                    <HardDrive class="size-6" />
                </div>
                <h1 class="mt-4 text-2xl font-semibold tracking-tight">
                    {{ t.files.empty_title }}
                </h1>
                <p
                    class="mx-auto mt-3 max-w-2xl text-sm leading-6 text-muted-foreground"
                >
                    {{ t.files.empty_description }}
                </p>
                <Button
                    v-if="props.can.createRoot"
                    type="button"
                    class="mt-6 rounded-2xl"
                    @click="openCreateDirectoryDialog"
                >
                    <FolderPlus class="size-4" />
                    {{ t.files.create_root }}
                </Button>
            </section>
        </div>
    </div>

    <Dialog
        :open="createDirectoryDialogOpen"
        @update:open="
            (isOpen) => {
                if (!isOpen) closeCreateDirectoryDialog();
            }
        "
    >
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t.files.new_folder }}</DialogTitle>
                <DialogDescription>
                    {{ t.files.new_folder_description }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitDirectory">
                <div class="grid gap-2">
                    <Label for="directory-name">{{
                        t.files.directory_name
                    }}</Label>
                    <Input
                        id="directory-name"
                        v-model="directoryForm.name"
                        :placeholder="t.files.directory_name_placeholder"
                    />
                    <InputError :message="directoryForm.errors.name" />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        @click="closeCreateDirectoryDialog"
                    >
                        {{ t.common.cancel }}
                    </Button>
                    <Button type="submit" :disabled="directoryForm.processing">
                        <FolderPlus class="size-4" />
                        {{ t.files.directory_action }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="createFileDialogOpen"
        @update:open="
            (isOpen) => {
                if (!isOpen) closeCreateFileDialog();
            }
        "
    >
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t.files.new_file }}</DialogTitle>
                <DialogDescription>
                    {{ t.files.new_file_description }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="submitFile">
                <div class="grid gap-2">
                    <Label for="file-name">{{ t.files.file_name }}</Label>
                    <Input
                        id="file-name"
                        v-model="createFileForm.name"
                        :placeholder="t.files.file_name_placeholder"
                    />
                    <InputError :message="createFileForm.errors.name" />
                </div>

                <DialogFooter class="gap-2">
                    <Button
                        type="button"
                        variant="ghost"
                        @click="closeCreateFileDialog"
                    >
                        {{ t.common.cancel }}
                    </Button>
                    <Button type="submit" :disabled="createFileForm.processing">
                        <FileText class="size-4" />
                        {{ t.files.file_action }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog
        :open="permissionsDialogOpen"
        @update:open="
            (isOpen) => {
                if (!isOpen) closePermissionDialog();
            }
        "
    >
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    {{
                        selectedPermissionDirectory?.name ||
                        t.files.permissions_title
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{ t.files.manage_access_description }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="selectedPermissionDirectory" class="space-y-5">
                <form
                    v-if="selectedPermissionDirectory.canEdit"
                    class="grid gap-4 md:grid-cols-3"
                    @submit.prevent="submitPermission"
                >
                    <div class="grid gap-2">
                        <Label for="permission-subject-type">{{
                            t.files.subject_type
                        }}</Label>
                        <select
                            id="permission-subject-type"
                            v-model="permissionForm.subject_type"
                            class="h-11 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="user">
                                {{ t.files.subject_user }}
                            </option>
                            <option value="group">
                                {{ t.files.subject_group }}
                            </option>
                        </select>
                    </div>

                    <div class="grid gap-2">
                        <Label for="permission-target">
                            {{
                                permissionForm.subject_type === 'user'
                                    ? t.files.select_user
                                    : t.files.select_group
                            }}
                        </Label>
                        <select
                            id="permission-target"
                            v-if="permissionForm.subject_type === 'user'"
                            v-model="permissionForm.user_id"
                            class="h-11 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option :value="null">
                                {{ t.files.select_user }}
                            </option>
                            <option
                                v-for="availableUser in props.availableUsers"
                                :key="availableUser.id"
                                :value="availableUser.id"
                            >
                                {{ availableUser.name }}
                            </option>
                        </select>
                        <select
                            v-else
                            v-model="permissionForm.user_group_id"
                            class="h-11 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option :value="null">
                                {{ t.files.select_group }}
                            </option>
                            <option
                                v-for="availableGroup in props.availableGroups"
                                :key="availableGroup.id"
                                :value="availableGroup.id"
                            >
                                {{ availableGroup.display_name }}
                            </option>
                        </select>
                        <InputError
                            :message="
                                permissionForm.errors.user_id ||
                                permissionForm.errors.user_group_id
                            "
                        />
                    </div>

                    <div class="grid gap-2">
                        <Label for="permission-level">{{
                            t.files.access_level
                        }}</Label>
                        <select
                            id="permission-level"
                            v-model="permissionForm.access_level"
                            class="h-11 rounded-md border border-input bg-transparent px-3 text-sm shadow-xs transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        >
                            <option value="read">{{ t.files.read }}</option>
                            <option value="edit">{{ t.files.edit }}</option>
                        </select>
                    </div>

                    <div class="flex justify-end md:col-span-3">
                        <Button
                            type="submit"
                            :disabled="permissionForm.processing"
                        >
                            <LockKeyhole class="size-4" />
                            {{ t.files.grant_access }}
                        </Button>
                    </div>
                </form>

                <div
                    v-else
                    class="rounded-2xl border border-dashed border-border bg-muted/20 p-4 text-sm text-muted-foreground"
                >
                    {{ t.files.readonly_notice }}
                </div>

                <div class="space-y-3">
                    <h3 class="text-sm font-semibold text-foreground">
                        {{ t.files.permissions_title }}
                    </h3>

                    <div
                        v-if="selectedPermissionEntries.length === 0"
                        class="rounded-2xl border border-dashed border-border bg-muted/20 p-4 text-sm text-muted-foreground"
                    >
                        {{ t.files.no_permissions }}
                    </div>

                    <article
                        v-for="permission in selectedPermissionEntries"
                        :key="permission.id"
                        class="flex flex-col gap-3 rounded-2xl border border-border bg-background/80 p-4 md:flex-row md:items-center md:justify-between"
                    >
                        <div class="space-y-1">
                            <div class="text-sm font-medium">
                                {{ permission.subject_name }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{
                                    permission.subject_type === 'user'
                                        ? t.files.subject_user
                                        : t.files.subject_group
                                }}
                                ·
                                {{
                                    permission.access_level === 'edit'
                                        ? t.files.edit
                                        : t.files.read
                                }}
                            </div>
                            <div class="text-xs text-muted-foreground">
                                {{ t.files.granted_by }}:
                                {{ permission.granted_by_name || '—' }}
                            </div>
                        </div>

                        <Button
                            v-if="selectedPermissionDirectory.canEdit"
                            type="button"
                            variant="ghost"
                            size="sm"
                            @click="removePermission(permission.id)"
                        >
                            <Trash2 class="size-4" />
                            {{ t.files.delete }}
                        </Button>
                    </article>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <div
        v-if="contextMenu.open && contextMenu.target"
        class="fixed inset-0 z-50"
        @click="closeContextMenu"
    >
        <div
            data-file-context-menu
            class="absolute w-56 rounded-2xl border border-border bg-popover p-2 text-popover-foreground shadow-2xl"
            :style="contextMenuStyle"
            @click.stop
        >
            <template v-if="contextMenu.target.type === 'workspace'">
                <template v-if="contextMenu.target.canEdit">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm transition hover:bg-muted"
                        @click="openCreateDirectoryDialog"
                    >
                        <FolderPlus class="size-4" />
                        {{ t.files.new_folder }}
                    </button>
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm transition hover:bg-muted"
                        @click="openCreateFileDialog"
                    >
                        <FileText class="size-4" />
                        {{ t.files.new_file }}
                    </button>
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm transition hover:bg-muted"
                        @click="openFilePicker"
                    >
                        <Upload class="size-4" />
                        {{ t.files.upload_file }}
                    </button>
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm transition hover:bg-muted"
                        @click="openCurrentDirectoryPermissions"
                    >
                        <LockKeyhole class="size-4" />
                        {{ t.files.manage_access }}
                    </button>
                </template>
                <div
                    v-else
                    class="rounded-xl px-3 py-2 text-sm text-muted-foreground"
                >
                    {{ t.files.readonly_notice }}
                </div>
            </template>

            <template v-else-if="contextMenu.target.type === 'directory'">
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm transition hover:bg-muted"
                    @click="visitDirectory(contextMenu.target.id)"
                >
                    <FolderOpen class="size-4" />
                    {{ t.files.open_directory }}
                </button>
                <button
                    v-if="contextMenu.target.canEdit"
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm transition hover:bg-muted"
                    @click="openPermissionDialog(contextMenu.target)"
                >
                    <LockKeyhole class="size-4" />
                    {{ t.files.manage_access }}
                </button>
                <button
                    v-if="contextMenu.target.canEdit"
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm text-destructive transition hover:bg-destructive/10"
                    @click="removeDirectory(contextMenu.target.id)"
                >
                    <Trash2 class="size-4" />
                    {{ t.files.delete }}
                </button>
            </template>

            <template v-else>
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm transition hover:bg-muted"
                    @click="downloadFile(contextMenu.target.downloadUrl)"
                >
                    <Download class="size-4" />
                    {{ t.files.open_file }}
                </button>
                <button
                    v-if="contextMenu.target.canEdit"
                    type="button"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-left text-sm text-destructive transition hover:bg-destructive/10"
                    @click="removeFile(contextMenu.target.id)"
                >
                    <Trash2 class="size-4" />
                    {{ t.files.delete }}
                </button>
            </template>
        </div>
    </div>
</template>

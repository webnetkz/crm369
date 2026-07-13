<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { Download, Eye, Hash, Package, PencilLine, Plus, Printer, Upload, UserCog, Wrench } from '@lucide/vue';
import { computed, ref, watch, watchEffect } from 'vue';
import {
    downloadCsvTemplate,
    exportCsv,
    importCsv,
} from '@/actions/App/Http/Controllers/EquipmentController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useLanguage } from '@/composables/useLanguage';
import {
    index as equipmentIndex,
    store as storeEquipment,
    update as updateEquipment,
} from '@/routes/equipment';

type EquipmentUser = {
    id: number;
    name: string;
    last_name: string | null;
    email: string;
};

type EquipmentItem = {
    id: number;
    name: string;
    qr_code: string;
    qr_code_svg_data_uri: string;
    status: string;
    status_label: string;
    issued_to_user: EquipmentUser | null;
    responsible_user: EquipmentUser | null;
    updated_at: string | null;
};

type StatusOption = {
    value: string;
    label: string;
    description: string;
};

const props = defineProps<{
    equipmentItems: EquipmentItem[];
    availableUsers: EquipmentUser[];
    statusOptions: StatusOption[];
    stats: {
        total: number;
        on_balance: number;
        issued: number;
        maintenance: number;
        written_off: number;
    };
    activeEquipmentItem: EquipmentItem | null;
}>();

const { t } = useLanguage();

const emptyUserValue = '__none__';
const defaultStatus = props.statusOptions[0]?.value ?? 'on_balance';

const form = useForm({
    name: '',
    qr_code: '',
    status: defaultStatus,
    responsible_user_id: emptyUserValue,
    issued_to_user_id: emptyUserValue,
});
const csvImportForm = useForm({
    delimiter: ';',
    file: null as File | null,
});

const dialogOpen = ref(false);
const editingEquipmentId = ref<number | null>(null);
const detailsDialogOpen = ref(false);
const selectedEquipmentItem = ref<EquipmentItem | null>(null);
const csvImportInput = ref<HTMLInputElement | null>(null);

const isEditing = computed(() => editingEquipmentId.value !== null);

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.equipment.title,
                href: equipmentIndex(),
            },
        ],
    });
});

watch(
    () => props.activeEquipmentItem,
    (equipmentItem) => {
        if (! equipmentItem) {
            return;
        }

        selectedEquipmentItem.value = equipmentItem;
        detailsDialogOpen.value = true;
    },
    { immediate: true },
);

const resetForm = (): void => {
    editingEquipmentId.value = null;
    form.name = '';
    form.qr_code = '';
    form.status = defaultStatus;
    form.responsible_user_id = emptyUserValue;
    form.issued_to_user_id = emptyUserValue;
    form.clearErrors();
};

const closeDialog = (): void => {
    dialogOpen.value = false;
    resetForm();
};

const closeDetailsDialog = (): void => {
    detailsDialogOpen.value = false;
    selectedEquipmentItem.value = null;
};

const openCreateDialog = (): void => {
    resetForm();
    dialogOpen.value = true;
};

const openDetailsDialog = (equipmentItem: EquipmentItem): void => {
    selectedEquipmentItem.value = equipmentItem;
    detailsDialogOpen.value = true;
};

const openEditDialog = (equipmentItem: EquipmentItem): void => {
    editingEquipmentId.value = equipmentItem.id;
    form.name = equipmentItem.name;
    form.qr_code = equipmentItem.qr_code;
    form.status = equipmentItem.status;
    form.responsible_user_id = equipmentItem.responsible_user
        ? String(equipmentItem.responsible_user.id)
        : emptyUserValue;
    form.issued_to_user_id = equipmentItem.issued_to_user
        ? String(equipmentItem.issued_to_user.id)
        : emptyUserValue;
    form.clearErrors();
    dialogOpen.value = true;
};

const submitForm = (): void => {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            closeDialog();
        },
    };

    if (editingEquipmentId.value === null) {
        form.post(storeEquipment.url(), options);

        return;
    }

    form.patch(updateEquipment.url(editingEquipmentId.value), options);
};

const statusMeta = (status: string) => {
    switch (status) {
        case 'issued':
            return 'bg-primary/10 text-primary';
        case 'maintenance':
        case 'repair':
            return 'bg-amber-500/12 text-amber-700 dark:text-amber-300';
        case 'written_off':
            return 'bg-rose-500/12 text-rose-700 dark:text-rose-300';
        default:
            return 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300';
    }
};

const userLabel = (user: EquipmentUser | null, emptyLabel: string): string => {
    if (! user) {
        return emptyLabel;
    }

    return [user.name, user.last_name].filter(Boolean).join(' ') || user.email;
};

const formattedDate = (value: string | null): string => {
    if (! value) {
        return '—';
    }

    return new Date(value).toLocaleString();
};

const escapeHtml = (value: string): string => {
    return value
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
};

const printableStatusLabel = (equipmentItem: EquipmentItem): string | null => {
    if (equipmentItem.status === 'issued') {
        return null;
    }

    return escapeHtml(equipmentItem.status_label);
};

const printEquipmentQr = (): void => {
    if (! selectedEquipmentItem.value) {
        return;
    }

    const equipmentItem = selectedEquipmentItem.value;
    const title = escapeHtml(equipmentItem.name);
    const qrCode = equipmentItem.qr_code_svg_data_uri;
    const qrCodeValue = escapeHtml(equipmentItem.qr_code);
    const statusLabel = printableStatusLabel(equipmentItem);
    const locale = document.documentElement.lang || 'en';
    const printMarkup = `<!doctype html>
<html lang="${escapeHtml(locale)}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <title>${title}</title>
        <style>
            :root {
                color-scheme: light;
                font-family: "Instrument Sans", Arial, sans-serif;
            }

            body {
                margin: 0;
                min-height: 100vh;
                display: grid;
                place-items: center;
                background: #f8fafc;
                color: #0f172a;
            }

            .card {
                width: min(420px, calc(100vw - 48px));
                border: 1px solid #cbd5e1;
                border-radius: 24px;
                background: #ffffff;
                padding: 28px;
                box-sizing: border-box;
                text-align: center;
            }

            .title {
                font-size: 24px;
                font-weight: 700;
                line-height: 1.2;
            }

            .status {
                margin-top: 10px;
                font-size: 14px;
                color: #475569;
            }

            .qr {
                margin: 24px auto 0;
                width: 240px;
                height: 240px;
                display: block;
                border: 1px solid #e2e8f0;
                border-radius: 24px;
                background: #ffffff;
                padding: 16px;
                box-sizing: border-box;
            }

            .code {
                margin-top: 18px;
                font-size: 14px;
                font-weight: 600;
                letter-spacing: 0.08em;
            }

            @media print {
                body {
                    background: #ffffff;
                }

                .card {
                    width: auto;
                    border-color: #e2e8f0;
                    box-shadow: none;
                }
            }
        </style>
    </head>
    <body>
        <main class="card">
            <div class="title">${title}</div>
            ${statusLabel ? `<div class="status">${statusLabel}</div>` : ''}
            <img class="qr" src="${qrCode}" alt="QR code ${qrCodeValue}" />
            <div class="code">${qrCodeValue}</div>
        </main>
    </body>
</html>`;

    const printFrame = document.createElement('iframe');

    printFrame.setAttribute('aria-hidden', 'true');
    printFrame.className = 'pointer-events-none fixed bottom-0 right-0 h-0 w-0 border-0 opacity-0';

    const cleanup = (): void => {
        printFrame.remove();
    };

    printFrame.onload = () => {
        const frameWindow = printFrame.contentWindow;

        if (! frameWindow) {
            cleanup();

            return;
        }

        frameWindow.onafterprint = cleanup;

        window.setTimeout(() => {
            frameWindow.focus();
            frameWindow.print();
            window.setTimeout(cleanup, 1_000);
        }, 150);
    };

    document.body.append(printFrame);
    printFrame.srcdoc = printMarkup;
};

const downloadEquipmentCsv = (): void => {
    window.location.assign(
        exportCsv.url({
            query: {
                delimiter: csvImportForm.delimiter,
            },
        }),
    );
};

const downloadEquipmentCsvTemplate = (): void => {
    window.location.assign(
        downloadCsvTemplate.url({
            query: {
                delimiter: csvImportForm.delimiter,
            },
        }),
    );
};

const openEquipmentCsvImport = (): void => {
    csvImportForm.clearErrors();
    csvImportForm.file = null;

    if (csvImportInput.value) {
        csvImportInput.value.value = '';
        csvImportInput.value.click();
    }
};

const handleEquipmentCsvFileChange = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    csvImportForm.file = input.files?.[0] ?? null;
};

const submitEquipmentCsvImport = (): void => {
    if (csvImportForm.file === null) {
        return;
    }

    csvImportForm.post(importCsv.url(), {
        preserveScroll: true,
        onSuccess: () => {
            csvImportForm.reset();
        },
        onFinish: () => {
            if (csvImportInput.value) {
                csvImportInput.value.value = '';
            }
        },
    });
};

const maintenanceStatusDescription = computed(() => {
    return props.statusOptions
        .filter((statusOption) => ['maintenance', 'repair'].includes(statusOption.value))
        .map((statusOption) => statusOption.label)
        .join(' / ');
});
</script>

<template>
    <Head :title="t.equipment.title" />

    <input
        ref="csvImportInput"
        type="file"
        accept=".csv,text/csv"
        class="hidden"
        @change="handleEquipmentCsvFileChange"
    />

    <h1 class="sr-only">{{ t.equipment.title }}</h1>

    <div class="space-y-8">
        <div
            class="flex flex-col gap-4 rounded-3xl border border-border bg-card p-6"
        >
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <Heading
                    variant="small"
                    :title="t.equipment.title"
                    :description="t.equipment.description"
                />

                <div class="flex flex-wrap gap-2">
                    <Button type="button" variant="outline" @click="downloadEquipmentCsv">
                        <Download class="size-4" />
                        {{ t.equipment.csv_export }}
                    </Button>
                    <Button type="button" variant="outline" @click="downloadEquipmentCsvTemplate">
                        <Download class="size-4" />
                        {{ t.equipment.csv_download_template }}
                    </Button>
                    <Button type="button" variant="outline" @click="openEquipmentCsvImport">
                        <Upload class="size-4" />
                        {{ t.equipment.csv_import }}
                    </Button>
                    <Button type="button" class="gap-2 self-start" @click="openCreateDialog">
                        <Plus class="size-4" />
                        <span>{{ t.equipment.create_item }}</span>
                    </Button>
                </div>
            </div>

            <div class="grid gap-2 rounded-2xl border border-dashed border-border p-4">
                <p class="text-sm text-muted-foreground">
                    {{ t.equipment.csv_description }}
                </p>
                <div class="flex flex-col gap-2 md:flex-row md:items-end">
                    <div class="grid gap-2">
                        <Label for="equipment-csv-delimiter">
                            {{ t.equipment.csv_delimiter }}
                        </Label>
                        <Input
                            id="equipment-csv-delimiter"
                            v-model="csvImportForm.delimiter"
                            :placeholder="t.equipment.csv_delimiter_placeholder"
                            class="w-28"
                        />
                    </div>
                    <Button
                        type="button"
                        :disabled="csvImportForm.processing || csvImportForm.file === null"
                        @click="submitEquipmentCsvImport"
                    >
                        <Upload class="size-4" />
                        {{ t.equipment.csv_import }}
                    </Button>
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ t.equipment.csv_delimiter_hint }}
                </p>
                <InputError :message="csvImportForm.errors.delimiter" />
                <InputError :message="csvImportForm.errors.file" />
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                    <Package class="size-4" />
                    <span>{{ t.equipment.summary.total }}</span>
                </div>
                <div class="mt-3 text-3xl font-semibold tracking-tight">
                    {{ stats.total }}
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                    <Package class="size-4" />
                    <span>{{ t.equipment.summary.on_balance }}</span>
                </div>
                <div class="mt-3 text-3xl font-semibold tracking-tight">
                    {{ stats.on_balance }}
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                    <UserCog class="size-4" />
                    <span>{{ t.equipment.summary.issued }}</span>
                </div>
                <div class="mt-3 text-3xl font-semibold tracking-tight">
                    {{ stats.issued }}
                </div>
            </div>

            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                    <Wrench class="size-4" />
                    <span>{{ t.equipment.summary.maintenance }}</span>
                </div>
                <div class="mt-3 text-3xl font-semibold tracking-tight">
                    {{ stats.maintenance }}
                </div>
                <p class="mt-2 text-xs text-muted-foreground">
                    {{ maintenanceStatusDescription }}
                </p>
            </div>

            <div class="rounded-2xl border border-border bg-card p-5">
                <div class="flex items-center gap-2 text-sm font-medium text-muted-foreground">
                    <Hash class="size-4" />
                    <span>{{ t.equipment.summary.written_off }}</span>
                </div>
                <div class="mt-3 text-3xl font-semibold tracking-tight">
                    {{ stats.written_off }}
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-border bg-card">
            <div
                v-if="equipmentItems.length === 0"
                class="space-y-2 px-6 py-12 text-center"
            >
                <h2 class="text-lg font-semibold">{{ t.equipment.empty_title }}</h2>
                <p class="mx-auto max-w-2xl text-sm leading-6 text-muted-foreground">
                    {{ t.equipment.empty_description }}
                </p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full divide-y divide-border text-sm">
                    <thead class="bg-muted/40">
                        <tr class="text-left">
                            <th class="px-6 py-4 font-medium text-muted-foreground">
                                {{ t.equipment.name }}
                            </th>
                            <th class="px-6 py-4 font-medium text-muted-foreground">
                                {{ t.equipment.qr_code }}
                            </th>
                            <th class="px-6 py-4 font-medium text-muted-foreground">
                                {{ t.equipment.status }}
                            </th>
                            <th class="px-6 py-4 font-medium text-muted-foreground">
                                {{ t.equipment.responsible_user }}
                            </th>
                            <th class="px-6 py-4 font-medium text-muted-foreground">
                                {{ t.equipment.issued_to_user }}
                            </th>
                            <th class="px-6 py-4 font-medium text-muted-foreground">
                                {{ t.equipment.last_updated }}
                            </th>
                            <th class="px-6 py-4 text-right font-medium text-muted-foreground">
                                {{ t.equipment.actions }}
                            </th>
                        </tr>
                    </thead>

                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="equipmentItem in equipmentItems"
                            :key="equipmentItem.id"
                            class="align-top"
                        >
                            <td class="px-6 py-4">
                                <div class="font-medium text-foreground">
                                    {{ equipmentItem.name }}
                                </div>
                            </td>

                            <td class="px-6 py-4">
                                <code class="rounded-md bg-muted px-2 py-1 text-xs">
                                    {{ equipmentItem.qr_code }}
                                </code>
                            </td>

                            <td class="px-6 py-4">
                                <Badge
                                    variant="secondary"
                                    :class="statusMeta(equipmentItem.status)"
                                >
                                    {{ equipmentItem.status_label }}
                                </Badge>
                            </td>

                            <td class="px-6 py-4 text-muted-foreground">
                                {{
                                    userLabel(
                                        equipmentItem.responsible_user,
                                        t.equipment.not_assigned,
                                    )
                                }}
                            </td>

                            <td class="px-6 py-4 text-muted-foreground">
                                {{
                                    userLabel(
                                        equipmentItem.issued_to_user,
                                        t.equipment.not_issued,
                                    )
                                }}
                            </td>

                            <td class="px-6 py-4 text-muted-foreground">
                                {{ formattedDate(equipmentItem.updated_at) }}
                            </td>

                            <td class="px-6 py-4">
                                <div class="flex justify-end gap-2">
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        class="gap-2"
                                        @click="openDetailsDialog(equipmentItem)"
                                    >
                                        <Eye class="size-4" />
                                        <span>{{ t.equipment.view }}</span>
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        class="gap-2"
                                        @click="openEditDialog(equipmentItem)"
                                    >
                                        <PencilLine class="size-4" />
                                        <span>{{ t.equipment.edit }}</span>
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <Dialog
        :open="detailsDialogOpen"
        @update:open="(value) => (value ? (detailsDialogOpen = true) : closeDetailsDialog())"
    >
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t.equipment.details_title }}</DialogTitle>
                <DialogDescription>
                    {{ t.equipment.details_description }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="selectedEquipmentItem" class="space-y-6">
                <div
                    class="rounded-3xl border border-border bg-muted/30 p-6"
                >
                    <div class="text-center">
                        <h2 class="text-xl font-semibold tracking-tight">
                            {{ selectedEquipmentItem.name }}
                        </h2>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ selectedEquipmentItem.status_label }}
                        </p>
                    </div>

                    <div class="mt-6 flex justify-center">
                        <div
                            class="overflow-hidden rounded-[1.75rem] border border-border bg-white p-4 shadow-sm"
                        >
                            <img
                                :src="selectedEquipmentItem.qr_code_svg_data_uri"
                                :alt="`${t.equipment.qr_code}: ${selectedEquipmentItem.qr_code}`"
                                class="size-52"
                            />
                        </div>
                    </div>

                    <div class="mt-4 text-center">
                        <code class="rounded-md bg-background px-3 py-1.5 text-sm font-medium">
                            {{ selectedEquipmentItem.qr_code }}
                        </code>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-border bg-card p-4">
                        <div class="text-sm text-muted-foreground">
                            {{ t.equipment.responsible_user }}
                        </div>
                        <div class="mt-1 font-medium">
                            {{
                                userLabel(
                                    selectedEquipmentItem.responsible_user,
                                    t.equipment.not_assigned,
                                )
                            }}
                        </div>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-4">
                        <div class="text-sm text-muted-foreground">
                            {{ t.equipment.issued_to_user }}
                        </div>
                        <div class="mt-1 font-medium">
                            {{
                                userLabel(
                                    selectedEquipmentItem.issued_to_user,
                                    t.equipment.not_issued,
                                )
                            }}
                        </div>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-4">
                        <div class="text-sm text-muted-foreground">
                            {{ t.equipment.status }}
                        </div>
                        <div class="mt-2">
                            <Badge
                                variant="secondary"
                                :class="statusMeta(selectedEquipmentItem.status)"
                            >
                                {{ selectedEquipmentItem.status_label }}
                            </Badge>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-4">
                        <div class="text-sm text-muted-foreground">
                            {{ t.equipment.last_updated }}
                        </div>
                        <div class="mt-1 font-medium">
                            {{ formattedDate(selectedEquipmentItem.updated_at) }}
                        </div>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        class="gap-2"
                        @click="printEquipmentQr"
                    >
                        <Printer class="size-4" />
                        <span>{{ t.equipment.print_qr }}</span>
                    </Button>
                    <Button type="button" variant="outline" @click="closeDetailsDialog">
                        {{ t.equipment.cancel }}
                    </Button>
                </DialogFooter>
            </div>
        </DialogContent>
    </Dialog>

    <Dialog :open="dialogOpen" @update:open="(value) => (value ? (dialogOpen = true) : closeDialog())">
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>
                    {{ isEditing ? t.equipment.edit_item : t.equipment.create_item }}
                </DialogTitle>
                <DialogDescription>
                    {{ t.equipment.description }}
                </DialogDescription>
            </DialogHeader>

            <form class="space-y-5" @submit.prevent="submitForm">
                <div class="grid gap-5 md:grid-cols-2">
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="equipment-name">{{ t.equipment.name }}</Label>
                        <Input
                            id="equipment-name"
                            v-model="form.name"
                            :placeholder="t.equipment.name_placeholder"
                            autocomplete="off"
                        />
                        <InputError :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="equipment-qr-code">{{ t.equipment.qr_code }}</Label>
                        <Input
                            id="equipment-qr-code"
                            v-model="form.qr_code"
                            :placeholder="t.equipment.qr_code_placeholder"
                            autocomplete="off"
                        />
                        <InputError :message="form.errors.qr_code" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="equipment-status">{{ t.equipment.status }}</Label>
                        <Select
                            :model-value="form.status"
                            @update:model-value="
                                (value) =>
                                    (form.status =
                                        typeof value === 'string'
                                            ? value
                                            : defaultStatus)
                            "
                        >
                            <SelectTrigger id="equipment-status" class="w-full">
                                <SelectValue :placeholder="t.equipment.status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="statusOption in statusOptions"
                                    :key="statusOption.value"
                                    :value="statusOption.value"
                                >
                                    <div class="space-y-1">
                                        <div>{{ statusOption.label }}</div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ statusOption.description }}
                                        </div>
                                    </div>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.status" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="equipment-responsible-user">
                            {{ t.equipment.responsible_user }}
                        </Label>
                        <Select
                            :model-value="form.responsible_user_id"
                            @update:model-value="
                                (value) =>
                                    (form.responsible_user_id =
                                        typeof value === 'string'
                                            ? value
                                            : emptyUserValue)
                            "
                        >
                            <SelectTrigger id="equipment-responsible-user" class="w-full">
                                <SelectValue :placeholder="t.equipment.not_assigned" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="emptyUserValue">
                                    {{ t.equipment.not_assigned }}
                                </SelectItem>
                                <SelectItem
                                    v-for="user in availableUsers"
                                    :key="user.id"
                                    :value="String(user.id)"
                                >
                                    {{ userLabel(user, user.email) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.responsible_user_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="equipment-issued-user">
                            {{ t.equipment.issued_to_user }}
                        </Label>
                        <Select
                            :model-value="form.issued_to_user_id"
                            @update:model-value="
                                (value) =>
                                    (form.issued_to_user_id =
                                        typeof value === 'string'
                                            ? value
                                            : emptyUserValue)
                            "
                        >
                            <SelectTrigger id="equipment-issued-user" class="w-full">
                                <SelectValue :placeholder="t.equipment.not_issued" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="emptyUserValue">
                                    {{ t.equipment.not_issued }}
                                </SelectItem>
                                <SelectItem
                                    v-for="user in availableUsers"
                                    :key="user.id"
                                    :value="String(user.id)"
                                >
                                    {{ userLabel(user, user.email) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.issued_to_user_id" />
                    </div>
                </div>

                <DialogFooter>
                    <Button type="button" variant="outline" @click="closeDialog">
                        {{ t.equipment.cancel }}
                    </Button>
                    <Button type="submit" :disabled="form.processing">
                        {{ t.equipment.save }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>

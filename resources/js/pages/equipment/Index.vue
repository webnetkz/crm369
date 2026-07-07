<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { Hash, Package, PencilLine, Plus, UserCog, Wrench } from '@lucide/vue';
import { computed, ref, watchEffect } from 'vue';
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

const dialogOpen = ref(false);
const editingEquipmentId = ref<number | null>(null);

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

const openCreateDialog = (): void => {
    resetForm();
    dialogOpen.value = true;
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

const maintenanceStatusDescription = computed(() => {
    return props.statusOptions
        .filter((statusOption) => ['maintenance', 'repair'].includes(statusOption.value))
        .map((statusOption) => statusOption.label)
        .join(' / ');
});
</script>

<template>
    <Head :title="t.equipment.title" />

    <h1 class="sr-only">{{ t.equipment.title }}</h1>

    <div class="space-y-8">
        <div
            class="flex flex-col gap-4 rounded-3xl border border-border bg-card p-6 lg:flex-row lg:items-start lg:justify-between"
        >
            <Heading
                variant="small"
                :title="t.equipment.title"
                :description="t.equipment.description"
            />

            <Button type="button" class="gap-2 self-start" @click="openCreateDialog">
                <Plus class="size-4" />
                <span>{{ t.equipment.create_item }}</span>
            </Button>
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
                                {{ t.equipment.edit }}
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

                            <td class="px-6 py-4 text-right">
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
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

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

<script setup lang="ts">
import {
    BadgeCheck,
    Briefcase,
    CircleX,
    Mail,
    Phone,
    UserRound,
    UsersRound,
} from '@lucide/vue';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { useInitials } from '@/composables/useInitials';
import { useLanguage } from '@/composables/useLanguage';
import type {
    CompanyStructureManagerOption,
    ManagedProfileFormState,
    ManagedProfileSaveState,
    ManagedUserProfile,
} from '@/types/ui';

type Props = {
    open: boolean;
    user: ManagedUserProfile | null;
    managerOptions?: CompanyStructureManagerOption[];
    canEdit?: boolean;
    saveState?: ManagedProfileSaveState;
};

const props = withDefaults(defineProps<Props>(), {
    canEdit: false,
    saveState: 'idle',
});
const form = defineModel<ManagedProfileFormState>('form', { required: true });

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
}>();

const { getInitials } = useInitials();
const { language, t } = useLanguage();

const avatarStyle = computed(() => ({
    objectPosition: 'center',
    transform: `scale(${props.user?.avatar_scale ?? 1})`,
}));

const availableManagerOptions = computed(() => {
    return (props.managerOptions ?? []).filter(
        (option) => option.id !== props.user?.id,
    );
});

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return t.value.common.not_specified;
    }

    return new Intl.DateTimeFormat(
        language.value === 'ru' ? 'ru-RU' : 'en-US',
        {
            dateStyle: 'medium',
            timeStyle: 'short',
        },
    ).format(new Date(value));
};

const formatUserName = (person: {
    name: string;
    last_name: string | null;
} | null): string => {
    if (!person) {
        return t.value.equipment.not_assigned;
    }

    return [person.name, person.last_name].filter(Boolean).join(' ');
};

const formatStructureUser = (person: {
    full_name: string;
    position: string | null;
} | null): string => {
    if (!person) {
        return t.value.company_structure.no_manager;
    }

    return person.position
        ? `${person.full_name} · ${person.position}`
        : person.full_name;
};
</script>

<template>
    <Sheet :open="open" @update:open="(isOpen) => emit('update:open', isOpen)">
        <SheetContent class="w-full sm:max-w-md md:max-w-lg">
            <SheetHeader class="border-b border-border px-6 py-6 text-left">
                <div class="flex items-start gap-4 pr-8">
                    <Avatar
                        class="size-18 overflow-hidden rounded-3xl border border-border shadow-sm"
                    >
                        <AvatarImage
                            v-if="user?.avatar"
                            :src="user.avatar"
                            :alt="user.name"
                            :style="avatarStyle"
                        />
                        <AvatarFallback
                            class="bg-muted text-lg font-semibold text-foreground"
                        >
                            {{ user ? getInitials(user.name) : '' }}
                        </AvatarFallback>
                    </Avatar>

                    <div class="min-w-0 space-y-3">
                        <div>
                            <SheetTitle class="truncate pr-2">
                                {{ user?.name }}
                            </SheetTitle>
                            <SheetDescription>
                                {{ t.admin.profile_description }}
                            </SheetDescription>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <span
                                v-if="user?.is_super_admin"
                                class="rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground"
                            >
                                {{ t.admin.super_admin }}
                            </span>
                            <span
                                v-if="user"
                                class="rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground"
                            >
                                {{
                                    user.group?.display_name ??
                                    t.admin.simple_user
                                }}
                            </span>
                            <span
                                v-if="user"
                                class="rounded-full px-2.5 py-1 text-xs"
                                :class="
                                    user.is_active
                                        ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                        : 'bg-destructive/10 text-destructive'
                                "
                            >
                                {{
                                    user.is_active
                                        ? t.admin.active
                                        : t.admin.inactive
                                }}
                            </span>
                        </div>
                    </div>
                </div>
            </SheetHeader>

            <div class="flex-1 space-y-6 overflow-y-auto px-6 py-6">
                <div
                    v-if="canEdit"
                    class="flex items-center justify-end text-xs text-muted-foreground"
                >
                    <span v-if="saveState === 'saving'">
                        {{ t.admin.profile_autosave_saving }}
                    </span>
                    <span
                        v-else-if="saveState === 'saved'"
                        class="text-emerald-600 dark:text-emerald-400"
                    >
                        {{ t.admin.profile_autosave_saved }}
                    </span>
                </div>

                <div class="grid gap-3">
                    <div class="rounded-2xl border border-border bg-card p-4">
                        <div
                            class="mb-1 flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <Mail class="size-4" />
                            {{ t.common.email }}
                        </div>
                        <template v-if="canEdit">
                            <Input
                                v-model="form.email"
                                type="email"
                                class="mt-2"
                                autocomplete="off"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.email"
                            />
                        </template>
                        <div v-else class="font-medium break-all">
                            {{ user?.email }}
                        </div>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-4">
                        <div
                            class="mb-1 flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <Phone class="size-4" />
                            {{ t.common.phone }}
                        </div>
                        <template v-if="canEdit">
                            <Input
                                v-model="form.phone"
                                type="tel"
                                class="mt-2"
                                inputmode="tel"
                                autocomplete="off"
                                :placeholder="t.profile.phone_placeholder"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.phone"
                            />
                        </template>
                        <div v-else class="font-medium">
                            {{ user?.phone ?? t.common.not_specified }}
                        </div>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-4">
                        <div
                            class="mb-1 flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <Briefcase class="size-4" />
                            {{ t.company_structure.position }}
                        </div>
                        <template v-if="canEdit">
                            <Input
                                v-model="form.position"
                                class="mt-2"
                                autocomplete="organization-title"
                                :placeholder="t.company_structure.no_position"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.position"
                            />
                        </template>
                        <div v-else class="font-medium">
                            {{
                                user?.position ??
                                t.company_structure.no_position
                            }}
                        </div>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-4">
                        <div
                            class="mb-1 flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <UserRound class="size-4" />
                            {{ t.company_structure.manager }}
                        </div>
                        <template v-if="canEdit">
                            <select
                                v-model="form.manager_id"
                                class="mt-2 h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">
                                    {{ t.company_structure.no_manager }}
                                </option>
                                <option
                                    v-for="option in availableManagerOptions"
                                    :key="option.id"
                                    :value="option.id"
                                >
                                    {{
                                        option.position
                                            ? `${option.full_name} · ${option.position}`
                                            : option.full_name
                                    }}
                                </option>
                            </select>
                            <InputError
                                class="mt-2"
                                :message="form.errors.manager_id"
                            />
                        </template>
                        <div v-else class="font-medium">
                            {{ formatStructureUser(user?.manager ?? null) }}
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div class="rounded-2xl border border-border bg-card p-4">
                        <div class="text-sm text-muted-foreground">
                            {{ t.common.name }}
                        </div>
                        <template v-if="canEdit">
                            <Input
                                v-model="form.name"
                                class="mt-2"
                                autocomplete="off"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.name"
                            />
                        </template>
                        <div v-else class="mt-1 font-medium">
                            {{ user?.name }}
                        </div>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-4">
                        <div class="text-sm text-muted-foreground">
                            {{ t.common.last_name }}
                        </div>
                        <template v-if="canEdit">
                            <Input
                                v-model="form.last_name"
                                class="mt-2"
                                autocomplete="off"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.last_name"
                            />
                        </template>
                        <div v-else class="mt-1 font-medium">
                            {{ user?.last_name ?? t.common.not_specified }}
                        </div>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-4">
                        <div class="text-sm text-muted-foreground">
                            {{ t.admin.group }}
                        </div>
                        <div class="mt-1 font-medium">
                            {{
                                user?.group?.display_name ?? t.admin.simple_user
                            }}
                        </div>
                    </div>

                    <div class="rounded-2xl border border-border bg-card p-4">
                        <div class="text-sm text-muted-foreground">
                            {{ t.admin.email_verified }}
                        </div>
                        <div class="mt-1 flex items-center gap-2 font-medium">
                            <BadgeCheck
                                v-if="user?.email_verified_at"
                                class="size-4 text-emerald-600 dark:text-emerald-400"
                            />
                            <CircleX v-else class="size-4 text-destructive" />
                            {{
                                user?.email_verified_at
                                    ? t.admin.verified
                                    : t.admin.not_verified
                            }}
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border border-border bg-card p-4 sm:col-span-2"
                    >
                        <div
                            class="mb-2 flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <UsersRound class="size-4" />
                            {{ t.company_structure.subordinates }}
                        </div>
                        <div
                            v-if="user?.subordinates.length"
                            class="flex flex-wrap gap-2"
                        >
                            <span
                                v-for="subordinate in user.subordinates"
                                :key="subordinate.id"
                                class="rounded-full border border-border bg-muted/60 px-3 py-1 text-xs font-medium text-foreground"
                            >
                                {{
                                    subordinate.position
                                        ? `${subordinate.full_name} · ${subordinate.position}`
                                        : subordinate.full_name
                                }}
                            </span>
                        </div>
                        <div v-else class="mt-1 font-medium">
                            {{ t.company_structure.no_subordinates }}
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border border-border bg-card p-4 sm:col-span-2"
                    >
                        <div class="text-sm text-muted-foreground">
                            {{ t.admin.created_at }}
                        </div>
                        <div class="mt-1 font-medium">
                            {{ formatDateTime(user?.created_at ?? null) }}
                        </div>
                    </div>
                </div>

                <div
                    v-if="user?.issued_equipment?.length"
                    class="space-y-3"
                >
                    <div class="text-sm font-medium text-foreground">
                        {{ t.profile.issued_equipment }}
                    </div>

                    <div class="grid gap-3">
                        <div
                            v-for="equipmentItem in user.issued_equipment"
                            :key="equipmentItem.id"
                            class="rounded-2xl border border-border bg-card p-4"
                        >
                            <div
                                class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                            >
                                <div class="space-y-1">
                                    <div class="font-medium">
                                        {{ equipmentItem.name }}
                                    </div>
                                    <div class="flex flex-wrap items-start gap-4">
                                        <div
                                            class="overflow-hidden rounded-lg border border-border bg-white p-2 shadow-sm"
                                        >
                                            <img
                                                :src="
                                                    equipmentItem.qr_code_svg_data_uri
                                                "
                                                :alt="`${t.equipment.qr_code}: ${equipmentItem.qr_code}`"
                                                class="size-24"
                                            />
                                        </div>
                                        <div
                                            class="text-sm text-muted-foreground"
                                        >
                                            {{ t.equipment.qr_code }}:
                                            {{ equipmentItem.qr_code }}
                                        </div>
                                    </div>
                                </div>

                                <span
                                    class="inline-flex w-fit rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground"
                                >
                                    {{ equipmentItem.status_label }}
                                </span>
                            </div>

                            <div class="mt-3 text-sm text-muted-foreground">
                                {{ t.equipment.responsible_user }}:
                                {{
                                    formatUserName(
                                        equipmentItem.responsible_user,
                                    )
                                }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </SheetContent>
    </Sheet>
</template>

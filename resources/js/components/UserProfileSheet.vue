<script setup lang="ts">
import {
    BadgeCheck,
    Briefcase,
    Check,
    ChevronsUpDown,
    CircleX,
    Mail,
    Phone,
    UserRound,
    UsersRound,
} from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
const managerPickerOpen = ref(false);
const managerSearchQuery = ref('');
const managerPickerRef = ref<HTMLElement | null>(null);
const managerSearchInputRef = ref<HTMLInputElement | null>(null);

const avatarStyle = computed(() => ({
    objectPosition: 'center',
    transform: `scale(${props.user?.avatar_scale ?? 1})`,
}));

const availableManagerOptions = computed(() => {
    return (props.managerOptions ?? []).filter(
        (option) => option.id !== props.user?.id,
    );
});

const selectedManagerOption = computed(() => {
    if (form.value.manager_id === '') {
        return null;
    }

    return (
        availableManagerOptions.value.find(
            (option) => option.id === form.value.manager_id,
        ) ?? null
    );
});

const filteredManagerOptions = computed(() => {
    const query = managerSearchQuery.value.trim().toLocaleLowerCase();

    if (query === '') {
        return availableManagerOptions.value.slice(0, 5);
    }

    return availableManagerOptions.value.filter((option) =>
        option.name.toLocaleLowerCase().startsWith(query),
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

const managerOptionSubtitle = (option: CompanyStructureManagerOption): string => {
    return option.position ?? option.email;
};

const managerOptionAvatarStyle = (
    option: CompanyStructureManagerOption,
): Record<string, string> => ({
    objectPosition: 'center',
    transform: `scale(${option.avatar_scale ?? 1})`,
});

const closeManagerPicker = (): void => {
    managerPickerOpen.value = false;
    managerSearchQuery.value = '';
};

const openManagerPicker = async (): Promise<void> => {
    managerPickerOpen.value = true;
    managerSearchQuery.value = '';

    await nextTick();
    managerSearchInputRef.value?.focus();
};

const toggleManagerPicker = (): void => {
    if (managerPickerOpen.value) {
        closeManagerPicker();

        return;
    }

    void openManagerPicker();
};

const selectManager = (option: CompanyStructureManagerOption | null): void => {
    form.value.manager_id = option?.id ?? '';
    closeManagerPicker();
};

const handleManagerPickerPointerDown = (event: MouseEvent): void => {
    if (
        managerPickerRef.value &&
        !managerPickerRef.value.contains(event.target as Node)
    ) {
        closeManagerPicker();
    }
};

watch(
    () => props.user?.id,
    () => {
        closeManagerPicker();
    },
);

onMounted(() => {
    document.addEventListener(
        'mousedown',
        handleManagerPickerPointerDown,
    );
});

onBeforeUnmount(() => {
    document.removeEventListener(
        'mousedown',
        handleManagerPickerPointerDown,
    );
});
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
                            <div
                                ref="managerPickerRef"
                                class="relative mt-2"
                            >
                                <button
                                    type="button"
                                    class="flex min-h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-left text-sm transition-colors hover:bg-muted/40"
                                    @click="toggleManagerPicker"
                                >
                                    <span class="truncate">
                                        {{
                                            formatStructureUser(
                                                selectedManagerOption,
                                            )
                                        }}
                                    </span>
                                    <ChevronsUpDown
                                        class="ml-2 size-4 shrink-0 text-muted-foreground"
                                    />
                                </button>

                                <div
                                    v-if="managerPickerOpen"
                                    class="absolute inset-x-0 z-30 mt-2 rounded-2xl border border-border bg-background p-2 shadow-lg"
                                >
                                    <input
                                        ref="managerSearchInputRef"
                                        v-model="managerSearchQuery"
                                        type="text"
                                        class="file:text-foreground placeholder:text-muted-foreground selection:bg-primary selection:text-primary-foreground dark:bg-input/30 border-input h-9 w-full min-w-0 rounded-md border bg-transparent px-3 py-1 text-base shadow-xs transition-[color,box-shadow] outline-none disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] md:text-sm"
                                        autocomplete="off"
                                        :placeholder="
                                            t.admin.user_search_placeholder
                                        "
                                        @keydown.esc.prevent="closeManagerPicker"
                                    />

                                    <div class="mt-2 grid max-h-72 gap-1 overflow-y-auto">
                                        <button
                                            type="button"
                                            class="flex items-center gap-3 rounded-xl px-3 py-2 text-left text-sm transition-colors hover:bg-muted"
                                            @mousedown.prevent="
                                                selectManager(null)
                                            "
                                        >
                                            <div
                                                class="flex size-9 items-center justify-center rounded-full bg-muted text-muted-foreground"
                                            >
                                                <CircleX class="size-4" />
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="truncate font-medium">
                                                    {{
                                                        t.company_structure.no_manager
                                                    }}
                                                </div>
                                            </div>
                                            <Check
                                                v-if="form.manager_id === ''"
                                                class="size-4 text-primary"
                                            />
                                        </button>

                                        <button
                                            v-for="option in filteredManagerOptions"
                                            :key="option.id"
                                            type="button"
                                            class="flex items-center gap-3 rounded-xl px-3 py-2 text-left text-sm transition-colors hover:bg-muted"
                                            @mousedown.prevent="
                                                selectManager(option)
                                            "
                                        >
                                            <Avatar
                                                class="size-9 rounded-full border border-border"
                                            >
                                                <AvatarImage
                                                    v-if="option.avatar"
                                                    :src="option.avatar"
                                                    :alt="option.full_name"
                                                    :style="
                                                        managerOptionAvatarStyle(
                                                            option,
                                                        )
                                                    "
                                                />
                                                <AvatarFallback>
                                                    {{
                                                        getInitials(
                                                            option.full_name,
                                                        )
                                                    }}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div class="min-w-0 flex-1">
                                                <div class="truncate font-medium">
                                                    {{ option.full_name }}
                                                </div>
                                                <div
                                                    class="truncate text-xs text-muted-foreground"
                                                >
                                                    {{
                                                        managerOptionSubtitle(
                                                            option,
                                                        )
                                                    }}
                                                </div>
                                            </div>
                                            <Check
                                                v-if="
                                                    form.manager_id === option.id
                                                "
                                                class="size-4 text-primary"
                                            />
                                        </button>

                                        <div
                                            v-if="
                                                filteredManagerOptions.length ===
                                                0
                                            "
                                            class="rounded-xl px-3 py-4 text-sm text-muted-foreground"
                                        >
                                            {{ t.admin.manager_search_empty }}
                                        </div>
                                    </div>
                                </div>
                            </div>
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
                            {{ t.common.middle_name }}
                        </div>
                        <template v-if="canEdit">
                            <Input
                                v-model="form.middle_name"
                                class="mt-2"
                                autocomplete="additional-name"
                            />
                            <InputError
                                class="mt-2"
                                :message="form.errors.middle_name"
                            />
                        </template>
                        <div v-else class="mt-1 font-medium">
                            {{ user?.middle_name ?? t.common.not_specified }}
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

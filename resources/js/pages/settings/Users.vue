<script setup lang="ts">
import {
    Head,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/vue3';
import {
    Ban,
    CircleCheck,
    CircleX,
    Columns3,
    Download,
    KeyRound,
    LogIn,
    RefreshCw,
    RotateCcw,
    Search,
    SlidersHorizontal,
    Upload,
    UserPlus,
    X,
} from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed, onBeforeUnmount, ref, watch, watchEffect } from 'vue';
import {
    downloadCsvTemplate as downloadUsersCsvTemplate,
    exportCsv as exportUsersCsv,
    importCsv as importUsersCsv,
    show as showManagedUser,
} from '@/actions/App/Http/Controllers/Settings/UserController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import PaginationControls from '@/components/PaginationControls.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import UserProfileSheet from '@/components/UserProfileSheet.vue';
import { useLanguage } from '@/composables/useLanguage';
import { usePasswordGenerator } from '@/composables/usePasswordGenerator';
import { fetchSameOriginJson } from '@/lib/sameOriginJson';
import { index, store } from '@/routes/settings/users';
import { update as updateUserActivation } from '@/routes/settings/users/activation';
import { update as updateUserGroup } from '@/routes/settings/users/group';
import { store as startUserImpersonation } from '@/routes/settings/users/impersonation';
import { reset as resetUserPassword } from '@/routes/settings/users/password';
import { update as updateUserProfile } from '@/routes/settings/users/profile';
import { update as updateUserTableColumns } from '@/routes/settings/users/table-columns';
import type {
    CompanyStructureManagerOption,
    ManagedProfileSaveState,
    ManagedUserProfile,
    PaginatedCollection,
    UserGroupOption,
} from '@/types/ui';

type UserRow = ManagedUserProfile & {
    can_be_impersonated: boolean;
};

type ManagedProfilePayload = {
    name: string;
    last_name: string;
    middle_name: string;
    email: string;
    phone: string;
    position: string;
    manager_id: number | null;
};

type UserFilters = {
    search: string;
    status: string;
    group: string;
    registered_from: string;
    registered_to: string;
    per_page: number;
};

type UserTableOptionalColumnKey =
    | 'position'
    | 'manager'
    | 'status'
    | 'email_verified'
    | 'group';

type UserTableOptionalColumnOption = {
    key: UserTableOptionalColumnKey;
    label: string;
};

const allUserTableOptionalColumnKeys: UserTableOptionalColumnKey[] = [
    'position',
    'manager',
    'status',
    'email_verified',
    'group',
];

const props = defineProps<{
    can: {
        manage_users: boolean;
        manage_activation: boolean;
        manage_accounts: boolean;
        impersonate_users: boolean;
    };
    users: PaginatedCollection<UserRow>;
    groups: UserGroupOption[];
    filters: UserFilters;
    perPageOptions: number[];
    visibleUserTableColumns: UserTableOptionalColumnKey[];
    managerOptions: CompanyStructureManagerOption[];
}>();

const page = usePage();
const { t } = useLanguage();
const { copy: copyToClipboard } = useClipboard();
const { generatePassword } = usePasswordGenerator();
const createUserDialogOpen = ref(false);
const selectedProfileUser = ref<UserRow | null>(null);
const selectedPasswordUser = ref<UserRow | null>(null);
const visibleUserTableColumns = ref<UserTableOptionalColumnKey[]>([
    ...props.visibleUserTableColumns,
]);
const showAdvancedFilters = ref(
    props.filters.status !== '' ||
        props.filters.group !== '' ||
        props.filters.registered_from !== '' ||
        props.filters.registered_to !== '',
);
const managedProfileSnapshot = ref<ManagedProfilePayload | null>(null);
const managedProfileSaveState = ref<ManagedProfileSaveState>('idle');
const isSyncingManagedProfile = ref(false);
const isSyncingFilters = ref(false);
const defaultKazakhstanPhonePrefix = '+7';
let managedProfileSaveTimeout: ReturnType<typeof setTimeout> | null = null;
let filterSearchTimeout: ReturnType<typeof setTimeout> | null = null;
let managedProfileRequestSequence = 0;

const createUserForm = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    email_verified: false,
});

const csvImportForm = useForm({
    delimiter: ';',
    file: null as File | null,
});

const passwordForm = useForm({
    password: '',
    password_confirmation: '',
});

const managedProfileForm = useForm({
    name: '',
    last_name: '',
    middle_name: '',
    email: '',
    phone: defaultKazakhstanPhonePrefix,
    position: '',
    manager_id: '' as number | '',
});

const filtersForm = useForm<UserFilters>({
    search: props.filters.search,
    status: props.filters.status,
    group: props.filters.group,
    registered_from: props.filters.registered_from,
    registered_to: props.filters.registered_to,
    per_page: props.filters.per_page,
});
const usersCsvInput = ref<HTMLInputElement | null>(null);

const visibleUsers = computed(() => props.users.data);
const userTableColumnOptions = computed<UserTableOptionalColumnOption[]>(() => [
    {
        key: 'position',
        label: t.value.company_structure.position,
    },
    {
        key: 'manager',
        label: t.value.company_structure.manager,
    },
    {
        key: 'status',
        label: t.value.admin.status,
    },
    {
        key: 'email_verified',
        label: t.value.admin.email_verified,
    },
    {
        key: 'group',
        label: t.value.admin.group,
    },
]);
const visibleUserTableColumnKeySet = computed(
    () => new Set(visibleUserTableColumns.value),
);
const showUserActionsColumn = computed(() => {
    return (
        props.can.manage_activation ||
        props.can.manage_accounts ||
        props.can.impersonate_users
    );
});
const desktopUserTableMinWidthClass = computed(() => {
    if (visibleUserTableColumns.value.length <= 1) {
        return 'min-w-[820px]';
    }

    if (visibleUserTableColumns.value.length <= 3) {
        return 'min-w-[1040px]';
    }

    return 'min-w-[1240px]';
});
const canAutoSubmitCreateUser = computed(() => {
    return (
        createUserForm.name.trim() !== '' && createUserForm.email.trim() !== ''
    );
});

const openCreateUserDialog = (): void => {
    createUserDialogOpen.value = true;
};

const closeCreateUserDialog = (): void => {
    createUserDialogOpen.value = false;
    createUserForm.reset();
    createUserForm.clearErrors();
};

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.admin.users_title,
                href: index(),
            },
        ],
    });
});

const submitCreateUser = (): void => {
    createUserForm.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => closeCreateUserDialog(),
    });
};

const downloadUsersCsvFile = (): void => {
    window.location.assign(
        exportUsersCsv.url({
            query: {
                delimiter: csvImportForm.delimiter,
            },
        }),
    );
};

const downloadUsersCsvTemplateFile = (): void => {
    window.location.assign(
        downloadUsersCsvTemplate.url({
            query: {
                delimiter: csvImportForm.delimiter,
            },
        }),
    );
};

const openUsersCsvImport = (): void => {
    csvImportForm.clearErrors();
    csvImportForm.file = null;

    if (usersCsvInput.value) {
        usersCsvInput.value.value = '';
        usersCsvInput.value.click();
    }
};

const handleUsersCsvFileChange = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    csvImportForm.file = input.files?.[0] ?? null;
};

const submitUsersCsvImport = (): void => {
    if (csvImportForm.file === null) {
        return;
    }

    csvImportForm.post(importUsersCsv.url(), {
        preserveScroll: true,
        onSuccess: () => {
            csvImportForm.reset();
        },
        onFinish: () => {
            if (usersCsvInput.value) {
                usersCsvInput.value.value = '';
            }
        },
    });
};

const applyGeneratedCreateUserPassword = async (): Promise<void> => {
    if (createUserForm.processing) {
        return;
    }

    const generatedPassword = generatePassword();

    createUserForm.password = generatedPassword;
    createUserForm.password_confirmation = generatedPassword;

    try {
        await copyToClipboard(generatedPassword);
    } catch {
        //
    }

    if (canAutoSubmitCreateUser.value) {
        submitCreateUser();
    }
};

const hasActiveFilters = computed(() => {
    return (
        filtersForm.search !== '' ||
        filtersForm.status !== '' ||
        filtersForm.group !== '' ||
        filtersForm.registered_from !== '' ||
        filtersForm.registered_to !== ''
    );
});

const openProfile = (user: UserRow): void => {
    selectedProfileUser.value = user;
};

const openManagedUserProfileById = async (userId: number): Promise<void> => {
    const requestSequence = ++managedProfileRequestSequence;

    try {
        const response = await fetchSameOriginJson<{
            data: UserRow;
        }>(showManagedUser.url(userId), {
            method: 'GET',
        });

        if (requestSequence !== managedProfileRequestSequence) {
            return;
        }

        selectedProfileUser.value = response.data;
    } catch (error) {
        console.error(error);
    }
};

const closeProfile = (): void => {
    clearManagedProfileSaveTimeout();
    managedProfileRequestSequence += 1;
    selectedProfileUser.value = null;
    managedProfileSnapshot.value = null;
    managedProfileSaveState.value = 'idle';
    managedProfileForm.clearErrors();
};

const openPasswordReset = (user: UserRow): void => {
    selectedPasswordUser.value = user;
    passwordForm.clearErrors();
    passwordForm.reset();
};

const closePasswordReset = (): void => {
    selectedPasswordUser.value = null;
    passwordForm.clearErrors();
    passwordForm.reset();
};

const submitPasswordReset = (): void => {
    if (!selectedPasswordUser.value) {
        return;
    }

    passwordForm.patch(resetUserPassword.url(selectedPasswordUser.value.id), {
        preserveScroll: true,
        onSuccess: closePasswordReset,
    });
};

const updateGroup = (user: UserRow, value: string): void => {
    router.patch(
        updateUserGroup.url(user.id),
        {
            user_group_id: value === '' ? null : Number(value),
        },
        {
            preserveScroll: true,
        },
    );
};

const toggleActivation = (user: UserRow): void => {
    router.patch(
        updateUserActivation.url(user.id),
        {
            is_active: !user.is_active,
        },
        {
            preserveScroll: true,
        },
    );
};

const impersonateUser = (user: UserRow): void => {
    router.post(
        startUserImpersonation.url(user.id),
        {},
        {
            preserveScroll: true,
        },
    );
};

const normalizeVisibleUserTableColumns = (
    selectedColumns: UserTableOptionalColumnKey[],
): UserTableOptionalColumnKey[] => {
    return allUserTableOptionalColumnKeys.filter((column) =>
        selectedColumns.includes(column),
    );
};

const isUserTableColumnVisible = (column: UserTableOptionalColumnKey): boolean => {
    return visibleUserTableColumnKeySet.value.has(column);
};

const persistVisibleUserTableColumns = (): void => {
    router.patch(
        updateUserTableColumns.url(),
        {
            visible_columns: visibleUserTableColumns.value,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
            only: ['visibleUserTableColumns'],
            onError: () => {
                visibleUserTableColumns.value = normalizeVisibleUserTableColumns(
                    props.visibleUserTableColumns,
                );
            },
        },
    );
};

const setUserTableColumnVisibility = (
    column: UserTableOptionalColumnKey,
    checked: boolean | 'indeterminate',
): void => {
    const nextVisibleColumns = checked
        ? [...visibleUserTableColumns.value, column]
        : visibleUserTableColumns.value.filter(
              (visibleColumn) => visibleColumn !== column,
          );

    visibleUserTableColumns.value =
        normalizeVisibleUserTableColumns(nextVisibleColumns);

    persistVisibleUserTableColumns();
};

const showUserActions = (user: UserRow): boolean => {
    return !user.is_super_admin && showUserActionsColumn.value;
};

const canToggleActivation = (user: UserRow): boolean => {
    return (
        props.can.manage_activation &&
        !user.is_super_admin &&
        user.id !== page.props.auth.user.id
    );
};

const canImpersonate = (user: UserRow): boolean => {
    return (
        props.can.impersonate_users &&
        !page.props.auth.isImpersonating &&
        user.can_be_impersonated
    );
};

const canResetPassword = (user: UserRow): boolean => {
    return (
        props.can.manage_accounts &&
        user.id !== page.props.auth.user.id &&
        (!user.is_super_admin || page.props.auth.isSuperAdmin)
    );
};

const canEditProfile = (user: UserRow | null): boolean => {
    if (!user || !props.can.manage_accounts) {
        return false;
    }

    return !user.is_super_admin || page.props.auth.isSuperAdmin;
};

const formatKazakhstanPhone = (value: string | null | undefined): string => {
    const digits = (value ?? '').replace(/\D/g, '');

    if (digits === '') {
        return defaultKazakhstanPhonePrefix;
    }

    let normalizedDigits = digits;

    if (normalizedDigits.startsWith('8')) {
        normalizedDigits = `7${normalizedDigits.slice(1)}`;
    } else if (!normalizedDigits.startsWith('7')) {
        normalizedDigits = `7${normalizedDigits}`;
    }

    normalizedDigits = normalizedDigits.slice(0, 11);

    const localNumber = normalizedDigits.slice(1);
    const segments = [
        localNumber.slice(0, 3),
        localNumber.slice(3, 6),
        localNumber.slice(6, 8),
        localNumber.slice(8, 10),
    ].filter(Boolean);

    return [defaultKazakhstanPhonePrefix, ...segments].join(' ').trim();
};

const managedProfilePayload = (): ManagedProfilePayload => ({
    name: managedProfileForm.name,
    last_name: managedProfileForm.last_name,
    middle_name: managedProfileForm.middle_name,
    email: managedProfileForm.email,
    phone: managedProfileForm.phone,
    position: managedProfileForm.position,
    manager_id:
        managedProfileForm.manager_id === ''
            ? null
            : managedProfileForm.manager_id,
});

const clearManagedProfileSaveTimeout = (): void => {
    if (managedProfileSaveTimeout !== null) {
        clearTimeout(managedProfileSaveTimeout);
        managedProfileSaveTimeout = null;
    }
};

const clearFilterSearchTimeout = (): void => {
    if (filterSearchTimeout !== null) {
        clearTimeout(filterSearchTimeout);
        filterSearchTimeout = null;
    }
};

const filterQuery = (): Record<string, string> => {
    const query: Record<string, string> = {};

    for (const [key, value] of Object.entries({
        search: filtersForm.search,
        status: filtersForm.status,
        group: filtersForm.group,
        registered_from: filtersForm.registered_from,
        registered_to: filtersForm.registered_to,
        per_page: String(filtersForm.per_page),
    })) {
        if (value !== '') {
            query[key] = value;
        }
    }

    return query;
};

const submitFilters = (): void => {
    clearFilterSearchTimeout();

    router.get(index.url(), filterQuery(), {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};

const queueSearchFilters = (): void => {
    clearFilterSearchTimeout();

    filterSearchTimeout = setTimeout(() => {
        submitFilters();
    }, 350);
};

const resetFilters = (): void => {
    clearFilterSearchTimeout();
    isSyncingFilters.value = true;

    filtersForm.search = '';
    filtersForm.status = '';
    filtersForm.group = '';
    filtersForm.registered_from = '';
    filtersForm.registered_to = '';
    filtersForm.per_page = props.perPageOptions[0] ?? 50;
    showAdvancedFilters.value = false;

    isSyncingFilters.value = false;
    submitFilters();
};

const updatePerPage = (value: number): void => {
    filtersForm.per_page = value;
    submitFilters();
};

const syncManagedProfileForm = (user: UserRow | null): void => {
    isSyncingManagedProfile.value = true;
    clearManagedProfileSaveTimeout();
    managedProfileForm.clearErrors();

    managedProfileForm.name = user?.name ?? '';
    managedProfileForm.last_name = user?.last_name ?? '';
    managedProfileForm.middle_name = user?.middle_name ?? '';
    managedProfileForm.email = user?.email ?? '';
    managedProfileForm.phone = formatKazakhstanPhone(user?.phone);
    managedProfileForm.position = user?.position ?? '';
    managedProfileForm.manager_id = user?.manager_id ?? '';

    managedProfileSnapshot.value = managedProfilePayload();
    managedProfileSaveState.value = 'idle';
    isSyncingManagedProfile.value = false;
};

const scheduleManagedProfileSave = (delay = 700): void => {
    clearManagedProfileSaveTimeout();

    managedProfileSaveTimeout = setTimeout(() => {
        submitManagedProfileUpdate();
    }, delay);
};

const submitManagedProfileUpdate = (): void => {
    const user = selectedProfileUser.value;
    const snapshot = managedProfileSnapshot.value;

    if (!user || !snapshot || !canEditProfile(user)) {
        return;
    }

    const current = managedProfilePayload();

    if (JSON.stringify(current) === JSON.stringify(snapshot)) {
        managedProfileSaveState.value = 'idle';

        return;
    }

    if (managedProfileForm.processing) {
        scheduleManagedProfileSave(250);

        return;
    }

    managedProfileSaveState.value = 'saving';

    managedProfileForm.patch(updateUserProfile.url(user.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            managedProfileSnapshot.value = managedProfilePayload();
            managedProfileSaveState.value = 'saved';

            window.setTimeout(() => {
                if (managedProfileSaveState.value === 'saved') {
                    managedProfileSaveState.value = 'idle';
                }
            }, 1400);
        },
        onError: () => {
            managedProfileSaveState.value = 'error';
        },
        onFinish: () => {
            const latest = managedProfileSnapshot.value;

            if (
                managedProfileSaveState.value !== 'error' &&
                latest &&
                JSON.stringify(managedProfilePayload()) !==
                    JSON.stringify(latest)
            ) {
                scheduleManagedProfileSave(350);
            }
        },
    });
};

watch(
    () => selectedProfileUser.value?.id ?? null,
    () => {
        syncManagedProfileForm(selectedProfileUser.value);
    },
);

watch(
    () => props.users.data,
    (users) => {
        if (!selectedProfileUser.value) {
            return;
        }

        const freshUser = users.find(
            (user) => user.id === selectedProfileUser.value?.id,
        );

        if (!freshUser) {
            closeProfile();

            return;
        }

        selectedProfileUser.value = freshUser;
        syncManagedProfileForm(freshUser);
    },
);

watch(
    () => props.visibleUserTableColumns,
    (columns) => {
        visibleUserTableColumns.value =
            normalizeVisibleUserTableColumns(columns);
    },
);

watch(
    () => props.filters,
    (filters) => {
        isSyncingFilters.value = true;

        filtersForm.search = filters.search;
        filtersForm.status = filters.status;
        filtersForm.group = filters.group;
        filtersForm.registered_from = filters.registered_from;
        filtersForm.registered_to = filters.registered_to;
        filtersForm.per_page = filters.per_page;

        if (
            filters.status !== '' ||
            filters.group !== '' ||
            filters.registered_from !== '' ||
            filters.registered_to !== ''
        ) {
            showAdvancedFilters.value = true;
        }

        isSyncingFilters.value = false;
    },
);

watch(
    () => managedProfileForm.phone,
    (value) => {
        const formatted = formatKazakhstanPhone(value);

        if (value !== formatted) {
            managedProfileForm.phone = formatted;
        }
    },
);

watch(
    () => filtersForm.search,
    () => {
        if (isSyncingFilters.value) {
            return;
        }

        queueSearchFilters();
    },
);

watch(
    () => [
        managedProfileForm.name,
        managedProfileForm.last_name,
        managedProfileForm.middle_name,
        managedProfileForm.email,
        managedProfileForm.phone,
        managedProfileForm.position,
        managedProfileForm.manager_id,
    ],
    () => {
        if (
            !selectedProfileUser.value ||
            !managedProfileSnapshot.value ||
            isSyncingManagedProfile.value ||
            !canEditProfile(selectedProfileUser.value)
        ) {
            return;
        }

        if (
            JSON.stringify(managedProfilePayload()) ===
            JSON.stringify(managedProfileSnapshot.value)
        ) {
            managedProfileSaveState.value = 'idle';

            return;
        }

        scheduleManagedProfileSave();
    },
);

onBeforeUnmount(() => {
    clearManagedProfileSaveTimeout();
    clearFilterSearchTimeout();
});

const formatStructureSummary = (
    person: UserRow['manager'] | CompanyStructureManagerOption | null,
): string => {
    if (!person) {
        return t.value.company_structure.no_manager;
    }

    return person.position
        ? `${person.full_name} · ${person.position}`
        : person.full_name;
};
</script>

<template>
    <Head :title="t.admin.users_title" />

    <input
        ref="usersCsvInput"
        type="file"
        accept=".csv,text/csv"
        class="hidden"
        @change="handleUsersCsvFileChange"
    />

    <h1 class="sr-only">{{ t.admin.users_title }}</h1>

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t.admin.users_title"
            :description="t.admin.users_description"
        />

        <section class="space-y-4 rounded-lg border border-border p-4">
            <div
                class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex items-center gap-2 font-medium">
                    <Search class="size-4" />
                    {{ t.admin.user_search }}
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="downloadUsersCsvFile"
                    >
                        <Download class="size-4" />
                        {{ t.admin.csv_export }}
                    </Button>
                    <Button
                        v-if="can.manage_accounts"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="downloadUsersCsvTemplateFile"
                    >
                        <Download class="size-4" />
                        {{ t.admin.csv_download_template }}
                    </Button>
                    <Button
                        v-if="can.manage_accounts"
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="openUsersCsvImport"
                    >
                        <Upload class="size-4" />
                        {{ t.admin.csv_import }}
                    </Button>
                    <Button
                        v-if="can.manage_accounts"
                        type="button"
                        size="sm"
                        @click="openCreateUserDialog"
                    >
                        <UserPlus class="size-4" />
                        {{ t.admin.create_user }}
                    </Button>

                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button type="button" variant="outline" size="sm">
                                <Columns3 class="size-4" />
                                {{ t.admin.show_columns }}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuLabel>
                                {{ t.admin.available_columns }}
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuCheckboxItem
                                v-for="column in userTableColumnOptions"
                                :key="column.key"
                                :checked="
                                    isUserTableColumnVisible(column.key)
                                "
                                class="pl-2"
                                @select.prevent="
                                    setUserTableColumnVisibility(
                                        column.key,
                                        !isUserTableColumnVisible(column.key),
                                    )
                                "
                            >
                                <template #indicator-icon>
                                    <span class="hidden" />
                                </template>
                                <Checkbox
                                    :checked="
                                        isUserTableColumnVisible(column.key)
                                    "
                                    class="pointer-events-none"
                                />
                                <span>{{ column.label }}</span>
                            </DropdownMenuCheckboxItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="showAdvancedFilters = !showAdvancedFilters"
                    >
                        <SlidersHorizontal class="size-4" />
                        {{ t.admin.advanced_search }}
                    </Button>

                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        :disabled="!hasActiveFilters"
                        @click="resetFilters"
                    >
                        <X class="size-4" />
                        {{ t.admin.reset_filters }}
                    </Button>
                </div>
            </div>

            <div class="grid gap-2 rounded-lg border border-dashed border-border p-3">
                <p class="text-sm text-muted-foreground">
                    {{ t.admin.csv_description }}
                </p>
                <div class="flex flex-col gap-2 md:flex-row md:items-end">
                    <div class="grid gap-2">
                        <Label for="users-csv-delimiter">
                            {{ t.admin.csv_delimiter }}
                        </Label>
                        <Input
                            id="users-csv-delimiter"
                            v-model="csvImportForm.delimiter"
                            :placeholder="t.admin.csv_delimiter_placeholder"
                            class="w-28"
                        />
                    </div>
                    <Button
                        v-if="can.manage_accounts"
                        type="button"
                        :disabled="csvImportForm.processing || csvImportForm.file === null"
                        @click="submitUsersCsvImport"
                    >
                        <Upload class="size-4" />
                        {{ t.admin.csv_import }}
                    </Button>
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ t.admin.csv_delimiter_hint }}
                </p>
                <InputError :message="csvImportForm.errors.delimiter" />
                <InputError :message="csvImportForm.errors.file" />
            </div>

            <div class="relative">
                <Search
                    class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                />
                <Input
                    v-model="filtersForm.search"
                    class="pl-9"
                    :placeholder="t.admin.user_search_placeholder"
                    autocomplete="off"
                />
            </div>

            <div
                v-if="showAdvancedFilters"
                class="grid gap-3 md:grid-cols-2 xl:grid-cols-4"
            >
                <div class="grid gap-2">
                    <Label for="status_filter">{{ t.admin.status }}</Label>
                    <select
                        id="status_filter"
                        v-model="filtersForm.status"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                        @change="submitFilters"
                    >
                        <option value="">{{ t.admin.all_statuses }}</option>
                        <option value="active">{{ t.admin.active }}</option>
                        <option value="inactive">{{ t.admin.inactive }}</option>
                    </select>
                </div>

                <div class="grid gap-2">
                    <Label for="group_filter">{{ t.admin.group }}</Label>
                    <select
                        id="group_filter"
                        v-model="filtersForm.group"
                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                        @change="submitFilters"
                    >
                        <option value="">{{ t.admin.all_groups }}</option>
                        <option value="none">{{ t.admin.simple_user }}</option>
                        <option
                            v-for="group in groups"
                            :key="group.id"
                            :value="String(group.id)"
                        >
                            {{ group.display_name }}
                        </option>
                    </select>
                </div>

                <div class="grid gap-2">
                    <Label for="registered_from">
                        {{ t.admin.registered_from }}
                    </Label>
                    <Input
                        id="registered_from"
                        v-model="filtersForm.registered_from"
                        type="date"
                        @change="submitFilters"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="registered_to">
                        {{ t.admin.registered_to }}
                    </Label>
                    <Input
                        id="registered_to"
                        v-model="filtersForm.registered_to"
                        type="date"
                        @change="submitFilters"
                    />
                </div>
            </div>
        </section>

        <Dialog
            :open="createUserDialogOpen"
            @update:open="(isOpen) => !isOpen && closeCreateUserDialog()"
        >
            <DialogContent v-if="can.manage_accounts" class="sm:max-w-2xl">
                <DialogHeader>
                    <div
                        class="mb-2 flex size-12 items-center justify-center rounded-2xl border border-border bg-muted"
                    >
                        <UserPlus class="size-5 text-foreground" />
                    </div>
                    <DialogTitle>{{ t.admin.create_user }}</DialogTitle>
                    <DialogDescription>
                        {{ t.admin.create_user_description }}
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitCreateUser">
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="new_user_name">{{
                                t.common.name
                            }}</Label>
                            <Input
                                id="new_user_name"
                                v-model="createUserForm.name"
                                :placeholder="t.auth.full_name"
                                autocomplete="off"
                            />
                            <InputError :message="createUserForm.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="new_user_email">{{
                                t.common.email
                            }}</Label>
                            <Input
                                id="new_user_email"
                                v-model="createUserForm.email"
                                type="email"
                                placeholder="email@example.com"
                                autocomplete="off"
                            />
                            <InputError
                                :message="createUserForm.errors.email"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="new_user_password">{{
                                t.common.password
                            }}</Label>
                            <PasswordInput
                                id="new_user_password"
                                v-model="createUserForm.password"
                                autocomplete="new-password"
                            />
                            <InputError
                                :message="createUserForm.errors.password"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="new_user_password_confirmation">
                                {{ t.auth.confirm_password }}
                            </Label>
                            <PasswordInput
                                id="new_user_password_confirmation"
                                v-model="createUserForm.password_confirmation"
                                autocomplete="new-password"
                            />
                            <InputError
                                :message="
                                    createUserForm.errors.password_confirmation
                                "
                            />
                        </div>

                        <div class="md:col-span-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="applyGeneratedCreateUserPassword"
                            >
                                <RefreshCw class="size-4" />
                                {{ t.common.generate_password }}
                            </Button>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <input
                            v-model="createUserForm.email_verified"
                            type="checkbox"
                            class="size-4 rounded border-input"
                        />
                        {{ t.admin.mark_email_verified }}
                    </label>
                    <InputError
                        :message="createUserForm.errors.email_verified"
                    />

                    <DialogFooter class="pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeCreateUserDialog"
                        >
                            {{ t.common.cancel }}
                        </Button>
                        <Button
                            type="submit"
                            :disabled="createUserForm.processing"
                        >
                            {{ t.admin.create_user }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <div class="space-y-3 md:hidden">
            <article
                v-for="user in visibleUsers"
                :key="user.id"
                class="rounded-lg border border-border p-4"
                :class="{ 'bg-muted/30 opacity-80': !user.is_active }"
            >
                <div class="flex items-start justify-between gap-3">
                    <button
                        type="button"
                        class="min-w-0 flex-1 rounded-lg text-left transition hover:bg-accent/40 focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                        @click="openProfile(user)"
                    >
                        <div class="truncate font-medium">{{ user.name }}</div>
                        <div
                            v-if="isUserTableColumnVisible('position')"
                            class="text-sm text-muted-foreground"
                        >
                            {{
                                user.position ??
                                t.company_structure.no_position
                            }}
                        </div>
                        <div class="text-sm break-all text-muted-foreground">
                            {{ user.email }}
                        </div>
                    </button>

                    <div class="flex shrink-0 flex-col items-end gap-2">
                        <span
                            v-if="user.is_super_admin"
                            class="rounded-full bg-muted px-2 py-1 text-xs text-muted-foreground"
                        >
                            {{ t.admin.super_admin }}
                        </span>
                        <span
                            v-if="isUserTableColumnVisible('status')"
                            class="rounded-full px-2 py-1 text-xs"
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

                <dl class="mt-4 grid gap-3 text-sm">
                    <div
                        v-if="isUserTableColumnVisible('email_verified')"
                        class="flex items-center justify-between gap-4"
                    >
                        <dt class="text-muted-foreground">
                            {{ t.admin.email_verified }}
                        </dt>
                        <dd>
                            <span
                                class="inline-flex items-center"
                                :title="
                                    user.email_verified_at
                                        ? t.admin.verified
                                        : t.admin.not_verified
                                "
                            >
                                <CircleCheck
                                    v-if="user.email_verified_at"
                                    class="size-5 text-emerald-600 dark:text-emerald-400"
                                    aria-hidden="true"
                                />
                                <CircleX
                                    v-else
                                    class="size-5 text-destructive"
                                    aria-hidden="true"
                                />
                                <span class="sr-only">
                                    {{
                                        user.email_verified_at
                                            ? t.admin.verified
                                            : t.admin.not_verified
                                    }}
                                </span>
                            </span>
                        </dd>
                    </div>

                    <div
                        v-if="isUserTableColumnVisible('manager')"
                        class="grid gap-2"
                    >
                        <dt class="text-muted-foreground">
                            {{ t.company_structure.manager }}
                        </dt>
                        <dd>
                            {{ formatStructureSummary(user.manager) }}
                        </dd>
                    </div>

                    <div
                        v-if="isUserTableColumnVisible('group')"
                        class="grid gap-2"
                    >
                        <dt class="text-muted-foreground">
                            {{ t.admin.group }}
                        </dt>
                        <dd>
                            <select
                                v-if="can.manage_users"
                                class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                :value="user.group?.id ?? ''"
                                @change="
                                    updateGroup(
                                        user,
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    )
                                "
                            >
                                <option value="">
                                    {{ t.admin.simple_user }}
                                </option>
                                <option
                                    v-for="group in groups"
                                    :key="group.id"
                                    :value="group.id"
                                >
                                    {{ group.display_name }}
                                </option>
                            </select>
                            <span v-else>
                                {{
                                    user.group?.display_name ??
                                    t.admin.simple_user
                                }}
                            </span>
                        </dd>
                    </div>
                </dl>

                <div
                    v-if="showUserActions(user)"
                    class="mt-4 grid gap-2"
                >
                    <Button
                        v-if="can.impersonate_users"
                        class="w-full"
                        variant="secondary"
                        size="sm"
                        :disabled="!canImpersonate(user)"
                        @click="impersonateUser(user)"
                    >
                        <LogIn />
                        {{ t.admin.impersonate }}
                    </Button>

                    <Button
                        v-if="can.manage_accounts"
                        class="w-full"
                        variant="outline"
                        size="sm"
                        :disabled="!canResetPassword(user)"
                        @click="openPasswordReset(user)"
                    >
                        <KeyRound />
                        {{ t.admin.reset_password }}
                    </Button>

                    <Button
                        v-if="can.manage_activation"
                        class="w-full"
                        :variant="user.is_active ? 'destructive' : 'outline'"
                        size="sm"
                        :disabled="!canToggleActivation(user)"
                        @click="toggleActivation(user)"
                    >
                        <Ban v-if="user.is_active" />
                        <RotateCcw v-else />
                        {{
                            user.is_active
                                ? t.admin.deactivate
                                : t.admin.activate
                        }}
                    </Button>
                </div>
            </article>
        </div>

        <div
            class="hidden overflow-x-auto rounded-lg border border-border md:block"
        >
            <table
                :class="[
                    'w-full table-fixed text-sm',
                    desktopUserTableMinWidthClass,
                ]"
            >
                <thead class="bg-muted/50 text-left">
                    <tr class="divide-x divide-border">
                        <th class="w-[16%] px-4 py-3 align-top font-medium">
                            {{ t.common.name }}
                        </th>
                        <th
                            v-if="isUserTableColumnVisible('position')"
                            class="w-[14%] px-4 py-3 align-top font-medium"
                        >
                            {{ t.company_structure.position }}
                        </th>
                        <th
                            v-if="isUserTableColumnVisible('manager')"
                            class="w-[12%] px-4 py-3 align-top font-medium"
                        >
                            {{ t.company_structure.manager }}
                        </th>
                        <th class="w-[18%] px-4 py-3 align-top font-medium">
                            {{ t.common.email }}
                        </th>
                        <th
                            v-if="isUserTableColumnVisible('status')"
                            class="w-[8%] px-4 py-3 align-top font-medium"
                        >
                            {{ t.admin.status }}
                        </th>
                        <th
                            v-if="isUserTableColumnVisible('email_verified')"
                            class="w-[10%] px-4 py-3 text-center align-top font-medium leading-tight"
                        >
                            {{ t.admin.email_verified }}
                        </th>
                        <th
                            v-if="isUserTableColumnVisible('group')"
                            class="w-[10%] px-4 py-3 align-top font-medium leading-tight"
                        >
                            {{ t.admin.group }}
                        </th>
                        <th
                            v-if="showUserActionsColumn"
                            class="w-[12%] px-4 py-3 text-right align-top font-medium leading-tight"
                        >
                            {{ t.admin.actions }}
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    <tr
                        v-for="user in visibleUsers"
                        :key="user.id"
                        class="divide-x divide-border"
                        :class="{ 'bg-muted/30 opacity-80': !user.is_active }"
                    >
                        <td class="min-w-0 px-4 py-3">
                            <button
                                type="button"
                                class="w-full rounded-lg text-left transition hover:bg-accent/40 focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:outline-none"
                                @click="openProfile(user)"
                            >
                                <div class="truncate font-medium">
                                    {{ user.name }}
                                </div>
                                <div
                                    v-if="user.is_super_admin"
                                    class="mt-1 text-xs text-muted-foreground"
                                >
                                    {{ t.admin.super_admin }}
                                </div>
                            </button>
                        </td>
                        <td
                            v-if="isUserTableColumnVisible('position')"
                            class="px-4 py-3 text-muted-foreground"
                        >
                            {{
                                user.position ??
                                t.company_structure.no_position
                            }}
                        </td>
                        <td
                            v-if="isUserTableColumnVisible('manager')"
                            class="px-4 py-3 text-muted-foreground"
                        >
                            {{ formatStructureSummary(user.manager) }}
                        </td>
                        <td class="px-4 py-3 text-muted-foreground">
                            <span class="break-all">{{ user.email }}</span>
                        </td>
                        <td
                            v-if="isUserTableColumnVisible('status')"
                            class="px-4 py-3"
                        >
                            <span
                                class="rounded-full px-2 py-1 text-xs"
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
                        </td>
                        <td
                            v-if="isUserTableColumnVisible('email_verified')"
                            class="px-4 py-3 text-center"
                        >
                            <span
                                class="inline-flex items-center justify-center"
                                :title="
                                    user.email_verified_at
                                        ? t.admin.verified
                                        : t.admin.not_verified
                                "
                            >
                                <CircleCheck
                                    v-if="user.email_verified_at"
                                    class="size-5 text-emerald-600 dark:text-emerald-400"
                                    aria-hidden="true"
                                />
                                <CircleX
                                    v-else
                                    class="size-5 text-destructive"
                                    aria-hidden="true"
                                />
                                <span class="sr-only">
                                    {{
                                        user.email_verified_at
                                            ? t.admin.verified
                                            : t.admin.not_verified
                                    }}
                                </span>
                            </span>
                        </td>
                        <td
                            v-if="isUserTableColumnVisible('group')"
                            class="px-4 py-3"
                        >
                            <select
                                v-if="can.manage_users"
                                class="h-9 w-full rounded-md border border-input bg-background px-3 text-sm"
                                :value="user.group?.id ?? ''"
                                @change="
                                    updateGroup(
                                        user,
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    )
                                "
                            >
                                <option value="">
                                    {{ t.admin.simple_user }}
                                </option>
                                <option
                                    v-for="group in groups"
                                    :key="group.id"
                                    :value="group.id"
                                >
                                    {{ group.display_name }}
                                </option>
                            </select>
                            <span v-else>
                                {{
                                    user.group?.display_name ??
                                    t.admin.simple_user
                                }}
                            </span>
                        </td>
                        <td
                            v-if="showUserActionsColumn"
                            class="px-4 py-3"
                        >
                            <div
                                v-if="showUserActions(user)"
                                class="flex justify-end gap-2"
                            >
                                <Button
                                    v-if="can.impersonate_users"
                                    variant="secondary"
                                    size="icon-sm"
                                    :disabled="!canImpersonate(user)"
                                    :title="t.admin.impersonate"
                                    @click="impersonateUser(user)"
                                >
                                    <LogIn />
                                </Button>

                                <Button
                                    v-if="can.manage_accounts"
                                    variant="outline"
                                    size="icon-sm"
                                    :disabled="!canResetPassword(user)"
                                    :title="t.admin.reset_password"
                                    @click="openPasswordReset(user)"
                                >
                                    <KeyRound />
                                </Button>

                                <Button
                                    v-if="can.manage_activation"
                                    :variant="
                                        user.is_active
                                            ? 'destructive'
                                            : 'outline'
                                    "
                                    size="icon-sm"
                                    :disabled="!canToggleActivation(user)"
                                    :title="
                                        user.is_active
                                            ? t.admin.deactivate
                                            : t.admin.activate
                                    "
                                    @click="toggleActivation(user)"
                                >
                                    <Ban v-if="user.is_active" />
                                    <RotateCcw v-else />
                                </Button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <PaginationControls
            :pagination="users"
            :per-page-options="perPageOptions"
            @update:per-page="updatePerPage"
        />

        <Dialog
            :open="selectedPasswordUser !== null"
            @update:open="(isOpen) => !isOpen && closePasswordReset()"
        >
            <DialogContent :show-close-button="false" class="sm:max-w-md">
                <DialogHeader>
                    <div
                        class="mb-2 flex size-12 items-center justify-center rounded-2xl border border-border bg-muted"
                    >
                        <KeyRound class="size-5 text-foreground" />
                    </div>
                    <DialogTitle>{{ t.admin.reset_password }}</DialogTitle>
                    <DialogDescription v-if="selectedPasswordUser">
                        {{ selectedPasswordUser.name }} -
                        {{ selectedPasswordUser.email }}
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitPasswordReset">
                    <div class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="reset_password">
                                {{ t.common.password }}
                            </Label>
                            <Input
                                id="reset_password"
                                v-model="passwordForm.password"
                                type="password"
                                autocomplete="new-password"
                            />
                            <InputError
                                :message="passwordForm.errors.password"
                            />
                        </div>

                        <div class="grid gap-2">
                            <Label for="reset_password_confirmation">
                                {{ t.auth.confirm_password }}
                            </Label>
                            <Input
                                id="reset_password_confirmation"
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                            />
                            <InputError
                                :message="
                                    passwordForm.errors.password_confirmation
                                "
                            />
                        </div>
                    </div>

                    <DialogFooter class="pt-2">
                        <Button
                            type="button"
                            variant="outline"
                            @click="closePasswordReset"
                        >
                            {{ t.common.cancel }}
                        </Button>
                        <Button
                            type="submit"
                            :disabled="passwordForm.processing"
                        >
                            {{ t.admin.reset_password }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <UserProfileSheet
            :open="selectedProfileUser !== null"
            :user="selectedProfileUser"
            :can-edit="canEditProfile(selectedProfileUser)"
            :save-state="managedProfileSaveState"
            :manager-options="managerOptions"
            v-model:form="managedProfileForm"
            @update:open="(isOpen) => !isOpen && closeProfile()"
            @open-user="openManagedUserProfileById"
        />
    </div>
</template>

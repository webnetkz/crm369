<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import { computed, ref, watch, watchEffect } from 'vue';
import Heading from '@/components/Heading.vue';
import PaginationControls from '@/components/PaginationControls.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useLanguage } from '@/composables/useLanguage';
import { index, update } from '@/routes/settings/rights';
import type { PaginatedCollection } from '@/types/ui';

type PermissionOption = {
    key: string;
    label: string;
    description: string;
    module_key?: string;
};

type PermissionGroup = {
    key: string;
    label: string;
    description: string;
    permissions: PermissionOption[];
};

type GroupRow = {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    users_count: number;
    permissions: string[];
};

const props = defineProps<{
    groups: PaginatedCollection<GroupRow>;
    availablePermissions: PermissionOption[];
    permissionGroups: PermissionGroup[];
    configurableModules: string[];
    filters: {
        per_page: number;
    };
    perPageOptions: number[];
}>();

const { t } = useLanguage();
const selectedPermissions = ref<Record<number, string[]>>({});
const savingGroupId = ref<number | null>(null);
const visibleGroups = computed(() => props.groups.data);

const syncPermissions = (): void => {
    selectedPermissions.value = Object.fromEntries(
        props.groups.data.map((group) => [group.id, [...group.permissions]]),
    );
};

watch(
    () => props.groups.data,
    () => syncPermissions(),
    { deep: true, immediate: true },
);

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.settings.rights,
                href: index(),
            },
        ],
    });
});

const updatePermission = (
    groupId: number,
    permission: string,
    checked: boolean | 'indeterminate',
): void => {
    const permissions = new Set(selectedPermissions.value[groupId] ?? []);

    if (checked === true) {
        permissions.add(permission);
    } else {
        permissions.delete(permission);
    }

    selectedPermissions.value[groupId] = [...permissions];
};

const selectedPermissionCount = (
    groupId: number,
    permissions: PermissionOption[],
): number => {
    const selected = new Set(selectedPermissions.value[groupId] ?? []);

    return permissions.filter((permission) => selected.has(permission.key)).length;
};

const setModulePermissions = (
    groupId: number,
    permissions: PermissionOption[],
    enabled: boolean,
): void => {
    const selected = new Set(selectedPermissions.value[groupId] ?? []);

    permissions.forEach((permission) => {
        if (enabled) {
            selected.add(permission.key);
            return;
        }

        selected.delete(permission.key);
    });

    selectedPermissions.value[groupId] = [...selected];
};

const savePermissions = (group: GroupRow): void => {
    savingGroupId.value = group.id;

    router.patch(
        update.url(group.id),
        {
            permissions: selectedPermissions.value[group.id] ?? [],
            configured_modules: props.configurableModules,
        },
        {
            preserveScroll: true,
            onFinish: () => {
                savingGroupId.value = null;
            },
        },
    );
};

const updatePerPage = (value: number): void => {
    router.get(index.url(), { per_page: value }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head :title="t.settings.rights" />

    <h1 class="sr-only">{{ t.settings.rights }}</h1>

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="t.settings.rights"
            :description="t.admin.rights_description"
        />

        <div class="grid gap-4">
            <section
                v-for="group in visibleGroups"
                :key="group.id"
                class="rounded-2xl border border-border bg-card p-5"
            >
                <div
                    class="flex flex-col gap-3 border-b border-border pb-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 text-base font-medium">
                            <ShieldCheck class="size-4 text-muted-foreground" />
                            {{ group.display_name }}
                        </div>
                        <p
                            v-if="group.description"
                            class="text-sm text-muted-foreground"
                        >
                            {{ group.description }}
                        </p>
                    </div>

                    <span
                        class="inline-flex w-fit rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground"
                    >
                        {{ t.admin.members }}: {{ group.users_count }}
                    </span>
                </div>

                <div class="mt-5 space-y-5">
                    <section
                        v-for="permissionGroup in permissionGroups"
                        :key="`${group.id}-${permissionGroup.key}`"
                        class="rounded-2xl border border-border/70 bg-background/50 p-4"
                    >
                        <div
                            class="flex flex-col gap-3 border-b border-border/70 pb-4 sm:flex-row sm:items-start sm:justify-between"
                        >
                            <div class="space-y-1">
                                <h2 class="text-sm font-semibold">
                                    {{ permissionGroup.label }}
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    {{ permissionGroup.description }}
                                </p>
                            </div>

                            <div
                                class="flex items-center gap-3 text-xs text-muted-foreground"
                            >
                                <span class="rounded-full bg-muted px-2.5 py-1">
                                    {{
                                        selectedPermissionCount(
                                            group.id,
                                            permissionGroup.permissions,
                                        )
                                    }}/{{ permissionGroup.permissions.length }}
                                </span>
                                <button
                                    type="button"
                                    class="font-medium text-foreground transition hover:text-primary"
                                    @click="
                                        setModulePermissions(
                                            group.id,
                                            permissionGroup.permissions,
                                            true,
                                        )
                                    "
                                >
                                    {{ t.admin.grant_all_permissions }}
                                </button>
                                <button
                                    type="button"
                                    class="font-medium text-muted-foreground transition hover:text-foreground"
                                    @click="
                                        setModulePermissions(
                                            group.id,
                                            permissionGroup.permissions,
                                            false,
                                        )
                                    "
                                >
                                    {{ t.admin.clear_permissions }}
                                </button>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-4 lg:grid-cols-2">
                            <label
                                v-for="permission in permissionGroup.permissions"
                                :key="permission.key"
                                class="flex items-start gap-3 rounded-xl border border-border bg-background/70 p-4"
                            >
                                <Checkbox
                                    :checked="
                                        (selectedPermissions[group.id] ?? []).includes(
                                            permission.key,
                                        )
                                    "
                                    @update:checked="
                                        (value: boolean | 'indeterminate') =>
                                            updatePermission(
                                                group.id,
                                                permission.key,
                                                value,
                                            )
                                    "
                                />

                                <div class="space-y-1">
                                    <div class="text-sm font-medium">
                                        {{ permission.label }}
                                    </div>
                                    <p class="text-sm text-muted-foreground">
                                        {{ permission.description }}
                                    </p>
                                </div>
                            </label>
                        </div>
                    </section>
                </div>

                <div class="mt-5 flex justify-end">
                    <Button
                        type="button"
                        :disabled="savingGroupId === group.id"
                        @click="savePermissions(group)"
                    >
                        {{ t.common.save }}
                    </Button>
                </div>
            </section>
        </div>

        <PaginationControls
            :pagination="groups"
            :per-page-options="perPageOptions"
            @update:per-page="updatePerPage"
        />
    </div>
</template>

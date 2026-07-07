<script setup lang="ts">
import {
    Head,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/vue3';
import {
    ArrowDown,
    ArrowUp,
    ExternalLink,
    Eye,
    EyeOff,
    LinkIcon,
    PencilLine,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watchEffect } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
} from '@/components/ui/select';
import { useLanguage } from '@/composables/useLanguage';
import { resolveMenuIcon } from '@/lib/menuIcons';
import { fetchSameOriginJson } from '@/lib/sameOriginJson';
import { edit, store } from '@/routes/settings/menu';
import { update as updateBuiltInVisibility } from '@/routes/settings/menu/built-in/visibility';
import {
    destroy as destroyMenuItem,
    update as updateMenuItem,
} from '@/routes/settings/menu/items';
import { update as updateCustomVisibility } from '@/routes/settings/menu/items/visibility';
import { update as updateMenuOrder } from '@/routes/settings/menu/order';

type MenuIconOption = {
    value: string;
    label: string;
};

type BuiltInMenuItem = {
    key: string;
    title: string;
    url: string;
    is_visible: boolean;
};

type CustomMenuItem = {
    id: number;
    title: string;
    icon: string | null;
    url: string;
    opens_in_new_tab: boolean;
    is_global: boolean;
    is_visible: boolean;
};

type SidebarOrderItem = {
    key: string;
    title: string;
    url: string;
};

type MenuState = {
    order?: string[];
};

type CheckboxValue = boolean | 'indeterminate' | null | undefined;

const props = defineProps<{
    can: {
        share_with_all_users: boolean;
    };
    availableIcons: MenuIconOption[];
    builtInItems: BuiltInMenuItem[];
    customItems: CustomMenuItem[];
}>();

const sidebarBuiltInKeys = [
    'news',
    'notifications',
    'dashboard',
    'projects',
    'chats',
    'knowledge-bases',
    'funnels',
    'forms',
    'contacts',
    'edo',
    'production',
    'warehouses',
    'tsd',
    'equipment',
] as const;

const page = usePage();
const { t } = useLanguage();
const savingSidebarOrder = ref(false);
let sidebarOrderRequestId = 0;

const isChecked = (value: CheckboxValue): boolean => {
    return value === true;
};

const createMenuItemDefaults = {
    title: '',
    icon: 'link',
    url: '',
    opens_in_new_tab: false,
    is_global: false,
};

const editMenuItemDefaults = {
    title: '',
    icon: 'link',
    url: '',
    opens_in_new_tab: false,
    is_visible: true,
    is_global: false,
};

const createForm = useForm({
    ...createMenuItemDefaults,
});

const editForm = useForm({
    ...editMenuItemDefaults,
});

const editingItemId = ref<number | null>(null);
const editDialogOpen = ref(false);

const createSelectedIconComponent = computed(() => {
    return resolveMenuIcon(createForm.icon);
});

const createSelectedIconLabel = computed(() => {
    const selectedOption = props.availableIcons.find(
        (option) => option.value === createForm.icon,
    );

    return selectedOption?.label ?? props.availableIcons[0]?.label ?? '';
});

const editSelectedIconComponent = computed(() => {
    return resolveMenuIcon(editForm.icon);
});

const editSelectedIconLabel = computed(() => {
    const selectedOption = props.availableIcons.find(
        (option) => option.value === editForm.icon,
    );

    return selectedOption?.label ?? props.availableIcons[0]?.label ?? '';
});

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.menu.title,
                href: edit(),
            },
        ],
    });
});

const resetCreateForm = (): void => {
    createForm.title = createMenuItemDefaults.title;
    createForm.icon = createMenuItemDefaults.icon;
    createForm.url = createMenuItemDefaults.url;
    createForm.opens_in_new_tab = createMenuItemDefaults.opens_in_new_tab;
    createForm.is_global = createMenuItemDefaults.is_global;
    createForm.clearErrors();
};

const resetEditForm = (): void => {
    editingItemId.value = null;
    editDialogOpen.value = false;
    editForm.title = editMenuItemDefaults.title;
    editForm.icon = editMenuItemDefaults.icon;
    editForm.url = editMenuItemDefaults.url;
    editForm.opens_in_new_tab = editMenuItemDefaults.opens_in_new_tab;
    editForm.is_visible = editMenuItemDefaults.is_visible;
    editForm.is_global = editMenuItemDefaults.is_global;
    editForm.clearErrors();
};

const submitCreateForm = (): void => {
    createForm.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => resetCreateForm(),
    });
};

const submitEditForm = (): void => {
    if (editingItemId.value === null) {
        return;
    }

    editForm.patch(updateMenuItem.url(editingItemId.value), {
        preserveScroll: true,
        onSuccess: () => resetEditForm(),
    });
};

const closeEditDialog = (): void => {
    resetEditForm();
};

const toggleBuiltInVisibility = (item: BuiltInMenuItem): void => {
    router.patch(
        updateBuiltInVisibility.url(item.key),
        {
            is_visible: !item.is_visible,
        },
        {
            preserveScroll: true,
        },
    );
};

const toggleCustomVisibility = (item: CustomMenuItem): void => {
    router.patch(
        updateCustomVisibility.url(item.id),
        {
            is_visible: !item.is_visible,
        },
        {
            preserveScroll: true,
        },
    );
};

const deleteCustomItem = (item: CustomMenuItem): void => {
    router.delete(destroyMenuItem.url(item.id), {
        preserveScroll: true,
        onSuccess: () => {
            if (editingItemId.value === item.id) {
                resetEditForm();
            }
        },
    });
};

const startEditing = (item: CustomMenuItem): void => {
    editingItemId.value = item.id;
    editDialogOpen.value = true;
    editForm.title = item.title;
    editForm.icon = item.icon ?? editMenuItemDefaults.icon;
    editForm.url = item.url;
    editForm.opens_in_new_tab = item.opens_in_new_tab;
    editForm.is_global = item.is_global;
    editForm.is_visible = item.is_visible;
    editForm.clearErrors();
};

const canEditCustomItem = (item: CustomMenuItem): boolean => {
    return !item.is_global || page.props.auth.canViewUsers;
};

const canDeleteCustomItem = (item: CustomMenuItem): boolean => {
    return canEditCustomItem(item);
};

const sidebarOrder = computed<string[]>(() => {
    return Array.isArray(page.props.menu?.order) ? page.props.menu.order : [];
});

const sidebarBuiltInItems = computed<SidebarOrderItem[]>(() => {
    const builtInItems = new Map(
        props.builtInItems.map((item) => [item.key, item] as const),
    );

    return sidebarBuiltInKeys.flatMap((key) => {
        const item = builtInItems.get(key);

        if (!item) {
            return [];
        }

        return [
            {
                key: item.key,
                title: item.title,
                url: item.url,
            },
        ];
    });
});

const sidebarSettingsItem = computed<SidebarOrderItem>(() => {
    const profileItem = props.builtInItems.find(
        (item) => item.key === 'settings.profile',
    );

    return {
        key: 'settings',
        title: t.value.common.settings,
        url: profileItem?.url ?? '/settings/profile',
    };
});

const sidebarCustomItems = computed<SidebarOrderItem[]>(() => {
    return props.customItems
        .filter((item) => item.is_visible)
        .map((item) => ({
            key: `custom:${item.id}`,
            title: item.title,
            url: item.url,
        }));
});

const sidebarItems = computed<SidebarOrderItem[]>(() => {
    return [
        ...sidebarBuiltInItems.value,
        sidebarSettingsItem.value,
        ...sidebarCustomItems.value,
    ];
});

const orderedSidebarItems = computed<SidebarOrderItem[]>(() => {
    const orderMap = new Map(
        sidebarOrder.value.map((key, index) => [key, index] as const),
    );

    return sidebarItems.value
        .map((item, index) => ({
            item,
            index,
            orderIndex: orderMap.get(item.key),
        }))
        .sort((first, second) => {
            if (
                typeof first.orderIndex === 'number' &&
                typeof second.orderIndex === 'number'
            ) {
                return first.orderIndex - second.orderIndex;
            }

            if (typeof first.orderIndex === 'number') {
                return -1;
            }

            if (typeof second.orderIndex === 'number') {
                return 1;
            }

            return first.index - second.index;
        })
        .map(({ item }) => item);
});

const normalizeSidebarOrder = (keys: string[]): string[] => {
    const allowedKeys = new Set(sidebarItems.value.map((item) => item.key));

    return keys.filter((key, index) => {
        return allowedKeys.has(key) && keys.indexOf(key) === index;
    });
};

const persistSidebarOrder = async (keys: string[]): Promise<void> => {
    const nextOrder = normalizeSidebarOrder(keys);
    const currentOrder = [...sidebarOrder.value];
    const menu = page.props.menu as MenuState | undefined;
    const requestId = ++sidebarOrderRequestId;

    if (!menu) {
        return;
    }

    menu.order = nextOrder;
    savingSidebarOrder.value = true;

    try {
        const response = await fetchSameOriginJson<{ order: string[] }>(
            updateMenuOrder.url(),
            {
                method: 'PATCH',
                body: JSON.stringify({
                    items: nextOrder,
                }),
            },
        );

        if (requestId === sidebarOrderRequestId) {
            menu.order = response.order;
        }
    } catch (error) {
        console.error(error);

        if (requestId === sidebarOrderRequestId) {
            menu.order = currentOrder;
        }
    } finally {
        if (requestId === sidebarOrderRequestId) {
            savingSidebarOrder.value = false;
        }
    }
};

const moveSidebarItem = (itemKey: string, direction: 'up' | 'down'): void => {
    if (savingSidebarOrder.value) {
        return;
    }

    const keys = orderedSidebarItems.value.map((item) => item.key);
    const itemIndex = keys.indexOf(itemKey);

    if (itemIndex === -1) {
        return;
    }

    const targetIndex = direction === 'up' ? itemIndex - 1 : itemIndex + 1;

    if (targetIndex < 0 || targetIndex >= keys.length) {
        return;
    }

    [keys[itemIndex], keys[targetIndex]] = [keys[targetIndex], keys[itemIndex]];

    void persistSidebarOrder(keys);
};

const isFirstSidebarItem = (itemKey: string): boolean => {
    return (
        orderedSidebarItems.value.findIndex((item) => item.key === itemKey) ===
        0
    );
};

const isLastSidebarItem = (itemKey: string): boolean => {
    return (
        orderedSidebarItems.value.findIndex((item) => item.key === itemKey) ===
        orderedSidebarItems.value.length - 1
    );
};
</script>

<template>
    <Head :title="t.menu.title" />

    <h1 class="sr-only">{{ t.menu.title }}</h1>

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="t.menu.title"
            :description="t.menu.description"
        />

        <form
            class="space-y-4 rounded-lg border border-border p-4"
            @submit.prevent="submitCreateForm"
        >
            <div class="flex items-center gap-2 font-medium">
                <LinkIcon class="size-4" />
                {{ t.menu.new_item_title }}
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="grid gap-2">
                    <Label for="menu_title">{{ t.menu.title_label }}</Label>
                    <Input
                        id="menu_title"
                        v-model="createForm.title"
                        :placeholder="t.menu.title_placeholder"
                        autocomplete="off"
                    />
                    <InputError :message="createForm.errors.title" />
                </div>

                <div class="grid gap-2">
                    <Label for="menu_url">{{ t.menu.url }}</Label>
                    <Input
                        id="menu_url"
                        v-model="createForm.url"
                        :placeholder="t.menu.url_placeholder"
                        autocomplete="off"
                    />
                    <InputError :message="createForm.errors.url" />
                </div>

                <div class="grid gap-2">
                    <Label for="menu_icon">{{ t.menu.icon_label }}</Label>
                    <Select
                        :model-value="createForm.icon"
                        @update:model-value="
                            (value) =>
                                (createForm.icon =
                                    typeof value === 'string'
                                        ? value
                                        : createMenuItemDefaults.icon)
                        "
                    >
                        <SelectTrigger id="menu_icon" class="w-full">
                            <div class="flex items-center gap-2">
                                <component
                                    :is="createSelectedIconComponent"
                                    class="size-4 text-muted-foreground"
                                />
                                <span>{{ createSelectedIconLabel }}</span>
                            </div>
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in props.availableIcons"
                                :key="option.value"
                                :value="option.value"
                            >
                                <div class="flex items-center gap-2">
                                    <component
                                        :is="resolveMenuIcon(option.value)"
                                        class="size-4 text-muted-foreground"
                                    />
                                    <span>{{ option.label }}</span>
                                </div>
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="createForm.errors.icon" />
                </div>
            </div>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <label class="flex items-center gap-2 text-sm">
                    <Checkbox
                        :checked="createForm.opens_in_new_tab"
                        @update:checked="
                            (value) =>
                                (createForm.opens_in_new_tab = isChecked(value))
                        "
                    />
                    <span>{{ t.menu.open_new_tab }}</span>
                </label>

                <label
                    v-if="props.can.share_with_all_users"
                    class="flex items-center gap-2 text-sm"
                >
                    <Checkbox
                        :checked="createForm.is_global"
                        @update:checked="
                            (value) => (createForm.is_global = isChecked(value))
                        "
                    />
                    <span>{{ t.menu.for_all_users }}</span>
                </label>
            </div>

            <Button type="submit" :disabled="createForm.processing">
                {{ t.menu.create_item }}
            </Button>
        </form>

        <section class="space-y-3">
            <Heading
                variant="small"
                :title="t.menu.sidebar_order"
                :description="t.menu.sidebar_order_description"
            />

            <div class="overflow-hidden rounded-lg border border-border">
                <ul class="divide-y divide-border">
                    <li
                        v-for="item in orderedSidebarItems"
                        :key="item.key"
                        class="flex items-center gap-3 px-4 py-3"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ item.title }}</p>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ item.url }}
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="icon-sm"
                                :disabled="
                                    savingSidebarOrder ||
                                    isFirstSidebarItem(item.key)
                                "
                                :title="t.menu.move_up"
                                @click="moveSidebarItem(item.key, 'up')"
                            >
                                <ArrowUp class="size-4" />
                                <span class="sr-only">{{
                                    t.menu.move_up
                                }}</span>
                            </Button>

                            <Button
                                type="button"
                                variant="outline"
                                size="icon-sm"
                                :disabled="
                                    savingSidebarOrder ||
                                    isLastSidebarItem(item.key)
                                "
                                :title="t.menu.move_down"
                                @click="moveSidebarItem(item.key, 'down')"
                            >
                                <ArrowDown class="size-4" />
                                <span class="sr-only">{{
                                    t.menu.move_down
                                }}</span>
                            </Button>
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <section class="space-y-3">
            <Heading variant="small" :title="t.menu.system_items" />

            <div class="overflow-x-auto rounded-lg border border-border">
                <table class="w-full min-w-[720px] text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th
                                class="border-r border-border px-4 py-3 font-medium"
                            >
                                {{ t.menu.title_label }}
                            </th>
                            <th
                                class="border-r border-border px-4 py-3 font-medium"
                            >
                                {{ t.menu.url }}
                            </th>
                            <th
                                class="border-r border-border px-4 py-3 font-medium"
                            >
                                {{ t.menu.visible }}
                            </th>
                            <th class="px-4 py-3 font-medium">
                                {{ t.admin.actions }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr v-for="item in builtInItems" :key="item.key">
                            <td
                                class="border-r border-border px-4 py-3 font-medium"
                            >
                                {{ item.title }}
                            </td>
                            <td
                                class="border-r border-border px-4 py-3 text-muted-foreground"
                            >
                                {{ item.url }}
                            </td>
                            <td class="border-r border-border px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium"
                                    :class="
                                        item.is_visible
                                            ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    <Eye
                                        v-if="item.is_visible"
                                        class="size-3"
                                    />
                                    <EyeOff v-else class="size-3" />
                                    {{
                                        item.is_visible
                                            ? t.menu.visible
                                            : t.menu.hidden
                                    }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    @click="toggleBuiltInVisibility(item)"
                                >
                                    {{
                                        item.is_visible
                                            ? t.menu.hidden
                                            : t.menu.visible
                                    }}
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-3">
            <Heading variant="small" :title="t.menu.custom_items" />

            <div class="overflow-x-auto rounded-lg border border-border">
                <table
                    v-if="customItems.length"
                    class="w-full min-w-[900px] text-sm"
                >
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th
                                class="border-r border-border px-4 py-3 font-medium"
                            >
                                {{ t.menu.title_label }}
                            </th>
                            <th
                                class="border-r border-border px-4 py-3 font-medium"
                            >
                                {{ t.menu.url }}
                            </th>
                            <th
                                class="border-r border-border px-4 py-3 font-medium"
                            >
                                {{ t.menu.scope }}
                            </th>
                            <th
                                class="border-r border-border px-4 py-3 font-medium"
                            >
                                {{ t.menu.target }}
                            </th>
                            <th
                                class="border-r border-border px-4 py-3 font-medium"
                            >
                                {{ t.menu.visible }}
                            </th>
                            <th class="px-4 py-3 font-medium">
                                {{ t.admin.actions }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        <tr
                            v-for="item in customItems"
                            :key="item.id"
                            :class="
                                editingItemId === item.id
                                    ? 'bg-muted/30'
                                    : undefined
                            "
                        >
                            <td
                                class="border-r border-border px-4 py-3 font-medium"
                            >
                                <div class="flex items-center gap-2">
                                    <component
                                        :is="resolveMenuIcon(item.icon)"
                                        class="size-4 text-muted-foreground"
                                    />
                                    <span>{{ item.title }}</span>
                                </div>
                            </td>
                            <td
                                class="border-r border-border px-4 py-3 text-muted-foreground"
                            >
                                <a
                                    :href="item.url"
                                    :target="
                                        item.opens_in_new_tab
                                            ? '_blank'
                                            : undefined
                                    "
                                    :rel="
                                        item.opens_in_new_tab
                                            ? 'noopener noreferrer'
                                            : undefined
                                    "
                                    class="inline-flex items-center gap-1 underline decoration-neutral-300 underline-offset-4 hover:decoration-current dark:decoration-neutral-500"
                                >
                                    {{ item.url }}
                                    <ExternalLink class="size-3" />
                                </a>
                            </td>
                            <td
                                class="border-r border-border px-4 py-3 text-muted-foreground"
                            >
                                {{
                                    item.is_global
                                        ? t.menu.shared_item
                                        : t.menu.personal_item
                                }}
                            </td>
                            <td
                                class="border-r border-border px-4 py-3 text-muted-foreground"
                            >
                                {{
                                    item.opens_in_new_tab
                                        ? t.menu.new_tab
                                        : t.menu.same_tab
                                }}
                            </td>
                            <td class="border-r border-border px-4 py-3">
                                <span
                                    class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium"
                                    :class="
                                        item.is_visible
                                            ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300'
                                            : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    <Eye
                                        v-if="item.is_visible"
                                        class="size-3"
                                    />
                                    <EyeOff v-else class="size-3" />
                                    {{
                                        item.is_visible
                                            ? t.menu.visible
                                            : t.menu.hidden
                                    }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-if="canEditCustomItem(item)"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="startEditing(item)"
                                    >
                                        <PencilLine class="size-4" />
                                        {{ t.menu.edit }}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="toggleCustomVisibility(item)"
                                    >
                                        {{
                                            item.is_visible
                                                ? t.menu.hidden
                                                : t.menu.visible
                                        }}
                                    </Button>
                                    <Button
                                        v-if="canDeleteCustomItem(item)"
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        @click="deleteCustomItem(item)"
                                    >
                                        <Trash2 class="size-4" />
                                        {{ t.menu.delete }}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div v-else class="p-6 text-sm text-muted-foreground">
                    {{ t.menu.empty_custom_items }}
                </div>
            </div>
        </section>

        <Dialog
            :open="editDialogOpen"
            @update:open="
                (isOpen) => {
                    if (!isOpen) closeEditDialog();
                }
            "
        >
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>{{ t.menu.edit_item_title }}</DialogTitle>
                    <DialogDescription>
                        {{ t.menu.description }}
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitEditForm">
                    <div class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="edit-menu-title">{{
                                t.menu.title_label
                            }}</Label>
                            <Input
                                id="edit-menu-title"
                                v-model="editForm.title"
                                :placeholder="t.menu.title_placeholder"
                                autocomplete="off"
                            />
                            <InputError :message="editForm.errors.title" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="edit-menu-url">{{ t.menu.url }}</Label>
                            <Input
                                id="edit-menu-url"
                                v-model="editForm.url"
                                :placeholder="t.menu.url_placeholder"
                                autocomplete="off"
                            />
                            <InputError :message="editForm.errors.url" />
                        </div>

                        <div class="grid gap-2">
                            <Label for="edit-menu-icon">{{
                                t.menu.icon_label
                            }}</Label>
                            <Select
                                :model-value="editForm.icon"
                                @update:model-value="
                                    (value) =>
                                        (editForm.icon =
                                            typeof value === 'string'
                                                ? value
                                                : editMenuItemDefaults.icon)
                                "
                            >
                                <SelectTrigger
                                    id="edit-menu-icon"
                                    class="w-full"
                                >
                                    <div class="flex items-center gap-2">
                                        <component
                                            :is="editSelectedIconComponent"
                                            class="size-4 text-muted-foreground"
                                        />
                                        <span>{{ editSelectedIconLabel }}</span>
                                    </div>
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="option in props.availableIcons"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        <div class="flex items-center gap-2">
                                            <component
                                                :is="
                                                    resolveMenuIcon(
                                                        option.value,
                                                    )
                                                "
                                                class="size-4 text-muted-foreground"
                                            />
                                            <span>{{ option.label }}</span>
                                        </div>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="editForm.errors.icon" />
                        </div>
                    </div>

                    <div
                        class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center"
                    >
                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox
                                :checked="editForm.opens_in_new_tab"
                                @update:checked="
                                    (value) =>
                                        (editForm.opens_in_new_tab =
                                            isChecked(value))
                                "
                            />
                            <span>{{ t.menu.open_new_tab }}</span>
                        </label>

                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox
                                :checked="editForm.is_visible"
                                @update:checked="
                                    (value) =>
                                        (editForm.is_visible = isChecked(value))
                                "
                            />
                            <span>{{ t.menu.visible }}</span>
                        </label>

                        <label
                            v-if="props.can.share_with_all_users"
                            class="flex items-center gap-2 text-sm"
                        >
                            <Checkbox
                                :checked="editForm.is_global"
                                @update:checked="
                                    (value) =>
                                        (editForm.is_global = isChecked(value))
                                "
                            />
                            <span>{{ t.menu.for_all_users }}</span>
                        </label>
                    </div>

                    <DialogFooter class="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="editForm.processing"
                            @click="closeEditDialog"
                        >
                            {{ t.menu.cancel_edit }}
                        </Button>
                        <Button type="submit" :disabled="editForm.processing">
                            {{ t.menu.save_item }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>

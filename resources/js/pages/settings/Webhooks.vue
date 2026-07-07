<script setup lang="ts">
import {
    Head,
    router,
    setLayoutProps,
    useForm,
    usePage,
} from '@inertiajs/vue3';
import {
    BookText,
    CheckSquare2,
    ChevronRight,
    Copy,
    KeyRound,
    RefreshCcw,
    Trash2,
    Webhook,
} from '@lucide/vue';
import { computed, ref, watch, watchEffect } from 'vue';
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
import { useLanguage } from '@/composables/useLanguage';
import {
    destroy,
    edit,
    regenerate,
    store,
    update,
} from '@/routes/settings/webhooks';

type PermissionOption = {
    key: string;
    label: string;
    description: string;
};

type WebhookCreator = {
    id: number;
    name: string;
    email: string;
} | null;

type WebhookRow = {
    id: number;
    name: string;
    token_prefix: string;
    permissions: string[];
    is_active: boolean;
    is_expired: boolean;
    expires_at: string | null;
    last_used_at: string | null;
    created_at: string | null;
    endpoint_url: string;
    creator: WebhookCreator;
};

type IssuedWebhook = {
    name: string;
    token: string;
    endpoint_url: string;
    signed_url: string;
} | null;

type WebhookDocumentation = {
    base_url: string;
    users_index_url: string;
    users_show_url: string;
    company_structure_index_url: string;
    company_structure_show_url: string;
    contacts_index_url: string;
    contacts_show_url: string;
    contacts_store_url: string;
    contacts_update_url: string;
    contacts_destroy_url: string;
    equipment_index_url: string;
    equipment_show_url: string;
    equipment_store_url: string;
    equipment_update_url: string;
    edo_index_url: string;
    edo_show_url: string;
    edo_store_url: string;
    edo_update_url: string;
    edo_public_link_url: string;
    tsd_index_url: string;
    tsd_store_url: string;
    warehouses_index_url: string;
    warehouses_show_url: string;
    warehouses_items_url: string;
    warehouses_store_url: string;
    warehouses_update_url: string;
    warehouses_destroy_url: string;
};

type DraftWebhook = {
    name: string;
    is_active: boolean;
    never_expires: boolean;
    expires_at: string;
    permissions: string[];
};

type PageProps = {
    flash?: {
        webhookToken?: IssuedWebhook;
    };
};

const props = defineProps<{
    documentation: WebhookDocumentation;
    webhooks: WebhookRow[];
    availablePermissions: PermissionOption[];
}>();

const page = usePage<PageProps>();
const { language, t } = useLanguage();
const copiedWebhookToken = ref(false);
const savingWebhookId = ref<number | null>(null);
const webhookErrors = ref<Record<number, Record<string, string>>>({});
const drafts = ref<Record<number, DraftWebhook>>({});

const createForm = useForm({
    name: '',
    permissions: [] as string[],
    is_active: true,
    never_expires: true,
    expires_at: '',
});

const readFlashWebhook = (): IssuedWebhook => {
    const flashFromPage = (page as typeof page & {
        flash?: { webhookToken?: IssuedWebhook };
    }).flash?.webhookToken;

    return flashFromPage ?? page.props.flash?.webhookToken ?? null;
};

const applyIssuedWebhook = (webhook: IssuedWebhook): void => {
    if (!webhook) {
        return;
    }

    issuedWebhook.value = webhook;
    issuedWebhookDialogOpen.value = true;
    copiedWebhookToken.value = false;
};

const formatDateTimeLocal = (value: string | null): string => {
    if (!value) {
        return '';
    }

    const date = new Date(value);
    const offset = date.getTimezoneOffset();
    const localDate = new Date(date.getTime() - offset * 60_000);

    return localDate.toISOString().slice(0, 16);
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

const authorizationHeaderValue = (token: string): string => {
    return `Authorization: Bearer ${token}`;
};

const webhookHeaderValue = (token: string): string => {
    return `X-Webhook-Token: ${token}`;
};

const PortalWebhookUsersReadPermission = 'users.read';
const PortalWebhookCompanyStructureReadPermission = 'company-structure.read';
const PortalWebhookContactsReadPermission = 'contacts.read';
const PortalWebhookContactsWritePermission = 'contacts.write';
const PortalWebhookEquipmentReadPermission = 'equipment.read';
const PortalWebhookEquipmentWritePermission = 'equipment.write';
const PortalWebhookEdoReadPermission = 'edo.read';
const PortalWebhookEdoWritePermission = 'edo.write';
const PortalWebhookTsdReadPermission = 'tsd.read';
const PortalWebhookTsdWritePermission = 'tsd.write';
const PortalWebhookWarehousesReadPermission = 'warehouses.read';
const PortalWebhookWarehousesWritePermission = 'warehouses.write';
const tokenQueryValue = '{token}';
const existingSectionId = 'webhook-existing';
const createSectionId = 'webhook-create';
const documentationSectionId = 'webhook-documentation';

const authenticationExamples = computed(() => [
    {
        label: t.value.webhooks.documentation_auth_bearer,
        value: authorizationHeaderValue(tokenQueryValue),
    },
    {
        label: t.value.webhooks.documentation_auth_header,
        value: webhookHeaderValue(tokenQueryValue),
    },
    {
        label: t.value.webhooks.documentation_auth_query,
        value: `${props.documentation.base_url}?token=${tokenQueryValue}`,
    },
]);

const endpointExamples = computed(() => [
    {
        method: 'GET',
        path: props.documentation.base_url,
        title: t.value.webhooks.documentation_endpoint_invoke_title,
        description: t.value.webhooks.documentation_endpoint_invoke_description,
        permission: t.value.webhooks.documentation_permission_none,
    },
    {
        method: 'GET',
        path: `${props.documentation.users_index_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_users_index_title,
        description: t.value.webhooks.documentation_endpoint_users_index_description,
        permission: PortalWebhookUsersReadPermission,
    },
    {
        method: 'GET',
        path: `${props.documentation.users_show_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_users_show_title,
        description: t.value.webhooks.documentation_endpoint_users_show_description,
        permission: PortalWebhookUsersReadPermission,
    },
    {
        method: 'GET',
        path: `${props.documentation.company_structure_index_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_company_structure_index_title,
        description:
            t.value.webhooks.documentation_endpoint_company_structure_index_description,
        permission: PortalWebhookCompanyStructureReadPermission,
    },
    {
        method: 'GET',
        path: `${props.documentation.company_structure_show_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_company_structure_show_title,
        description:
            t.value.webhooks.documentation_endpoint_company_structure_show_description,
        permission: PortalWebhookCompanyStructureReadPermission,
    },
    {
        method: 'GET',
        path: `${props.documentation.contacts_index_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_contacts_index_title,
        description: t.value.webhooks.documentation_endpoint_contacts_index_description,
        permission: PortalWebhookContactsReadPermission,
    },
    {
        method: 'GET',
        path: `${props.documentation.contacts_show_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_contacts_show_title,
        description: t.value.webhooks.documentation_endpoint_contacts_show_description,
        permission: PortalWebhookContactsReadPermission,
    },
    {
        method: 'POST',
        path: `${props.documentation.contacts_store_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_contacts_store_title,
        description: t.value.webhooks.documentation_endpoint_contacts_store_description,
        permission: PortalWebhookContactsWritePermission,
    },
    {
        method: 'PATCH',
        path: `${props.documentation.contacts_update_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_contacts_update_title,
        description: t.value.webhooks.documentation_endpoint_contacts_update_description,
        permission: PortalWebhookContactsWritePermission,
    },
    {
        method: 'DELETE',
        path: `${props.documentation.contacts_destroy_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_contacts_destroy_title,
        description: t.value.webhooks.documentation_endpoint_contacts_destroy_description,
        permission: PortalWebhookContactsWritePermission,
    },
    {
        method: 'GET',
        path: `${props.documentation.equipment_index_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_equipment_index_title,
        description: t.value.webhooks.documentation_endpoint_equipment_index_description,
        permission: PortalWebhookEquipmentReadPermission,
    },
    {
        method: 'GET',
        path: `${props.documentation.equipment_show_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_equipment_show_title,
        description: t.value.webhooks.documentation_endpoint_equipment_show_description,
        permission: PortalWebhookEquipmentReadPermission,
    },
    {
        method: 'POST',
        path: `${props.documentation.equipment_store_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_equipment_store_title,
        description: t.value.webhooks.documentation_endpoint_equipment_store_description,
        permission: PortalWebhookEquipmentWritePermission,
    },
    {
        method: 'PATCH',
        path: `${props.documentation.equipment_update_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_equipment_update_title,
        description: t.value.webhooks.documentation_endpoint_equipment_update_description,
        permission: PortalWebhookEquipmentWritePermission,
    },
    {
        method: 'GET',
        path: `${props.documentation.edo_index_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_edo_index_title,
        description: t.value.webhooks.documentation_endpoint_edo_index_description,
        permission: PortalWebhookEdoReadPermission,
    },
    {
        method: 'GET',
        path: `${props.documentation.edo_show_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_edo_show_title,
        description: t.value.webhooks.documentation_endpoint_edo_show_description,
        permission: PortalWebhookEdoReadPermission,
    },
    {
        method: 'POST',
        path: `${props.documentation.edo_store_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_edo_store_title,
        description: t.value.webhooks.documentation_endpoint_edo_store_description,
        permission: PortalWebhookEdoWritePermission,
    },
    {
        method: 'PATCH',
        path: `${props.documentation.edo_update_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_edo_update_title,
        description: t.value.webhooks.documentation_endpoint_edo_update_description,
        permission: PortalWebhookEdoWritePermission,
    },
    {
        method: 'POST',
        path: `${props.documentation.edo_public_link_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_edo_public_link_title,
        description: t.value.webhooks.documentation_endpoint_edo_public_link_description,
        permission: PortalWebhookEdoWritePermission,
    },
    {
        method: 'GET',
        path: `${props.documentation.tsd_index_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_tsd_index_title,
        description: t.value.webhooks.documentation_endpoint_tsd_index_description,
        permission: PortalWebhookTsdReadPermission,
    },
    {
        method: 'POST',
        path: `${props.documentation.tsd_store_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_tsd_store_title,
        description: t.value.webhooks.documentation_endpoint_tsd_store_description,
        permission: PortalWebhookTsdWritePermission,
    },
    {
        method: 'GET',
        path: `${props.documentation.warehouses_index_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_warehouses_index_title,
        description:
            t.value.webhooks.documentation_endpoint_warehouses_index_description,
        permission: PortalWebhookWarehousesReadPermission,
    },
    {
        method: 'GET',
        path: `${props.documentation.warehouses_show_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_warehouses_show_title,
        description:
            t.value.webhooks.documentation_endpoint_warehouses_show_description,
        permission: PortalWebhookWarehousesReadPermission,
    },
    {
        method: 'GET',
        path: `${props.documentation.warehouses_items_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_warehouses_items_title,
        description:
            t.value.webhooks.documentation_endpoint_warehouses_items_description,
        permission: PortalWebhookWarehousesReadPermission,
    },
    {
        method: 'POST',
        path: `${props.documentation.warehouses_store_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_warehouses_store_title,
        description:
            t.value.webhooks.documentation_endpoint_warehouses_store_description,
        permission: PortalWebhookWarehousesWritePermission,
    },
    {
        method: 'PATCH',
        path: `${props.documentation.warehouses_update_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_warehouses_update_title,
        description:
            t.value.webhooks.documentation_endpoint_warehouses_update_description,
        permission: PortalWebhookWarehousesWritePermission,
    },
    {
        method: 'DELETE',
        path: `${props.documentation.warehouses_destroy_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_warehouses_destroy_title,
        description:
            t.value.webhooks.documentation_endpoint_warehouses_destroy_description,
        permission: PortalWebhookWarehousesWritePermission,
    },
]);

const sidebarNavigationSections = computed(() => [
    {
        id: createSectionId,
        title: t.value.webhooks.create_title,
    },
    {
        id: documentationSectionId,
        title: t.value.webhooks.documentation_title,
    },
]);

const syncDrafts = (): void => {
    drafts.value = Object.fromEntries(
        props.webhooks.map((webhook) => [
            webhook.id,
            {
                name: webhook.name,
                is_active: webhook.is_active,
                never_expires: webhook.expires_at === null,
                expires_at: formatDateTimeLocal(webhook.expires_at),
                permissions: [...webhook.permissions],
            },
        ]),
    );
};

watch(
    () => props.webhooks,
    () => syncDrafts(),
    { deep: true, immediate: true },
);

const selectedWebhookId = ref<number | null>(props.webhooks[0]?.id ?? null);

const selectedWebhook = computed(() => {
    if (selectedWebhookId.value === null) {
        return props.webhooks[0] ?? null;
    }

    return (
        props.webhooks.find((webhook) => webhook.id === selectedWebhookId.value) ??
        props.webhooks[0] ??
        null
    );
});

const selectedWebhookDraft = computed(() => {
    if (!selectedWebhook.value) {
        return null;
    }

    return drafts.value[selectedWebhook.value.id] ?? null;
});

watch(
    () => props.webhooks,
    (webhooks) => {
        if (webhooks.length === 0) {
            selectedWebhookId.value = null;

            return;
        }

        const hasSelectedWebhook = webhooks.some(
            (webhook) => webhook.id === selectedWebhookId.value,
        );

        if (!hasSelectedWebhook) {
            selectedWebhookId.value = webhooks[0].id;
        }
    },
    { immediate: true },
);

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.webhooks.title,
                href: edit(),
            },
        ],
    });
});

const issuedWebhook = ref<IssuedWebhook>(readFlashWebhook());
const issuedWebhookDialogOpen = ref(issuedWebhook.value !== null);

watch(
    () => readFlashWebhook(),
    (flashWebhook) => {
        applyIssuedWebhook(flashWebhook);
    },
    { immediate: true },
);

const togglePermission = (
    permissions: string[],
    permission: string,
    checked: boolean | 'indeterminate',
): string[] => {
    const set = new Set(permissions);

    if (checked === true) {
        set.add(permission);
    } else {
        set.delete(permission);
    }

    return [...set];
};

const serializePermissions = (permissions: string[]): string[] => {
    return [
        ...new Set(
            permissions.filter(
                (permission): permission is string => permission.trim() !== '',
            ),
        ),
    ];
};

const submitCreate = (): void => {
    createForm.transform((data) => ({
        ...data,
        permissions: serializePermissions(data.permissions),
        is_active: data.is_active === true,
        never_expires: data.never_expires === true,
        expires_at: data.never_expires ? null : data.expires_at.trim() || null,
    }));

    createForm.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.permissions = [];
            createForm.is_active = true;
            createForm.never_expires = true;
            createForm.expires_at = '';
        },
        onFlash: (flash: { webhookToken?: IssuedWebhook }) => {
            applyIssuedWebhook(flash.webhookToken ?? null);
        },
    });
};

const saveWebhook = (webhook: WebhookRow): void => {
    const draft = drafts.value[webhook.id];

    if (!draft) {
        return;
    }

    savingWebhookId.value = webhook.id;
    webhookErrors.value[webhook.id] = {};

    router.patch(
        update.url(webhook.id),
        {
            name: draft.name,
            permissions: serializePermissions(draft.permissions),
            is_active: draft.is_active,
            never_expires: draft.never_expires,
            expires_at: draft.never_expires
                ? null
                : draft.expires_at.trim() || null,
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                webhookErrors.value[webhook.id] = errors;
            },
            onFinish: () => {
                savingWebhookId.value = null;
            },
        },
    );
};

const regenerateWebhook = (webhook: WebhookRow): void => {
    router.post(regenerate.url(webhook.id), {}, {
        preserveScroll: true,
        onFlash: (flash: { webhookToken?: IssuedWebhook }) => {
            applyIssuedWebhook(flash.webhookToken ?? null);
        },
    });
};

const deleteWebhook = (webhook: WebhookRow): void => {
    router.delete(destroy.url(webhook.id), { preserveScroll: true });
};

const copyWebhookToken = async (): Promise<void> => {
    if (!issuedWebhook.value?.token) {
        return;
    }

    await navigator.clipboard.writeText(issuedWebhook.value.token);
    copiedWebhookToken.value = true;
};

const closeIssuedWebhookDialog = (): void => {
    issuedWebhookDialogOpen.value = false;
};

const selectWebhook = (webhookId: number): void => {
    selectedWebhookId.value = webhookId;
};
</script>

<template>
    <Head :title="t.webhooks.title" />

    <h1 class="sr-only">{{ t.webhooks.title }}</h1>

    <Dialog
        :open="issuedWebhookDialogOpen"
        @update:open="
            (isOpen) => {
                if (!isOpen) closeIssuedWebhookDialog();
            }
        "
    >
        <DialogContent class="sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle class="flex items-center gap-2">
                    <KeyRound class="size-5" />
                    {{ t.webhooks.issued_token_title }}
                </DialogTitle>
                <DialogDescription>
                    {{ t.webhooks.issued_token_description }}
                </DialogDescription>
            </DialogHeader>

            <div v-if="issuedWebhook" class="space-y-4">
                <div class="grid gap-3 lg:grid-cols-2">
                    <div class="grid gap-2">
                        <Label>{{ t.webhooks.endpoint_url }}</Label>
                        <textarea
                            class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            rows="3"
                            readonly
                            :value="issuedWebhook.endpoint_url"
                        ></textarea>
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ t.webhooks.plain_token }}</Label>
                        <textarea
                            class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            rows="3"
                            readonly
                            :value="issuedWebhook.token"
                        ></textarea>
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ t.webhooks.authorization_header }}</Label>
                        <textarea
                            class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            rows="3"
                            readonly
                            :value="authorizationHeaderValue(issuedWebhook.token)"
                        ></textarea>
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ t.webhooks.header_token }}</Label>
                        <textarea
                            class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            rows="3"
                            readonly
                            :value="webhookHeaderValue(issuedWebhook.token)"
                        ></textarea>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label>{{ t.webhooks.signed_url }}</Label>
                    <textarea
                        class="min-h-24 rounded-md border border-input bg-background px-3 py-2 text-sm"
                        rows="3"
                        readonly
                        :value="issuedWebhook.signed_url"
                    ></textarea>
                </div>

                <p class="text-sm text-muted-foreground">
                    {{ t.webhooks.token_usage_help }}
                </p>
            </div>

            <DialogFooter class="gap-2">
                <Button
                    type="button"
                    variant="outline"
                    @click="closeIssuedWebhookDialog"
                >
                    {{ t.common.cancel }}
                </Button>
                <Button type="button" @click="copyWebhookToken">
                    <Copy class="size-4" />
                    {{
                        copiedWebhookToken
                            ? t.common.copied
                            : t.webhooks.copy_token
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="t.webhooks.title"
            :description="t.webhooks.description"
        />

        <div class="grid gap-6 xl:grid-cols-[300px_minmax(0,1fr)] xl:items-start">
            <aside class="space-y-4 xl:sticky xl:top-24">
                <section class="rounded-2xl border border-border bg-card p-4">
                    <div class="text-sm font-semibold">
                        {{ t.webhooks.existing_title }}
                    </div>

                    <div
                        v-if="webhooks.length === 0"
                        class="mt-4 rounded-xl border border-dashed border-border p-4 text-sm text-muted-foreground"
                    >
                        {{ t.webhooks.empty }}
                    </div>

                    <div v-else class="mt-4 grid gap-2">
                        <button
                            v-for="webhook in webhooks"
                            :key="`sidebar-webhook-${webhook.id}`"
                            type="button"
                            class="rounded-xl border px-3 py-3 text-left transition-colors"
                            :class="
                                selectedWebhookId === webhook.id
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border bg-background/70 hover:bg-muted'
                            "
                            @click="selectWebhook(webhook.id)"
                        >
                            <div
                                class="flex items-start justify-between gap-3"
                            >
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-medium">
                                        {{
                                            drafts[webhook.id]?.name ??
                                            webhook.name
                                        }}
                                    </div>
                                    <div
                                        class="mt-1 font-mono text-xs text-muted-foreground"
                                    >
                                        {{ webhook.token_prefix }}
                                    </div>
                                </div>

                                <span
                                    class="shrink-0 rounded-full px-2 py-1 text-[11px]"
                                    :class="
                                        webhook.is_expired
                                            ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                                            : drafts[webhook.id]?.is_active
                                              ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300'
                                              : 'bg-muted text-muted-foreground'
                                    "
                                >
                                    {{
                                        webhook.is_expired
                                            ? t.webhooks.expired
                                            : drafts[webhook.id]?.is_active
                                              ? t.webhooks.active
                                              : t.webhooks.inactive
                                    }}
                                </span>
                            </div>
                        </button>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-4">
                    <div class="text-sm font-semibold">
                        {{ t.webhooks.title }}
                    </div>

                    <div class="mt-4 grid gap-2">
                        <a
                            :href="`#${existingSectionId}`"
                            class="rounded-xl border border-transparent px-3 py-2 text-sm text-muted-foreground transition-colors hover:border-border hover:bg-muted hover:text-foreground"
                        >
                            {{ t.webhooks.existing_title }}
                        </a>
                        <a
                            v-for="section in sidebarNavigationSections"
                            :key="section.id"
                            :href="`#${section.id}`"
                            class="rounded-xl border border-transparent px-3 py-2 text-sm text-muted-foreground transition-colors hover:border-border hover:bg-muted hover:text-foreground"
                        >
                            {{ section.title }}
                        </a>
                    </div>
                </section>
            </aside>

            <div class="space-y-8">
            <section
                :id="existingSectionId"
                class="scroll-mt-24 space-y-4 rounded-2xl border border-border bg-card p-5"
            >
                <Heading variant="small" :title="t.webhooks.existing_title" />

                <div
                    v-if="webhooks.length === 0"
                    class="rounded-2xl border border-dashed border-border p-6 text-sm text-muted-foreground"
                >
                    {{ t.webhooks.empty }}
                </div>

                <article
                    v-else-if="selectedWebhook && selectedWebhookDraft"
                    :id="`webhook-${selectedWebhook.id}`"
                    class="space-y-5"
                >
                <div
                    class="flex flex-col gap-4 border-b border-border pb-5 lg:flex-row lg:items-start lg:justify-between"
                >
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <Webhook class="size-4 text-muted-foreground" />
                            <h2 class="text-lg font-semibold">
                                {{
                                    selectedWebhookDraft.name ||
                                    selectedWebhook.name
                                }}
                            </h2>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span
                                class="rounded-full px-2.5 py-1"
                                :class="
                                    selectedWebhook.is_expired
                                        ? 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                                        : selectedWebhookDraft.is_active
                                          ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-300'
                                          : 'bg-muted text-muted-foreground'
                                "
                            >
                                {{
                                    selectedWebhook.is_expired
                                        ? t.webhooks.expired
                                        : selectedWebhookDraft.is_active
                                          ? t.webhooks.active
                                          : t.webhooks.inactive
                                }}
                            </span>
                            <span
                                class="rounded-full bg-muted px-2.5 py-1 text-muted-foreground"
                            >
                                {{ t.webhooks.token_prefix }}:
                                {{ selectedWebhook.token_prefix }}
                            </span>
                        </div>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox
                            :checked="
                                selectedWebhookDraft.is_active ??
                                selectedWebhook.is_active
                            "
                            @update:checked="
                                (value: boolean | 'indeterminate') => {
                                    selectedWebhookDraft.is_active =
                                        value === true;
                                }
                            "
                        />
                        <span>{{ t.webhooks.active }}</span>
                    </label>
                </div>

                <div
                    class="space-y-4 rounded-2xl border border-border bg-background/70 p-4"
                >
                    <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div class="grid gap-3">
                            <label
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <Checkbox
                                    :checked="
                                        selectedWebhookDraft.never_expires
                                    "
                                    @update:checked="
                                        (value: boolean | 'indeterminate') => {
                                            selectedWebhookDraft.never_expires =
                                                value === true;
                                            if (selectedWebhookDraft.never_expires) {
                                                selectedWebhookDraft.expires_at =
                                                    '';
                                            }
                                        }
                                    "
                                />
                                <span>{{ t.webhooks.never_expires }}</span>
                            </label>

                            <div
                                v-if="!selectedWebhookDraft.never_expires"
                                class="grid gap-2"
                            >
                                <Label
                                    :for="`expires-at-${selectedWebhook.id}`"
                                >{{
                                    t.webhooks.expires_at
                                }}</Label>
                                <Input
                                    :id="`expires-at-${selectedWebhook.id}`"
                                    v-model="selectedWebhookDraft.expires_at"
                                    type="datetime-local"
                                />
                                <p
                                    v-if="
                                        webhookErrors[selectedWebhook.id]
                                            ?.expires_at
                                    "
                                    class="text-sm text-destructive"
                                >
                                    {{
                                        webhookErrors[selectedWebhook.id]
                                            .expires_at
                                    }}
                                </p>
                            </div>

                            <p class="text-sm text-muted-foreground">
                                {{ t.webhooks.expires_at_help }}
                            </p>
                        </div>

                        <div class="grid gap-2 text-sm">
                            <div>
                                <span class="text-muted-foreground"
                                    >{{ t.webhooks.created_by }}:</span
                                >
                                {{ selectedWebhook.creator?.name ?? '—' }}
                            </div>
                            <div>
                                <span class="text-muted-foreground"
                                    >{{ t.webhooks.created_at }}:</span
                                >
                                {{
                                    selectedWebhook.created_at
                                        ? formatDateTime(
                                              selectedWebhook.created_at,
                                          )
                                        : '—'
                                }}
                            </div>
                            <div>
                                <span class="text-muted-foreground"
                                    >{{ t.webhooks.last_used_at }}:</span
                                >
                                {{
                                    selectedWebhook.last_used_at
                                        ? formatDateTime(
                                              selectedWebhook.last_used_at,
                                          )
                                        : t.webhooks.never_used
                                }}
                            </div>
                            <div>
                                <span class="text-muted-foreground"
                                    >{{ t.webhooks.expires_at }}:</span
                                >
                                {{
                                    selectedWebhook.expires_at
                                        ? formatDateTime(
                                              selectedWebhook.expires_at,
                                          )
                                        : t.webhooks.never_expires
                                }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-5">
                    <div class="grid gap-2">
                        <Label
                            :for="`webhook-name-${selectedWebhook.id}`"
                        >{{
                            t.webhooks.name
                        }}</Label>
                        <Input
                            :id="`webhook-name-${selectedWebhook.id}`"
                            v-model="selectedWebhookDraft.name"
                            :placeholder="t.webhooks.name_placeholder"
                        />
                        <p
                            v-if="webhookErrors[selectedWebhook.id]?.name"
                            class="text-sm text-destructive"
                        >
                            {{ webhookErrors[selectedWebhook.id].name }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <Label>{{ t.webhooks.endpoint_url }}</Label>
                        <textarea
                            class="min-h-20 rounded-md border border-input bg-background px-3 py-2 text-sm"
                            rows="2"
                            readonly
                            :value="selectedWebhook.endpoint_url"
                        ></textarea>
                        <p class="text-sm text-muted-foreground">
                            {{ t.webhooks.token_usage_help }}
                        </p>
                        <p class="text-sm text-muted-foreground">
                            {{ t.webhooks.token_regenerate_hint }}
                        </p>
                    </div>

                    <div class="space-y-3">
                        <div
                            class="flex items-center gap-2 text-sm font-medium"
                        >
                            <CheckSquare2 class="size-4 text-muted-foreground" />
                            {{ t.webhooks.permissions }}
                        </div>

                        <div
                            class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3"
                        >
                            <label
                                v-for="permission in availablePermissions"
                                :key="`${selectedWebhook.id}-${permission.key}`"
                                class="flex items-start gap-3 rounded-xl border border-border bg-background/70 p-4"
                            >
                                <Checkbox
                                    :checked="
                                        selectedWebhookDraft.permissions.includes(
                                            permission.key,
                                        )
                                    "
                                    @update:checked="
                                        (
                                            value: boolean | 'indeterminate',
                                        ) => {
                                            selectedWebhookDraft.permissions =
                                                togglePermission(
                                                    selectedWebhookDraft.permissions,
                                                    permission.key,
                                                    value,
                                                );
                                        }
                                    "
                                />
                                <div class="min-w-0 space-y-1">
                                    <div class="text-sm font-medium">
                                        {{ permission.label }}
                                    </div>
                                    <p
                                        class="break-words text-sm text-muted-foreground"
                                    >
                                        {{ permission.description }}
                                    </p>
                                </div>
                            </label>
                        </div>
                        <p
                            v-if="
                                webhookErrors[selectedWebhook.id]?.permissions
                            "
                            class="text-sm text-destructive"
                        >
                            {{ webhookErrors[selectedWebhook.id].permissions }}
                        </p>
                    </div>
                </div>

                <div class="flex flex-wrap gap-3 border-t border-border pt-5">
                    <Button
                        type="button"
                        :disabled="savingWebhookId === selectedWebhook.id"
                        @click="saveWebhook(selectedWebhook)"
                    >
                        {{ t.webhooks.save }}
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        @click="regenerateWebhook(selectedWebhook)"
                    >
                        <RefreshCcw class="mr-2 size-4" />
                        {{ t.webhooks.regenerate }}
                    </Button>

                    <Button
                        type="button"
                        variant="outline"
                        @click="deleteWebhook(selectedWebhook)"
                    >
                        <Trash2 class="mr-2 size-4" />
                        {{ t.webhooks.delete }}
                    </Button>
                </div>
                </article>
            </section>

            <section
                :id="createSectionId"
                class="scroll-mt-24 space-y-4 rounded-2xl border border-border bg-card p-5"
            >
                <Heading
                    variant="small"
                    :title="t.webhooks.create_title"
                    :description="t.webhooks.create_description"
                />

                <form class="space-y-5" @submit.prevent="submitCreate">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="webhook-name">{{
                                t.webhooks.name
                            }}</Label>
                            <Input
                                id="webhook-name"
                                v-model="createForm.name"
                                :placeholder="t.webhooks.name_placeholder"
                            />
                            <InputError :message="createForm.errors.name" />
                        </div>

                        <div class="grid gap-2">
                            <Label>{{ t.webhooks.status }}</Label>
                            <label class="flex items-center gap-2 text-sm">
                                <Checkbox
                                    :checked="createForm.is_active"
                                    @update:checked="
                                        (value: boolean | 'indeterminate') =>
                                            (createForm.is_active =
                                                value === true)
                                    "
                                />
                                <span>{{ t.webhooks.active }}</span>
                            </label>
                            <InputError
                                :message="createForm.errors.is_active"
                            />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-center gap-2 text-sm font-medium">
                            <CheckSquare2 class="size-4 text-muted-foreground" />
                            {{ t.webhooks.permissions }}
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ t.webhooks.permissions_description }}
                        </p>

                        <div class="grid gap-3 md:grid-cols-2 2xl:grid-cols-3">
                            <label
                                v-for="permission in availablePermissions"
                                :key="`create-${permission.key}`"
                                class="flex items-start gap-3 rounded-xl border border-border bg-background/70 p-4"
                            >
                                <Checkbox
                                    :checked="
                                        createForm.permissions.includes(
                                            permission.key,
                                        )
                                    "
                                    @update:checked="
                                        (value: boolean | 'indeterminate') =>
                                            (createForm.permissions =
                                                togglePermission(
                                                    createForm.permissions,
                                                    permission.key,
                                                    value,
                                                ))
                                    "
                                />
                                <div class="min-w-0 space-y-1">
                                    <div class="text-sm font-medium">
                                        {{ permission.label }}
                                    </div>
                                    <p
                                        class="break-words text-sm text-muted-foreground"
                                    >
                                        {{ permission.description }}
                                    </p>
                                </div>
                            </label>
                        </div>
                        <InputError :message="createForm.errors.permissions" />
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div
                            class="space-y-3 rounded-xl border border-border bg-background/70 p-4"
                        >
                            <label
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <Checkbox
                                    :checked="createForm.never_expires"
                                    @update:checked="
                                        (
                                            value:
                                                boolean | 'indeterminate',
                                        ) => {
                                            createForm.never_expires =
                                                value === true;
                                            if (createForm.never_expires) {
                                                createForm.expires_at = '';
                                            }
                                        }
                                    "
                                />
                                <span>{{ t.webhooks.never_expires }}</span>
                            </label>

                            <div
                                v-if="!createForm.never_expires"
                                class="grid gap-2"
                            >
                                <Label for="create-expires-at">{{
                                    t.webhooks.expires_at
                                }}</Label>
                                <Input
                                    id="create-expires-at"
                                    v-model="createForm.expires_at"
                                    type="datetime-local"
                                />
                                <InputError
                                    :message="createForm.errors.expires_at"
                                />
                            </div>

                            <p class="text-sm text-muted-foreground">
                                {{ t.webhooks.expires_at_help }}
                            </p>
                        </div>
                    </div>

                    <Button type="submit" :disabled="createForm.processing">
                        {{ t.webhooks.create }}
                    </Button>
                </form>
            </section>

            <section
                :id="documentationSectionId"
                class="scroll-mt-24 space-y-5 rounded-2xl border border-border bg-card p-5"
            >
                <Heading
                    variant="small"
                    :title="t.webhooks.documentation_title"
                    :description="t.webhooks.documentation_description"
                />

                <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(320px,420px)]">
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 text-base font-medium">
                            <BookText class="size-5" />
                            {{ t.webhooks.documentation_overview_title }}
                        </div>

                        <p class="text-sm text-muted-foreground">
                            {{ t.webhooks.documentation_overview_description }}
                        </p>

                        <div class="grid gap-2">
                            <Label>{{
                                t.webhooks.documentation_base_url
                            }}</Label>
                            <Input
                                :model-value="props.documentation.base_url"
                                readonly
                            />
                        </div>

                        <div
                            class="rounded-xl border border-border bg-background/70 p-4"
                        >
                            <div class="text-sm font-medium">
                                {{ t.webhooks.documentation_auth_title }}
                            </div>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{
                                    t.webhooks.documentation_auth_description
                                }}
                            </p>

                            <div class="mt-4 space-y-3">
                                <div
                                    v-for="example in authenticationExamples"
                                    :key="example.label"
                                    class="grid gap-2"
                                >
                                    <Label>{{ example.label }}</Label>
                                    <textarea
                                        class="min-h-16 rounded-md border border-input bg-background px-3 py-2 text-sm"
                                        rows="2"
                                        readonly
                                        :value="example.value"
                                    ></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="space-y-3 rounded-2xl border border-border bg-background/70 p-4"
                    >
                        <div class="text-sm font-medium">
                            {{ t.webhooks.documentation_notes_title }}
                        </div>

                        <div
                            class="flex items-start gap-2 text-sm text-muted-foreground"
                        >
                            <ChevronRight class="mt-0.5 size-4 shrink-0" />
                            <span>{{
                                t.webhooks.documentation_note_token
                            }}</span>
                        </div>

                        <div
                            class="flex items-start gap-2 text-sm text-muted-foreground"
                        >
                            <ChevronRight class="mt-0.5 size-4 shrink-0" />
                            <span>{{
                                t.webhooks.documentation_note_base
                            }}</span>
                        </div>

                        <div
                            class="flex items-start gap-2 text-sm text-muted-foreground"
                        >
                            <ChevronRight class="mt-0.5 size-4 shrink-0" />
                            <span>{{
                                t.webhooks.documentation_note_permissions
                            }}</span>
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="text-base font-medium">
                        {{ t.webhooks.documentation_endpoints_title }}
                    </div>

                    <article
                        v-for="endpoint in endpointExamples"
                        :key="endpoint.path"
                        class="space-y-3 rounded-2xl border border-border bg-background/70 p-4"
                    >
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <span
                                class="rounded-full bg-emerald-100 px-2.5 py-1 font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                            >
                                {{ endpoint.method }}
                            </span>
                            <span class="font-mono text-xs break-all">
                                {{ endpoint.path }}
                            </span>
                        </div>

                        <div class="space-y-1">
                            <div class="font-medium">{{ endpoint.title }}</div>
                            <p class="text-sm text-muted-foreground">
                                {{ endpoint.description }}
                            </p>
                        </div>

                        <p class="text-sm text-muted-foreground">
                            {{ t.webhooks.documentation_permission_label }}:
                            <span class="font-mono">{{
                                endpoint.permission
                            }}</span>
                        </p>
                    </article>
                </div>
            </section>
            </div>
        </div>
    </div>
</template>

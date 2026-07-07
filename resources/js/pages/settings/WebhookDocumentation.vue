<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { BookText, ChevronRight } from '@lucide/vue';
import { computed, watchEffect } from 'vue';
import Heading from '@/components/Heading.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import { edit as editDocumentation } from '@/routes/settings/webhooks/documentation';

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

type EndpointExample = {
    method: string;
    path: string;
    title: string;
    description: string;
    permission: string;
};

const props = defineProps<{
    documentation: WebhookDocumentation;
}>();

const { t } = useLanguage();

const tokenQueryValue = '{token}';
const overviewSectionId = 'webhook-docs-overview';
const endpointsSectionId = 'webhook-docs-endpoints';
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

const authorizationHeaderValue = (token: string): string => {
    return `Authorization: Bearer ${token}`;
};

const webhookHeaderValue = (token: string): string => {
    return `X-Webhook-Token: ${token}`;
};

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.settings.webhooks_documentation,
                href: editDocumentation(),
            },
        ],
    });
});

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

const endpointExamples = computed<EndpointExample[]>(() => [
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
        description: t.value.webhooks.documentation_endpoint_company_structure_index_description,
        permission: PortalWebhookCompanyStructureReadPermission,
    },
    {
        method: 'GET',
        path: `${props.documentation.company_structure_show_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_company_structure_show_title,
        description: t.value.webhooks.documentation_endpoint_company_structure_show_description,
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
        description: t.value.webhooks.documentation_endpoint_warehouses_index_description,
        permission: PortalWebhookWarehousesReadPermission,
    },
    {
        method: 'GET',
        path: `${props.documentation.warehouses_show_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_warehouses_show_title,
        description: t.value.webhooks.documentation_endpoint_warehouses_show_description,
        permission: PortalWebhookWarehousesReadPermission,
    },
    {
        method: 'GET',
        path: `${props.documentation.warehouses_items_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_warehouses_items_title,
        description: t.value.webhooks.documentation_endpoint_warehouses_items_description,
        permission: PortalWebhookWarehousesReadPermission,
    },
    {
        method: 'POST',
        path: `${props.documentation.warehouses_store_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_warehouses_store_title,
        description: t.value.webhooks.documentation_endpoint_warehouses_store_description,
        permission: PortalWebhookWarehousesWritePermission,
    },
    {
        method: 'PATCH',
        path: `${props.documentation.warehouses_update_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_warehouses_update_title,
        description: t.value.webhooks.documentation_endpoint_warehouses_update_description,
        permission: PortalWebhookWarehousesWritePermission,
    },
    {
        method: 'DELETE',
        path: `${props.documentation.warehouses_destroy_url}?token=${tokenQueryValue}`,
        title: t.value.webhooks.documentation_endpoint_warehouses_destroy_title,
        description: t.value.webhooks.documentation_endpoint_warehouses_destroy_description,
        permission: PortalWebhookWarehousesWritePermission,
    },
]);

const documentationSections = computed(() => [
    {
        id: overviewSectionId,
        title: t.value.webhooks.documentation_overview_title,
    },
    {
        id: endpointsSectionId,
        title: t.value.webhooks.documentation_endpoints_title,
    },
]);
</script>

<template>
    <Head :title="t.settings.webhooks_documentation" />

    <h1 class="sr-only">{{ t.settings.webhooks_documentation }}</h1>

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="t.webhooks.documentation_title"
            :description="t.webhooks.documentation_description"
        />

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_18rem] xl:items-start">
            <div class="space-y-6">
                <section
                    :id="overviewSectionId"
                    class="scroll-mt-24 space-y-5 rounded-2xl border border-border bg-card p-5"
                >
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
                                <Label>{{ t.webhooks.documentation_base_url }}</Label>
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
                                    {{ t.webhooks.documentation_auth_description }}
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
                                <span>{{ t.webhooks.documentation_note_token }}</span>
                            </div>

                            <div
                                class="flex items-start gap-2 text-sm text-muted-foreground"
                            >
                                <ChevronRight class="mt-0.5 size-4 shrink-0" />
                                <span>{{ t.webhooks.documentation_note_base }}</span>
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
                </section>

                <section
                    :id="endpointsSectionId"
                    class="scroll-mt-24 space-y-4 rounded-2xl border border-border bg-card p-5"
                >
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
                            <span class="break-all font-mono text-xs">
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
                            <span class="font-mono">{{ endpoint.permission }}</span>
                        </p>
                    </article>
                </section>
            </div>

            <aside class="xl:sticky xl:top-24">
                <div class="rounded-2xl border border-border bg-card p-4">
                    <div class="text-sm font-semibold">
                        {{ t.webhooks.documentation_title }}
                    </div>

                    <div class="mt-4 grid gap-2">
                        <a
                            v-for="section in documentationSections"
                            :key="section.id"
                            :href="`#${section.id}`"
                            class="rounded-xl border border-transparent px-3 py-2 text-sm text-muted-foreground transition-colors hover:border-border hover:bg-muted hover:text-foreground"
                        >
                            {{ section.title }}
                        </a>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>

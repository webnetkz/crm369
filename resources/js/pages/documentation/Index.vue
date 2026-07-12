<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, BookText, FileCode2, Link2, Webhook } from '@lucide/vue';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import Heading from '@/components/Heading.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import { dashboard } from '@/routes';

type DocumentationSectionKey = 'api' | 'webhooks';

type ApiDocumentationEndpoint = {
    method: string;
    path: string;
    summary: string;
    permission: string;
    access: string;
    content_type: string;
    target_user: string;
};

type ApiDocumentationSection = {
    title: string;
    description: string;
    notes: string[];
    endpoints: ApiDocumentationEndpoint[];
};

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
    directories_index_url: string;
    directories_show_url: string;
    directories_store_url: string;
    directories_update_url: string;
    directories_destroy_url: string;
    directory_records_store_url: string;
    directory_records_update_url: string;
    directory_records_destroy_url: string;
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

type WebhookEndpointExample = {
    method: string;
    path: string;
    title: string;
    description: string;
    permission: string;
};

const props = defineProps<{
    sections: {
        api: boolean;
        webhooks: boolean;
    };
    apiBaseUrl: string | null;
    apiDocumentation: ApiDocumentationSection[] | null;
    webhookDocumentation: WebhookDocumentation | null;
}>();

const { t } = useLanguage();

const apiSectionId = (index: number): string => `api-doc-section-${index + 1}`;
const tokenPlaceholder = '{token}';
const webhookOverviewSectionId = 'webhook-doc-overview';
const webhookEndpointsSectionId = 'webhook-doc-endpoints';
const activeNavigationSectionId = ref<string | null>(null);
let navigationSectionObserver: IntersectionObserver | null = null;

const availableSections = computed(() => {
    return [
        ...(props.sections.api
            ? [
                  {
                      key: 'api' as const,
                      title: t.value.settings.api,
                      description: t.value.documentation.api_caption,
                      icon: FileCode2,
                  },
              ]
            : []),
        ...(props.sections.webhooks
            ? [
                  {
                      key: 'webhooks' as const,
                      title: t.value.settings.webhooks,
                      description: t.value.documentation.webhooks_caption,
                      icon: Webhook,
                  },
              ]
            : []),
    ];
});

const initialSection = (): DocumentationSectionKey => {
    if (typeof window !== 'undefined') {
        const requestedSection = new URLSearchParams(
            window.location.search,
        ).get('section');

        if (requestedSection === 'api' && props.sections.api) {
            return 'api';
        }

        if (requestedSection === 'webhooks' && props.sections.webhooks) {
            return 'webhooks';
        }
    }

    return availableSections.value[0]?.key ?? 'api';
};

const activeSection = ref<DocumentationSectionKey>(initialSection());

const setActiveSection = (section: DocumentationSectionKey): void => {
    activeSection.value = section;

    if (typeof window === 'undefined') {
        return;
    }

    const url = new URL(window.location.href);
    url.searchParams.set('section', section);
    window.history.replaceState({}, '', url.toString());
};

const apiNavigationSections = computed(() => {
    return (props.apiDocumentation ?? []).map((section, index) => ({
        id: apiSectionId(index),
        title: section.title,
    }));
});

const documentationNavigationSections = computed(() => {
    if (activeSection.value === 'webhooks' && props.webhookDocumentation) {
        return [
            {
                id: webhookOverviewSectionId,
                title: t.value.webhooks.documentation_overview_title,
            },
            {
                id: webhookEndpointsSectionId,
                title: t.value.webhooks.documentation_endpoints_title,
            },
        ];
    }

    return apiNavigationSections.value;
});

const setActiveNavigationSection = (sectionId: string | null): void => {
    activeNavigationSectionId.value = sectionId;
};

const syncActiveNavigationSectionFromHash = (): void => {
    if (typeof window === 'undefined') {
        return;
    }

    const sectionIds = documentationNavigationSections.value.map(
        (section) => section.id,
    );
    const hashSectionId = decodeURIComponent(window.location.hash).replace(
        '#',
        '',
    );

    if (hashSectionId && sectionIds.includes(hashSectionId)) {
        setActiveNavigationSection(hashSectionId);

        return;
    }

    setActiveNavigationSection(sectionIds[0] ?? null);
};

const disconnectNavigationSectionObserver = (): void => {
    navigationSectionObserver?.disconnect();
    navigationSectionObserver = null;
};

const observeNavigationSections = (): void => {
    if (typeof window === 'undefined') {
        return;
    }

    syncActiveNavigationSectionFromHash();
    disconnectNavigationSectionObserver();

    if (!('IntersectionObserver' in window)) {
        return;
    }

    const elements = documentationNavigationSections.value
        .map((section) => document.getElementById(section.id))
        .filter(
            (element): element is HTMLElement => element instanceof HTMLElement,
        );

    if (elements.length === 0) {
        return;
    }

    navigationSectionObserver = new IntersectionObserver(
        (entries) => {
            const visibleEntries = entries
                .filter((entry) => entry.isIntersecting)
                .sort(
                    (firstEntry, secondEntry) =>
                        secondEntry.intersectionRatio -
                            firstEntry.intersectionRatio ||
                        firstEntry.boundingClientRect.top -
                            secondEntry.boundingClientRect.top,
                );

            const nextSectionId = visibleEntries[0]?.target.id;

            if (nextSectionId) {
                setActiveNavigationSection(nextSectionId);
            }
        },
        {
            rootMargin: '-20% 0px -55% 0px',
            threshold: [0.15, 0.35, 0.6],
        },
    );

    elements.forEach((element) => navigationSectionObserver?.observe(element));
};

const handleNavigationHashChange = (): void => {
    syncActiveNavigationSectionFromHash();
};

watch(
    () => documentationNavigationSections.value.map((section) => section.id),
    async () => {
        await nextTick();
        observeNavigationSections();
    },
    { immediate: true },
);

onMounted(() => {
    window.addEventListener('hashchange', handleNavigationHashChange);
    observeNavigationSections();
});

onBeforeUnmount(() => {
    window.removeEventListener('hashchange', handleNavigationHashChange);
    disconnectNavigationSectionObserver();
});

const webhookAuthenticationExamples = computed(() => {
    if (!props.webhookDocumentation) {
        return [];
    }

    return [
        {
            label: t.value.webhooks.documentation_auth_bearer,
            value: `Authorization: Bearer ${tokenPlaceholder}`,
        },
        {
            label: t.value.webhooks.documentation_auth_header,
            value: `X-Webhook-Token: ${tokenPlaceholder}`,
        },
        {
            label: t.value.webhooks.documentation_auth_query,
            value: `${props.webhookDocumentation.base_url}?token=${tokenPlaceholder}`,
        },
    ];
});

const webhookEndpointExamples = computed<WebhookEndpointExample[]>(() => {
    if (!props.webhookDocumentation) {
        return [];
    }

    return [
        {
            method: 'GET',
            path: props.webhookDocumentation.base_url,
            title: t.value.webhooks.documentation_endpoint_invoke_title,
            description:
                t.value.webhooks.documentation_endpoint_invoke_description,
            permission: t.value.webhooks.documentation_permission_none,
        },
        {
            method: 'GET',
            path: `${props.webhookDocumentation.users_index_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks.documentation_endpoint_users_index_title,
            description:
                t.value.webhooks.documentation_endpoint_users_index_description,
            permission: 'users.read',
        },
        {
            method: 'GET',
            path: `${props.webhookDocumentation.users_show_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks.documentation_endpoint_users_show_title,
            description:
                t.value.webhooks.documentation_endpoint_users_show_description,
            permission: 'users.read',
        },
        {
            method: 'GET',
            path: `${props.webhookDocumentation.company_structure_index_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks
                .documentation_endpoint_company_structure_index_title,
            description:
                t.value.webhooks
                    .documentation_endpoint_company_structure_index_description,
            permission: 'company-structure.read',
        },
        {
            method: 'GET',
            path: `${props.webhookDocumentation.contacts_index_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks.documentation_endpoint_contacts_index_title,
            description:
                t.value.webhooks
                    .documentation_endpoint_contacts_index_description,
            permission: 'contacts.read',
        },
        {
            method: 'POST',
            path: `${props.webhookDocumentation.contacts_store_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks.documentation_endpoint_contacts_store_title,
            description:
                t.value.webhooks
                    .documentation_endpoint_contacts_store_description,
            permission: 'contacts.write',
        },
        {
            method: 'GET',
            path: `${props.webhookDocumentation.directories_index_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks.documentation_endpoint_directories_index_title,
            description:
                t.value.webhooks
                    .documentation_endpoint_directories_index_description,
            permission: 'directories.read',
        },
        {
            method: 'GET',
            path: `${props.webhookDocumentation.directories_show_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks.documentation_endpoint_directories_show_title,
            description:
                t.value.webhooks
                    .documentation_endpoint_directories_show_description,
            permission: 'directories.read',
        },
        {
            method: 'POST',
            path: `${props.webhookDocumentation.directories_store_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks.documentation_endpoint_directories_store_title,
            description:
                t.value.webhooks
                    .documentation_endpoint_directories_store_description,
            permission: 'directories.write',
        },
        {
            method: 'PATCH',
            path: `${props.webhookDocumentation.directories_update_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks.documentation_endpoint_directories_update_title,
            description:
                t.value.webhooks
                    .documentation_endpoint_directories_update_description,
            permission: 'directories.write',
        },
        {
            method: 'DELETE',
            path: `${props.webhookDocumentation.directories_destroy_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks.documentation_endpoint_directories_destroy_title,
            description:
                t.value.webhooks
                    .documentation_endpoint_directories_destroy_description,
            permission: 'directories.write',
        },
        {
            method: 'POST',
            path: `${props.webhookDocumentation.directory_records_store_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks.documentation_endpoint_directory_records_store_title,
            description:
                t.value.webhooks
                    .documentation_endpoint_directory_records_store_description,
            permission: 'directories.write',
        },
        {
            method: 'PATCH',
            path: `${props.webhookDocumentation.directory_records_update_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks.documentation_endpoint_directory_records_update_title,
            description:
                t.value.webhooks
                    .documentation_endpoint_directory_records_update_description,
            permission: 'directories.write',
        },
        {
            method: 'DELETE',
            path: `${props.webhookDocumentation.directory_records_destroy_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks.documentation_endpoint_directory_records_destroy_title,
            description:
                t.value.webhooks
                    .documentation_endpoint_directory_records_destroy_description,
            permission: 'directories.write',
        },
        {
            method: 'GET',
            path: `${props.webhookDocumentation.equipment_index_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks
                .documentation_endpoint_equipment_index_title,
            description:
                t.value.webhooks
                    .documentation_endpoint_equipment_index_description,
            permission: 'equipment.read',
        },
        {
            method: 'POST',
            path: `${props.webhookDocumentation.equipment_store_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks
                .documentation_endpoint_equipment_store_title,
            description:
                t.value.webhooks
                    .documentation_endpoint_equipment_store_description,
            permission: 'equipment.write',
        },
        {
            method: 'GET',
            path: `${props.webhookDocumentation.edo_index_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks.documentation_endpoint_edo_index_title,
            description:
                t.value.webhooks.documentation_endpoint_edo_index_description,
            permission: 'edo.read',
        },
        {
            method: 'POST',
            path: `${props.webhookDocumentation.edo_public_link_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks
                .documentation_endpoint_edo_public_link_title,
            description:
                t.value.webhooks
                    .documentation_endpoint_edo_public_link_description,
            permission: 'edo.write',
        },
        {
            method: 'GET',
            path: `${props.webhookDocumentation.tsd_index_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks.documentation_endpoint_tsd_index_title,
            description:
                t.value.webhooks.documentation_endpoint_tsd_index_description,
            permission: 'tsd.read',
        },
        {
            method: 'POST',
            path: `${props.webhookDocumentation.tsd_store_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks.documentation_endpoint_tsd_store_title,
            description:
                t.value.webhooks.documentation_endpoint_tsd_store_description,
            permission: 'tsd.write',
        },
        {
            method: 'GET',
            path: `${props.webhookDocumentation.warehouses_index_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks
                .documentation_endpoint_warehouses_index_title,
            description:
                t.value.webhooks
                    .documentation_endpoint_warehouses_index_description,
            permission: 'warehouses.read',
        },
        {
            method: 'DELETE',
            path: `${props.webhookDocumentation.warehouses_destroy_url}?token=${tokenPlaceholder}`,
            title: t.value.webhooks
                .documentation_endpoint_warehouses_destroy_title,
            description:
                t.value.webhooks
                    .documentation_endpoint_warehouses_destroy_description,
            permission: 'warehouses.write',
        },
    ];
});
</script>

<template>
    <Head :title="t.documentation.title" />

    <div class="flex min-h-[calc(100vh-2rem)] flex-col gap-6">
        <header
            class="rounded-[2rem] border border-border/70 bg-background/90 p-4 shadow-sm supports-[backdrop-filter]:bg-background/78 supports-[backdrop-filter]:backdrop-blur"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
            >
                <Link
                    :href="dashboard()"
                    class="group inline-flex w-fit items-center gap-3 rounded-2xl px-2 py-2 transition-colors hover:bg-muted/70"
                >
                    <AppLogo />
                    <div
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <ArrowLeft
                            class="size-4 transition-transform group-hover:-translate-x-0.5"
                        />
                        <span>{{ t.documentation.back_to_platform }}</span>
                    </div>
                </Link>

                <div class="max-w-2xl space-y-1">
                    <p
                        class="text-xs font-semibold tracking-[0.24em] text-primary uppercase"
                    >
                        {{ t.documentation.title }}
                    </p>
                    <h1 class="text-2xl font-semibold tracking-tight">
                        {{ t.documentation.overview_title }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{ t.documentation.description }}
                    </p>
                </div>
            </div>
        </header>

        <div class="grid flex-1 gap-6 xl:grid-cols-[280px_minmax(0,1fr)_280px]">
            <aside class="space-y-4 xl:sticky xl:top-4 xl:self-start">
                <section
                    class="rounded-[2rem] border border-border/70 bg-card/92 p-4 shadow-sm"
                >
                    <div class="flex items-center gap-2 text-sm font-semibold">
                        <BookText class="size-4" />
                        {{ t.documentation.sections_label }}
                    </div>

                    <div class="mt-4 grid gap-2">
                        <button
                            v-for="section in availableSections"
                            :key="section.key"
                            type="button"
                            class="w-full rounded-2xl border px-4 py-3 text-left transition-colors"
                            :class="
                                activeSection === section.key
                                    ? 'border-primary/30 bg-primary/10 text-foreground'
                                    : 'border-border bg-background/70 text-muted-foreground hover:bg-muted hover:text-foreground'
                            "
                            @click="setActiveSection(section.key)"
                        >
                            <div class="flex items-start gap-3">
                                <component
                                    :is="section.icon"
                                    class="mt-0.5 size-4 shrink-0"
                                />
                                <div class="space-y-1">
                                    <div class="text-sm font-semibold">
                                        {{ section.title }}
                                    </div>
                                    <p
                                        class="text-xs leading-5 text-muted-foreground"
                                    >
                                        {{ section.description }}
                                    </p>
                                </div>
                            </div>
                        </button>
                    </div>
                </section>
            </aside>

            <main class="min-w-0">
                <section
                    v-if="
                        activeSection === 'api' &&
                        props.apiDocumentation &&
                        props.apiBaseUrl
                    "
                    class="space-y-8"
                >
                    <Heading
                        variant="small"
                        :title="t.settings.api_documentation"
                        :description="t.api.documentation_description"
                    />

                    <section
                        class="rounded-[2rem] border border-border/70 bg-card/92 p-5 shadow-sm"
                    >
                        <div
                            class="flex items-center gap-2 text-base font-medium"
                        >
                            <FileCode2 class="size-5" />
                            {{ t.api.overview_title }}
                        </div>

                        <p class="mt-3 text-sm text-muted-foreground">
                            {{ t.api.overview_description }}
                        </p>

                        <p
                            class="mt-4 rounded-2xl bg-muted/60 px-4 py-3 text-sm text-muted-foreground"
                        >
                            {{ t.api.target_user_overview }}
                        </p>

                        <div class="mt-4 grid gap-2">
                            <Label>{{ t.api.base_url }}</Label>
                            <Input :model-value="props.apiBaseUrl" readonly />
                        </div>
                    </section>

                    <section class="space-y-5">
                        <article
                            v-for="(section, index) in props.apiDocumentation"
                            :id="apiSectionId(index)"
                            :key="section.title"
                            class="scroll-mt-24 rounded-[2rem] border border-border/70 bg-card/92 p-5 shadow-sm"
                        >
                            <div>
                                <h2 class="text-lg font-semibold">
                                    {{ section.title }}
                                </h2>
                                <p class="mt-2 text-sm text-muted-foreground">
                                    {{ section.description }}
                                </p>
                            </div>

                            <div
                                v-if="section.notes.length > 0"
                                class="mt-4 grid gap-2 rounded-2xl border border-border bg-muted/40 p-4 text-sm text-muted-foreground"
                            >
                                <p v-for="note in section.notes" :key="note">
                                    {{ note }}
                                </p>
                            </div>

                            <div
                                class="mt-4 overflow-x-auto rounded-2xl border border-border"
                            >
                                <table class="w-full min-w-[1260px] text-sm">
                                    <thead class="bg-muted/50 text-left">
                                        <tr>
                                            <th
                                                class="border-r border-border px-4 py-3 font-medium"
                                            >
                                                {{ t.api.method }}
                                            </th>
                                            <th
                                                class="border-r border-border px-4 py-3 font-medium"
                                            >
                                                {{ t.api.path }}
                                            </th>
                                            <th
                                                class="border-r border-border px-4 py-3 font-medium"
                                            >
                                                {{ t.api.summary }}
                                            </th>
                                            <th
                                                class="border-r border-border px-4 py-3 font-medium"
                                            >
                                                {{ t.api.permission }}
                                            </th>
                                            <th
                                                class="border-r border-border px-4 py-3 font-medium"
                                            >
                                                {{ t.api.access }}
                                            </th>
                                            <th
                                                class="border-r border-border px-4 py-3 font-medium"
                                            >
                                                {{ t.api.target_user }}
                                            </th>
                                            <th class="px-4 py-3 font-medium">
                                                {{ t.api.content_type }}
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-border">
                                        <tr
                                            v-for="endpoint in section.endpoints"
                                            :key="`${section.title}-${endpoint.method}-${endpoint.path}`"
                                        >
                                            <td
                                                class="border-r border-border px-4 py-3 font-semibold"
                                            >
                                                {{ endpoint.method }}
                                            </td>
                                            <td
                                                class="border-r border-border px-4 py-3 font-mono text-xs text-muted-foreground"
                                            >
                                                {{ endpoint.path }}
                                            </td>
                                            <td
                                                class="border-r border-border px-4 py-3 text-muted-foreground"
                                            >
                                                {{ endpoint.summary }}
                                            </td>
                                            <td
                                                class="border-r border-border px-4 py-3 font-mono text-xs text-muted-foreground"
                                            >
                                                {{ endpoint.permission }}
                                            </td>
                                            <td
                                                class="border-r border-border px-4 py-3 text-muted-foreground"
                                            >
                                                {{ endpoint.access }}
                                            </td>
                                            <td
                                                class="border-r border-border px-4 py-3 text-muted-foreground"
                                            >
                                                {{ endpoint.target_user }}
                                            </td>
                                            <td
                                                class="px-4 py-3 text-muted-foreground"
                                            >
                                                {{ endpoint.content_type }}
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    </section>
                </section>

                <section
                    v-else-if="
                        activeSection === 'webhooks' &&
                        props.webhookDocumentation
                    "
                    class="space-y-8"
                >
                    <Heading
                        variant="small"
                        :title="t.settings.webhooks_documentation"
                        :description="t.webhooks.documentation_description"
                    />

                    <section
                        :id="webhookOverviewSectionId"
                        class="grid scroll-mt-24 gap-5 xl:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]"
                    >
                        <article
                            class="rounded-[2rem] border border-border/70 bg-card/92 p-5 shadow-sm"
                        >
                            <div
                                class="flex items-center gap-2 text-base font-medium"
                            >
                                <Webhook class="size-5" />
                                {{ t.webhooks.documentation_overview_title }}
                            </div>

                            <p class="mt-3 text-sm text-muted-foreground">
                                {{
                                    t.webhooks
                                        .documentation_overview_description
                                }}
                            </p>

                            <div class="mt-4 grid gap-2">
                                <Label>{{
                                    t.webhooks.documentation_base_url
                                }}</Label>
                                <Input
                                    :model-value="
                                        props.webhookDocumentation.base_url
                                    "
                                    readonly
                                />
                            </div>

                            <div
                                class="mt-5 rounded-2xl border border-border bg-muted/35 p-4"
                            >
                                <div
                                    class="flex items-center gap-2 text-sm font-semibold"
                                >
                                    <Link2 class="size-4" />
                                    {{ t.webhooks.documentation_auth_title }}
                                </div>
                                <p class="mt-2 text-sm text-muted-foreground">
                                    {{
                                        t.webhooks
                                            .documentation_auth_description
                                    }}
                                </p>

                                <div class="mt-4 grid gap-3">
                                    <div
                                        v-for="example in webhookAuthenticationExamples"
                                        :key="example.label"
                                        class="rounded-2xl border border-border bg-background/80 p-3"
                                    >
                                        <div
                                            class="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                                        >
                                            {{ example.label }}
                                        </div>
                                        <p
                                            class="mt-2 font-mono text-xs break-all text-foreground"
                                        >
                                            {{ example.value }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </article>

                        <article
                            class="rounded-[2rem] border border-border/70 bg-card/92 p-5 shadow-sm"
                        >
                            <div class="text-base font-medium">
                                {{ t.webhooks.documentation_notes_title }}
                            </div>

                            <div
                                class="mt-4 grid gap-3 text-sm text-muted-foreground"
                            >
                                <div
                                    class="rounded-2xl border border-border bg-background/80 p-4"
                                >
                                    {{ t.webhooks.documentation_note_token }}
                                </div>
                                <div
                                    class="rounded-2xl border border-border bg-background/80 p-4"
                                >
                                    {{ t.webhooks.documentation_note_base }}
                                </div>
                                <div
                                    class="rounded-2xl border border-border bg-background/80 p-4"
                                >
                                    {{
                                        t.webhooks
                                            .documentation_note_permissions
                                    }}
                                </div>
                            </div>
                        </article>
                    </section>

                    <section
                        :id="webhookEndpointsSectionId"
                        class="scroll-mt-24 space-y-4"
                    >
                        <div
                            class="flex items-center gap-2 text-base font-medium"
                        >
                            <BookText class="size-5" />
                            {{ t.webhooks.documentation_endpoints_title }}
                        </div>

                        <div class="grid gap-4 xl:grid-cols-2">
                            <article
                                v-for="endpoint in webhookEndpointExamples"
                                :key="`${endpoint.method}-${endpoint.path}`"
                                class="rounded-[2rem] border border-border/70 bg-card/92 p-5 shadow-sm"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <div>
                                        <h2 class="text-base font-semibold">
                                            {{ endpoint.title }}
                                        </h2>
                                        <p
                                            class="mt-2 text-sm text-muted-foreground"
                                        >
                                            {{ endpoint.description }}
                                        </p>
                                    </div>
                                    <span
                                        class="rounded-full border border-border bg-background px-3 py-1 text-xs font-semibold"
                                    >
                                        {{ endpoint.method }}
                                    </span>
                                </div>

                                <div
                                    class="mt-4 rounded-2xl border border-border bg-background/80 p-4"
                                >
                                    <div
                                        class="text-xs font-semibold tracking-[0.18em] text-muted-foreground uppercase"
                                    >
                                        URL
                                    </div>
                                    <p
                                        class="mt-2 font-mono text-xs break-all text-foreground"
                                    >
                                        {{ endpoint.path }}
                                    </p>
                                </div>

                                <p class="mt-4 text-sm text-muted-foreground">
                                    {{
                                        t.webhooks
                                            .documentation_permission_label
                                    }}:
                                    <span class="font-medium text-foreground">
                                        {{ endpoint.permission }}
                                    </span>
                                </p>
                            </article>
                        </div>
                    </section>
                </section>
            </main>

            <aside
                v-if="documentationNavigationSections.length > 0"
                class="hidden xl:sticky xl:top-4 xl:block xl:self-start"
            >
                <section
                    class="max-h-[calc(100vh-2rem)] overflow-y-auto rounded-[2rem] border border-border/70 bg-background/95 p-4 pr-3 shadow-sm"
                >
                    <div class="text-sm font-semibold">
                        {{ t.documentation.sections_label }}
                    </div>

                    <div class="mt-4 grid gap-2">
                        <a
                            v-for="section in documentationNavigationSections"
                            :key="section.id"
                            :href="`#${section.id}`"
                            class="rounded-xl border px-3 py-2 text-sm transition-colors"
                            :class="
                                activeNavigationSectionId === section.id
                                    ? 'border-primary/30 bg-primary/10 font-medium text-foreground'
                                    : 'border-transparent text-muted-foreground hover:border-border hover:bg-muted hover:text-foreground'
                            "
                            @click="setActiveNavigationSection(section.id)"
                        >
                            {{ section.title }}
                        </a>
                    </div>
                </section>
            </aside>
        </div>
    </div>
</template>

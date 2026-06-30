<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { MessageSquareShare, ShieldCheck } from '@lucide/vue';
import { computed, ref, watch, watchEffect } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import { edit, update } from '@/routes/settings/integrations';

type GroupRow = {
    id: number;
    name: string;
    display_name: string;
    description: string | null;
    users_count: number;
};

type GroupAccessRow = {
    user_group_id: number;
    access_level: string;
};

type IntegrationRow = {
    id: number;
    driver: string;
    name: string;
    description: string | null;
    is_active: boolean;
    settings: Record<string, string | null>;
    group_accesses: GroupAccessRow[];
};

type AccessLevel = {
    key: string;
    label: string;
    description: string;
};

type IntegrationDraft = {
    name: string;
    is_active: boolean;
    settings: Record<string, string>;
    group_accesses: Record<number, string>;
};

const props = defineProps<{
    groups: GroupRow[];
    integrations: IntegrationRow[];
    accessLevels: AccessLevel[];
    superAdminAccessLevel: string;
}>();

const { t } = useLanguage();
const drafts = ref<Record<number, IntegrationDraft>>({});
const savingIntegrationId = ref<number | null>(null);
const formErrors = ref<Record<number, Record<string, string>>>({});

const syncDrafts = (): void => {
    drafts.value = Object.fromEntries(
        props.integrations.map((integration) => [
            integration.id,
            {
                name: integration.name,
                is_active: integration.is_active,
                settings: Object.fromEntries(
                    Object.entries(integration.settings).map(([key, value]) => [
                        key,
                        value ?? '',
                    ]),
                ),
                group_accesses: Object.fromEntries(
                    props.groups.map((group) => [
                        group.id,
                        integration.group_accesses.find(
                            (access) => access.user_group_id === group.id,
                        )?.access_level ?? 'none',
                    ]),
                ),
            },
        ]),
    );
};

watch(
    () => [props.integrations, props.groups],
    () => syncDrafts(),
    { deep: true, immediate: true },
);

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.integrations.title,
                href: edit(),
            },
        ],
    });
});

const driverFields = (
    driver: string,
): Array<{ key: string; label: string; placeholder: string; type?: string }> => {
    if (driver === 'whatsapp_business') {
        return [
            {
                key: 'api_url',
                label: t.value.integrations.api_url,
                placeholder: t.value.integrations.api_url_placeholder,
            },
            {
                key: 'channel_id',
                label: t.value.integrations.channel_id,
                placeholder: t.value.integrations.channel_id_placeholder,
            },
            {
                key: 'phone_number',
                label: t.value.integrations.phone_number,
                placeholder: t.value.integrations.phone_number_placeholder,
            },
            {
                key: 'api_token',
                label: t.value.integrations.api_token,
                placeholder: t.value.integrations.api_token_placeholder,
                type: 'password',
            },
        ];
    }

    return [
        {
            key: 'bot_username',
            label: t.value.integrations.bot_username,
            placeholder: t.value.integrations.bot_username_placeholder,
        },
        {
            key: 'bot_token',
            label: t.value.integrations.bot_token,
            placeholder: t.value.integrations.bot_token_placeholder,
            type: 'password',
        },
        {
            key: 'webhook_secret',
            label: t.value.integrations.webhook_secret,
            placeholder: t.value.integrations.webhook_secret_placeholder,
            type: 'password',
        },
    ];
};

const accessDescriptionMap = computed<Record<string, string>>(() => {
    return Object.fromEntries(
        props.accessLevels.map((level) => [level.key, level.description]),
    );
});

const accessLabelMap = computed<Record<string, string>>(() => {
    return Object.fromEntries(
        props.accessLevels.map((level) => [level.key, level.label]),
    );
});

const integrationSectionId = (driver: string): string => {
    return `integration-section-${driver}`;
};

const integrationSectionTitle = (driver: string): string => {
    return driver === 'whatsapp_business'
        ? t.value.integrations.whatsapp_business
        : t.value.integrations.telegram;
};

const saveIntegration = (integration: IntegrationRow): void => {
    const draft = drafts.value[integration.id];

    if (!draft) {
        return;
    }

    savingIntegrationId.value = integration.id;
    formErrors.value[integration.id] = {};

    router.patch(
        update.url(integration.id),
        {
            name: draft.name,
            is_active: draft.is_active,
            settings: draft.settings,
            group_accesses: props.groups.map((group) => ({
                user_group_id: group.id,
                access_level: draft.group_accesses[group.id] ?? 'none',
            })),
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                formErrors.value[integration.id] = errors;
            },
            onFinish: () => {
                savingIntegrationId.value = null;
            },
        },
    );
};
</script>

<template>
    <Head :title="t.integrations.title" />

    <h1 class="sr-only">{{ t.integrations.title }}</h1>

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="t.integrations.title"
            :description="t.integrations.description"
        />

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-2xl border border-border bg-card p-5">
                <div class="flex items-center gap-2 text-base font-medium">
                    <ShieldCheck class="size-4 text-muted-foreground" />
                    {{ t.integrations.super_admin_access }}
                </div>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ t.integrations.super_admin_access_description }}
                </p>
                <Badge class="mt-4" variant="secondary">
                    {{ t.integrations.full_access }}
                </Badge>
            </section>

            <section class="rounded-2xl border border-border bg-card p-5">
                <div class="flex items-center gap-2 text-base font-medium">
                    <MessageSquareShare class="size-4 text-muted-foreground" />
                    {{ t.integrations.conversation_owner_rule_title }}
                </div>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ t.integrations.conversation_owner_rule_description }}
                </p>
            </section>
        </div>

        <nav
            aria-label="Integration sections"
            class="rounded-2xl border border-border bg-card p-4"
        >
            <div class="flex flex-wrap gap-3">
                <a
                    v-for="integration in integrations"
                    :key="`integration-link-${integration.id}`"
                    :href="`#${integrationSectionId(integration.driver)}`"
                    class="inline-flex items-center rounded-full border border-border bg-background px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                >
                    {{ integrationSectionTitle(integration.driver) }}
                </a>
            </div>
        </nav>

        <section
            v-for="integration in integrations"
            :key="integration.id"
            :id="integrationSectionId(integration.driver)"
            class="space-y-5 rounded-2xl border border-border bg-card p-5"
        >
            <div
                class="flex flex-col gap-4 border-b border-border pb-5 lg:flex-row lg:items-start lg:justify-between"
            >
                <div class="space-y-2">
                    <p class="text-sm font-medium text-muted-foreground">
                        {{ integrationSectionTitle(integration.driver) }}
                    </p>
                    <div class="flex items-center gap-3">
                        <h2 class="text-lg font-semibold">
                            {{ drafts[integration.id]?.name || integration.name }}
                        </h2>
                        <Badge
                            :variant="drafts[integration.id]?.is_active ? 'default' : 'secondary'"
                        >
                            {{
                                drafts[integration.id]?.is_active
                                    ? t.integrations.active
                                    : t.integrations.inactive
                            }}
                        </Badge>
                    </div>
                    <p
                        v-if="integration.description"
                        class="max-w-3xl text-sm text-muted-foreground"
                    >
                        {{ integration.description }}
                    </p>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <Checkbox
                        :checked="drafts[integration.id]?.is_active ?? false"
                        @update:checked="
                            (value: boolean | 'indeterminate') => {
                                if (drafts[integration.id]) {
                                    drafts[integration.id].is_active =
                                        value === true;
                                }
                            }
                        "
                    />
                    <span>{{ t.integrations.channel_status }}</span>
                </label>
            </div>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(320px,420px)]">
                <div class="space-y-4">
                    <Heading
                        variant="small"
                        :title="t.integrations.connection_settings"
                    />

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="grid gap-2 md:col-span-2">
                            <Label :for="`integration-name-${integration.id}`">
                                {{ t.integrations.channel_name }}
                            </Label>
                            <Input
                                :id="`integration-name-${integration.id}`"
                                v-model="drafts[integration.id].name"
                                :placeholder="t.integrations.channel_name_placeholder"
                            />
                            <p
                                v-if="formErrors[integration.id]?.name"
                                class="text-sm text-destructive"
                            >
                                {{ formErrors[integration.id].name }}
                            </p>
                        </div>

                        <div
                            v-for="field in driverFields(integration.driver)"
                            :key="`${integration.id}-${field.key}`"
                            class="grid gap-2"
                        >
                            <Label :for="`${field.key}-${integration.id}`">
                                {{ field.label }}
                            </Label>
                            <Input
                                :id="`${field.key}-${integration.id}`"
                                v-model="drafts[integration.id].settings[field.key]"
                                :type="field.type ?? 'text'"
                                :placeholder="field.placeholder"
                                autocomplete="off"
                            />
                            <p
                                v-if="
                                    formErrors[integration.id]?.[
                                        `settings.${field.key}`
                                    ]
                                "
                                class="text-sm text-destructive"
                            >
                                {{
                                    formErrors[integration.id][
                                        `settings.${field.key}`
                                    ]
                                }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="space-y-4 rounded-2xl border border-border bg-background/70 p-4">
                    <Heading
                        variant="small"
                        :title="t.integrations.group_access"
                        :description="t.integrations.group_access_description"
                    />

                    <div class="space-y-3">
                        <div
                            v-for="group in groups"
                            :key="`${integration.id}-${group.id}`"
                            class="rounded-xl border border-border bg-card p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <div class="font-medium">
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
                                    class="rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground"
                                >
                                    {{ t.integrations.members }}:
                                    {{ group.users_count }}
                                </span>
                            </div>

                            <div class="mt-4 grid gap-2">
                                <select
                                    v-model="drafts[integration.id].group_accesses[group.id]"
                                    class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                >
                                    <option
                                        v-for="level in accessLevels"
                                        :key="level.key"
                                        :value="level.key"
                                    >
                                        {{ level.label }}
                                    </option>
                                </select>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        accessDescriptionMap[
                                            drafts[integration.id].group_accesses[
                                                group.id
                                            ]
                                        ]
                                    }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-between gap-3 border-t border-border pt-5">
                <div class="text-sm text-muted-foreground">
                    {{ t.integrations.super_admin_access }}:
                    {{ accessLabelMap[superAdminAccessLevel] ?? t.integrations.full_access }}
                </div>

                <Button
                    type="button"
                    :disabled="savingIntegrationId === integration.id"
                    @click="saveIntegration(integration)"
                >
                    {{ t.integrations.save_channel }}
                </Button>
            </div>
        </section>
    </div>
</template>

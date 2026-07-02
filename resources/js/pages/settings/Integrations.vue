<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { MessageSquareShare, ShieldCheck } from '@lucide/vue';
import { computed, ref, watch, watchEffect } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
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

type IntegrationSettingValue = string | boolean | null;

type IntegrationRow = {
    id: number;
    driver: string;
    name: string;
    description: string | null;
    is_active: boolean;
    settings: Record<string, IntegrationSettingValue>;
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
    settings: Record<string, string | boolean>;
    group_accesses: Record<number, string>;
};

type DriverFieldOption = {
    value: string;
    label: string;
};

type DriverField = {
    key: string;
    label: string;
    type: 'text' | 'password' | 'select' | 'checkbox';
    placeholder?: string;
    options?: DriverFieldOption[];
    description?: string;
    fullWidth?: boolean;
};

type DriverFieldSection = {
    title: string;
    description?: string;
    fields: DriverField[];
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
const editorOpen = ref(false);
const selectedIntegrationId = ref<number | null>(null);

const telephonyResponsibleOptions = computed<DriverFieldOption[]>(() => [
    {
        value: 'call_receiver',
        label: t.value.integrations.responsible_call_receiver,
    },
    {
        value: 'last_contact_owner',
        label: t.value.integrations.responsible_last_contact_owner,
    },
    {
        value: 'round_robin_queue',
        label: t.value.integrations.responsible_round_robin_queue,
    },
]);

const telephonyMissedCallOptions = computed<DriverFieldOption[]>(() => [
    {
        value: 'notify_only',
        label: t.value.integrations.missed_call_notify_only,
    },
    {
        value: 'create_activity',
        label: t.value.integrations.missed_call_create_activity,
    },
    {
        value: 'create_contact_and_activity',
        label: t.value.integrations.missed_call_create_contact_and_activity,
    },
]);

const telephonyRecordingOptions = computed<DriverFieldOption[]>(() => [
    {
        value: 'disabled',
        label: t.value.integrations.recording_disabled,
    },
    {
        value: 'incoming_only',
        label: t.value.integrations.recording_incoming_only,
    },
    {
        value: 'outgoing_only',
        label: t.value.integrations.recording_outgoing_only,
    },
    {
        value: 'all_calls',
        label: t.value.integrations.recording_all_calls,
    },
]);

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
                        typeof value === 'boolean' ? value : (value ?? ''),
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

watch(
    () => props.integrations,
    (integrations) => {
        if (integrations.length === 0) {
            selectedIntegrationId.value = null;
            editorOpen.value = false;

            return;
        }

        if (selectedIntegrationId.value === null) {
            return;
        }

        const hasSelectedIntegration = integrations.some(
            (integration) => integration.id === selectedIntegrationId.value,
        );

        if (!hasSelectedIntegration) {
            selectedIntegrationId.value = integrations[0]?.id ?? null;
        }
    },
    { immediate: true },
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

const selectedIntegration = computed(() => {
    if (selectedIntegrationId.value === null) {
        return null;
    }

    return (
        props.integrations.find(
            (integration) => integration.id === selectedIntegrationId.value,
        ) ?? null
    );
});

const selectedDraft = computed(() => {
    if (!selectedIntegration.value) {
        return null;
    }

    return drafts.value[selectedIntegration.value.id] ?? null;
});

const integrationTitle = (driver: string): string => {
    if (driver === 'whatsapp_business') {
        return t.value.integrations.whatsapp_business;
    }

    if (driver === 'telephony') {
        return t.value.integrations.telephony;
    }

    return t.value.integrations.telegram;
};

const configuredSettingsCount = (integrationId: number): number => {
    const settings = drafts.value[integrationId]?.settings;

    if (!settings) {
        return 0;
    }

    return Object.values(settings).filter((value) => {
        if (typeof value === 'boolean') {
            return value;
        }

        return value.trim() !== '';
    }).length;
};

const telephonyProviderSummary = (integrationId: number): string => {
    const provider = drafts.value[integrationId]?.settings.provider_name;

    return typeof provider === 'string' && provider.trim() !== ''
        ? provider
        : t.value.common.not_specified;
};

const driverFieldSections = (driver: string): DriverFieldSection[] => {
    if (driver === 'whatsapp_business') {
        return [
            {
                title: t.value.integrations.connection_settings,
                fields: [
                    {
                        key: 'api_url',
                        label: t.value.integrations.api_url,
                        type: 'text',
                        placeholder: t.value.integrations.api_url_placeholder,
                    },
                    {
                        key: 'channel_id',
                        label: t.value.integrations.channel_id,
                        type: 'text',
                        placeholder:
                            t.value.integrations.channel_id_placeholder,
                    },
                    {
                        key: 'phone_number',
                        label: t.value.integrations.phone_number,
                        type: 'text',
                        placeholder:
                            t.value.integrations.phone_number_placeholder,
                    },
                    {
                        key: 'api_token',
                        label: t.value.integrations.api_token,
                        type: 'password',
                        placeholder: t.value.integrations.api_token_placeholder,
                    },
                ],
            },
        ];
    }

    if (driver === 'telephony') {
        return [
            {
                title: t.value.integrations.telephony_connection_title,
                description:
                    t.value.integrations.telephony_connection_description,
                fields: [
                    {
                        key: 'provider_name',
                        label: t.value.integrations.provider_name,
                        type: 'text',
                        placeholder:
                            t.value.integrations.provider_name_placeholder,
                    },
                    {
                        key: 'api_url',
                        label: t.value.integrations.api_url,
                        type: 'text',
                        placeholder: t.value.integrations.api_url_placeholder,
                    },
                    {
                        key: 'account_id',
                        label: t.value.integrations.account_id,
                        type: 'text',
                        placeholder:
                            t.value.integrations.account_id_placeholder,
                    },
                    {
                        key: 'extension_number',
                        label: t.value.integrations.extension_number,
                        type: 'text',
                        placeholder:
                            t.value.integrations.extension_number_placeholder,
                    },
                    {
                        key: 'phone_number',
                        label: t.value.integrations.phone_number,
                        type: 'text',
                        placeholder:
                            t.value.integrations.phone_number_placeholder,
                    },
                    {
                        key: 'default_line',
                        label: t.value.integrations.default_line,
                        type: 'text',
                        placeholder:
                            t.value.integrations.default_line_placeholder,
                    },
                    {
                        key: 'api_token',
                        label: t.value.integrations.api_token,
                        type: 'password',
                        placeholder: t.value.integrations.api_token_placeholder,
                    },
                    {
                        key: 'webhook_url',
                        label: t.value.integrations.webhook_url,
                        type: 'text',
                        placeholder:
                            t.value.integrations.webhook_url_placeholder,
                        fullWidth: true,
                    },
                    {
                        key: 'webhook_secret',
                        label: t.value.integrations.webhook_secret,
                        type: 'password',
                        placeholder:
                            t.value.integrations.webhook_secret_placeholder,
                        fullWidth: true,
                    },
                ],
            },
            {
                title: t.value.integrations.telephony_routing_title,
                description: t.value.integrations.telephony_routing_description,
                fields: [
                    {
                        key: 'outbound_caller_id',
                        label: t.value.integrations.outbound_caller_id,
                        type: 'text',
                        placeholder:
                            t.value.integrations.outbound_caller_id_placeholder,
                    },
                    {
                        key: 'responsible_mode',
                        label: t.value.integrations.responsible_mode,
                        type: 'select',
                        placeholder:
                            t.value.integrations.responsible_mode_placeholder,
                        options: telephonyResponsibleOptions.value,
                    },
                    {
                        key: 'missed_call_mode',
                        label: t.value.integrations.missed_call_mode,
                        type: 'select',
                        placeholder:
                            t.value.integrations.missed_call_mode_placeholder,
                        options: telephonyMissedCallOptions.value,
                    },
                    {
                        key: 'recording_mode',
                        label: t.value.integrations.recording_mode,
                        type: 'select',
                        placeholder:
                            t.value.integrations.recording_mode_placeholder,
                        options: telephonyRecordingOptions.value,
                    },
                ],
            },
            {
                title: t.value.integrations.telephony_automation_title,
                description:
                    t.value.integrations.telephony_automation_description,
                fields: [
                    {
                        key: 'create_contact_for_unknown_calls',
                        label: t.value.integrations
                            .create_contact_for_unknown_calls,
                        type: 'checkbox',
                        description:
                            t.value.integrations
                                .create_contact_for_unknown_calls_description,
                        fullWidth: true,
                    },
                    {
                        key: 'create_activity_for_missed_calls',
                        label: t.value.integrations
                            .create_activity_for_missed_calls,
                        type: 'checkbox',
                        description:
                            t.value.integrations
                                .create_activity_for_missed_calls_description,
                        fullWidth: true,
                    },
                    {
                        key: 'log_incoming_calls',
                        label: t.value.integrations.log_incoming_calls,
                        type: 'checkbox',
                        description:
                            t.value.integrations.log_incoming_calls_description,
                        fullWidth: true,
                    },
                    {
                        key: 'log_outgoing_calls',
                        label: t.value.integrations.log_outgoing_calls,
                        type: 'checkbox',
                        description:
                            t.value.integrations.log_outgoing_calls_description,
                        fullWidth: true,
                    },
                ],
            },
        ];
    }

    return [
        {
            title: t.value.integrations.connection_settings,
            fields: [
                {
                    key: 'bot_username',
                    label: t.value.integrations.bot_username,
                    type: 'text',
                    placeholder: t.value.integrations.bot_username_placeholder,
                },
                {
                    key: 'bot_token',
                    label: t.value.integrations.bot_token,
                    type: 'password',
                    placeholder: t.value.integrations.bot_token_placeholder,
                },
                {
                    key: 'webhook_secret',
                    label: t.value.integrations.webhook_secret,
                    type: 'password',
                    placeholder:
                        t.value.integrations.webhook_secret_placeholder,
                },
            ],
        },
    ];
};

const openEditor = (integrationId: number): void => {
    selectedIntegrationId.value = integrationId;
    editorOpen.value = true;
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

        <section class="space-y-5 rounded-2xl border border-border bg-card p-5">
            <Heading
                variant="small"
                :title="t.integrations.list_title"
                :description="t.integrations.list_description"
            />

            <div
                v-if="integrations.length === 0"
                class="rounded-2xl border border-dashed border-border p-6 text-sm text-muted-foreground"
            >
                {{ t.integrations.empty }}
            </div>

            <div v-else class="grid gap-4 xl:grid-cols-2">
                <article
                    v-for="integration in integrations"
                    :key="integration.id"
                    class="space-y-5 rounded-2xl border border-border bg-background/70 p-5"
                >
                    <div
                        class="flex flex-col gap-4 border-b border-border pb-5 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div class="space-y-2">
                            <p
                                class="text-sm font-medium text-muted-foreground"
                            >
                                {{ integrationTitle(integration.driver) }}
                            </p>
                            <div class="flex items-center gap-3">
                                <h2 class="text-lg font-semibold">
                                    {{
                                        drafts[integration.id]?.name ||
                                        integration.name
                                    }}
                                </h2>
                                <Badge
                                    :variant="
                                        drafts[integration.id]?.is_active
                                            ? 'default'
                                            : 'secondary'
                                    "
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
                                class="max-w-2xl text-sm text-muted-foreground"
                            >
                                {{ integration.description }}
                            </p>
                        </div>

                        <Button
                            type="button"
                            variant="outline"
                            @click="openEditor(integration.id)"
                        >
                            {{ t.integrations.edit_sidebar }}
                        </Button>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div
                            class="rounded-xl border border-border bg-card p-4"
                        >
                            <div class="text-sm text-muted-foreground">
                                {{ t.common.platform }}
                            </div>
                            <div class="mt-1 font-medium">
                                {{ integrationTitle(integration.driver) }}
                            </div>
                        </div>

                        <div
                            class="rounded-xl border border-border bg-card p-4"
                        >
                            <div class="text-sm text-muted-foreground">
                                {{ t.integrations.connection_settings }}
                            </div>
                            <div class="mt-1 font-medium">
                                {{
                                    t.integrations.configured_fields.replace(
                                        ':count',
                                        String(
                                            configuredSettingsCount(
                                                integration.id,
                                            ),
                                        ),
                                    )
                                }}
                            </div>
                        </div>

                        <div
                            v-if="integration.driver === 'telephony'"
                            class="rounded-xl border border-border bg-card p-4 sm:col-span-2"
                        >
                            <div class="text-sm text-muted-foreground">
                                {{ t.integrations.provider_name }}
                            </div>
                            <div class="mt-1 font-medium">
                                {{ telephonyProviderSummary(integration.id) }}
                            </div>
                        </div>
                    </div>
                </article>
            </div>
        </section>
    </div>

    <Sheet :open="editorOpen" @update:open="(isOpen) => (editorOpen = isOpen)">
        <SheetContent
            side="right"
            class="w-full overflow-y-auto p-0 sm:max-w-3xl"
        >
            <template v-if="selectedIntegration && selectedDraft">
                <SheetHeader class="border-b border-border px-6 py-6 text-left">
                    <div class="space-y-3 pr-8">
                        <p class="text-sm font-medium text-muted-foreground">
                            {{ integrationTitle(selectedIntegration.driver) }}
                        </p>
                        <div class="flex items-center gap-3">
                            <SheetTitle>
                                {{
                                    selectedDraft.name ||
                                    selectedIntegration.name
                                }}
                            </SheetTitle>
                            <Badge
                                :variant="
                                    selectedDraft.is_active
                                        ? 'default'
                                        : 'secondary'
                                "
                            >
                                {{
                                    selectedDraft.is_active
                                        ? t.integrations.active
                                        : t.integrations.inactive
                                }}
                            </Badge>
                        </div>
                        <SheetDescription>
                            {{ t.integrations.editor_description }}
                        </SheetDescription>
                    </div>
                </SheetHeader>

                <div class="space-y-6 px-6 py-6">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <section
                            class="rounded-2xl border border-border bg-card p-5"
                        >
                            <div
                                class="flex items-center gap-2 text-base font-medium"
                            >
                                <ShieldCheck
                                    class="size-4 text-muted-foreground"
                                />
                                {{ t.integrations.super_admin_access }}
                            </div>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{
                                    t.integrations
                                        .super_admin_access_description
                                }}
                            </p>
                            <Badge class="mt-4" variant="secondary">
                                {{ t.integrations.full_access }}
                            </Badge>
                        </section>

                        <section
                            class="rounded-2xl border border-border bg-card p-5"
                        >
                            <div
                                class="flex items-center gap-2 text-base font-medium"
                            >
                                <MessageSquareShare
                                    class="size-4 text-muted-foreground"
                                />
                                {{
                                    t.integrations.conversation_owner_rule_title
                                }}
                            </div>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{
                                    t.integrations
                                        .conversation_owner_rule_description
                                }}
                            </p>
                        </section>
                    </div>

                    <div
                        class="space-y-4 rounded-2xl border border-border bg-card p-5"
                    >
                        <Heading
                            variant="small"
                            :title="t.integrations.connection_settings"
                        />

                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="grid gap-2 md:col-span-2">
                                <Label
                                    :for="`integration-name-${selectedIntegration.id}`"
                                >
                                    {{ t.integrations.channel_name }}
                                </Label>
                                <Input
                                    :id="`integration-name-${selectedIntegration.id}`"
                                    v-model="selectedDraft.name"
                                    :placeholder="
                                        t.integrations.channel_name_placeholder
                                    "
                                />
                                <InputError
                                    :message="
                                        formErrors[selectedIntegration.id]?.name
                                    "
                                />
                            </div>
                        </div>
                    </div>

                    <section
                        v-for="section in driverFieldSections(
                            selectedIntegration.driver,
                        )"
                        :key="section.title"
                        class="space-y-4 rounded-2xl border border-border bg-card p-5"
                    >
                        <Heading
                            variant="small"
                            :title="section.title"
                            :description="section.description"
                        />

                        <div class="grid gap-4 md:grid-cols-2">
                            <div
                                v-for="field in section.fields"
                                :key="`${selectedIntegration.id}-${field.key}`"
                                class="grid gap-2"
                                :class="field.fullWidth ? 'md:col-span-2' : ''"
                            >
                                <template v-if="field.type === 'checkbox'">
                                    <label
                                        class="rounded-xl border border-border bg-background/70 p-4"
                                    >
                                        <div class="flex items-start gap-3">
                                            <Checkbox
                                                :checked="
                                                    selectedDraft.settings[
                                                        field.key
                                                    ] === true
                                                "
                                                @update:checked="
                                                    (
                                                        value:
                                                            | boolean
                                                            | 'indeterminate',
                                                    ) => {
                                                        selectedDraft.settings[
                                                            field.key
                                                        ] = value === true;
                                                    }
                                                "
                                            />
                                            <div class="space-y-1">
                                                <div class="font-medium">
                                                    {{ field.label }}
                                                </div>
                                                <p
                                                    v-if="field.description"
                                                    class="text-sm text-muted-foreground"
                                                >
                                                    {{ field.description }}
                                                </p>
                                            </div>
                                        </div>
                                    </label>
                                </template>

                                <template v-else-if="field.type === 'select'">
                                    <Label
                                        :for="`${field.key}-${selectedIntegration.id}`"
                                    >
                                        {{ field.label }}
                                    </Label>
                                    <select
                                        :id="`${field.key}-${selectedIntegration.id}`"
                                        v-model="
                                            selectedDraft.settings[field.key]
                                        "
                                        class="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                                    >
                                        <option value="">
                                            {{
                                                field.placeholder ??
                                                t.common.not_specified
                                            }}
                                        </option>
                                        <option
                                            v-for="option in field.options"
                                            :key="option.value"
                                            :value="option.value"
                                        >
                                            {{ option.label }}
                                        </option>
                                    </select>
                                </template>

                                <template v-else>
                                    <Label
                                        :for="`${field.key}-${selectedIntegration.id}`"
                                    >
                                        {{ field.label }}
                                    </Label>
                                    <Input
                                        :id="`${field.key}-${selectedIntegration.id}`"
                                        v-model="
                                            selectedDraft.settings[field.key]
                                        "
                                        :type="field.type"
                                        :placeholder="field.placeholder"
                                        autocomplete="off"
                                    />
                                </template>

                                <InputError
                                    :message="
                                        formErrors[selectedIntegration.id]?.[
                                            `settings.${field.key}`
                                        ]
                                    "
                                />
                            </div>
                        </div>
                    </section>

                    <div
                        class="space-y-4 rounded-2xl border border-border bg-background/70 p-5"
                    >
                        <Heading
                            variant="small"
                            :title="t.integrations.group_access"
                            :description="
                                t.integrations.group_access_description
                            "
                        />

                        <label class="flex items-center gap-2 text-sm">
                            <Checkbox
                                :checked="selectedDraft.is_active"
                                @update:checked="
                                    (value: boolean | 'indeterminate') => {
                                        selectedDraft.is_active =
                                            value === true;
                                    }
                                "
                            />
                            <span>{{ t.integrations.channel_status }}</span>
                        </label>

                        <div class="space-y-3">
                            <div
                                v-for="group in groups"
                                :key="`${selectedIntegration.id}-${group.id}`"
                                class="rounded-xl border border-border bg-card p-4"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
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
                                        v-model="
                                            selectedDraft.group_accesses[
                                                group.id
                                            ]
                                        "
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
                                                selectedDraft.group_accesses[
                                                    group.id
                                                ]
                                            ]
                                        }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="flex items-center justify-between gap-3 border-t border-border pt-5"
                    >
                        <div class="text-sm text-muted-foreground">
                            {{ t.integrations.super_admin_access }}:
                            {{
                                accessLabelMap[superAdminAccessLevel] ??
                                t.integrations.full_access
                            }}
                        </div>

                        <Button
                            type="button"
                            :disabled="
                                savingIntegrationId === selectedIntegration.id
                            "
                            @click="saveIntegration(selectedIntegration)"
                        >
                            {{ t.integrations.save_channel }}
                        </Button>
                    </div>
                </div>
            </template>
        </SheetContent>
    </Sheet>
</template>

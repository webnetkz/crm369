<script setup lang="ts">
import { Head, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    ArrowDownToLine,
    ArrowUpFromLine,
    Boxes,
    CheckCircle2,
    Clock3,
    Database,
    LockKeyhole,
    Plus,
    Power,
    RefreshCcw,
    ServerCog,
    Settings2,
    ShieldCheck,
    Trash2,
    XCircle,
} from '@lucide/vue';
import { computed, ref, watch, watchEffect } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
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
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { useLanguage } from '@/composables/useLanguage';
import { destroy, edit, store, test, update } from '@/routes/settings/one-c';

type SelectOption = {
    value: string;
    label: string;
    description: string;
};

type EntityDefinition = {
    key: string;
    label: string;
    description: string;
    directions: string[];
};

type EntitySettings = {
    enabled: boolean;
    direction: string;
    source_of_truth: string;
};

type UpdatedBy = {
    id: number;
    name: string;
    email: string;
} | null;

type OneCIntegration = {
    id: number;
    name: string;
    product: string;
    transport: string;
    is_enabled: boolean;
    base_url: string | null;
    api_path: string;
    auth_type: string;
    username: string | null;
    password_configured: boolean;
    token_configured: boolean;
    verify_tls: boolean;
    connect_timeout_seconds: number;
    request_timeout_seconds: number;
    import_enabled: boolean;
    export_enabled: boolean;
    schedule_enabled: boolean;
    sync_interval_minutes: number;
    sync_window_start: string | null;
    sync_window_end: string | null;
    batch_size: number;
    default_sync_mode: string;
    conflict_strategy: string;
    stop_on_error: boolean;
    dry_run: boolean;
    entities: Record<string, EntitySettings>;
    enabled_at: string | null;
    disabled_at: string | null;
    last_tested_at: string | null;
    last_test_succeeded: boolean | null;
    last_test_duration_ms: number | null;
    last_test_message: string | null;
    last_sync_at: string | null;
    last_successful_sync_at: string | null;
    last_sync_status: string | null;
    last_sync_message: string | null;
    updated_at: string | null;
    updated_by: UpdatedBy;
};

type IntegrationFormData = {
    name: string;
    product: string;
    transport: string;
    is_enabled: boolean;
    base_url: string;
    api_path: string;
    auth_type: string;
    username: string;
    password: string;
    token: string;
    verify_tls: boolean;
    connect_timeout_seconds: number;
    request_timeout_seconds: number;
    import_enabled: boolean;
    export_enabled: boolean;
    schedule_enabled: boolean;
    sync_interval_minutes: number;
    sync_window_start: string;
    sync_window_end: string;
    batch_size: number;
    default_sync_mode: string;
    conflict_strategy: string;
    stop_on_error: boolean;
    dry_run: boolean;
    entities: Record<string, EntitySettings>;
};

type BooleanSettingKey =
    | 'import_enabled'
    | 'export_enabled'
    | 'schedule_enabled'
    | 'dry_run'
    | 'stop_on_error';

const props = defineProps<{
    integrations: OneCIntegration[];
    selectedIntegrationId: number | null;
    productOptions: SelectOption[];
    transportOptions: SelectOption[];
    entityDefinitions: EntityDefinition[];
}>();

const { language, t } = useLanguage();
const selectedIntegrationId = ref<number | null>(props.selectedIntegrationId);
const createDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const testingConnection = ref(false);

const blankEntities = (): Record<string, EntitySettings> => {
    return Object.fromEntries(
        props.entityDefinitions.map((definition) => [
            definition.key,
            {
                enabled: false,
                direction: definition.directions[0] ?? 'import',
                source_of_truth: 'one_c',
            },
        ]),
    );
};

const emptyFormData = (): IntegrationFormData => ({
    name: '',
    product: 'enterprise_management',
    transport: 'odata',
    is_enabled: false,
    base_url: '',
    api_path: '/odata/standard.odata',
    auth_type: 'basic',
    username: '',
    password: '',
    token: '',
    verify_tls: true,
    connect_timeout_seconds: 5,
    request_timeout_seconds: 30,
    import_enabled: true,
    export_enabled: false,
    schedule_enabled: false,
    sync_interval_minutes: 60,
    sync_window_start: '',
    sync_window_end: '',
    batch_size: 100,
    default_sync_mode: 'incremental',
    conflict_strategy: 'one_c_wins',
    stop_on_error: true,
    dry_run: false,
    entities: blankEntities(),
});

const formDataFor = (integration: OneCIntegration): IntegrationFormData => ({
    name: integration.name,
    product: integration.product,
    transport: integration.transport,
    is_enabled: integration.is_enabled,
    base_url: integration.base_url ?? '',
    api_path: integration.api_path,
    auth_type: integration.auth_type,
    username: integration.username ?? '',
    password: '',
    token: '',
    verify_tls: integration.verify_tls,
    connect_timeout_seconds: integration.connect_timeout_seconds,
    request_timeout_seconds: integration.request_timeout_seconds,
    import_enabled: integration.import_enabled,
    export_enabled: integration.export_enabled,
    schedule_enabled: integration.schedule_enabled,
    sync_interval_minutes: integration.sync_interval_minutes,
    sync_window_start: integration.sync_window_start?.slice(0, 5) ?? '',
    sync_window_end: integration.sync_window_end?.slice(0, 5) ?? '',
    batch_size: integration.batch_size,
    default_sync_mode: integration.default_sync_mode,
    conflict_strategy: integration.conflict_strategy,
    stop_on_error: integration.stop_on_error,
    dry_run: integration.dry_run,
    entities: Object.fromEntries(
        Object.entries(integration.entities).map(([key, settings]) => [
            key,
            { ...settings },
        ]),
    ),
});

const form = useForm<IntegrationFormData>(emptyFormData());
const createForm = useForm({
    name: '',
    product: 'enterprise_management',
    transport: 'odata',
});
const deleteForm = useForm({
    confirmation: '',
});

const selectedIntegration = computed<OneCIntegration | null>(() => {
    if (selectedIntegrationId.value === null) {
        return null;
    }

    return (
        props.integrations.find(
            (integration) => integration.id === selectedIntegrationId.value,
        ) ?? null
    );
});

watch(
    () => props.integrations,
    (integrations) => {
        if (integrations.length === 0) {
            selectedIntegrationId.value = null;

            return;
        }

        if (
            selectedIntegrationId.value === null ||
            !integrations.some(
                (integration) => integration.id === selectedIntegrationId.value,
            )
        ) {
            selectedIntegrationId.value = integrations[0]?.id ?? null;
        }
    },
    { immediate: true },
);

watch(
    selectedIntegration,
    (integration) => {
        if (!integration) {
            form.defaults(emptyFormData());
            form.reset();
            form.clearErrors();

            return;
        }

        form.defaults(formDataFor(integration));
        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.one_c.title,
                href: edit(),
            },
        ],
    });
});

const selectIntegration = (integrationId: number): void => {
    selectedIntegrationId.value = integrationId;
};

const createConnection = (): void => {
    createForm.post(store.url(), {
        preserveScroll: true,
        onSuccess: () => {
            createDialogOpen.value = false;
            createForm.reset();
        },
    });
};

const saveConnection = (): void => {
    if (!selectedIntegration.value) {
        return;
    }

    form.patch(update.url(selectedIntegration.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.password = '';
            form.token = '';
        },
    });
};

const testConnection = (): void => {
    if (!selectedIntegration.value || form.isDirty || testingConnection.value) {
        return;
    }

    testingConnection.value = true;
    router.post(
        test.url(selectedIntegration.value.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                testingConnection.value = false;
            },
        },
    );
};

const deleteConnection = (): void => {
    if (!selectedIntegration.value) {
        return;
    }

    deleteForm.delete(destroy.url(selectedIntegration.value.id), {
        preserveScroll: true,
        onError: (errors) => {
            if (errors.delete) {
                deleteForm.setError('confirmation', errors.delete);
            }
        },
        onSuccess: () => {
            deleteDialogOpen.value = false;
        },
    });
};

const enabledEntityCount = computed(() => {
    return Object.values(form.entities).filter((entity) => entity.enabled)
        .length;
});

const credentialsReady = computed(() => {
    if (form.auth_type === 'none') {
        return true;
    }

    if (form.auth_type === 'basic') {
        return (
            form.username.trim() !== '' &&
            (form.password.trim() !== '' ||
                (selectedIntegration.value?.auth_type === 'basic' &&
                    selectedIntegration.value.password_configured))
        );
    }

    return (
        form.token.trim() !== '' ||
        (selectedIntegration.value?.auth_type === 'bearer' &&
            selectedIntegration.value.token_configured)
    );
});

const readinessItems = computed(() => [
    {
        label: t.value.one_c.readiness_endpoint,
        ready: form.base_url.trim() !== '',
    },
    {
        label: t.value.one_c.readiness_credentials,
        ready: credentialsReady.value,
    },
    {
        label: t.value.one_c.readiness_entities,
        ready: enabledEntityCount.value > 0,
    },
    {
        label: t.value.one_c.readiness_exchange,
        ready: form.import_enabled || form.export_enabled,
    },
]);

const exchangeToggles = computed(() => [
    {
        key: 'import_enabled' as BooleanSettingKey,
        icon: ArrowDownToLine,
        title: t.value.one_c.import_enabled,
        description: t.value.one_c.import_enabled_description,
    },
    {
        key: 'export_enabled' as BooleanSettingKey,
        icon: ArrowUpFromLine,
        title: t.value.one_c.export_enabled,
        description: t.value.one_c.export_enabled_description,
    },
    {
        key: 'schedule_enabled' as BooleanSettingKey,
        icon: Clock3,
        title: t.value.one_c.schedule_enabled,
        description: t.value.one_c.schedule_enabled_description,
    },
    {
        key: 'dry_run' as BooleanSettingKey,
        icon: AlertTriangle,
        title: t.value.one_c.dry_run,
        description: t.value.one_c.dry_run_description,
    },
    {
        key: 'stop_on_error' as BooleanSettingKey,
        icon: XCircle,
        title: t.value.one_c.stop_on_error,
        description: t.value.one_c.stop_on_error_description,
    },
]);

const toggleFormBoolean = (key: BooleanSettingKey): void => {
    form[key] = !form[key];
};

const productLabel = (product: string): string => {
    return (
        props.productOptions.find((option) => option.value === product)
            ?.label ?? product
    );
};

const directionLabel = (direction: string): string => {
    const labels: Record<string, string> = {
        import: t.value.one_c.direction_import,
        export: t.value.one_c.direction_export,
        bidirectional: t.value.one_c.direction_bidirectional,
    };

    return labels[direction] ?? direction;
};

const sourceLabel = (source: string): string => {
    const labels: Record<string, string> = {
        one_c: t.value.one_c.source_one_c,
        crm: t.value.one_c.source_crm,
        newest: t.value.one_c.source_newest,
    };

    return labels[source] ?? source;
};

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return t.value.one_c.never;
    }

    return new Intl.DateTimeFormat(
        language.value === 'ru' ? 'ru-RU' : 'en-US',
        {
            dateStyle: 'medium',
            timeStyle: 'short',
        },
    ).format(new Date(value));
};

const apiPathPlaceholder = computed(() => {
    return form.transport === 'odata'
        ? t.value.one_c.api_path_odata_placeholder
        : t.value.one_c.api_path_http_placeholder;
});

const secretPlaceholder = (configured: boolean, fallback: string): string => {
    return configured ? t.value.one_c.secret_configured_placeholder : fallback;
};
</script>

<template>
    <Head :title="t.one_c.title" />

    <div class="space-y-8">
        <h1 class="sr-only">{{ t.one_c.title }}</h1>

        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
        >
            <Heading
                variant="small"
                :title="t.one_c.title"
                :description="t.one_c.description"
            />

            <Button
                type="button"
                class="shrink-0"
                @click="createDialogOpen = true"
            >
                <Plus class="size-4" />
                {{ t.one_c.add_connection }}
            </Button>
        </div>

        <Alert class="border-amber-500/25 bg-amber-500/5">
            <ShieldCheck class="size-4 text-amber-600 dark:text-amber-400" />
            <AlertTitle>{{ t.one_c.super_admin_only }}</AlertTitle>
            <AlertDescription>{{
                t.one_c.module_description
            }}</AlertDescription>
        </Alert>

        <div class="grid gap-6 2xl:grid-cols-[18rem_minmax(0,1fr)]">
            <aside class="space-y-3 2xl:sticky 2xl:top-36 2xl:self-start">
                <div class="space-y-1 px-1">
                    <h2 class="font-semibold">
                        {{ t.one_c.connections_title }}
                    </h2>
                    <p class="text-sm leading-5 text-muted-foreground">
                        {{ t.one_c.connections_description }}
                    </p>
                </div>

                <div v-if="integrations.length" class="space-y-2">
                    <button
                        v-for="integration in integrations"
                        :key="integration.id"
                        type="button"
                        class="w-full rounded-xl border p-4 text-left transition-colors"
                        :class="
                            selectedIntegrationId === integration.id
                                ? 'border-primary/35 bg-primary/5 shadow-xs'
                                : 'border-border bg-card hover:bg-muted/40'
                        "
                        @click="selectIntegration(integration.id)"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg"
                                :class="
                                    integration.is_enabled
                                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                <Database class="size-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div
                                    class="flex items-center justify-between gap-2"
                                >
                                    <p class="truncate text-sm font-medium">
                                        {{ integration.name }}
                                    </p>
                                    <span
                                        class="size-2 shrink-0 rounded-full"
                                        :class="
                                            integration.is_enabled
                                                ? 'bg-emerald-500'
                                                : 'bg-muted-foreground/40'
                                        "
                                    />
                                </div>
                                <p
                                    class="mt-1 truncate text-xs text-muted-foreground"
                                >
                                    {{ productLabel(integration.product) }}
                                </p>
                                <div
                                    class="mt-2 flex items-center gap-1.5 text-xs"
                                >
                                    <CheckCircle2
                                        v-if="
                                            integration.last_test_succeeded ===
                                            true
                                        "
                                        class="size-3.5 text-emerald-500"
                                    />
                                    <XCircle
                                        v-else-if="
                                            integration.last_test_succeeded ===
                                            false
                                        "
                                        class="size-3.5 text-destructive"
                                    />
                                    <Activity
                                        v-else
                                        class="size-3.5 text-muted-foreground"
                                    />
                                    <span
                                        class="truncate text-muted-foreground"
                                    >
                                        {{
                                            integration.last_test_succeeded ===
                                            true
                                                ? t.one_c.test_passed
                                                : integration.last_test_succeeded ===
                                                    false
                                                  ? t.one_c.test_failed
                                                  : t.one_c.not_tested
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </button>
                </div>

                <button
                    type="button"
                    class="flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-border px-4 py-3 text-sm text-muted-foreground transition-colors hover:border-primary/40 hover:bg-primary/5 hover:text-foreground"
                    @click="createDialogOpen = true"
                >
                    <Plus class="size-4" />
                    {{ t.one_c.add_connection }}
                </button>
            </aside>

            <div
                v-if="!selectedIntegration"
                class="flex min-h-96 flex-col items-center justify-center gap-4 rounded-2xl border border-dashed border-border bg-card/40 p-10 text-center"
            >
                <div
                    class="flex size-14 items-center justify-center rounded-2xl bg-muted"
                >
                    <Database class="size-6 text-muted-foreground" />
                </div>
                <div class="max-w-md space-y-2">
                    <h2 class="text-lg font-semibold">
                        {{ t.one_c.empty_title }}
                    </h2>
                    <p class="text-sm leading-6 text-muted-foreground">
                        {{ t.one_c.empty_description }}
                    </p>
                </div>
                <Button type="button" @click="createDialogOpen = true">
                    <Plus class="size-4" />
                    {{ t.one_c.create_connection }}
                </Button>
            </div>

            <div v-else class="min-w-0 space-y-6">
                <section
                    class="rounded-2xl border border-border bg-card p-5 shadow-xs"
                >
                    <div
                        class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between"
                    >
                        <div class="flex min-w-0 items-start gap-4">
                            <div
                                class="flex size-12 shrink-0 items-center justify-center rounded-xl"
                                :class="
                                    form.is_enabled
                                        ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400'
                                        : 'bg-muted text-muted-foreground'
                                "
                            >
                                <Power class="size-5" />
                            </div>
                            <div class="min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="truncate text-lg font-semibold">
                                        {{ selectedIntegration.name }}
                                    </h2>
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs font-medium"
                                        :class="
                                            form.is_enabled
                                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                                : 'bg-muted text-muted-foreground'
                                        "
                                    >
                                        {{
                                            form.is_enabled
                                                ? t.one_c.active
                                                : t.one_c.inactive
                                        }}
                                    </span>
                                </div>
                                <p class="text-sm text-muted-foreground">
                                    {{ t.one_c.operational_help }}
                                </p>
                            </div>
                        </div>

                        <button
                            type="button"
                            role="switch"
                            :aria-checked="form.is_enabled"
                            :aria-label="t.one_c.operational_status"
                            class="relative inline-flex h-8 w-14 shrink-0 items-center rounded-full border transition-colors outline-none focus-visible:ring-2 focus-visible:ring-ring/60 focus-visible:ring-offset-2"
                            :class="
                                form.is_enabled
                                    ? 'border-emerald-500/30 bg-emerald-500'
                                    : 'border-border bg-muted'
                            "
                            @click="form.is_enabled = !form.is_enabled"
                        >
                            <span
                                class="pointer-events-none size-6 rounded-full bg-white shadow-sm transition-transform"
                                :class="
                                    form.is_enabled
                                        ? 'translate-x-7'
                                        : 'translate-x-1'
                                "
                            />
                        </button>
                    </div>
                    <InputError
                        class="mt-3"
                        :message="form.errors.is_enabled"
                    />
                </section>

                <div
                    class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]"
                >
                    <form
                        class="min-w-0 space-y-6"
                        @submit.prevent="saveConnection"
                    >
                        <section
                            class="space-y-6 rounded-2xl border border-border bg-card p-5 shadow-xs"
                        >
                            <div class="flex items-start gap-3">
                                <ServerCog class="mt-0.5 size-5 text-primary" />
                                <div>
                                    <h2 class="font-semibold">
                                        {{ t.one_c.connection_section }}
                                    </h2>
                                    <p
                                        class="mt-1 text-sm leading-5 text-muted-foreground"
                                    >
                                        {{
                                            t.one_c
                                                .connection_section_description
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2 md:col-span-2">
                                    <Label for="one-c-name">{{
                                        t.one_c.connection_name
                                    }}</Label>
                                    <Input
                                        id="one-c-name"
                                        v-model="form.name"
                                        name="name"
                                        :placeholder="
                                            t.one_c.connection_name_placeholder
                                        "
                                    />
                                    <InputError :message="form.errors.name" />
                                </div>

                                <div class="grid gap-2 md:col-span-2">
                                    <Label>{{ t.one_c.product }}</Label>
                                    <div class="grid gap-3 lg:grid-cols-3">
                                        <button
                                            v-for="option in productOptions"
                                            :key="option.value"
                                            type="button"
                                            class="rounded-xl border p-4 text-left transition-colors"
                                            :class="
                                                form.product === option.value
                                                    ? 'border-primary/40 bg-primary/5 ring-1 ring-primary/15'
                                                    : 'border-border hover:bg-muted/40'
                                            "
                                            @click="form.product = option.value"
                                        >
                                            <span
                                                class="text-sm font-semibold"
                                                >{{ option.label }}</span
                                            >
                                            <span
                                                class="mt-1.5 block text-xs leading-5 text-muted-foreground"
                                            >
                                                {{ option.description }}
                                            </span>
                                        </button>
                                    </div>
                                    <InputError
                                        :message="form.errors.product"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="one-c-transport">{{
                                        t.one_c.transport
                                    }}</Label>
                                    <Select v-model="form.transport">
                                        <SelectTrigger
                                            id="one-c-transport"
                                            class="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem
                                                v-for="option in transportOptions"
                                                :key="option.value"
                                                :value="option.value"
                                            >
                                                {{ option.label }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <p
                                        class="text-xs leading-5 text-muted-foreground"
                                    >
                                        {{
                                            transportOptions.find(
                                                (option) =>
                                                    option.value ===
                                                    form.transport,
                                            )?.description
                                        }}
                                    </p>
                                    <InputError
                                        :message="form.errors.transport"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="one-c-api-path">{{
                                        t.one_c.api_path
                                    }}</Label>
                                    <Input
                                        id="one-c-api-path"
                                        v-model="form.api_path"
                                        name="api_path"
                                        :placeholder="apiPathPlaceholder"
                                    />
                                    <InputError
                                        :message="form.errors.api_path"
                                    />
                                </div>

                                <div class="grid gap-2 md:col-span-2">
                                    <Label for="one-c-base-url">{{
                                        t.one_c.base_url
                                    }}</Label>
                                    <Input
                                        id="one-c-base-url"
                                        v-model="form.base_url"
                                        name="base_url"
                                        type="url"
                                        :placeholder="
                                            t.one_c.base_url_placeholder
                                        "
                                        autocomplete="url"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        {{ t.one_c.base_url_help }}
                                    </p>
                                    <InputError
                                        :message="form.errors.base_url"
                                    />
                                </div>
                            </div>
                        </section>

                        <section
                            class="space-y-6 rounded-2xl border border-border bg-card p-5 shadow-xs"
                        >
                            <div class="flex items-start gap-3">
                                <LockKeyhole
                                    class="mt-0.5 size-5 text-primary"
                                />
                                <div>
                                    <h2 class="font-semibold">
                                        {{ t.one_c.security_section }}
                                    </h2>
                                    <p
                                        class="mt-1 text-sm leading-5 text-muted-foreground"
                                    >
                                        {{
                                            t.one_c.security_section_description
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <Label for="one-c-auth-type">{{
                                        t.one_c.auth_type
                                    }}</Label>
                                    <Select v-model="form.auth_type">
                                        <SelectTrigger
                                            id="one-c-auth-type"
                                            class="w-full"
                                        >
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="basic">{{
                                                t.one_c.auth_basic
                                            }}</SelectItem>
                                            <SelectItem value="bearer">{{
                                                t.one_c.auth_bearer
                                            }}</SelectItem>
                                            <SelectItem value="none">{{
                                                t.one_c.auth_none
                                            }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="form.errors.auth_type"
                                    />
                                </div>

                                <div
                                    v-if="form.auth_type === 'basic'"
                                    class="grid gap-2"
                                >
                                    <Label for="one-c-username">{{
                                        t.one_c.username
                                    }}</Label>
                                    <Input
                                        id="one-c-username"
                                        v-model="form.username"
                                        name="username"
                                        :placeholder="
                                            t.one_c.username_placeholder
                                        "
                                        autocomplete="username"
                                    />
                                    <InputError
                                        :message="form.errors.username"
                                    />
                                </div>

                                <div
                                    v-if="form.auth_type === 'basic'"
                                    class="grid gap-2 md:col-span-2"
                                >
                                    <Label for="one-c-password">{{
                                        t.one_c.password
                                    }}</Label>
                                    <Input
                                        id="one-c-password"
                                        v-model="form.password"
                                        name="password"
                                        type="password"
                                        :placeholder="
                                            secretPlaceholder(
                                                selectedIntegration.password_configured &&
                                                    selectedIntegration.auth_type ===
                                                        'basic',
                                                t.one_c.password_placeholder,
                                            )
                                        "
                                        autocomplete="new-password"
                                    />
                                    <InputError
                                        :message="form.errors.password"
                                    />
                                </div>

                                <div
                                    v-if="form.auth_type === 'bearer'"
                                    class="grid gap-2 md:col-span-2"
                                >
                                    <Label for="one-c-token">{{
                                        t.one_c.token
                                    }}</Label>
                                    <Input
                                        id="one-c-token"
                                        v-model="form.token"
                                        name="token"
                                        type="password"
                                        :placeholder="
                                            secretPlaceholder(
                                                selectedIntegration.token_configured &&
                                                    selectedIntegration.auth_type ===
                                                        'bearer',
                                                t.one_c.token_placeholder,
                                            )
                                        "
                                        autocomplete="new-password"
                                    />
                                    <InputError :message="form.errors.token" />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="one-c-connect-timeout">{{
                                        t.one_c.connect_timeout
                                    }}</Label>
                                    <Input
                                        id="one-c-connect-timeout"
                                        v-model="form.connect_timeout_seconds"
                                        name="connect_timeout_seconds"
                                        type="number"
                                        min="1"
                                        max="30"
                                    />
                                    <InputError
                                        :message="
                                            form.errors.connect_timeout_seconds
                                        "
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="one-c-request-timeout">{{
                                        t.one_c.request_timeout
                                    }}</Label>
                                    <Input
                                        id="one-c-request-timeout"
                                        v-model="form.request_timeout_seconds"
                                        name="request_timeout_seconds"
                                        type="number"
                                        min="5"
                                        max="300"
                                    />
                                    <InputError
                                        :message="
                                            form.errors.request_timeout_seconds
                                        "
                                    />
                                </div>
                            </div>

                            <button
                                type="button"
                                role="switch"
                                :aria-checked="form.verify_tls"
                                class="flex w-full items-center justify-between gap-4 rounded-xl border border-border p-4 text-left transition-colors hover:bg-muted/30"
                                @click="form.verify_tls = !form.verify_tls"
                            >
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium">{{
                                        t.one_c.verify_tls
                                    }}</span>
                                    <span
                                        class="mt-1 block text-xs leading-5 text-muted-foreground"
                                    >
                                        {{ t.one_c.verify_tls_description }}
                                    </span>
                                </span>
                                <span
                                    class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition-colors"
                                    :class="
                                        form.verify_tls
                                            ? 'bg-primary'
                                            : 'bg-muted'
                                    "
                                >
                                    <span
                                        class="size-5 rounded-full bg-white shadow-sm transition-transform"
                                        :class="
                                            form.verify_tls
                                                ? 'translate-x-6'
                                                : 'translate-x-1'
                                        "
                                    />
                                </span>
                            </button>
                        </section>

                        <section
                            class="space-y-6 rounded-2xl border border-border bg-card p-5 shadow-xs"
                        >
                            <div class="flex items-start gap-3">
                                <Settings2 class="mt-0.5 size-5 text-primary" />
                                <div>
                                    <h2 class="font-semibold">
                                        {{ t.one_c.exchange_section }}
                                    </h2>
                                    <p
                                        class="mt-1 text-sm leading-5 text-muted-foreground"
                                    >
                                        {{
                                            t.one_c.exchange_section_description
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-3 md:grid-cols-2">
                                <button
                                    v-for="toggle in exchangeToggles"
                                    :key="toggle.key"
                                    type="button"
                                    role="switch"
                                    :aria-checked="form[toggle.key]"
                                    class="flex items-start justify-between gap-4 rounded-xl border border-border p-4 text-left transition-colors hover:bg-muted/30"
                                    @click="toggleFormBoolean(toggle.key)"
                                >
                                    <span
                                        class="flex min-w-0 items-start gap-3"
                                    >
                                        <component
                                            :is="toggle.icon"
                                            class="mt-0.5 size-4 shrink-0 text-muted-foreground"
                                        />
                                        <span>
                                            <span
                                                class="block text-sm font-medium"
                                                >{{ toggle.title }}</span
                                            >
                                            <span
                                                class="mt-1 block text-xs leading-5 text-muted-foreground"
                                            >
                                                {{ toggle.description }}
                                            </span>
                                        </span>
                                    </span>
                                    <span
                                        class="relative mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors"
                                        :class="
                                            form[toggle.key]
                                                ? 'bg-primary'
                                                : 'bg-muted'
                                        "
                                    >
                                        <span
                                            class="size-4 rounded-full bg-white shadow-sm transition-transform"
                                            :class="
                                                form[toggle.key]
                                                    ? 'translate-x-6'
                                                    : 'translate-x-1'
                                            "
                                        />
                                    </span>
                                </button>
                            </div>
                            <InputError :message="form.errors.import_enabled" />

                            <div
                                class="grid gap-4 md:grid-cols-2 lg:grid-cols-3"
                            >
                                <div class="grid gap-2">
                                    <Label for="one-c-sync-mode">{{
                                        t.one_c.default_sync_mode
                                    }}</Label>
                                    <Select v-model="form.default_sync_mode">
                                        <SelectTrigger
                                            id="one-c-sync-mode"
                                            class="w-full"
                                            ><SelectValue
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="incremental">{{
                                                t.one_c.sync_mode_incremental
                                            }}</SelectItem>
                                            <SelectItem value="full">{{
                                                t.one_c.sync_mode_full
                                            }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="form.errors.default_sync_mode"
                                    />
                                </div>

                                <div class="grid gap-2">
                                    <Label for="one-c-batch-size">{{
                                        t.one_c.batch_size
                                    }}</Label>
                                    <Input
                                        id="one-c-batch-size"
                                        v-model="form.batch_size"
                                        type="number"
                                        min="10"
                                        max="1000"
                                    />
                                    <InputError
                                        :message="form.errors.batch_size"
                                    />
                                </div>

                                <div
                                    class="grid gap-2 md:col-span-2 lg:col-span-1"
                                >
                                    <Label for="one-c-conflicts">{{
                                        t.one_c.conflict_strategy
                                    }}</Label>
                                    <Select v-model="form.conflict_strategy">
                                        <SelectTrigger
                                            id="one-c-conflicts"
                                            class="w-full"
                                            ><SelectValue
                                        /></SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="one_c_wins">{{
                                                t.one_c.conflict_one_c_wins
                                            }}</SelectItem>
                                            <SelectItem value="crm_wins">{{
                                                t.one_c.conflict_crm_wins
                                            }}</SelectItem>
                                            <SelectItem value="newest_wins">{{
                                                t.one_c.conflict_newest_wins
                                            }}</SelectItem>
                                            <SelectItem value="skip">{{
                                                t.one_c.conflict_skip
                                            }}</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <InputError
                                        :message="form.errors.conflict_strategy"
                                    />
                                </div>
                            </div>

                            <div
                                v-if="form.schedule_enabled"
                                class="grid gap-4 rounded-xl border border-primary/15 bg-primary/5 p-4 sm:grid-cols-3"
                            >
                                <div class="grid gap-2">
                                    <Label for="one-c-interval">{{
                                        t.one_c.sync_interval
                                    }}</Label>
                                    <Input
                                        id="one-c-interval"
                                        v-model="form.sync_interval_minutes"
                                        type="number"
                                        min="5"
                                        max="1440"
                                    />
                                    <InputError
                                        :message="
                                            form.errors.sync_interval_minutes
                                        "
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="one-c-window-start">{{
                                        t.one_c.sync_window_start
                                    }}</Label>
                                    <Input
                                        id="one-c-window-start"
                                        v-model="form.sync_window_start"
                                        type="time"
                                    />
                                    <InputError
                                        :message="form.errors.sync_window_start"
                                    />
                                </div>
                                <div class="grid gap-2">
                                    <Label for="one-c-window-end">{{
                                        t.one_c.sync_window_end
                                    }}</Label>
                                    <Input
                                        id="one-c-window-end"
                                        v-model="form.sync_window_end"
                                        type="time"
                                    />
                                    <InputError
                                        :message="form.errors.sync_window_end"
                                    />
                                </div>
                            </div>
                        </section>

                        <section
                            class="space-y-6 rounded-2xl border border-border bg-card p-5 shadow-xs"
                        >
                            <div class="flex items-start gap-3">
                                <Boxes class="mt-0.5 size-5 text-primary" />
                                <div>
                                    <h2 class="font-semibold">
                                        {{ t.one_c.entities_section }}
                                    </h2>
                                    <p
                                        class="mt-1 text-sm leading-5 text-muted-foreground"
                                    >
                                        {{
                                            t.one_c.entities_section_description
                                        }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-4 lg:grid-cols-2">
                                <div
                                    v-for="definition in entityDefinitions"
                                    :key="definition.key"
                                    class="space-y-4 rounded-xl border p-4 transition-colors"
                                    :class="
                                        form.entities[definition.key]?.enabled
                                            ? 'border-primary/25 bg-primary/5'
                                            : 'border-border bg-muted/15'
                                    "
                                >
                                    <div
                                        class="flex items-start justify-between gap-4"
                                    >
                                        <div class="min-w-0">
                                            <h3 class="text-sm font-semibold">
                                                {{ definition.label }}
                                            </h3>
                                            <p
                                                class="mt-1 text-xs leading-5 text-muted-foreground"
                                            >
                                                {{ definition.description }}
                                            </p>
                                        </div>
                                        <button
                                            type="button"
                                            role="switch"
                                            :aria-checked="
                                                form.entities[definition.key]
                                                    ?.enabled
                                            "
                                            :aria-label="definition.label"
                                            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors"
                                            :class="
                                                form.entities[definition.key]
                                                    ?.enabled
                                                    ? 'bg-primary'
                                                    : 'bg-muted'
                                            "
                                            @click="
                                                form.entities[
                                                    definition.key
                                                ].enabled =
                                                    !form.entities[
                                                        definition.key
                                                    ].enabled
                                            "
                                        >
                                            <span
                                                class="size-4 rounded-full bg-white shadow-sm transition-transform"
                                                :class="
                                                    form.entities[
                                                        definition.key
                                                    ]?.enabled
                                                        ? 'translate-x-6'
                                                        : 'translate-x-1'
                                                "
                                            />
                                        </button>
                                    </div>

                                    <div
                                        v-if="
                                            form.entities[definition.key]
                                                ?.enabled
                                        "
                                        class="grid gap-3 sm:grid-cols-2"
                                    >
                                        <div class="grid gap-2">
                                            <Label>{{
                                                t.one_c.direction
                                            }}</Label>
                                            <Select
                                                v-model="
                                                    form.entities[
                                                        definition.key
                                                    ].direction
                                                "
                                            >
                                                <SelectTrigger class="w-full"
                                                    ><SelectValue
                                                /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem
                                                        v-for="direction in definition.directions"
                                                        :key="direction"
                                                        :value="direction"
                                                    >
                                                        {{
                                                            directionLabel(
                                                                direction,
                                                            )
                                                        }}
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                :message="
                                                    form.errors[
                                                        `entities.${definition.key}.direction`
                                                    ]
                                                "
                                            />
                                        </div>
                                        <div class="grid gap-2">
                                            <Label>{{
                                                t.one_c.source_of_truth
                                            }}</Label>
                                            <Select
                                                v-model="
                                                    form.entities[
                                                        definition.key
                                                    ].source_of_truth
                                                "
                                            >
                                                <SelectTrigger class="w-full"
                                                    ><SelectValue
                                                /></SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="one_c">{{
                                                        sourceLabel('one_c')
                                                    }}</SelectItem>
                                                    <SelectItem value="crm">{{
                                                        sourceLabel('crm')
                                                    }}</SelectItem>
                                                    <SelectItem
                                                        value="newest"
                                                        >{{
                                                            sourceLabel(
                                                                'newest',
                                                            )
                                                        }}</SelectItem
                                                    >
                                                </SelectContent>
                                            </Select>
                                            <InputError
                                                :message="
                                                    form.errors[
                                                        `entities.${definition.key}.source_of_truth`
                                                    ]
                                                "
                                            />
                                        </div>
                                    </div>
                                    <p
                                        v-else
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ t.one_c.entity_disabled }}
                                    </p>
                                </div>
                            </div>
                            <InputError :message="form.errors.entities" />
                        </section>

                        <div
                            class="flex flex-col-reverse gap-3 sm:flex-row sm:items-center sm:justify-end"
                        >
                            <p
                                v-if="form.isDirty"
                                class="mr-auto text-xs text-amber-600 dark:text-amber-400"
                            >
                                {{ t.one_c.unsaved_changes }}
                            </p>
                            <Button
                                type="button"
                                variant="outline"
                                :disabled="form.isDirty || testingConnection"
                                @click="testConnection"
                            >
                                <Spinner
                                    v-if="testingConnection"
                                    class="size-4"
                                />
                                <RefreshCcw v-else class="size-4" />
                                {{
                                    testingConnection
                                        ? t.one_c.testing_connection
                                        : t.one_c.test_connection
                                }}
                            </Button>
                            <Button
                                type="submit"
                                :disabled="form.processing || !form.isDirty"
                            >
                                <Spinner
                                    v-if="form.processing"
                                    class="size-4"
                                />
                                {{ t.one_c.save_settings }}
                            </Button>
                        </div>
                    </form>

                    <aside class="space-y-4 xl:sticky xl:top-36 xl:self-start">
                        <section
                            class="space-y-5 rounded-2xl border border-border bg-card p-5 shadow-xs"
                        >
                            <div>
                                <h2 class="font-semibold">
                                    {{ t.one_c.overview_title }}
                                </h2>
                                <p
                                    class="mt-1 text-xs leading-5 text-muted-foreground"
                                >
                                    {{ t.one_c.overview_description }}
                                </p>
                            </div>

                            <div class="space-y-3">
                                <div
                                    class="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span class="text-muted-foreground">{{
                                        t.one_c.operational_status
                                    }}</span>
                                    <span
                                        class="font-medium"
                                        :class="
                                            form.is_enabled
                                                ? 'text-emerald-600 dark:text-emerald-400'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{
                                            form.is_enabled
                                                ? t.one_c.active
                                                : t.one_c.inactive
                                        }}
                                    </span>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span class="text-muted-foreground">{{
                                        t.one_c.connection_status
                                    }}</span>
                                    <span
                                        class="flex items-center gap-1.5 font-medium"
                                    >
                                        <CheckCircle2
                                            v-if="
                                                selectedIntegration.last_test_succeeded ===
                                                true
                                            "
                                            class="size-4 text-emerald-500"
                                        />
                                        <XCircle
                                            v-else-if="
                                                selectedIntegration.last_test_succeeded ===
                                                false
                                            "
                                            class="size-4 text-destructive"
                                        />
                                        <Activity
                                            v-else
                                            class="size-4 text-muted-foreground"
                                        />
                                        {{
                                            selectedIntegration.last_test_succeeded ===
                                            true
                                                ? t.one_c.test_passed
                                                : selectedIntegration.last_test_succeeded ===
                                                    false
                                                  ? t.one_c.test_failed
                                                  : t.one_c.not_tested
                                        }}
                                    </span>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span class="text-muted-foreground">{{
                                        t.one_c.last_test
                                    }}</span>
                                    <span
                                        class="text-right text-xs font-medium"
                                    >
                                        {{
                                            formatDateTime(
                                                selectedIntegration.last_tested_at,
                                            )
                                        }}
                                    </span>
                                </div>
                                <div
                                    v-if="
                                        selectedIntegration.last_test_duration_ms !==
                                        null
                                    "
                                    class="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span class="text-muted-foreground">{{
                                        t.one_c.response_time
                                    }}</span>
                                    <span class="font-medium">
                                        {{
                                            selectedIntegration.last_test_duration_ms
                                        }}
                                        ms
                                    </span>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span class="text-muted-foreground">{{
                                        t.one_c.last_sync
                                    }}</span>
                                    <span
                                        class="text-right text-xs font-medium"
                                    >
                                        {{
                                            formatDateTime(
                                                selectedIntegration.last_sync_at,
                                            )
                                        }}
                                    </span>
                                </div>
                            </div>

                            <p
                                v-if="selectedIntegration.last_test_message"
                                class="rounded-lg bg-muted/60 p-3 text-xs leading-5 text-muted-foreground"
                            >
                                {{ selectedIntegration.last_test_message }}
                            </p>

                            <Button
                                type="button"
                                variant="outline"
                                class="w-full"
                                :disabled="form.isDirty || testingConnection"
                                @click="testConnection"
                            >
                                <Spinner
                                    v-if="testingConnection"
                                    class="size-4"
                                />
                                <RefreshCcw v-else class="size-4" />
                                {{ t.one_c.test_connection }}
                            </Button>
                        </section>

                        <section
                            class="space-y-4 rounded-2xl border border-border bg-card p-5 shadow-xs"
                        >
                            <h2 class="font-semibold">
                                {{ t.one_c.readiness_title }}
                            </h2>
                            <ul class="space-y-3">
                                <li
                                    v-for="item in readinessItems"
                                    :key="item.label"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <CheckCircle2
                                        v-if="item.ready"
                                        class="size-4 shrink-0 text-emerald-500"
                                    />
                                    <XCircle
                                        v-else
                                        class="size-4 shrink-0 text-muted-foreground/50"
                                    />
                                    <span
                                        :class="
                                            item.ready
                                                ? 'text-foreground'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{ item.label }}
                                    </span>
                                </li>
                            </ul>
                        </section>

                        <section
                            v-if="
                                selectedIntegration.updated_by ||
                                selectedIntegration.updated_at
                            "
                            class="rounded-2xl border border-border bg-card p-5 text-xs leading-5 text-muted-foreground shadow-xs"
                        >
                            <p class="font-medium text-foreground">
                                {{ t.one_c.updated_by }}
                            </p>
                            <p
                                v-if="selectedIntegration.updated_by"
                                class="mt-1"
                            >
                                {{ selectedIntegration.updated_by.name }} ·
                                {{ selectedIntegration.updated_by.email }}
                            </p>
                            <p>
                                {{
                                    formatDateTime(
                                        selectedIntegration.updated_at,
                                    )
                                }}
                            </p>
                        </section>

                        <Button
                            type="button"
                            variant="ghost"
                            class="w-full text-destructive hover:bg-destructive/10 hover:text-destructive"
                            :disabled="form.is_enabled"
                            @click="deleteDialogOpen = true"
                        >
                            <Trash2 class="size-4" />
                            {{ t.one_c.delete_connection }}
                        </Button>
                    </aside>
                </div>
            </div>
        </div>
    </div>

    <Dialog v-model:open="createDialogOpen">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>{{ t.one_c.create_connection }}</DialogTitle>
                <DialogDescription>{{
                    t.one_c.create_description
                }}</DialogDescription>
            </DialogHeader>

            <form class="space-y-4" @submit.prevent="createConnection">
                <div class="grid gap-2">
                    <Label for="new-one-c-name">{{
                        t.one_c.connection_name
                    }}</Label>
                    <Input
                        id="new-one-c-name"
                        v-model="createForm.name"
                        :placeholder="t.one_c.connection_name_placeholder"
                        autofocus
                    />
                    <InputError :message="createForm.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label>{{ t.one_c.product }}</Label>
                    <Select v-model="createForm.product">
                        <SelectTrigger class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in productOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="createForm.errors.product" />
                </div>

                <div class="grid gap-2">
                    <Label>{{ t.one_c.transport }}</Label>
                    <Select v-model="createForm.transport">
                        <SelectTrigger class="w-full"
                            ><SelectValue
                        /></SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in transportOptions"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="createForm.errors.transport" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="createDialogOpen = false"
                    >
                        {{ t.common.cancel }}
                    </Button>
                    <Button type="submit" :disabled="createForm.processing">
                        <Spinner v-if="createForm.processing" class="size-4" />
                        {{ t.one_c.create_connection }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="deleteDialogOpen">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>{{ t.one_c.delete_title }}</DialogTitle>
                <DialogDescription>{{
                    t.one_c.delete_description
                }}</DialogDescription>
            </DialogHeader>
            <InputError :message="deleteForm.errors.confirmation" />
            <DialogFooter>
                <Button
                    type="button"
                    variant="outline"
                    @click="deleteDialogOpen = false"
                >
                    {{ t.common.cancel }}
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    @click="deleteConnection"
                >
                    <Trash2 class="size-4" />
                    {{ t.one_c.delete_confirm }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

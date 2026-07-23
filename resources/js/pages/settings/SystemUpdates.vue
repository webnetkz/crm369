<script setup lang="ts">
import { Head, setLayoutProps, useForm, usePoll } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    CircleDashed,
    Clock3,
    Database,
    FileCode2,
    GitBranch,
    History,
    LoaderCircle,
    Package,
    RefreshCw,
    Rocket,
    Server,
    ShieldCheck,
    XCircle,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, ref, watch, watchEffect } from 'vue';
import SystemUpdateController from '@/actions/App/Http/Controllers/Settings/SystemUpdateController';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useLanguage } from '@/composables/useLanguage';
import { edit } from '@/routes/settings/system-updates';

type ComponentStatus = 'current' | 'update_available' | 'unknown';
type RunStatus = 'queued' | 'running' | 'completed' | 'failed';

type SystemComponent = {
    key: string;
    currentVersion: string | null;
    latestVersion: string | null;
    currentReference?: string | null;
    latestReference?: string | null;
    releaseUrl?: string | null;
    publishedAt?: string | null;
    status: ComponentStatus;
    updateAvailable: boolean;
    canUpdate: boolean;
    blockedReason?: string | null;
    pendingPackages?: number | null;
};

type UpdateStep = {
    at: string | null;
    progress: number;
    stage: string | null;
    message: string | null;
};

type UpdateRun = {
    id: number;
    uuid: string;
    component: string;
    status: RunStatus;
    currentVersion: string | null;
    targetVersion: string | null;
    progress: number;
    stage: string | null;
    message: string | null;
    startedAt: string | null;
    finishedAt: string | null;
    createdAt: string | null;
    requestedBy: { id: number; name: string } | null;
    steps: UpdateStep[];
};

const props = defineProps<{
    repository: {
        name: string;
        branch: string;
        url: string;
    };
    databaseReady: boolean;
    snapshot: {
        components: SystemComponent[];
        checkedAt: string | null;
        error: string | null;
    };
    latestRun: UpdateRun | null;
    history: UpdateRun[];
}>();

const { language, t } = useLanguage();
const checkForm = useForm({});
const updateForm = useForm({
    component: '',
});
const selectedComponent = ref<SystemComponent | null>(null);
const application = computed(
    () =>
        props.snapshot.components.find(
            (component) => component.key === 'application',
        ) ?? null,
);
const infrastructure = computed(() =>
    props.snapshot.components.filter(
        (component) => component.key !== 'application',
    ),
);
const runIsActive = computed(
    () =>
        props.latestRun?.status === 'queued' ||
        props.latestRun?.status === 'running',
);
const bridgeUnavailable = computed(() =>
    props.snapshot.components.some(
        (component) =>
            component.updateAvailable &&
            !component.canUpdate &&
            component.blockedReason !== 'working_tree_modified',
    ),
);

const { start: startPolling, stop: stopPolling } = usePoll(
    2500,
    {
        only: ['snapshot', 'latestRun', 'history'],
    },
    {
        autoStart: false,
        keepAlive: true,
        mode: 'rest',
    },
);

watch(
    runIsActive,
    (active) => {
        if (active) {
            startPolling();
        } else {
            stopPolling();
        }
    },
    { immediate: true },
);

const componentIcon = (key: string): Component =>
    ({
        application: Rocket,
        laravel: FileCode2,
        php: FileCode2,
        postgresql: Database,
        redis: Server,
        nginx: Server,
        node: Package,
        composer: Package,
        ubuntu: Server,
    })[key] ?? Package;

const statusIcon = (status: ComponentStatus): Component =>
    ({
        current: CheckCircle2,
        update_available: RefreshCw,
        unknown: CircleDashed,
    })[status];

const statusClasses = (status: ComponentStatus): string =>
    ({
        current:
            'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        update_available:
            'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        unknown: 'border-border bg-muted/45 text-muted-foreground',
    })[status];

const runStatusClasses = (status: RunStatus): string =>
    ({
        queued: 'border-border bg-muted/45 text-muted-foreground',
        running:
            'border-blue-500/25 bg-blue-500/10 text-blue-700 dark:text-blue-300',
        completed:
            'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        failed: 'border-destructive/25 bg-destructive/10 text-destructive',
    })[status];

const runStatusIcon = (status: RunStatus): Component =>
    ({
        queued: Clock3,
        running: LoaderCircle,
        completed: CheckCircle2,
        failed: XCircle,
    })[status];

const componentName = (key: string): string =>
    t.value.system_updates.components[key] ?? key;

const formatVersion = (
    component: SystemComponent,
    value: string | null,
    latest = false,
): string => {
    if (component.key === 'ubuntu' && latest && value !== null) {
        return t.value.system_updates.pending_packages.replace(':count', value);
    }

    return value ?? '—';
};

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        language.value === 'ru' ? 'ru-RU' : 'en-US',
        { dateStyle: 'medium', timeStyle: 'short' },
    ).format(new Date(value));
};

const checkUpdates = (): void => {
    checkForm.post(SystemUpdateController.check.url(), {
        preserveScroll: true,
    });
};

const selectComponent = (component: SystemComponent): void => {
    selectedComponent.value = component;
    updateForm.component = component.key;
};

const startUpdate = (): void => {
    if (!selectedComponent.value) {
        return;
    }

    updateForm.post(
        SystemUpdateController.start.url(selectedComponent.value.key),
        {
            preserveScroll: true,
            onSuccess: () => {
                selectedComponent.value = null;
                startPolling();
            },
        },
    );
};

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.system_updates.title,
                href: edit(),
            },
        ],
    });
});
</script>

<template>
    <Head :title="t.system_updates.title" />

    <h1 class="sr-only">{{ t.system_updates.title }}</h1>

    <Dialog
        :open="selectedComponent !== null"
        @update:open="
            (open) => {
                if (!open && !updateForm.processing) selectedComponent = null;
            }
        "
    >
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>
                    {{
                        t.system_updates.confirm_title.replace(
                            ':component',
                            componentName(selectedComponent?.key ?? ''),
                        )
                    }}
                </DialogTitle>
                <DialogDescription>
                    {{
                        t.system_updates.confirm_description
                            .replace(
                                ':component',
                                componentName(selectedComponent?.key ?? ''),
                            )
                            .replace(
                                ':version',
                                selectedComponent?.latestVersion ?? '—',
                            )
                    }}
                </DialogDescription>
            </DialogHeader>

            <Alert>
                <ShieldCheck class="size-4" />
                <AlertDescription>
                    {{ t.system_updates.confirm_warning }}
                </AlertDescription>
            </Alert>

            <p
                v-if="updateForm.errors.component"
                class="text-sm text-destructive"
            >
                {{ updateForm.errors.component }}
            </p>

            <DialogFooter class="gap-2">
                <Button
                    type="button"
                    variant="outline"
                    :disabled="updateForm.processing"
                    @click="selectedComponent = null"
                >
                    {{ t.system_updates.cancel }}
                </Button>
                <Button
                    type="button"
                    :disabled="updateForm.processing"
                    @click="startUpdate"
                >
                    <LoaderCircle
                        v-if="updateForm.processing"
                        class="size-4 animate-spin"
                    />
                    <RefreshCw v-else class="size-4" />
                    {{
                        updateForm.processing
                            ? t.system_updates.starting
                            : t.system_updates.start_update
                    }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <div class="space-y-6 pb-10">
        <Card
            class="relative overflow-hidden border-primary/15 bg-gradient-to-br from-card via-card to-primary/7 shadow-md"
        >
            <div
                class="pointer-events-none absolute -top-28 -right-16 size-72 rounded-full bg-primary/10 blur-3xl"
            />
            <CardContent
                class="relative grid gap-6 p-6 sm:p-8 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-center"
            >
                <div class="max-w-3xl space-y-4">
                    <Badge variant="outline" class="gap-2 bg-background/70">
                        <ShieldCheck class="size-3.5" />
                        {{ t.system_updates.repository }}:
                        {{ repository.name }}
                    </Badge>
                    <div class="space-y-2">
                        <h2
                            class="text-2xl font-semibold tracking-tight sm:text-3xl"
                        >
                            {{ t.system_updates.title }}
                        </h2>
                        <p class="text-sm text-muted-foreground sm:text-base">
                            {{ t.system_updates.description }}
                        </p>
                    </div>
                    <div
                        class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm text-muted-foreground"
                    >
                        <a
                            :href="repository.url"
                            target="_blank"
                            rel="noreferrer"
                            class="inline-flex items-center gap-2 text-foreground hover:underline"
                        >
                            <GitBranch class="size-4" />
                            {{ repository.name }} · {{ repository.branch }}
                        </a>
                        <span>
                            {{
                                snapshot.checkedAt
                                    ? t.system_updates.last_checked.replace(
                                          ':date',
                                          formatDateTime(snapshot.checkedAt),
                                      )
                                    : t.system_updates.never_checked
                            }}
                        </span>
                    </div>
                </div>

                <Button
                    type="button"
                    size="lg"
                    :disabled="
                        !databaseReady || checkForm.processing || runIsActive
                    "
                    @click="checkUpdates"
                >
                    <LoaderCircle
                        v-if="checkForm.processing"
                        class="size-4 animate-spin"
                    />
                    <RefreshCw v-else class="size-4" />
                    {{
                        checkForm.processing
                            ? t.system_updates.checking
                            : t.system_updates.check_updates
                    }}
                </Button>
            </CardContent>
        </Card>

        <Alert v-if="snapshot.error" variant="destructive">
            <AlertTriangle class="size-4" />
            <AlertDescription>{{ snapshot.error }}</AlertDescription>
        </Alert>

        <Alert v-if="bridgeUnavailable">
            <AlertTriangle class="size-4" />
            <AlertTitle>
                {{ t.system_updates.bridge_unavailable_title }}
            </AlertTitle>
            <AlertDescription>
                {{ t.system_updates.bridge_unavailable_description }}
            </AlertDescription>
        </Alert>

        <Alert
            v-if="application?.blockedReason === 'working_tree_modified'"
            variant="destructive"
        >
            <AlertTriangle class="size-4" />
            <AlertDescription>
                {{ t.system_updates.working_tree_modified }}
            </AlertDescription>
        </Alert>

        <Card v-if="application">
            <CardHeader>
                <div
                    class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div class="flex gap-3">
                        <div
                            class="grid size-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary"
                        >
                            <Rocket class="size-5" />
                        </div>
                        <div>
                            <CardTitle>
                                {{ t.system_updates.application_title }}
                            </CardTitle>
                            <CardDescription>
                                {{ t.system_updates.application_description }}
                            </CardDescription>
                        </div>
                    </div>
                    <Badge
                        variant="outline"
                        class="gap-1.5 self-start"
                        :class="statusClasses(application.status)"
                    >
                        <component
                            :is="statusIcon(application.status)"
                            class="size-3.5"
                        />
                        {{
                            application.status === 'current'
                                ? t.system_updates.up_to_date
                                : application.status === 'update_available'
                                  ? t.system_updates.update_available
                                  : t.system_updates.unknown
                        }}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent
                class="grid gap-4 md:grid-cols-[1fr_auto_1fr_auto] md:items-end"
            >
                <div class="rounded-xl border bg-muted/20 p-4">
                    <p class="text-xs font-medium text-muted-foreground">
                        {{ t.system_updates.current_version }}
                    </p>
                    <p class="mt-1 font-mono text-sm font-semibold">
                        {{
                            formatVersion(
                                application,
                                application.currentVersion,
                            )
                        }}
                    </p>
                    <p
                        v-if="application.currentReference"
                        class="mt-1 truncate font-mono text-xs text-muted-foreground"
                    >
                        {{ application.currentReference }}
                    </p>
                </div>
                <RefreshCw
                    class="hidden size-5 text-muted-foreground md:block"
                />
                <div class="rounded-xl border bg-muted/20 p-4">
                    <p class="text-xs font-medium text-muted-foreground">
                        {{ t.system_updates.latest_version }}
                    </p>
                    <a
                        v-if="application.releaseUrl"
                        :href="application.releaseUrl"
                        target="_blank"
                        rel="noreferrer"
                        class="mt-1 block font-mono text-sm font-semibold hover:underline"
                    >
                        {{
                            formatVersion(
                                application,
                                application.latestVersion,
                                true,
                            )
                        }}
                    </a>
                    <p v-else class="mt-1 font-mono text-sm font-semibold">
                        {{
                            formatVersion(
                                application,
                                application.latestVersion,
                                true,
                            )
                        }}
                    </p>
                    <p
                        v-if="application.latestReference"
                        class="mt-1 truncate font-mono text-xs text-muted-foreground"
                    >
                        {{ application.latestReference }}
                    </p>
                </div>
                <Button
                    type="button"
                    :disabled="
                        !databaseReady ||
                        !application.canUpdate ||
                        runIsActive ||
                        updateForm.processing
                    "
                    @click="selectComponent(application)"
                >
                    <RefreshCw class="size-4" />
                    {{ t.system_updates.update }}
                </Button>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle>{{ t.system_updates.components_title }}</CardTitle>
                <CardDescription>
                    {{ t.system_updates.components_description }}
                </CardDescription>
            </CardHeader>
            <CardContent class="grid gap-3">
                <article
                    v-for="item in infrastructure"
                    :key="item.key"
                    class="grid gap-4 rounded-xl border p-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,0.8fr)_minmax(0,0.8fr)_auto] lg:items-center"
                >
                    <div class="flex min-w-0 gap-3">
                        <div
                            class="grid size-10 shrink-0 place-items-center rounded-xl bg-muted text-muted-foreground"
                        >
                            <component
                                :is="componentIcon(item.key)"
                                class="size-5"
                            />
                        </div>
                        <div class="min-w-0">
                            <p class="font-medium">
                                {{ componentName(item.key) }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{
                                    t.system_updates.component_descriptions[
                                        item.key
                                    ]
                                }}
                            </p>
                        </div>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">
                            {{ t.system_updates.current_version }}
                        </p>
                        <p class="mt-1 font-mono text-sm break-all">
                            {{ formatVersion(item, item.currentVersion) }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">
                            {{ t.system_updates.latest_version }}
                        </p>
                        <p class="mt-1 font-mono text-sm break-all">
                            {{ formatVersion(item, item.latestVersion, true) }}
                        </p>
                    </div>
                    <div
                        class="flex items-center justify-between gap-3 lg:justify-end"
                    >
                        <Badge
                            variant="outline"
                            class="gap-1.5"
                            :class="statusClasses(item.status)"
                        >
                            <component
                                :is="statusIcon(item.status)"
                                class="size-3.5"
                            />
                            {{
                                item.status === 'current'
                                    ? t.system_updates.up_to_date
                                    : item.status === 'update_available'
                                      ? t.system_updates.update_available
                                      : t.system_updates.unknown
                            }}
                        </Badge>
                        <Button
                            type="button"
                            size="sm"
                            :disabled="
                                !databaseReady ||
                                !item.canUpdate ||
                                runIsActive ||
                                updateForm.processing
                            "
                            @click="selectComponent(item)"
                        >
                            {{ t.system_updates.update }}
                        </Button>
                    </div>
                </article>
            </CardContent>
        </Card>

        <Card v-if="latestRun">
            <CardHeader>
                <div
                    class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                >
                    <div>
                        <CardTitle>{{
                            t.system_updates.progress_title
                        }}</CardTitle>
                        <CardDescription>
                            {{ t.system_updates.progress_description }}
                        </CardDescription>
                    </div>
                    <Badge
                        variant="outline"
                        class="gap-1.5 self-start"
                        :class="runStatusClasses(latestRun.status)"
                    >
                        <component
                            :is="runStatusIcon(latestRun.status)"
                            class="size-3.5"
                            :class="{
                                'animate-spin': latestRun.status === 'running',
                            }"
                        />
                        {{ t.system_updates.statuses[latestRun.status] }}
                    </Badge>
                </div>
            </CardHeader>
            <CardContent class="space-y-5">
                <div class="space-y-2">
                    <div
                        class="flex items-center justify-between gap-3 text-sm"
                    >
                        <span class="font-medium">
                            {{
                                t.system_updates.stages[
                                    latestRun.stage ?? 'queued'
                                ] ?? latestRun.stage
                            }}
                        </span>
                        <span class="font-mono tabular-nums">
                            {{ latestRun.progress }}%
                        </span>
                    </div>
                    <div
                        class="h-2.5 overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        :aria-valuenow="latestRun.progress"
                        aria-valuemin="0"
                        aria-valuemax="100"
                    >
                        <div
                            class="h-full rounded-full bg-primary transition-[width] duration-500"
                            :class="{
                                'bg-destructive': latestRun.status === 'failed',
                            }"
                            :style="{ width: `${latestRun.progress}%` }"
                        />
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ latestRun.message }}
                    </p>
                </div>

                <div
                    v-if="latestRun.steps.length"
                    class="max-h-72 space-y-2 overflow-y-auto rounded-xl border bg-muted/15 p-3"
                >
                    <div
                        v-for="(step, index) in latestRun.steps"
                        :key="`${step.at}-${index}`"
                        class="grid grid-cols-[auto_1fr_auto] gap-3 text-sm"
                    >
                        <CheckCircle2
                            class="mt-0.5 size-4 text-muted-foreground"
                        />
                        <div>
                            <p class="font-medium">
                                {{
                                    t.system_updates.stages[
                                        step.stage ?? 'starting'
                                    ] ?? step.stage
                                }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ step.message }}
                            </p>
                        </div>
                        <span class="font-mono text-xs text-muted-foreground">
                            {{ step.progress }}%
                        </span>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2">
                    <History class="size-5" />
                    {{ t.system_updates.history_title }}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div v-if="history.length" class="space-y-2">
                    <article
                        v-for="run in history"
                        :key="run.id"
                        class="grid gap-3 rounded-xl border px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center"
                    >
                        <div>
                            <p class="text-sm font-medium">
                                {{ componentName(run.component) }}
                                <span class="text-muted-foreground">
                                    {{ run.currentVersion ?? '—' }} →
                                    {{ run.targetVersion ?? '—' }}
                                </span>
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ formatDateTime(run.createdAt) }} ·
                                {{ run.requestedBy?.name ?? '—' }}
                            </p>
                        </div>
                        <Badge
                            variant="outline"
                            :class="runStatusClasses(run.status)"
                        >
                            {{ t.system_updates.statuses[run.status] }}
                        </Badge>
                        <span class="font-mono text-sm tabular-nums">
                            {{ run.progress }}%
                        </span>
                    </article>
                </div>
                <div
                    v-else
                    class="flex items-center gap-3 rounded-xl border border-dashed p-6 text-sm text-muted-foreground"
                >
                    <History class="size-5" />
                    {{ t.system_updates.history_empty }}
                </div>
            </CardContent>
        </Card>
    </div>
</template>

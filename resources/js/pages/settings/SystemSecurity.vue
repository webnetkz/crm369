<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    AlertTriangle,
    CheckCircle2,
    ChevronRight,
    CircleDashed,
    Clock3,
    History,
    LoaderCircle,
    LockKeyhole,
    MinusCircle,
    RefreshCw,
    ScanLine,
    ShieldAlert,
    ShieldCheck,
    Sparkles,
    XCircle,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, onUnmounted, ref, watchEffect } from 'vue';
import SystemSecurityController from '@/actions/App/Http/Controllers/Settings/SystemSecurityController';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import { edit } from '@/routes/settings/system-security';

type AuditStatus = 'passed' | 'warning' | 'failed' | 'skipped';
type RiskLevel = 'protected' | 'attention' | 'high_risk';
type ManualKey =
    | 'backups_verified'
    | 'infrastructure_patched'
    | 'privileged_access_reviewed'
    | 'security_headers_configured'
    | 'incident_plan_ready'
    | 'secrets_rotated';

type NamedUser = {
    id: number;
    name: string;
};

type Policy = {
    enabled: boolean;
    featureAvailable: boolean;
    activeUsers: number;
    protectedUsers: number;
    pendingUsers: number;
    coveragePercent: number;
    enforcedAt: string | null;
    updatedBy: NamedUser | null;
};

type AuditCheck = {
    key: string;
    category: string;
    status: AuditStatus;
    severity: 'critical' | 'important' | 'recommended';
    meta: Record<string, number | boolean>;
};

type AuditSummary = {
    id: number;
    score: number;
    riskLevel: RiskLevel;
    passedCount: number;
    warningCount: number;
    failedCount: number;
    skippedCount: number;
    totalCount: number;
    durationMs: number;
    checkedAt: string;
    performedBy: NamedUser | null;
};

type AuditRecord = AuditSummary & {
    checks: AuditCheck[];
};

const props = defineProps<{
    policy: Policy;
    audit: {
        latest: AuditRecord | null;
        history: AuditSummary[];
        manualDefaults: Record<ManualKey, boolean>;
    };
}>();

const { language, t } = useLanguage();
const scanStage = ref(0);
let scanTimer: ReturnType<typeof setInterval> | null = null;

const manualKeys: ManualKey[] = [
    'backups_verified',
    'infrastructure_patched',
    'privileged_access_reviewed',
    'security_headers_configured',
    'incident_plan_ready',
    'secrets_rotated',
];

const automaticCheckKeys = [
    'production_environment',
    'debug_mode',
    'app_key',
    'https_url',
    'session_secure',
    'session_hardening',
    'session_encryption',
    'email_verification',
    'auth_rate_limits',
    'global_two_factor_policy',
    'two_factor_coverage',
    'super_admin_security',
    'api_tokens',
    'webhook_tokens',
    'stale_sessions',
    'failed_jobs',
    'pending_migrations',
    'storage_permissions',
    'integration_tls',
    'integration_secrets',
    'composer_dependencies',
    'npm_dependencies',
];

const auditForm = useForm({
    manual: { ...props.audit.manualDefaults },
});
const policyForm = useForm({
    enabled: props.policy.enabled,
});

const latest = computed(() => props.audit.latest);
const auditError = computed(
    () =>
        (auditForm.errors as Record<string, string | undefined>).audit ?? null,
);
const automaticChecks = computed<AuditCheck[]>(() => {
    if (!latest.value) {
        return automaticCheckKeys.map((key) => ({
            key,
            category: 'runtime',
            status: 'skipped',
            severity: 'recommended',
            meta: {},
        }));
    }

    return latest.value.checks.filter((check) => check.meta.manual !== true);
});
const findings = computed<AuditCheck[]>(() =>
    latest.value
        ? latest.value.checks.filter(
              (check) =>
                  check.status === 'failed' || check.status === 'warning',
          )
        : [],
);
const confirmedManualCount = computed(
    () => manualKeys.filter((key) => auditForm.manual[key]).length,
);
const scoreColor = computed(() => {
    if (!latest.value) {
        return 'var(--muted-foreground)';
    }

    return {
        protected: 'oklch(0.7 0.17 160)',
        attention: 'oklch(0.77 0.17 75)',
        high_risk: 'oklch(0.64 0.22 25)',
    }[latest.value.riskLevel];
});
const scoreRingStyle = computed(() => ({
    background: `conic-gradient(${scoreColor.value} ${(latest.value?.score ?? 0) * 3.6}deg, var(--muted) 0deg)`,
}));
const scanStageLabel = computed(() => {
    const stages = [
        t.value.system_security.scan_runtime,
        t.value.system_security.scan_access,
        t.value.system_security.scan_integrations,
        t.value.system_security.scan_score,
    ];

    return stages[Math.min(scanStage.value, stages.length - 1)];
});

const statusIcon = (status: AuditStatus): Component =>
    ({
        passed: CheckCircle2,
        warning: AlertTriangle,
        failed: XCircle,
        skipped: MinusCircle,
    })[status];

const statusClasses = (status: AuditStatus): string =>
    ({
        passed: 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        warning:
            'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        failed: 'border-destructive/25 bg-destructive/10 text-destructive dark:text-red-300',
        skipped: 'border-border bg-muted/45 text-muted-foreground',
    })[status];

const riskClasses = (riskLevel: RiskLevel): string =>
    ({
        protected:
            'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        attention:
            'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        high_risk:
            'border-destructive/25 bg-destructive/10 text-destructive dark:text-red-300',
    })[riskLevel];

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        language.value === 'ru' ? 'ru-RU' : 'en-US',
        { dateStyle: 'medium', timeStyle: 'short' },
    ).format(new Date(value));
};

const formatMetaValue = (value: number | boolean): string => {
    if (typeof value === 'boolean') {
        return value ? t.value.system_security.yes : t.value.system_security.no;
    }

    return new Intl.NumberFormat(
        language.value === 'ru' ? 'ru-RU' : 'en-US',
    ).format(value);
};

const metaEntries = (
    meta: Record<string, number | boolean>,
): [string, number | boolean][] =>
    Object.entries(meta).filter(([key]) => key !== 'manual');

const checkCopy = (key: string) =>
    t.value.system_security.checks[key] ?? {
        title: key,
        description: '',
        recommendation: '',
    };

const updateManualAnswer = (key: ManualKey, value: unknown): void => {
    auditForm.manual[key] = value === true;
};

const stopScanning = (): void => {
    if (scanTimer) {
        clearInterval(scanTimer);
        scanTimer = null;
    }
};

const runAudit = (): void => {
    auditForm.post(SystemSecurityController.storeAudit.url(), {
        preserveScroll: true,
        onStart: () => {
            scanStage.value = 0;
            stopScanning();
            scanTimer = setInterval(() => {
                scanStage.value = Math.min(scanStage.value + 1, 3);
            }, 1400);
        },
        onFinish: stopScanning,
    });
};

const toggleTwoFactorRequirement = (): void => {
    policyForm.enabled = !props.policy.enabled;
    policyForm.patch(
        SystemSecurityController.updateTwoFactorRequirement.url(),
        {
            preserveScroll: true,
            onError: () => {
                policyForm.enabled = props.policy.enabled;
            },
        },
    );
};

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.system_security.title,
                href: edit(),
            },
        ],
    });
});

onUnmounted(stopScanning);
</script>

<template>
    <Head :title="t.system_security.title" />

    <h1 class="sr-only">{{ t.system_security.title }}</h1>

    <div class="space-y-6 pb-10">
        <Card
            class="relative overflow-hidden border-primary/15 bg-gradient-to-br from-card via-card to-primary/7 shadow-md"
        >
            <div
                class="pointer-events-none absolute -top-32 -right-20 size-80 rounded-full bg-primary/10 blur-3xl"
            />
            <CardContent
                class="relative grid gap-7 p-6 sm:p-8 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-center"
            >
                <div class="max-w-3xl space-y-4">
                    <Badge variant="outline" class="gap-2 bg-background/70">
                        <LockKeyhole class="size-3.5" />
                        {{ t.system_security.hero_badge }}
                    </Badge>
                    <div class="space-y-2">
                        <h2
                            class="text-2xl font-semibold tracking-tight sm:text-3xl"
                        >
                            {{ t.system_security.title }}
                        </h2>
                        <p
                            class="text-sm leading-6 text-muted-foreground sm:text-base"
                        >
                            {{ t.system_security.subtitle }}
                        </p>
                    </div>
                    <div
                        v-if="latest"
                        class="flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-muted-foreground"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            <Clock3 class="size-3.5" />
                            {{ t.system_security.last_check }}:
                            {{ formatDateTime(latest.checkedAt) }}
                        </span>
                        <span v-if="latest.performedBy">
                            {{ t.system_security.performed_by }}:
                            {{ latest.performedBy.name }}
                        </span>
                        <span>
                            {{ t.system_security.duration }}:
                            {{ latest.durationMs }}
                            {{ t.system_security.milliseconds }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-col items-center gap-4 sm:flex-row">
                    <div
                        class="relative grid size-36 shrink-0 place-items-center rounded-full p-2"
                        :style="scoreRingStyle"
                    >
                        <div
                            class="grid size-full place-items-center rounded-full bg-card text-center shadow-inner"
                        >
                            <div>
                                <div class="text-4xl font-bold tabular-nums">
                                    {{ latest?.score ?? '—' }}
                                </div>
                                <div
                                    class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                                >
                                    {{ t.system_security.score }} / 100
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="w-full min-w-56 space-y-3">
                        <Badge
                            v-if="latest"
                            variant="outline"
                            :class="riskClasses(latest.riskLevel)"
                        >
                            {{ t.system_security[latest.riskLevel] }}
                        </Badge>
                        <p v-else class="text-sm text-muted-foreground">
                            {{ t.system_security.not_checked }}
                        </p>
                        <Button
                            size="lg"
                            class="w-full"
                            :disabled="auditForm.processing"
                            data-test="run-system-security-audit"
                            @click="runAudit"
                        >
                            <LoaderCircle
                                v-if="auditForm.processing"
                                class="size-4 animate-spin"
                            />
                            <ScanLine v-else-if="!latest" class="size-4" />
                            <RefreshCw v-else class="size-4" />
                            {{
                                auditForm.processing
                                    ? t.system_security.audit_running
                                    : latest
                                      ? t.system_security.run_again
                                      : t.system_security.run_audit
                            }}
                        </Button>
                    </div>
                </div>
            </CardContent>
        </Card>

        <Alert
            v-if="auditForm.processing"
            class="border-primary/25 bg-primary/5"
        >
            <LoaderCircle class="size-4 animate-spin text-primary" />
            <AlertDescription class="flex items-center justify-between gap-4">
                <span>{{ scanStageLabel }}</span>
                <span class="font-mono text-xs">{{ scanStage + 1 }}/4</span>
            </AlertDescription>
        </Alert>
        <Alert v-else-if="auditError" variant="destructive">
            <AlertTriangle class="size-4" />
            <AlertDescription>{{ auditError }}</AlertDescription>
        </Alert>

        <Card class="overflow-hidden border-primary/20">
            <CardContent
                class="grid gap-6 p-0 lg:grid-cols-[minmax(0,1fr)_340px]"
            >
                <div class="space-y-5 p-6 sm:p-7">
                    <div class="flex items-start gap-4">
                        <div
                            class="grid size-11 shrink-0 place-items-center rounded-2xl bg-primary/10 text-primary"
                        >
                            <ShieldCheck v-if="policy.enabled" class="size-5" />
                            <ShieldAlert v-else class="size-5" />
                        </div>
                        <div class="space-y-1.5">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="font-semibold">
                                    {{
                                        t.system_security
                                            .two_factor_policy_title
                                    }}
                                </h2>
                                <Badge
                                    variant="outline"
                                    :class="
                                        policy.enabled
                                            ? 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                            : 'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-300'
                                    "
                                >
                                    {{
                                        policy.enabled
                                            ? t.system_security
                                                  .two_factor_enabled
                                            : t.system_security
                                                  .two_factor_disabled
                                    }}
                                </Badge>
                            </div>
                            <p
                                class="max-w-3xl text-sm leading-6 text-muted-foreground"
                            >
                                {{
                                    t.system_security
                                        .two_factor_policy_description
                                }}
                            </p>
                        </div>
                    </div>

                    <Alert
                        v-if="policyForm.errors.enabled"
                        variant="destructive"
                    >
                        <AlertTriangle class="size-4" />
                        <AlertDescription>
                            {{ policyForm.errors.enabled }}
                        </AlertDescription>
                    </Alert>
                    <Alert
                        v-else-if="!policy.featureAvailable"
                        variant="destructive"
                    >
                        <AlertTriangle class="size-4" />
                        <AlertDescription>
                            {{ t.system_security.feature_unavailable }}
                        </AlertDescription>
                    </Alert>

                    <Button
                        :variant="policy.enabled ? 'outline' : 'default'"
                        :disabled="
                            policyForm.processing ||
                            (!policy.featureAvailable && !policy.enabled)
                        "
                        data-test="toggle-mandatory-two-factor"
                        @click="toggleTwoFactorRequirement"
                    >
                        <LoaderCircle
                            v-if="policyForm.processing"
                            class="size-4 animate-spin"
                        />
                        <ShieldCheck v-else class="size-4" />
                        {{
                            policy.enabled
                                ? t.system_security
                                      .disable_two_factor_requirement
                                : t.system_security
                                      .enable_two_factor_requirement
                        }}
                    </Button>
                </div>

                <div
                    class="space-y-5 border-t bg-muted/25 p-6 lg:border-t-0 lg:border-l"
                >
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-sm font-medium">
                            {{ t.system_security.coverage }}
                        </span>
                        <span class="text-2xl font-bold tabular-nums">
                            {{ policy.coveragePercent }}%
                        </span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full bg-emerald-500 transition-[width] duration-500"
                            :style="{ width: `${policy.coveragePercent}%` }"
                        />
                    </div>
                    <dl class="grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-xl border bg-background/70 p-3">
                            <dt class="text-[11px] text-muted-foreground">
                                {{ t.system_security.active_users }}
                            </dt>
                            <dd class="mt-1 text-lg font-semibold tabular-nums">
                                {{ policy.activeUsers }}
                            </dd>
                        </div>
                        <div class="rounded-xl border bg-background/70 p-3">
                            <dt class="text-[11px] text-muted-foreground">
                                {{ t.system_security.protected_users }}
                            </dt>
                            <dd
                                class="mt-1 text-lg font-semibold text-emerald-600 tabular-nums dark:text-emerald-300"
                            >
                                {{ policy.protectedUsers }}
                            </dd>
                        </div>
                        <div class="rounded-xl border bg-background/70 p-3">
                            <dt class="text-[11px] text-muted-foreground">
                                {{ t.system_security.pending_users }}
                            </dt>
                            <dd
                                class="mt-1 text-lg font-semibold text-amber-600 tabular-nums dark:text-amber-300"
                            >
                                {{ policy.pendingUsers }}
                            </dd>
                        </div>
                    </dl>
                    <p
                        v-if="policy.updatedBy"
                        class="text-xs text-muted-foreground"
                    >
                        {{ t.system_security.policy_updated_by }}:
                        {{ policy.updatedBy.name }} ·
                        {{ formatDateTime(policy.enforcedAt) }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
            <Card>
                <CardHeader>
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1.5">
                            <CardTitle class="flex items-center gap-2 text-lg">
                                <ScanLine class="size-5 text-primary" />
                                {{ t.system_security.automatic_checks }}
                            </CardTitle>
                            <CardDescription>
                                {{
                                    t.system_security
                                        .automatic_checks_description
                                }}
                            </CardDescription>
                        </div>
                        <Badge variant="secondary">
                            {{ automaticChecks.length }}
                        </Badge>
                    </div>
                </CardHeader>
                <CardContent class="space-y-3">
                    <article
                        v-for="check in automaticChecks"
                        :key="check.key"
                        class="rounded-2xl border border-border/70 bg-muted/15 p-4 transition-colors hover:bg-muted/25"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="mt-0.5 grid size-9 shrink-0 place-items-center rounded-xl border"
                                :class="statusClasses(check.status)"
                            >
                                <component
                                    :is="statusIcon(check.status)"
                                    class="size-4"
                                />
                            </div>
                            <div class="min-w-0 flex-1 space-y-2">
                                <div
                                    class="flex flex-wrap items-start justify-between gap-2"
                                >
                                    <div>
                                        <h3 class="text-sm font-medium">
                                            {{ checkCopy(check.key).title }}
                                        </h3>
                                        <p
                                            class="mt-1 text-xs leading-5 text-muted-foreground"
                                        >
                                            {{
                                                checkCopy(check.key).description
                                            }}
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5">
                                        <Badge
                                            variant="outline"
                                            class="text-[10px]"
                                        >
                                            {{
                                                t.system_security.categories[
                                                    check.category
                                                ] ?? check.category
                                            }}
                                        </Badge>
                                        <Badge
                                            variant="outline"
                                            class="text-[10px]"
                                            :class="statusClasses(check.status)"
                                        >
                                            {{
                                                t.system_security[check.status]
                                            }}
                                        </Badge>
                                    </div>
                                </div>
                                <div
                                    v-if="metaEntries(check.meta).length"
                                    class="flex flex-wrap gap-1.5"
                                >
                                    <span
                                        v-for="[key, value] in metaEntries(
                                            check.meta,
                                        )"
                                        :key="key"
                                        class="rounded-md bg-background px-2 py-1 text-[10px] text-muted-foreground ring-1 ring-border"
                                    >
                                        {{
                                            t.system_security.meta_labels[
                                                key
                                            ] ?? key
                                        }}:
                                        <strong class="text-foreground">
                                            {{ formatMetaValue(value) }}
                                        </strong>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </article>
                </CardContent>
            </Card>

            <Card class="h-fit xl:sticky xl:top-6">
                <CardHeader>
                    <CardTitle class="flex items-center gap-2 text-lg">
                        <CircleDashed class="size-5 text-primary" />
                        {{ t.system_security.manual_checklist }}
                    </CardTitle>
                    <CardDescription>
                        {{ t.system_security.manual_checklist_description }}
                    </CardDescription>
                </CardHeader>
                <CardContent class="space-y-4">
                    <div
                        class="flex items-center justify-between rounded-xl bg-muted/45 px-3 py-2 text-xs"
                    >
                        <span>{{ t.system_security.confirmed }}</span>
                        <strong>
                            {{ confirmedManualCount }}/{{ manualKeys.length }}
                        </strong>
                    </div>
                    <div class="space-y-3">
                        <Label
                            v-for="key in manualKeys"
                            :key="key"
                            :for="`system-audit-${key}`"
                            class="flex cursor-pointer items-start gap-3 rounded-xl border p-3 transition-colors hover:bg-muted/35"
                        >
                            <Checkbox
                                :id="`system-audit-${key}`"
                                :model-value="auditForm.manual[key]"
                                class="mt-0.5"
                                @update:model-value="
                                    updateManualAnswer(key, $event)
                                "
                            />
                            <span class="space-y-1">
                                <span class="block text-sm font-medium">
                                    {{ checkCopy(key).title }}
                                </span>
                                <span
                                    class="block text-xs leading-5 font-normal text-muted-foreground"
                                >
                                    {{ checkCopy(key).description }}
                                </span>
                            </span>
                        </Label>
                    </div>
                    <Button
                        class="w-full"
                        size="lg"
                        :disabled="auditForm.processing"
                        @click="runAudit"
                    >
                        <LoaderCircle
                            v-if="auditForm.processing"
                            class="size-4 animate-spin"
                        />
                        <ScanLine v-else class="size-4" />
                        {{ t.system_security.run_audit }}
                    </Button>
                </CardContent>
            </Card>
        </div>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-lg">
                    <Sparkles class="size-5 text-amber-500" />
                    {{ t.system_security.findings }}
                </CardTitle>
                <CardDescription>
                    {{ t.system_security.findings_description }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="findings.length" class="grid gap-3 lg:grid-cols-2">
                    <article
                        v-for="finding in findings"
                        :key="finding.key"
                        class="rounded-2xl border p-4"
                        :class="statusClasses(finding.status)"
                    >
                        <div class="flex items-start gap-3">
                            <component
                                :is="statusIcon(finding.status)"
                                class="mt-0.5 size-5 shrink-0"
                            />
                            <div class="space-y-2">
                                <div
                                    class="flex flex-wrap items-center gap-2 text-foreground"
                                >
                                    <h3 class="text-sm font-semibold">
                                        {{ checkCopy(finding.key).title }}
                                    </h3>
                                    <Badge
                                        variant="outline"
                                        class="text-[10px]"
                                    >
                                        {{
                                            t.system_security.severities[
                                                finding.severity
                                            ]
                                        }}
                                    </Badge>
                                </div>
                                <p class="text-xs leading-5 text-current/85">
                                    <strong>
                                        {{ t.system_security.recommendation }}:
                                    </strong>
                                    {{ checkCopy(finding.key).recommendation }}
                                </p>
                            </div>
                        </div>
                    </article>
                </div>
                <div
                    v-else
                    class="flex items-center gap-3 rounded-2xl border border-emerald-500/25 bg-emerald-500/10 p-5 text-sm text-emerald-700 dark:text-emerald-300"
                >
                    <CheckCircle2 class="size-5 shrink-0" />
                    {{ t.system_security.no_findings }}
                </div>
            </CardContent>
        </Card>

        <Card>
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-lg">
                    <History class="size-5 text-primary" />
                    {{ t.system_security.history }}
                </CardTitle>
                <CardDescription>
                    {{ t.system_security.history_description }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div v-if="audit.history.length" class="space-y-2">
                    <article
                        v-for="record in audit.history"
                        :key="record.id"
                        class="grid gap-3 rounded-xl border px-4 py-3 sm:grid-cols-[auto_minmax(0,1fr)_auto_auto] sm:items-center"
                    >
                        <div
                            class="grid size-10 place-items-center rounded-xl border font-semibold tabular-nums"
                            :class="riskClasses(record.riskLevel)"
                        >
                            {{ record.score }}
                        </div>
                        <div>
                            <p class="text-sm font-medium">
                                {{ formatDateTime(record.checkedAt) }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ record.performedBy?.name ?? '—' }} ·
                                {{ record.durationMs }}
                                {{ t.system_security.milliseconds }}
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs">
                            <span
                                class="text-emerald-600 dark:text-emerald-300"
                            >
                                {{ record.passedCount }}
                                {{ t.system_security.passed.toLowerCase() }}
                            </span>
                            <span class="text-amber-600 dark:text-amber-300">
                                {{ record.warningCount }}
                                {{ t.system_security.warning.toLowerCase() }}
                            </span>
                            <span class="text-destructive">
                                {{ record.failedCount }}
                                {{ t.system_security.failed.toLowerCase() }}
                            </span>
                        </div>
                        <ChevronRight
                            class="hidden size-4 text-muted-foreground sm:block"
                        />
                    </article>
                </div>
                <div
                    v-else
                    class="flex items-center gap-3 rounded-xl border border-dashed p-6 text-sm text-muted-foreground"
                >
                    <History class="size-5" />
                    {{ t.system_security.no_history }}
                </div>
            </CardContent>
        </Card>
    </div>
</template>

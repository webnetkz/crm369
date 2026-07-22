<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    CheckCircle2,
    CircleDashed,
    Fingerprint,
    History,
    KeyRound,
    LoaderCircle,
    LockKeyhole,
    MailCheck,
    MinusCircle,
    MonitorSmartphone,
    RefreshCw,
    ScanLine,
    ShieldCheck,
    Sparkles,
    XCircle,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed, onUnmounted, ref } from 'vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import { Alert, AlertDescription } from '@/components/ui/alert';
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

type AuditStatus = 'passed' | 'warning' | 'failed' | 'skipped';
type RiskLevel = 'protected' | 'attention' | 'high_risk';

type ManualKey =
    | 'unique_password'
    | 'recovery_codes_stored'
    | 'sessions_reviewed'
    | 'device_protected'
    | 'phishing_ready';

type AuditCheck = {
    key: string;
    category: string;
    status: AuditStatus;
    severity: string;
    weight: number;
    earned: number;
    meta: Record<string, number | boolean | string>;
};

type AuditSummary = {
    id: number;
    score: number;
    risk_level: RiskLevel;
    passed_count: number;
    warning_count: number;
    failed_count: number;
    skipped_count: number;
    total_count: number;
    checked_at: string;
    checked_at_diff: string;
};

type AuditRecord = AuditSummary & {
    checks: AuditCheck[];
};

export type SecurityAuditData = {
    latest: AuditRecord | null;
    history: AuditSummary[];
    manualDefaults: Record<ManualKey, boolean>;
};

const props = defineProps<{
    audit: SecurityAuditData;
}>();

const { language, t } = useLanguage();
const scanStage = ref(0);
let scanTimer: ReturnType<typeof setInterval> | null = null;

const manualKeys: ManualKey[] = [
    'unique_password',
    'recovery_codes_stored',
    'sessions_reviewed',
    'device_protected',
    'phishing_ready',
];

const automaticCheckKeys = [
    'email_verified',
    'two_factor_enabled',
    'passkey_registered',
    'recovery_codes_available',
    'active_sessions',
    'recent_login_alerts',
    'api_tokens',
];

const auditForm = useForm({
    manual: { ...props.audit.manualDefaults },
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
            category: 'pending',
            status: 'skipped',
            severity: 'recommended',
            weight: 0,
            earned: 0,
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
const manualConfirmedCount = computed(
    () => manualKeys.filter((key) => auditForm.manual[key]).length,
);
const scoreColor = computed(() => {
    if (!latest.value) {
        return 'var(--muted-foreground)';
    }

    if (latest.value.risk_level === 'protected') {
        return 'oklch(0.7 0.17 160)';
    }

    if (latest.value.risk_level === 'attention') {
        return 'oklch(0.77 0.17 75)';
    }

    return 'oklch(0.64 0.22 25)';
});
const scoreRingStyle = computed(() => ({
    background: `conic-gradient(${scoreColor.value} ${(latest.value?.score ?? 0) * 3.6}deg, var(--muted) 0deg)`,
}));
const scanStageLabel = computed(() => {
    const stages = [
        t.value.security.audit_scan_identity,
        t.value.security.audit_scan_access,
        t.value.security.audit_scan_activity,
        t.value.security.audit_scan_score,
    ];

    return stages[Math.min(scanStage.value, stages.length - 1)];
});

const checkIcons: Record<string, Component> = {
    email_verified: MailCheck,
    two_factor_enabled: ShieldCheck,
    passkey_registered: Fingerprint,
    recovery_codes_available: KeyRound,
    active_sessions: MonitorSmartphone,
    recent_login_alerts: Activity,
    api_tokens: LockKeyhole,
    unique_password: KeyRound,
    recovery_codes_stored: ShieldCheck,
    sessions_reviewed: MonitorSmartphone,
    device_protected: LockKeyhole,
    phishing_ready: Sparkles,
};

const iconForCheck = (key: string): Component => checkIcons[key] ?? ShieldCheck;

const statusIcon = (status: AuditStatus): Component => {
    return {
        passed: CheckCircle2,
        warning: AlertTriangle,
        failed: XCircle,
        skipped: MinusCircle,
    }[status];
};

const statusClasses = (status: AuditStatus): string => {
    return {
        passed: 'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        warning:
            'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        failed: 'border-destructive/25 bg-destructive/10 text-destructive dark:text-red-300',
        skipped: 'border-border bg-muted/45 text-muted-foreground',
    }[status];
};

const riskClasses = (riskLevel: RiskLevel): string => {
    return {
        protected:
            'border-emerald-500/25 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
        attention:
            'border-amber-500/25 bg-amber-500/10 text-amber-700 dark:text-amber-300',
        high_risk:
            'border-destructive/25 bg-destructive/10 text-destructive dark:text-red-300',
    }[riskLevel];
};

const detailForCheck = (check: AuditCheck): string | null => {
    if (check.key === 'active_sessions') {
        return `${t.value.security.audit_detected}: ${check.meta.count ?? 0}`;
    }

    if (check.key === 'recent_login_alerts') {
        return `${t.value.security.audit_detected}: ${check.meta.count ?? 0}`;
    }

    if (check.key === 'api_tokens') {
        return `${t.value.security.audit_total}: ${check.meta.count ?? 0} · ${t.value.security.audit_risky}: ${check.meta.risky_count ?? 0}`;
    }

    return null;
};

const formatDateTime = (value: string): string => {
    return new Intl.DateTimeFormat(
        language.value === 'ru' ? 'ru-RU' : 'en-US',
        { dateStyle: 'medium', timeStyle: 'short' },
    ).format(new Date(value));
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
    auditForm.post(SecurityController.storeAudit.url(), {
        preserveScroll: true,
        onStart: () => {
            scanStage.value = 0;
            stopScanning();
            scanTimer = setInterval(() => {
                scanStage.value = Math.min(scanStage.value + 1, 3);
            }, 450);
        },
        onFinish: stopScanning,
    });
};

onUnmounted(stopScanning);
</script>

<template>
    <section class="space-y-5" aria-labelledby="security-audit-title">
        <Card
            class="relative overflow-hidden border-primary/15 bg-gradient-to-br from-card via-card to-primary/5 shadow-md"
        >
            <div
                class="pointer-events-none absolute -top-24 -right-20 size-72 rounded-full bg-primary/8 blur-3xl"
            />
            <CardHeader
                class="relative gap-5 border-b border-border/60 pb-6 lg:flex-row lg:items-center lg:justify-between"
            >
                <div class="flex max-w-2xl items-start gap-4">
                    <div
                        class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-primary text-primary-foreground shadow-lg shadow-primary/20"
                    >
                        <ShieldCheck class="size-6" />
                    </div>
                    <div class="space-y-1.5">
                        <CardTitle id="security-audit-title" class="text-xl">
                            {{ t.security.audit_title }}
                        </CardTitle>
                        <CardDescription class="text-sm leading-6">
                            {{ t.security.audit_description }}
                        </CardDescription>
                    </div>
                </div>

                <Button
                    size="lg"
                    class="w-full gap-2 lg:w-auto"
                    :disabled="auditForm.processing"
                    data-test="run-security-audit"
                    @click="runAudit"
                >
                    <LoaderCircle
                        v-if="auditForm.processing"
                        class="size-4 animate-spin"
                    />
                    <ScanLine v-else class="size-4" />
                    {{
                        auditForm.processing
                            ? t.security.audit_running
                            : latest
                              ? t.security.audit_run_again
                              : t.security.audit_run
                    }}
                </Button>
            </CardHeader>

            <CardContent
                class="relative grid gap-6 pt-6 lg:grid-cols-[220px_1fr]"
            >
                <div class="flex flex-col items-center justify-center gap-3">
                    <div
                        class="grid size-36 place-items-center rounded-full p-2 shadow-inner"
                        :style="scoreRingStyle"
                    >
                        <div
                            class="grid size-full place-items-center rounded-full bg-card text-center"
                        >
                            <div>
                                <p class="text-4xl font-bold tracking-tight">
                                    {{ latest?.score ?? '—' }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ t.security.audit_score_of }}
                                </p>
                            </div>
                        </div>
                    </div>
                    <span
                        v-if="latest"
                        class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-semibold"
                        :class="riskClasses(latest.risk_level)"
                    >
                        {{ t.security.audit_risk[latest.risk_level] }}
                    </span>
                    <span v-else class="text-sm text-muted-foreground">
                        {{ t.security.audit_never_run }}
                    </span>
                </div>

                <div class="flex min-w-0 flex-col justify-center gap-5">
                    <div
                        v-if="latest"
                        class="grid grid-cols-2 gap-3 sm:grid-cols-4"
                    >
                        <div
                            class="rounded-xl border border-border/70 bg-background/70 p-3"
                        >
                            <p
                                class="text-2xl font-semibold text-emerald-600 dark:text-emerald-400"
                            >
                                {{ latest.passed_count }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ t.security.audit_passed }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border/70 bg-background/70 p-3"
                        >
                            <p
                                class="text-2xl font-semibold text-amber-600 dark:text-amber-400"
                            >
                                {{ latest.warning_count }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ t.security.audit_warnings }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border/70 bg-background/70 p-3"
                        >
                            <p class="text-2xl font-semibold text-destructive">
                                {{ latest.failed_count }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ t.security.audit_failed }}
                            </p>
                        </div>
                        <div
                            class="rounded-xl border border-border/70 bg-background/70 p-3"
                        >
                            <p class="text-2xl font-semibold">
                                {{ latest.total_count }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ t.security.audit_total_checks }}
                            </p>
                        </div>
                    </div>

                    <div
                        v-else
                        class="rounded-2xl border border-dashed border-border bg-background/60 p-5"
                    >
                        <div class="flex items-start gap-3">
                            <Sparkles class="mt-0.5 size-5 text-primary" />
                            <div>
                                <p class="font-medium">
                                    {{ t.security.audit_first_title }}
                                </p>
                                <p
                                    class="mt-1 text-sm leading-6 text-muted-foreground"
                                >
                                    {{ t.security.audit_first_description }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="latest"
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        <RefreshCw class="size-4" />
                        {{ t.security.audit_last_checked }}:
                        <span class="text-foreground">
                            {{ formatDateTime(latest.checked_at) }}
                        </span>
                    </div>
                </div>
            </CardContent>

            <div
                v-if="auditForm.processing"
                class="relative border-t border-border/60 bg-primary/5 px-6 py-4"
                aria-live="polite"
            >
                <div
                    class="mb-2 flex items-center justify-between gap-3 text-sm"
                >
                    <span class="font-medium">{{ scanStageLabel }}</span>
                    <span class="text-muted-foreground">
                        {{ scanStage + 1 }}/4
                    </span>
                </div>
                <div class="h-2 overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-full rounded-full bg-primary transition-all duration-500"
                        :style="{ width: `${(scanStage + 1) * 25}%` }"
                    />
                </div>
            </div>
        </Card>

        <Alert v-if="auditError" variant="destructive">
            <AlertTriangle class="size-4" />
            <AlertDescription>{{ auditError }}</AlertDescription>
        </Alert>

        <div class="grid gap-5 xl:grid-cols-2">
            <Card class="gap-0 py-0 shadow-sm">
                <CardHeader class="border-b border-border/60 py-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <CardTitle class="text-base">
                                {{ t.security.audit_automatic_title }}
                            </CardTitle>
                            <CardDescription>
                                {{ t.security.audit_automatic_description }}
                            </CardDescription>
                        </div>
                        <span
                            class="rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground"
                        >
                            {{ automaticChecks.length }}
                        </span>
                    </div>
                </CardHeader>
                <CardContent class="divide-y divide-border/60 p-0">
                    <div
                        v-for="check in automaticChecks"
                        :key="check.key"
                        class="flex items-start gap-3 p-4"
                    >
                        <div
                            class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-xl bg-muted/70"
                        >
                            <component
                                :is="iconForCheck(check.key)"
                                class="size-4"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="text-sm font-medium">
                                    {{
                                        t.security.audit_checks[check.key].title
                                    }}
                                </p>
                                <span
                                    v-if="latest"
                                    class="inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[11px] font-medium"
                                    :class="statusClasses(check.status)"
                                >
                                    <component
                                        :is="statusIcon(check.status)"
                                        class="size-3"
                                    />
                                    {{ t.security.audit_status[check.status] }}
                                </span>
                            </div>
                            <p
                                class="mt-1 text-xs leading-5 text-muted-foreground"
                            >
                                {{
                                    t.security.audit_checks[check.key]
                                        .description
                                }}
                            </p>
                            <p
                                v-if="latest && detailForCheck(check)"
                                class="mt-1 text-xs font-medium"
                            >
                                {{ detailForCheck(check) }}
                            </p>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card class="gap-0 py-0 shadow-sm">
                <CardHeader class="border-b border-border/60 py-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <CardTitle class="text-base">
                                {{ t.security.audit_manual_title }}
                            </CardTitle>
                            <CardDescription>
                                {{ t.security.audit_manual_description }}
                            </CardDescription>
                        </div>
                        <span
                            class="rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary"
                        >
                            {{ manualConfirmedCount }}/{{ manualKeys.length }}
                        </span>
                    </div>
                </CardHeader>
                <CardContent class="divide-y divide-border/60 p-0">
                    <Label
                        v-for="key in manualKeys"
                        :key="key"
                        :for="`audit-${key}`"
                        class="flex cursor-pointer items-start gap-3 p-4 transition-colors hover:bg-muted/35"
                    >
                        <Checkbox
                            :id="`audit-${key}`"
                            class="mt-0.5"
                            :model-value="auditForm.manual[key]"
                            :disabled="auditForm.processing"
                            @update:model-value="
                                updateManualAnswer(key, $event)
                            "
                        />
                        <span class="min-w-0">
                            <span class="block text-sm leading-5 font-medium">
                                {{ t.security.audit_manual[`${key}_title`] }}
                            </span>
                            <span
                                class="mt-1 block text-xs leading-5 text-muted-foreground"
                            >
                                {{
                                    t.security.audit_manual[
                                        `${key}_description`
                                    ]
                                }}
                            </span>
                        </span>
                    </Label>
                </CardContent>
            </Card>
        </div>

        <Card v-if="latest && findings.length" class="gap-0 py-0 shadow-sm">
            <CardHeader class="border-b border-border/60 py-5">
                <div class="flex items-start gap-3">
                    <div
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-700 dark:text-amber-300"
                    >
                        <AlertTriangle class="size-4" />
                    </div>
                    <div class="space-y-1">
                        <CardTitle class="text-base">
                            {{ t.security.audit_findings_title }}
                        </CardTitle>
                        <CardDescription>
                            {{ t.security.audit_findings_description }}
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="grid gap-3 p-4 md:grid-cols-2">
                <article
                    v-for="check in findings"
                    :key="check.key"
                    class="rounded-xl border border-border/70 bg-muted/20 p-4"
                >
                    <div class="flex items-start gap-3">
                        <component
                            :is="statusIcon(check.status)"
                            class="mt-0.5 size-5 shrink-0"
                            :class="
                                check.status === 'failed'
                                    ? 'text-destructive'
                                    : 'text-amber-600 dark:text-amber-400'
                            "
                        />
                        <div>
                            <p class="text-sm font-medium">
                                {{ t.security.audit_checks[check.key].title }}
                            </p>
                            <p
                                class="mt-1 text-xs leading-5 text-muted-foreground"
                            >
                                {{
                                    t.security.audit_checks[check.key]
                                        .recommendation
                                }}
                            </p>
                        </div>
                    </div>
                </article>
            </CardContent>
        </Card>

        <Card
            v-else-if="latest"
            class="border-emerald-500/20 bg-emerald-500/5 shadow-sm"
        >
            <CardContent class="flex items-start gap-3 p-5">
                <CheckCircle2 class="mt-0.5 size-5 shrink-0 text-emerald-600" />
                <div>
                    <p class="font-medium">
                        {{ t.security.audit_no_findings_title }}
                    </p>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ t.security.audit_no_findings_description }}
                    </p>
                </div>
            </CardContent>
        </Card>

        <Card v-if="audit.history.length" class="gap-0 py-0 shadow-sm">
            <CardHeader class="border-b border-border/60 py-5">
                <div class="flex items-center gap-3">
                    <History class="size-5 text-muted-foreground" />
                    <div>
                        <CardTitle class="text-base">
                            {{ t.security.audit_history_title }}
                        </CardTitle>
                        <CardDescription>
                            {{ t.security.audit_history_description }}
                        </CardDescription>
                    </div>
                </div>
            </CardHeader>
            <CardContent class="p-0">
                <div
                    v-for="record in audit.history"
                    :key="record.id"
                    class="grid gap-3 border-b border-border/60 p-4 last:border-b-0 sm:grid-cols-[80px_1fr_auto] sm:items-center"
                >
                    <div class="flex items-baseline gap-1">
                        <span class="text-2xl font-semibold">{{
                            record.score
                        }}</span>
                        <span class="text-xs text-muted-foreground">/100</span>
                    </div>
                    <div>
                        <p class="text-sm font-medium">
                            {{ formatDateTime(record.checked_at) }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ record.passed_count }}
                            {{ t.security.audit_passed_lower }} ·
                            {{ record.failed_count }}
                            {{ t.security.audit_failed_lower }}
                        </p>
                    </div>
                    <span
                        class="inline-flex w-fit items-center rounded-full border px-2.5 py-1 text-xs font-medium"
                        :class="riskClasses(record.risk_level)"
                    >
                        {{ t.security.audit_risk[record.risk_level] }}
                    </span>
                </div>
            </CardContent>
        </Card>

        <div
            v-if="!latest"
            class="flex items-center gap-2 text-xs text-muted-foreground"
        >
            <CircleDashed class="size-4" />
            {{ t.security.audit_privacy_note }}
        </div>
    </section>
</template>

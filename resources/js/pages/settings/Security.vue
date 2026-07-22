<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    Clock3,
    MapPin,
    MonitorSmartphone,
    RefreshCw,
    ShieldCheck,
} from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { watchEffect } from 'vue';
import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import type { Props as ManagePasskeysProps } from '@/components/ManagePasskeys.vue';
import ManagePasskeys from '@/components/ManagePasskeys.vue';
import type { Props as ManageTwoFactorProps } from '@/components/ManageTwoFactor.vue';
import ManageTwoFactor from '@/components/ManageTwoFactor.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import type { SecurityAuditData } from '@/components/SecurityAuditPanel.vue';
import SecurityAuditPanel from '@/components/SecurityAuditPanel.vue';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import { usePasswordGenerator } from '@/composables/usePasswordGenerator';
import { edit as editProfile } from '@/routes/profile';
import { edit } from '@/routes/security';

type SessionRow = {
    id: string;
    ip_address: string | null;
    user_agent: string | null;
    browser: string | null;
    platform: string | null;
    device_type: string;
    device_label: string;
    is_current: boolean;
    last_active_at: string;
    last_active_at_diff: string;
};

type LoginActivityRow = {
    id: number;
    ip_address: string | null;
    user_agent: string | null;
    browser: string | null;
    platform: string | null;
    device_type: string;
    device_label: string;
    is_new_device: boolean;
    is_new_ip: boolean;
    logged_in_at: string;
    logged_in_at_diff: string;
};

type Props = {
    passwordRules: string;
    sessions: SessionRow[];
    loginActivities: LoginActivityRow[];
    securityAudit: SecurityAuditData;
    twoFactorRequired: boolean;
    mustCompleteTwoFactor: boolean;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

const props = defineProps<Props>();
const { language, t } = useLanguage();
const { copy } = useClipboard();
const { generatePassword } = usePasswordGenerator();
const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submit = (): void => {
    form.put(SecurityController.update.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () =>
            form.reset('current_password', 'password', 'password_confirmation'),
    });
};

const applyGeneratedPassword = async (): Promise<void> => {
    if (form.processing) {
        return;
    }

    const generatedPassword = generatePassword();

    form.password = generatedPassword;
    form.password_confirmation = generatedPassword;

    try {
        await copy(generatedPassword);
    } catch {
        //
    }
};

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat(
        language.value === 'ru' ? 'ru-RU' : 'en-US',
        {
            dateStyle: 'medium',
            timeStyle: 'short',
        },
    ).format(new Date(value));
};

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.security.security_settings,
                href: edit(),
            },
        ],
    });
});
</script>

<template>
    <Head :title="t.security.security_settings" />

    <h1 class="sr-only">{{ t.security.security_settings }}</h1>

    <div class="space-y-6">
        <Button
            as-child
            variant="outline"
            size="lg"
            class="w-full justify-start sm:w-auto"
        >
            <Link :href="editProfile()">
                <ArrowLeft class="h-4 w-4" />
                {{ t.common.back }}
            </Link>
        </Button>

        <Alert
            v-if="props.mustCompleteTwoFactor"
            class="border-amber-500/35 bg-amber-500/10"
        >
            <ShieldCheck class="size-4 text-amber-600 dark:text-amber-300" />
            <AlertTitle>{{
                t.system_security.setup_required_title
            }}</AlertTitle>
            <AlertDescription>
                {{ t.system_security.two_factor_setup_required }}
            </AlertDescription>
        </Alert>

        <SecurityAuditPanel :audit="props.securityAudit" />

        <Heading
            variant="small"
            :title="t.security.update_password"
            :description="t.security.update_password_description"
        />

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <Label for="current_password">{{
                    t.security.current_password
                }}</Label>
                <PasswordInput
                    id="current_password"
                    v-model="form.current_password"
                    name="current_password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                    :placeholder="t.security.current_password"
                />
                <InputError :message="form.errors.current_password" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                    <Label for="password">{{ t.security.new_password }}</Label>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="applyGeneratedPassword"
                    >
                        <RefreshCw class="size-4" />
                        {{ t.common.generate_password }}
                    </Button>
                </div>
                <PasswordInput
                    id="password"
                    v-model="form.password"
                    name="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    :placeholder="t.security.new_password"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">{{
                    t.security.confirm_password
                }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    name="password_confirmation"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    :placeholder="t.security.confirm_password"
                    :passwordrules="props.passwordRules"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="form.processing"
                    data-test="update-password-button"
                >
                    {{ t.common.save }}
                </Button>
            </div>
        </form>

        <section class="space-y-6 rounded-2xl border border-border p-5">
            <Heading
                variant="small"
                :title="t.security.sessions_title"
                :description="t.security.sessions_description"
            />

            <div v-if="props.sessions.length" class="space-y-3">
                <article
                    v-for="session in props.sessions"
                    :key="session.id"
                    class="rounded-2xl border border-border/70 bg-muted/20 p-4"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <MonitorSmartphone class="size-4" />
                                <p class="font-medium">
                                    {{ session.device_label }}
                                </p>
                                <span
                                    v-if="session.is_current"
                                    class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-1 text-xs font-medium text-primary"
                                >
                                    {{ t.security.current_session }}
                                </span>
                            </div>

                            <p
                                class="text-sm text-muted-foreground"
                                :title="session.user_agent ?? undefined"
                            >
                                {{
                                    session.user_agent ??
                                    t.security.user_agent_unavailable
                                }}
                            </p>
                        </div>

                        <div
                            class="flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <Clock3 class="size-4" />
                            <span>{{
                                formatDateTime(session.last_active_at)
                            }}</span>
                        </div>
                    </div>

                    <div
                        class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-sm text-muted-foreground"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            <MapPin class="size-4" />
                            {{ t.security.ip_address }}:
                            {{ session.ip_address ?? '—' }}
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <Clock3 class="size-4" />
                            {{ t.security.last_activity }}:
                            {{ session.last_active_at_diff }}
                        </span>
                    </div>
                </article>
            </div>

            <p v-else class="text-sm text-muted-foreground">
                {{ t.security.no_active_sessions }}
            </p>
        </section>

        <section class="space-y-6 rounded-2xl border border-border p-5">
            <Heading
                variant="small"
                :title="t.security.login_history_title"
                :description="t.security.login_history_description"
            />

            <div v-if="props.loginActivities.length" class="space-y-3">
                <article
                    v-for="activity in props.loginActivities"
                    :key="activity.id"
                    class="rounded-2xl border border-border/70 bg-muted/20 p-4"
                >
                    <div
                        class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"
                    >
                        <div class="space-y-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <ShieldCheck class="size-4" />
                                <p class="font-medium">
                                    {{ activity.device_label }}
                                </p>
                                <span
                                    v-if="activity.is_new_device"
                                    class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-800 dark:bg-amber-500/20 dark:text-amber-200"
                                >
                                    {{ t.security.new_device }}
                                </span>
                                <span
                                    v-if="activity.is_new_ip"
                                    class="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-1 text-xs font-medium text-sky-800 dark:bg-sky-500/20 dark:text-sky-200"
                                >
                                    {{ t.security.new_ip }}
                                </span>
                            </div>

                            <p
                                class="text-sm text-muted-foreground"
                                :title="activity.user_agent ?? undefined"
                            >
                                {{
                                    activity.user_agent ??
                                    t.security.user_agent_unavailable
                                }}
                            </p>
                        </div>

                        <div
                            class="flex items-center gap-2 text-sm text-muted-foreground"
                        >
                            <Clock3 class="size-4" />
                            <span>{{
                                formatDateTime(activity.logged_in_at)
                            }}</span>
                        </div>
                    </div>

                    <div
                        class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-sm text-muted-foreground"
                    >
                        <span class="inline-flex items-center gap-1.5">
                            <MapPin class="size-4" />
                            {{ t.security.ip_address }}:
                            {{ activity.ip_address ?? '—' }}
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <Clock3 class="size-4" />
                            {{ t.security.logged_in_at }}:
                            {{ activity.logged_in_at_diff }}
                        </span>
                    </div>
                </article>
            </div>

            <p v-else class="text-sm text-muted-foreground">
                {{ t.security.no_login_history }}
            </p>
        </section>
    </div>

    <ManageTwoFactor
        :canManageTwoFactor="canManageTwoFactor"
        :canDisableTwoFactor="!props.twoFactorRequired"
        :requiresConfirmation="requiresConfirmation"
        :twoFactorEnabled="twoFactorEnabled"
    />

    <ManagePasskeys
        :canManagePasskeys="canManagePasskeys"
        :passkeys="passkeys"
    />
</template>

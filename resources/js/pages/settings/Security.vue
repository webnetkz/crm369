<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import { ArrowLeft, RefreshCw } from '@lucide/vue';
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
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import { usePasswordGenerator } from '@/composables/usePasswordGenerator';
import { edit as editProfile } from '@/routes/profile';
import { edit } from '@/routes/security';

type Props = {
    passwordRules: string;
} & ManagePasskeysProps &
    ManageTwoFactorProps;

const props = defineProps<Props>();
const { t } = useLanguage();
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
    </div>

    <ManageTwoFactor
        :canManageTwoFactor="canManageTwoFactor"
        :requiresConfirmation="requiresConfirmation"
        :twoFactorEnabled="twoFactorEnabled"
    />

    <ManagePasskeys
        :canManagePasskeys="canManagePasskeys"
        :passkeys="passkeys"
    />
</template>

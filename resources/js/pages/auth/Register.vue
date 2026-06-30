<script setup lang="ts">
import { Head, setLayoutProps, useForm } from '@inertiajs/vue3';
import { RefreshCw } from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useLanguage } from '@/composables/useLanguage';
import { usePasswordGenerator } from '@/composables/usePasswordGenerator';
import { login } from '@/routes';
import { store as registerUser } from '@/routes/register';

defineProps<{
    passwordRules: string;
}>();

const { t } = useLanguage();
const { copy } = useClipboard();
const { generatePassword } = usePasswordGenerator();
const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const canAutoSubmitRegistration = computed(() => {
    return form.name.trim() !== '' && form.email.trim() !== '';
});

const submit = (): void => {
    form.post(registerUser.url(), {
        preserveScroll: true,
        onSuccess: () => form.reset('password', 'password_confirmation'),
    });
};

const applyGeneratedPassword = async (): Promise<void> => {
    const generatedPassword = generatePassword();

    form.password = generatedPassword;
    form.password_confirmation = generatedPassword;

    try {
        await copy(generatedPassword);
    } catch {
        //
    }

    if (canAutoSubmitRegistration.value && !form.processing) {
        submit();
    }
};

watchEffect(() => {
    setLayoutProps({
        title: t.value.auth.create_account,
        description: t.value.auth.create_account_description,
    });
});
</script>

<template>
    <Head :title="t.common.register" />

    <form class="flex flex-col gap-6" @submit.prevent="submit">
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">{{ t.common.name }}</Label>
                <Input
                    id="name"
                    type="text"
                    required
                    autofocus
                    v-model="form.name"
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    :placeholder="t.auth.full_name"
                />
                <InputError :message="form.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ t.auth.email_address }}</Label>
                <Input
                    id="email"
                    type="email"
                    required
                    v-model="form.email"
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    placeholder="email@example.com"
                />
                <InputError :message="form.errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between gap-3">
                    <Label for="password">{{ t.common.password }}</Label>
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
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    :placeholder="t.common.password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="form.errors.password" />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">{{ t.auth.confirm_password }}</Label>
                <PasswordInput
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    :placeholder="t.auth.confirm_password"
                    :passwordrules="passwordRules"
                />
                <InputError :message="form.errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                tabindex="5"
                :disabled="form.processing"
                data-test="register-user-button"
            >
                <Spinner v-if="form.processing" />
                {{ t.auth.create_account }}
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            {{ t.auth.already_have_account }}
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="6"
                >{{ t.common.login }}</TextLink
            >
        </div>
    </form>
</template>

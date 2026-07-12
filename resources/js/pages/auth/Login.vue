<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { ref, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useCsrfToken } from '@/composables/useCsrfToken';
import { useLanguage } from '@/composables/useLanguage';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const { t } = useLanguage();
const csrfToken = useCsrfToken();
const isSubmitting = ref(false);
const loginUrl = store.url();

const submitLogin = (): void => {
    isSubmitting.value = true;
};

watchEffect(() => {
    setLayoutProps({
        title: t.value.auth.login_title,
        description: t.value.auth.enter_credentials,
    });
});
</script>

<template>
    <Head :title="t.common.login" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <PasskeyVerify />

    <form
        :action="loginUrl"
        method="post"
        class="flex flex-col gap-6"
        @submit="submitLogin"
    >
        <input type="hidden" name="_token" :value="csrfToken" />
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">{{ t.auth.email_address }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="$page.props.errors?.email" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-center justify-between">
                    <Label for="password">{{ t.common.password }}</Label>
                    <TextLink
                        v-if="canResetPassword"
                        :href="request()"
                        class="text-sm"
                        :tabindex="5"
                    >
                        {{ t.auth.forgot_password }}?
                    </TextLink>
                </div>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    :tabindex="2"
                    autocomplete="current-password"
                    :placeholder="t.common.password"
                />
                <InputError :message="$page.props.errors?.password" />
            </div>

            <div class="flex items-center justify-between">
                <Label for="remember" class="flex items-center space-x-3">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <span>{{ t.auth.remember_me }}</span>
                </Label>
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="isSubmitting"
                data-test="login-button"
            >
                <Spinner v-if="isSubmitting" />
                {{ t.common.login }}
            </Button>
        </div>

        <div class="text-center text-sm text-muted-foreground">
            {{ t.auth.dont_have_account }}
            <TextLink :href="register()" :tabindex="5">{{ t.common.register }}</TextLink>
        </div>
    </form>
</template>

<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useCsrfToken } from '@/composables/useCsrfToken';
import { useLanguage } from '@/composables/useLanguage';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineProps<{
    status?: string;
}>();

const { t } = useLanguage();
const csrfToken = useCsrfToken();

watchEffect(() => {
    setLayoutProps({
        title: t.value.auth.forgot_password,
        description: t.value.auth.forgot_password_description,
    });
});
</script>

<template>
    <Head :title="t.auth.forgot_password" />

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form
            v-bind="email.form()"
            :transform="(data) => ({ ...data, _token: csrfToken })"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="email">{{ t.auth.email_address }}</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    autofocus
                    placeholder="email@example.com"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="my-6 flex items-center justify-start">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" />
                    {{ t.auth.send_password_reset_link }}
                </Button>
            </div>
        </Form>

        <div class="space-x-1 text-center text-sm text-muted-foreground">
            <span>{{ t.auth.or_return_to }}</span>
            <TextLink :href="login()">{{ t.common.login }}</TextLink>
        </div>
    </div>
</template>

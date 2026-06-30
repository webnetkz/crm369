<script setup lang="ts">
import { Form, Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import { watchEffect } from 'vue';
import {
    index as confirmOptions,
    store as confirmStore,
} from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useLanguage } from '@/composables/useLanguage';
import { edit as editProfile } from '@/routes/profile';
import { store } from '@/routes/password/confirm';

const { t } = useLanguage();

watchEffect(() => {
    setLayoutProps({
        title: t.value.auth.confirm_password,
        description: t.value.auth.confirm_password_description,
    });
});
</script>

<template>
    <Head :title="t.auth.confirm_password" />

    <PasskeyVerify
        :routes="{
            options: confirmOptions(),
            submit: confirmStore(),
        }"
        :label="t.auth.confirm_with_passkey"
        :loading-label="t.auth.confirming"
        :separator="t.auth.or_confirm_with_password"
    />

    <Button
        as-child
        variant="outline"
        size="lg"
        class="mb-6 w-full justify-start"
    >
        <Link :href="editProfile()">
            <ArrowLeft class="h-4 w-4" />
            {{ t.common.back }}
        </Link>
    </Button>

    <Form
        v-bind="store.form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label htmlFor="password">{{ t.common.password }}</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                    autofocus
                />

                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center">
                <Button
                    class="w-full"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" />
                    {{ t.auth.confirm_password_button }}
                </Button>
            </div>
        </div>
    </Form>
</template>

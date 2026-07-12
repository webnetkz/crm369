<script setup lang="ts">
import { Form, Head, setLayoutProps } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, ref, watch, watchEffect } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { useCsrfToken } from '@/composables/useCsrfToken';
import { useLanguage } from '@/composables/useLanguage';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const authenticationCodeLength = 6;
const showRecoveryInput = ref<boolean>(false);
const code = ref<string>('');
const challengeContainer = ref<HTMLElement | null>(null);
const { t } = useLanguage();
const csrfToken = useCsrfToken();

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: t.value.auth.recovery_code,
            description: t.value.auth.recovery_code_description,
            buttonText: t.value.auth.sign_in_with_authentication_code,
        };
    }

    return {
        title: t.value.auth.two_factor_code,
        description: t.value.auth.two_factor_code_description,
        buttonText: t.value.auth.sign_in_with_recovery_code,
    };
});

watchEffect(() => {
    setLayoutProps({
        title: authConfigContent.value.title,
        description: authConfigContent.value.description,
    });
});

const focusChallengeField = (): void => {
    void nextTick(() => {
        const selector = showRecoveryInput.value
            ? 'input[name="recovery_code"]'
            : 'input[data-input-otp]';

        challengeContainer.value
            ?.querySelector<HTMLInputElement>(selector)
            ?.focus();
    });
};

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};

const handleCodeUpdate = (
    value: string | number | null | undefined,
    submit: () => void,
    clearErrors: (...fields: string[]) => void,
    processing: boolean,
): void => {
    code.value = String(value ?? '');
    clearErrors('code');

    if (processing || code.value.length !== authenticationCodeLength) {
        return;
    }

    void nextTick(() => {
        if (
            !showRecoveryInput.value &&
            code.value.length === authenticationCodeLength
        ) {
            submit();
        }
    });
};

onMounted(() => {
    focusChallengeField();
});

watch(showRecoveryInput, () => {
    focusChallengeField();
});
</script>

<template>
    <Head :title="t.auth.two_factor_authentication" />

    <div ref="challengeContainer" class="space-y-6">
        <template v-if="!showRecoveryInput">
            <Form
                v-bind="store.form()"
                :transform="(data) => ({ ...data, _token: csrfToken })"
                class="space-y-4"
                #default="{ errors, processing, clearErrors, submit }"
            >
                <input type="hidden" name="code" :value="code" />
                <div
                    class="flex flex-col items-center justify-center space-y-3 text-center"
                >
                    <div class="flex w-full items-center justify-center">
                        <InputOTP
                            id="otp"
                            :model-value="code"
                            :maxlength="authenticationCodeLength"
                            :disabled="processing"
                            autofocus
                            :aria-invalid="Boolean(errors.code)"
                            @update:model-value="
                                (value: string | number | null | undefined) =>
                                    handleCodeUpdate(
                                        value,
                                        submit,
                                        clearErrors,
                                        processing,
                                    )
                            "
                        >
                            <InputOTPGroup>
                                <InputOTPSlot
                                    v-for="index in authenticationCodeLength"
                                    :key="index"
                                    :index="index - 1"
                                    :aria-invalid="Boolean(errors.code)"
                                    :class="
                                        errors.code
                                            ? 'border-destructive text-destructive data-[active=true]:border-destructive data-[active=true]:ring-destructive/30'
                                            : undefined
                                    "
                                />
                            </InputOTPGroup>
                        </InputOTP>
                    </div>
                    <InputError :message="errors.code" />
                    <Spinner
                        v-if="processing"
                        class="size-4 text-muted-foreground"
                    />
                </div>
                <div class="text-center text-sm text-muted-foreground">
                    <span>{{ t.common.or_you_can }} </span>
                    <button
                        type="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>

        <template v-else>
            <Form
                v-bind="store.form()"
                :transform="(data) => ({ ...data, _token: csrfToken })"
                class="space-y-4"
                reset-on-error
                #default="{ errors, processing, clearErrors }"
            >
                <Input
                    name="recovery_code"
                    type="text"
                    :placeholder="t.auth.enter_recovery_code"
                    :autofocus="showRecoveryInput"
                    required
                />
                <InputError :message="errors.recovery_code" />
                <Button type="submit" class="w-full" :disabled="processing">{{
                    t.common.continue
                }}</Button>

                <div class="text-center text-sm text-muted-foreground">
                    <span>{{ t.common.or_you_can }} </span>
                    <button
                        type="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>
    </div>
</template>

<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch, watchEffect } from 'vue';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import LanguageTabs from '@/components/LanguageTabs.vue';
import LocalizedFilePicker from '@/components/LocalizedFilePicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getInitials } from '@/composables/useInitials';
import { useLanguage } from '@/composables/useLanguage';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { IssuedEquipmentSummary } from '@/types/ui';

const props = defineProps<{
    issuedEquipment: IssuedEquipmentSummary[];
}>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const { t } = useLanguage();
const localAvatarUrl = ref<string | null>(null);
const defaultKazakhstanPhonePrefix = '+7';

const formatKazakhstanPhone = (value: string | null | undefined): string => {
    const digits = (value ?? '').replace(/\D/g, '');

    if (digits === '') {
        return defaultKazakhstanPhonePrefix;
    }

    let normalizedDigits = digits;

    if (normalizedDigits.startsWith('8')) {
        normalizedDigits = `7${normalizedDigits.slice(1)}`;
    } else if (!normalizedDigits.startsWith('7')) {
        normalizedDigits = `7${normalizedDigits}`;
    }

    normalizedDigits = normalizedDigits.slice(0, 11);

    const localNumber = normalizedDigits.slice(1);
    const segments = [
        localNumber.slice(0, 3),
        localNumber.slice(3, 6),
        localNumber.slice(6, 8),
        localNumber.slice(8, 10),
    ].filter(Boolean);

    return [defaultKazakhstanPhonePrefix, ...segments].join(' ').trim();
};

const profileForm = useForm({
    _method: 'patch',
    name: user.value.name,
    last_name: user.value.last_name ?? '',
    middle_name: user.value.middle_name ?? '',
    email: user.value.email,
    phone: formatKazakhstanPhone(user.value.phone),
    position: user.value.position ?? '',
    avatar: null as File | null,
    avatar_scale: user.value.avatar_scale ?? 1,
});

const avatarPreviewUrl = computed(
    () => localAvatarUrl.value ?? user.value.avatar,
);

const avatarPreviewStyle = computed(() => ({
    objectPosition: 'center',
    transform: `scale(${profileForm.avatar_scale})`,
}));

const clearLocalAvatarUrl = (): void => {
    if (localAvatarUrl.value) {
        URL.revokeObjectURL(localAvatarUrl.value);
        localAvatarUrl.value = null;
    }
};

const selectAvatar = (file: File | null): void => {
    clearLocalAvatarUrl();

    if (file) {
        localAvatarUrl.value = URL.createObjectURL(file);
    }
};

const submitProfile = (): void => {
    profileForm.post(ProfileController.update.url(), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            profileForm.avatar = null;
        },
    });
};

onBeforeUnmount(clearLocalAvatarUrl);

watch(
    () => profileForm.phone,
    (value) => {
        const formatted = formatKazakhstanPhone(value);

        if (value !== formatted) {
            profileForm.phone = formatted;
        }
    },
);

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.profile.profile_settings,
                href: edit(),
            },
        ],
    });
});

const formatUserName = (person: {
    name: string;
    last_name: string | null;
} | null): string => {
    if (!person) {
        return t.value.equipment.not_assigned;
    }

    return [person.name, person.last_name].filter(Boolean).join(' ');
};
</script>

<template>
    <Head :title="t.profile.profile_settings" />

    <h1 class="sr-only">{{ t.profile.profile_settings }}</h1>

    <div class="flex flex-col space-y-6">
        <Heading
            variant="small"
            :title="t.profile.update_profile"
            :description="t.profile.update_profile_description"
        />

        <form class="space-y-6" @submit.prevent="submitProfile">
            <section
                class="grid gap-5 rounded-lg border border-border p-4 md:grid-cols-[auto_1fr] md:items-center"
            >
                <div class="flex justify-center md:justify-start">
                    <div
                        class="size-28 overflow-hidden rounded-full border border-border bg-muted shadow-inner"
                    >
                        <img
                            v-if="avatarPreviewUrl"
                            :src="avatarPreviewUrl"
                            :alt="user.name"
                            class="size-full object-cover"
                            :style="avatarPreviewStyle"
                        />
                        <div
                            v-else
                            class="flex size-full items-center justify-center bg-muted text-3xl font-semibold text-muted-foreground"
                        >
                            {{ getInitials(profileForm.name) }}
                        </div>
                    </div>
                </div>

                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="avatar">{{ t.profile.avatar }}</Label>
                        <LocalizedFilePicker
                            id="avatar"
                            v-model="profileForm.avatar"
                            accept="image/png,image/jpeg,image/jpg,image/webp"
                            @change="selectAvatar"
                        />
                        <p class="text-sm text-muted-foreground">
                            {{ t.profile.avatar_help }}
                        </p>
                        <InputError :message="profileForm.errors.avatar" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="avatar_scale">
                            {{ t.profile.zoom }}
                        </Label>
                        <Input
                            id="avatar_scale"
                            v-model.number="profileForm.avatar_scale"
                            type="range"
                            min="0.5"
                            max="3"
                            step="0.05"
                        />
                        <InputError
                            :message="profileForm.errors.avatar_scale"
                        />
                    </div>
                </div>
            </section>

            <div class="grid gap-2">
                <Label for="name">{{ t.common.name }}</Label>
                <Input
                    id="name"
                    v-model="profileForm.name"
                    class="mt-1 block w-full"
                    required
                    autocomplete="name"
                    :placeholder="t.auth.full_name"
                />
                <InputError class="mt-2" :message="profileForm.errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="last_name">{{ t.common.last_name }}</Label>
                <Input
                    id="last_name"
                    v-model="profileForm.last_name"
                    class="mt-1 block w-full"
                    autocomplete="family-name"
                    :placeholder="t.auth.last_name"
                />
                <InputError
                    class="mt-2"
                    :message="profileForm.errors.last_name"
                />
            </div>

            <div class="grid gap-2">
                <Label for="middle_name">{{ t.common.middle_name }}</Label>
                <Input
                    id="middle_name"
                    v-model="profileForm.middle_name"
                    class="mt-1 block w-full"
                    autocomplete="additional-name"
                    :placeholder="t.auth.middle_name"
                />
                <InputError
                    class="mt-2"
                    :message="profileForm.errors.middle_name"
                />
            </div>

            <div class="grid gap-2">
                <Label for="email">{{ t.common.email }}</Label>
                <Input
                    id="email"
                    v-model="profileForm.email"
                    type="email"
                    class="mt-1 block w-full"
                    required
                    autocomplete="username"
                    :placeholder="t.auth.email_address"
                />
                <InputError class="mt-2" :message="profileForm.errors.email" />
            </div>

            <div class="grid gap-2">
                <Label for="phone">{{ t.common.phone }}</Label>
                <Input
                    id="phone"
                    v-model="profileForm.phone"
                    type="tel"
                    class="mt-1 block w-full"
                    inputmode="tel"
                    autocomplete="tel"
                    :placeholder="t.profile.phone_placeholder"
                />
                <p class="text-sm text-muted-foreground">
                    {{ t.profile.phone_help }}
                </p>
                <InputError class="mt-2" :message="profileForm.errors.phone" />
            </div>

            <div class="grid gap-2">
                <Label for="position">{{ t.company_structure.position }}</Label>
                <Input
                    id="position"
                    v-model="profileForm.position"
                    class="mt-1 block w-full"
                    autocomplete="organization-title"
                    :placeholder="t.company_structure.no_position"
                />
                <InputError
                    class="mt-2"
                    :message="profileForm.errors.position"
                />
            </div>

            <section
                v-if="props.issuedEquipment.length > 0"
                class="space-y-4 rounded-lg border border-border p-4"
            >
                <Heading
                    variant="small"
                    :title="t.profile.issued_equipment"
                    :description="t.profile.issued_equipment_description"
                />

                <div class="grid gap-3">
                    <div
                        v-for="equipmentItem in props.issuedEquipment"
                        :key="equipmentItem.id"
                        class="rounded-2xl border border-border bg-card p-4"
                    >
                        <div
                            class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
                        >
                            <div class="space-y-1">
                                <div class="font-medium">
                                    {{ equipmentItem.name }}
                                </div>
                                <div class="flex flex-wrap items-start gap-4">
                                    <div
                                        class="overflow-hidden rounded-lg border border-border bg-white p-2 shadow-sm"
                                    >
                                        <img
                                            :src="
                                                equipmentItem.qr_code_svg_data_uri
                                            "
                                            :alt="`${t.equipment.qr_code}: ${equipmentItem.qr_code}`"
                                            class="size-24"
                                        />
                                    </div>
                                    <div
                                        class="text-sm text-muted-foreground"
                                    >
                                        {{ t.equipment.qr_code }}:
                                        {{ equipmentItem.qr_code }}
                                    </div>
                                </div>
                            </div>

                            <span
                                class="inline-flex w-fit rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground"
                            >
                                {{ equipmentItem.status_label }}
                            </span>
                        </div>

                        <div
                            class="mt-3 text-sm text-muted-foreground"
                        >
                            {{ t.equipment.responsible_user }}:
                            {{ formatUserName(equipmentItem.responsible_user) }}
                        </div>
                    </div>
                </div>
            </section>

            <section class="space-y-4 rounded-lg border border-border p-4">
                <Heading
                    variant="small"
                    :title="t.settings.language_settings"
                    :description="t.settings.language_description"
                />

                <LanguageTabs />
            </section>

            <div v-if="page.props.mustVerifyEmail && !user.email_verified_at">
                <p class="-mt-4 text-sm text-muted-foreground">
                    {{ t.profile.email_unverified }}
                    <Link
                        :href="send()"
                        as="button"
                        class="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                    >
                        {{ t.profile.resend_verification }}
                    </Link>
                </p>

                <div
                    v-if="page.props.status === 'verification-link-sent'"
                    class="mt-2 text-sm font-medium text-green-600"
                >
                    {{ t.profile.verification_link_sent }}
                </div>
            </div>

            <div
                v-if="profileForm.progress"
                class="h-2 overflow-hidden rounded-full bg-muted"
            >
                <div
                    class="h-full bg-primary transition-all"
                    :style="{ width: `${profileForm.progress.percentage}%` }"
                ></div>
            </div>

            <div class="flex items-center gap-4">
                <Button
                    :disabled="profileForm.processing"
                    data-test="update-profile-button"
                >
                    {{ t.common.save }}
                </Button>
            </div>
        </form>
    </div>
</template>

<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    BadgeCheck,
    ChevronsUpDown,
    CircleX,
    LogOut,
    Mail,
    Phone,
    RotateCcw,
    Settings,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import UserInfo from '@/components/UserInfo.vue';
import { useInitials } from '@/composables/useInitials';
import { useLanguage } from '@/composables/useLanguage';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import { destroy as destroyImpersonation } from '@/routes/settings/impersonation';

const page = usePage();
const auth = computed(() => page.props.auth);
const user = computed(() => auth.value.user);
const { getInitials } = useInitials();
const { language, t } = useLanguage();
const profileSheetOpen = ref(false);
const stoppingImpersonation = ref(false);

const avatarImageStyle = computed(() => ({
    objectPosition: 'center',
    transform: `scale(${user.value.avatar_scale ?? 1})`,
}));

const userGroupLabel = computed(() => {
    return user.value.group?.display_name ?? t.value.admin.simple_user;
});

const formatDateTime = (value: string | null): string => {
    if (! value) {
        return t.value.common.not_specified;
    }

    return new Intl.DateTimeFormat(
        language.value === 'ru' ? 'ru-RU' : 'en-US',
        {
            dateStyle: 'medium',
            timeStyle: 'short',
        },
    ).format(new Date(value));
};

const formatUserName = (person: {
    name: string;
    last_name: string | null;
} | null): string => {
    if (! person) {
        return t.value.equipment.not_assigned;
    }

    return [person.name, person.last_name].filter(Boolean).join(' ');
};

const handleLogout = (): void => {
    router.flushAll();
};

const closeProfileSheet = (): void => {
    profileSheetOpen.value = false;
};

const stopImpersonation = (): void => {
    if (stoppingImpersonation.value) {
        return;
    }

    stoppingImpersonation.value = true;

    router.delete(destroyImpersonation.url(), {
        preserveScroll: true,
        onFinish: () => {
            stoppingImpersonation.value = false;
        },
    });
};
</script>

<template>
    <SidebarMenu>
        <SidebarMenuItem>
            <Sheet
                :open="profileSheetOpen"
                @update:open="profileSheetOpen = $event"
            >
                <SheetTrigger as-child>
                    <SidebarMenuButton
                        size="lg"
                        class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                        data-test="sidebar-menu-button"
                    >
                        <UserInfo :user="user" />
                        <ChevronsUpDown class="ml-auto size-4" />
                    </SidebarMenuButton>
                </SheetTrigger>

                <SheetContent
                    side="left"
                    class="inset-y-auto top-auto bottom-4 left-4 flex h-auto max-h-[calc(100vh-2rem)] w-[calc(100vw-1.5rem)] flex-col gap-0 rounded-3xl border p-0 sm:max-w-md"
                >
                    <SheetHeader
                        class="border-b border-border px-5 py-5 text-left"
                    >
                        <div class="flex items-start gap-4 pr-8">
                            <Avatar
                                class="size-16 overflow-hidden rounded-3xl border border-border shadow-sm"
                            >
                                <AvatarImage
                                    v-if="user.avatar"
                                    :src="user.avatar"
                                    :alt="user.name"
                                    :style="avatarImageStyle"
                                />
                                <AvatarFallback
                                    class="bg-muted text-lg font-semibold text-foreground"
                                >
                                    {{ getInitials(user.name) }}
                                </AvatarFallback>
                            </Avatar>

                            <div class="min-w-0 space-y-3">
                                <div>
                                    <SheetTitle class="truncate pr-2">
                                        {{ user.name }}
                                    </SheetTitle>
                                    <SheetDescription>
                                        {{ t.profile.update_profile_description }}
                                    </SheetDescription>
                                </div>

                                <div class="flex flex-wrap gap-2">
                                    <span
                                        v-if="auth.isSuperAdmin"
                                        class="rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground"
                                    >
                                        {{ t.admin.super_admin }}
                                    </span>
                                    <span
                                        class="rounded-full bg-muted px-2.5 py-1 text-xs text-muted-foreground"
                                    >
                                        {{ userGroupLabel }}
                                    </span>
                                    <span
                                        class="rounded-full px-2.5 py-1 text-xs"
                                        :class="
                                            user.is_active
                                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                                : 'bg-destructive/10 text-destructive'
                                        "
                                    >
                                        {{
                                            user.is_active
                                                ? t.admin.active
                                                : t.admin.inactive
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </SheetHeader>

                    <div class="flex-1 space-y-6 overflow-y-auto px-5 py-5">
                        <div class="grid gap-3">
                            <div
                                class="rounded-2xl border border-border bg-card p-4"
                            >
                                <div
                                    class="mb-1 flex items-center gap-2 text-sm text-muted-foreground"
                                >
                                    <Mail class="size-4" />
                                    {{ t.common.email }}
                                </div>
                                <div class="break-all font-medium">
                                    {{ user.email }}
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-border bg-card p-4"
                            >
                                <div
                                    class="mb-1 flex items-center gap-2 text-sm text-muted-foreground"
                                >
                                    <Phone class="size-4" />
                                    {{ t.common.phone }}
                                </div>
                                <div class="font-medium">
                                    {{ user.phone ?? t.common.not_specified }}
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <div
                                class="rounded-2xl border border-border bg-card p-4"
                            >
                                <div class="text-sm text-muted-foreground">
                                    {{ t.common.name }}
                                </div>
                                <div class="mt-1 font-medium">
                                    {{ user.name }}
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-border bg-card p-4"
                            >
                                <div class="text-sm text-muted-foreground">
                                    {{ t.common.last_name }}
                                </div>
                                <div class="mt-1 font-medium">
                                    {{ user.last_name ?? t.common.not_specified }}
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-border bg-card p-4"
                            >
                                <div class="text-sm text-muted-foreground">
                                    {{ t.common.middle_name }}
                                </div>
                                <div class="mt-1 font-medium">
                                    {{ user.middle_name ?? t.common.not_specified }}
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-border bg-card p-4"
                            >
                                <div class="text-sm text-muted-foreground">
                                    {{ t.admin.group }}
                                </div>
                                <div class="mt-1 font-medium">
                                    {{ userGroupLabel }}
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-border bg-card p-4"
                            >
                                <div class="text-sm text-muted-foreground">
                                    {{ t.admin.email_verified }}
                                </div>
                                <div
                                    class="mt-1 flex items-center gap-2 font-medium"
                                >
                                    <BadgeCheck
                                        v-if="user.email_verified_at"
                                        class="size-4 text-emerald-600 dark:text-emerald-400"
                                    />
                                    <CircleX
                                        v-else
                                        class="size-4 text-destructive"
                                    />
                                    {{
                                        user.email_verified_at
                                            ? t.admin.verified
                                            : t.admin.not_verified
                                    }}
                                </div>
                            </div>

                            <div
                                class="rounded-2xl border border-border bg-card p-4 sm:col-span-2"
                            >
                                <div class="text-sm text-muted-foreground">
                                    {{ t.admin.created_at }}
                                </div>
                                <div class="mt-1 font-medium">
                                    {{ formatDateTime(user.created_at ?? null) }}
                                </div>
                            </div>
                        </div>

                        <div
                            v-if="user.issued_equipment?.length"
                            class="space-y-3"
                        >
                            <div class="text-sm font-medium text-foreground">
                                {{ t.profile.issued_equipment }}
                            </div>

                            <div class="grid gap-3">
                                <div
                                    v-for="equipmentItem in user.issued_equipment"
                                    :key="equipmentItem.id"
                                    class="rounded-2xl border border-border bg-card p-4"
                                >
                                    <div
                                        class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between"
                                    >
                                        <div class="space-y-1">
                                            <div class="font-medium">
                                                {{ equipmentItem.name }}
                                            </div>
                                            <div
                                                class="flex flex-wrap items-start gap-4"
                                            >
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
                                        {{
                                            formatUserName(
                                                equipmentItem.responsible_user,
                                            )
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="border-t border-border px-5 py-4"
                    >
                        <div class="grid gap-2">
                            <Button as-child class="w-full justify-start">
                                <Link
                                    :href="edit()"
                                    prefetch
                                    @click="closeProfileSheet"
                                >
                                    <Settings class="size-4" />
                                    {{ t.common.settings }}
                                </Link>
                            </Button>

                            <Button
                                v-if="auth.isImpersonating"
                                variant="outline"
                                class="w-full justify-start"
                                :disabled="stoppingImpersonation"
                                @click="stopImpersonation"
                            >
                                <RotateCcw class="size-4" />
                                {{ t.admin.return_to_account }}
                            </Button>

                            <Button
                                as-child
                                variant="outline"
                                class="w-full justify-start"
                            >
                                <Link
                                    :href="logout()"
                                    as="button"
                                    data-test="logout-button"
                                    @click="handleLogout"
                                >
                                    <LogOut class="size-4" />
                                    {{ t.common.logout }}
                                </Link>
                            </Button>
                        </div>
                    </div>
                </SheetContent>
            </Sheet>
        </SidebarMenuItem>
    </SidebarMenu>
</template>

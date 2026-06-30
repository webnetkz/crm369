<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell, Check, CheckCheck, ExternalLink } from '@lucide/vue';
import { computed, ref } from 'vue';
import { update, updateAll } from '@/actions/App/Http/Controllers/NotificationController';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useLanguage } from '@/composables/useLanguage';
import { index } from '@/routes/notifications';
import type { NotificationCenter, PortalNotificationItem } from '@/types/ui';

const page = usePage();
const { language, t } = useLanguage();
const optimisticallyReadNotificationIds = ref<Set<string>>(new Set());

const notifications = computed(
    () => page.props.notifications as NotificationCenter,
);

const unreadBadge = computed(() => {
    const count = notifications.value.unreadCount;

    if (count <= 0) {
        return null;
    }

    return count > 99 ? '99+' : String(count);
});

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat(
        language.value === 'ru' ? 'ru-RU' : 'en-US',
        {
            dateStyle: 'medium',
            timeStyle: 'short',
        },
    ).format(new Date(value));
};

const isMarkedAsRead = (notification: PortalNotificationItem): boolean => {
    return notification.isRead || optimisticallyReadNotificationIds.value.has(notification.id);
};

const markAsRead = (notification: PortalNotificationItem): void => {
    if (isMarkedAsRead(notification)) {
        return;
    }

    optimisticallyReadNotificationIds.value.add(notification.id);

    router.patch(
        update.url(notification.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                optimisticallyReadNotificationIds.value.delete(notification.id);
            },
        },
    );
};

const markAllAsRead = (): void => {
    if (notifications.value.unreadCount === 0) {
        return;
    }

    router.patch(
        updateAll.url(),
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const openTarget = (notification: PortalNotificationItem): void => {
    if (!notification.actionUrl) {
        markAsRead(notification);

        return;
    }

    if (isMarkedAsRead(notification)) {
        router.visit(notification.actionUrl);

        return;
    }

    router.patch(
        update.url(notification.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                router.visit(notification.actionUrl as string);
            },
        },
    );
};
</script>

<template>
    <Sheet>
        <SheetTrigger :as-child="true">
            <Button
                variant="ghost"
                size="icon"
                class="relative h-9 w-9 cursor-pointer rounded-full"
                :title="t.notifications.panel_title"
            >
                <Bell class="size-5 opacity-85" />
                <span
                    v-if="unreadBadge"
                    class="absolute -top-0.5 -right-0.5 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-destructive px-1 text-[11px] font-semibold text-white"
                >
                    {{ unreadBadge }}
                </span>
            </Button>
        </SheetTrigger>

        <SheetContent side="right" class="w-full gap-0 p-0 sm:max-w-md md:max-w-lg">
            <SheetHeader class="border-b border-border px-6 py-6 text-left">
                <div class="flex items-start justify-between gap-4 pr-8">
                    <div class="space-y-1">
                        <SheetTitle>{{ t.notifications.panel_title }}</SheetTitle>
                        <SheetDescription>
                            {{ t.notifications.panel_description }}
                        </SheetDescription>
                    </div>

                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        :disabled="notifications.unreadCount === 0"
                        @click="markAllAsRead"
                    >
                        <CheckCheck />
                        {{ t.notifications.mark_all_as_read }}
                    </Button>
                </div>
            </SheetHeader>

            <div class="flex-1 overflow-y-auto px-6 py-6">
                <div
                    v-if="notifications.items.length === 0"
                    class="rounded-2xl border border-dashed border-border bg-muted/30 p-8 text-center text-sm text-muted-foreground"
                >
                    {{ t.notifications.empty }}
                </div>

                <div v-else class="space-y-3">
                    <article
                        v-for="notification in notifications.items"
                        :key="notification.id"
                        class="rounded-2xl border p-4 transition"
                        @mouseenter="markAsRead(notification)"
                        :class="
                            isMarkedAsRead(notification)
                                ? 'border-border bg-background'
                                : 'border-primary/20 bg-primary/5'
                        "
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 space-y-2">
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="!isMarkedAsRead(notification)"
                                        class="size-2 rounded-full bg-primary"
                                    ></span>
                                    <h3 class="font-medium">
                                        {{ notification.title }}
                                    </h3>
                                </div>

                                <p class="text-sm text-muted-foreground">
                                    {{ notification.message }}
                                </p>

                                <p
                                    v-if="notification.createdAt"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ formatDateTime(notification.createdAt) }}
                                </p>
                            </div>

                            <Button
                                v-if="!isMarkedAsRead(notification)"
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                :title="t.notifications.mark_as_read"
                                @click="markAsRead(notification)"
                            >
                                <Check />
                            </Button>
                        </div>

                        <div
                            v-if="notification.actionUrl"
                            class="mt-4 flex justify-end"
                        >
                            <Button
                                type="button"
                                variant="secondary"
                                size="sm"
                                @click="openTarget(notification)"
                            >
                                <ExternalLink />
                                {{
                                    notification.actionLabel ??
                                    t.notifications.open_target
                                }}
                            </Button>
                        </div>
                    </article>
                </div>
            </div>

            <div class="border-t border-border px-6 py-4">
                <Button as-child type="button" variant="outline" class="w-full">
                    <Link :href="index()">
                        {{ t.notifications.open_page }}
                    </Link>
                </Button>
            </div>
        </SheetContent>
    </Sheet>
</template>

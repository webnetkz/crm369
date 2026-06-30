<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm, usePage, usePoll } from '@inertiajs/vue3';
import { Bell, Check, CheckCheck, ExternalLink } from '@lucide/vue';
import { computed, watchEffect } from 'vue';
import { update, updateAll } from '@/actions/App/Http/Controllers/NotificationController';
import Heading from '@/components/Heading.vue';
import PaginationControls from '@/components/PaginationControls.vue';
import { Button } from '@/components/ui/button';
import { useLanguage } from '@/composables/useLanguage';
import { index } from '@/routes/notifications';
import type { PaginatedCollection, PortalNotificationItem } from '@/types/ui';

type Filters = {
    status: string;
    per_page: number;
};

const props = defineProps<{
    notificationFeed: PaginatedCollection<PortalNotificationItem>;
    filters: Filters;
    perPageOptions: number[];
}>();

const { language, t } = useLanguage();
const page = usePage();

usePoll(
    10000,
    {
        only: ['notifications', 'notificationFeed'],
        preserveScroll: true,
        preserveState: true,
    },
    {
        mode: 'rest',
    },
);

const filtersForm = useForm<Filters>({
    status: props.filters.status,
    per_page: props.filters.per_page,
});

const unreadCount = computed(() => {
    return Number(page.props.notifications?.unreadCount ?? 0);
});

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.notifications.panel_title,
                href: index(),
            },
        ],
    });
});

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '';
    }

    return new Intl.DateTimeFormat(language.value === 'ru' ? 'ru-RU' : 'en-US', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};

const submitFilters = (): void => {
    router.get(
        index.url(),
        {
            status: filtersForm.status,
            per_page: filtersForm.per_page,
        },
        {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        },
    );
};

const updatePerPage = (value: number): void => {
    filtersForm.per_page = value;
    submitFilters();
};

const markAsRead = (notification: PortalNotificationItem): void => {
    if (notification.isRead) {
        return;
    }

    router.patch(
        update.url(notification.id),
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const markAllAsRead = (): void => {
    if (unreadCount.value === 0) {
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

    if (notification.isRead) {
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
    <Head :title="t.notifications.panel_title" />

    <h1 class="sr-only">{{ t.notifications.panel_title }}</h1>

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="t.notifications.panel_title"
            :description="t.notifications.panel_description"
        />

        <section class="space-y-4 rounded-2xl border border-border p-5">
            <div
                class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="flex size-12 items-center justify-center rounded-2xl bg-primary/10 text-primary"
                    >
                        <Bell class="size-5" />
                    </div>

                    <div>
                        <div class="text-sm text-muted-foreground">
                            {{ t.notifications.panel_title }}
                        </div>
                        <div class="text-lg font-semibold">
                            {{ unreadCount }}
                        </div>
                    </div>
                </div>

                <Button
                    type="button"
                    variant="outline"
                    :disabled="unreadCount === 0"
                    @click="markAllAsRead"
                >
                    <CheckCheck class="size-4" />
                    {{ t.notifications.mark_all_as_read }}
                </Button>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    size="sm"
                    :variant="filtersForm.status === 'all' ? 'default' : 'outline'"
                    @click="
                        filtersForm.status = 'all';
                        submitFilters();
                    "
                >
                    {{ t.notifications.status_all }}
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :variant="filtersForm.status === 'unread' ? 'default' : 'outline'"
                    @click="
                        filtersForm.status = 'unread';
                        submitFilters();
                    "
                >
                    {{ t.notifications.status_unread }}
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :variant="filtersForm.status === 'read' ? 'default' : 'outline'"
                    @click="
                        filtersForm.status = 'read';
                        submitFilters();
                    "
                >
                    {{ t.notifications.status_read }}
                </Button>
            </div>
        </section>

        <section class="space-y-4">
            <div
                v-if="props.notificationFeed.data.length === 0"
                class="rounded-2xl border border-dashed border-border bg-muted/30 p-8 text-center text-sm text-muted-foreground"
            >
                {{ t.notifications.empty }}
            </div>

            <div v-else class="space-y-3">
                <article
                    v-for="notification in props.notificationFeed.data"
                    :key="notification.id"
                    class="rounded-2xl border p-5 transition"
                    :class="
                        notification.isRead
                            ? 'border-border bg-background'
                            : 'border-primary/20 bg-primary/5'
                    "
                >
                    <div
                        class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
                    >
                        <div class="min-w-0 space-y-2">
                            <div class="flex items-center gap-2">
                                <span
                                    v-if="!notification.isRead"
                                    class="size-2 rounded-full bg-primary"
                                ></span>
                                <h2 class="font-medium">
                                    {{ notification.title }}
                                </h2>
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

                        <div class="flex flex-wrap items-center gap-2 lg:justify-end">
                            <Button
                                v-if="!notification.isRead"
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="markAsRead(notification)"
                            >
                                <Check class="size-4" />
                                {{ t.notifications.mark_as_read }}
                            </Button>

                            <Button
                                v-if="notification.actionUrl"
                                as-child
                                type="button"
                                variant="secondary"
                                size="sm"
                            >
                                <Link
                                    :href="notification.actionUrl"
                                    @click.prevent="openTarget(notification)"
                                >
                                    <ExternalLink class="size-4" />
                                    {{
                                        notification.actionLabel ??
                                        t.notifications.open_target
                                    }}
                                </Link>
                            </Button>
                        </div>
                    </div>
                </article>
            </div>

            <PaginationControls
                :pagination="props.notificationFeed"
                :per-page-options="props.perPageOptions"
                @update:per-page="updatePerPage"
            />
        </section>
    </div>
</template>

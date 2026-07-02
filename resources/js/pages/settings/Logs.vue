<script setup lang="ts">
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import { FileText } from '@lucide/vue';
import { watchEffect } from 'vue';
import Heading from '@/components/Heading.vue';
import PaginationControls from '@/components/PaginationControls.vue';
import { useLanguage } from '@/composables/useLanguage';
import { edit } from '@/routes/settings/logs';
import type { PaginatedCollection } from '@/types/ui';

type LogFile = {
    name: string;
    size: number;
    modified_at: string | null;
    entries_count: number;
};

type LogEntry = {
    file_name: string;
    channel: string | null;
    level: string | null;
    summary: string;
    content: string;
    timestamp: string | null;
};

const props = defineProps<{
    files: LogFile[];
    entries: PaginatedCollection<LogEntry>;
    filters: {
        per_page: number;
    };
    perPageOptions: number[];
}>();

const { language, t } = useLanguage();

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.settings.logs,
                href: edit(),
            },
        ],
    });
});

const formatDateTime = (value: string | null): string => {
    if (!value) {
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

const formatBytes = (bytes: number): string => {
    const locale = language.value === 'ru' ? 'ru-RU' : 'en-US';

    if (bytes < 1024) {
        return `${new Intl.NumberFormat(locale).format(bytes)} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${new Intl.NumberFormat(locale, {
            maximumFractionDigits: 1,
        }).format(bytes / 1024)} KB`;
    }

    return `${new Intl.NumberFormat(locale, {
        maximumFractionDigits: 1,
    }).format(bytes / (1024 * 1024))} MB`;
};

const levelClasses = (level: string | null): string => {
    switch (level) {
        case 'error':
        case 'critical':
        case 'alert':
        case 'emergency':
            return 'border-red-500/30 bg-red-500/10 text-red-700 dark:text-red-300';
        case 'warning':
            return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
        case 'info':
            return 'border-sky-500/30 bg-sky-500/10 text-sky-700 dark:text-sky-300';
        default:
            return 'border-border bg-muted text-muted-foreground';
    }
};

const updatePerPage = (value: number): void => {
    router.get(edit.url(), { per_page: value }, {
        preserveScroll: true,
        preserveState: true,
        replace: true,
    });
};
</script>

<template>
    <Head :title="t.settings.logs" />

    <h1 class="sr-only">{{ t.settings.logs }}</h1>

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="t.settings.logs"
            :description="t.admin.logs_description"
        />

        <div class="grid gap-5 xl:grid-cols-[320px_minmax(0,1fr)]">
            <aside class="space-y-4 rounded-2xl border border-border bg-card p-5">
                <div class="flex items-center gap-2">
                    <FileText class="size-4 text-muted-foreground" />
                    <h2 class="text-sm font-semibold">{{ t.admin.logs_files_title }}</h2>
                </div>

                <div
                    v-if="props.files.length > 0"
                    class="space-y-3"
                >
                    <article
                        v-for="file in props.files"
                        :key="file.name"
                        class="rounded-xl border border-border bg-background/70 p-4"
                    >
                        <div class="truncate text-sm font-medium text-foreground">
                            {{ file.name }}
                        </div>
                        <dl class="mt-3 space-y-2 text-xs text-muted-foreground">
                            <div class="flex items-center justify-between gap-3">
                                <dt>{{ t.admin.logs_modified }}</dt>
                                <dd class="text-right">{{ formatDateTime(file.modified_at) }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt>{{ t.admin.logs_size }}</dt>
                                <dd>{{ formatBytes(file.size) }}</dd>
                            </div>
                            <div class="flex items-center justify-between gap-3">
                                <dt>{{ t.admin.logs_entries_count }}</dt>
                                <dd>{{ file.entries_count }}</dd>
                            </div>
                        </dl>
                    </article>
                </div>

                <p v-else class="text-sm text-muted-foreground">
                    {{ t.admin.logs_empty }}
                </p>
            </aside>

            <section class="space-y-5">
                <div class="rounded-2xl border border-border bg-card p-5">
                    <h2 class="text-sm font-semibold">{{ t.admin.logs_entries_title }}</h2>
                </div>

                <div
                    v-if="props.entries.data.length === 0"
                    class="rounded-2xl border border-dashed border-border bg-card px-6 py-12 text-center text-sm text-muted-foreground"
                >
                    {{ t.admin.logs_empty }}
                </div>

                <div v-else class="space-y-4">
                    <article
                        v-for="entry in props.entries.data"
                        :key="`${entry.file_name}-${entry.timestamp ?? entry.summary}`"
                        class="rounded-2xl border border-border bg-card p-5"
                    >
                        <div class="flex flex-col gap-3 border-b border-border pb-4">
                            <div class="text-sm font-medium text-foreground">
                                {{ entry.summary }}
                            </div>

                            <div class="flex flex-wrap gap-2 text-xs">
                                <span class="rounded-full border border-border bg-muted px-2.5 py-1 text-muted-foreground">
                                    {{ entry.file_name }}
                                </span>
                                <span
                                    class="rounded-full border px-2.5 py-1"
                                    :class="levelClasses(entry.level)"
                                >
                                    {{ t.admin.logs_level }}:
                                    {{ (entry.level ?? 'unknown').toUpperCase() }}
                                </span>
                                <span
                                    v-if="entry.channel"
                                    class="rounded-full border border-border bg-background px-2.5 py-1 text-muted-foreground"
                                >
                                    {{ t.admin.logs_channel }}: {{ entry.channel }}
                                </span>
                                <span class="rounded-full border border-border bg-background px-2.5 py-1 text-muted-foreground">
                                    {{ t.admin.logs_recorded_at }}:
                                    {{ formatDateTime(entry.timestamp) }}
                                </span>
                            </div>
                        </div>

                        <pre class="mt-4 overflow-x-auto rounded-xl bg-muted/60 p-4 font-mono text-xs leading-5 whitespace-pre-wrap break-words text-foreground">{{ entry.content }}</pre>
                    </article>
                </div>

                <PaginationControls
                    :pagination="props.entries"
                    :per-page-options="props.perPageOptions"
                    @update:per-page="updatePerPage"
                />
            </section>
        </div>
    </div>
</template>

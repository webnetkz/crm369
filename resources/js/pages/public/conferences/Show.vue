<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ExternalLink, MessageSquareText, MonitorUp, PencilRuler, Video } from '@lucide/vue';
import { computed } from 'vue';
import { useLanguage } from '@/composables/useLanguage';

type PublicConference = {
    id: number;
    title: string;
    description: string | null;
    starts_at: string | null;
    ended_at: string | null;
    status: 'live' | 'scheduled' | 'ended';
    provider_label: string;
    embed_url: string;
    meeting_url: string;
    creator: {
        id: number;
        name: string;
        last_name: string | null;
        email: string;
    } | null;
};

const props = defineProps<{
    conference: PublicConference;
}>();

const { language, t } = useLanguage();

const locale = computed(() => (language.value === 'ru' ? 'ru-RU' : 'en-US'));

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return t.value.common.not_specified;
    }

    return new Intl.DateTimeFormat(locale.value, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
};
</script>

<template>
    <Head :title="conference.title" />

    <div class="min-h-screen bg-linear-to-br from-background via-background to-primary/5 px-4 py-10 sm:px-6">
        <div class="mx-auto max-w-7xl space-y-6">
            <section class="rounded-3xl border border-border bg-card/90 p-6 shadow-sm">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-3">
                        <div class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-sm font-medium text-primary">
                            <Video class="size-4" />
                            {{ t.conferences.public_page_title }}
                        </div>

                        <div>
                            <h1 class="text-3xl font-semibold tracking-tight">
                                {{ conference.title }}
                            </h1>
                            <p class="mt-2 max-w-3xl text-sm leading-6 text-muted-foreground">
                                {{
                                    conference.description ||
                                    t.conferences.public_page_description
                                }}
                            </p>
                        </div>

                        <div class="grid gap-2 text-sm text-muted-foreground">
                            <div>
                                <span class="font-medium text-foreground">
                                    {{ t.conferences.host }}:
                                </span>
                                {{
                                    [conference.creator?.name, conference.creator?.last_name]
                                        .filter(Boolean)
                                        .join(' ') || t.common.not_specified
                                }}
                            </div>
                            <div>
                                <span class="font-medium text-foreground">
                                    {{ t.conferences.field_starts_at }}:
                                </span>
                                {{ formatDateTime(conference.starts_at) }}
                            </div>
                            <div>
                                <span class="font-medium text-foreground">
                                    {{ t.conferences.provider_label }}:
                                </span>
                                {{ conference.provider_label }}
                            </div>
                        </div>
                    </div>

                    <a
                        v-if="conference.status !== 'ended'"
                        :href="conference.meeting_url"
                        target="_blank"
                        rel="noreferrer"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-md border border-input bg-background px-4 text-sm font-medium shadow-xs transition hover:bg-accent hover:text-accent-foreground"
                    >
                        <ExternalLink class="size-4" />
                        {{ t.conferences.open_in_new_tab }}
                    </a>
                </div>

                <div class="mt-6 grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-2xl border border-border/70 bg-background/70 p-4 text-sm text-muted-foreground">
                        <div class="flex items-center gap-2 font-medium text-foreground">
                            <Video class="size-4" />
                            {{ t.conferences.capability_video }}
                        </div>
                    </div>
                    <div class="rounded-2xl border border-border/70 bg-background/70 p-4 text-sm text-muted-foreground">
                        <div class="flex items-center gap-2 font-medium text-foreground">
                            <MessageSquareText class="size-4" />
                            {{ t.conferences.capability_chat }}
                        </div>
                    </div>
                    <div class="rounded-2xl border border-border/70 bg-background/70 p-4 text-sm text-muted-foreground">
                        <div class="flex items-center gap-2 font-medium text-foreground">
                            <MonitorUp class="size-4" />
                            {{ t.conferences.capability_screen }}
                        </div>
                    </div>
                    <div class="rounded-2xl border border-border/70 bg-background/70 p-4 text-sm text-muted-foreground">
                        <div class="flex items-center gap-2 font-medium text-foreground">
                            <PencilRuler class="size-4" />
                            {{ t.conferences.capability_whiteboard }}
                        </div>
                    </div>
                </div>
            </section>

            <div
                v-if="conference.status === 'ended'"
                class="rounded-3xl border border-dashed border-border bg-card/70 p-10 text-center text-sm text-muted-foreground"
            >
                {{ t.conferences.ended_notice }}
            </div>

            <iframe
                v-else
                :src="conference.embed_url"
                class="h-[calc(100vh-12rem)] min-h-[44rem] w-full rounded-3xl border border-border bg-black shadow-sm"
                allow="camera; microphone; display-capture; fullscreen; clipboard-read; clipboard-write"
            ></iframe>
        </div>
    </div>
</template>

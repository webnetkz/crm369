<script setup lang="ts">
import { Head, Link, setLayoutProps, useForm } from '@inertiajs/vue3';
import { CalendarClock, Plus, Users, Video } from '@lucide/vue';
import { computed, watchEffect } from 'vue';
import { store as storeConference } from '@/actions/App/Http/Controllers/ConferenceController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import TaskUserPicker from '@/components/TaskUserPicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import { index, show as showConference } from '@/routes/conferences';
import type { ProjectUserSummary } from '@/types/ui';

type ConferenceListItem = {
    id: number;
    title: string;
    description: string | null;
    starts_at: string | null;
    ended_at: string | null;
    status: 'live' | 'scheduled' | 'ended';
    allow_external_guests: boolean;
    external_join_url: string | null;
    invited_users_count: number;
    creator: {
        id: number;
        name: string;
        last_name: string | null;
        email: string;
    } | null;
};

const props = defineProps<{
    conferences: ConferenceListItem[];
    availableUsers: ProjectUserSummary[];
    provider: {
        label: string;
    };
}>();

const { language, t } = useLanguage();

const form = useForm({
    title: '',
    description: '',
    starts_at: '',
    allow_external_guests: true,
    invited_user_ids: [] as number[],
});

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.conferences.title,
                href: index(),
            },
        ],
    });
});

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

const conferenceStatusLabel = (status: ConferenceListItem['status']): string => {
    if (status === 'scheduled') {
        return t.value.conferences.status_scheduled;
    }

    if (status === 'ended') {
        return t.value.conferences.status_ended;
    }

    return t.value.conferences.status_live;
};

const conferenceStatusClass = (status: ConferenceListItem['status']): string => {
    if (status === 'scheduled') {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    if (status === 'ended') {
        return 'bg-muted text-muted-foreground';
    }

    return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
};

const submit = (): void => {
    form.post(storeConference.url(), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            form.allow_external_guests = true;
            form.invited_user_ids = [];
        },
    });
};
</script>

<template>
    <Head :title="t.conferences.title" />

    <h1 class="sr-only">{{ t.conferences.title }}</h1>

    <div class="space-y-8">
        <Heading
            variant="small"
            :title="t.conferences.title"
            :description="t.conferences.description"
        />

        <div class="grid gap-6 xl:grid-cols-[24rem_minmax(0,1fr)]">
            <section class="rounded-2xl border border-border bg-card p-5 shadow-xs">
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-base font-semibold">
                        <Plus class="size-4 text-primary" />
                        {{ t.conferences.create_title }}
                    </div>
                    <p class="text-sm leading-6 text-muted-foreground">
                        {{ t.conferences.create_description }}
                    </p>
                </div>

                <form class="mt-6 space-y-5" @submit.prevent="submit">
                    <div class="space-y-2">
                        <Label for="conference-title">
                            {{ t.conferences.field_title }}
                        </Label>
                        <Input
                            id="conference-title"
                            v-model="form.title"
                            :placeholder="t.conferences.field_title"
                        />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="space-y-2">
                        <Label for="conference-description">
                            {{ t.conferences.field_description }}
                        </Label>
                        <textarea
                            id="conference-description"
                            v-model="form.description"
                            rows="4"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm shadow-xs outline-none transition placeholder:text-muted-foreground focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        ></textarea>
                        <InputError :message="form.errors.description" />
                    </div>

                    <div class="space-y-2">
                        <Label for="conference-starts-at">
                            {{ t.conferences.field_starts_at }}
                        </Label>
                        <Input
                            id="conference-starts-at"
                            v-model="form.starts_at"
                            type="datetime-local"
                        />
                        <InputError :message="form.errors.starts_at" />
                    </div>

                    <div class="space-y-2">
                        <Label>
                            {{ t.conferences.field_invited_users }}
                        </Label>
                        <TaskUserPicker
                            v-model="form.invited_user_ids"
                            :empty-label="t.conferences.select_users"
                            :options="availableUsers"
                            multiple
                        />
                        <InputError :message="form.errors.invited_user_ids" />
                    </div>

                    <label
                        class="flex items-start gap-3 rounded-xl border border-border bg-background/60 p-3"
                    >
                        <input
                            v-model="form.allow_external_guests"
                            type="checkbox"
                            class="mt-1 size-4 rounded border-input text-primary focus:ring-ring"
                        />
                        <span class="space-y-1">
                            <span class="block text-sm font-medium">
                                {{ t.conferences.field_allow_external_guests }}
                            </span>
                            <span class="block text-xs text-muted-foreground">
                                {{ t.conferences.public_page_description }}
                            </span>
                        </span>
                    </label>

                    <Button type="submit" :disabled="form.processing" class="w-full">
                        <Video class="size-4" />
                        {{ t.conferences.create_button }}
                    </Button>
                </form>
            </section>

            <section class="space-y-4">
                <div
                    class="flex flex-col gap-3 rounded-2xl border border-border bg-card/70 p-5 sm:flex-row sm:items-center sm:justify-between"
                >
                    <div class="space-y-1">
                        <div class="text-sm font-medium">
                            {{ t.conferences.title }}
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ provider.label }}
                        </p>
                    </div>
                    <div
                        class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-sm font-medium text-primary"
                    >
                        <Users class="size-4" />
                        {{ conferences.length }}
                    </div>
                </div>

                <div
                    v-if="conferences.length === 0"
                    class="rounded-2xl border border-dashed border-border bg-muted/30 p-8 text-center text-sm text-muted-foreground"
                >
                    {{ t.conferences.empty }}
                </div>

                <div v-else class="grid gap-4 lg:grid-cols-2">
                    <Link
                        v-for="conference in conferences"
                        :key="conference.id"
                        :href="showConference(conference.id)"
                        class="rounded-2xl border border-border bg-card p-5 shadow-xs transition hover:border-primary/30 hover:bg-primary/5"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-2">
                                <h2 class="text-base font-semibold">
                                    {{ conference.title }}
                                </h2>
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        conference.description ||
                                        t.conferences.meeting_description
                                    }}
                                </p>
                            </div>

                            <span
                                class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                                :class="conferenceStatusClass(conference.status)"
                            >
                                {{ conferenceStatusLabel(conference.status) }}
                            </span>
                        </div>

                        <div class="mt-5 grid gap-3 text-sm text-muted-foreground">
                            <div class="flex items-center gap-2">
                                <CalendarClock class="size-4" />
                                {{ formatDateTime(conference.starts_at) }}
                            </div>
                            <div class="flex items-center gap-2">
                                <Users class="size-4" />
                                {{
                                    `${t.conferences.participants}: ${
                                        conference.invited_users_count + 1
                                    }`
                                }}
                            </div>
                        </div>
                    </Link>
                </div>
            </section>
        </div>
    </div>
</template>

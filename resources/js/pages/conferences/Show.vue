<script setup lang="ts">
import { Head, Link, router, setLayoutProps, useForm } from '@inertiajs/vue3';
import {
    Copy,
    ExternalLink,
    MessageSquareText,
    MonitorUp,
    PencilRuler,
    SquareTerminal,
    Users,
    Video,
} from '@lucide/vue';
import { useClipboard } from '@vueuse/core';
import { computed, ref, watchEffect } from 'vue';
import { end as endConference } from '@/actions/App/Http/Controllers/ConferenceController';
import { store as storeConferenceInvitation } from '@/actions/App/Http/Controllers/ConferenceInvitationController';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import TaskUserPicker from '@/components/TaskUserPicker.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import { index, show as showConference } from '@/routes/conferences';
import type { ProjectUserSummary } from '@/types/ui';

type ConferenceListItem = {
    id: number;
    title: string;
    status: 'live' | 'scheduled' | 'ended';
};

type ConferenceDetail = {
    id: number;
    title: string;
    description: string | null;
    starts_at: string | null;
    ended_at: string | null;
    status: 'live' | 'scheduled' | 'ended';
    allow_external_guests: boolean;
    external_join_url: string | null;
    room_name: string;
    provider_label: string;
    embed_url: string;
    meeting_url: string;
    creator: {
        id: number;
        name: string;
        last_name: string | null;
        email: string;
    } | null;
    can: {
        manage: boolean;
        invite: boolean;
    };
    invited_users: Array<{
        id: number;
        invited_at: string | null;
        joined_at: string | null;
        last_opened_at: string | null;
        user: ProjectUserSummary | null;
    }>;
};

const props = defineProps<{
    conference: ConferenceDetail;
    conferences: ConferenceListItem[];
    availableUsers: ProjectUserSummary[];
    provider: {
        label: string;
    };
}>();

const { copy } = useClipboard();
const { language, t } = useLanguage();
const copiedPublicLink = ref(false);

const inviteForm = useForm({
    invited_user_ids: [] as number[],
});

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.conferences.title,
                href: index(),
            },
            {
                title: props.conference.title,
                href: showConference(props.conference.id),
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

const conferenceStatusLabel = computed(() => {
    if (props.conference.status === 'scheduled') {
        return t.value.conferences.status_scheduled;
    }

    if (props.conference.status === 'ended') {
        return t.value.conferences.status_ended;
    }

    return t.value.conferences.status_live;
});

const conferenceStatusClass = computed(() => {
    if (props.conference.status === 'scheduled') {
        return 'bg-amber-500/10 text-amber-700 dark:text-amber-300';
    }

    if (props.conference.status === 'ended') {
        return 'bg-muted text-muted-foreground';
    }

    return 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
});

const invitedUserIds = computed(() => {
    return props.conference.invited_users
        .map((invitation) => invitation.user?.id)
        .filter((id): id is number => typeof id === 'number');
});

const excludedUserIds = computed(() => {
    const creatorId = props.conference.creator?.id;

    return [
        ...invitedUserIds.value,
        ...(typeof creatorId === 'number' ? [creatorId] : []),
    ];
});

const submitInvitations = (): void => {
    inviteForm.post(storeConferenceInvitation.url(props.conference.id), {
        preserveScroll: true,
        onSuccess: () => {
            inviteForm.reset();
            inviteForm.invited_user_ids = [];
        },
    });
};

const endMeeting = (): void => {
    router.patch(endConference(props.conference.id), {}, { preserveScroll: true });
};

const copyPublicLink = async (): Promise<void> => {
    if (!props.conference.external_join_url) {
        return;
    }

    await copy(props.conference.external_join_url);
    copiedPublicLink.value = true;

    window.setTimeout(() => {
        copiedPublicLink.value = false;
    }, 1800);
};
</script>

<template>
    <Head :title="conference.title" />

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="conference.title"
            :description="conference.description || t.conferences.description"
        />

        <div class="grid gap-6 xl:grid-cols-[22rem_minmax(0,1fr)]">
            <aside class="space-y-4">
                <section class="rounded-2xl border border-border bg-card p-5 shadow-xs">
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-2">
                            <div class="text-base font-semibold">
                                {{ conference.title }}
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{ conference.provider_label }}
                            </div>
                        </div>

                        <span
                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-medium"
                            :class="conferenceStatusClass"
                        >
                            {{ conferenceStatusLabel }}
                        </span>
                    </div>

                    <div class="mt-5 space-y-3 text-sm text-muted-foreground">
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
                                {{ t.conferences.room_code }}:
                            </span>
                            <span class="break-all">{{ conference.room_name }}</span>
                        </div>
                    </div>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <Button
                            v-if="conference.external_join_url"
                            type="button"
                            variant="outline"
                            @click="copyPublicLink"
                        >
                            <Copy class="size-4" />
                            {{
                                copiedPublicLink
                                    ? t.conferences.copy_link_success
                                    : t.conferences.copy_public_link
                            }}
                        </Button>

                        <a
                            v-if="conference.status !== 'ended'"
                            :href="conference.meeting_url"
                            target="_blank"
                            rel="noreferrer"
                            class="inline-flex h-9 items-center justify-center gap-2 rounded-md border border-input bg-background px-4 text-sm font-medium shadow-xs transition hover:bg-accent hover:text-accent-foreground"
                        >
                            <ExternalLink class="size-4" />
                            {{ t.conferences.open_in_new_tab }}
                        </a>
                    </div>

                    <div class="mt-5 rounded-2xl border border-border/70 bg-background/70 p-4">
                        <div class="text-sm font-medium">
                            {{ t.conferences.meeting_capabilities }}
                        </div>
                        <div class="mt-4 grid gap-3 text-sm text-muted-foreground">
                            <div class="flex items-center gap-2">
                                <Video class="size-4" />
                                {{ t.conferences.capability_video }}
                            </div>
                            <div class="flex items-center gap-2">
                                <MessageSquareText class="size-4" />
                                {{ t.conferences.capability_chat }}
                            </div>
                            <div class="flex items-center gap-2">
                                <MonitorUp class="size-4" />
                                {{ t.conferences.capability_screen }}
                            </div>
                            <div class="flex items-center gap-2">
                                <PencilRuler class="size-4" />
                                {{ t.conferences.capability_whiteboard }}
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-2xl border border-border bg-card p-5 shadow-xs">
                    <div class="flex items-center gap-2 text-base font-semibold">
                        <Users class="size-4 text-primary" />
                        {{ t.conferences.participants }}
                    </div>

                    <div v-if="conference.invited_users.length === 0" class="mt-4 text-sm text-muted-foreground">
                        {{ t.conferences.no_invited_users }}
                    </div>

                    <div v-else class="mt-4 space-y-3">
                        <article
                            v-for="invitation in conference.invited_users"
                            :key="invitation.id"
                            class="rounded-xl border border-border/70 bg-background/70 p-3"
                        >
                            <div class="font-medium">
                                {{
                                    [invitation.user?.name, invitation.user?.last_name]
                                        .filter(Boolean)
                                        .join(' ')
                                }}
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{ invitation.user?.email }}
                            </div>
                        </article>
                    </div>
                </section>

                <section
                    v-if="conference.can.invite"
                    class="rounded-2xl border border-border bg-card p-5 shadow-xs"
                >
                    <div class="space-y-2">
                        <div class="text-base font-semibold">
                            {{ t.conferences.invite_more }}
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ t.conferences.invite_more_description }}
                        </p>
                    </div>

                    <form class="mt-5 space-y-4" @submit.prevent="submitInvitations">
                        <div class="space-y-2">
                            <Label>
                                {{ t.conferences.field_invited_users }}
                            </Label>
                            <TaskUserPicker
                                v-model="inviteForm.invited_user_ids"
                                :empty-label="t.conferences.select_users"
                                :options="availableUsers"
                                :exclude-user-ids="excludedUserIds"
                                multiple
                            />
                            <InputError :message="inviteForm.errors.invited_user_ids" />
                        </div>

                        <Button type="submit" :disabled="inviteForm.processing" class="w-full">
                            <Users class="size-4" />
                            {{ t.conferences.invite_button }}
                        </Button>
                    </form>
                </section>

                <section v-if="conference.can.manage" class="rounded-2xl border border-border bg-card p-5 shadow-xs">
                    <Button
                        type="button"
                        variant="outline"
                        class="w-full"
                        :disabled="conference.status === 'ended'"
                        @click="endMeeting"
                    >
                        <SquareTerminal class="size-4" />
                        {{ t.conferences.end_conference }}
                    </Button>
                </section>

                <section class="rounded-2xl border border-border bg-card p-5 shadow-xs">
                    <div class="text-base font-semibold">
                        {{ t.conferences.title }}
                    </div>

                    <div class="mt-4 space-y-2">
                        <Link
                            v-for="item in conferences"
                            :key="item.id"
                            :href="showConference(item.id)"
                            class="block rounded-xl border px-3 py-2 text-sm transition"
                            :class="
                                item.id === conference.id
                                    ? 'border-primary/30 bg-primary/5 text-foreground'
                                    : 'border-border bg-background text-muted-foreground hover:text-foreground'
                            "
                        >
                            {{ item.title }}
                        </Link>
                    </div>
                </section>
            </aside>

            <section class="space-y-4">
                <div class="rounded-2xl border border-border bg-card p-5 shadow-xs">
                    <div class="space-y-1">
                        <div class="text-base font-semibold">
                            {{ t.conferences.meeting_title }}
                        </div>
                        <p class="text-sm text-muted-foreground">
                            {{ t.conferences.meeting_description }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="conference.status === 'ended'"
                    class="rounded-2xl border border-dashed border-border bg-muted/30 p-8 text-center text-sm text-muted-foreground"
                >
                    {{ t.conferences.ended_notice }}
                </div>

                <iframe
                    v-else
                    :src="conference.embed_url"
                    class="h-[calc(100vh-12rem)] min-h-[42rem] w-full rounded-2xl border border-border bg-black shadow-sm"
                    allow="camera; microphone; display-capture; fullscreen; clipboard-read; clipboard-write"
                ></iframe>
            </section>
        </div>
    </div>
</template>

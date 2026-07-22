<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { LoaderCircle, MessageSquareMore } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { index } from '@/actions/App/Http/Controllers/ChatSidebarController';
import ChatCenterSheet from '@/components/ChatCenterSheet.vue';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useChatCenterPresence } from '@/composables/useChatCenterPresence';
import { getInitials } from '@/composables/useInitials';
import { useLanguage } from '@/composables/useLanguage';
import { fetchSameOriginJson } from '@/lib/sameOriginJson';
import type { ChatCenter } from '@/types/ui';

type SheetMode = 'chats' | 'search';

type DockEntry = {
    key: string;
    title: string;
    subtitle: string | null;
    avatar: string | null;
    avatarScale: number;
    isGeneral: boolean;
    unreadCount: number;
    conversationId: number | null;
    contactId: number | null;
};

const page = usePage();
const { t } = useLanguage();
const { isAnyChatCenterVisible } = useChatCenterPresence();
const open = ref(false);
const mode = ref<SheetMode>('chats');
const initialConversationId = ref<number | null>(null);
const initialContactId = ref<number | null>(null);
const payload = ref<ChatCenter | null>(null);
const loading = ref(false);
const failedAvatarKeys = ref(new Set<string>());
let pollInterval: ReturnType<typeof setInterval> | null = null;
let dockAbortController: AbortController | null = null;

const unreadBadge = computed(() => {
    const count = page.props.chat.unreadCount;

    if (count <= 0) {
        return null;
    }

    return count > 99 ? '99+' : String(count);
});

const isChatModuleEnabled = computed(() => {
    return page.props.portal.enabledModules.includes('chats');
});

const shouldShowDock = computed(() => {
    return (
        isChatModuleEnabled.value &&
        page.component !== 'chats/Index' &&
        !isAnyChatCenterVisible.value
    );
});

const dockEntries = computed<DockEntry[]>(() => {
    const entries: DockEntry[] = [];
    const seenUsers = new Set<number>();

    for (const conversation of payload.value?.conversations ?? []) {
        const participant = conversation.participant;
        const isGeneral = conversation.type === 'general';

        if (!isGeneral && !participant) {
            continue;
        }

        entries.push({
            key: `conversation-${conversation.id}`,
            title: conversation.title,
            subtitle: conversation.subtitle,
            avatar: isGeneral
                ? page.props.portal.logoUrl
                : (participant?.avatar ?? null),
            avatarScale: isGeneral ? 1 : (participant?.avatarScale ?? 1),
            isGeneral,
            unreadCount: conversation.unreadCount,
            conversationId: conversation.id,
            contactId: participant?.id ?? null,
        });

        if (participant) {
            seenUsers.add(participant.id);
        }
    }

    for (const contact of payload.value?.contacts ?? []) {
        if (seenUsers.has(contact.id)) {
            continue;
        }

        entries.push({
            key: `contact-${contact.id}`,
            title: contact.name,
            subtitle: contact.email,
            avatar: contact.avatar,
            avatarScale: contact.avatarScale,
            isGeneral: false,
            unreadCount: 0,
            conversationId: null,
            contactId: contact.id,
        });
        seenUsers.add(contact.id);
    }

    return entries;
});

const visibleDockEntries = computed<DockEntry[]>(() => {
    return [...dockEntries.value]
        .sort((left, right) => {
            return Number(right.unreadCount > 0) - Number(left.unreadCount > 0);
        })
        .slice(0, 6);
});

const avatarStyle = (entry: DockEntry): Record<string, string> => ({
    objectPosition: 'center',
    transform: `scale(${entry.avatarScale ?? 1})`,
});

const avatarKey = (entry: DockEntry): string =>
    `${entry.key}:${entry.avatar ?? ''}`;

const shouldShowAvatar = (
    entry: DockEntry,
): entry is DockEntry & {
    avatar: string;
} => {
    return Boolean(
        entry.avatar && !failedAvatarKeys.value.has(avatarKey(entry)),
    );
};

const markAvatarFailed = (entry: DockEntry): void => {
    failedAvatarKeys.value.add(avatarKey(entry));
};

const entryAriaLabel = (entry: DockEntry): string => {
    if (entry.unreadCount > 0) {
        return `${entry.title}. ${t.value.chat.unread}: ${entry.unreadCount}`;
    }

    return entry.subtitle ? `${entry.title}. ${entry.subtitle}` : entry.title;
};

const syncSharedUnread = (unreadCount: number): void => {
    (page.props as Record<string, unknown>).chat = {
        unreadCount,
    };
};

const fetchDockData = async (): Promise<void> => {
    if (dockAbortController !== null) {
        return;
    }

    const requestController = new AbortController();

    dockAbortController = requestController;
    loading.value = payload.value === null;

    try {
        const data = await fetchSameOriginJson<ChatCenter>(index.url(), {
            method: 'GET',
            signal: requestController.signal,
        });

        payload.value = data;
        syncSharedUnread(data.unreadCount);
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        console.error(error);
    } finally {
        if (dockAbortController === requestController) {
            dockAbortController = null;
            loading.value = false;
        }
    }
};

const refreshDockData = (): void => {
    void fetchDockData();
};

const startPolling = (): void => {
    if (pollInterval) {
        return;
    }

    pollInterval = setInterval(() => {
        if (document.hidden || dockAbortController !== null) {
            return;
        }

        void fetchDockData();
    }, 10000);
};

const handleVisibilityChange = (): void => {
    if (!document.hidden) {
        void fetchDockData();
    }
};

const stopPolling = (): void => {
    if (!pollInterval) {
        return;
    }

    clearInterval(pollInterval);
    pollInterval = null;
};

const openChatCenter = (
    nextMode: SheetMode,
    conversationId: number | null = null,
    contactId: number | null = null,
): void => {
    mode.value = nextMode;
    initialConversationId.value = conversationId;
    initialContactId.value = contactId;
    open.value = true;
};

onMounted(() => {
    void fetchDockData();
    startPolling();
    document.addEventListener('visibilitychange', handleVisibilityChange);
});

onBeforeUnmount(() => {
    stopPolling();
    dockAbortController?.abort();
    document.removeEventListener('visibilitychange', handleVisibilityChange);
});
</script>

<template>
    <div
        v-if="shouldShowDock"
        class="pointer-events-none fixed right-3 bottom-5 z-30 sm:right-5"
    >
        <div
            class="group/dock pointer-events-auto relative flex flex-col items-end gap-3"
            @pointerenter="refreshDockData"
            @focusin="refreshDockData"
        >
            <div
                aria-hidden="true"
                class="pointer-events-none absolute right-0 bottom-full h-5 w-20 group-focus-within/dock:pointer-events-auto group-hover/dock:pointer-events-auto"
            />

            <div
                v-if="visibleDockEntries.length > 0"
                class="pointer-events-none absolute right-0 bottom-full mb-3 flex max-w-[min(22rem,calc(100vw-1.5rem))] translate-y-2 flex-wrap items-center justify-end gap-2 overflow-visible rounded-3xl border border-border/70 bg-background/88 p-2 opacity-0 shadow-2xl backdrop-blur-xl transition-all duration-200 group-focus-within/dock:pointer-events-auto group-focus-within/dock:translate-y-0 group-focus-within/dock:opacity-100 group-hover/dock:pointer-events-auto group-hover/dock:translate-y-0 group-hover/dock:opacity-100 supports-[backdrop-filter]:bg-background/70"
            >
                <button
                    v-for="entry in visibleDockEntries"
                    :key="entry.key"
                    type="button"
                    class="group/avatar relative shrink-0 rounded-2xl transition outline-none hover:-translate-y-0.5 focus-visible:ring-2 focus-visible:ring-ring"
                    :title="
                        entry.subtitle
                            ? `${entry.title} · ${entry.subtitle}`
                            : entry.title
                    "
                    :aria-label="entryAriaLabel(entry)"
                    @click="
                        openChatCenter(
                            'chats',
                            entry.conversationId,
                            entry.contactId,
                        )
                    "
                >
                    <span class="relative block overflow-visible">
                        <Avatar
                            class="size-11 rounded-2xl border border-border bg-background shadow-sm transition group-hover/avatar:border-primary/40"
                        >
                            <img
                                v-if="shouldShowAvatar(entry)"
                                :src="entry.avatar"
                                :alt="entry.title"
                                class="absolute inset-0 z-10 aspect-square size-full"
                                :class="
                                    entry.isGeneral
                                        ? 'bg-white object-contain p-1'
                                        : 'object-cover'
                                "
                                :style="avatarStyle(entry)"
                                @error="markAvatarFailed(entry)"
                            />
                            <AvatarFallback
                                :aria-hidden="shouldShowAvatar(entry)"
                                class="rounded-2xl bg-muted font-semibold text-foreground"
                            >
                                {{ getInitials(entry.title) }}
                            </AvatarFallback>
                        </Avatar>
                        <span
                            v-if="entry.unreadCount > 0"
                            aria-hidden="true"
                            class="absolute -top-2 -right-2 z-30 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground ring-2 ring-background"
                        >
                            {{
                                entry.unreadCount > 9 ? '9+' : entry.unreadCount
                            }}
                        </span>
                    </span>
                </button>
            </div>

            <div
                class="rounded-3xl border border-border/70 bg-background/88 p-2 shadow-2xl backdrop-blur-xl supports-[backdrop-filter]:bg-background/70"
            >
                <Button
                    type="button"
                    size="icon"
                    class="relative size-12 rounded-2xl shadow-sm"
                    :title="t.chat.sidebar_trigger"
                    @click="openChatCenter('chats')"
                >
                    <LoaderCircle
                        v-if="loading && !payload"
                        class="size-5 animate-spin opacity-85"
                    />
                    <MessageSquareMore v-else class="size-5 opacity-90" />
                    <span
                        v-if="unreadBadge"
                        class="absolute -top-1 -right-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[11px] font-semibold text-primary-foreground"
                    >
                        {{ unreadBadge }}
                    </span>
                </Button>
            </div>
        </div>
    </div>

    <ChatCenterSheet
        :open="open"
        :mode="mode"
        :initial-conversation-id="initialConversationId"
        :initial-contact-id="initialContactId"
        @update:open="open = $event"
    />
</template>

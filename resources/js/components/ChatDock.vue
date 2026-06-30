<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { LoaderCircle, MessageSquareMore } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { index } from '@/actions/App/Http/Controllers/ChatSidebarController';
import ChatCenterSheet from '@/components/ChatCenterSheet.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
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
let pollInterval: ReturnType<typeof setInterval> | null = null;

const unreadBadge = computed(() => {
    const count = page.props.chat.unreadCount;

    if (count <= 0) {
        return null;
    }

    return count > 99 ? '99+' : String(count);
});

const shouldShowDock = computed(() => {
    return (
        page.component !== 'chats/Index' && !isAnyChatCenterVisible.value
    );
});

const dockEntries = computed<DockEntry[]>(() => {
    const entries: DockEntry[] = [];
    const seenUsers = new Set<number>();

    for (const conversation of payload.value?.conversations ?? []) {
        if (!conversation.participant) {
            continue;
        }

        entries.push({
            key: `conversation-${conversation.id}`,
            title: conversation.title,
            subtitle: conversation.subtitle,
            avatar: conversation.participant.avatar,
            avatarScale: conversation.participant.avatarScale,
            unreadCount: conversation.unreadCount,
            conversationId: conversation.id,
            contactId: conversation.participant.id,
        });
        seenUsers.add(conversation.participant.id);
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
            unreadCount: 0,
            conversationId: null,
            contactId: contact.id,
        });
        seenUsers.add(contact.id);
    }

    return entries.slice(0, 6);
});

const avatarStyle = (entry: DockEntry): Record<string, string> => ({
    objectPosition: 'center',
    transform: `scale(${entry.avatarScale ?? 1})`,
});

const syncSharedUnread = (unreadCount: number): void => {
    (page.props as Record<string, unknown>).chat = {
        unreadCount,
    };
};

const fetchDockData = async (): Promise<void> => {
    loading.value = payload.value === null;

    try {
        const data = await fetchSameOriginJson<ChatCenter>(index.url(), {
            method: 'GET',
        });

        payload.value = data;
        syncSharedUnread(data.unreadCount);
    } catch (error) {
        console.error(error);
    } finally {
        loading.value = false;
    }
};

const startPolling = (): void => {
    if (pollInterval) {
        return;
    }

    pollInterval = setInterval(() => {
        void fetchDockData();
    }, 10000);
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
});

onBeforeUnmount(() => {
    stopPolling();
});
</script>

<template>
    <div
        v-if="shouldShowDock"
        class="pointer-events-none fixed right-3 bottom-5 z-30 sm:right-5"
    >
        <div class="pointer-events-auto relative flex flex-col items-end gap-3 group/dock">
            <div
                aria-hidden="true"
                class="pointer-events-none absolute right-0 bottom-full h-5 w-20 group-focus-within/dock:pointer-events-auto group-hover/dock:pointer-events-auto"
            />

            <div
                class="pointer-events-none absolute right-0 bottom-full mb-3 flex translate-y-2 flex-col items-center gap-2 rounded-3xl border border-border/70 bg-background/88 p-2 opacity-0 shadow-2xl transition-all duration-200 group-focus-within/dock:pointer-events-auto group-focus-within/dock:translate-y-0 group-focus-within/dock:opacity-100 group-hover/dock:pointer-events-auto group-hover/dock:translate-y-0 group-hover/dock:opacity-100 backdrop-blur-xl supports-[backdrop-filter]:bg-background/70"
            >
                <div
                    v-if="dockEntries.length > 0"
                    class="flex flex-col items-center gap-2"
                >
                    <button
                        v-for="entry in dockEntries"
                        :key="entry.key"
                        type="button"
                        class="group relative rounded-2xl outline-none transition hover:-translate-y-1 focus-visible:ring-2 focus-visible:ring-ring"
                        :title="entry.subtitle ? `${entry.title} · ${entry.subtitle}` : entry.title"
                        @click="openChatCenter('chats', entry.conversationId, entry.contactId)"
                    >
                        <Avatar class="size-12 rounded-2xl border border-border bg-background shadow-sm transition group-hover:border-primary/40">
                            <AvatarImage
                                v-if="entry.avatar"
                                :src="entry.avatar"
                                :alt="entry.title"
                                :style="avatarStyle(entry)"
                            />
                            <AvatarFallback class="rounded-2xl bg-muted font-semibold text-foreground">
                                {{ getInitials(entry.title) }}
                            </AvatarFallback>
                        </Avatar>
                        <span
                            v-if="entry.unreadCount > 0"
                            class="absolute -top-1 -right-1 inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[11px] font-semibold text-primary-foreground"
                        >
                            {{ entry.unreadCount > 9 ? '9+' : entry.unreadCount }}
                        </span>
                    </button>
                </div>
            </div>

            <div class="rounded-3xl border border-border/70 bg-background/88 p-2 shadow-2xl backdrop-blur-xl supports-[backdrop-filter]:bg-background/70">
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

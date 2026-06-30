<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    LoaderCircle,
    MessageSquareMore,
    Search,
    SendHorizontal,
    Users,
} from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { store as storeMessage } from '@/actions/App/Http/Controllers/ChatMessageController';
import {
    index,
    startDirect,
} from '@/actions/App/Http/Controllers/ChatSidebarController';
import ChatEmojiPicker from '@/components/ChatEmojiPicker.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { useInitials } from '@/composables/useInitials';
import { useLanguage } from '@/composables/useLanguage';
import { fetchSameOriginJson } from '@/lib/sameOriginJson';
import type {
    ChatActiveConversation,
    ChatCenter,
    ChatConversationListItem,
    ChatUserSummary,
} from '@/types/ui';

type PanelMode = 'chats' | 'search';

type Props = {
    active?: boolean;
    mode: PanelMode;
    initialConversationId?: number | null;
    initialContactId?: number | null;
};

const props = withDefaults(defineProps<Props>(), {
    active: true,
    initialConversationId: null,
    initialContactId: null,
});

const page = usePage();
const { getInitials } = useInitials();
const { language, t } = useLanguage();
const payload = ref<ChatCenter | null>(null);
const loading = ref(false);
const sending = ref(false);
const loadError = ref<string | null>(null);
const search = ref('');
const draft = ref('');
const activeConversationId = ref<number | null>(null);
const searchInput = ref<HTMLInputElement | null>(null);
const draftTextarea = ref<HTMLTextAreaElement | null>(null);
const messagesContainer = ref<HTMLElement | null>(null);
let pollInterval: ReturnType<typeof setInterval> | null = null;
let searchTimeout: ReturnType<typeof setTimeout> | null = null;

const conversations = computed<ChatConversationListItem[]>(() => {
    return payload.value?.conversations ?? [];
});

const contacts = computed<ChatUserSummary[]>(() => {
    return payload.value?.contacts ?? [];
});

const activeConversation = computed<ChatActiveConversation | null>(() => {
    return payload.value?.activeConversation ?? null;
});

const panelTitle = computed(() => {
    return props.mode === 'search'
        ? t.value.chat.search_title
        : t.value.chat.title;
});

const panelDescription = computed(() => {
    return props.mode === 'search'
        ? t.value.chat.search_description
        : t.value.chat.description;
});

const syncSharedUnread = (unreadCount: number): void => {
    (page.props as Record<string, unknown>).chat = {
        unreadCount,
    };
};

const scrollMessagesToBottom = async (): Promise<void> => {
    await nextTick();

    if (!messagesContainer.value) {
        return;
    }

    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
};

const focusSearch = async (): Promise<void> => {
    await nextTick();
    searchInput.value?.focus();
};

const insertEmoji = async (emoji: string): Promise<void> => {
    const textarea = draftTextarea.value;
    const selectionStart = textarea?.selectionStart ?? draft.value.length;
    const selectionEnd = textarea?.selectionEnd ?? draft.value.length;

    draft.value = `${draft.value.slice(0, selectionStart)}${emoji}${draft.value.slice(selectionEnd)}`;

    await nextTick();

    textarea?.focus();

    const nextSelection = selectionStart + emoji.length;

    textarea?.setSelectionRange(nextSelection, nextSelection);
};

const fetchSidebar = async (): Promise<void> => {
    loading.value = payload.value === null;
    loadError.value = null;

    try {
        const data = await fetchSameOriginJson<ChatCenter>(
            index.url({
                query: {
                    search: search.value || undefined,
                    conversation: activeConversationId.value ?? undefined,
                },
            }),
            {
                method: 'GET',
            },
        );

        payload.value = data;
        activeConversationId.value =
            data.activeConversation?.id ?? activeConversationId.value;
        syncSharedUnread(data.unreadCount);
        await scrollMessagesToBottom();
    } catch (error) {
        console.error(error);
        loadError.value = t.value.common.error;
    } finally {
        loading.value = false;
    }
};

const openConversation = async (conversationId: number): Promise<void> => {
    activeConversationId.value = conversationId;
    await fetchSidebar();
};

const startConversation = async (user: ChatUserSummary): Promise<void> => {
    await startConversationById(user.id);
};

const startConversationById = async (userId: number): Promise<void> => {
    sending.value = true;

    try {
        const data = await fetchSameOriginJson<{ conversationId: number }>(
            startDirect.url(),
            {
                method: 'POST',
                body: JSON.stringify({ user_id: userId }),
            },
        );

        activeConversationId.value = data.conversationId;
        await fetchSidebar();
    } catch (error) {
        console.error(error);
        loadError.value = t.value.common.error;
    } finally {
        sending.value = false;
    }
};

const sendMessage = async (): Promise<void> => {
    if (
        !activeConversationId.value ||
        draft.value.trim() === '' ||
        sending.value
    ) {
        return;
    }

    sending.value = true;
    loadError.value = null;

    try {
        await fetchSameOriginJson(
            storeMessage.url(activeConversationId.value),
            {
                method: 'POST',
                body: JSON.stringify({ body: draft.value }),
            },
        );

        draft.value = '';
        await fetchSidebar();
    } catch (error) {
        console.error(error);
        loadError.value = t.value.common.error;
    } finally {
        sending.value = false;
    }
};

const handleDraftKeydown = async (event: KeyboardEvent): Promise<void> => {
    if (event.key !== 'Enter' || event.shiftKey) {
        return;
    }

    event.preventDefault();
    await sendMessage();
};

const formatDateTime = (value: string | null, short = false): string => {
    if (!value) {
        return '';
    }

    const locale = language.value === 'ru' ? 'ru-RU' : 'en-US';

    return short
        ? new Intl.DateTimeFormat(locale, {
              hour: '2-digit',
              minute: '2-digit',
          }).format(new Date(value))
        : new Intl.DateTimeFormat(locale, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(value));
};

const avatarStyle = (
    user: ChatUserSummary | null | undefined,
): Record<string, string> => {
    return {
        objectPosition: 'center',
        transform: `scale(${user?.avatarScale ?? 1})`,
    };
};

const loadRequestedConversation = async (): Promise<void> => {
    if (props.initialConversationId !== null) {
        activeConversationId.value = props.initialConversationId;
        await fetchSidebar();

        return;
    }

    if (props.initialContactId !== null) {
        await startConversationById(props.initialContactId);

        return;
    }

    await fetchSidebar();
};

const startPolling = (): void => {
    if (pollInterval || !props.active) {
        return;
    }

    pollInterval = setInterval(() => {
        if (!props.active) {
            return;
        }

        void fetchSidebar();
    }, 5000);
};

const stopPolling = (): void => {
    if (!pollInterval) {
        return;
    }

    clearInterval(pollInterval);
    pollInterval = null;
};

watch(
    () => props.active,
    async (isActive) => {
        if (!isActive) {
            stopPolling();

            return;
        }

        await loadRequestedConversation();
        startPolling();

        if (props.mode === 'search') {
            await focusSearch();
        }
    },
    { immediate: true },
);

watch(
    () => props.mode,
    async (mode) => {
        if (props.active && mode === 'search') {
            await focusSearch();
        }
    },
);

watch(
    () => [props.initialConversationId, props.initialContactId] as const,
    async (
        [conversationId, contactId],
        [previousConversationId, previousContactId],
    ) => {
        if (!props.active) {
            return;
        }

        if (
            conversationId === previousConversationId &&
            contactId === previousContactId
        ) {
            return;
        }

        await loadRequestedConversation();
    },
);

watch(search, () => {
    if (!props.active) {
        return;
    }

    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }

    searchTimeout = setTimeout(() => {
        void fetchSidebar();
    }, 250);
});

onBeforeUnmount(() => {
    stopPolling();

    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
});
</script>

<template>
    <div class="flex h-full min-h-0 flex-col lg:flex-row">
        <div
            class="flex min-h-0 w-full flex-col border-b border-border bg-muted/20 lg:w-[24rem] lg:border-r lg:border-b-0"
        >
            <div class="border-b border-border px-5 py-5 text-left">
                <h2 class="text-base font-semibold text-foreground">
                    {{ panelTitle }}
                </h2>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ panelDescription }}
                </p>
            </div>

            <div class="border-b border-border px-5 py-4">
                <div class="relative">
                    <Search
                        class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                    />
                    <input
                        ref="searchInput"
                        v-model="search"
                        class="h-10 w-full rounded-md border border-input bg-transparent pr-3 pl-9 text-sm shadow-xs transition-[color,box-shadow] outline-none placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        :placeholder="t.chat.search_placeholder"
                    />
                </div>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto px-3 py-4">
                <div class="space-y-6">
                    <section class="space-y-3">
                        <div class="flex items-center justify-between px-2">
                            <h3
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                {{ t.chat.recent_chats }}
                            </h3>
                            <span
                                v-if="page.props.chat.unreadCount > 0"
                                class="rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-medium text-primary"
                            >
                                {{ page.props.chat.unreadCount }}
                            </span>
                        </div>

                        <div
                            v-if="loading && conversations.length === 0"
                            class="px-2 text-sm text-muted-foreground"
                        >
                            {{ t.chat.loading }}
                        </div>

                        <div
                            v-else-if="conversations.length === 0"
                            class="rounded-2xl border border-dashed border-border bg-background/80 p-4 text-sm text-muted-foreground"
                        >
                            {{ t.chat.no_conversations }}
                        </div>

                        <div v-else class="space-y-1.5">
                            <button
                                v-for="conversation in conversations"
                                :key="conversation.id"
                                type="button"
                                class="flex w-full items-start gap-3 rounded-2xl border px-3 py-3 text-left transition hover:border-primary/40 hover:bg-background"
                                :class="
                                    conversation.id === activeConversationId
                                        ? 'border-primary/40 bg-background shadow-sm'
                                        : 'border-transparent'
                                "
                                @click="openConversation(conversation.id)"
                            >
                                <Avatar
                                    class="mt-0.5 size-10 rounded-2xl border border-border"
                                >
                                    <AvatarImage
                                        v-if="conversation.participant?.avatar"
                                        :src="conversation.participant.avatar"
                                        :alt="conversation.title"
                                        :style="
                                            avatarStyle(
                                                conversation.participant,
                                            )
                                        "
                                    />
                                    <AvatarFallback
                                        class="rounded-2xl bg-muted font-semibold text-foreground"
                                    >
                                        {{ getInitials(conversation.title) }}
                                    </AvatarFallback>
                                </Avatar>

                                <div class="min-w-0 flex-1 space-y-1">
                                    <div
                                        class="flex items-start justify-between gap-2"
                                    >
                                        <div class="min-w-0">
                                            <div
                                                class="truncate text-sm font-semibold text-foreground"
                                            >
                                                {{ conversation.title }}
                                            </div>
                                            <div
                                                class="truncate text-xs text-muted-foreground"
                                            >
                                                {{ conversation.subtitle }}
                                            </div>
                                        </div>

                                        <div
                                            class="flex shrink-0 flex-col items-end gap-1"
                                        >
                                            <span
                                                class="text-[11px] text-muted-foreground"
                                            >
                                                {{
                                                    formatDateTime(
                                                        conversation.lastMessageAt,
                                                        true,
                                                    )
                                                }}
                                            </span>
                                            <span
                                                v-if="
                                                    conversation.unreadCount > 0
                                                "
                                                class="inline-flex min-h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-[11px] font-semibold text-primary-foreground"
                                            >
                                                {{ conversation.unreadCount }}
                                            </span>
                                        </div>
                                    </div>

                                    <p
                                        class="line-clamp-2 text-xs text-muted-foreground"
                                    >
                                        {{
                                            conversation.excerpt ??
                                            t.chat.open_chat
                                        }}
                                    </p>
                                </div>
                            </button>
                        </div>
                    </section>

                    <section class="space-y-3">
                        <div class="flex items-center gap-2 px-2">
                            <Users class="size-4 text-muted-foreground" />
                            <h3
                                class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                            >
                                {{ t.chat.contacts }}
                            </h3>
                        </div>

                        <div
                            v-if="contacts.length === 0"
                            class="rounded-2xl border border-dashed border-border bg-background/80 p-4 text-sm text-muted-foreground"
                        >
                            {{ t.chat.no_contacts }}
                        </div>

                        <div v-else class="space-y-1.5">
                            <div
                                v-for="contact in contacts"
                                :key="contact.id"
                                class="flex items-center gap-3 rounded-2xl border border-transparent px-3 py-3 transition hover:border-primary/30 hover:bg-background"
                            >
                                <Avatar
                                    class="size-10 rounded-2xl border border-border"
                                >
                                    <AvatarImage
                                        v-if="contact.avatar"
                                        :src="contact.avatar"
                                        :alt="contact.name"
                                        :style="avatarStyle(contact)"
                                    />
                                    <AvatarFallback
                                        class="rounded-2xl bg-muted font-semibold text-foreground"
                                    >
                                        {{ getInitials(contact.name) }}
                                    </AvatarFallback>
                                </Avatar>

                                <div class="min-w-0 flex-1">
                                    <div
                                        class="truncate text-sm font-medium text-foreground"
                                    >
                                        {{ contact.name }}
                                    </div>
                                    <div
                                        class="truncate text-xs text-muted-foreground"
                                    >
                                        {{ contact.email }}
                                    </div>
                                </div>

                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    :disabled="sending"
                                    @click="startConversation(contact)"
                                >
                                    {{ t.chat.start_chat }}
                                </Button>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <div class="flex min-h-0 flex-1 flex-col bg-background">
            <div v-if="activeConversation" class="flex min-h-0 flex-1 flex-col">
                <div class="border-b border-border px-6 py-5">
                    <div class="flex items-center gap-3">
                        <Avatar
                            class="size-11 rounded-2xl border border-border"
                        >
                            <AvatarImage
                                v-if="activeConversation.participant?.avatar"
                                :src="activeConversation.participant.avatar"
                                :alt="activeConversation.title"
                                :style="
                                    avatarStyle(activeConversation.participant)
                                "
                            />
                            <AvatarFallback
                                class="rounded-2xl bg-muted font-semibold text-foreground"
                            >
                                {{ getInitials(activeConversation.title) }}
                            </AvatarFallback>
                        </Avatar>

                        <div class="min-w-0">
                            <div
                                class="truncate text-base font-semibold text-foreground"
                            >
                                {{ activeConversation.title }}
                            </div>
                            <div class="truncate text-sm text-muted-foreground">
                                {{ activeConversation.subtitle }}
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    ref="messagesContainer"
                    class="min-h-0 flex-1 space-y-4 overflow-y-auto px-6 py-6"
                >
                    <div
                        v-for="message in activeConversation.messages"
                        :key="message.id"
                        class="flex"
                        :class="message.isOwn ? 'justify-end' : 'justify-start'"
                    >
                        <div class="max-w-[80%] space-y-2">
                            <div
                                class="rounded-3xl px-4 py-3 text-sm break-words whitespace-pre-wrap shadow-sm"
                                :class="
                                    message.isOwn
                                        ? 'bg-primary text-primary-foreground'
                                        : 'border border-border bg-muted/35 text-foreground'
                                "
                            >
                                {{ message.body }}
                            </div>
                            <div
                                class="px-1 text-[11px] text-muted-foreground"
                                :class="
                                    message.isOwn ? 'text-right' : 'text-left'
                                "
                            >
                                {{
                                    message.isOwn
                                        ? t.chat.you
                                        : message.user.name
                                }}
                                ·
                                {{ formatDateTime(message.createdAt, true) }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t border-border px-6 py-5">
                    <div v-if="loadError" class="mb-3 text-sm text-destructive">
                        {{ loadError }}
                    </div>

                    <div class="relative">
                        <textarea
                            ref="draftTextarea"
                            v-model="draft"
                            rows="3"
                            class="min-h-24 w-full resize-none rounded-3xl border border-input bg-transparent px-4 py-3 pr-28 text-sm transition outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/50"
                            :placeholder="t.chat.message_placeholder"
                            @keydown="handleDraftKeydown"
                        ></textarea>

                        <ChatEmojiPicker
                            :disabled="sending"
                            @select="insertEmoji"
                        />

                        <Button
                            type="button"
                            size="icon"
                            class="absolute right-3 bottom-3 size-10 rounded-full"
                            :title="sending ? t.chat.sending : t.chat.send"
                            :aria-label="sending ? t.chat.sending : t.chat.send"
                            :disabled="sending || draft.trim() === ''"
                            @click="sendMessage"
                        >
                            <LoaderCircle
                                v-if="sending"
                                class="size-4 animate-spin"
                            />
                            <SendHorizontal v-else class="size-4" />
                        </Button>
                    </div>
                </div>
            </div>

            <div
                v-else
                class="flex flex-1 items-center justify-center px-6 py-12"
            >
                <div
                    class="max-w-md rounded-3xl border border-dashed border-border bg-muted/20 p-8 text-center"
                >
                    <div
                        class="mx-auto mb-4 flex size-14 items-center justify-center rounded-2xl bg-background shadow-sm"
                    >
                        <MessageSquareMore
                            class="size-6 text-muted-foreground"
                        />
                    </div>
                    <h3 class="text-lg font-semibold text-foreground">
                        {{ t.chat.empty_state_title }}
                    </h3>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t.chat.empty_state_description }}
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

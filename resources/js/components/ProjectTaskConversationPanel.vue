<script setup lang="ts">
import {
    MessageSquareMore,
    Pencil,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import {
    destroy as destroyMessage,
    store as storeMessage,
    update as updateMessage,
} from '@/actions/App/Http/Controllers/ChatMessageController';
import { show as showTaskConversation } from '@/actions/App/Http/Controllers/ProjectTaskConversationController';
import ChatMessageAttachments from '@/components/ChatMessageAttachments.vue';
import ChatMessageComposer from '@/components/ChatMessageComposer.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/composables/useInitials';
import { useLanguage } from '@/composables/useLanguage';
import { fetchSameOriginJson } from '@/lib/sameOriginJson';
import type { ChatActiveConversation, ChatUserSummary } from '@/types/ui';

type Props = {
    taskId: number;
    active?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    active: true,
});

const { getInitials } = useInitials();
const { language, t } = useLanguage();
const conversation = ref<ChatActiveConversation | null>(null);
const loading = ref(false);
const sending = ref(false);
const loadError = ref<string | null>(null);
const draft = ref('');
const selectedAttachments = ref<File[]>([]);
const editingMessageId = ref<number | null>(null);
const messagesContainer = ref<HTMLElement | null>(null);
let pollInterval: ReturnType<typeof setInterval> | null = null;

const participants = computed<ChatUserSummary[]>(() => {
    return conversation.value?.participants ?? [];
});

const conversationId = computed<number | null>(() => {
    return conversation.value?.id ?? null;
});

const hasMessages = computed<boolean>(() => {
    return (conversation.value?.messages.length ?? 0) > 0;
});

const isEditingMessage = computed<boolean>(() => {
    return editingMessageId.value !== null;
});

const editingMessage = computed(() => {
    return (
        conversation.value?.messages.find(
            (message) => message.id === editingMessageId.value,
        ) ?? null
    );
});

const avatarStyle = (
    user: ChatUserSummary | null | undefined,
): Record<string, string> => {
    return {
        objectPosition: 'center',
        transform: `scale(${user?.avatarScale ?? 1})`,
    };
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

const scrollMessagesToBottom = async (): Promise<void> => {
    await nextTick();

    if (!messagesContainer.value) {
        return;
    }

    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
};

const loadConversation = async (options?: {
    suppressErrors?: boolean;
}): Promise<void> => {
    if (!props.active) {
        return;
    }

    loading.value = conversation.value === null;
    loadError.value = null;

    try {
        const response = await fetchSameOriginJson<{
            conversation: ChatActiveConversation;
        }>(showTaskConversation.url(props.taskId), {
            method: 'GET',
        });

        conversation.value = response.conversation;
        await scrollMessagesToBottom();
    } catch (error) {
        console.error(error);

        if (!options?.suppressErrors) {
            loadError.value = t.value.common.error;
        }
    } finally {
        loading.value = false;
    }
};

const cancelEditingMessage = (): void => {
    editingMessageId.value = null;
    draft.value = '';
    selectedAttachments.value = [];
};

const startEditingMessage = (message: ChatActiveConversation['messages'][number]): void => {
    if (sending.value || message.isDeleted) {
        return;
    }

    editingMessageId.value = message.id;
    draft.value = message.body;
    selectedAttachments.value = [];
};

const removeMessage = async (
    message: ChatActiveConversation['messages'][number],
): Promise<void> => {
    if (
        sending.value ||
        !conversationId.value ||
        message.isDeleted
    ) {
        return;
    }

    sending.value = true;
    loadError.value = null;

    try {
        await fetchSameOriginJson(
            destroyMessage.url([conversationId.value, message.id]),
            {
                method: 'DELETE',
            },
        );

        if (editingMessageId.value === message.id) {
            cancelEditingMessage();
        }

        await loadConversation({
            suppressErrors: true,
        });
    } catch (error) {
        console.error(error);
        loadError.value = t.value.common.error;
    } finally {
        sending.value = false;
    }
};

const submitMessage = async (): Promise<void> => {
    const canSubmitEmptyEdit =
        editingMessage.value !== null &&
        editingMessage.value.attachments.length > 0;

    if (
        !conversationId.value ||
        (
            draft.value.trim() === '' &&
            selectedAttachments.value.length === 0 &&
            !canSubmitEmptyEdit
        ) ||
        sending.value
    ) {
        return;
    }

    sending.value = true;
    loadError.value = null;

    try {
        if (editingMessageId.value !== null) {
            await fetchSameOriginJson(
                updateMessage.url([conversationId.value, editingMessageId.value]),
                {
                    method: 'PATCH',
                    body: JSON.stringify({
                        body: draft.value,
                    }),
                },
            );

            cancelEditingMessage();
            await loadConversation({
                suppressErrors: true,
            });

            return;
        }

        const formData = new FormData();

        formData.append('body', draft.value);

        selectedAttachments.value.forEach((attachment) => {
            formData.append('attachments[]', attachment);
        });

        await fetchSameOriginJson(storeMessage.url(conversationId.value), {
            method: 'POST',
            body: formData,
        });

        draft.value = '';
        selectedAttachments.value = [];
        await loadConversation({
            suppressErrors: true,
        });
    } catch (error) {
        console.error(error);
        loadError.value = t.value.common.error;
    } finally {
        sending.value = false;
    }
};

const startPolling = (): void => {
    if (pollInterval || !props.active) {
        return;
    }

    pollInterval = setInterval(() => {
        if (!props.active) {
            return;
        }

        void loadConversation();
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
    () => [props.taskId, props.active] as const,
    async ([taskId, isActive], previousValue) => {
        const [previousTaskId, previousActive] = previousValue ?? [null, false];

        if (!isActive) {
            stopPolling();

            return;
        }

        if (taskId !== previousTaskId || !previousActive) {
            cancelEditingMessage();
            conversation.value = null;
            await loadConversation();
        }

        startPolling();
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    stopPolling();
});
</script>

<template>
    <div
        class="flex h-full min-h-0 flex-col border-t border-border bg-muted/15 xl:border-t-0 xl:border-l"
    >
        <div class="border-b border-border px-5 py-5">
            <div class="flex items-center gap-2">
                <Users class="size-4 text-muted-foreground" />
                <div>
                    <h3 class="text-sm font-semibold text-foreground">
                        {{ t.projects.task_discussion }}
                    </h3>
                    <p class="text-sm text-muted-foreground">
                        {{ t.projects.task_discussion_description }}
                    </p>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-2">
                <div
                    v-for="participant in participants"
                    :key="participant.id"
                    class="inline-flex items-center gap-2 rounded-full border border-border bg-background px-2.5 py-1.5"
                >
                    <Avatar class="size-7 rounded-full border border-border">
                        <AvatarImage
                            v-if="participant.avatar"
                            :src="participant.avatar"
                            :alt="participant.name"
                            :style="avatarStyle(participant)"
                        />
                        <AvatarFallback
                            class="bg-muted text-[11px] font-semibold text-foreground"
                        >
                            {{ getInitials(participant.name) }}
                        </AvatarFallback>
                    </Avatar>
                    <span class="max-w-28 truncate text-xs text-foreground">
                        {{ participant.name }}
                    </span>
                </div>

                <span
                    v-if="participants.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ t.projects.task_discussion_no_participants }}
                </span>
            </div>
        </div>

        <div
            ref="messagesContainer"
            class="min-h-0 flex-1 overflow-y-auto px-5 py-5"
        >
            <div
                v-if="loading && !conversation"
                class="text-sm text-muted-foreground"
            >
                {{ t.chat.loading }}
            </div>

            <div
                v-else-if="!hasMessages"
                class="flex h-full min-h-64 items-center justify-center"
            >
                <div
                    class="max-w-sm rounded-3xl border border-dashed border-border bg-background/80 p-6 text-center"
                >
                    <div
                        class="mx-auto mb-4 flex size-12 items-center justify-center rounded-2xl bg-muted/30"
                    >
                        <MessageSquareMore
                            class="size-5 text-muted-foreground"
                        />
                    </div>
                    <h4 class="text-base font-semibold text-foreground">
                        {{ t.projects.task_discussion_empty_title }}
                    </h4>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ t.projects.task_discussion_empty_description }}
                    </p>
                </div>
            </div>

            <div v-else class="space-y-4">
                <div
                    v-for="message in conversation?.messages ?? []"
                    :key="message.id"
                    class="flex"
                    :class="message.isOwn ? 'justify-end' : 'justify-start'"
                >
                    <div class="max-w-[88%] space-y-2">
                        <div
                            class="rounded-3xl px-4 py-3 text-sm break-words whitespace-pre-wrap shadow-sm"
                            :class="
                                message.isDeleted
                                    ? 'border border-dashed border-border bg-muted/20 italic text-muted-foreground'
                                    : message.isOwn
                                    ? 'bg-primary text-primary-foreground'
                                    : 'border border-border bg-background text-foreground'
                            "
                        >
                            <div
                                v-if="message.body !== ''"
                                class="break-words whitespace-pre-wrap"
                            >
                                {{ message.body }}
                            </div>
                            <ChatMessageAttachments
                                :attachments="message.attachments"
                                :own="message.isOwn"
                            />
                        </div>
                        <div
                            class="px-1 text-[11px] text-muted-foreground"
                            :class="message.isOwn ? 'text-right' : 'text-left'"
                        >
                            {{
                                message.isOwn ? t.chat.you : message.user.name
                            }}
                            ·
                            {{ formatDateTime(message.createdAt, true) }}
                            <template v-if="message.isDeleted">
                                · {{ t.chat.deleted }}
                            </template>
                            <template v-else-if="message.isEdited">
                                · {{ t.chat.edited }}
                            </template>
                            <button
                                v-if="message.isOwn && !message.isDeleted"
                                type="button"
                                class="ml-2 inline-flex size-6 items-center justify-center rounded-full transition hover:bg-muted hover:text-foreground"
                                :title="t.chat.edit_message"
                                :aria-label="t.chat.edit_message"
                                :disabled="sending"
                                @click="startEditingMessage(message)"
                            >
                                <Pencil class="size-3" />
                            </button>
                            <button
                                v-if="message.isOwn && !message.isDeleted"
                                type="button"
                                class="ml-1 inline-flex size-6 items-center justify-center rounded-full transition hover:bg-muted hover:text-foreground"
                                :title="t.chat.delete_message"
                                :aria-label="t.chat.delete_message"
                                :disabled="sending"
                                @click="removeMessage(message)"
                            >
                                    <Trash2 class="size-3" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t border-border px-5 py-5">
            <div v-if="loadError" class="mb-3 text-sm text-destructive">
                {{ loadError }}
            </div>

            <ChatMessageComposer
                v-model="draft"
                v-model:attachments="selectedAttachments"
                :is-editing="isEditingMessage"
                :can-attach="!isEditingMessage"
                :allow-empty-submit="
                    isEditingMessage &&
                    (editingMessage?.attachments.length ?? 0) > 0
                "
                :sending="sending"
                :placeholder="t.projects.task_discussion_placeholder"
                @cancel-edit="cancelEditingMessage"
                @submit="submitMessage"
            />
        </div>
    </div>
</template>

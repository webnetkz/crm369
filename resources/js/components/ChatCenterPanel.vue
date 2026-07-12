<script setup lang="ts">
import { useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    MessageSquareMore,
    Pencil,
    Search,
    Trash2,
    Users,
} from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import {
    destroy as destroyMessage,
    store as storeMessage,
    update as updateMessage,
} from '@/actions/App/Http/Controllers/ChatMessageController';
import {
    index,
    showUserProfile,
    startDirect,
} from '@/actions/App/Http/Controllers/ChatSidebarController';
import { updateProfile as updateUserProfile } from '@/actions/App/Http/Controllers/Settings/UserController';
import ChatMessageAttachments from '@/components/ChatMessageAttachments.vue';
import ChatMessageComposer from '@/components/ChatMessageComposer.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import UserProfileSheet from '@/components/UserProfileSheet.vue';
import { useChatCenterPresence } from '@/composables/useChatCenterPresence';
import { useInitials } from '@/composables/useInitials';
import { useLanguage } from '@/composables/useLanguage';
import { fetchSameOriginJson } from '@/lib/sameOriginJson';
import type {
    ChatActiveConversation,
    ChatCenter,
    ChatConversationListItem,
    ChatUserSummary,
    CompanyStructureManagerOption,
    ManagedProfileSaveState,
    ManagedUserProfile,
} from '@/types/ui';

type PanelMode = 'chats' | 'search';

type Props = {
    active?: boolean;
    mode: PanelMode;
    initialConversationId?: number | null;
    initialContactId?: number | null;
};

type ManagedProfilePayload = {
    name: string;
    last_name: string;
    middle_name: string;
    email: string;
    phone: string;
    position: string;
    manager_id: number | null;
};

const props = withDefaults(defineProps<Props>(), {
    active: true,
    initialConversationId: null,
    initialContactId: null,
});

const page = usePage();
const { getInitials } = useInitials();
const { language, t } = useLanguage();
const { setChatCenterVisible } = useChatCenterPresence();
const payload = ref<ChatCenter | null>(null);
const loading = ref(false);
const sending = ref(false);
const loadError = ref<string | null>(null);
const search = ref('');
const draft = ref('');
const selectedAttachments = ref<File[]>([]);
const editingMessageId = ref<number | null>(null);
const activeConversationId = ref<number | null>(null);
const selectedProfileUser = ref<ManagedUserProfile | null>(null);
const selectedProfileCanEdit = ref(false);
const managerOptions = ref<CompanyStructureManagerOption[]>([]);
const managedProfileSnapshot = ref<ManagedProfilePayload | null>(null);
const managedProfileSaveState = ref<ManagedProfileSaveState>('idle');
const isSyncingManagedProfile = ref(false);
const searchInput = ref<HTMLInputElement | null>(null);
const messagesContainer = ref<HTMLElement | null>(null);
const defaultKazakhstanPhonePrefix = '+7';
const managedProfileForm = useForm({
    name: '',
    last_name: '',
    middle_name: '',
    email: '',
    phone: defaultKazakhstanPhonePrefix,
    position: '',
    manager_id: '' as number | '',
});
let pollInterval: ReturnType<typeof setInterval> | null = null;
let searchTimeout: ReturnType<typeof setTimeout> | null = null;
let managedProfileSaveTimeout: ReturnType<typeof setTimeout> | null = null;
let profileRequestSequence = 0;
let sidebarRequestSequence = 0;
let sidebarAbortController: AbortController | null = null;

const conversations = computed<ChatConversationListItem[]>(() => {
    return payload.value?.conversations ?? [];
});

const contacts = computed<ChatUserSummary[]>(() => {
    return payload.value?.contacts ?? [];
});

const activeConversation = computed<ChatActiveConversation | null>(() => {
    return payload.value?.activeConversation ?? null;
});

const isEditingMessage = computed<boolean>(() => {
    return editingMessageId.value !== null;
});

const editingMessage = computed(() => {
    return (
        activeConversation.value?.messages.find(
            (message) => message.id === editingMessageId.value,
        ) ?? null
    );
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

const fetchSidebar = async (options?: {
    suppressErrors?: boolean;
}): Promise<void> => {
    const requestSequence = ++sidebarRequestSequence;

    sidebarAbortController?.abort();
    sidebarAbortController = new AbortController();

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
                signal: sidebarAbortController.signal,
            },
        );

        if (requestSequence !== sidebarRequestSequence) {
            return;
        }

        payload.value = data;
        activeConversationId.value =
            data.activeConversation?.id ?? activeConversationId.value;
        syncSharedUnread(data.unreadCount);
        await scrollMessagesToBottom();
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError') {
            return;
        }

        if (requestSequence !== sidebarRequestSequence) {
            return;
        }

        console.error(error);

        if (!options?.suppressErrors) {
            loadError.value = t.value.common.error;
        }
    } finally {
        if (requestSequence === sidebarRequestSequence) {
            sidebarAbortController = null;
            loading.value = false;
        }
    }
};

const openConversation = async (conversationId: number): Promise<void> => {
    cancelEditingMessage();
    activeConversationId.value = conversationId;
    await fetchSidebar();
};

const closeConversation = async (): Promise<void> => {
    cancelEditingMessage();
    activeConversationId.value = null;
    await fetchSidebar();
};

const openProfile = async (
    user: ChatUserSummary | null | undefined,
): Promise<void> => {
    if (!user) {
        return;
    }

    const requestSequence = ++profileRequestSequence;

    try {
        const response = await fetchSameOriginJson<{
            data: ManagedUserProfile;
            canEdit: boolean;
            managerOptions: CompanyStructureManagerOption[];
        }>(showUserProfile.url(user.id), {
            method: 'GET',
        });

        if (requestSequence !== profileRequestSequence) {
            return;
        }

        selectedProfileUser.value = response.data;
        selectedProfileCanEdit.value = response.canEdit;
        managerOptions.value = response.managerOptions;
        syncManagedProfileForm(response.data);
    } catch (error) {
        console.error(error);
        loadError.value = t.value.common.error;
    }
};

const openProfileById = async (userId: number): Promise<void> => {
    await openProfile({
        id: userId,
        name: '',
        email: '',
        phone: null,
        avatar: null,
        avatarScale: 1,
    });
};

const closeProfile = (): void => {
    clearManagedProfileSaveTimeout();
    profileRequestSequence += 1;
    selectedProfileUser.value = null;
    selectedProfileCanEdit.value = false;
    managerOptions.value = [];
    managedProfileSnapshot.value = null;
    managedProfileSaveState.value = 'idle';
    managedProfileForm.clearErrors();
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
        await fetchSidebar({
            suppressErrors: true,
        });
    } catch (error) {
        console.error(error);
        loadError.value = t.value.common.error;
    } finally {
        sending.value = false;
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
        !activeConversationId.value ||
        message.isDeleted
    ) {
        return;
    }

    sending.value = true;
    loadError.value = null;

    try {
        await fetchSameOriginJson(
            destroyMessage.url([activeConversationId.value, message.id]),
            {
                method: 'DELETE',
            },
        );

        if (editingMessageId.value === message.id) {
            cancelEditingMessage();
        }

        await fetchSidebar({
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
        !activeConversationId.value ||
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
                updateMessage.url([
                    activeConversationId.value,
                    editingMessageId.value,
                ]),
                {
                    method: 'PATCH',
                    body: JSON.stringify({
                        body: draft.value,
                    }),
                },
            );

            cancelEditingMessage();
            await fetchSidebar({
                suppressErrors: true,
            });

            return;
        }

        const formData = new FormData();

        formData.append('body', draft.value);

        selectedAttachments.value.forEach((attachment) => {
            formData.append('attachments[]', attachment);
        });

        await fetchSameOriginJson(
            storeMessage.url(activeConversationId.value),
            {
                method: 'POST',
                body: formData,
            },
        );

        draft.value = '';
        selectedAttachments.value = [];
        await fetchSidebar({
            suppressErrors: true,
        });
    } catch (error) {
        console.error(error);
        loadError.value = t.value.common.error;
    } finally {
        sending.value = false;
    }
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

const formatKazakhstanPhone = (value: string | null | undefined): string => {
    const digits = (value ?? '').replace(/\D/g, '');

    if (digits === '') {
        return defaultKazakhstanPhonePrefix;
    }

    let normalizedDigits = digits;

    if (normalizedDigits.startsWith('8')) {
        normalizedDigits = `7${normalizedDigits.slice(1)}`;
    } else if (!normalizedDigits.startsWith('7')) {
        normalizedDigits = `7${normalizedDigits}`;
    }

    normalizedDigits = normalizedDigits.slice(0, 11);

    const localNumber = normalizedDigits.slice(1);
    const segments = [
        localNumber.slice(0, 3),
        localNumber.slice(3, 6),
        localNumber.slice(6, 8),
        localNumber.slice(8, 10),
    ].filter(Boolean);

    return [defaultKazakhstanPhonePrefix, ...segments].join(' ').trim();
};

const avatarStyle = (
    user: ChatUserSummary | null | undefined,
): Record<string, string> => {
    return {
        objectPosition: 'center',
        transform: `scale(${user?.avatarScale ?? 1})`,
    };
};

const managedProfilePayload = (): ManagedProfilePayload => ({
    name: managedProfileForm.name,
    last_name: managedProfileForm.last_name,
    middle_name: managedProfileForm.middle_name,
    email: managedProfileForm.email,
    phone: managedProfileForm.phone,
    position: managedProfileForm.position,
    manager_id:
        managedProfileForm.manager_id === ''
            ? null
            : managedProfileForm.manager_id,
});

const clearManagedProfileSaveTimeout = (): void => {
    if (managedProfileSaveTimeout !== null) {
        clearTimeout(managedProfileSaveTimeout);
        managedProfileSaveTimeout = null;
    }
};

const syncManagedProfileForm = (user: ManagedUserProfile | null): void => {
    isSyncingManagedProfile.value = true;
    clearManagedProfileSaveTimeout();
    managedProfileForm.clearErrors();

    managedProfileForm.name = user?.name ?? '';
    managedProfileForm.last_name = user?.last_name ?? '';
    managedProfileForm.middle_name = user?.middle_name ?? '';
    managedProfileForm.email = user?.email ?? '';
    managedProfileForm.phone = formatKazakhstanPhone(user?.phone);
    managedProfileForm.position = user?.position ?? '';
    managedProfileForm.manager_id = user?.manager_id ?? '';

    managedProfileSnapshot.value = managedProfilePayload();
    managedProfileSaveState.value = 'idle';
    isSyncingManagedProfile.value = false;
};

const scheduleManagedProfileSave = (delay = 700): void => {
    clearManagedProfileSaveTimeout();

    managedProfileSaveTimeout = setTimeout(() => {
        void submitManagedProfileUpdate();
    }, delay);
};

const submitManagedProfileUpdate = async (): Promise<void> => {
    const user = selectedProfileUser.value;
    const snapshot = managedProfileSnapshot.value;

    if (!user || !snapshot || !selectedProfileCanEdit.value) {
        return;
    }

    const current = managedProfilePayload();

    if (JSON.stringify(current) === JSON.stringify(snapshot)) {
        managedProfileSaveState.value = 'idle';

        return;
    }

    if (managedProfileForm.processing) {
        scheduleManagedProfileSave(250);

        return;
    }

    managedProfileSaveState.value = 'saving';

    managedProfileForm.patch(updateUserProfile.url(user.id), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            const emailChanged = current.email !== snapshot.email;

            selectedProfileUser.value = {
                ...user,
                name: current.name,
                last_name: current.last_name === '' ? null : current.last_name,
                middle_name:
                    current.middle_name === '' ? null : current.middle_name,
                email: current.email,
                phone: current.phone,
                position: current.position === '' ? null : current.position,
                manager_id: current.manager_id,
                email_verified_at: emailChanged ? null : user.email_verified_at,
            };
            managedProfileSnapshot.value = managedProfilePayload();
            managedProfileSaveState.value = 'saved';
            void fetchSidebar();

            window.setTimeout(() => {
                if (managedProfileSaveState.value === 'saved') {
                    managedProfileSaveState.value = 'idle';
                }
            }, 1400);
        },
        onError: () => {
            managedProfileSaveState.value = 'error';
        },
        onFinish: () => {
            const latest = managedProfileSnapshot.value;

            if (
                managedProfileSaveState.value !== 'error' &&
                latest &&
                JSON.stringify(managedProfilePayload()) !==
                    JSON.stringify(latest)
            ) {
                scheduleManagedProfileSave(350);
            }
        },
    });
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
        setChatCenterVisible(isActive);

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

watch(
    () => selectedProfileUser.value?.id ?? null,
    () => {
        syncManagedProfileForm(selectedProfileUser.value);
    },
);

watch(
    () => managedProfileForm.phone,
    (value) => {
        const formatted = formatKazakhstanPhone(value);

        if (value !== formatted) {
            managedProfileForm.phone = formatted;
        }
    },
);

watch(
    () => [
        managedProfileForm.name,
        managedProfileForm.last_name,
        managedProfileForm.middle_name,
        managedProfileForm.email,
        managedProfileForm.phone,
        managedProfileForm.position,
        managedProfileForm.manager_id,
    ],
    () => {
        if (
            !selectedProfileUser.value ||
            !managedProfileSnapshot.value ||
            isSyncingManagedProfile.value ||
            !selectedProfileCanEdit.value
        ) {
            return;
        }

        if (
            JSON.stringify(managedProfilePayload()) ===
            JSON.stringify(managedProfileSnapshot.value)
        ) {
            managedProfileSaveState.value = 'idle';

            return;
        }

        scheduleManagedProfileSave();
    },
);

onBeforeUnmount(() => {
    stopPolling();
    sidebarAbortController?.abort();
    clearManagedProfileSaveTimeout();

    if (searchTimeout) {
        clearTimeout(searchTimeout);
    }
});
</script>

<template>
    <div class="flex h-full min-h-0 flex-col lg:flex-row">
        <div
            class="min-h-0 w-full flex-col border-b border-border bg-muted/20 lg:flex lg:w-[24rem] lg:border-r lg:border-b-0"
            :class="activeConversation ? 'hidden' : 'flex'"
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
                            <div
                                v-for="conversation in conversations"
                                :key="conversation.id"
                                class="flex items-start gap-3 rounded-2xl border px-3 py-3 transition hover:border-primary/40 hover:bg-background"
                                :class="
                                    conversation.id === activeConversationId
                                        ? 'border-primary/40 bg-background shadow-sm'
                                        : 'border-transparent'
                                "
                                @click="openConversation(conversation.id)"
                            >
                                <button
                                    type="button"
                                    class="mt-0.5 shrink-0"
                                    :disabled="!conversation.participant"
                                    @click="
                                        $event.stopPropagation();
                                        openProfile(conversation.participant)
                                    "
                                >
                                    <Avatar
                                        class="size-10 rounded-2xl border border-border transition hover:border-primary/40"
                                    >
                                        <AvatarImage
                                            v-if="
                                                conversation.participant?.avatar
                                            "
                                            :src="
                                                conversation.participant.avatar
                                            "
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
                                            {{
                                                getInitials(conversation.title)
                                            }}
                                        </AvatarFallback>
                                    </Avatar>
                                </button>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-start gap-2">
                                        <div class="min-w-0 flex-1 text-left">
                                            <div
                                                class="truncate text-sm font-semibold text-foreground"
                                            >
                                                {{ conversation.title }}
                                            </div>
                                            <div
                                                v-if="conversation.subtitle"
                                                class="truncate text-xs text-muted-foreground"
                                            >
                                                {{ conversation.subtitle }}
                                            </div>
                                            <span
                                                class="mt-1 line-clamp-2 block text-xs text-muted-foreground transition hover:text-foreground"
                                            >
                                                {{
                                                    conversation.excerpt ??
                                                    t.chat.open_chat
                                                }}
                                            </span>
                                        </div>

                                        <button
                                            type="button"
                                            class="flex shrink-0 flex-col items-end gap-1"
                                            @click="$event.stopPropagation()"
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
                                        </button>
                                    </div>
                                </div>
                            </div>
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
                                <button
                                    type="button"
                                    class="shrink-0"
                                    @click="openProfile(contact)"
                                >
                                    <Avatar
                                        class="size-10 rounded-2xl border border-border transition hover:border-primary/40"
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
                                </button>

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

        <div
            class="min-h-0 flex-1 flex-col bg-background lg:flex"
            :class="activeConversation ? 'flex' : 'hidden lg:flex'"
        >
            <div v-if="activeConversation" class="flex min-h-0 flex-1 flex-col">
                <div class="border-b border-border px-6 py-5">
                    <div class="flex items-center gap-3 text-left">
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            class="size-10 shrink-0 lg:hidden"
                            :title="t.common.back"
                            @click="closeConversation"
                        >
                            <ArrowLeft class="size-5" />
                        </Button>
                        <button
                            type="button"
                            class="shrink-0"
                            :disabled="!activeConversation.participant"
                            @click="openProfile(activeConversation.participant)"
                        >
                            <Avatar
                                class="size-11 rounded-2xl border border-border transition hover:border-primary/40"
                            >
                                <AvatarImage
                                    v-if="
                                        activeConversation.participant?.avatar
                                    "
                                    :src="activeConversation.participant.avatar"
                                    :alt="activeConversation.title"
                                    :style="
                                        avatarStyle(
                                            activeConversation.participant,
                                        )
                                    "
                                />
                                <AvatarFallback
                                    class="rounded-2xl bg-muted font-semibold text-foreground"
                                >
                                    {{ getInitials(activeConversation.title) }}
                                </AvatarFallback>
                            </Avatar>
                        </button>
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
                                    message.isDeleted
                                        ? 'border border-dashed border-border bg-muted/20 italic text-muted-foreground'
                                        : message.isOwn
                                        ? 'bg-primary text-primary-foreground'
                                        : 'border border-border bg-muted/35 text-foreground'
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

                <div class="border-t border-border px-6 py-5">
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
                        :placeholder="t.chat.message_placeholder"
                        @cancel-edit="cancelEditingMessage"
                        @submit="submitMessage"
                    />
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

        <UserProfileSheet
            :open="selectedProfileUser !== null"
            :user="selectedProfileUser"
            :can-edit="selectedProfileCanEdit"
            :save-state="managedProfileSaveState"
            :manager-options="managerOptions"
            v-model:form="managedProfileForm"
            @update:open="(isOpen) => !isOpen && closeProfile()"
            @open-user="openProfileById"
        />
    </div>
</template>

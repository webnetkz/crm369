import type { ComputedRef } from 'vue';
import { computed, onBeforeUnmount, ref } from 'vue';

type UseChatCenterPresenceReturn = {
    isAnyChatCenterVisible: ComputedRef<boolean>;
    setChatCenterVisible: (value: boolean) => void;
};

const activeChatCenterScopes = ref<string[]>([]);
let nextChatCenterScopeId = 0;

const createChatCenterScopeId = (): string => {
    nextChatCenterScopeId += 1;

    return `chat-center-${nextChatCenterScopeId}`;
};

export function useChatCenterPresence(
    scopeId = createChatCenterScopeId(),
): UseChatCenterPresenceReturn {
    const setChatCenterVisible = (value: boolean): void => {
        const visibleScopes = activeChatCenterScopes.value.filter(
            (activeScopeId) => activeScopeId !== scopeId,
        );

        activeChatCenterScopes.value = value
            ? [...visibleScopes, scopeId]
            : visibleScopes;
    };

    onBeforeUnmount(() => {
        setChatCenterVisible(false);
    });

    return {
        isAnyChatCenterVisible: computed(() => {
            return activeChatCenterScopes.value.length > 0;
        }),
        setChatCenterVisible,
    };
}

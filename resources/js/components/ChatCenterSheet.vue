<script setup lang="ts">
import ChatCenterPanel from '@/components/ChatCenterPanel.vue';
import { Sheet, SheetContent } from '@/components/ui/sheet';

type SheetMode = 'chats' | 'search';

type Props = {
    open: boolean;
    mode: SheetMode;
    initialConversationId?: number | null;
    initialContactId?: number | null;
};

withDefaults(defineProps<Props>(), {
    initialConversationId: null,
    initialContactId: null,
});

const emit = defineEmits<{
    (event: 'update:open', value: boolean): void;
}>();
</script>

<template>
    <Sheet :open="open" @update:open="(value) => emit('update:open', value)">
        <SheetContent side="right" class="w-full gap-0 p-0 sm:max-w-4xl xl:max-w-5xl">
            <ChatCenterPanel
                :active="open"
                :mode="mode"
                :initial-conversation-id="initialConversationId"
                :initial-contact-id="initialContactId"
            />
        </SheetContent>
    </Sheet>
</template>

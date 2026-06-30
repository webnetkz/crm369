<script setup lang="ts">
import { Head, setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import ChatCenterPanel from '@/components/ChatCenterPanel.vue';
import Heading from '@/components/Heading.vue';
import { useLanguage } from '@/composables/useLanguage';
import { index } from '@/routes/chats';

type ChatPageMode = 'chats' | 'search';

const props = defineProps<{
    mode: ChatPageMode;
    initialConversationId: number | null;
    initialContactId: number | null;
}>();

const { t } = useLanguage();

watchEffect(() => {
    setLayoutProps({
        breadcrumbs: [
            {
                title: t.value.chat.title,
                href: index(),
            },
        ],
    });
});
</script>

<template>
    <Head :title="t.chat.title" />

    <div class="space-y-6">
        <Heading
            variant="small"
            :title="t.chat.title"
            :description="t.chat.description"
        />

        <section class="h-[calc(100vh-13rem)] min-h-[38rem] overflow-hidden rounded-2xl border border-border bg-background shadow-sm">
            <ChatCenterPanel
                :active="true"
                :mode="props.mode"
                :initial-conversation-id="props.initialConversationId"
                :initial-contact-id="props.initialContactId"
            />
        </section>
    </div>
</template>

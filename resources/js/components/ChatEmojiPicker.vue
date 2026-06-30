<script setup lang="ts">
import { Smile } from '@lucide/vue';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useLanguage } from '@/composables/useLanguage';

withDefaults(
    defineProps<{
        disabled?: boolean;
    }>(),
    {
        disabled: false,
    },
);

const emit = defineEmits<{
    select: [emoji: string];
}>();

const { t } = useLanguage();
const open = ref(false);

const emojiOptions = [
    '😀',
    '😁',
    '😂',
    '😊',
    '😍',
    '😎',
    '🤔',
    '🙌',
    '👏',
    '👍',
    '👋',
    '🤝',
    '🙏',
    '💡',
    '🔥',
    '🎉',
    '🚀',
    '✅',
    '❤️',
    '⭐',
    '📌',
    '📎',
    '👀',
    '💬',
];

const selectEmoji = (emoji: string): void => {
    emit('select', emoji);
    open.value = false;
};
</script>

<template>
    <DropdownMenu v-model:open="open">
        <DropdownMenuTrigger :as-child="true">
            <Button
                type="button"
                variant="ghost"
                size="icon"
                class="absolute right-14 bottom-3 size-10 rounded-full text-muted-foreground hover:text-foreground"
                :title="t.chat.emoji_picker"
                :aria-label="t.chat.emoji_picker"
                :disabled="disabled"
            >
                <Smile class="size-4" />
            </Button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            align="end"
            side="top"
            :side-offset="8"
            class="w-72 rounded-2xl p-3"
        >
            <div class="mb-2 px-1 text-xs font-medium text-muted-foreground">
                {{ t.chat.emoji_picker_title }}
            </div>

            <div class="grid grid-cols-6 gap-1">
                <button
                    v-for="emoji in emojiOptions"
                    :key="emoji"
                    type="button"
                    class="inline-flex size-10 items-center justify-center rounded-2xl text-xl transition hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    @click="selectEmoji(emoji)"
                >
                    {{ emoji }}
                </button>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
</template>

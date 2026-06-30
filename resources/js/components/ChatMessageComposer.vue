<script setup lang="ts">
import { LoaderCircle, Paperclip, SendHorizontal, X } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import ChatEmojiPicker from '@/components/ChatEmojiPicker.vue';
import { Button } from '@/components/ui/button';
import { useLanguage } from '@/composables/useLanguage';

const props = withDefaults(
    defineProps<{
        modelValue?: string;
        attachments?: File[];
        placeholder: string;
        sending?: boolean;
    }>(),
    {
        modelValue: '',
        attachments: () => [],
        sending: false,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'update:attachments', value: File[]): void;
    (e: 'submit'): void;
}>();

const { t } = useLanguage();
const dragDepth = ref(0);
const fileInputElement = ref<HTMLInputElement | null>(null);
const textareaElement = ref<HTMLTextAreaElement | null>(null);

const canSubmit = computed(() => {
    return props.modelValue.trim() !== '' || props.attachments.length > 0;
});

const isDropActive = computed(() => dragDepth.value > 0);

const formatFileSize = (sizeBytes: number): string => {
    if (sizeBytes < 1024) {
        return `${sizeBytes} B`;
    }

    if (sizeBytes < 1024 * 1024) {
        return `${(sizeBytes / 1024).toFixed(1)} KB`;
    }

    return `${(sizeBytes / (1024 * 1024)).toFixed(1)} MB`;
};

const updateDraft = (event: Event): void => {
    emit('update:modelValue', (event.target as HTMLTextAreaElement).value);
};

const openFilePicker = (): void => {
    if (props.sending) {
        return;
    }

    fileInputElement.value?.click();
};

const addFiles = (files: FileList | File[]): void => {
    const nextFiles = Array.from(files);

    if (nextFiles.length === 0) {
        return;
    }

    const uniqueFiles = [
        ...props.attachments,
        ...nextFiles.filter((candidate) => {
            return !props.attachments.some((existing) => {
                return (
                    existing.name === candidate.name &&
                    existing.size === candidate.size &&
                    existing.lastModified === candidate.lastModified
                );
            });
        }),
    ];

    emit('update:attachments', uniqueFiles);
};

const handleFileChange = (event: Event): void => {
    const input = event.target as HTMLInputElement | null;
    addFiles(input?.files ?? []);

    if (input) {
        input.value = '';
    }
};

const removeAttachment = (fileIndex: number): void => {
    emit(
        'update:attachments',
        props.attachments.filter((_, index) => index !== fileIndex),
    );
};

const insertEmoji = async (emoji: string): Promise<void> => {
    const textarea = textareaElement.value;
    const selectionStart = textarea?.selectionStart ?? props.modelValue.length;
    const selectionEnd = textarea?.selectionEnd ?? props.modelValue.length;
    const nextValue = `${props.modelValue.slice(0, selectionStart)}${emoji}${props.modelValue.slice(selectionEnd)}`;

    emit('update:modelValue', nextValue);

    await nextTick();

    textarea?.focus();

    const nextSelection = selectionStart + emoji.length;

    textarea?.setSelectionRange(nextSelection, nextSelection);
};

const handleDraftKeydown = (event: KeyboardEvent): void => {
    if (event.key !== 'Enter' || event.shiftKey || props.sending || !canSubmit.value) {
        return;
    }

    event.preventDefault();
    emit('submit');
};

const handleDragEnter = (event: DragEvent): void => {
    if (props.sending || !event.dataTransfer?.types.includes('Files')) {
        return;
    }

    dragDepth.value += 1;
};

const handleDragOver = (event: DragEvent): void => {
    if (props.sending) {
        return;
    }

    event.preventDefault();

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'copy';
    }
};

const handleDragLeave = (event: DragEvent): void => {
    if (props.sending || !event.dataTransfer?.types.includes('Files')) {
        return;
    }

    dragDepth.value = Math.max(0, dragDepth.value - 1);
};

const handleDrop = (event: DragEvent): void => {
    event.preventDefault();
    dragDepth.value = 0;

    if (props.sending) {
        return;
    }

    addFiles(event.dataTransfer?.files ?? []);
};

const submit = (): void => {
    if (props.sending || !canSubmit.value) {
        return;
    }

    emit('submit');
};
</script>

<template>
    <div
        class="relative rounded-3xl border border-input bg-background transition"
        :class="isDropActive ? 'border-primary/60 ring-4 ring-primary/10' : ''"
        @dragenter="handleDragEnter"
        @dragover="handleDragOver"
        @dragleave="handleDragLeave"
        @drop="handleDrop"
    >
        <div v-if="attachments.length > 0" class="border-b border-border/70 px-4 py-3">
            <div class="mb-2 text-xs font-medium text-muted-foreground">
                {{ t.chat.attached_files }}
            </div>
            <div class="flex flex-wrap gap-2">
                <div
                    v-for="(attachment, index) in attachments"
                    :key="`${attachment.name}-${attachment.size}-${attachment.lastModified}`"
                    class="inline-flex max-w-full items-center gap-2 rounded-2xl border border-border bg-muted/50 px-3 py-2"
                >
                    <Paperclip class="size-4 shrink-0 text-muted-foreground" />
                    <span class="min-w-0">
                        <span class="block truncate text-sm font-medium text-foreground">
                            {{ attachment.name }}
                        </span>
                        <span class="block text-xs text-muted-foreground">
                            {{ formatFileSize(attachment.size) }}
                        </span>
                    </span>
                    <button
                        type="button"
                        class="inline-flex size-7 shrink-0 items-center justify-center rounded-full text-muted-foreground transition hover:bg-background hover:text-foreground"
                        :title="t.chat.remove_attachment"
                        :aria-label="t.chat.remove_attachment"
                        @click="removeAttachment(index)"
                    >
                        <X class="size-4" />
                    </button>
                </div>
            </div>
        </div>

        <textarea
            ref="textareaElement"
            :value="modelValue"
            rows="3"
            class="min-h-24 w-full resize-none rounded-3xl bg-transparent px-4 py-3 pl-14 pr-28 text-sm transition outline-none focus:ring-0"
            :placeholder="placeholder"
            @input="updateDraft"
            @keydown="handleDraftKeydown"
        ></textarea>

        <input
            ref="fileInputElement"
            type="file"
            class="sr-only"
            multiple
            :disabled="sending"
            @change="handleFileChange"
        />

        <Button
            type="button"
            variant="ghost"
            size="icon"
            class="absolute left-3 bottom-3 size-10 rounded-full text-muted-foreground hover:text-foreground"
            :title="t.chat.attach_files"
            :aria-label="t.chat.attach_files"
            :disabled="sending"
            @click="openFilePicker"
        >
            <Paperclip class="size-4" />
        </Button>

        <ChatEmojiPicker :disabled="sending" @select="insertEmoji" />

        <Button
            type="button"
            size="icon"
            class="absolute right-3 bottom-3 size-10 rounded-full"
            :title="sending ? t.chat.sending : t.chat.send"
            :aria-label="sending ? t.chat.sending : t.chat.send"
            :disabled="sending || !canSubmit"
            @click="submit"
        >
            <LoaderCircle v-if="sending" class="size-4 animate-spin" />
            <SendHorizontal v-else class="size-4" />
        </Button>

        <div
            v-if="isDropActive"
            class="pointer-events-none absolute inset-0 flex items-center justify-center rounded-3xl bg-background/85"
        >
            <div class="rounded-2xl border border-dashed border-primary/40 bg-primary/5 px-4 py-3 text-center">
                <div class="text-sm font-medium text-foreground">
                    {{ t.chat.drop_files_here }}
                </div>
            </div>
        </div>
    </div>
</template>

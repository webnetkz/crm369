<script setup lang="ts">
import { Paperclip } from '@lucide/vue';
import { computed } from 'vue';
import type { ChatAttachmentItem } from '@/types/ui';

const props = withDefaults(
    defineProps<{
        attachments: ChatAttachmentItem[];
        own?: boolean;
    }>(),
    {
        own: false,
    },
);

const attachmentClassName = computed(() => {
    return props.own
        ? 'border-primary-foreground/20 bg-primary-foreground/10 text-primary-foreground hover:bg-primary-foreground/15'
        : 'border-border/70 bg-background/80 text-foreground hover:bg-muted/70';
});

const imageClassName = computed(() => {
    return props.own
        ? 'border-primary-foreground/20'
        : 'border-border/70';
});

const formatFileSize = (sizeBytes: number): string => {
    if (sizeBytes < 1024) {
        return `${sizeBytes} B`;
    }

    if (sizeBytes < 1024 * 1024) {
        return `${(sizeBytes / 1024).toFixed(1)} KB`;
    }

    return `${(sizeBytes / (1024 * 1024)).toFixed(1)} MB`;
};
</script>

<template>
    <div v-if="attachments.length > 0" class="space-y-2">
        <template v-for="attachment in attachments" :key="attachment.id">
            <a
                v-if="attachment.previewUrl"
                :href="attachment.downloadUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="block overflow-hidden rounded-2xl border transition"
                :class="imageClassName"
            >
                <img
                    :src="attachment.previewUrl"
                    :alt="attachment.name"
                    class="max-h-80 w-full bg-black/5 object-cover"
                    loading="lazy"
                />
                <span
                    class="flex items-center justify-between gap-3 px-3 py-2 text-xs"
                    :class="attachmentClassName"
                >
                    <span class="min-w-0 truncate font-medium">
                        {{ attachment.name }}
                    </span>
                    <span class="shrink-0 opacity-80">
                        {{ formatFileSize(attachment.sizeBytes) }}
                    </span>
                </span>
            </a>

            <a
                v-else
                :href="attachment.downloadUrl"
                target="_blank"
                rel="noopener noreferrer"
                class="flex items-center gap-3 rounded-2xl border px-3 py-2 transition"
                :class="attachmentClassName"
            >
                <span
                    class="flex size-8 shrink-0 items-center justify-center rounded-xl bg-black/10"
                >
                    <Paperclip class="size-4" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-sm font-medium">
                        {{ attachment.name }}
                    </span>
                    <span class="block text-xs opacity-80">
                        {{ formatFileSize(attachment.sizeBytes) }}
                    </span>
                </span>
            </a>
        </template>
    </div>
</template>

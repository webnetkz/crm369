<script setup lang="ts">
import type { KnowledgeBaseBlock } from '@/types/ui';

const props = defineProps<{
    blocks: KnowledgeBaseBlock[];
}>();

const headingTag = (level?: number | null): 'h1' | 'h2' | 'h3' => {
    if (level === 1) {
        return 'h1';
    }

    if (level === 3) {
        return 'h3';
    }

    return 'h2';
};
</script>

<template>
    <div class="space-y-6">
        <template
            v-for="(block, index) in props.blocks"
            :key="`${block.type}-${index}`"
        >
            <p
                v-if="block.type === 'paragraph'"
                class="text-sm leading-7 text-foreground/90 [&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4 [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_code]:py-0.5 [&_code]:font-mono [&_strong]:font-semibold"
                v-html="block.content ?? ''"
            ></p>

            <component
                :is="headingTag(block.heading_level)"
                v-else-if="block.type === 'heading'"
                class="font-semibold tracking-tight text-foreground [&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4 [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_code]:py-0.5 [&_code]:font-mono"
                :class="
                    block.heading_level === 1
                        ? 'text-3xl'
                        : block.heading_level === 3
                          ? 'text-lg'
                          : 'text-2xl'
                "
            >
                <span v-html="block.content ?? ''"></span>
            </component>

            <ol
                v-else-if="block.type === 'list' && block.ordered"
                class="list-decimal space-y-2 pl-5 text-sm leading-7 text-foreground/90"
            >
                <li v-for="(item, itemIndex) in block.items" :key="itemIndex">
                    <span
                        class="[&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4 [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_code]:py-0.5 [&_code]:font-mono [&_strong]:font-semibold"
                        v-html="item"
                    ></span>
                </li>
            </ol>

            <ul
                v-else-if="block.type === 'list'"
                class="list-disc space-y-2 pl-5 text-sm leading-7 text-foreground/90"
            >
                <li v-for="(item, itemIndex) in block.items" :key="itemIndex">
                    <span
                        class="[&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4 [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_code]:py-0.5 [&_code]:font-mono [&_strong]:font-semibold"
                        v-html="item"
                    ></span>
                </li>
            </ul>

            <figure v-else-if="block.type === 'image'" class="space-y-3">
                <img
                    v-if="block.image_url"
                    :src="block.image_url"
                    :alt="block.caption ?? ''"
                    class="w-full rounded-3xl border border-border bg-white object-cover shadow-sm"
                />
                <figcaption
                    v-if="block.caption"
                    class="text-sm text-muted-foreground"
                >
                    {{ block.caption }}
                </figcaption>
            </figure>
        </template>
    </div>
</template>

<script setup lang="ts">
import {
    Bold,
    Eraser,
    GripHorizontal,
    ImagePlus,
    Italic,
    Link2,
    List,
    ListOrdered,
    Pilcrow,
    Plus,
    Strikethrough,
    Trash2,
    Type,
    Underline,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import RichTextEditable from '@/components/knowledge/RichTextEditable.vue';
import LocalizedFilePicker from '@/components/LocalizedFilePicker.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useLanguage } from '@/composables/useLanguage';
import type { KnowledgeBaseBlock } from '@/types/ui';

const props = defineProps<{
    modelValue: KnowledgeBaseBlock[];
    errors: Record<string, string>;
}>();

const emit = defineEmits<{
    (event: 'update:modelValue', value: KnowledgeBaseBlock[]): void;
}>();

const { t } = useLanguage();
const activeEditable = ref<HTMLElement | null>(null);

const blocks = computed({
    get: () => props.modelValue,
    set: (value: KnowledgeBaseBlock[]) => emit('update:modelValue', value),
});

const updateBlock = (
    index: number,
    patch: Partial<KnowledgeBaseBlock>,
): void => {
    blocks.value = blocks.value.map((block, blockIndex) =>
        blockIndex === index ? { ...block, ...patch } : block,
    );
};

const removeBlock = (index: number): void => {
    blocks.value = blocks.value.filter((_, blockIndex) => blockIndex !== index);
};

const addBlock = (type: KnowledgeBaseBlock['type']): void => {
    if (type === 'heading') {
        blocks.value = [
            ...blocks.value,
            { type, content: '', heading_level: 2 },
        ];

        return;
    }

    if (type === 'list') {
        blocks.value = [...blocks.value, { type, items: [''], ordered: false }];

        return;
    }

    if (type === 'image') {
        blocks.value = [
            ...blocks.value,
            {
                type,
                image_file: null,
                image_path: null,
                image_url: null,
                caption: '',
            },
        ];

        return;
    }

    blocks.value = [...blocks.value, { type, content: '' }];
};

const updateListItem = (
    blockIndex: number,
    itemIndex: number,
    value: string,
): void => {
    blocks.value = blocks.value.map((block, currentBlockIndex) => {
        if (currentBlockIndex !== blockIndex) {
            return block;
        }

        const items = [...(block.items ?? [])];
        items[itemIndex] = value;

        return {
            ...block,
            items,
        };
    });
};

const addListItem = (blockIndex: number): void => {
    blocks.value = blocks.value.map((block, currentBlockIndex) => {
        if (currentBlockIndex !== blockIndex) {
            return block;
        }

        return {
            ...block,
            items: [...(block.items ?? []), ''],
        };
    });
};

const removeListItem = (blockIndex: number, itemIndex: number): void => {
    blocks.value = blocks.value.map((block, currentBlockIndex) => {
        if (currentBlockIndex !== blockIndex) {
            return block;
        }

        return {
            ...block,
            items: (block.items ?? []).filter(
                (_, currentItemIndex) => currentItemIndex !== itemIndex,
            ),
        };
    });
};

const blockError = (index: number, field: string): string | undefined => {
    return props.errors[`blocks.${index}.${field}`];
};

const setActiveEditable = (value: HTMLElement | null): void => {
    activeEditable.value = value;
};

const dispatchEditorInput = (): void => {
    activeEditable.value?.dispatchEvent(new Event('input', { bubbles: true }));
};

const applyInlineCommand = (command: string, value?: string): void => {
    if (!activeEditable.value) {
        return;
    }

    activeEditable.value.focus();
    document.execCommand(command, false, value);
    dispatchEditorInput();
};

const insertLink = (): void => {
    const url = window.prompt(t.value.knowledge.link_prompt, 'https://');

    if (url === null) {
        return;
    }

    if (url.trim() === '') {
        applyInlineCommand('unlink');

        return;
    }

    applyInlineCommand('createLink', url.trim());
};

const clearFormatting = (): void => {
    applyInlineCommand('removeFormat');
    applyInlineCommand('unlink');
};
</script>

<template>
    <div class="space-y-5">
        <div
            class="sticky top-4 z-10 rounded-3xl border border-border bg-background/95 p-3 shadow-sm supports-[backdrop-filter]:bg-background/80 supports-[backdrop-filter]:backdrop-blur"
        >
            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="!activeEditable"
                    :title="t.knowledge.format_bold"
                    @mousedown.prevent="applyInlineCommand('bold')"
                >
                    <Bold class="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="!activeEditable"
                    :title="t.knowledge.format_italic"
                    @mousedown.prevent="applyInlineCommand('italic')"
                >
                    <Italic class="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="!activeEditable"
                    :title="t.knowledge.format_underline"
                    @mousedown.prevent="applyInlineCommand('underline')"
                >
                    <Underline class="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="!activeEditable"
                    :title="t.knowledge.format_strikethrough"
                    @mousedown.prevent="applyInlineCommand('strikeThrough')"
                >
                    <Strikethrough class="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="!activeEditable"
                    :title="t.knowledge.format_link"
                    @mousedown.prevent="insertLink"
                >
                    <Link2 class="size-4" />
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    :disabled="!activeEditable"
                    :title="t.knowledge.clear_formatting"
                    @mousedown.prevent="clearFormatting"
                >
                    <Eraser class="size-4" />
                </Button>
            </div>

            <p class="mt-3 text-xs text-muted-foreground">
                {{ t.knowledge.editor_help }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="addBlock('paragraph')"
            >
                <Pilcrow class="size-4" />
                {{ t.knowledge.add_paragraph }}
            </Button>
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="addBlock('heading')"
            >
                <Type class="size-4" />
                {{ t.knowledge.add_heading }}
            </Button>
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="addBlock('list')"
            >
                <List class="size-4" />
                {{ t.knowledge.add_list }}
            </Button>
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="addBlock('image')"
            >
                <ImagePlus class="size-4" />
                {{ t.knowledge.add_image }}
            </Button>
        </div>

        <div class="space-y-4">
            <article
                v-for="(block, index) in blocks"
                :key="`${block.type}-${index}`"
                class="rounded-3xl border border-border bg-card p-4 shadow-sm"
            >
                <div class="mb-4 flex items-center justify-between gap-3">
                    <div
                        class="flex items-center gap-2 text-sm font-medium text-foreground"
                    >
                        <GripHorizontal class="size-4 text-muted-foreground" />
                        <span>
                            {{
                                block.type === 'paragraph'
                                    ? t.knowledge.add_paragraph
                                    : block.type === 'heading'
                                      ? t.knowledge.add_heading
                                      : block.type === 'list'
                                        ? t.knowledge.add_list
                                        : t.knowledge.add_image
                            }}
                        </span>
                    </div>

                    <Button
                        type="button"
                        variant="ghost"
                        size="icon-sm"
                        @click="removeBlock(index)"
                    >
                        <Trash2 class="size-4" />
                    </Button>
                </div>

                <div v-if="block.type === 'paragraph'" class="space-y-2">
                    <Label>{{ t.knowledge.add_paragraph }}</Label>
                    <RichTextEditable
                        :model-value="block.content ?? ''"
                        :placeholder="t.knowledge.paragraph_placeholder"
                        @focus-editor="setActiveEditable"
                        @update:model-value="
                            (value) => updateBlock(index, { content: value })
                        "
                    />
                    <InputError :message="blockError(index, 'content')" />
                </div>

                <div
                    v-else-if="block.type === 'heading'"
                    class="grid gap-4 md:grid-cols-[minmax(0,1fr)_180px]"
                >
                    <div class="space-y-2">
                        <Label>{{ t.knowledge.add_heading }}</Label>
                        <RichTextEditable
                            :model-value="block.content ?? ''"
                            class="min-h-16 text-base font-semibold tracking-tight"
                            :placeholder="t.knowledge.heading_placeholder"
                            @focus-editor="setActiveEditable"
                            @update:model-value="
                                (value) =>
                                    updateBlock(index, { content: value })
                            "
                        />
                        <InputError :message="blockError(index, 'content')" />
                    </div>

                    <div class="space-y-2">
                        <Label>{{ t.knowledge.heading_level }}</Label>
                        <select
                            class="h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            :value="String(block.heading_level ?? 2)"
                            @change="
                                updateBlock(index, {
                                    heading_level: Number(
                                        ($event.target as HTMLSelectElement)
                                            .value,
                                    ),
                                })
                            "
                        >
                            <option value="1">H1</option>
                            <option value="2">H2</option>
                            <option value="3">H3</option>
                        </select>
                    </div>
                </div>

                <div v-else-if="block.type === 'list'" class="space-y-4">
                    <div class="grid gap-4 md:grid-cols-[220px_minmax(0,1fr)]">
                        <div class="space-y-2">
                            <Label>{{ t.knowledge.list_type }}</Label>
                            <select
                                class="h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                                :value="block.ordered ? 'ordered' : 'unordered'"
                                @change="
                                    updateBlock(index, {
                                        ordered:
                                            ($event.target as HTMLSelectElement)
                                                .value === 'ordered',
                                    })
                                "
                            >
                                <option value="unordered">
                                    {{ t.knowledge.unordered_list }}
                                </option>
                                <option value="ordered">
                                    {{ t.knowledge.ordered_list }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div
                            v-for="(item, itemIndex) in block.items ?? []"
                            :key="itemIndex"
                            class="flex items-start gap-2"
                        >
                            <div class="pt-2 text-muted-foreground">
                                <ListOrdered
                                    v-if="block.ordered"
                                    class="size-4"
                                />
                                <List v-else class="size-4" />
                            </div>
                            <RichTextEditable
                                :model-value="item"
                                class="min-h-11 flex-1 rounded-xl"
                                :placeholder="t.knowledge.list_item_placeholder"
                                @focus-editor="setActiveEditable"
                                @update:model-value="
                                    (value) =>
                                        updateListItem(index, itemIndex, value)
                                "
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                @click="removeListItem(index, itemIndex)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>

                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        @click="addListItem(index)"
                    >
                        <Plus class="size-4" />
                        {{ t.knowledge.add_list_item }}
                    </Button>
                    <InputError :message="blockError(index, 'items')" />
                </div>

                <div v-else class="space-y-4">
                    <div
                        v-if="block.image_url"
                        class="overflow-hidden rounded-3xl border border-border bg-white"
                    >
                        <img
                            :src="block.image_url"
                            :alt="block.caption ?? ''"
                            class="max-h-80 w-full object-cover"
                        />
                    </div>

                    <LocalizedFilePicker
                        :id="`knowledge_block_image_${index}`"
                        accept="image/png,image/jpeg,image/jpg,image/webp"
                        :model-value="block.image_file ?? null"
                        @update:model-value="
                            (file) =>
                                updateBlock(index, {
                                    image_file: file,
                                    image_path: block.image_path ?? null,
                                    image_url: block.image_url ?? null,
                                })
                        "
                    />
                    <InputError :message="blockError(index, 'image_file')" />

                    <div class="space-y-2">
                        <Label>{{ t.knowledge.image_caption }}</Label>
                        <Input
                            :model-value="block.caption ?? ''"
                            :placeholder="t.knowledge.image_caption_placeholder"
                            @update:model-value="
                                (value) =>
                                    updateBlock(index, {
                                        caption: String(value),
                                    })
                            "
                        />
                    </div>
                </div>
            </article>
        </div>
    </div>
</template>

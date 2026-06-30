<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    modelValue: string | null | undefined;
    placeholder: string;
    class?: string;
}>();

const emit = defineEmits<{
    (event: 'update:modelValue', value: string): void;
    (event: 'focus-editor', value: HTMLElement | null): void;
}>();

const editor = ref<HTMLDivElement | null>(null);
const isFocused = ref(false);
const isEmpty = ref(true);

const editorClasses = computed(() => {
    return cn(
        'relative min-h-24 w-full rounded-2xl border border-input bg-transparent px-4 py-3 text-sm leading-6 break-words whitespace-pre-wrap text-foreground transition outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50',
        '[&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4 [&_code]:rounded [&_code]:bg-muted [&_code]:px-1 [&_code]:py-0.5 [&_code]:font-mono [&_strong]:font-semibold',
        isEmpty.value
            ? 'before:pointer-events-none before:absolute before:top-3 before:left-4 before:text-muted-foreground/70 before:content-[attr(data-placeholder)]'
            : '',
        props.class,
    );
});

const normalizeHtml = (value: string | null | undefined): string => {
    if (!value) {
        return '';
    }

    const normalized = value
        .replace(/&nbsp;/gi, ' ')
        .replace(/^(?:\s|<br\s*\/?>)+|(?:\s|<br\s*\/?>)+$/gi, '')
        .trim();

    return normalized;
};

const syncEmptyState = (): void => {
    isEmpty.value = normalizeHtml(editor.value?.innerHTML) === '';
};

const syncFromProps = (value: string | null | undefined): void => {
    if (!editor.value) {
        return;
    }

    const normalized = normalizeHtml(value);

    if (editor.value.innerHTML !== normalized) {
        editor.value.innerHTML = normalized;
    }

    syncEmptyState();
};

const emitValue = (): void => {
    if (!editor.value) {
        return;
    }

    const normalized = normalizeHtml(editor.value.innerHTML);

    if (editor.value.innerHTML !== normalized) {
        editor.value.innerHTML = normalized;
    }

    emit('update:modelValue', normalized);
    syncEmptyState();
};

const handleFocus = (): void => {
    isFocused.value = true;
    emit('focus-editor', editor.value);
    syncEmptyState();
};

const handleBlur = (): void => {
    emitValue();
    isFocused.value = false;
    emit('focus-editor', null);
};

onMounted(() => {
    syncFromProps(props.modelValue);
});

watch(
    () => props.modelValue,
    (value) => {
        if (!isFocused.value) {
            syncFromProps(value);
        }
    },
);
</script>

<template>
    <div
        ref="editor"
        contenteditable="true"
        :data-placeholder="props.placeholder"
        :class="editorClasses"
        @blur="handleBlur"
        @focus="handleFocus"
        @input="emitValue"
    ></div>
</template>

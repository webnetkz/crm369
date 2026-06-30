<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import { useLanguage } from '@/composables/useLanguage';

const props = withDefaults(
    defineProps<{
        id: string;
        name?: string;
        accept?: string;
        disabled?: boolean;
        modelValue?: File | null;
    }>(),
    {
        name: undefined,
        accept: undefined,
        disabled: false,
        modelValue: null,
    },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: File | null): void;
    (e: 'change', value: File | null): void;
}>();

const { t } = useLanguage();
const inputElement = ref<HTMLInputElement | null>(null);

const selectedFileName = computed(() => {
    return props.modelValue?.name ?? t.value.common.no_file_selected;
});

const openPicker = (): void => {
    inputElement.value?.click();
};

const handleChange = (event: Event): void => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0] ?? null;

    emit('update:modelValue', file);
    emit('change', file);
};

watch(
    () => props.modelValue,
    (value) => {
        if (value === null && inputElement.value) {
            inputElement.value.value = '';
        }
    },
);
</script>

<template>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <Button
            type="button"
            variant="outline"
            class="w-full sm:w-auto"
            :disabled="disabled"
            :aria-controls="id"
            @click="openPicker"
        >
            {{ t.common.choose_file }}
        </Button>

        <p class="min-w-0 text-sm text-muted-foreground">
            <span class="block truncate">{{ selectedFileName }}</span>
        </p>

        <input
            :id="id"
            ref="inputElement"
            :name="name"
            type="file"
            class="sr-only"
            :accept="accept"
            :disabled="disabled"
            @change="handleChange"
        />
    </div>
</template>

<script setup lang="ts">
import { useLanguage } from '@/composables/useLanguage';

const { language, t, updateLanguage } = useLanguage();

const tabs = [
    { value: 'ru', labelKey: 'russian' },
    { value: 'en', labelKey: 'english' },
] as const;
</script>

<template>
    <div
        class="inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800"
    >
        <button
            v-for="{ value, labelKey } in tabs"
            :key="value"
            type="button"
            @click="updateLanguage(value)"
            :class="[
                'flex items-center rounded-md px-3.5 py-1.5 transition-colors',
                language === value
                    ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                    : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
            ]"
        >
            <span class="text-sm">{{ t.settings[labelKey] }}</span>
        </button>
    </div>
</template>

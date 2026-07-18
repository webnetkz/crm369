<script setup lang="ts">
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        stream: MediaStream | null;
        name: string;
        avatar?: string | null;
        muted?: boolean;
        local?: boolean;
    }>(),
    {
        avatar: null,
        muted: false,
        local: false,
    },
);

const video = ref<HTMLVideoElement | null>(null);

const initials = computed(() => {
    return (
        props.name
            .split(/\s+/)
            .filter(Boolean)
            .slice(0, 2)
            .map((part) => part[0]?.toUpperCase())
            .join('') || '?'
    );
});

watch(
    [video, () => props.stream],
    ([element, stream]) => {
        if (!element) {
            return;
        }

        element.srcObject = stream;

        if (stream) {
            void element.play().catch(() => undefined);
        }
    },
    { immediate: true },
);
</script>

<template>
    <article
        class="group relative min-h-48 overflow-hidden rounded-2xl border border-white/10 bg-slate-900 shadow-lg"
    >
        <video
            v-show="stream"
            ref="video"
            autoplay
            playsinline
            :muted="muted"
            class="absolute inset-0 h-full w-full object-cover"
        ></video>

        <div
            v-if="!stream"
            class="absolute inset-0 grid place-items-center bg-linear-to-br from-slate-800 to-slate-950"
        >
            <img
                v-if="avatar"
                :src="avatar"
                :alt="name"
                class="size-24 rounded-full object-cover ring-4 ring-white/10"
            />
            <div
                v-else
                class="grid size-24 place-items-center rounded-full bg-primary/25 text-3xl font-semibold text-primary-foreground ring-4 ring-white/10"
            >
                {{ initials }}
            </div>
        </div>

        <div
            class="absolute inset-x-0 bottom-0 flex items-end justify-between gap-3 bg-linear-to-t from-black/80 to-transparent p-4 pt-12"
        >
            <div class="truncate text-sm font-medium text-white">
                {{ name }}
            </div>
        </div>
    </article>
</template>

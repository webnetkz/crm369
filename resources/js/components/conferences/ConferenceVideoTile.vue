<script setup lang="ts">
import { AudioLines, Pin, PinOff, Play } from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { useLanguage } from '@/composables/useLanguage';

const props = withDefaults(
    defineProps<{
        stream: MediaStream | null;
        name: string;
        avatar?: string | null;
        muted?: boolean;
        local?: boolean;
        speaking?: boolean;
        pinned?: boolean;
        featured?: boolean;
    }>(),
    {
        avatar: null,
        muted: false,
        local: false,
        speaking: false,
        pinned: false,
        featured: false,
    },
);

const emit = defineEmits<{
    togglePin: [];
}>();

const { t } = useLanguage();
const video = ref<HTMLVideoElement | null>(null);
const hasVideoTrack = ref(false);
const playbackBlocked = ref(false);

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

const playStream = async (): Promise<void> => {
    const element = video.value;

    if (!element || !props.stream) {
        playbackBlocked.value = false;

        return;
    }

    try {
        await element.play();
        playbackBlocked.value = false;
    } catch {
        playbackBlocked.value = !props.muted;
    }
};

watch(
    [video, () => props.stream, () => props.muted],
    ([element, stream], _previous, onCleanup) => {
        if (!element) {
            return;
        }

        element.srcObject = stream;

        if (!stream) {
            hasVideoTrack.value = false;
            playbackBlocked.value = false;

            return;
        }

        const listeners: Array<() => void> = [];
        const refreshVideoTrack = (): void => {
            hasVideoTrack.value = stream.getVideoTracks().some((track) => {
                return (
                    track.readyState === 'live' && track.enabled && !track.muted
                );
            });
        };
        const listenToTrack = (track: MediaStreamTrack): void => {
            const refresh = (): void => refreshVideoTrack();

            for (const eventName of ['ended', 'mute', 'unmute']) {
                track.addEventListener(eventName, refresh);
                listeners.push(() =>
                    track.removeEventListener(eventName, refresh),
                );
            }
        };
        const handleAddedTrack = (event: MediaStreamTrackEvent): void => {
            listenToTrack(event.track);
            refreshVideoTrack();
            void playStream();
        };

        stream.getTracks().forEach(listenToTrack);
        stream.addEventListener('addtrack', handleAddedTrack);
        stream.addEventListener('removetrack', refreshVideoTrack);
        listeners.push(
            () => stream.removeEventListener('addtrack', handleAddedTrack),
            () => stream.removeEventListener('removetrack', refreshVideoTrack),
        );

        refreshVideoTrack();
        void playStream();

        onCleanup(() => {
            listeners.forEach((removeListener) => removeListener());
        });
    },
    { immediate: true },
);
</script>

<template>
    <article
        class="group relative overflow-hidden rounded-2xl border-2 bg-slate-900 shadow-lg transition-[border-color,box-shadow]"
        :class="[
            featured ? 'min-h-[24rem] lg:min-h-full' : 'min-h-48',
            speaking
                ? 'border-emerald-400 ring-2 shadow-emerald-500/20 ring-emerald-400/35'
                : pinned
                  ? 'border-sky-400/80'
                  : 'border-white/10',
        ]"
    >
        <video
            v-show="hasVideoTrack"
            ref="video"
            autoplay
            playsinline
            :muted="muted"
            class="absolute inset-0 h-full w-full object-cover"
        ></video>

        <div
            v-if="!hasVideoTrack"
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

        <button
            v-if="playbackBlocked"
            type="button"
            class="absolute inset-0 z-10 m-auto flex h-fit w-fit items-center gap-2 rounded-full bg-black/75 px-4 py-2 text-sm font-medium text-white shadow-lg ring-1 ring-white/20 transition hover:bg-black/90 focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
            @click="playStream"
        >
            <Play class="size-4" />
            {{ t.conferences.play_media }}
        </button>

        <button
            type="button"
            class="absolute top-3 right-3 z-30 grid size-9 place-items-center rounded-full text-white shadow-lg ring-1 transition focus-visible:ring-2 focus-visible:ring-white focus-visible:outline-none"
            :class="
                pinned
                    ? 'bg-sky-500 ring-sky-300 hover:bg-sky-400'
                    : 'bg-black/65 ring-white/20 hover:bg-black/85'
            "
            :title="
                pinned
                    ? t.conferences.unpin_participant
                    : t.conferences.pin_participant
            "
            :aria-label="
                pinned
                    ? t.conferences.unpin_participant
                    : t.conferences.pin_participant
            "
            @click="emit('togglePin')"
        >
            <PinOff v-if="pinned" class="size-4" />
            <Pin v-else class="size-4" />
        </button>

        <div
            class="absolute inset-x-0 bottom-0 z-20 flex items-end justify-between gap-3 bg-linear-to-t from-black/80 to-transparent p-4 pt-12"
        >
            <div class="truncate text-sm font-medium text-white">
                {{ name }}
            </div>
            <div
                v-if="speaking"
                class="flex shrink-0 items-center gap-1 rounded-full bg-emerald-500/90 px-2 py-1 text-[0.6875rem] font-medium text-white"
            >
                <AudioLines class="size-3.5" />
                {{ t.conferences.speaking }}
            </div>
        </div>
    </article>
</template>

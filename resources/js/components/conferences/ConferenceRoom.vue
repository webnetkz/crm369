<script setup lang="ts">
import {
    LoaderCircle,
    Maximize2,
    MessageSquareText,
    Mic,
    MicOff,
    MonitorUp,
    PhoneOff,
    Send,
    Users,
    Video,
    VideoOff,
    Wifi,
    WifiOff,
    X,
} from '@lucide/vue';
import { computed, nextTick, onBeforeUnmount, ref } from 'vue';
import {
    join as joinConferenceRoom,
    leave as leaveConferenceRoom,
    message as storeConferenceMessage,
    signal as storeConferenceSignal,
    sync as syncConferenceRoom,
} from '@/actions/App/Http/Controllers/PublicConferenceRoomController';
import ConferenceVideoTile from '@/components/conferences/ConferenceVideoTile.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLanguage } from '@/composables/useLanguage';
import { fetchSameOriginJson } from '@/lib/sameOriginJson';

type RoomParticipant = {
    id: number;
    display_name: string;
    is_guest: boolean;
    joined_at: string;
    user: {
        id: number;
        avatar: string | null;
    } | null;
};

type RoomMessage = {
    id: number;
    participant_id: number | null;
    display_name: string;
    body: string;
    created_at: string;
};

type RoomSignal = {
    id: number;
    sender_participant_id: number;
    type: 'offer' | 'answer' | 'ice-candidate';
    payload: Record<string, unknown>;
};

type JoinResponse = {
    participant: RoomParticipant & { token: string };
    participants: RoomParticipant[];
    messages: RoomMessage[];
    signal_cursor: number;
    message_cursor: number;
    ice_servers: RTCIceServer[];
    poll_interval_ms: number;
};

type SyncResponse = {
    participants: RoomParticipant[];
    signals: RoomSignal[];
    messages: RoomMessage[];
    signal_cursor: number;
    message_cursor: number;
};

const props = withDefaults(
    defineProps<{
        roomKey: string;
        conferenceTitle: string;
        initialDisplayName?: string | null;
    }>(),
    {
        initialDisplayName: null,
    },
);

const { t } = useLanguage();
const roomElement = ref<HTMLElement | null>(null);
const chatMessagesElement = ref<HTMLElement | null>(null);
const guestDisplayName = ref(props.initialDisplayName ?? '');
const joined = ref(false);
const joining = ref(false);
const leaving = ref(false);
const chatOpen = ref(false);
const chatBody = ref('');
const sendingMessage = ref(false);
const roomError = ref<string | null>(null);
const mediaError = ref<string | null>(null);
const connected = ref(true);
const participants = ref<RoomParticipant[]>([]);
const messages = ref<RoomMessage[]>([]);
const localStream = ref<MediaStream | null>(null);
const remoteStreams = ref<Record<number, MediaStream>>({});
const participantId = ref<number | null>(null);
const participantToken = ref<string | null>(null);
const signalCursor = ref(0);
const messageCursor = ref(0);
const pollIntervalMs = ref(1200);
const iceServers = ref<RTCIceServer[]>([]);
const microphoneEnabled = ref(false);
const cameraEnabled = ref(false);
const sharingScreen = ref(false);

const peerConnections = new Map<number, RTCPeerConnection>();
const pendingCandidates = new Map<number, RTCIceCandidateInit[]>();
let cameraTrack: MediaStreamTrack | null = null;
let screenTrack: MediaStreamTrack | null = null;
let pollTimer: ReturnType<typeof setTimeout> | null = null;
let roomActive = false;
let syncInFlight = false;

const ownParticipant = computed(() => {
    return (
        participants.value.find(
            (participant) => participant.id === participantId.value,
        ) ?? null
    );
});

const remoteParticipants = computed(() => {
    return participants.value.filter(
        (participant) => participant.id !== participantId.value,
    );
});

const localDisplayName = computed(() => {
    return (
        ownParticipant.value?.display_name ||
        guestDisplayName.value ||
        t.value.conferences.you
    );
});

const videoGridClass = computed(() => {
    const count = participants.value.length || 1;

    if (count === 1) {
        return 'mx-auto w-full max-w-4xl grid-cols-1';
    }

    if (count === 2) {
        return 'grid-cols-1 md:grid-cols-2';
    }

    return 'grid-cols-1 md:grid-cols-2 2xl:grid-cols-3';
});

const requestBody = (payload: Record<string, unknown>): RequestInit => ({
    method: 'POST',
    body: JSON.stringify(payload),
});

const participantCredentials = (): Record<string, unknown> => ({
    participant_id: participantId.value,
    participant_token: participantToken.value,
});

const prepareLocalMedia = async (): Promise<void> => {
    if (localStream.value || !navigator.mediaDevices?.getUserMedia) {
        return;
    }

    try {
        localStream.value = await navigator.mediaDevices.getUserMedia({
            audio: true,
            video: { width: { ideal: 1280 }, height: { ideal: 720 } },
        });
    } catch {
        try {
            localStream.value = await navigator.mediaDevices.getUserMedia({
                audio: true,
                video: false,
            });
        } catch {
            mediaError.value = t.value.conferences.media_unavailable;
            localStream.value = new MediaStream();
        }
    }

    cameraTrack = localStream.value.getVideoTracks()[0] ?? null;
    cameraEnabled.value = cameraTrack?.enabled ?? false;
    microphoneEnabled.value =
        localStream.value.getAudioTracks()[0]?.enabled ?? false;
};

const sendSignal = async (
    targetParticipantId: number,
    type: RoomSignal['type'],
    payload: Record<string, unknown>,
): Promise<void> => {
    if (!participantId.value || !participantToken.value || !roomActive) {
        return;
    }

    await fetchSameOriginJson(
        storeConferenceSignal.url(props.roomKey),
        requestBody({
            ...participantCredentials(),
            target_participant_id: targetParticipantId,
            type,
            payload,
        }),
    );
};

const createPeerConnection = (
    remoteParticipantId: number,
): RTCPeerConnection => {
    const existing = peerConnections.get(remoteParticipantId);

    if (existing) {
        return existing;
    }

    const connection = new RTCPeerConnection({ iceServers: iceServers.value });

    localStream.value?.getTracks().forEach((track) => {
        connection.addTrack(track, localStream.value as MediaStream);
    });

    connection.onicecandidate = (event): void => {
        if (!event.candidate) {
            return;
        }

        void sendSignal(remoteParticipantId, 'ice-candidate', {
            candidate: event.candidate.candidate,
            sdpMid: event.candidate.sdpMid,
            sdpMLineIndex: event.candidate.sdpMLineIndex,
        }).catch(() => {
            connected.value = false;
        });
    };

    connection.ontrack = (event): void => {
        const stream = event.streams[0] ?? new MediaStream([event.track]);
        remoteStreams.value = {
            ...remoteStreams.value,
            [remoteParticipantId]: stream,
        };
    };

    connection.onconnectionstatechange = (): void => {
        if (connection.connectionState === 'connected') {
            connected.value = true;
        }

        if (['failed', 'closed'].includes(connection.connectionState)) {
            closePeer(remoteParticipantId);
        }
    };

    peerConnections.set(remoteParticipantId, connection);

    return connection;
};

const flushPendingCandidates = async (
    remoteParticipantId: number,
): Promise<void> => {
    const connection = peerConnections.get(remoteParticipantId);
    const candidates = pendingCandidates.get(remoteParticipantId) ?? [];

    if (!connection?.remoteDescription) {
        return;
    }

    for (const candidate of candidates) {
        await connection.addIceCandidate(candidate);
    }

    pendingCandidates.delete(remoteParticipantId);
};

const makeOffer = async (remoteParticipantId: number): Promise<void> => {
    const connection = createPeerConnection(remoteParticipantId);
    const offer = await connection.createOffer();
    await connection.setLocalDescription(offer);
    await sendSignal(remoteParticipantId, 'offer', {
        type: offer.type,
        sdp: offer.sdp,
    });
};

const handleSignal = async (signal: RoomSignal): Promise<void> => {
    if (
        !participants.value.some(
            (participant) => participant.id === signal.sender_participant_id,
        )
    ) {
        return;
    }

    const connection = createPeerConnection(signal.sender_participant_id);

    if (signal.type === 'offer') {
        await connection.setRemoteDescription(
            signal.payload as unknown as RTCSessionDescriptionInit,
        );
        await flushPendingCandidates(signal.sender_participant_id);
        const answer = await connection.createAnswer();
        await connection.setLocalDescription(answer);
        await sendSignal(signal.sender_participant_id, 'answer', {
            type: answer.type,
            sdp: answer.sdp,
        });

        return;
    }

    if (signal.type === 'answer') {
        await connection.setRemoteDescription(
            signal.payload as unknown as RTCSessionDescriptionInit,
        );
        await flushPendingCandidates(signal.sender_participant_id);

        return;
    }

    const candidate = signal.payload as RTCIceCandidateInit;

    if (!connection.remoteDescription) {
        pendingCandidates.set(signal.sender_participant_id, [
            ...(pendingCandidates.get(signal.sender_participant_id) ?? []),
            candidate,
        ]);

        return;
    }

    await connection.addIceCandidate(candidate);
};

function closePeer(remoteParticipantId: number): void {
    const connection = peerConnections.get(remoteParticipantId);

    peerConnections.delete(remoteParticipantId);
    connection?.close();
    pendingCandidates.delete(remoteParticipantId);

    const streams = { ...remoteStreams.value };
    delete streams[remoteParticipantId];
    remoteStreams.value = streams;
}

const reconcileParticipants = (nextParticipants: RoomParticipant[]): void => {
    const activeIds = new Set(
        nextParticipants.map((participant) => participant.id),
    );

    peerConnections.forEach((_connection, remoteParticipantId) => {
        if (!activeIds.has(remoteParticipantId)) {
            closePeer(remoteParticipantId);
        }
    });

    participants.value = nextParticipants;
};

const appendMessages = async (
    incomingMessages: RoomMessage[],
): Promise<void> => {
    if (incomingMessages.length === 0) {
        return;
    }

    const knownIds = new Set(messages.value.map((message) => message.id));
    messages.value = [
        ...messages.value,
        ...incomingMessages.filter((message) => !knownIds.has(message.id)),
    ];

    await nextTick();
    chatMessagesElement.value?.scrollTo({
        top: chatMessagesElement.value.scrollHeight,
        behavior: 'smooth',
    });
};

const scheduleSync = (): void => {
    if (!roomActive) {
        return;
    }

    if (pollTimer) {
        clearTimeout(pollTimer);
    }

    pollTimer = window.setTimeout(() => void syncRoom(), pollIntervalMs.value);
};

const syncRoom = async (): Promise<void> => {
    if (
        !roomActive ||
        syncInFlight ||
        !participantId.value ||
        !participantToken.value
    ) {
        return;
    }

    syncInFlight = true;

    if (pollTimer) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }

    try {
        const response = await fetchSameOriginJson<SyncResponse>(
            syncConferenceRoom.url(props.roomKey),
            requestBody({
                ...participantCredentials(),
                signal_cursor: signalCursor.value,
                message_cursor: messageCursor.value,
            }),
        );

        reconcileParticipants(response.participants);

        for (const signal of response.signals) {
            await handleSignal(signal);
        }

        signalCursor.value = response.signal_cursor;
        messageCursor.value = response.message_cursor;
        await appendMessages(response.messages);
        connected.value = true;
    } catch {
        connected.value = false;
    } finally {
        syncInFlight = false;
        scheduleSync();
    }
};

const joinRoom = async (): Promise<void> => {
    if (!props.initialDisplayName && guestDisplayName.value.trim() === '') {
        roomError.value = t.value.conferences.guest_name_required;

        return;
    }

    joining.value = true;
    roomError.value = null;
    mediaError.value = null;

    try {
        await prepareLocalMedia();

        const response = await fetchSameOriginJson<JoinResponse>(
            joinConferenceRoom.url(props.roomKey),
            requestBody({
                display_name: guestDisplayName.value.trim() || null,
            }),
        );

        participantId.value = response.participant.id;
        participantToken.value = response.participant.token;
        signalCursor.value = response.signal_cursor;
        messageCursor.value = response.message_cursor;
        pollIntervalMs.value = Math.max(750, response.poll_interval_ms);
        iceServers.value = response.ice_servers;
        participants.value = response.participants;
        messages.value = response.messages;
        joined.value = true;
        roomActive = true;

        const existingParticipants = response.participants.filter(
            (participant) => participant.id < response.participant.id,
        );

        for (const participant of existingParticipants) {
            try {
                await makeOffer(participant.id);
            } catch {
                connected.value = false;
            }
        }

        scheduleSync();
    } catch {
        roomError.value = t.value.conferences.room_connection_error;
        stopLocalMedia();
    } finally {
        joining.value = false;
    }
};

const toggleMicrophone = (): void => {
    const audioTrack = localStream.value?.getAudioTracks()[0];

    if (!audioTrack) {
        return;
    }

    audioTrack.enabled = !audioTrack.enabled;
    microphoneEnabled.value = audioTrack.enabled;
};

const replaceOutgoingVideoTrack = async (
    track: MediaStreamTrack | null,
): Promise<void> => {
    for (const connection of peerConnections.values()) {
        const videoSender = connection
            .getSenders()
            .find((sender) => sender.track?.kind === 'video');

        if (videoSender) {
            await videoSender.replaceTrack(track);
        } else if (track && localStream.value) {
            connection.addTrack(track, localStream.value);
        }
    }
};

const toggleCamera = async (): Promise<void> => {
    if (sharingScreen.value) {
        await stopScreenShare();

        return;
    }

    if (!cameraTrack || cameraTrack.readyState === 'ended') {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                video: true,
            });
            cameraTrack = stream.getVideoTracks()[0] ?? null;

            if (cameraTrack && localStream.value) {
                localStream.value.addTrack(cameraTrack);
                await replaceOutgoingVideoTrack(cameraTrack);
            }
        } catch {
            mediaError.value = t.value.conferences.camera_unavailable;

            return;
        }
    } else {
        cameraTrack.enabled = !cameraTrack.enabled;
    }

    cameraEnabled.value = cameraTrack?.enabled ?? false;
};

const shareScreen = async (): Promise<void> => {
    if (sharingScreen.value) {
        await stopScreenShare();

        return;
    }

    try {
        const displayStream = await navigator.mediaDevices.getDisplayMedia({
            video: true,
            audio: false,
        });
        screenTrack = displayStream.getVideoTracks()[0] ?? null;

        if (!screenTrack || !localStream.value) {
            return;
        }

        localStream.value
            .getVideoTracks()
            .forEach((track) => localStream.value?.removeTrack(track));
        localStream.value.addTrack(screenTrack);
        await replaceOutgoingVideoTrack(screenTrack);
        sharingScreen.value = true;
        cameraEnabled.value = true;
        screenTrack.onended = (): void => void stopScreenShare();
    } catch {
        mediaError.value = t.value.conferences.screen_share_unavailable;
    }
};

const stopScreenShare = async (): Promise<void> => {
    if (!localStream.value) {
        return;
    }

    if (screenTrack) {
        localStream.value.removeTrack(screenTrack);
        screenTrack.onended = null;
        screenTrack.stop();
        screenTrack = null;
    }

    if (cameraTrack && cameraTrack.readyState === 'live') {
        localStream.value.addTrack(cameraTrack);
        await replaceOutgoingVideoTrack(cameraTrack);
        cameraEnabled.value = cameraTrack.enabled;
    } else {
        await replaceOutgoingVideoTrack(null);
        cameraEnabled.value = false;
    }

    sharingScreen.value = false;
};

const sendMessage = async (): Promise<void> => {
    const body = chatBody.value.trim();

    if (!body || sendingMessage.value) {
        return;
    }

    sendingMessage.value = true;

    try {
        await fetchSameOriginJson(
            storeConferenceMessage.url(props.roomKey),
            requestBody({
                ...participantCredentials(),
                body,
            }),
        );
        chatBody.value = '';
        await syncRoom();
    } catch {
        roomError.value = t.value.conferences.message_send_error;
    } finally {
        sendingMessage.value = false;
    }
};

const stopLocalMedia = (): void => {
    localStream.value?.getTracks().forEach((track) => track.stop());
    localStream.value = null;
    cameraTrack = null;
    screenTrack = null;
    cameraEnabled.value = false;
    microphoneEnabled.value = false;
    sharingScreen.value = false;
};

const resetRoomState = (): void => {
    roomActive = false;

    if (pollTimer) {
        clearTimeout(pollTimer);
        pollTimer = null;
    }

    peerConnections.forEach((connection) => connection.close());
    peerConnections.clear();
    pendingCandidates.clear();
    remoteStreams.value = {};
    participants.value = [];
    messages.value = [];
    joined.value = false;
    participantId.value = null;
    participantToken.value = null;
    syncInFlight = false;
};

const leaveRoom = async (): Promise<void> => {
    if (!participantId.value || !participantToken.value) {
        resetRoomState();

        return;
    }

    leaving.value = true;

    try {
        await fetchSameOriginJson(
            leaveConferenceRoom.url(props.roomKey),
            requestBody(participantCredentials()),
        );
    } catch {
        // Presence expires automatically when a browser closes unexpectedly.
    } finally {
        resetRoomState();
        stopLocalMedia();
        leaving.value = false;
    }
};

const toggleFullscreen = async (): Promise<void> => {
    if (!document.fullscreenElement) {
        await roomElement.value?.requestFullscreen();

        return;
    }

    await document.exitFullscreen();
};

const formatMessageTime = (value: string): string => {
    return new Intl.DateTimeFormat(undefined, {
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

onBeforeUnmount(() => {
    const leavingParticipantId = participantId.value;
    const leavingParticipantToken = participantToken.value;

    if (joined.value && leavingParticipantId && leavingParticipantToken) {
        void fetchSameOriginJson(
            leaveConferenceRoom.url(props.roomKey),
            requestBody({
                participant_id: leavingParticipantId,
                participant_token: leavingParticipantToken,
            }),
        ).catch(() => undefined);
    }

    resetRoomState();
    stopLocalMedia();
});
</script>

<template>
    <section
        ref="roomElement"
        class="overflow-hidden rounded-2xl border border-border bg-slate-950 shadow-xl"
    >
        <div
            v-if="!joined"
            class="grid min-h-[38rem] place-items-center p-5 sm:p-8"
        >
            <div class="w-full max-w-3xl space-y-6">
                <div
                    class="mx-auto aspect-video max-w-2xl overflow-hidden rounded-2xl border border-white/10 bg-slate-900"
                >
                    <ConferenceVideoTile
                        :stream="localStream"
                        :name="
                            guestDisplayName ||
                            initialDisplayName ||
                            t.conferences.you
                        "
                        muted
                        local
                        class="h-full border-0"
                    />
                </div>

                <div class="mx-auto max-w-md space-y-4 text-center">
                    <div class="space-y-1">
                        <h2 class="text-xl font-semibold text-white">
                            {{ conferenceTitle }}
                        </h2>
                        <p class="text-sm text-slate-400">
                            {{ t.conferences.local_room_description }}
                        </p>
                    </div>

                    <Input
                        v-if="!initialDisplayName"
                        v-model="guestDisplayName"
                        :placeholder="t.conferences.guest_name"
                        maxlength="120"
                        class="border-white/15 bg-white/10 text-white placeholder:text-slate-500"
                        @keyup.enter="joinRoom"
                    />

                    <p v-if="roomError" class="text-sm text-rose-400">
                        {{ roomError }}
                    </p>
                    <p v-if="mediaError" class="text-sm text-amber-300">
                        {{ mediaError }}
                    </p>

                    <Button
                        type="button"
                        size="lg"
                        class="w-full"
                        :disabled="joining"
                        @click="joinRoom"
                    >
                        <LoaderCircle
                            v-if="joining"
                            class="size-4 animate-spin"
                        />
                        <Video v-else class="size-4" />
                        {{
                            joining
                                ? t.conferences.connecting
                                : t.conferences.join_room
                        }}
                    </Button>
                </div>
            </div>
        </div>

        <div v-else class="flex min-h-[42rem] bg-slate-950 text-white">
            <div class="flex min-w-0 flex-1 flex-col">
                <header
                    class="flex items-center justify-between gap-4 border-b border-white/10 px-4 py-3 sm:px-5"
                >
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold">
                            {{ conferenceTitle }}
                        </div>
                        <div
                            class="mt-0.5 flex items-center gap-2 text-xs text-slate-400"
                        >
                            <Users class="size-3.5" />
                            {{ participants.length }}
                            <Wifi
                                v-if="connected"
                                class="ml-1 size-3.5 text-emerald-400"
                            />
                            <WifiOff
                                v-else
                                class="ml-1 size-3.5 text-amber-400"
                            />
                        </div>
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="text-white hover:bg-white/10 hover:text-white"
                        @click="toggleFullscreen"
                    >
                        <Maximize2 class="size-4" />
                    </Button>
                </header>

                <div class="flex-1 overflow-y-auto p-3 sm:p-5">
                    <div
                        class="grid min-h-full auto-rows-fr gap-3"
                        :class="videoGridClass"
                    >
                        <ConferenceVideoTile
                            :stream="localStream"
                            :name="localDisplayName"
                            :avatar="ownParticipant?.user?.avatar"
                            muted
                            local
                        />
                        <ConferenceVideoTile
                            v-for="participant in remoteParticipants"
                            :key="participant.id"
                            :stream="remoteStreams[participant.id] ?? null"
                            :name="participant.display_name"
                            :avatar="participant.user?.avatar"
                        />
                    </div>
                </div>

                <p
                    v-if="mediaError"
                    class="px-5 pb-2 text-center text-xs text-amber-300"
                >
                    {{ mediaError }}
                </p>
                <p
                    v-if="roomError"
                    class="px-5 pb-2 text-center text-xs text-rose-400"
                >
                    {{ roomError }}
                </p>

                <footer
                    class="flex flex-wrap items-center justify-center gap-2 border-t border-white/10 bg-black/20 px-3 py-4"
                >
                    <Button
                        type="button"
                        size="icon"
                        :variant="
                            microphoneEnabled ? 'secondary' : 'destructive'
                        "
                        class="rounded-full"
                        :title="t.conferences.toggle_microphone"
                        @click="toggleMicrophone"
                    >
                        <Mic v-if="microphoneEnabled" class="size-4" />
                        <MicOff v-else class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        :variant="cameraEnabled ? 'secondary' : 'destructive'"
                        class="rounded-full"
                        :title="t.conferences.toggle_camera"
                        @click="toggleCamera"
                    >
                        <Video v-if="cameraEnabled" class="size-4" />
                        <VideoOff v-else class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        :variant="sharingScreen ? 'default' : 'secondary'"
                        class="rounded-full"
                        :title="t.conferences.share_screen"
                        @click="shareScreen"
                    >
                        <MonitorUp class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        :variant="chatOpen ? 'default' : 'secondary'"
                        class="rounded-full"
                        :title="t.conferences.open_chat"
                        @click="chatOpen = !chatOpen"
                    >
                        <MessageSquareText class="size-4" />
                    </Button>
                    <Button
                        type="button"
                        size="icon"
                        variant="destructive"
                        class="ml-2 rounded-full"
                        :disabled="leaving"
                        :title="t.conferences.leave_room"
                        @click="leaveRoom"
                    >
                        <LoaderCircle
                            v-if="leaving"
                            class="size-4 animate-spin"
                        />
                        <PhoneOff v-else class="size-4" />
                    </Button>
                </footer>
            </div>

            <aside
                v-if="chatOpen"
                class="flex w-full max-w-84 shrink-0 flex-col border-l border-white/10 bg-slate-900 sm:w-84"
            >
                <header
                    class="flex items-center justify-between border-b border-white/10 px-4 py-3"
                >
                    <div class="flex items-center gap-2 font-medium">
                        <MessageSquareText class="size-4" />
                        {{ t.conferences.chat_title }}
                    </div>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        class="text-white hover:bg-white/10 hover:text-white"
                        @click="chatOpen = false"
                    >
                        <X class="size-4" />
                    </Button>
                </header>

                <div
                    ref="chatMessagesElement"
                    class="flex-1 space-y-3 overflow-y-auto p-4"
                >
                    <p
                        v-if="messages.length === 0"
                        class="py-8 text-center text-sm text-slate-400"
                    >
                        {{ t.conferences.chat_empty }}
                    </p>
                    <article
                        v-for="message in messages"
                        :key="message.id"
                        class="rounded-xl bg-white/6 p-3"
                    >
                        <div
                            class="flex items-center justify-between gap-2 text-xs"
                        >
                            <span class="truncate font-medium text-white">{{
                                message.display_name
                            }}</span>
                            <time class="shrink-0 text-slate-500">{{
                                formatMessageTime(message.created_at)
                            }}</time>
                        </div>
                        <p
                            class="mt-1.5 text-sm break-words whitespace-pre-wrap text-slate-200"
                        >
                            {{ message.body }}
                        </p>
                    </article>
                </div>

                <form
                    class="flex gap-2 border-t border-white/10 p-3"
                    @submit.prevent="sendMessage"
                >
                    <Input
                        v-model="chatBody"
                        :placeholder="t.conferences.chat_placeholder"
                        maxlength="2000"
                        class="border-white/15 bg-white/8 text-white placeholder:text-slate-500"
                    />
                    <Button
                        type="submit"
                        size="icon"
                        :disabled="sendingMessage || !chatBody.trim()"
                    >
                        <LoaderCircle
                            v-if="sendingMessage"
                            class="size-4 animate-spin"
                        />
                        <Send v-else class="size-4" />
                    </Button>
                </form>
            </aside>
        </div>
    </section>
</template>

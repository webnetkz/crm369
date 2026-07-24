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
import { processConferenceRoomSync } from '@/lib/conferenceRoomSync';
import { audioLevel, updateSpeakingState } from '@/lib/conferenceSpeaking';
import type { SpeakingState } from '@/lib/conferenceSpeaking';
import { fetchSameOriginJson, SameOriginJsonError } from '@/lib/sameOriginJson';

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
    ice_servers_expires_at: number | null;
    poll_interval_ms: number;
};

type SyncResponse = {
    participants: RoomParticipant[];
    signals: RoomSignal[];
    messages: RoomMessage[];
    signal_cursor: number;
    message_cursor: number;
    ice_servers: RTCIceServer[];
    ice_servers_expires_at: number | null;
};

type VideoParticipant = {
    id: number;
    name: string;
    avatar: string | null;
    stream: MediaStream | null;
    local: boolean;
    muted: boolean;
};

type ParticipantAudioMonitor = {
    stream: MediaStream;
    source: MediaStreamAudioSourceNode;
    analyser: AnalyserNode;
    samples: Uint8Array;
    state: SpeakingState;
};

type PeerConnectionRole = 'offerer' | 'answerer';

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
const pinnedParticipantId = ref<number | null>(null);
const speakingParticipantIds = ref<ReadonlySet<number>>(new Set());

const peerConnections = new Map<number, RTCPeerConnection>();
const pendingCandidates = new Map<number, RTCIceCandidateInit[]>();
const pendingLocalCandidates = new Map<number, Record<string, unknown>[]>();
const localDescriptionInFlight = new Set<number>();
const audioSenders = new Map<number, RTCRtpSender>();
const videoSenders = new Map<number, RTCRtpSender>();
const peerRestartAttempts = new Map<number, number>();
const peerRestartTimers = new Map<number, ReturnType<typeof setTimeout>>();
const pendingSignalRetries = new Map<number, RoomSignal>();
const signalRetryAttempts = new Map<number, number>();
const participantAudioMonitors = new Map<number, ParticipantAudioMonitor>();
let cameraTrack: MediaStreamTrack | null = null;
let screenTrack: MediaStreamTrack | null = null;
let pollTimer: ReturnType<typeof setTimeout> | null = null;
let speakerAnimationFrame: number | null = null;
let lastSpeakerSampleAt = 0;
let speakerAudioContext: AudioContext | null = null;
let roomActive = false;
let syncInFlight = false;
let iceServerFingerprint = '';

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

const videoParticipants = computed<VideoParticipant[]>(() => {
    const ownId = participantId.value;
    const own = ownParticipant.value;
    const tiles: VideoParticipant[] = [];

    if (ownId !== null) {
        tiles.push({
            id: ownId,
            name: localDisplayName.value,
            avatar: own?.user?.avatar ?? null,
            stream: localStream.value,
            local: true,
            muted: true,
        });
    }

    for (const participant of remoteParticipants.value) {
        tiles.push({
            id: participant.id,
            name: participant.display_name,
            avatar: participant.user?.avatar ?? null,
            stream: remoteStreams.value[participant.id] ?? null,
            local: false,
            muted: false,
        });
    }

    return tiles;
});

const pinnedVideoParticipant = computed(() => {
    return (
        videoParticipants.value.find(
            (participant) => participant.id === pinnedParticipantId.value,
        ) ?? null
    );
});

const secondaryVideoParticipants = computed(() => {
    return videoParticipants.value.filter(
        (participant) => participant.id !== pinnedParticipantId.value,
    );
});

const videoGridClass = computed(() => {
    const count = videoParticipants.value.length || 1;

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

const speakingSetsMatch = (
    first: ReadonlySet<number>,
    second: ReadonlySet<number>,
): boolean => {
    return (
        first.size === second.size &&
        [...first].every((participant) => second.has(participant))
    );
};

const removeParticipantAudioMonitor = (participant: number): void => {
    const monitor = participantAudioMonitors.get(participant);

    monitor?.source.disconnect();
    monitor?.analyser.disconnect();
    participantAudioMonitors.delete(participant);
};

const syncParticipantAudioMonitors = (): void => {
    if (!speakerAudioContext) {
        return;
    }

    const participantStreams = new Map(
        videoParticipants.value
            .filter(
                (
                    participant,
                ): participant is VideoParticipant & { stream: MediaStream } =>
                    participant.stream !== null &&
                    participant.stream
                        .getAudioTracks()
                        .some((track) => track.readyState === 'live'),
            )
            .map((participant) => [participant.id, participant.stream]),
    );

    participantAudioMonitors.forEach((monitor, participant) => {
        if (participantStreams.get(participant) !== monitor.stream) {
            removeParticipantAudioMonitor(participant);
        }
    });

    participantStreams.forEach((stream, participant) => {
        if (participantAudioMonitors.has(participant)) {
            return;
        }

        try {
            const analyser = speakerAudioContext?.createAnalyser();
            const source = speakerAudioContext?.createMediaStreamSource(stream);

            if (!analyser || !source) {
                return;
            }

            analyser.fftSize = 512;
            analyser.smoothingTimeConstant = 0.35;
            source.connect(analyser);

            participantAudioMonitors.set(participant, {
                stream,
                source,
                analyser,
                samples: new Uint8Array(analyser.fftSize),
                state: {
                    speaking: false,
                    lastVoiceAt: 0,
                },
            });
        } catch {
            removeParticipantAudioMonitor(participant);
        }
    });
};

const sampleParticipantAudio = (now: number): void => {
    if (now - lastSpeakerSampleAt >= 100) {
        const speakingParticipants = new Set<number>();

        participantAudioMonitors.forEach((monitor, participant) => {
            monitor.analyser.getByteTimeDomainData(monitor.samples);
            monitor.state = updateSpeakingState(
                monitor.state,
                audioLevel(monitor.samples),
                now,
            );

            if (monitor.state.speaking) {
                speakingParticipants.add(participant);
            }
        });

        if (
            !speakingSetsMatch(
                speakingParticipantIds.value,
                speakingParticipants,
            )
        ) {
            speakingParticipantIds.value = speakingParticipants;
        }

        lastSpeakerSampleAt = now;
    }

    speakerAnimationFrame = window.requestAnimationFrame(
        sampleParticipantAudio,
    );
};

const startSpeakerDetection = async (): Promise<void> => {
    if (typeof window.AudioContext === 'undefined') {
        return;
    }

    speakerAudioContext ??= new AudioContext();

    if (speakerAudioContext.state === 'suspended') {
        await speakerAudioContext.resume().catch(() => undefined);
    }

    syncParticipantAudioMonitors();

    speakerAnimationFrame ??= window.requestAnimationFrame(
        sampleParticipantAudio,
    );
};

const stopSpeakerDetection = (): void => {
    if (speakerAnimationFrame !== null) {
        cancelAnimationFrame(speakerAnimationFrame);
        speakerAnimationFrame = null;
    }

    participantAudioMonitors.forEach((_monitor, participant) => {
        removeParticipantAudioMonitor(participant);
    });

    const audioContext = speakerAudioContext;
    speakerAudioContext = null;
    lastSpeakerSampleAt = 0;
    speakingParticipantIds.value = new Set();
    void audioContext?.close();
};

const togglePinnedParticipant = (participant: number): void => {
    pinnedParticipantId.value =
        pinnedParticipantId.value === participant ? null : participant;
    void startSpeakerDetection();
};

const applyIceServers = (nextIceServers: RTCIceServer[]): void => {
    const fingerprint = JSON.stringify(nextIceServers);

    if (fingerprint === iceServerFingerprint) {
        return;
    }

    iceServers.value = nextIceServers;
    iceServerFingerprint = fingerprint;

    peerConnections.forEach((connection, remoteParticipantId) => {
        try {
            connection.setConfiguration({ iceServers: nextIceServers });
        } catch (error) {
            console.warn('Conference ICE server refresh failed.', {
                remoteParticipantId,
                error:
                    error instanceof DOMException ? error.name : 'UnknownError',
            });
        }
    });
};

const reportIceCandidateError = (
    remoteParticipantId: number,
    connection: RTCPeerConnection,
    error: unknown,
): void => {
    console.warn('Conference ICE candidate was skipped.', {
        remoteParticipantId,
        error: error instanceof DOMException ? error.name : 'UnknownError',
        signalingState: connection.signalingState,
        iceConnectionState: connection.iceConnectionState,
    });
};

const prepareLocalMedia = async (): Promise<void> => {
    if (localStream.value) {
        return;
    }

    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
        mediaError.value = t.value.conferences.secure_context_required;
        localStream.value = new MediaStream();

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
    role: PeerConnectionRole,
): RTCPeerConnection => {
    const existing = peerConnections.get(remoteParticipantId);

    if (existing) {
        return existing;
    }

    const connection = new RTCPeerConnection({ iceServers: iceServers.value });

    if (role === 'offerer') {
        const stream = localStream.value ?? new MediaStream();
        const audioTrack = stream.getAudioTracks()[0];
        const videoTrack = stream.getVideoTracks()[0];
        const audioTransceiver = connection.addTransceiver(
            audioTrack ?? 'audio',
            {
                direction: 'sendrecv',
                streams: [stream],
            },
        );
        const videoTransceiver = connection.addTransceiver(
            videoTrack ?? 'video',
            {
                direction: 'sendrecv',
                streams: [stream],
            },
        );

        audioSenders.set(remoteParticipantId, audioTransceiver.sender);
        videoSenders.set(remoteParticipantId, videoTransceiver.sender);
    }

    connection.onicecandidate = (event): void => {
        if (!event.candidate) {
            return;
        }

        const candidate = {
            candidate: event.candidate.candidate,
            sdpMid: event.candidate.sdpMid,
            sdpMLineIndex: event.candidate.sdpMLineIndex,
        };

        if (localDescriptionInFlight.has(remoteParticipantId)) {
            pendingLocalCandidates.set(remoteParticipantId, [
                ...(pendingLocalCandidates.get(remoteParticipantId) ?? []),
                candidate,
            ]);

            return;
        }

        void sendSignal(remoteParticipantId, 'ice-candidate', candidate).catch(
            () => {
                connected.value = false;
            },
        );
    };

    connection.ontrack = (event): void => {
        const stream = event.streams[0] ?? new MediaStream([event.track]);
        remoteStreams.value = {
            ...remoteStreams.value,
            [remoteParticipantId]: stream,
        };
        syncParticipantAudioMonitors();
    };

    connection.onconnectionstatechange = (): void => {
        if (connection.connectionState === 'connected') {
            connected.value = true;
            clearPeerRestart(remoteParticipantId);

            return;
        }

        if (connection.connectionState === 'disconnected') {
            connected.value = false;
            schedulePeerRestart(remoteParticipantId, connection, 3000);

            return;
        }

        if (connection.connectionState === 'failed') {
            connected.value = false;

            if (shouldInitiateOffer(remoteParticipantId)) {
                schedulePeerRestart(remoteParticipantId, connection);
            } else {
                closePeer(remoteParticipantId);
            }

            return;
        }

        if (connection.connectionState === 'closed') {
            closePeer(remoteParticipantId);
        }
    };

    peerConnections.set(remoteParticipantId, connection);

    return connection;
};

const attachLocalTracksToRemoteOffer = async (
    remoteParticipantId: number,
    connection: RTCPeerConnection,
): Promise<void> => {
    const stream = localStream.value ?? new MediaStream();
    const tracks = new Map<MediaStreamTrack['kind'], MediaStreamTrack | null>([
        ['audio', stream.getAudioTracks()[0] ?? null],
        ['video', stream.getVideoTracks()[0] ?? null],
    ]);

    for (const [kind, track] of tracks) {
        const transceiver = connection
            .getTransceivers()
            .find(
                (candidate) =>
                    candidate.receiver.track.kind === kind &&
                    !candidate.stopped,
            );

        if (!transceiver) {
            continue;
        }

        transceiver.direction = 'sendrecv';
        transceiver.sender.setStreams(stream);
        await transceiver.sender.replaceTrack(track);

        if (kind === 'audio') {
            audioSenders.set(remoteParticipantId, transceiver.sender);
        } else {
            videoSenders.set(remoteParticipantId, transceiver.sender);
        }
    }
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
        try {
            await connection.addIceCandidate(candidate);
        } catch (error) {
            reportIceCandidateError(remoteParticipantId, connection, error);

            continue;
        }
    }

    pendingCandidates.delete(remoteParticipantId);
};

const flushPendingLocalCandidates = async (
    remoteParticipantId: number,
): Promise<void> => {
    const candidates = pendingLocalCandidates.get(remoteParticipantId) ?? [];
    pendingLocalCandidates.delete(remoteParticipantId);

    for (const candidate of candidates) {
        await sendSignal(remoteParticipantId, 'ice-candidate', candidate);
    }
};

const sendLocalDescription = async (
    remoteParticipantId: number,
    type: 'offer' | 'answer',
    description: RTCSessionDescriptionInit,
): Promise<void> => {
    const connection = peerConnections.get(remoteParticipantId);

    if (!connection) {
        throw new Error('WebRTC peer connection was not created.');
    }

    localDescriptionInFlight.add(remoteParticipantId);

    try {
        await connection.setLocalDescription(description);

        const localDescription = connection.localDescription;

        if (!localDescription) {
            throw new Error('Local WebRTC description was not created.');
        }

        await sendSignal(remoteParticipantId, type, {
            type,
            sdp: localDescription.sdp,
        });
    } catch (error) {
        pendingLocalCandidates.delete(remoteParticipantId);

        throw error;
    } finally {
        localDescriptionInFlight.delete(remoteParticipantId);
    }

    await flushPendingLocalCandidates(remoteParticipantId);
};

const makeOffer = async (remoteParticipantId: number): Promise<void> => {
    const connection = createPeerConnection(remoteParticipantId, 'offerer');
    const offer = await connection.createOffer();
    await sendLocalDescription(remoteParticipantId, 'offer', offer);
};

const shouldInitiateOffer = (remoteParticipantId: number): boolean => {
    return (
        participantId.value !== null &&
        participantId.value > remoteParticipantId
    );
};

const clearPeerRestart = (remoteParticipantId: number): void => {
    const timer = peerRestartTimers.get(remoteParticipantId);

    if (timer) {
        clearTimeout(timer);
    }

    peerRestartTimers.delete(remoteParticipantId);
    peerRestartAttempts.delete(remoteParticipantId);
};

const schedulePeerRestart = (
    remoteParticipantId: number,
    connection: RTCPeerConnection,
    delay = 0,
): void => {
    if (
        !roomActive ||
        !shouldInitiateOffer(remoteParticipantId) ||
        peerRestartTimers.has(remoteParticipantId) ||
        (peerRestartAttempts.get(remoteParticipantId) ?? 0) >= 3
    ) {
        return;
    }

    const timer = window.setTimeout(async () => {
        peerRestartTimers.delete(remoteParticipantId);

        if (
            !roomActive ||
            peerConnections.get(remoteParticipantId) !== connection
        ) {
            return;
        }

        if (connection.signalingState !== 'stable') {
            schedulePeerRestart(remoteParticipantId, connection, 1000);

            return;
        }

        peerRestartAttempts.set(
            remoteParticipantId,
            (peerRestartAttempts.get(remoteParticipantId) ?? 0) + 1,
        );

        try {
            const offer = await connection.createOffer({ iceRestart: true });
            await sendLocalDescription(remoteParticipantId, 'offer', offer);
        } catch {
            connected.value = false;
            schedulePeerRestart(remoteParticipantId, connection, 2000);
        }
    }, delay);

    peerRestartTimers.set(remoteParticipantId, timer);
};

const handleSignal = async (signal: RoomSignal): Promise<void> => {
    if (
        !participants.value.some(
            (participant) => participant.id === signal.sender_participant_id,
        )
    ) {
        return;
    }

    const remoteParticipantId = signal.sender_participant_id;

    if (signal.type === 'offer') {
        let connection = peerConnections.get(remoteParticipantId);

        if (connection && connection.signalingState !== 'stable') {
            const queuedCandidates =
                pendingCandidates.get(remoteParticipantId) ?? [];
            closePeer(remoteParticipantId);
            pendingCandidates.set(remoteParticipantId, queuedCandidates);
            connection = undefined;
        }

        connection ??= createPeerConnection(remoteParticipantId, 'answerer');

        let negotiationStage = 'set-remote-offer';

        try {
            await connection.setRemoteDescription(
                signal.payload as unknown as RTCSessionDescriptionInit,
            );
            negotiationStage = 'attach-local-tracks';
            await attachLocalTracksToRemoteOffer(
                remoteParticipantId,
                connection,
            );
            negotiationStage = 'create-answer';
            const answer = await connection.createAnswer();
            negotiationStage = 'set-and-send-local-answer';
            await sendLocalDescription(remoteParticipantId, 'answer', answer);
            negotiationStage = 'flush-remote-candidates';
            await flushPendingCandidates(remoteParticipantId);
        } catch (error) {
            console.warn('Conference WebRTC offer negotiation failed.', {
                remoteParticipantId,
                stage: negotiationStage,
                error:
                    error instanceof DOMException ? error.name : 'UnknownError',
                message: error instanceof Error ? error.message : null,
            });

            const queuedCandidates =
                pendingCandidates.get(remoteParticipantId) ?? [];
            closePeer(remoteParticipantId);
            pendingCandidates.set(remoteParticipantId, queuedCandidates);

            throw error;
        }

        return;
    }

    if (signal.type === 'answer') {
        const connection = peerConnections.get(remoteParticipantId);

        if (!connection) {
            return;
        }

        if (
            connection.signalingState === 'stable' &&
            connection.remoteDescription?.type === 'answer'
        ) {
            return;
        }

        await connection.setRemoteDescription(
            signal.payload as unknown as RTCSessionDescriptionInit,
        );
        await flushPendingCandidates(remoteParticipantId);

        return;
    }

    const candidate = signal.payload as RTCIceCandidateInit;
    const connection = peerConnections.get(remoteParticipantId);

    if (!connection?.remoteDescription) {
        pendingCandidates.set(remoteParticipantId, [
            ...(pendingCandidates.get(remoteParticipantId) ?? []),
            candidate,
        ]);

        return;
    }

    try {
        await connection.addIceCandidate(candidate);
    } catch (error) {
        reportIceCandidateError(remoteParticipantId, connection, error);

        return;
    }
};

function closePeer(remoteParticipantId: number): void {
    const connection = peerConnections.get(remoteParticipantId);

    peerConnections.delete(remoteParticipantId);
    connection?.close();
    clearPeerRestart(remoteParticipantId);
    pendingCandidates.delete(remoteParticipantId);
    pendingLocalCandidates.delete(remoteParticipantId);
    localDescriptionInFlight.delete(remoteParticipantId);
    audioSenders.delete(remoteParticipantId);
    videoSenders.delete(remoteParticipantId);
    removeParticipantAudioMonitor(remoteParticipantId);

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

    if (
        pinnedParticipantId.value !== null &&
        !activeIds.has(pinnedParticipantId.value)
    ) {
        pinnedParticipantId.value = null;
    }
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

        const retrySignals = [...pendingSignalRetries.values()];
        applyIceServers(response.ice_servers);
        pendingSignalRetries.clear();
        const retrySignalIds = new Set(retrySignals.map((signal) => signal.id));
        const failedSignals = await processConferenceRoomSync(
            {
                ...response,
                signals: [
                    ...retrySignals,
                    ...response.signals.filter(
                        (signal) => !retrySignalIds.has(signal.id),
                    ),
                ],
            },
            {
                reconcileParticipants,
                appendMessages,
                setSignalCursor(cursor) {
                    signalCursor.value = cursor;
                },
                setMessageCursor(cursor) {
                    messageCursor.value = cursor;
                },
                async handleSignal(signal) {
                    await handleSignal(signal);
                    signalRetryAttempts.delete(signal.id);
                },
                onSignalError(signal, error) {
                    console.warn('Conference WebRTC signal failed.', {
                        signalId: signal.id,
                        signalType: signal.type,
                        senderParticipantId: signal.sender_participant_id,
                        error:
                            error instanceof DOMException
                                ? error.name
                                : 'UnknownError',
                        message: error instanceof Error ? error.message : null,
                    });
                },
            },
        );

        for (const signal of failedSignals) {
            const attempts = (signalRetryAttempts.get(signal.id) ?? 0) + 1;

            if (attempts < 3) {
                signalRetryAttempts.set(signal.id, attempts);
                pendingSignalRetries.set(signal.id, signal);
            } else {
                signalRetryAttempts.delete(signal.id);
                closePeer(signal.sender_participant_id);
            }
        }

        connected.value = failedSignals.length === 0;
    } catch (error) {
        if (
            error instanceof SameOriginJsonError &&
            error.code === 'participant_expired'
        ) {
            roomError.value = t.value.conferences.participant_expired;
            resetRoomState();
            await joinRoom();

            return;
        }

        if (
            error instanceof SameOriginJsonError &&
            error.code === 'conference_ended'
        ) {
            roomError.value = t.value.conferences.ended_notice;
            resetRoomState();
            stopLocalMedia();

            return;
        }

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
    void startSpeakerDetection();

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
        applyIceServers(response.ice_servers);
        participants.value = response.participants;
        messages.value = response.messages;
        joined.value = true;
        roomActive = true;
        syncParticipantAudioMonitors();

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
    } catch (error) {
        roomError.value =
            error instanceof SameOriginJsonError
                ? error.message
                : t.value.conferences.room_connection_error;
        stopLocalMedia();
        stopSpeakerDetection();
    } finally {
        joining.value = false;
    }
};

const replaceOutgoingAudioTrack = async (
    track: MediaStreamTrack | null,
): Promise<void> => {
    await Promise.all(
        [...audioSenders.values()].map((sender) => sender.replaceTrack(track)),
    );
};

const toggleMicrophone = async (): Promise<void> => {
    let audioTrack = localStream.value?.getAudioTracks()[0];

    if (!audioTrack || audioTrack.readyState === 'ended') {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: true,
            });
            audioTrack = stream.getAudioTracks()[0];

            if (!audioTrack || !localStream.value) {
                return;
            }

            localStream.value.addTrack(audioTrack);
            await replaceOutgoingAudioTrack(audioTrack);
            syncParticipantAudioMonitors();
        } catch {
            mediaError.value = t.value.conferences.media_unavailable;

            return;
        }
    } else {
        audioTrack.enabled = !audioTrack.enabled;
    }

    microphoneEnabled.value = audioTrack.enabled;
};

const replaceOutgoingVideoTrack = async (
    track: MediaStreamTrack | null,
): Promise<void> => {
    await Promise.all(
        [...videoSenders.values()].map((sender) => sender.replaceTrack(track)),
    );
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
    pendingLocalCandidates.clear();
    localDescriptionInFlight.clear();
    audioSenders.clear();
    videoSenders.clear();
    peerRestartTimers.forEach((timer) => clearTimeout(timer));
    peerRestartTimers.clear();
    peerRestartAttempts.clear();
    pendingSignalRetries.clear();
    signalRetryAttempts.clear();
    pinnedParticipantId.value = null;
    stopSpeakerDetection();
    remoteStreams.value = {};
    participants.value = [];
    messages.value = [];
    iceServers.value = [];
    iceServerFingerprint = '';
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
                        v-if="pinnedVideoParticipant"
                        class="grid min-h-full gap-3 lg:grid-cols-[minmax(0,1fr)_15rem]"
                    >
                        <ConferenceVideoTile
                            :stream="pinnedVideoParticipant.stream"
                            :name="pinnedVideoParticipant.name"
                            :avatar="pinnedVideoParticipant.avatar"
                            :muted="pinnedVideoParticipant.muted"
                            :local="pinnedVideoParticipant.local"
                            :speaking="
                                speakingParticipantIds.has(
                                    pinnedVideoParticipant.id,
                                )
                            "
                            pinned
                            featured
                            @toggle-pin="
                                togglePinnedParticipant(
                                    pinnedVideoParticipant.id,
                                )
                            "
                        />

                        <div
                            v-if="secondaryVideoParticipants.length > 0"
                            class="grid auto-rows-[minmax(9rem,1fr)] grid-cols-2 gap-3 lg:max-h-[calc(100vh-14rem)] lg:grid-cols-1 lg:overflow-y-auto lg:pr-1"
                        >
                            <ConferenceVideoTile
                                v-for="participant in secondaryVideoParticipants"
                                :key="participant.id"
                                :stream="participant.stream"
                                :name="participant.name"
                                :avatar="participant.avatar"
                                :muted="participant.muted"
                                :local="participant.local"
                                :speaking="
                                    speakingParticipantIds.has(participant.id)
                                "
                                @toggle-pin="
                                    togglePinnedParticipant(participant.id)
                                "
                            />
                        </div>
                    </div>

                    <div
                        v-else
                        class="grid min-h-full auto-rows-fr gap-3"
                        :class="videoGridClass"
                    >
                        <ConferenceVideoTile
                            v-for="participant in videoParticipants"
                            :key="participant.id"
                            :stream="participant.stream"
                            :name="participant.name"
                            :avatar="participant.avatar"
                            :muted="participant.muted"
                            :local="participant.local"
                            :speaking="
                                speakingParticipantIds.has(participant.id)
                            "
                            @toggle-pin="
                                togglePinnedParticipant(participant.id)
                            "
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

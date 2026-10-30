import type { Page } from '@inertiajs/core';
import {
    getCurrentScope,
    onScopeDispose,
    readonly,
    ref,
    shallowRef,
} from 'vue';
import type { DeepReadonly, Ref, ShallowRef } from 'vue';
import { isNativeShell } from '@/pwa/platform';
import { call as callRoute } from '@/routes/messages';
import type {
    CallPeer,
    CallSignal,
    CallSignalType,
    CallsConfig,
} from '@/types';
import {
    audioContext,
    claimSound,
    isMessageSoundEnabled,
    tone,
} from './activity';
import { connectionState } from './echo';
import { requestJson } from './http';

/**
 * Video calls, peer to peer. The two browsers exchange their set-up
 * messages through the server (POST messages.call → `.call.signal` on each
 * participant's own channel) and then send audio and video straight to each
 * other over WebRTC. There is no relay on purpose — the server's line is
 * metered — so when two networks won't connect directly (most often two
 * phones on mobile data) the call fails, and the ended card says why.
 *
 * One call at a time per tab. A ring reaches every tab of the callee; the
 * first to answer gets the call and the others drop the banner.
 */

export type CallStatus = 'ringing' | 'connecting' | 'connected' | 'ended';

/** Why a call is over; the ended card picks its words from this. */
export type CallEndReason =
    | 'hungUp'
    | 'cancelled'
    | 'declined'
    | 'busy'
    | 'noAnswer'
    | 'connectionFailed'
    | 'connectionLost'
    | 'setupFailed'
    | 'mediaDenied'
    | 'noDevices'
    | 'mediaBusy'
    | 'mediaFailed'
    | 'peerMediaFailed'
    | 'unsupported'
    | 'offline'
    | 'signalFailed';

export type ActiveCall = {
    id: string;
    conversationId: number;
    peer: CallPeer;
    /** The peer's tab once known: their ring, or their accept. */
    peerTab: string | null;
    outgoing: boolean;
    status: CallStatus;
    reason: CallEndReason | null;
    connectedAt: number | null;
    endedAt: number | null;
    /** The connection dropped and WebRTC is trying to bring it back. */
    reconnecting: boolean;
    micOn: boolean;
    cameraOn: boolean;
    /** False when no camera could be started: audio only from this side. */
    hasCamera: boolean;
    /** Whether a video track has arrived from the peer. */
    peerVideo: boolean;
    /** The peer's camera and microphone, as their `media` signals say. */
    peerCameraOn: boolean;
    peerMicOn: boolean;
    peerHasCamera: boolean;
};

export type IncomingCall = {
    id: string;
    conversationId: number;
    peer: CallPeer;
    fromTab: string;
};

/** How long a ring lasts before it counts as unanswered, on both sides. */
const RING_MS = 45_000;
/** How long the connection may take after the answer before it is given up. */
const CONNECT_MS = 25_000;
/** How long a dropped connection may try to come back. */
const DISCONNECT_GRACE_MS = 8_000;
/** ICE candidates come in bursts; send them together. */
const CANDIDATE_BATCH_MS = 150;
/** How long "Call ended" stays after a normal hang-up. */
const ENDED_CARD_MS = 4_000;
const RING_TONE_MS = 2_600;
const RINGBACK_MS = 3_000;

const tabId = newId();

let me: number | null = null;
let config: CallsConfig = { enabled: false, iceServers: [] };
let pageHideBound = false;

const current = ref<ActiveCall | null>(null);
const incoming = ref<IncomingCall | null>(null);
const localStream = shallowRef<MediaStream | null>(null);
const remoteStream = shallowRef<MediaStream | null>(null);

let rtc: RTCPeerConnection | null = null;
let queuedCandidates: RTCIceCandidateInit[] = [];
let outgoingCandidates: RTCIceCandidateInit[] = [];
let toneKind: 'ring' | 'ringback' | null = null;

type TimerName =
    | 'ring'
    | 'connect'
    | 'disconnect'
    | 'candidates'
    | 'ended'
    | 'incoming'
    | 'tone';

const timers: Partial<Record<TimerName, ReturnType<typeof setTimeout>>> = {};
const missedListeners = new Set<(peer: CallPeer) => void>();

function newId(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }

    const hex = (digits: number): string =>
        Math.floor(Math.random() * 16 ** digits)
            .toString(16)
            .padStart(digits, '0');

    return `${hex(8)}-${hex(4)}-4${hex(3)}-a${hex(3)}-${hex(12)}`;
}

function clearTimer(name: TimerName): void {
    clearTimeout(timers[name]);
    delete timers[name];
}

function clearCallTimers(): void {
    (['ring', 'connect', 'disconnect', 'candidates', 'ended'] as const).forEach(
        clearTimer,
    );
}

function patch(changes: Partial<ActiveCall>): void {
    if (current.value) {
        Object.assign(current.value, changes);
    }
}

function stopTracks(stream: MediaStream): void {
    stream.getTracks().forEach((track) => track.stop());
}

function freshCall(
    call: Pick<
        ActiveCall,
        'id' | 'conversationId' | 'peer' | 'peerTab' | 'outgoing' | 'status'
    >,
): ActiveCall {
    return {
        ...call,
        reason: null,
        connectedAt: null,
        endedAt: null,
        reconnecting: false,
        micOn: true,
        cameraOn: true,
        hasCamera: true,
        peerVideo: false,
        peerCameraOn: true,
        peerMicOn: true,
        peerHasCamera: true,
    };
}

/**
 * Reads the shared `calls` prop (on or off, STUN servers). Called with
 * every page the server sends, so a change takes effect without a reload.
 */
export function configureCalls(page: Page): void {
    const calls = page.props.calls as Partial<CallsConfig> | undefined;

    config = {
        enabled: calls?.enabled === true,
        iceServers: Array.isArray(calls?.iceServers) ? calls.iceServers : [],
    };

    bindPageHide();
}

/**
 * Whose tab this is. A different person (login, logout, impersonation)
 * ends whatever call or ring the previous one had.
 */
export function setCallsViewer(userId: number | null): void {
    if (userId === me) {
        return;
    }

    me = userId;
    resetCalls();
}

/**
 * Whether this page can place and take calls: calls are on, the browser
 * has WebRTC and camera access, and this is not the native app (whose
 * WebView has no camera or microphone permission yet).
 */
export function callsAvailable(): boolean {
    return (
        typeof window !== 'undefined' &&
        config.enabled &&
        !isNativeShell() &&
        window.isSecureContext &&
        typeof RTCPeerConnection === 'function' &&
        typeof navigator.mediaDevices?.getUserMedia === 'function'
    );
}

/**
 * The call this tab is in (or has just ended), the ring it is being
 * offered, and the two media streams, for the call window.
 */
export function useCalls(): {
    call: DeepReadonly<Ref<ActiveCall | null>>;
    incoming: DeepReadonly<Ref<IncomingCall | null>>;
    localStream: Readonly<ShallowRef<MediaStream | null>>;
    remoteStream: Readonly<ShallowRef<MediaStream | null>>;
} {
    return {
        call: readonly(current),
        incoming: readonly(incoming),
        localStream,
        remoteStream,
    };
}

/**
 * Runs `callback` when a ring went unanswered here (the caller gave up, or
 * it rang out). Returns a function that stops it; inside a component scope
 * that happens on its own.
 */
export function onMissedCall(callback: (peer: CallPeer) => void): () => void {
    if (typeof window === 'undefined') {
        return () => {};
    }

    missedListeners.add(callback);

    const stop = (): void => {
        missedListeners.delete(callback);
    };

    if (getCurrentScope()) {
        onScopeDispose(stop);
    }

    return stop;
}

async function send(
    call: Pick<ActiveCall, 'conversationId' | 'id'>,
    type: CallSignalType,
    toTab: string | null,
    data: Record<string, unknown> = {},
    keepalive = false,
): Promise<boolean> {
    try {
        await requestJson(
            callRoute(call.conversationId),
            {
                call_id: call.id,
                type,
                from_tab: tabId,
                to_tab: toTab,
                data,
            },
            { keepalive },
        );

        return true;
    } catch {
        return false;
    }
}

/**
 * Calls the other person of a direct conversation. Camera and microphone
 * are asked for first, so the permission prompt is over before anyone is
 * disturbed; then the ring goes out for RING_MS.
 */
export async function startCall(
    conversationId: number,
    peer: CallPeer,
): Promise<void> {
    if ((current.value && current.value.status !== 'ended') || incoming.value) {
        return;
    }

    clearTimer('ended');

    const id = newId();

    current.value = freshCall({
        id,
        conversationId,
        peer,
        peerTab: null,
        outgoing: true,
        status: 'ringing',
    });

    if (!callsAvailable()) {
        finish('unsupported');

        return;
    }

    if (connectionState.value !== 'connected') {
        finish('offline');

        return;
    }

    const media = await acquireMedia();

    if (current.value?.id !== id) {
        // Hung up while the permission prompt was open.
        if (media instanceof MediaStream) {
            stopTracks(media);
        }

        return;
    }

    if (!(media instanceof MediaStream)) {
        finish(media);

        return;
    }

    attachLocal(media);

    if (!(await send(current.value, 'ring', null))) {
        finish('signalFailed');

        return;
    }

    if (current.value?.id !== id || current.value.status !== 'ringing') {
        return;
    }

    timers.ring = setTimeout(() => {
        const call = current.value;

        if (call?.id === id && call.status === 'ringing') {
            void send(call, 'cancel', null);
            finish('noAnswer');
        }
    }, RING_MS);

    startTone('ringback');
}

/** Takes the ring this tab is showing. */
export async function acceptCall(): Promise<void> {
    const ring = incoming.value;

    if (!ring || (current.value && current.value.status !== 'ended')) {
        return;
    }

    clearIncoming();
    clearTimer('ended');

    current.value = freshCall({
        id: ring.id,
        conversationId: ring.conversationId,
        peer: ring.peer,
        peerTab: ring.fromTab,
        outgoing: false,
        status: 'connecting',
    });

    const media = await acquireMedia();

    if (current.value?.id !== ring.id) {
        if (media instanceof MediaStream) {
            stopTracks(media);
        }

        return;
    }

    if (!(media instanceof MediaStream)) {
        void send(current.value, 'decline', ring.fromTab, { reason: 'media' });
        finish(media);

        return;
    }

    attachLocal(media);
    rtc = createConnection(current.value);

    if (!(await send(current.value, 'accept', ring.fromTab))) {
        finish('signalFailed');
    }
}

/** Says no to the ring this tab is showing. */
export function declineCall(): void {
    const ring = incoming.value;

    if (!ring) {
        return;
    }

    clearIncoming();
    void send(ring, 'decline', ring.fromTab);
}

/** Ends the call, or gives up ringing. */
export function hangUp(): void {
    const call = current.value;

    if (!call || call.status === 'ended') {
        return;
    }

    if (call.status === 'ringing') {
        void send(call, 'cancel', null);
        finish('cancelled', false);

        return;
    }

    void send(call, 'hangup', call.peerTab);
    finish('hungUp');
}

/** Closes the ended card. */
export function dismissCall(): void {
    clearTimer('ended');

    if (current.value?.status === 'ended') {
        current.value = null;
    }
}

/** Calls the same person again from the ended card. */
export function retryCall(): void {
    const call = current.value;

    if (call?.status !== 'ended') {
        return;
    }

    current.value = null;
    void startCall(call.conversationId, call.peer);
}

export function toggleMic(): void {
    const call = current.value;
    const stream = localStream.value;

    if (!call || !stream) {
        return;
    }

    const on = !call.micOn;

    stream.getAudioTracks().forEach((track) => {
        track.enabled = on;
    });
    patch({ micOn: on });
    sendMediaState();
}

/**
 * Stops or resumes sending video. The track is kept (the camera stays
 * warm) but taken off the connection, so the peer's track goes mute and
 * their window shows the avatar instead of a black frame.
 */
export function toggleCamera(): void {
    const call = current.value;
    const stream = localStream.value;

    if (!call || !stream || !call.hasCamera) {
        return;
    }

    const on = !call.cameraOn;
    const [track] = stream.getVideoTracks();

    if (track) {
        track.enabled = on;
        rtc?.getSenders()
            .filter((sender) => sender.track === track || sender.track === null)
            .forEach((sender) => {
                if (sender.track !== null || on) {
                    void sender.replaceTrack(on ? track : null);
                }
            });
    }

    patch({ cameraOn: on });
    sendMediaState();
}

/** Tells the peer which of this side's camera and microphone are on. */
function sendMediaState(): void {
    const call = current.value;

    if (
        !call ||
        (call.status !== 'connecting' && call.status !== 'connected')
    ) {
        return;
    }

    void send(call, 'media', call.peerTab, {
        camera: call.cameraOn,
        mic: call.micOn,
        hasCamera: call.hasCamera,
    });
}

/** Ends everything, e.g. when someone else signs in. */
export function resetCalls(): void {
    finish('hungUp', false);
    clearIncoming();
    current.value = null;
}

/** A `.call.signal` from the user's own channel (realtime/index.ts). */
export function receiveCallSignal(signal: CallSignal): void {
    if (signal.from_tab === tabId) {
        return;
    }

    if (signal.from.id === me) {
        // Another of my tabs took or refused a ring we both had.
        if (
            (signal.type === 'accept' || signal.type === 'decline') &&
            incoming.value?.id === signal.call_id
        ) {
            clearIncoming();
        }

        return;
    }

    if (signal.to_tab !== null && signal.to_tab !== tabId) {
        return;
    }

    // An ended card is not a call.
    const call = current.value?.status === 'ended' ? null : current.value;
    const data: Record<string, unknown> =
        signal.data && !Array.isArray(signal.data) ? signal.data : {};

    switch (signal.type) {
        case 'ring':
            handleRing(signal, call);

            return;
        case 'cancel':
            if (incoming.value?.id === signal.call_id) {
                clearIncoming();
                announceMissed(signal.from);
            } else if (call?.id === signal.call_id && !call.outgoing) {
                finish('cancelled');
            }

            return;
        case 'accept':
            if (
                call?.id === signal.call_id &&
                call.outgoing &&
                call.status === 'ringing'
            ) {
                clearTimer('ring');
                stopTone();
                patch({ peerTab: signal.from_tab, status: 'connecting' });
                void makeOffer(call);
            }

            return;
        case 'decline':
            if (call?.id === signal.call_id && call.outgoing) {
                if (call.status === 'ringing') {
                    // Their other tabs are still ringing.
                    void send(call, 'cancel', null);
                }

                finish(
                    data.reason === 'media' ? 'peerMediaFailed' : 'declined',
                );
            }

            return;
        case 'busy':
            if (call?.id === signal.call_id && call.outgoing) {
                void send(call, 'cancel', null);
                finish('busy');
            }

            return;
        case 'offer':
            if (call?.id === signal.call_id && !call.outgoing) {
                void takeOffer(call, data.sdp);
            }

            return;
        case 'answer':
            if (call?.id === signal.call_id && call.outgoing) {
                void takeAnswer(data.sdp);
            }

            return;
        case 'candidates':
            if (call?.id === signal.call_id) {
                void takeCandidates(data.candidates);
            }

            return;
        case 'media':
            if (call?.id === signal.call_id) {
                patch({
                    peerCameraOn: data.camera !== false,
                    peerMicOn: data.mic !== false,
                    peerHasCamera: data.hasCamera !== false,
                });
            }

            return;
        case 'hangup':
            if (call?.id === signal.call_id) {
                finish(peerHangupReason(call, data.reason), true, false);
            }
    }
}

/** What the peer's hang-up means here: their failure is our failure too. */
function peerHangupReason(call: ActiveCall, reason: unknown): CallEndReason {
    if (reason === 'failed') {
        return call.status === 'connected'
            ? 'connectionLost'
            : 'connectionFailed';
    }

    return call.status === 'connected' ? 'hungUp' : 'cancelled';
}

function handleRing(signal: CallSignal, call: ActiveCall | null): void {
    if (!callsAvailable()) {
        // This tab can't take it; another tab or device of theirs may.
        return;
    }

    if (call || incoming.value) {
        if (
            call?.id !== signal.call_id &&
            incoming.value?.id !== signal.call_id
        ) {
            void send(
                { conversationId: signal.conversation_id, id: signal.call_id },
                'busy',
                signal.from_tab,
            );
        }

        return;
    }

    incoming.value = {
        id: signal.call_id,
        conversationId: signal.conversation_id,
        peer: signal.from,
        fromTab: signal.from_tab,
    };

    startTone('ring');

    timers.incoming = setTimeout(() => {
        if (incoming.value?.id === signal.call_id) {
            clearIncoming();
            announceMissed(signal.from);
        }
    }, RING_MS);
}

function clearIncoming(): void {
    incoming.value = null;
    clearTimer('incoming');

    if (toneKind === 'ring') {
        stopTone();
    }
}

function announceMissed(peer: CallPeer): void {
    missedListeners.forEach((listener) => listener(peer));
}

/**
 * Camera and microphone; with sound alone when there is no camera or it
 * won't start. A reason instead of a stream when not even that works.
 */
async function acquireMedia(): Promise<MediaStream | CallEndReason> {
    try {
        return await navigator.mediaDevices.getUserMedia({
            audio: true,
            video: {
                width: { ideal: 1280 },
                height: { ideal: 720 },
                facingMode: 'user',
            },
        });
    } catch (error) {
        const name = errorName(error);

        if (
            name === 'NotFoundError' ||
            name === 'DevicesNotFoundError' ||
            name === 'OverconstrainedError' ||
            name === 'NotReadableError'
        ) {
            try {
                return await navigator.mediaDevices.getUserMedia({
                    audio: true,
                });
            } catch (audioError) {
                return mediaReason(audioError);
            }
        }

        return mediaReason(error);
    }
}

function errorName(error: unknown): string {
    return typeof error === 'object' && error !== null && 'name' in error
        ? String((error as { name: unknown }).name)
        : '';
}

function mediaReason(error: unknown): CallEndReason {
    switch (errorName(error)) {
        case 'NotAllowedError':
        case 'PermissionDeniedError':
        case 'SecurityError':
            return 'mediaDenied';
        case 'NotFoundError':
        case 'DevicesNotFoundError':
        case 'OverconstrainedError':
            return 'noDevices';
        case 'NotReadableError':
        case 'TrackStartError':
        case 'AbortError':
            return 'mediaBusy';
        default:
            return 'mediaFailed';
    }
}

function attachLocal(stream: MediaStream): void {
    const hasCamera = stream.getVideoTracks().length > 0;

    localStream.value = stream;
    patch({ hasCamera, cameraOn: hasCamera });
}

function createConnection(call: ActiveCall): RTCPeerConnection {
    const connection = new RTCPeerConnection({ iceServers: config.iceServers });
    const stream = localStream.value;

    stream?.getTracks().forEach((track) => connection.addTrack(track, stream));

    connection.ontrack = (event) => {
        // A new stream object each time, so the <video> picks the change up.
        const tracks = remoteStream.value?.getTracks() ?? [];

        if (!tracks.includes(event.track)) {
            tracks.push(event.track);
        }

        remoteStream.value = new MediaStream(tracks);

        if (event.track.kind === 'video') {
            patch({ peerVideo: true });
            event.track.onended = () => patch({ peerVideo: false });
        }
    };

    connection.onicecandidate = (event) => {
        if (event.candidate) {
            outgoingCandidates.push(event.candidate.toJSON());
            timers.candidates ??= setTimeout(
                () => flushCandidates(call),
                CANDIDATE_BATCH_MS,
            );
        } else {
            flushCandidates(call);
        }
    };

    connection.onconnectionstatechange = () => {
        if (current.value?.id !== call.id) {
            return;
        }

        switch (connection.connectionState) {
            case 'connected': {
                clearTimer('connect');
                clearTimer('disconnect');

                const first = current.value.connectedAt === null;

                patch({
                    status: 'connected',
                    reconnecting: false,
                    connectedAt: current.value.connectedAt ?? Date.now(),
                });

                if (first) {
                    sendMediaState();
                }

                return;
            }
            case 'disconnected':
                // Often a blip (a phone changing network): give it a moment.
                patch({ reconnecting: true });
                timers.disconnect ??= setTimeout(
                    () => lost(call),
                    DISCONNECT_GRACE_MS,
                );

                return;
            case 'failed':
                lost(call);
        }
    };

    connection.oniceconnectionstatechange = () => {
        if (connection.iceConnectionState === 'failed') {
            lost(call);
        }
    };

    timers.connect = setTimeout(() => {
        if (
            current.value?.id === call.id &&
            current.value.status === 'connecting'
        ) {
            finish('connectionFailed');
        }
    }, CONNECT_MS);

    return connection;
}

function lost(call: ActiveCall): void {
    const now = current.value;

    if (now?.id !== call.id || now.status === 'ended') {
        return;
    }

    finish(now.connectedAt ? 'connectionLost' : 'connectionFailed');
}

function flushCandidates(call: ActiveCall): void {
    clearTimer('candidates');

    const batch = outgoingCandidates;

    outgoingCandidates = [];

    if (!batch.length || current.value?.id !== call.id) {
        return;
    }

    void send(call, 'candidates', call.peerTab, { candidates: batch });
}

async function makeOffer(call: ActiveCall): Promise<void> {
    const connection = (rtc = createConnection(call));

    try {
        await connection.setLocalDescription(await connection.createOffer());

        if (
            !(await send(call, 'offer', call.peerTab, {
                sdp: connection.localDescription?.toJSON(),
            }))
        ) {
            finish('signalFailed');
        }
    } catch (error) {
        reportFailure('offer', error);
        finish('setupFailed');
    }
}

async function takeOffer(call: ActiveCall, sdp: unknown): Promise<void> {
    const connection = rtc;

    if (!connection || !isDescription(sdp)) {
        return;
    }

    try {
        await connection.setRemoteDescription(sdp);
        await drainCandidates(connection);
        await connection.setLocalDescription(await connection.createAnswer());

        if (
            !(await send(call, 'answer', call.peerTab, {
                sdp: connection.localDescription?.toJSON(),
            }))
        ) {
            finish('signalFailed');
        }
    } catch (error) {
        reportFailure('answer', error);
        finish('setupFailed');
    }
}

async function takeAnswer(sdp: unknown): Promise<void> {
    const connection = rtc;

    if (!connection || !isDescription(sdp)) {
        return;
    }

    try {
        await connection.setRemoteDescription(sdp);
        await drainCandidates(connection);
    } catch (error) {
        reportFailure('remote answer', error);
        finish('setupFailed');
    }
}

/** WebRTC refused a step: worth a line in the console when someone asks why a call failed. */
function reportFailure(step: string, error: unknown): void {
    console.error(`Video call: the ${step} failed.`, error);
}

async function takeCandidates(candidates: unknown): Promise<void> {
    if (!Array.isArray(candidates)) {
        return;
    }

    queuedCandidates.push(...candidates.filter(isCandidate));

    if (rtc?.remoteDescription) {
        await drainCandidates(rtc);
    }
}

/** Adds the candidates that waited for the remote description. */
async function drainCandidates(connection: RTCPeerConnection): Promise<void> {
    const batch = queuedCandidates;

    queuedCandidates = [];

    for (const candidate of batch) {
        try {
            await connection.addIceCandidate(candidate);
        } catch {
            // A stale or malformed candidate: the others still count.
        }
    }
}

function isDescription(value: unknown): value is RTCSessionDescriptionInit {
    return (
        typeof value === 'object' &&
        value !== null &&
        typeof (value as { sdp?: unknown }).sdp === 'string' &&
        typeof (value as { type?: unknown }).type === 'string'
    );
}

function isCandidate(value: unknown): value is RTCIceCandidateInit {
    return typeof value === 'object' && value !== null && 'candidate' in value;
}

const FAILURES: CallEndReason[] = [
    'connectionFailed',
    'connectionLost',
    'setupFailed',
];

/**
 * Ends the call this tab is in: closes the connection, stops the camera
 * and microphone, and leaves the ended card up with `reason` (unless
 * `show` is false, e.g. the user's own cancel). A failure after the answer
 * is told to the peer, so they don't wait for their own timeout; not when
 * the end came from them (`notifyPeer` false).
 */
function finish(reason: CallEndReason, show = true, notifyPeer = true): void {
    const call = current.value;

    if (!call || call.status === 'ended') {
        return;
    }

    if (
        notifyPeer &&
        FAILURES.includes(reason) &&
        (call.status === 'connecting' || call.status === 'connected')
    ) {
        void send(call, 'hangup', call.peerTab, { reason: 'failed' });
    }

    clearCallTimers();
    stopTone();
    rtc?.close();
    rtc = null;
    queuedCandidates = [];
    outgoingCandidates = [];

    if (localStream.value) {
        stopTracks(localStream.value);
    }

    localStream.value = null;
    remoteStream.value = null;

    if (!show) {
        current.value = null;

        return;
    }

    patch({
        status: 'ended',
        reason,
        endedAt: Date.now(),
        reconnecting: false,
    });

    if (reason === 'hungUp') {
        timers.ended = setTimeout(dismissCall, ENDED_CARD_MS);
    }
}

/**
 * The ring (two rising pairs) or the caller's ringback (one low note),
 * repeated until stopped. Silent before the user's first interaction with
 * the page, when they turned sounds off, and in tabs where another tab
 * has just rung.
 */
function startTone(kind: 'ring' | 'ringback'): void {
    stopTone();
    toneKind = kind;

    const tick = (): void => {
        if (toneKind !== kind) {
            return;
        }

        const context = audioContext();

        if (
            context &&
            context.state === 'running' &&
            isMessageSoundEnabled() &&
            claimSound(`manteion.calls.${kind}`, 1_000)
        ) {
            const now = context.currentTime;

            if (kind === 'ring') {
                tone(context, 880, now, 0.18);
                tone(context, 1318.5, now + 0.14, 0.3);
                tone(context, 880, now + 0.5, 0.18);
                tone(context, 1318.5, now + 0.64, 0.3);
            } else {
                tone(context, 440, now, 0.9);
            }
        }

        timers.tone = setTimeout(
            tick,
            kind === 'ring' ? RING_TONE_MS : RINGBACK_MS,
        );
    };

    tick();
}

function stopTone(): void {
    toneKind = null;
    clearTimer('tone');
}

/** Tells the peer when the tab closes mid-call, so they don't wait for the connection to time out. */
function bindPageHide(): void {
    if (pageHideBound || typeof window === 'undefined') {
        return;
    }

    pageHideBound = true;

    window.addEventListener('pagehide', () => {
        const call = current.value;

        if (!call || call.status === 'ended') {
            return;
        }

        if (call.status === 'ringing') {
            void send(call, 'cancel', null, {}, true);
        } else {
            void send(call, 'hangup', call.peerTab, {}, true);
        }
    });
}

import { getCurrentScope, onScopeDispose } from 'vue';
import type { ActivityItem, IncomingMessage } from '@/types';

type Listener<T> = (payload: T) => void;

const activityListeners = new Set<Listener<ActivityItem>>();
const incomingListeners = new Set<Listener<IncomingMessage>>();

function listen<T>(listeners: Set<Listener<T>>, callback: Listener<T>) {
    // Nothing arrives during a server render, and its scopes are never
    // disposed: a listener added there would stay forever.
    if (typeof window === 'undefined') {
        return () => {};
    }

    listeners.add(callback);

    const stop = (): void => {
        listeners.delete(callback);
    };

    if (getCurrentScope()) {
        onScopeDispose(stop);
    }

    return stop;
}

function emit<T>(listeners: Set<Listener<T>>, payload: T): void {
    listeners.forEach((listener) => {
        try {
            listener(payload);
        } catch (error) {
            console.error(error);
        }
    });
}

/**
 * Calls `callback` with every new activity item for the signed-in user as it
 * is created (the broadcast of a database notification), e.g. to prepend it
 * on the Activity page or show a toast. Returns a function that stops it;
 * inside a component that also happens when it unmounts.
 */
export function onActivity(callback: Listener<ActivityItem>): () => void {
    return listen(activityListeners, callback);
}

/**
 * Calls `callback` for every message someone else sends in one of the
 * user's conversations they have not muted (`.message.received`), wherever
 * the user is in the app. Returns a function that stops it; inside a
 * component that also happens when it unmounts.
 */
export function onIncomingMessage(
    callback: Listener<IncomingMessage>,
): () => void {
    return listen(incomingListeners, callback);
}

/**
 * A broadcast notification as an ActivityItem. Laravel adds the
 * notification's `id` and its class as `type` (the full class name unless
 * the notification overrides broadcastType()); the client knows the
 * basename. Something that arrives live is unread.
 */
function toActivityItem(payload: Record<string, unknown>): ActivityItem {
    const type = String(payload.type ?? '');

    return {
        ...(payload as unknown as ActivityItem),
        type: type.slice(type.lastIndexOf('\\') + 1) as ActivityItem['type'],
        excerpt: (payload.excerpt as string | null | undefined) ?? null,
        pending: (payload.pending as boolean | null | undefined) ?? null,
        unread: (payload.unread as boolean | undefined) ?? true,
        created_at:
            (payload.created_at as string | undefined) ??
            new Date().toISOString(),
    };
}

/** Feeds a `.notification()` broadcast from the user channel to the listeners. */
export function emitActivity(payload: Record<string, unknown>): void {
    emit(activityListeners, toActivityItem(payload));
}

/** Feeds a `.message.received` broadcast from the user channel to the listeners. */
export function emitIncomingMessage(payload: IncomingMessage): void {
    emit(incomingListeners, payload);
}

const SOUND_KEY = 'manteion.sound';
const SOUND_PLAYED_KEY = 'manteion.sound.played';

let audio: AudioContext | null = null;
let soundUnlockStarted = false;

/** Whether the message sound is on (the default) for this browser. */
export function isMessageSoundEnabled(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    try {
        const stored = window.localStorage.getItem(SOUND_KEY);

        return !['off', '0', 'false'].includes(stored ?? '');
    } catch {
        return true;
    }
}

/** Turns the message sound on or off for this browser. */
export function setMessageSoundEnabled(enabled: boolean): void {
    try {
        window.localStorage.setItem(SOUND_KEY, enabled ? 'on' : 'off');
    } catch {
        // Storage blocked (private mode): the choice lasts for this page only.
    }
}

/**
 * Whether another tab of the app just played the same sound (every open
 * tab receives the broadcast), claiming it for this one if not. `key` names
 * the sound; `windowMs` is how long a claim lasts.
 */
export function claimSound(
    key: string = SOUND_PLAYED_KEY,
    windowMs: number = 1500,
): boolean {
    try {
        const now = Date.now();
        const last = Number(window.localStorage.getItem(key));

        if (now - last < windowMs) {
            return false;
        }

        window.localStorage.setItem(key, String(now));
    } catch {
        // No storage to coordinate through: play.
    }

    return true;
}

/**
 * The audio context, made and started right now, for a sound that can't
 * wait for a tap: a call ringing. Whether it may play yet is for the caller
 * to find out from `resume()`: the promise settles at once where the
 * browser lets a page start audio by itself (the Android app, an installed
 * app, a site the user plays media on, a page they have tapped before) and
 * otherwise waits for the first tap. Don't read `state` for that: Firefox
 * reports a context it has just made as suspended for a moment even when
 * it is allowed to run. Null when there is no Web Audio.
 */
export function ensureAudio(): AudioContext | null {
    if (typeof window === 'undefined') {
        return null;
    }

    const Context =
        window.AudioContext ??
        (window as unknown as { webkitAudioContext?: typeof AudioContext })
            .webkitAudioContext;

    if (!Context) {
        return null;
    }

    try {
        audio ??= new Context();
    } catch {
        return null;
    }

    return audio;
}

/**
 * A sine note: `frequency` Hz from `start` (context time) for `length`
 * seconds, at `level` (0–1). A chime by default, fading all the way; with
 * `sustain` it holds its level and stops short, like a ring.
 */
export function tone(
    context: AudioContext,
    frequency: number,
    start: number,
    length: number,
    level: number = 0.16,
    sustain: boolean = false,
): void {
    const oscillator = context.createOscillator();
    const gain = context.createGain();

    oscillator.type = 'sine';
    oscillator.frequency.setValueAtTime(frequency, start);
    gain.gain.setValueAtTime(0.0001, start);
    gain.gain.exponentialRampToValueAtTime(level, start + 0.012);

    if (sustain) {
        gain.gain.setValueAtTime(level, start + length - 0.03);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + length);
    } else {
        gain.gain.exponentialRampToValueAtTime(0.0001, start + length);
    }

    oscillator.connect(gain).connect(context.destination);
    oscillator.start(start);
    oscillator.stop(start + length + 0.02);
}

/**
 * A short two-note chime for an incoming message, synthesised with Web Audio
 * (no asset to load). Silent when the user turned it off, before they have
 * interacted with the page (browsers only allow audio after that), or when
 * another tab has just played it.
 */
export function playMessageSound(): void {
    if (
        !audio ||
        audio.state !== 'running' ||
        !isMessageSoundEnabled() ||
        !claimSound()
    ) {
        return;
    }

    const now = audio.currentTime;

    tone(audio, 880, now, 0.16);
    tone(audio, 1318.5, now + 0.1, 0.28);
}

/**
 * Prepares the audio context on the user's first tap or key press, the only
 * moment browsers allow it to start. Client only, once.
 */
export function setupMessageSound(): void {
    if (typeof window === 'undefined' || soundUnlockStarted) {
        return;
    }

    soundUnlockStarted = true;

    const Context =
        window.AudioContext ??
        (window as unknown as { webkitAudioContext?: typeof AudioContext })
            .webkitAudioContext;

    if (!Context) {
        return;
    }

    const unlock = (): void => {
        try {
            audio ??= new Context();
            void audio.resume();
        } catch {
            return;
        }

        window.removeEventListener('pointerdown', unlock, true);
        window.removeEventListener('keydown', unlock, true);
    };

    window.addEventListener('pointerdown', unlock, true);
    window.addEventListener('keydown', unlock, true);
}

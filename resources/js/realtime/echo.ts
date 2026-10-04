import { http } from '@inertiajs/vue3';
import { configureEcho, echo } from '@laravel/echo-vue';
import type { Broadcaster } from 'laravel-echo';
import { getCurrentScope, onScopeDispose, readonly, ref } from 'vue';
import type { Ref } from 'vue';
import { socketId } from './http';

export type ConnectionState = 'connecting' | 'connected' | 'disconnected';

/** A private channel on the Reverb connection. */
export type PrivateChannel = Broadcaster['reverb']['private'];

const state = ref<ConnectionState>('connecting');

/**
 * The WebSocket connection to Reverb. "connecting" until the first attempt
 * settles, and again while it retries after a drop; "disconnected" once it
 * has given up for now (offline, server unreachable — it keeps retrying) or
 * when this environment has no Reverb at all. Always "connecting" on the
 * server.
 */
export const connectionState: Readonly<Ref<ConnectionState>> = readonly(state);

const reconnectListeners = new Set<() => void>();
const channelUsers = new Map<string, number>();

let started = false;
let live = false;
let connectedBefore = false;

function toConnectionState(pusherState: string): ConnectionState {
    switch (pusherState) {
        case 'connected':
            return 'connected';
        case 'initialized':
        case 'connecting':
            return 'connecting';
        default:
            // unavailable, failed, disconnected
            return 'disconnected';
    }
}

export type ReverbClient = {
    key: string;
    host: string | null;
    port: number;
    scheme: string;
};

/**
 * Connects Echo to Reverb: where the server says (the shared `reverb` page
 * prop), so the same build works on any host; failing that, the
 * VITE_REVERB_* values baked into the build. Client only, once; app.ts
 * calls it through setupRealtime().
 */
export function setupEcho(client: ReverbClient | null = null): void {
    if (typeof window === 'undefined' || started) {
        return;
    }

    started = true;

    const env = import.meta.env;
    const key = client?.key ?? env.VITE_REVERB_APP_KEY;

    if (!key) {
        // No Reverb here: report "disconnected" (after hydration) so the
        // fallbacks — the badge poll — take over.
        queueMicrotask(() => {
            state.value = 'disconnected';
        });

        return;
    }

    const scheme = client?.scheme ?? (env.VITE_REVERB_SCHEME || 'https');
    const forceTLS = scheme === 'https';
    const port =
        (client ? client.port : Number(env.VITE_REVERB_PORT)) ||
        (forceTLS ? 443 : 80);
    const host = client ? client.host : env.VITE_REVERB_HOST;

    configureEcho({
        broadcaster: 'reverb',
        key,
        wsHost: host || window.location.hostname,
        wsPort: port,
        wssPort: port,
        forceTLS,
        enabledTransports: ['ws', 'wss'],
    });

    live = true;

    const connection = echo<'reverb'>().connector.pusher.connection;

    connection.bind('state_change', (change: { current: string }) => {
        state.value = toConnectionState(change.current);
    });

    // Pusher re-subscribes every channel by itself after a reconnect; these
    // callbacks are for catching up on what was missed in between.
    connection.bind('connected', () => {
        if (connectedBefore) {
            reconnectListeners.forEach((listener) => listener());
        }

        connectedBefore = true;
    });

    // Inertia visits and useHttp calls carry the socket id as well, so an
    // endpoint that broadcasts `->toOthers()` skips the tab that asked.
    http.onRequest((config) => {
        const id = socketId();

        return id
            ? { ...config, headers: { ...config.headers, 'X-Socket-ID': id } }
            : config;
    });
}

/** Whether Echo is running (client side, with a Reverb key). */
export function echoIsLive(): boolean {
    return live;
}

/**
 * Runs `callback` every time the connection comes back after a drop (not on
 * the first connect). Returns a function that stops it; inside a component
 * or effect scope that also happens on its own when the scope ends.
 */
export function onReconnect(callback: () => void): () => void {
    // Never on the server: its render scopes are not disposed.
    if (typeof window === 'undefined') {
        return () => {};
    }

    reconnectListeners.add(callback);

    const stop = (): void => {
        reconnectListeners.delete(callback);
    };

    if (getCurrentScope()) {
        onScopeDispose(stop);
    }

    return stop;
}

/**
 * Subscribes to a private channel, shared by everyone who asks for the same
 * name; it is left once the last of them calls `release()`. Unbind your own
 * listeners before releasing, since others may still hold the channel.
 * Returns null when Echo is not running (on the server, or without Reverb).
 *
 * Use this (or the composables built on it) rather than echo-vue's useEcho
 * for the channels the realtime modules manage: useEcho keeps its own count
 * and would leave a channel these still listen on.
 */
export function joinPrivateChannel(
    name: string,
): { channel: PrivateChannel; release: () => void } | null {
    if (!live) {
        return null;
    }

    const channel = echo<'reverb'>().private(name);
    let released = false;

    channelUsers.set(name, (channelUsers.get(name) ?? 0) + 1);

    return {
        channel,
        release: () => {
            if (released) {
                return;
            }

            released = true;

            const users = (channelUsers.get(name) ?? 1) - 1;

            if (users > 0) {
                channelUsers.set(name, users);

                return;
            }

            channelUsers.delete(name);
            echo().leaveChannel(`private-${name}`);
        },
    };
}

/**
 * Runs `callback` whenever the channel's subscription succeeds — right away
 * if it already has — and again after every re-subscription that follows a
 * reconnect. Returns a function that unbinds it.
 */
export function onSubscribed(
    channel: PrivateChannel,
    callback: () => void,
): () => void {
    let active = true;
    const handler = (): void => {
        if (active) {
            callback();
        }
    };

    channel.subscription.bind('pusher:subscription_succeeded', handler);

    if (channel.subscription.subscribed) {
        queueMicrotask(handler);
    }

    return () => {
        active = false;
        channel.subscription.unbind('pusher:subscription_succeeded', handler);
    };
}

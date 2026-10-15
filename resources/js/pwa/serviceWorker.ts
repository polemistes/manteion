import { router } from '@inertiajs/vue3';
import { getCurrentScope, onScopeDispose } from 'vue';
import { ManteionPush } from '@/native/plugin';
import { xsrfToken } from '@/realtime/http';
import { isNativeShell } from './platform';

/**
 * The `data` of a push that the service worker handed to this page instead
 * of showing it, because the page was focused on the push's own URL.
 */
export type PushData = {
    type?: string;
    url?: string;
    conversation_id?: number;
    message_id?: number;
    badge_count?: number;
    [key: string]: unknown;
};

type PushListener = (data: PushData) => void;

/** A plain static file (public/sw.js), not a route; it must sit at the root to control every page. */
const SERVICE_WORKER_URL = '/sw.js';

const pushListeners = new Set<PushListener>();

let started = false;

function supported(): boolean {
    return typeof window !== 'undefined' && 'serviceWorker' in navigator;
}

function handleMessage(event: MessageEvent): void {
    const message = event.data as {
        type?: unknown;
        url?: unknown;
        data?: unknown;
    } | null;

    if (!message || typeof message !== 'object') {
        return;
    }

    // A notification was clicked while this window was open: show its page
    // with an Inertia visit rather than a full reload. The reply tells the
    // worker not to fall back to navigating the window itself.
    if (message.type === 'navigate' && typeof message.url === 'string') {
        const url = new URL(message.url, window.location.href);

        if (url.origin !== window.location.origin) {
            return;
        }

        event.ports[0]?.postMessage({ type: 'navigating' });
        router.visit(url.pathname + url.search + url.hash);

        return;
    }

    if (message.type === 'push') {
        dispatchPushMessage((message.data ?? {}) as PushData);
    }
}

/**
 * Hands a push that arrived while the page was open to onPushMessage()'s
 * listeners: from the service worker, or from the Android app.
 */
export function dispatchPushMessage(data: PushData): void {
    pushListeners.forEach((listener) => listener(data));
}

/**
 * Registers public/sw.js (offline fallback, push notifications) once the
 * page has loaded, and starts listening to it. Client only, once.
 */
export function registerServiceWorker(): void {
    if (!supported() || started) {
        return;
    }

    started = true;

    navigator.serviceWorker.addEventListener('message', handleMessage);
    navigator.serviceWorker.startMessages();

    const register = (): void => {
        navigator.serviceWorker
            .register(SERVICE_WORKER_URL, { scope: '/' })
            .catch((error: unknown) => {
                console.warn('Service worker registration failed', error);
            });
    };

    if (document.readyState === 'complete') {
        register();
    } else {
        window.addEventListener('load', register, { once: true });
    }
}

/**
 * The service worker's registration once it is active, registering it now
 * if the page hasn't yet (push needs an active worker to subscribe with).
 */
export async function activeServiceWorker(): Promise<ServiceWorkerRegistration> {
    if (!(await navigator.serviceWorker.getRegistration())) {
        await navigator.serviceWorker.register(SERVICE_WORKER_URL, {
            scope: '/',
        });
    }

    return navigator.serviceWorker.ready;
}

/**
 * Calls `callback` with the data of each push the service worker handed to
 * this page instead of showing a notification. Returns a function that
 * stops it; inside a component that also happens when it unmounts.
 */
export function onPushMessage(callback: PushListener): () => void {
    // Never on the server: its render scopes are not disposed.
    if (typeof window === 'undefined') {
        return () => {};
    }

    pushListeners.add(callback);

    const stop = (): void => {
        pushListeners.delete(callback);
    };

    if (getCurrentScope()) {
        onScopeDispose(stop);
    }

    return stop;
}

/**
 * Has the service worker POST `body` as JSON to `url` (same origin) for a
 * page that is going away: requests of its own can be dropped as it
 * unloads, but the worker outlives it. False when no worker controls the
 * page, so the caller can use a beacon instead.
 */
export function postFromServiceWorker(
    url: string,
    body: Record<string, unknown>,
): boolean {
    const controller = supported() ? navigator.serviceWorker.controller : null;

    if (!controller) {
        return false;
    }

    controller.postMessage({ type: 'post', url, body, xsrf: xsrfToken() });

    return true;
}

/**
 * Closes the shown notifications with this tag (e.g. `conversation-12`), or
 * all of the app's notifications without one.
 */
export function clearNotifications(tag?: string): void {
    // The Android app shows its notifications itself.
    if (isNativeShell()) {
        void ManteionPush.cancel({ tag }).catch(() => undefined);

        return;
    }

    if (!supported()) {
        return;
    }

    navigator.serviceWorker
        .getRegistration()
        .then((registration) =>
            registration?.active?.postMessage({
                type: 'clear-notifications',
                tag,
            }),
        )
        .catch(() => {
            // No worker, no notifications to clear.
        });
}

/**
 * Shows `count` on the installed app's icon, or clears it at 0. A no-op
 * where the Badging API is missing or the app isn't installed.
 */
export function setAppBadge(count: number): void {
    if (typeof window === 'undefined' || !('setAppBadge' in navigator)) {
        return;
    }

    const update =
        count > 0 ? navigator.setAppBadge(count) : navigator.clearAppBadge();

    update.catch(() => {
        // Not allowed here (iOS without notification permission, say).
    });
}

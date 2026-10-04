import { requestJson } from '@/realtime/http';
import {
    destroy as destroyPushSubscription,
    store as storePushSubscription,
} from '@/routes/push-subscriptions';
import { iosVersion, isIos, isNativeShell, isStandalone } from './platform';
import { activeServiceWorker } from './serviceWorker';

/**
 * Whether this browser can get pushes: "needs-install" is iOS/iPadOS 16.4+
 * in a browser tab, where web push only works once the app is added to the
 * home screen and opened from there.
 */
export type PushSupport = 'supported' | 'needs-install' | 'unsupported';

export type PushPermission = NotificationPermission | 'unsupported';

/**
 * - subscribed: this device now gets pushes.
 * - dismissed: the permission prompt was closed without an answer.
 * - denied: notifications are blocked for the site (only the browser's
 *   site settings can undo that).
 * - unsupported: see pushSupport().
 * - failed: the browser or the server refused the subscription.
 */
export type SubscribeResult =
    'subscribed' | 'dismissed' | 'denied' | 'unsupported' | 'failed';

function hasPushApis(): boolean {
    return (
        typeof window !== 'undefined' &&
        'serviceWorker' in navigator &&
        'PushManager' in window &&
        'Notification' in window
    );
}

export function pushSupport(): PushSupport {
    if (typeof window === 'undefined' || isNativeShell()) {
        return 'unsupported';
    }

    if (isIos() && !isStandalone()) {
        const version = iosVersion();

        return !version ||
            version[0] > 16 ||
            (version[0] === 16 && version[1] >= 4)
            ? 'needs-install'
            : 'unsupported';
    }

    return hasPushApis() ? 'supported' : 'unsupported';
}

/** The site's notification permission in this browser. */
export function permission(): PushPermission {
    return hasPushApis() ? Notification.permission : 'unsupported';
}

function urlBase64ToUint8Array(base64String: string): Uint8Array<ArrayBuffer> {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');
    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(new ArrayBuffer(rawData.length));

    for (let i = 0; i < rawData.length; i++) {
        outputArray[i] = rawData.charCodeAt(i);
    }

    return outputArray;
}

function sameKey(current: ArrayBuffer | null, wanted: Uint8Array): boolean {
    if (!current || current.byteLength !== wanted.byteLength) {
        return false;
    }

    const bytes = new Uint8Array(current);

    return bytes.every((byte, index) => byte === wanted[index]);
}

/** The payload encryption to ask for; every current browser does aes128gcm. */
function contentEncoding(): string {
    return PushManager.supportedContentEncodings?.[0] ?? 'aes128gcm';
}

async function currentSubscription(): Promise<PushSubscription | null> {
    const registration = await navigator.serviceWorker.getRegistration();

    return (await registration?.pushManager.getSubscription()) ?? null;
}

/** This browser's push endpoint, while it has a subscription. */
export async function currentEndpoint(): Promise<string | null> {
    if (!hasPushApis()) {
        return null;
    }

    try {
        return (await currentSubscription())?.endpoint ?? null;
    } catch {
        return null;
    }
}

/** Whether this browser holds a push subscription (with permission). */
export async function isSubscribed(): Promise<boolean> {
    if (!hasPushApis() || Notification.permission !== 'granted') {
        return false;
    }

    try {
        return (await currentSubscription()) !== null;
    } catch {
        return false;
    }
}

/**
 * Asks for notification permission, subscribes this browser and registers
 * it as one of the user's devices (POST /push-subscriptions). Must be called
 * straight from a tap or click: browsers only show the prompt then.
 */
export async function subscribe(
    vapidPublicKey: string,
): Promise<SubscribeResult> {
    if (pushSupport() !== 'supported') {
        return 'unsupported';
    }

    // Ask before anything else is awaited, or Safari loses the user's tap
    // and never shows the prompt.
    const answer = await Notification.requestPermission();

    if (answer === 'denied') {
        return 'denied';
    }

    if (answer !== 'granted') {
        return 'dismissed';
    }

    let created: PushSubscription | null = null;

    try {
        const registration = await activeServiceWorker();
        const applicationServerKey = urlBase64ToUint8Array(vapidPublicKey);
        let subscription = await registration.pushManager.getSubscription();

        // Subscribed under an older VAPID key: that one can't be reused.
        if (
            subscription &&
            !sameKey(
                subscription.options.applicationServerKey,
                applicationServerKey,
            )
        ) {
            await subscription.unsubscribe();
            subscription = null;
        }

        if (!subscription) {
            subscription = created = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey,
            });
        }

        const json = subscription.toJSON();

        await requestJson(storePushSubscription(), {
            endpoint: json.endpoint ?? subscription.endpoint,
            key: json.keys?.p256dh ?? null,
            token: json.keys?.auth ?? null,
            content_encoding: contentEncoding(),
        });

        return 'subscribed';
    } catch {
        // Don't leave a subscription behind that the server doesn't know of.
        await created?.unsubscribe().catch(() => false);

        return 'failed';
    }
}

/**
 * Stops pushes to this browser: drops its subscription and removes the
 * device from the user's account (DELETE /push-subscriptions).
 */
export async function unsubscribe(): Promise<void> {
    if (!hasPushApis()) {
        return;
    }

    try {
        const subscription = await currentSubscription();

        if (!subscription) {
            return;
        }

        const endpoint = subscription.endpoint;

        await subscription.unsubscribe();
        await requestJson(destroyPushSubscription(), { endpoint });
    } catch {
        // The server drops an endpoint that stopped working the next time
        // it pushes to it.
    }
}

/**
 * Drops this browser's subscription without telling the server (which can't
 * be asked once signed out); its row goes the first time a push to it
 * fails. Used on logout so a shared browser stops getting the previous
 * user's notifications.
 */
export async function forgetSubscription(): Promise<void> {
    if (!hasPushApis()) {
        return;
    }

    try {
        await (await currentSubscription())?.unsubscribe();
    } catch {
        // Nothing more to do from here.
    }
}

import type { PushPermission, PushSupport, SubscribeResult } from '@/pwa/push';
import { requestJson } from '@/realtime/http';
import {
    destroy as destroyPushSubscription,
    store as storePushSubscription,
} from '@/routes/push-subscriptions';
import { ManteionPush } from './plugin';

/**
 * Push notifications in the Android app: the same functions as
 * pwa/push.ts, which hands over to these inside the app. The device is the
 * app's FCM registration token, registered with the site as a push
 * subscription of kind "fcm". The token is kept here with the user it was
 * registered for, so the app knows whether it is on and for whom.
 */
const DEVICE_KEY = 'manteion.appDevice';

type Device = { token: string; userId: number | null };

function storedDevice(): Device | null {
    try {
        const device = JSON.parse(
            window.localStorage.getItem(DEVICE_KEY) ?? 'null',
        ) as Device | null;

        return device && typeof device.token === 'string' ? device : null;
    } catch {
        return null;
    }
}

function storeDevice(device: Device | null): void {
    try {
        if (device) {
            window.localStorage.setItem(DEVICE_KEY, JSON.stringify(device));
        } else {
            window.localStorage.removeItem(DEVICE_KEY);
        }
    } catch {
        // The WebView's storage is always there; nothing to do if not.
    }
}

async function register(token: string, userId: number | null): Promise<void> {
    await requestJson(storePushSubscription(), {
        kind: 'fcm',
        endpoint: token,
    });
    storeDevice({ token, userId });
}

export function pushSupport(): PushSupport {
    return 'supported';
}

export async function permission(): Promise<PushPermission> {
    try {
        const { receive } = await ManteionPush.checkPermissions();

        return receive === 'prompt' ? 'default' : receive;
    } catch {
        return 'unsupported';
    }
}

export async function currentEndpoint(): Promise<string | null> {
    return storedDevice()?.token ?? null;
}

export async function isSubscribed(): Promise<boolean> {
    return storedDevice() !== null && (await permission()) === 'granted';
}

/**
 * Asks Android for permission (once; after a "no" only its settings can
 * turn it on), gets the FCM token and registers it as the user's device.
 */
export async function subscribe(
    userId: number | null,
): Promise<SubscribeResult> {
    let answer: string;

    try {
        answer = (await ManteionPush.requestPermissions()).receive;
    } catch {
        return 'failed';
    }

    if (answer === 'denied') {
        return 'denied';
    }

    if (answer !== 'granted') {
        return 'dismissed';
    }

    let token: string;

    try {
        token = (await ManteionPush.getToken()).token;
    } catch (error) {
        return (error as { code?: string }).code === 'UNCONFIGURED'
            ? 'unsupported'
            : 'failed';
    }

    try {
        await register(token, userId);

        return 'subscribed';
    } catch {
        return 'failed';
    }
}

/**
 * Stops pushes to the app: removes the device from the account and drops
 * the token, so FCM refuses it from now on.
 */
export async function unsubscribe(): Promise<void> {
    const device = storedDevice();

    storeDevice(null);

    if (device) {
        await requestJson(destroyPushSubscription(), {
            endpoint: device.token,
        }).catch(() => null);
    }

    await ManteionPush.deleteToken().catch(() => undefined);
}

/**
 * On logout: the same, sent while the session still exists. Should the
 * request fail, the dropped token makes the next push to it fail, and the
 * server removes the device then.
 */
export async function forgetSubscription(): Promise<void> {
    await unsubscribe();
}

/**
 * At every start and whenever FCM hands out a new token: registers the new
 * token in place of the old one, so pushes keep arriving. A device turned
 * on for someone else (whose session ran out on this phone) is dropped,
 * with that user's notifications, rather than moved to whoever is signed
 * in now; they can turn it on.
 */
export async function refreshRegistration(
    userId: number | null,
): Promise<void> {
    const device = storedDevice();

    if (!device || userId === null) {
        return;
    }

    if (device.userId !== userId) {
        storeDevice(null);
        // The previous user's notifications and conversation shortcuts go
        // too: their text is not for whoever is signed in now, and a reply
        // from one would go out under this account.
        await ManteionPush.cancel({}).catch(() => undefined);
        await ManteionPush.deleteToken().catch(() => undefined);

        return;
    }

    if ((await permission()) !== 'granted') {
        return;
    }

    try {
        const { token } = await ManteionPush.getToken();

        if (token !== device.token) {
            await register(token, userId);
            await requestJson(destroyPushSubscription(), {
                endpoint: device.token,
            }).catch(() => null);
        }
    } catch {
        // Tried again at the next start.
    }
}

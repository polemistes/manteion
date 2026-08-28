import { router } from '@inertiajs/vue3';

function isSupported(): boolean {
    return 'serviceWorker' in navigator && 'PushManager' in window;
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

function contentEncoding(): string {
    const supported = (
        window.PushManager as unknown as {
            supportedContentEncodings?: string[];
        }
    ).supportedContentEncodings;

    return supported?.[0] ?? 'aesgcm';
}

async function subscribe(vapidPublicKey: string): Promise<boolean> {
    if (!isSupported()) {
        return false;
    }

    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        return false;
    }

    await navigator.serviceWorker.register('/sw.js');
    const registration = await navigator.serviceWorker.ready;

    const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(vapidPublicKey),
    });

    const json = subscription.toJSON();

    router.post(
        '/push-subscriptions',
        {
            endpoint: json.endpoint,
            key: json.keys?.p256dh ?? null,
            token: json.keys?.auth ?? null,
            content_encoding: contentEncoding(),
        },
        { preserveScroll: true, preserveState: true },
    );

    return true;
}

async function unsubscribe(): Promise<void> {
    if (!isSupported()) {
        return;
    }

    const registration = await navigator.serviceWorker.ready.catch(() => null);
    const subscription = await registration?.pushManager.getSubscription();

    if (!subscription) {
        return;
    }

    const endpoint = subscription.endpoint;
    await subscription.unsubscribe();

    router.delete('/push-subscriptions', {
        data: { endpoint },
        preserveScroll: true,
        preserveState: true,
    });
}

export function usePushNotifications() {
    return { isSupported, subscribe, unsubscribe };
}

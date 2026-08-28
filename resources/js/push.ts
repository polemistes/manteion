/**
 * Registers the web-push service worker, when the browser supports it. A
 * complete no-op on browsers/webviews without Push API support (notably
 * the iOS Capacitor app shell).
 */
export function setupPushNotifications(): void {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        return;
    }

    void navigator.serviceWorker.register('/sw.js');
}

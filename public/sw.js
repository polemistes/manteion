/*
 * Manteion's service worker. Plain JavaScript served as-is from /sw.js (not
 * built by Vite), so it can control every page of the site.
 *
 * - Push: shows the notification, folding new messages of one conversation
 *   into one notification, keeps the app icon badge in step, and leaves the
 *   notification out when the user is already looking at its page.
 * - Notification clicks: bring an open window to the page, or open one.
 * - Offline: page loads go to the network first and fall back to the cached
 *   offline page. Nothing else is cached — no Inertia responses, no
 *   signed-in HTML.
 *
 * Bump OFFLINE_CACHE when public/offline.html changes.
 */

const OFFLINE_CACHE = 'manteion-offline-v1';
const OFFLINE_URL = '/offline.html';
const DEFAULT_ICON = '/icons/icon-192.png';
const DEFAULT_BADGE = '/icons/badge-96.png';

const userAgent = self.navigator.userAgent;

// WebKit (Safari, iOS home screen apps) revokes a push subscription after a
// few pushes that showed nothing, even with the app in front, so there every
// push is shown.
const mustShowEveryPush =
    /AppleWebKit/.test(userAgent) &&
    !/Chrome|Chromium|Edg|OPR|SamsungBrowser/.test(userAgent);

const strings = {
    en: { newMessages: (count) => `${count} new messages` },
    nb: { newMessages: (count) => `${count} nye meldinger` },
};

function stringsFor(lang) {
    const language = String(lang || self.navigator.language || 'en');

    return /^(nb|nn|no)\b/i.test(language) ? strings.nb : strings.en;
}

function absoluteUrl(url) {
    try {
        const resolved = new URL(url || '/', self.location.origin);

        return resolved.origin === self.location.origin
            ? resolved.href
            : self.location.origin + '/';
    } catch {
        return self.location.origin + '/';
    }
}

function samePage(clientUrl, url) {
    const a = new URL(clientUrl);
    const b = new URL(url);
    const path = (value) => value.pathname.replace(/\/+$/, '') || '/';

    return a.origin === b.origin && path(a) === path(b);
}

function contentEncoding() {
    const encodings =
        self.PushManager && self.PushManager.supportedContentEncodings;

    return (encodings && encodings[0]) || 'aes128gcm';
}

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(OFFLINE_CACHE)
            .then((cache) =>
                cache.add(new Request(OFFLINE_URL, { cache: 'reload' })),
            )
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        (async () => {
            const names = await caches.keys();

            await Promise.all(
                names
                    .filter(
                        (name) =>
                            name.startsWith('manteion-') &&
                            name !== OFFLINE_CACHE,
                    )
                    .map((name) => caches.delete(name)),
            );

            if (self.registration.navigationPreload) {
                await self.registration.navigationPreload.enable();
            }

            await self.clients.claim();
        })(),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Only full page loads; every other request goes straight to the
    // network as if there were no service worker.
    if (request.mode !== 'navigate' || request.method !== 'GET') {
        return;
    }

    event.respondWith(
        (async () => {
            try {
                const preloaded = await event.preloadResponse;

                return preloaded || (await fetch(request));
            } catch {
                const offline = await caches.match(OFFLINE_URL, {
                    cacheName: OFFLINE_CACHE,
                });

                return offline || Response.error();
            }
        })(),
    );
});

async function setAppBadge(count) {
    const value = Number(count);

    if (!Number.isFinite(value) || !('setAppBadge' in self.navigator)) {
        return;
    }

    try {
        if (value > 0) {
            await self.navigator.setAppBadge(value);
        } else {
            await self.navigator.clearAppBadge();
        }
    } catch {
        // Not allowed here; the open page sets it again when it can.
    }
}

function parsePush(data) {
    if (!data) {
        return {};
    }

    try {
        return data.json() || {};
    } catch {
        return { body: data.text() };
    }
}

async function showPush(data) {
    const payload = parsePush(data);
    const info =
        payload.data && typeof payload.data === 'object' ? payload.data : {};
    const url = absoluteUrl(info.url);

    await setAppBadge(info.badge_count);

    // The user is looking at this very page: let the page show it.
    const windows = await self.clients.matchAll({
        type: 'window',
        includeUncontrolled: true,
    });
    const watching = windows.find(
        (client) =>
            client.focused &&
            client.visibilityState === 'visible' &&
            samePage(client.url, url),
    );

    if (watching) {
        watching.postMessage({ type: 'push', data: info });

        if (!mustShowEveryPush) {
            return;
        }
    }

    const tag = payload.tag || undefined;
    const options = {
        body: payload.body || '',
        icon: payload.icon || DEFAULT_ICON,
        badge: payload.badge || DEFAULT_BADGE,
        tag,
        renotify: Boolean(tag && payload.renotify),
        timestamp: payload.timestamp || Date.now(),
        lang: payload.lang || undefined,
        dir: payload.dir || 'auto',
        data: { ...info, url },
    };

    if (payload.image) {
        options.image = payload.image;
    }

    if (Array.isArray(payload.actions)) {
        options.actions = payload.actions;
    }

    // Several messages in one conversation: one notification that counts
    // them and shows the newest ("3 new messages" and the latest line).
    if (info.type === 'message' && tag) {
        const [previous] = await self.registration.getNotifications({ tag });
        const before = (previous && previous.data) || {};
        const count = previous ? (Number(before.message_count) || 1) + 1 : 1;
        const latest = options.body;

        if (count > 1) {
            const summary = stringsFor(options.lang).newMessages(count);

            options.body =
                latest && latest !== before.last_body
                    ? `${summary}\n${latest}`
                    : summary;
        }

        options.data = {
            ...options.data,
            message_count: count,
            last_body: latest,
        };
    }

    await self.registration.showNotification(
        payload.title || 'Manteion',
        options,
    );
}

self.addEventListener('push', (event) => {
    event.waitUntil(showPush(event.data));
});

/**
 * Asks an open window to show `url` itself (an Inertia visit keeps the app
 * running); true when it answers.
 */
function askToNavigate(client, url) {
    return new Promise((resolve) => {
        const channel = new MessageChannel();
        const timer = setTimeout(() => resolve(false), 1500);

        channel.port1.onmessage = () => {
            clearTimeout(timer);
            resolve(true);
        };

        client.postMessage({ type: 'navigate', url }, [channel.port2]);
    });
}

async function openPage(url) {
    const windows = await self.clients.matchAll({
        type: 'window',
        includeUncontrolled: true,
    });
    const onPage = windows.find((client) => samePage(client.url, url));

    if (onPage) {
        await onPage.focus().catch(() => {});

        return;
    }

    const target =
        windows.find((client) => client.focused) ||
        windows.find((client) => client.visibilityState === 'visible') ||
        windows[0];

    if (!target) {
        await self.clients.openWindow(url);

        return;
    }

    const focused = (await target.focus().catch(() => null)) || target;

    if (await askToNavigate(focused, url)) {
        return;
    }

    try {
        await focused.navigate(url);
    } catch {
        await self.clients.openWindow(url);
    }
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const data = event.notification.data || {};

    event.waitUntil(openPage(absoluteUrl(data.url)));
});

function sendJson(method, url, body, xsrf) {
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    // A worker can't read cookies; a page that asks passes the CSRF token.
    // (Browsers that send Sec-Fetch-Site pass Laravel's check without it.)
    if (xsrf) {
        headers['X-XSRF-TOKEN'] = xsrf;
    }

    return fetch(url, {
        method,
        credentials: 'same-origin',
        headers,
        body: JSON.stringify(body),
    });
}

self.addEventListener('message', (event) => {
    const message = event.data || {};

    if (message.type === 'clear-notifications') {
        event.waitUntil(
            self.registration
                .getNotifications(
                    message.tag ? { tag: message.tag } : undefined,
                )
                .then((notifications) =>
                    notifications.forEach((notification) =>
                        notification.close(),
                    ),
                ),
        );
    }

    // A page that is closing asks the worker to send its last request: a
    // controlled page's own beacons and keepalive fetches can be dropped
    // as it unloads, while the worker lives on long enough to send it.
    if (message.type === 'post' && typeof message.url === 'string') {
        const url = new URL(message.url, self.location.origin);

        if (url.origin === self.location.origin) {
            event.waitUntil(
                sendJson(
                    'POST',
                    url.href,
                    message.body || {},
                    message.xsrf,
                ).catch(() => {}),
            );
        }
    }
});

function sendSubscription(method, body) {
    return sendJson(method, '/push-subscriptions', body);
}

// The push service replaced this browser's subscription: register the new
// one in its place, so pushes keep arriving.
self.addEventListener('pushsubscriptionchange', (event) => {
    event.waitUntil(
        (async () => {
            const previous = event.oldSubscription;
            let subscription = event.newSubscription;

            if (
                !subscription &&
                previous &&
                previous.options &&
                previous.options.applicationServerKey
            ) {
                subscription = await self.registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: previous.options.applicationServerKey,
                });
            }

            if (!subscription) {
                return;
            }

            const json = subscription.toJSON();

            await sendSubscription('POST', {
                endpoint: json.endpoint,
                key: json.keys ? json.keys.p256dh : null,
                token: json.keys ? json.keys.auth : null,
                content_encoding: contentEncoding(),
            });

            if (previous && previous.endpoint !== subscription.endpoint) {
                await sendSubscription('DELETE', {
                    endpoint: previous.endpoint,
                }).catch(() => {});
            }
        })(),
    );
});

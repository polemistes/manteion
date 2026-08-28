// Plain static service worker — not processed by Vite. Handles incoming web
// push events and opens the app to the relevant page on notification click.

self.addEventListener('push', (event) => {
    if (!event.data) {
        return;
    }

    const payload = event.data.json();

    event.waitUntil(
        self.registration.showNotification(payload.title, {
            body: payload.body,
            icon: payload.icon || '/favicon.png',
            badge: payload.badge,
            data: payload.data || {},
            tag: payload.tag,
        }),
    );
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const url = event.notification.data && event.notification.data.url;

    if (!url) {
        return;
    }

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clients) => {
                for (const client of clients) {
                    if (client.url.endsWith(url) && 'focus' in client) {
                        return client.focus();
                    }
                }

                return self.clients.openWindow(url);
            }),
    );
});

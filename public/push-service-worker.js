'use strict';

self.addEventListener('push', (event) => {
    let payload = {};
    try {
        payload = event.data ? event.data.json() : {};
    } catch {
        payload = {};
    }

    const title = typeof payload.title === 'string' ? payload.title : 'Hospitrainity';
    const body = typeof payload.body === 'string' ? payload.body : 'You have a new update.';
    const url = typeof payload.url === 'string' && payload.url.startsWith('/') && !payload.url.startsWith('//') ? payload.url : '/';
    event.waitUntil(self.registration.showNotification(title, {
        body,
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        data: { url },
        tag: 'hospitrainity-update',
        renotify: false,
    }));
});

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const url = event.notification.data?.url || '/';
    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then((windows) => {
        const existing = windows.find((client) => new URL(client.url).pathname === url);
        return existing ? existing.focus() : clients.openWindow(url);
    }));
});

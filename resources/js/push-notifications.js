function decodeBase64Url(value) {
    const padding = '='.repeat((4 - (value.length % 4)) % 4);
    const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
    return Uint8Array.from(window.atob(base64), (character) => character.charCodeAt(0));
}

async function jsonRequest(url, options = {}) {
    const response = await window.fetch(url, {
        ...options,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            ...(options.headers || {}),
        },
    });
    if (!response.ok) throw new Error(`push_request_${response.status}`);
    return response.json();
}

export function initializePushNotifications() {
    const root = document.querySelector('[data-push-settings]');
    if (!(root instanceof HTMLElement)) return;

    const status = root.querySelector('[data-push-status]');
    const subscribe = root.querySelector('[data-push-subscribe]');
    const unsubscribe = root.querySelector('[data-push-unsubscribe]');
    const test = root.querySelector('[data-push-test]');
    const announce = (message) => { if (status) status.textContent = message; };

    if (!window.isSecureContext || !('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
        announce(root.dataset.unsupportedMessage || 'Push notifications are not supported here.');
        [subscribe, unsubscribe, test].forEach((button) => { if (button instanceof HTMLButtonElement) button.disabled = true; });
        return;
    }

    const registration = navigator.serviceWorker.register('/push-service-worker.js', { scope: '/' });

    subscribe?.addEventListener('click', async () => {
        try {
            const permission = await Notification.requestPermission();
            if (permission !== 'granted') throw new Error('push_permission_denied');
            const worker = await registration;
            const pushSubscription = await worker.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: decodeBase64Url(root.dataset.publicKey || ''),
            });
            const json = pushSubscription.toJSON();
            const result = await jsonRequest(root.dataset.storeUrl, {
                method: 'POST',
                body: JSON.stringify({ ...json, contentEncoding: 'aes128gcm' }),
            });
            root.dataset.subscriptionId = String(result.id);
            announce(root.dataset.subscribedMessage || 'Push notifications enabled.');
        } catch {
            announce(root.dataset.failedMessage || 'Push notifications could not be enabled.');
        }
    });

    unsubscribe?.addEventListener('click', async () => {
        try {
            const worker = await registration;
            const current = await worker.pushManager.getSubscription();
            if (current) await current.unsubscribe();
            const id = root.dataset.subscriptionId;
            if (id) await jsonRequest(`${root.dataset.destroyBaseUrl}/${id}`, { method: 'DELETE' });
            announce(root.dataset.unsubscribedMessage || 'Push notifications disabled.');
        } catch {
            announce(root.dataset.failedMessage || 'Push notification settings could not be changed.');
        }
    });

    test?.addEventListener('click', async () => {
        try {
            await jsonRequest(root.dataset.testUrl, { method: 'POST', body: '{}' });
            announce(root.dataset.testMessage || 'Test notification queued.');
        } catch {
            announce(root.dataset.failedMessage || 'Test notification could not be queued.');
        }
    });
}

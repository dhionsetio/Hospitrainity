export function initNotificationPanel() {
    const panel = document.getElementById('hsp-notifications-panel');
    if (!panel) return;

    const listContainer = panel.querySelector('[data-notification-list]');
    const recheckButton = panel.querySelector('[data-notification-recheck]');
    const badge = document.querySelector('[data-notification-badge]');

    const fetchNotifications = async () => {
        if (!listContainer) return;
        listContainer.innerHTML = '<p class="text-center text-xs text-neutral-500 py-4">Loading notifications...</p>';

        try {
            const response = await fetch('/notifications/panel', {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });
            if (!response.ok) throw new Error('Network error');
            const data = await response.json();

            // Update badge
            if (badge) {
                if (data.unread_count > 0) {
                    badge.textContent = data.unread_count > 99 ? '99+' : data.unread_count;
                    badge.classList.remove('hidden');
                } else {
                    badge.classList.add('hidden');
                }
            }

            // Render list items
            if (data.items.length === 0) {
                listContainer.innerHTML = `
                    <div class="py-6 text-center text-xs text-neutral-500">
                        <i class="fa-solid fa-bell-slash text-xl text-neutral-400 mb-1" aria-hidden="true"></i>
                        <p>No notifications yet</p>
                    </div>
                `;
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

            listContainer.innerHTML = data.items.map((item) => {
                const isUnread = !item.read_at;
                return `
                    <div class="flex items-start justify-between gap-2 p-2.5 rounded-lg border text-xs transition-colors ${
                        isUnread ? 'border-indigo-200 bg-indigo-50/50 dark:border-indigo-900/50 dark:bg-indigo-950/30' : 'border-neutral-200 bg-white dark:border-neutral-700 dark:bg-neutral-800'
                    }">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5 font-bold text-neutral-900 dark:text-white">
                                ${isUnread ? '<span class="h-1.5 w-1.5 rounded-full bg-indigo-600 shrink-0"></span>' : ''}
                                <span class="truncate">${escapeHtml(item.title)}</span>
                            </div>
                            ${item.body ? `<p class="mt-0.5 text-neutral-600 dark:text-neutral-300 line-clamp-2">${escapeHtml(item.body)}</p>` : ''}
                            <span class="mt-1 block text-[10px] text-neutral-400 dark:text-neutral-500">${escapeHtml(item.created_at_human)}</span>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                            ${isUnread ? `
                                <button type="button" data-action-read="${item.id}" class="p-1 text-neutral-500 hover:text-emerald-600" title="Mark as read">
                                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                                </button>
                            ` : ''}
                            <button type="button" data-action-delete="${item.id}" class="p-1 text-neutral-400 hover:text-red-600" title="Delete">
                                <i class="fa-solid fa-trash-can" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                `;
            }).join('');

            // Attach event handlers for inline mark read & delete
            listContainer.querySelectorAll('[data-action-read]').forEach((btn) => {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const id = btn.getAttribute('data-action-read');
                    await fetch(`/notifications/${id}/read`, {
                        method: 'PATCH',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    fetchNotifications();
                });
            });

            listContainer.querySelectorAll('[data-action-delete]').forEach((btn) => {
                btn.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const id = btn.getAttribute('data-action-delete');
                    await fetch(`/notifications/${id}`, {
                        method: 'DELETE',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                    });
                    fetchNotifications();
                });
            });

        } catch {
            if (listContainer) {
                listContainer.innerHTML = '<p class="text-center text-xs text-red-500 py-4">Failed to load notifications.</p>';
            }
        }
    };

    if (recheckButton) {
        recheckButton.addEventListener('click', (e) => {
            e.preventDefault();
            fetchNotifications();
        });
    }

    // Defer the first network request until the notifications panel is opened
    // for the first time. This avoids a fetch on every page load for users who
    // never open the bell; the unread badge is already server-rendered.
    let hasLoadedNotifications = false;
    const loadNotificationsOnce = () => {
        if (hasLoadedNotifications) return;
        hasLoadedNotifications = true;
        fetchNotifications();
    };

    const toggleButton = document.querySelector('[data-notification-toggle]');
    if (toggleButton) {
        toggleButton.addEventListener('click', loadNotificationsOnce);
    } else {
        // Fallback: preserve the original eager behavior when no toggle exists.
        fetchNotifications();
    }
}

function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

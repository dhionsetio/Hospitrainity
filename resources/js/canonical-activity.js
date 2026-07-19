export function initializeCanonicalActivity(root = document, currentLocation = window.location) {
    const errorSummary = root.querySelector('[data-error-summary]');
    if (errorSummary instanceof HTMLElement) {
        errorSummary.focus();
        errorSummary.addEventListener('click', (event) => {
            const link = event.target instanceof Element
                ? event.target.closest('[data-error-link]')
                : null;
            if (!(link instanceof HTMLElement) || !link.matches('a[href^="#"]')) return;

            const target = root.getElementById(link.hash.slice(1));
            if (!(target instanceof HTMLElement)) return;

            event.preventDefault();
            target.scrollIntoView?.({ block: 'center', behavior: 'auto' });
            const control = target.querySelector('[aria-invalid="true"]')
                ?? target.querySelector('input, select, textarea, button');
            if (control instanceof HTMLElement) control.focus();
            else target.focus();
        });
    }

    if (currentLocation.hash === '#attempt-result') {
        const attemptResult = root.getElementById('attempt-result');
        if (attemptResult instanceof HTMLElement) attemptResult.focus();
    }

    root.querySelectorAll('[data-ordering-list]').forEach((list) => {
        if (!(list instanceof HTMLOListElement)) return;

        const refresh = () => {
            const items = Array.from(list.querySelectorAll(':scope > [data-ordering-item]'));
            items.forEach((item, index) => {
                const position = item.querySelector('[data-ordering-position]');
                if (position) position.textContent = `${list.dataset.orderingLabel || 'Position'} ${index + 1}`;
                const up = item.querySelector('[data-order-up]');
                const down = item.querySelector('[data-order-down]');
                if (up instanceof HTMLButtonElement) {
                    up.disabled = index === 0;
                    up.setAttribute('aria-label', `${list.dataset.orderingUpLabel || 'Move item at position'} ${index + 1} ${list.dataset.orderingUpSuffix || 'up'}`);
                }
                if (down instanceof HTMLButtonElement) {
                    down.disabled = index === items.length - 1;
                    down.setAttribute('aria-label', `${list.dataset.orderingDownLabel || 'Move item at position'} ${index + 1} ${list.dataset.orderingDownSuffix || 'down'}`);
                }
            });
        };

        list.addEventListener('click', (event) => {
            const button = event.target instanceof Element
                ? event.target.closest('[data-order-up], [data-order-down]')
                : null;
            const item = button?.closest('[data-ordering-item]');
            if (!(button instanceof HTMLButtonElement) || !(item instanceof HTMLLIElement)) return;

            if (button.matches('[data-order-up]') && item.previousElementSibling) {
                list.insertBefore(item, item.previousElementSibling);
            } else if (button.matches('[data-order-down]') && item.nextElementSibling) {
                list.insertBefore(item.nextElementSibling, item);
            }
            refresh();
            const movedPosition = Array.from(list.children).indexOf(item) + 1;
            const status = list.parentElement?.querySelector('[data-ordering-status]');
            if (status) status.textContent = `${list.dataset.orderingMovedLabel || 'Item moved to position'} ${movedPosition}.`;
            const focusTarget = button.disabled
                ? item.querySelector(button.matches('[data-order-up]') ? '[data-order-down]' : '[data-order-up]')
                : button;
            if (focusTarget instanceof HTMLButtonElement) focusTarget.focus();
        });
        refresh();
    });
}

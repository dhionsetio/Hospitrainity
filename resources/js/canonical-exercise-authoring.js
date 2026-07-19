function initializeExerciseAuthoring(root = document) {
    root.querySelectorAll('[data-exercise-authoring]').forEach((form) => {
        const list = form.querySelector('[data-exercise-items]');
        const status = form.querySelector('[data-exercise-authoring-status]');
        const add = form.querySelector('[data-add-exercise-item]');
        if (!(list instanceof HTMLOListElement)) return;
        const minimum = Number.parseInt(form.dataset.minItems || '1', 10);
        const maximum = Number.parseInt(form.dataset.maxItems || '20', 10);

        const refresh = () => {
            const rows = Array.from(list.querySelectorAll(':scope > [data-exercise-item]'));
            rows.forEach((row, index) => {
                row.querySelectorAll('[name]').forEach((field) => {
                    if (field.name.startsWith('items[')) {
                        field.name = field.name.replace(/^items\[\d+\]/, `items[${index}]`);
                    }
                });
                const number = row.querySelector('[data-exercise-item-number]');
                if (number) number.textContent = `Item ${index + 1}`;
                const up = row.querySelector('[data-item-up]');
                const down = row.querySelector('[data-item-down]');
                const remove = row.querySelector('[data-remove-exercise-item]');
                if (up instanceof HTMLButtonElement) {
                    up.disabled = index === 0;
                    up.setAttribute('aria-label', `Move item ${index + 1} up`);
                }
                if (down instanceof HTMLButtonElement) {
                    down.disabled = index === rows.length - 1;
                    down.setAttribute('aria-label', `Move item ${index + 1} down`);
                }
                if (remove instanceof HTMLButtonElement) {
                    remove.disabled = rows.length <= minimum;
                    remove.setAttribute('aria-label', `Remove item ${index + 1}`);
                }
            });
            if (add instanceof HTMLButtonElement) add.disabled = rows.length >= maximum;
        };

        list.addEventListener('click', (event) => {
            const button = event.target instanceof Element
                ? event.target.closest('[data-item-up], [data-item-down], [data-remove-exercise-item]')
                : null;
            const item = button?.closest('[data-exercise-item]');
            if (!(button instanceof HTMLButtonElement) || !(item instanceof HTMLLIElement)) return;
            if (button.matches('[data-item-up]') && item.previousElementSibling) {
                list.insertBefore(item, item.previousElementSibling);
                if (status) status.textContent = 'Exercise item moved up.';
            } else if (button.matches('[data-item-down]') && item.nextElementSibling) {
                list.insertBefore(item.nextElementSibling, item);
                if (status) status.textContent = 'Exercise item moved down.';
            } else if (button.matches('[data-remove-exercise-item]') && list.children.length > minimum) {
                const focusTarget = item.previousElementSibling || item.nextElementSibling || add;
                item.remove();
                if (status) status.textContent = 'Exercise item removed.';
                refresh();
                focusTarget?.querySelector?.('[data-item-up]')?.focus?.();

                return;
            }
            refresh();
            const movedButton = item.querySelector(button.matches('[data-item-up]') ? '[data-item-up]' : '[data-item-down]');
            if (movedButton instanceof HTMLButtonElement && !movedButton.disabled) movedButton.focus();
        });

        add?.addEventListener('click', () => {
            if (list.children.length >= maximum || !(list.firstElementChild instanceof HTMLLIElement)) return;
            const item = list.firstElementChild.cloneNode(true);
            item.querySelectorAll('input, textarea, select').forEach((field) => {
                if (field instanceof HTMLInputElement || field instanceof HTMLTextAreaElement) field.value = '';
                if (field instanceof HTMLSelectElement) field.selectedIndex = 0;
            });
            item.querySelectorAll('.font-mono').forEach((code) => code.remove());
            list.appendChild(item);
            refresh();
            item.querySelector('textarea, input, select')?.focus();
            if (status) status.textContent = 'Exercise item added.';
        });
        refresh();
    });
}

export { initializeExerciseAuthoring };

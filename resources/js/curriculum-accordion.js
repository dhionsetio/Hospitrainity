/**
 * Curriculum Accordion Toggle for Module Landing Page
 * Allows step circle buttons to toggle visibility of associated lesson sections with smooth CSS animation.
 */

export function initCurriculumAccordion(container = document) {
    const accordions = container.querySelectorAll('[data-curriculum-accordion]');
    accordions.forEach((accordion) => {
        const triggers = accordion.querySelectorAll('[data-step-trigger]');
        triggers.forEach((trigger) => {
            const stepId = trigger.getAttribute('data-step-trigger');
            if (!stepId) return;

            const content = accordion.querySelector(`[data-step-content="${stepId}"]`);
            if (!content) return;

            // Initialize state: collapsed by default if JavaScript is enabled
            content.classList.add('transition-all', 'duration-300', 'ease-in-out', 'overflow-hidden');

            const isExpanded = trigger.getAttribute('aria-expanded') === 'true';
            if (!isExpanded) {
                content.style.maxHeight = '0px';
                content.style.opacity = '0';
                content.classList.add('pointer-events-none');
            } else {
                content.style.maxHeight = `${content.scrollHeight}px`;
                content.style.opacity = '1';
                content.classList.remove('pointer-events-none');
            }

            trigger.addEventListener('click', () => {
                const currentExpanded = trigger.getAttribute('aria-expanded') === 'true';
                const nextExpanded = !currentExpanded;

                trigger.setAttribute('aria-expanded', String(nextExpanded));

                if (nextExpanded) {
                    content.classList.remove('pointer-events-none');
                    content.style.maxHeight = `${content.scrollHeight + 300}px`;
                    content.style.opacity = '1';
                } else {
                    content.style.maxHeight = '0px';
                    content.style.opacity = '0';
                    content.classList.add('pointer-events-none');
                }
            });
        });
    });
}

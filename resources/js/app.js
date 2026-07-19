import Alpine from '@alpinejs/csp';
import { saveProgress } from './progress';
import { createMediaController, isPlaybackCancellation } from './media-playback';
import { registerAdminComponents } from './admin-forms';
import { initializeCanonicalActivity } from './canonical-activity';
import { initializeExerciseAuthoring } from './canonical-exercise-authoring';

registerAdminComponents(Alpine);
window.Alpine = Alpine;
Alpine.start();

// Compatibility bridge for the two learner views that still have page-specific
// inline navigation code. The request implementation itself remains centralized.
window.HospitrainityProgress = Object.freeze({ saveProgress });
window.HospitrainityMedia = Object.freeze({ createMediaController, isPlaybackCancellation });

function initializePageInteractions() {
    initializeCanonicalActivity();
    initializeExerciseAuthoring();

    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    const setMobileMenuOpen = (open, returnFocus = false) => {
        if (!mobileMenuButton || !mobileMenu) return;

        mobileMenu.classList.toggle('hidden', !open);
        mobileMenuButton.setAttribute('aria-expanded', String(open));
        mobileMenuButton.setAttribute(
            'aria-label',
            open ? mobileMenuButton.dataset.closeLabel : mobileMenuButton.dataset.openLabel,
        );

        if (returnFocus) mobileMenuButton.focus();
    };

    mobileMenuButton?.addEventListener('click', () => {
        setMobileMenuOpen(mobileMenuButton.getAttribute('aria-expanded') !== 'true');
    });
    mobileMenu?.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => setMobileMenuOpen(false));
    });

    const profileButton = document.getElementById('profile-button');
    const profileMenu = document.getElementById('dropdown-menu');
    const menuItems = Array.from(profileMenu?.querySelectorAll('[role="menuitem"]') ?? []);
    const setProfileMenuOpen = (open, focusIndex = null) => {
        if (!profileButton || !profileMenu) return;

        profileMenu.classList.toggle('hidden', !open);
        profileButton.setAttribute('aria-expanded', String(open));

        if (open && focusIndex !== null && menuItems.length > 0) {
            menuItems[Math.max(0, Math.min(focusIndex, menuItems.length - 1))].focus();
        }
    };

    profileButton?.addEventListener('click', () => {
        const willOpen = profileButton.getAttribute('aria-expanded') !== 'true';
        setProfileMenuOpen(willOpen, willOpen ? 0 : null);
    });

    profileButton?.addEventListener('keydown', (event) => {
        if (!['Enter', ' ', 'ArrowDown', 'ArrowUp'].includes(event.key)) return;

        event.preventDefault();
        setProfileMenuOpen(true, event.key === 'ArrowUp' ? menuItems.length - 1 : 0);
    });

    profileMenu?.addEventListener('keydown', (event) => {
        const currentIndex = menuItems.indexOf(document.activeElement);

        if (event.key === 'Escape') {
            event.preventDefault();
            setProfileMenuOpen(false);
            profileButton?.focus();
        } else if (event.key === 'Tab') {
            setProfileMenuOpen(false);
        } else if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
            event.preventDefault();
            let nextIndex = currentIndex;
            if (event.key === 'Home') nextIndex = 0;
            if (event.key === 'End') nextIndex = menuItems.length - 1;
            if (event.key === 'ArrowDown') nextIndex = (currentIndex + 1) % menuItems.length;
            if (event.key === 'ArrowUp') nextIndex = (currentIndex - 1 + menuItems.length) % menuItems.length;
            menuItems[nextIndex]?.focus();
        }
    });

    menuItems.forEach((item) => item.addEventListener('click', () => setProfileMenuOpen(false)));

    window.addEventListener('click', (event) => {
        if (
            profileButton
            && profileMenu
            && event.target instanceof Node
            && !profileButton.contains(event.target)
            && !profileMenu.contains(event.target)
        ) {
            setProfileMenuOpen(false);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mobileMenuButton?.getAttribute('aria-expanded') === 'true') {
            setMobileMenuOpen(false, true);
        }
    });

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-confirm-submit]')) return;

        const fallback = document.documentElement.dataset.confirmFallback || 'Continue?';
        if (!window.confirm(form.dataset.confirmSubmit || fallback)) {
            event.preventDefault();
        }
    });

    document.querySelectorAll('[data-progress-filter-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[data-progress-submit]');
            const ready = form.querySelector('[data-progress-ready]');
            const loading = form.querySelector('[data-progress-loading]');
            const status = form.querySelector('[data-progress-status]');

            if (button instanceof HTMLButtonElement) button.disabled = true;
            if (ready instanceof HTMLElement) ready.hidden = true;
            if (loading instanceof HTMLElement) loading.hidden = false;
            if (status instanceof HTMLElement && loading instanceof HTMLElement) {
                status.textContent = loading.textContent;
            }
        });
    });

}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializePageInteractions, { once: true });
} else {
    initializePageInteractions();
}

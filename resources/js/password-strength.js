/**
 * Password Strength Evaluator (NIST SP 800-63B Advisory Guidelines)
 * Evaluates password strength based on length, character variety, and common pattern hints.
 */

const COMMON_WEAK_PASSWORDS = new Set([
    'password',
    '12345678',
    '123456789',
    '1234567890',
    'qwertyuiop',
    'administrator',
    'admin123',
    'hospitrainity',
    'letmein123',
    'password123'
]);

/**
 * Calculates strength score and returns key/label information.
 * @param {string} password
 * @param {number} minLength
 * @returns {{ score: number, level: string, percent: number }}
 */
export function evaluatePasswordStrength(password, minLength = 8) {
    if (!password || typeof password !== 'string') {
        return { score: 0, level: 'weak', percent: 0 };
    }

    const val = password.trim();
    if (val.length < minLength) {
        return { score: 1, level: 'weak', percent: 25 };
    }

    // Common password check
    if (COMMON_WEAK_PASSWORDS.has(val.toLowerCase())) {
        return { score: 1, level: 'weak', percent: 25 };
    }

    let score = 1;

    // Length tiers
    if (val.length >= minLength + 3) score += 1;
    if (val.length >= minLength + 7) score += 1;

    // Character variety
    if (/[a-z]/.test(val)) score += 1;
    if (/[A-Z]/.test(val)) score += 1;
    if (/[0-9]/.test(val)) score += 1;
    if (/[^a-zA-Z0-9]/.test(val)) score += 1;

    if (score <= 3) {
        return { score, level: 'weak', percent: 25 };
    } else if (score <= 4) {
        return { score, level: 'good', percent: 50 };
    } else if (score <= 6) {
        return { score, level: 'strong', percent: 75 };
    } else {
        return { score, level: 'very_strong', percent: 100 };
    }
}

/**
 * Attaches event listener to container password inputs to update strength meter live regions.
 * @param {HTMLElement} container
 */
export function initPasswordStrengthMeter(container = document) {
    const widgets = container.querySelectorAll('[data-password-strength]');
    widgets.forEach((widget) => {
        const inputId = widget.getAttribute('data-for');
        if (!inputId) return;

        const input = document.getElementById(inputId);
        if (!input) return;

        const minLengthAttr = widget.getAttribute('data-min-length');
        const minLength = minLengthAttr ? parseInt(minLengthAttr, 10) : 8;

        const bar = widget.querySelector('[data-strength-bar]');
        const statusText = widget.querySelector('[data-strength-status]');

        const updateUI = () => {
            const val = input.value;
            if (!val) {
                if (bar) {
                    bar.style.width = '0%';
                    bar.className = 'h-2 rounded-full transition-all duration-300 bg-neutral-200';
                }
                if (statusText) {
                    statusText.textContent = '';
                }
                return;
            }

            const { level, percent } = evaluatePasswordStrength(val, minLength);

            let bgClass = 'bg-red-500';
            let labelText = widget.getAttribute('data-label-weak') || 'Weak';

            if (level === 'good') {
                bgClass = 'bg-amber-500';
                labelText = widget.getAttribute('data-label-good') || 'Good';
            } else if (level === 'strong') {
                bgClass = 'bg-blue-600';
                labelText = widget.getAttribute('data-label-strong') || 'Strong';
            } else if (level === 'very_strong') {
                bgClass = 'bg-emerald-600';
                labelText = widget.getAttribute('data-label-very-strong') || 'Very Strong';
            }

            if (bar) {
                bar.style.width = `${percent}%`;
                bar.className = `h-2 rounded-full transition-all duration-300 ${bgClass}`;
            }

            if (statusText) {
                statusText.textContent = `${labelText}`;
            }
        };

        input.addEventListener('input', updateUI);
        // Initial state sync
        updateUI();
    });
}

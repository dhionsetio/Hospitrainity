import { Passkeys } from '@laravel/passkeys';

function status(message, error = false) {
    const output = document.querySelector('[data-passkey-status]');
    if (!(output instanceof HTMLElement)) return;
    output.textContent = message;
    output.classList.toggle('text-red-800', error);
}

export function initializePasskeys() {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    Passkeys.configure({ fetch: { headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf ?? '' } } });
    const supported = Passkeys.isSupported();
    document.querySelectorAll('[data-passkey-login], [data-passkey-register], [data-passkey-confirm]').forEach((button) => {
        if (button instanceof HTMLButtonElement) button.hidden = !supported;
    });

    document.querySelector('[data-passkey-login]')?.addEventListener('click', async () => {
        status('Waiting for your passkey…');
        try {
            const response = await Passkeys.verify();
            window.location.assign(response.redirect || '/dashboard');
        } catch (error) {
            status(error instanceof Error ? error.message : 'Passkey sign-in failed.', true);
        }
    });
    document.querySelector('[data-passkey-confirm]')?.addEventListener('click', async () => {
        status('Waiting for your passkey…');
        try {
            const response = await Passkeys.verify({ routes: { options: '/passkeys/confirm/options', submit: '/passkeys/confirm' } });
            window.location.assign(response.redirect || '/security');
        } catch (error) {
            status(error instanceof Error ? error.message : 'Passkey confirmation failed.', true);
        }
    });
    document.querySelector('[data-passkey-register]')?.addEventListener('click', async () => {
        const input = document.querySelector('[data-passkey-name]');
        const name = input instanceof HTMLInputElement ? input.value.trim() : '';
        if (!name) return status('Enter a name for this passkey.', true);
        status('Waiting for your device to create a passkey…');
        try {
            await Passkeys.register({ name });
            window.location.reload();
        } catch (error) {
            status(error instanceof Error ? error.message : 'Passkey registration failed.', true);
        }
    });
}

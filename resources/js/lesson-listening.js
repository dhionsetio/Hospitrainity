import { createMediaController, isPlaybackCancellation } from './media-playback.js';

function readableText(target) {
    return target.textContent?.replace(/\s+/g, ' ').trim() || '';
}

export function initializeLessonListening({
    root = document,
    createController = createMediaController,
} = {}) {
    if (root.documentElement?.dataset.audio === 'off') return;

    root.querySelectorAll('[data-speak-target]').forEach((button) => {
        if (!(button instanceof HTMLButtonElement) || button.dataset.listeningReady === 'true') return;

        const target = root.getElementById(button.dataset.speakTarget || '');
        const status = root.getElementById(button.getAttribute('aria-describedby') || '');
        if (!(target instanceof HTMLElement) || !(status instanceof HTMLElement)) return;

        const controller = createController({ language: button.dataset.speakLanguage || 'en-US' });
        const listenLabel = button.querySelector('[data-listen-label]');
        const stopLabel = button.querySelector('[data-stop-label]');
        let playing = false;

        const setPlaying = (value) => {
            playing = value;
            button.setAttribute('aria-pressed', String(value));
            if (listenLabel instanceof HTMLElement) listenLabel.hidden = value;
            if (stopLabel instanceof HTMLElement) stopLabel.hidden = !value;
        };

        button.dataset.listeningReady = 'true';
        button.addEventListener('click', async () => {
            if (playing) {
                controller.stop();
                setPlaying(false);
                status.textContent = button.dataset.stoppedMessage || '';

                return;
            }

            const text = readableText(target);
            if (text === '') return;

            setPlaying(true);
            status.textContent = button.dataset.playingMessage || '';
            try {
                await controller.speak(text);
                status.textContent = button.dataset.finishedMessage || '';
            } catch (error) {
                if (!isPlaybackCancellation(error)) {
                    status.textContent = button.dataset.failedMessage || '';
                }
            } finally {
                setPlaying(false);
            }
        });
    });
}

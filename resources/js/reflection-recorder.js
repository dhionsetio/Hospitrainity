/**
 * Hospitrainity Warm-Up Reflection Audio Recorder
 * Pure Web MediaRecorder factory with fallback codec resolution & accessible localized announcements.
 */

export function createReflectionRecorder({
    mediaDevices = typeof navigator !== 'undefined' ? navigator.mediaDevices : null,
    MediaRecorderCtor = typeof MediaRecorder !== 'undefined' ? MediaRecorder : null,
    isTypeSupported = typeof MediaRecorder !== 'undefined' && typeof MediaRecorder.isTypeSupported === 'function'
        ? (type) => MediaRecorder.isTypeSupported(type)
        : () => false,
    isSecureContext = typeof window !== 'undefined' ? Boolean(window.isSecureContext) : false,
    strings = {},
} = {}) {
    let state = 'idle'; // 'idle' | 'recording' | 'stopped' | 'disabled'
    let mediaRecorder = null;
    let audioChunks = [];
    let mediaStream = null;

    const defaultStrings = {
        started: 'Voice recording started. Speak into your microphone.',
        finished: 'Voice recording finished and attached.',
        discarded: 'Recording discarded.',
        unavailable: 'Microphone access is unavailable or disabled.',
        ...strings,
    };

    function resolveMimeType() {
        if (!isTypeSupported) return '';
        const candidateTypes = [
            'audio/webm;codecs=opus',
            'audio/webm',
            'audio/mp4',
            'audio/ogg',
        ];
        for (const type of candidateTypes) {
            if (isTypeSupported(type)) {
                return type;
            }
        }
        return '';
    }

    return {
        getState: () => state,
        isSupported: () => isSecureContext && Boolean(mediaDevices?.getUserMedia),
        resolveMimeType,
        start: async (onDataAvailable, onStop, onError) => {
            if (!isSecureContext || !mediaDevices?.getUserMedia) {
                state = 'disabled';
                onError?.({ code: 'mic_unavailable', message: defaultStrings.unavailable });
                return;
            }

            audioChunks = [];
            try {
                mediaStream = await mediaDevices.getUserMedia({ audio: true });
            } catch (err) {
                state = 'disabled';
                const code = err && err.name === 'NotFoundError' ? 'NotFoundError' : 'NotAllowedError';
                onError?.({ code, message: defaultStrings.unavailable });
                return;
            }

            const mimeType = resolveMimeType();
            const options = mimeType ? { mimeType } : {};

            try {
                mediaRecorder = MediaRecorderCtor ? new MediaRecorderCtor(mediaStream, options) : null;
            } catch {
                try {
                    mediaRecorder = MediaRecorderCtor ? new MediaRecorderCtor(mediaStream) : null;
                } catch {
                    state = 'disabled';
                    onError?.({ code: 'construction_failed', message: defaultStrings.unavailable });
                    return;
                }
            }

            if (!mediaRecorder) {
                state = 'disabled';
                onError?.({ code: 'unsupported', message: defaultStrings.unavailable });
                return;
            }

            mediaRecorder.ondataavailable = (event) => {
                if (event.data && event.data.size > 0) {
                    audioChunks.push(event.data);
                    onDataAvailable?.(event.data);
                }
            };

            mediaRecorder.onstop = () => {
                if (mediaStream) {
                    mediaStream.getTracks().forEach((track) => track.stop());
                }

                const chosenType = mediaRecorder.mimeType || mimeType || 'audio/webm';
                const blob = new Blob(audioChunks, { type: chosenType });
                const ext = chosenType.includes('mp4') ? 'm4a' : (chosenType.includes('ogg') ? 'ogg' : 'webm');

                state = 'stopped';
                onStop?.({ blob, mimeType: chosenType, extension: ext, message: defaultStrings.finished });
            };

            mediaRecorder.start();
            state = 'recording';
        },
        stop: () => {
            if (mediaRecorder && mediaRecorder.state === 'recording') {
                mediaRecorder.stop();
            }
        },
    };
}

export function initReflectionRecorder() {
    if (typeof document === 'undefined') return;
    const containers = document.querySelectorAll('[data-reflection-recorder]');
    if (!containers.length) return;

    containers.forEach((container) => {
        setupRecorderContainer(container);
    });
}

function setupRecorderContainer(container) {
    const recordBtn = container.querySelector('[data-mic-record]');
    const stopBtn = container.querySelector('[data-mic-stop]');
    const discardBtn = container.querySelector('[data-mic-discard]');
    const statusRegion = container.querySelector('[data-mic-status]');
    const noticeRegion = container.querySelector('[data-mic-notice]');
    const audioPreview = container.querySelector('[data-mic-preview]');
    const fileInput = container.querySelector('[data-mic-file-input]');

    if (!recordBtn) return;

    const strings = {
        started: container.getAttribute('data-string-recording-started') || 'Voice recording started. Speak into your microphone.',
        finished: container.getAttribute('data-string-recording-finished') || 'Voice recording finished and attached.',
        discarded: container.getAttribute('data-string-recording-discarded') || 'Recording discarded.',
        unavailable: container.getAttribute('data-string-mic-unavailable') || 'Microphone access is unavailable or disabled.',
    };

    const recorder = createReflectionRecorder({ strings });
    let previewObjectUrl = null;

    function announce(message) {
        if (statusRegion) {
            statusRegion.textContent = message;
        }
    }

    function disableRecorder() {
        recordBtn.disabled = true;
        recordBtn.setAttribute('aria-disabled', 'true');
        recordBtn.classList.add('opacity-60', 'cursor-not-allowed');
        if (noticeRegion) {
            noticeRegion.classList.remove('hidden');
        }
        announce(strings.unavailable);
    }

    if (!recorder.isSupported()) {
        disableRecorder();
        return;
    }

    recordBtn.addEventListener('click', () => {
        recorder.start(
            null,
            ({ blob, extension, message }) => {
                if (previewObjectUrl) {
                    URL.revokeObjectURL(previewObjectUrl);
                }
                previewObjectUrl = URL.createObjectURL(blob);

                if (audioPreview) {
                    audioPreview.src = previewObjectUrl;
                    audioPreview.classList.remove('hidden');
                }

                if (fileInput && typeof DataTransfer !== 'undefined') {
                    const file = new File([blob], `recorded-reflection.${extension}`, { type: blob.type });
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    fileInput.files = dt.files;
                }

                if (stopBtn) stopBtn.classList.add('hidden');
                if (recordBtn) recordBtn.classList.remove('hidden');
                if (discardBtn) discardBtn.classList.remove('hidden');

                announce(message);
            },
            () => {
                disableRecorder();
            }
        );

        if (recordBtn) recordBtn.classList.add('hidden');
        if (stopBtn) stopBtn.classList.remove('hidden');
        announce(strings.started);
    });

    if (stopBtn) {
        stopBtn.addEventListener('click', () => {
            recorder.stop();
        });
    }

    if (discardBtn) {
        discardBtn.addEventListener('click', () => {
            if (previewObjectUrl) {
                URL.revokeObjectURL(previewObjectUrl);
                previewObjectUrl = null;
            }
            if (audioPreview) {
                audioPreview.src = '';
                audioPreview.classList.add('hidden');
            }
            if (fileInput) {
                fileInput.value = '';
            }
            discardBtn.classList.add('hidden');
            announce(strings.discarded);
        });
    }
}

// Auto-boot on DOMContentLoaded
if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => {
        initReflectionRecorder();
    });
}

export class MediaPlaybackError extends Error {
    constructor(code, message, options = {}) {
        super(message, options);
        this.name = "MediaPlaybackError";
        this.code = code;
    }
}

export function isPlaybackCancellation(error) {
    return error instanceof MediaPlaybackError && error.code === "cancelled";
}

function errorMessage(cause, fallback) {
    return cause instanceof Error && cause.message ? cause.message : fallback;
}

export function createMediaController({
    AudioCtor = globalThis.Audio,
    speechSynthesisImpl = globalThis.speechSynthesis,
    UtteranceCtor = globalThis.SpeechSynthesisUtterance,
    language = "en-US",
} = {}) {
    let cancelActive = null;

    function stop() {
        const cancel = cancelActive;
        cancelActive = null;
        if (cancel) cancel();
    }

    function playUrl(url) {
        const source = typeof url === "string" ? url.trim() : "";
        if (!source) {
            return Promise.reject(new MediaPlaybackError("invalid-audio-url", "An audio URL is required."));
        }
        if (typeof AudioCtor !== "function") {
            return Promise.reject(new MediaPlaybackError("audio-unsupported", "Audio playback is unavailable."));
        }

        stop();

        return new Promise((resolve, reject) => {
            let finished = false;
            let audio;

            const finish = (callback, value) => {
                if (finished) return;
                finished = true;
                if (cancelActive === cancel) cancelActive = null;
                callback(value);
            };
            const fail = cause => finish(
                reject,
                new MediaPlaybackError(
                    "audio-failed",
                    errorMessage(cause, "The audio file could not be played."),
                    { cause },
                ),
            );
            const cancel = () => {
                try {
                    audio?.pause?.();
                    if (audio && "currentTime" in audio) audio.currentTime = 0;
                } catch {
                    // Cancellation is best effort; the promise still settles.
                }
                finish(reject, new MediaPlaybackError("cancelled", "Playback was cancelled."));
            };

            try {
                audio = new AudioCtor(source);
                audio.addEventListener?.("ended", () => finish(resolve));
                audio.addEventListener?.("error", event => fail(event?.error || audio.error));
                if (!audio.addEventListener) {
                    audio.onended = () => finish(resolve);
                    audio.onerror = event => fail(event?.error || audio.error);
                }
                cancelActive = cancel;

                Promise.resolve(audio.play()).catch(fail);
            } catch (error) {
                fail(error);
            }
        });
    }

    function speak(text) {
        const prompt = typeof text === "string" ? text.trim() : "";
        if (!prompt) {
            return Promise.reject(new MediaPlaybackError("invalid-speech-text", "Speech text is required."));
        }
        if (!speechSynthesisImpl || typeof UtteranceCtor !== "function") {
            return Promise.reject(new MediaPlaybackError("tts-unsupported", "Speech synthesis is unavailable."));
        }

        stop();

        return new Promise((resolve, reject) => {
            let finished = false;
            const utterance = new UtteranceCtor(prompt);

            const finish = (callback, value) => {
                if (finished) return;
                finished = true;
                if (cancelActive === cancel) cancelActive = null;
                callback(value);
            };
            const cancel = () => {
                try {
                    speechSynthesisImpl.cancel();
                } catch {
                    // Cancellation is best effort; the promise still settles.
                }
                finish(reject, new MediaPlaybackError("cancelled", "Speech was cancelled."));
            };

            utterance.lang = language;
            const voices = typeof speechSynthesisImpl.getVoices === "function"
                ? speechSynthesisImpl.getVoices()
                : [];
            const voice = voices.find(candidate => /en[-_]US/i.test(candidate.lang))
                || voices.find(candidate => /^en/i.test(candidate.lang));
            if (voice) utterance.voice = voice;

            utterance.onend = () => finish(resolve);
            utterance.onerror = event => finish(
                reject,
                new MediaPlaybackError(
                    "tts-failed",
                    `Speech synthesis failed${event?.error ? `: ${event.error}` : "."}`,
                    { cause: event },
                ),
            );
            cancelActive = cancel;

            try {
                speechSynthesisImpl.speak(utterance);
            } catch (error) {
                finish(
                    reject,
                    new MediaPlaybackError(
                        "tts-failed",
                        errorMessage(error, "Speech synthesis failed."),
                        { cause: error },
                    ),
                );
            }
        });
    }

    async function playPrompt({ audioUrl = null, promptText = "", onFallback = null } = {}) {
        if (typeof audioUrl === "string" && audioUrl.trim() !== "") {
            try {
                return await playUrl(audioUrl);
            } catch (error) {
                if (isPlaybackCancellation(error)) throw error;
                if (typeof onFallback === "function") onFallback(error);
            }
        }

        return speak(promptText);
    }

    return Object.freeze({ playPrompt, playUrl, speak, stop });
}

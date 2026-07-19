import assert from "node:assert/strict";
import test from "node:test";

import {
    MediaPlaybackError,
    createMediaController,
    isPlaybackCancellation,
} from "../../resources/js/media-playback.js";

class FakeAudio extends EventTarget {
    static instances = [];
    static playError = null;

    constructor(url) {
        super();
        this.url = url;
        this.currentTime = 0;
        this.paused = false;
        FakeAudio.instances.push(this);
    }

    play() {
        return FakeAudio.playError ? Promise.reject(FakeAudio.playError) : Promise.resolve();
    }

    pause() {
        this.paused = true;
    }
}

class FakeUtterance {
    constructor(text) {
        this.text = text;
        this.lang = "";
        this.voice = null;
        this.onend = null;
        this.onerror = null;
    }
}

function speechDouble({ fail = false } = {}) {
    return {
        cancelled: false,
        spoken: [],
        cancel() { this.cancelled = true; },
        getVoices() { return [{ lang: "en-US", name: "Test English" }]; },
        speak(utterance) {
            this.spoken.push(utterance);
            queueMicrotask(() => {
                if (fail) utterance.onerror?.({ error: "synthesis-failed" });
                else utterance.onend?.();
            });
        },
    };
}

test.beforeEach(() => {
    FakeAudio.instances = [];
    FakeAudio.playError = null;
});

test("audio playback resolves only after the media ends", async () => {
    const controller = createMediaController({
        AudioCtor: FakeAudio,
        speechSynthesisImpl: speechDouble(),
        UtteranceCtor: FakeUtterance,
    });
    let resolved = false;
    const playback = controller.playUrl("/storage/prompt.mp3").then(() => { resolved = true; });
    await Promise.resolve();
    assert.equal(resolved, false);
    FakeAudio.instances[0].dispatchEvent(new Event("ended"));
    await playback;
    assert.equal(resolved, true);
});

test("a failed recording falls back to explicit prompt text", async () => {
    const speech = speechDouble();
    FakeAudio.playError = new Error("missing file");
    const controller = createMediaController({
        AudioCtor: FakeAudio,
        speechSynthesisImpl: speech,
        UtteranceCtor: FakeUtterance,
    });
    let fallbackCause;

    await controller.playPrompt({
        audioUrl: "/storage/missing.mp3",
        promptText: "reservation",
        onFallback: error => { fallbackCause = error; },
    });

    assert.equal(fallbackCause.code, "audio-failed");
    assert.equal(speech.spoken[0].text, "reservation");
    assert.equal(speech.spoken[0].lang, "en-US");
});

test("speech errors reject and do not leave playback stuck", async () => {
    const controller = createMediaController({
        AudioCtor: FakeAudio,
        speechSynthesisImpl: speechDouble({ fail: true }),
        UtteranceCtor: FakeUtterance,
    });

    await assert.rejects(
        controller.speak("front desk"),
        error => error instanceof MediaPlaybackError && error.code === "tts-failed",
    );
});

test("stopping active media settles it as an intentional cancellation", async () => {
    const controller = createMediaController({
        AudioCtor: FakeAudio,
        speechSynthesisImpl: speechDouble(),
        UtteranceCtor: FakeUtterance,
    });
    const playback = controller.playUrl("/storage/long.wav");
    controller.stop();

    await assert.rejects(playback, isPlaybackCancellation);
    assert.equal(FakeAudio.instances[0].paused, true);
});

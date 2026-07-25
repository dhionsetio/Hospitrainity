import assert from "node:assert/strict";
import test from "node:test";
import { createReflectionRecorder, initReflectionRecorder } from "../../resources/js/reflection-recorder.js";

test("initReflectionRecorder initializes without crashing in non-browser env", () => {
    assert.doesNotThrow(() => {
        initReflectionRecorder();
    });
});

test("createReflectionRecorder picks audio/webm;codecs=opus when supported", () => {
    const recorder = createReflectionRecorder({
        isSecureContext: true,
        mediaDevices: { getUserMedia: async () => ({ getTracks: () => [] }) },
        isTypeSupported: (type) => type === "audio/webm;codecs=opus" || type === "audio/mp4",
    });

    assert.equal(recorder.resolveMimeType(), "audio/webm;codecs=opus");
});

test("createReflectionRecorder falls back to audio/mp4 when only audio/mp4 is supported", () => {
    const recorder = createReflectionRecorder({
        isSecureContext: true,
        mediaDevices: { getUserMedia: async () => ({ getTracks: () => [] }) },
        isTypeSupported: (type) => type === "audio/mp4",
    });

    assert.equal(recorder.resolveMimeType(), "audio/mp4");
});

test("createReflectionRecorder returns empty MIME type when none supported", () => {
    const recorder = createReflectionRecorder({
        isSecureContext: true,
        mediaDevices: { getUserMedia: async () => ({ getTracks: () => [] }) },
        isTypeSupported: () => false,
    });

    assert.equal(recorder.resolveMimeType(), "");
});

test("createReflectionRecorder disables itself on getUserMedia NotAllowedError", async () => {
    const recorder = createReflectionRecorder({
        isSecureContext: true,
        mediaDevices: {
            getUserMedia: async () => {
                const err = new Error("Permission denied");
                err.name = "NotAllowedError";
                throw err;
            },
        },
        isTypeSupported: () => true,
    });

    let reportedError = null;
    await recorder.start(
        null,
        null,
        (error) => {
            reportedError = error;
        }
    );

    assert.equal(recorder.getState(), "disabled");
    assert.equal(reportedError?.code, "NotAllowedError");
});

test("createReflectionRecorder disables itself on getUserMedia NotFoundError", async () => {
    const recorder = createReflectionRecorder({
        isSecureContext: true,
        mediaDevices: {
            getUserMedia: async () => {
                const err = new Error("Device not found");
                err.name = "NotFoundError";
                throw err;
            },
        },
        isTypeSupported: () => true,
    });

    let reportedError = null;
    await recorder.start(
        null,
        null,
        (error) => {
            reportedError = error;
        }
    );

    assert.equal(recorder.getState(), "disabled");
    assert.equal(reportedError?.code, "NotFoundError");
});

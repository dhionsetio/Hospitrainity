import assert from "node:assert/strict";
import test from "node:test";

import {
    ProgressPersistenceError,
    saveProgress,
} from "../../resources/js/progress.js";

test("saveProgress posts the expected authenticated JSON and awaits success", async () => {
    let request;
    const response = await saveProgress({
        url: "/progress",
        items: [10, 20],
        type: "Exercise",
        csrfToken: "known-token",
        fetchImpl: async (url, options) => {
            request = { url, options };
            return { ok: true, status: 200 };
        },
    });

    assert.equal(response.status, 200);
    assert.equal(request.url, "/progress");
    assert.equal(request.options.method, "POST");
    assert.equal(request.options.credentials, "same-origin");
    assert.equal(request.options.headers["X-CSRF-TOKEN"], "known-token");
    assert.deepEqual(JSON.parse(request.options.body), {
        items: [10, 20],
        type: "Exercise",
    });
});

test("saveProgress preserves actionable authentication, validation, throttle, and server statuses", async t => {
    for (const status of [419, 422, 429, 500]) {
        await t.test(String(status), async () => {
            await assert.rejects(
                saveProgress({
                    url: "/progress",
                    items: [10],
                    type: "Exercise",
                    csrfToken: "known-token",
                    fetchImpl: async () => ({ ok: false, status }),
                }),
                error => error instanceof ProgressPersistenceError && error.status === status,
            );
        });
    }
});

test("saveProgress rejects network failures", async () => {
    await assert.rejects(
        saveProgress({
            url: "/progress",
            items: [10],
            type: "Exercise",
            csrfToken: "known-token",
            fetchImpl: async () => { throw new TypeError("offline"); },
        }),
        error => error instanceof ProgressPersistenceError && error.cause instanceof TypeError,
    );
});

test("saveProgress validates its input before sending", async () => {
    await assert.rejects(
        saveProgress({
            url: "/progress",
            items: [],
            type: "Exercise",
            csrfToken: "known-token",
            fetchImpl: async () => ({ ok: true, status: 200 }),
        }),
        ProgressPersistenceError,
    );
});

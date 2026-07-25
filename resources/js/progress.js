export class ProgressPersistenceError extends Error {
    constructor(message, { cause, status = null } = {}) {
        super(message, { cause });
        this.name = "ProgressPersistenceError";
        this.status = status;
    }
}

export function readCsrfToken(documentRef = globalThis.document) {
    const token = documentRef
        ?.querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content")
        ?.trim();

    if (!token) {
        throw new ProgressPersistenceError("The CSRF token is missing.");
    }

    return token;
}

/**
 * Persist learner progress and resolve only after the server confirms success.
 * Callers must keep the learner on the page when this function rejects.
 */
export async function saveProgress({
    url,
    items,
    type,
    csrfToken,
    signal,
    fetchImpl = globalThis.fetch,
}) {
    if (!url || !Array.isArray(items) || items.length === 0 || !type) {
        throw new ProgressPersistenceError("The progress request is incomplete.");
    }

    if (typeof fetchImpl !== "function") {
        throw new ProgressPersistenceError("Progress saving is unavailable in this browser.");
    }

    let response;
    try {
        response = await fetchImpl(url, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken || readCsrfToken(),
            },
            body: JSON.stringify({ items, type }),
            signal,
        });
    } catch (error) {
        if (error instanceof ProgressPersistenceError) throw error;

        throw new ProgressPersistenceError("The progress request could not reach the server.", {
            cause: error,
        });
    }

    if (!response.ok) {
        throw new ProgressPersistenceError("The server did not accept the progress update.", {
            status: response.status,
        });
    }

    return response;
}

/**
 * Persist an exercise score and resolve only after the server confirms success.
 */
export async function saveScore({
    url,
    exerciseId,
    score,
    maxScore,
    responseData = null,
    csrfToken,
    signal,
    fetchImpl = globalThis.fetch,
}) {
    if (!url || !exerciseId || score === undefined || score === null || maxScore === undefined || maxScore === null) {
        throw new ProgressPersistenceError("The score request is incomplete.");
    }

    if (typeof fetchImpl !== "function") {
        throw new ProgressPersistenceError("Score saving is unavailable in this browser.");
    }

    let response;
    try {
        response = await fetchImpl(url, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": csrfToken || readCsrfToken(),
            },
            body: JSON.stringify({
                exercise_id: exerciseId,
                score,
                max_score: maxScore,
                response_data: responseData,
            }),
            signal,
        });
    } catch (error) {
        if (error instanceof ProgressPersistenceError) throw error;

        throw new ProgressPersistenceError("The score request could not reach the server.", {
            cause: error,
        });
    }

    if (!response.ok) {
        throw new ProgressPersistenceError("The server did not accept the score update.", {
            status: response.status,
        });
    }

    return response;
}

export function bufferOfflineScore({ exerciseId, score, maxScore, responseData = null }) {
    try {
        const raw = localStorage.getItem("hsp_offline_scores");
        const scores = raw ? JSON.parse(raw) : [];
        scores.push({ exerciseId, score, maxScore, responseData, timestamp: Date.now() });
        localStorage.setItem("hsp_offline_scores", JSON.stringify(scores));
    } catch (e) {
        console.warn("Failed to buffer offline score:", e);
    }
}

export async function syncOfflineScores({ url = "/scores/store", fetchImpl = globalThis.fetch } = {}) {
    let raw;
    try {
        raw = localStorage.getItem("hsp_offline_scores");
    } catch {
        return;
    }
    if (!raw) return;

    let scores;
    try {
        scores = JSON.parse(raw);
    } catch {
        localStorage.removeItem("hsp_offline_scores");
        return;
    }

    if (!Array.isArray(scores) || scores.length === 0) return;

    const remaining = [];
    for (const item of scores) {
        try {
            await saveScore({
                url,
                exerciseId: item.exerciseId,
                score: item.score,
                maxScore: item.maxScore,
                responseData: item.responseData,
                fetchImpl,
            });
        } catch {
            remaining.push(item);
        }
    }

    try {
        if (remaining.length > 0) {
            localStorage.setItem("hsp_offline_scores", JSON.stringify(remaining));
        } else {
            localStorage.removeItem("hsp_offline_scores");
        }
    } catch (e) {
        console.warn("Failed to update offline score buffer:", e);
    }
}

if (typeof window !== "undefined") {
    window.addEventListener("online", () => {
        syncOfflineScores().catch(() => {});
    });
}



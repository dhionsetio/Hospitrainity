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


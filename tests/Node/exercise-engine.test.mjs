import assert from "node:assert/strict";
import test from "node:test";
import { JSDOM } from "jsdom";

import { initExercises } from "../../resources/js/exercises/engine.js";

const fixtures = {
    spelling_quiz: { correct_answer: "reservation", prompt_text: "reservation" },
    matching_game: {
        pairs: [
            { question: "Hello", answer: "Halo" },
            { question: "Thanks", answer: "Terima kasih" },
        ],
    },
    fill_in_the_blank: { sentence_parts: ["Welcome ", " the hotel."], correct_answer: "to" },
    listening_task: { instruction: "Choose one", options: ["Good morning", "Good evening"], correct_answer: "Good evening" },
    speaking_practice: { prompt_text: "Welcome to our hotel." },
    sentence_scramble: { sentence: "Your room is ready" },
    translation_match: { question_word: "room", options: ["meja", "kamar"], correct_answer: "kamar" },
    fill_with_options: { sentence_parts: ["The guest ", " early."], options: ["left", "arrived"], correct_answer: "arrived" },
    multiple_choice_quiz: { question_text: "Choose A", options: ["B", "A"], correct_answer: "A" },
    fill_multiple_blanks: { sentence_parts: ["The ", " is ", "."], correct_answers: ["room", "ready"] },
    silent_letter_hunt: { sentence: "Use a knife.", words: [{ word: "knife", silent_letter_index: 0 }] },
    pronunciation_drill: { prompt_text: "Could I see your passport, please?" },
    sound_sorting: {
        categories: [{ id: "short", name: "Short" }, { id: "long", name: "Long" }],
        words: [{ word: "ship", category_id: "short" }, { word: "sheep", category_id: "long" }],
    },
    sequencing: { steps: ["Greet the guest", "Confirm the reservation"] },
};

class InstantUtterance {
    constructor(text) {
        this.text = text;
        this.onend = null;
        this.onerror = null;
    }
}

class InstantAudio extends EventTarget {
    play() {
        queueMicrotask(() => this.dispatchEvent(new Event("ended")));
        return Promise.resolve();
    }
    pause() {}
}

function pageFor(type, { exercises = null, fetchImpl = async () => ({ ok: true, status: 200 }) } = {}) {
    const dom = new JSDOM(`<!doctype html><html><head><meta name="csrf-token" content="test-token"></head><body>
        <div id="exercises-data"></div>
        <h2 id="exercise-title"></h2>
        <div id="game-container"></div>
        <div id="footer-container"><div id="feedback-footer"><div id="feedback-text"></div></div></div>
        <div id="exercise-media-status"></div>
        <div id="progress-save-error" class="hidden" tabindex="-1"><p id="progress-save-error-message"></p></div>
        <button id="progress-retry-button" type="button">Retry save</button>
        <progress id="progress-bar" value="0" max="100"></progress><p id="item-counter"></p>
        <button class="side-nav-item" data-index="0" type="button">1</button>
        <button class="side-nav-item" data-index="1" type="button">2</button>
        <button id="prev-button" type="button">Previous</button>
        <button id="answer-retry-button" class="hidden" type="button">Try Again</button>
        <button id="check-button" type="button">Check</button>
        <button id="next-button" class="hidden" type="button">Next</button>
    </body></html>`, { url: "https://hospitrainity.test/lesson/1/exercises" });

    const { window } = dom;
    window.HTMLElement.prototype.scrollIntoView = () => {};
    const speech = {
        cancel() {},
        getVoices() { return [{ lang: "en-US", name: "Test" }]; },
        speak(utterance) { queueMicrotask(() => utterance.onend?.()); },
    };
    window.speechSynthesis = speech;
    window.SpeechSynthesisUtterance = InstantUtterance;
    window.Audio = InstantAudio;

    globalThis.window = window;
    globalThis.document = window.document;
    globalThis.speechSynthesis = speech;
    globalThis.SpeechSynthesisUtterance = InstantUtterance;
    globalThis.Audio = InstantAudio;

    const requests = [];
    globalThis.fetch = async (url, options) => {
        requests.push({ url, options });
        return fetchImpl(url, options);
    };

    const data = window.document.getElementById("exercises-data");
    data.dataset.i18n = "{}";
    data.dataset.returnUrl = "/lesson/1";
    data.dataset.progressUrl = "/progress";
    data.dataset.exercises = JSON.stringify(exercises ?? [
        { id: 100, title: type, type, content: fixtures[type] },
        { id: 101, title: "Next fixture", type: "pronunciation_drill", content: { prompt_text: "Next" } },
    ]);

    initExercises();
    return { dom, requests };
}

function option(value) {
    return [...document.querySelectorAll(".option-button")]
        .find(button => button.dataset.value === value);
}

function assertNamedNativeControls(type) {
    for (const button of document.querySelectorAll("#game-container button")) {
        const name = button.textContent.trim() || button.getAttribute("aria-label") || button.title;
        assert.ok(name, `${type} rendered an unnamed button`);
    }
    for (const input of document.querySelectorAll("#game-container input")) {
        assert.ok(input.getAttribute("aria-label"), `${type} rendered an unnamed input`);
    }
}

function pressEnter(input) {
    input.dispatchEvent(new window.KeyboardEvent("keydown", { key: "Enter", bubbles: true }));
}

function retryIncorrect(type) {
    const retry = document.getElementById("answer-retry-button");
    const next = document.getElementById("next-button");
    assert.equal(retry.classList.contains("hidden"), false, `${type} did not expose Retry after an incorrect attempt`);
    assert.equal(next.classList.contains("hidden"), false, `${type} did not preserve attempted-completion navigation`);
    retry.click();
}

function clickWordsInOrder(sentence) {
    for (const word of sentence.split(" ")) {
        const button = [...document.querySelectorAll(".word-bank-button")]
            .find(candidate => candidate.dataset.word === word && !candidate.disabled);
        button.click();
    }
}

async function completeMatching(content) {
    for (const pair of content.pairs) {
        document.querySelector(`.match-item[data-type="question"][data-value="${pair.question}"]`).click();
        document.querySelector(`.match-item[data-type="answer"][data-value="${pair.answer}"]`).click();
        await new Promise(resolve => setTimeout(resolve, 120));
    }
}

function placeSoundWords(correctly) {
    for (const token of [...document.querySelectorAll(".sort-token")]) {
        const targets = [...document.querySelectorAll(".sort-bucket-target")];
        const target = targets.find(candidate => {
            const category = candidate.closest(".sort-bucket").dataset.category;
            return correctly ? category === token.dataset.correctCategory : category !== token.dataset.correctCategory;
        });
        token.click();
        target.click();
    }
}

function orderSequence(steps, correctly) {
    const rows = [...document.querySelectorAll(".seq-item")];
    const current = rows.map(row => row.querySelector(".flex-1").textContent);
    const isCorrect = current.every((step, index) => step === steps[index]);
    if (isCorrect === correctly) return;
    document.querySelector('.seq-down[data-index="0"]').click();
}

async function exercisePath(type) {
    const content = fixtures[type];

    switch (type) {
        case "spelling_quiz": {
            let input = document.querySelector("#game-container input");
            input.value = "wrong";
            pressEnter(input);
            retryIncorrect(type);
            input = document.querySelector("#game-container input");
            assert.equal(document.activeElement, input, "spelling retry did not restore focus to the answer field");
            input.value = content.correct_answer;
            pressEnter(input);
            break;
        }
        case "matching_game": {
            const wrongQuestion = document.querySelector('.match-item[data-type="question"][data-value="Hello"]');
            const wrongAnswer = document.querySelector('.match-item[data-type="answer"][data-value="Terima kasih"]');
            wrongQuestion.click();
            wrongAnswer.click();
            assert.ok(wrongQuestion.classList.contains("incorrect"), "matching game did not expose an incorrect attempt");
            await new Promise(resolve => setTimeout(resolve, 1050));
            await completeMatching(content);
            break;
        }
        case "fill_in_the_blank": {
            let input = document.querySelector("#game-container input");
            input.value = "wrong";
            pressEnter(input);
            retryIncorrect(type);
            input = document.querySelector("#game-container input");
            input.value = content.correct_answer;
            pressEnter(input);
            break;
        }
        case "listening_task":
        case "translation_match":
        case "fill_with_options":
        case "multiple_choice_quiz": {
            option(content.options.find(value => value !== content.correct_answer)).click();
            retryIncorrect(type);
            option(content.correct_answer).click();
            break;
        }
        case "speaking_practice":
        case "pronunciation_drill":
            document.querySelector("#game-container button").click();
            await Promise.resolve();
            break;
        case "sentence_scramble": {
            document.querySelector(".word-bank-button").click();
            document.getElementById("check-button").click();
            retryIncorrect(type);
            clickWordsInOrder(content.sentence);
            document.getElementById("check-button").click();
            break;
        }
        case "fill_multiple_blanks": {
            let inputs = [...document.querySelectorAll(".fill-input")];
            inputs.forEach(input => { input.value = "wrong"; });
            pressEnter(inputs[0]);
            retryIncorrect(type);
            inputs = [...document.querySelectorAll(".fill-input")];
            inputs.forEach((input, index) => { input.value = content.correct_answers[index]; });
            pressEnter(inputs[0]);
            break;
        }
        case "silent_letter_hunt":
            document.querySelector(".word-button").click();
            break;
        case "sound_sorting":
            placeSoundWords(false);
            document.getElementById("check-button").click();
            retryIncorrect(type);
            placeSoundWords(true);
            document.getElementById("check-button").click();
            break;
        case "sequencing":
            orderSequence(content.steps, false);
            document.getElementById("check-button").click();
            retryIncorrect(type);
            orderSequence(content.steps, true);
            document.getElementById("check-button").click();
            break;
        default:
            throw new Error(`Missing exercise path for ${type}`);
    }
}

test("all fourteen exercise renderers support named keyboard controls, retry semantics, and persisted completion", async t => {
    for (const type of Object.keys(fixtures)) {
        await t.test(type, async () => {
            const { dom, requests } = pageFor(type);
            assertNamedNativeControls(type);
            await exercisePath(type);

            const next = document.getElementById("next-button");
            assert.equal(next.classList.contains("hidden"), false, `${type} did not expose completion navigation`);
            assertNamedNativeControls(type);
            next.click();
            await new Promise(resolve => setTimeout(resolve, 0));

            assert.equal(requests.length, 1, `${type} did not persist completion exactly once`);
            assert.deepEqual(JSON.parse(requests[0].options.body), { items: [100], type: "Exercise" });
            assert.equal(document.getElementById("exercise-title").textContent, "Next fixture");
            dom.window.close();
        });
    }
});

test("exercise renderers treat titles, prompts, answers, options, sentences, categories, and URLs as data", async t => {
    const payload = '"><img src=x onerror="globalThis.__xssExecuted=true">';
    const javascriptUrl = "javascript:globalThis.__xssExecuted=true";
    const cases = [
        {
            name: "title, prompt, answer, and option",
            type: "multiple_choice_quiz",
            content: { question_text: payload, options: [payload, "safe"], correct_answer: payload },
            exerciseTitle: payload,
            interact() { option("safe").click(); },
        },
        {
            name: "sentence",
            type: "sentence_scramble",
            content: { sentence: payload },
        },
        {
            name: "category",
            type: "sound_sorting",
            content: {
                categories: [{ id: "unsafe", name: payload }],
                words: [{ word: payload, category_id: "unsafe" }],
            },
        },
        {
            name: "prompt, answer, and URL",
            type: "spelling_quiz",
            content: { correct_answer: payload, prompt_text: payload, audio_url: javascriptUrl },
            interact() {
                const input = document.querySelector("#game-container input");
                input.value = "wrong";
                pressEnter(input);
            },
        },
    ];

    for (const fixture of cases) {
        await t.test(fixture.name, () => {
            globalThis.__xssExecuted = false;
            const { dom } = pageFor(fixture.type, {
                exercises: [{
                    id: 200,
                    title: fixture.exerciseTitle ?? fixture.type,
                    type: fixture.type,
                    content: fixture.content,
                }],
            });
            fixture.interact?.();

            assert.equal(document.querySelector("#game-container img, #game-container script, #game-container iframe, #game-container svg"), null);
            assert.equal(document.querySelector('[src^="javascript:"], [href^="javascript:"]'), null);
            assert.equal(globalThis.__xssExecuted, false);
            dom.window.close();
        });
    }
});

test("a pending progress request blocks double-click and navigation until persistence succeeds", async () => {
    let resolveRequest;
    const response = new Promise(resolve => { resolveRequest = resolve; });
    const { dom, requests } = pageFor("pronunciation_drill", {
        fetchImpl: async () => response,
    });

    document.querySelector("#game-container button").click();
    await Promise.resolve();
    const next = document.getElementById("next-button");
    next.click();
    next.click();
    document.querySelector('.side-nav-item[data-index="1"]').click();

    assert.equal(requests.length, 1);
    assert.equal(next.disabled, true);
    assert.equal(document.getElementById("exercise-title").textContent, "pronunciation_drill");

    resolveRequest({ ok: true, status: 200 });
    await new Promise(resolve => setTimeout(resolve, 0));
    assert.equal(document.getElementById("exercise-title").textContent, "Next fixture");
    dom.window.close();
});

test("a failed progress request keeps the learner in place and retry completes the original navigation", async () => {
    let attempt = 0;
    const originalConsoleError = console.error;
    console.error = () => {};

    try {
        const { dom, requests } = pageFor("pronunciation_drill", {
            fetchImpl: async () => (++attempt === 1
                ? { ok: false, status: 500 }
                : { ok: true, status: 200 }),
        });

        document.querySelector("#game-container button").click();
        await Promise.resolve();
        document.getElementById("next-button").click();
        await new Promise(resolve => setTimeout(resolve, 0));

        const progressError = document.getElementById("progress-save-error");
        assert.equal(document.getElementById("exercise-title").textContent, "pronunciation_drill");
        assert.equal(progressError.classList.contains("hidden"), false);
        assert.equal(document.activeElement, progressError);

        document.getElementById("progress-retry-button").click();
        await new Promise(resolve => setTimeout(resolve, 0));
        assert.equal(requests.length, 2);
        assert.equal(document.getElementById("exercise-title").textContent, "Next fixture");
        dom.window.close();
    } finally {
        console.error = originalConsoleError;
    }
});

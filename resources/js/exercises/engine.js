import { saveProgress } from "../progress.js";
import { createMediaController, isPlaybackCancellation } from "../media-playback.js";

let allExercises = [];
let currentExerciseIndex = 0;
let returnUrl = "";
let progressUrl = "";
let progressSavePending = false;
let retryProgressAction = null;
let selectedItems = { question: null, answer: null };
let I18N = {};
let mediaController = null;

const ui = {};

function setVisible(node, visible) {
    node.classList.toggle("hidden", !visible);
}

function t(key, fallback, replacements) {
    let value = Object.prototype.hasOwnProperty.call(I18N, key) ? I18N[key] : fallback;

    if (replacements) {
        for (const [name, replacement] of Object.entries(replacements)) {
            value = value.split(`:${name}`).join(String(replacement));
        }
    }

    return String(value);
}

function element(tagName, { className = "", text = null, type = null } = {}) {
    const node = document.createElement(tagName);
    if (className) node.className = className;
    if (text !== null) node.textContent = String(text);
    if (type !== null) node.type = type;
    return node;
}

function icon(className) {
    const node = element("i", { className });
    node.setAttribute("aria-hidden", "true");
    return node;
}

function setButtonLabel(button, label, iconClass = null, iconAfter = false) {
    const text = document.createTextNode(String(label));
    button.replaceChildren();

    if (iconClass && !iconAfter) button.append(icon(iconClass), text);
    else if (iconClass) button.append(text, icon(iconClass));
    else button.append(text);
}

function appendPrompt(container, text, className = "text-neutral-600 mb-4") {
    const prompt = element("p", { className, text });
    container.appendChild(prompt);
    return prompt;
}

function renderMessage(message, className = "text-center text-red-500") {
    ui.gameContainer.replaceChildren(element("p", { className, text: message }));
}

function createOptionButton(value, className) {
    const button = element("button", { className, text: value, type: "button" });
    button.dataset.value = String(value);
    return button;
}

function submitOnEnter(input, handler) {
    input.addEventListener("keydown", event => {
        if (event.key !== "Enter" || event.isComposing) return;
        event.preventDefault();
        handler();
    });
}

function isContentValid(type, content) {
    if (!content || typeof content !== "object") return false;

    const str = value => typeof value === "string" && value.trim().length > 0;
    const arr = value => Array.isArray(value) && value.length > 0;

    switch (type) {
        case "spelling_quiz":
            return str(content.correct_answer) && str(content.prompt_text);
        case "matching_game":
            return arr(content.pairs) && content.pairs.every(pair => pair && str(pair.question) && str(pair.answer));
        case "fill_in_the_blank":
            return str(content.correct_answer)
                && ((Array.isArray(content.sentence_parts) && content.sentence_parts.length >= 2)
                    || str(content.sentence_template));
        case "listening_task":
            return str(content.correct_answer) && arr(content.options);
        case "speaking_practice":
        case "pronunciation_drill":
            return str(content.prompt_text);
        case "sentence_scramble":
            return str(content.sentence);
        case "translation_match":
            return str(content.question_word) && str(content.correct_answer) && arr(content.options);
        case "fill_with_options":
            return str(content.correct_answer)
                && Array.isArray(content.sentence_parts)
                && content.sentence_parts.length >= 2
                && arr(content.options);
        case "multiple_choice_quiz":
            return str(content.question_text) && str(content.correct_answer) && arr(content.options);
        case "fill_multiple_blanks":
            return arr(content.sentence_parts) && arr(content.correct_answers);
        case "silent_letter_hunt":
            return str(content.sentence)
                && arr(content.words)
                && content.words.every(word => word && str(word.word) && Number.isInteger(word.silent_letter_index));
        case "sound_sorting":
            return arr(content.categories) && arr(content.words);
        case "sequencing": {
            const steps = content.steps || content.sequence || content.order || [];
            return Array.isArray(steps) && steps.length >= 2;
        }
        default:
            return true;
    }
}

function renderCurrentExercise() {
    if (currentExerciseIndex < 0 || currentExerciseIndex >= allExercises.length) return;

    const exercise = allExercises[currentExerciseIndex];
    mediaController?.stop();
    ui.title.textContent = String(exercise.title || "");
    setMediaStatus();
    ui.feedbackFooter.className = "border-t-4 transition-colors duration-300 -mt-2 -mx-6 mb-4";
    ui.feedbackText.replaceChildren();
    setVisible(ui.checkButton, true);
    setButtonLabel(ui.checkButton, t("checkLabel", "Check"));
    setVisible(ui.nextButton, false);
    setVisible(ui.answerRetryButton, false);
    ui.answerRetryButton.onclick = null;
    setButtonLabel(
        ui.nextButton,
        currentExerciseIndex === allExercises.length - 1
            ? t("doneLabel", "Done")
            : t("nextLabel", "Next"),
        currentExerciseIndex === allExercises.length - 1 ? null : "fas fa-chevron-right ml-2",
        true,
    );
    setVisible(ui.footerContainer, true);
    selectedItems = { question: null, answer: null };

    const renderer = RENDERERS[exercise.type];
    if (!renderer) {
        renderMessage(t("notImplemented", "This exercise type ':type' has not been implemented yet.", {
            type: exercise.type,
        }));
        setVisible(ui.footerContainer, false);
    } else if (!isContentValid(exercise.type, exercise.content)) {
        console.error("Invalid exercise content for type:", exercise.type, exercise.content);
        renderMessage(t("contentError", "This exercise could not be loaded."));
        setVisible(ui.footerContainer, false);
    } else {
        try {
            renderer(exercise.content);
        } catch (error) {
            console.error("Failed to render exercise type:", exercise.type, error);
            renderMessage(t("contentError", "This exercise could not be loaded."));
            setVisible(ui.footerContainer, false);
        }
    }

    updateGlobalUI();
}

function updateGlobalUI() {
    const progress = ((currentExerciseIndex + 1) / allExercises.length) * 100;
    ui.progressBar.value = Math.round(progress);
    ui.progressBar.textContent = `${Math.round(progress)}%`;
    ui.itemCounter.textContent = `${currentExerciseIndex + 1} / ${allExercises.length}`;
    ui.prevButton.disabled = currentExerciseIndex === 0 || progressSavePending;
    ui.nextButton.disabled = progressSavePending;

    ui.sideNavItems.forEach((navItem, index) => {
        navItem.classList.toggle("active", index === currentExerciseIndex);
        if (index === currentExerciseIndex) navItem.setAttribute("aria-current", "step");
        else navItem.removeAttribute("aria-current");
        navItem.disabled = progressSavePending;

        if (index === currentExerciseIndex) {
            navItem.scrollIntoView({ behavior: "smooth", block: "nearest", inline: "center" });
        }
    });
}

function showFeedback(correct, message = "") {
    ui.feedbackFooter.classList.remove("feedback-correct", "feedback-incorrect");
    setVisible(ui.checkButton, false);
    setVisible(ui.nextButton, true);
    setVisible(ui.answerRetryButton, !correct);
    ui.answerRetryButton.onclick = correct ? null : () => {
        renderCurrentExercise();
        const primaryAnswerControl = ui.gameContainer.querySelector("input")
            || ui.gameContainer.querySelector("button");
        primaryAnswerControl?.focus();
    };

    if (correct) {
        ui.feedbackFooter.classList.add("feedback-correct");
        ui.feedbackText.textContent = t("correct", "Correct!");
    } else {
        ui.feedbackFooter.classList.add("feedback-incorrect");
        ui.feedbackText.textContent = message || t("tryAgain", "Try again!");
    }
}

function setMediaStatus(message = "", isError = false) {
    ui.mediaStatus.textContent = message;
    ui.mediaStatus.classList.toggle("text-red-700", isError);
    ui.mediaStatus.classList.toggle("text-neutral-600", !isError);
}

async function playExercisePrompt(button, promptText, audioUrl = null, disableButton = true) {
    if (!mediaController) {
        setMediaStatus(t("mediaUnavailable", "Audio playback is unavailable in this browser."), true);
        return;
    }

    if (disableButton) button.disabled = true;
    button.setAttribute("aria-busy", "true");
    setMediaStatus(t("playingAudio", "Playing audio..."));

    try {
        await mediaController.playPrompt({
            audioUrl,
            promptText,
            onFallback: () => setMediaStatus(t(
                "audioFallback",
                "The audio file was unavailable. Using browser speech instead.",
            )),
        });
        setMediaStatus(t("audioFinished", "Audio finished."));
    } catch (error) {
        if (!isPlaybackCancellation(error)) {
            console.error("Exercise media playback failed:", error);
            setMediaStatus(t(
                "mediaFailureContinue",
                "Unable to play this audio. You can still answer or continue.",
            ), true);
        }
    } finally {
        if (disableButton) button.disabled = false;
        button.removeAttribute("aria-busy");
    }
}

function renderSpellingQuiz(content) {
    const wrapper = element("div", { className: "text-center" });
    appendPrompt(wrapper, t("spellingPrompt", "Listen and type what you hear."));

    const audioButton = element("button", {
        className: "mb-6 text-indigo-600",
        type: "button",
    });
    audioButton.setAttribute("aria-label", t("playSpellingPrompt", "Play spelling prompt"));
    audioButton.setAttribute("aria-controls", "exercise-media-status");
    audioButton.appendChild(icon("fas fa-volume-up fa-3x"));

    const input = element("input", {
        className: "w-full p-4 text-center text-2xl border-2 rounded-lg",
        type: "text",
    });
    input.placeholder = t("spellingPlaceholder", "Type here...");
    input.setAttribute("aria-label", t("spellingAnswerLabel", "Spelling answer"));

    wrapper.append(audioButton, input);
    ui.gameContainer.replaceChildren(wrapper);

    audioButton.addEventListener("click", () => playExercisePrompt(
        audioButton,
        content.prompt_text,
        content.audio_url,
    ));

    ui.checkButton.onclick = () => {
        const isCorrect = input.value.trim().toLowerCase() === content.correct_answer.toLowerCase();
        showFeedback(isCorrect, `${t("correctAnswerLabel", "Correct answer:")} ${content.correct_answer}`);
    };
    submitOnEnter(input, () => ui.checkButton.click());
}

function renderMatchingGame(content) {
    setVisible(ui.checkButton, false);
    const questions = content.pairs.map(pair => pair.question).sort(() => 0.5 - Math.random());
    const answers = content.pairs.map(pair => pair.answer).sort(() => 0.5 - Math.random());
    const wrapper = element("div");
    appendPrompt(wrapper, t("matchingPrompt", "Match the corresponding items."), "text-center text-neutral-600 mb-6");
    const columns = element("div", { className: "flex justify-between gap-4" });
    const questionColumn = element("div", { className: "flex flex-col gap-3 w-1/2" });
    const answerColumn = element("div", { className: "flex flex-col gap-3 w-1/2" });

    for (const question of questions) {
        const button = createOptionButton(question, "match-item p-4 border rounded-lg");
        button.dataset.type = "question";
        button.addEventListener("click", selectMatchItem);
        questionColumn.appendChild(button);
    }

    for (const answer of answers) {
        const button = createOptionButton(answer, "match-item p-4 border rounded-lg");
        button.dataset.type = "answer";
        button.addEventListener("click", selectMatchItem);
        answerColumn.appendChild(button);
    }

    columns.append(questionColumn, answerColumn);
    wrapper.appendChild(columns);
    ui.gameContainer.replaceChildren(wrapper);
}

function renderFillInTheBlank(content) {
    let parts = content.sentence_parts;
    if (!Array.isArray(parts) || parts.length < 2) {
        const template = typeof content.sentence_template === "string" ? content.sentence_template : "";
        const blankIndex = template.indexOf("___");
        parts = blankIndex >= 0
            ? [template.slice(0, blankIndex), template.slice(blankIndex + 3)]
            : [template, ""];
    }

    const wrapper = element("div", { className: "text-center" });
    appendPrompt(wrapper, t("fillPrompt", "Fill in the blank."));
    const sentence = element("div", { className: "items-center justify-center text-2xl bg-white p-6 rounded-lg" });
    const input = element("input", {
        className: "w-32 mx-2 text-center border-b-2 focus:ring-0 focus:border-indigo-500",
        type: "text",
    });
    input.setAttribute("aria-label", t("blankAnswerLabel", "Answer for the blank"));
    sentence.append(element("span", { text: parts[0] }), input, element("span", { text: parts[1] }));
    wrapper.appendChild(sentence);
    ui.gameContainer.replaceChildren(wrapper);

    ui.checkButton.onclick = () => {
        const isCorrect = input.value.trim().toLowerCase() === content.correct_answer.toLowerCase();
        showFeedback(isCorrect, `${t("correctAnswerLabel", "Correct answer:")} ${content.correct_answer}`);
    };
    submitOnEnter(input, () => ui.checkButton.click());
}

function renderListeningTask(content) {
    setVisible(ui.checkButton, false);
    const wrapper = element("div", { className: "text-center" });
    appendPrompt(wrapper, content.instruction || t("listeningPrompt", "Listen and choose the correct answer."));

    const audioButton = element("button", { className: "mb-6 text-indigo-600", type: "button" });
    audioButton.setAttribute("aria-label", t("playListeningPrompt", "Play listening prompt"));
    audioButton.setAttribute("aria-controls", "exercise-media-status");
    audioButton.appendChild(icon("fas fa-volume-up fa-3x"));
    audioButton.addEventListener("click", () => playExercisePrompt(audioButton, content.correct_answer));

    const options = element("div", { className: "flex flex-col gap-3" });
    for (const option of content.options) {
        const button = createOptionButton(option, "option-button p-4 border rounded-lg text-lg");
        button.addEventListener("click", () => {
            const isCorrect = checkAnswer(button.dataset.value, content.correct_answer, true);
            options.querySelectorAll(".option-button").forEach(candidate => { candidate.disabled = true; });
            button.classList.add(isCorrect ? "correct" : "incorrect");
            showFeedback(isCorrect);
        });
        options.appendChild(button);
    }

    wrapper.append(audioButton, options);
    ui.gameContainer.replaceChildren(wrapper);
}

function renderSpeakingPractice(content) {
    renderPronunciationDrill(content);
}

function renderSentenceScramble(content) {
    const words = content.sentence.split(" ");
    const shuffledWords = [...words].sort(() => 0.5 - Math.random());
    const wrapper = element("div", { className: "text-center" });
    appendPrompt(wrapper, t("scramblePrompt", "Arrange the following into a correct sentence."));

    const answerArea = element("div", {
        className: "w-full min-h-[60px] bg-white rounded-lg p-3 border-b-4 flex flex-wrap gap-2 items-center",
    });
    answerArea.id = "answer-area";
    const wordBank = element("div", { className: "mt-8 flex flex-wrap gap-2 justify-center" });
    wordBank.id = "word-bank";

    shuffledWords.forEach((word, index) => {
        const button = createOptionButton(word, "word-bank-button p-2 px-4 bg-white border-2 rounded-lg text-lg");
        button.dataset.word = word;
        button.dataset.index = String(index);
        button.addEventListener("click", moveWordToAnswer);
        wordBank.appendChild(button);
    });

    wrapper.append(answerArea, wordBank);
    ui.gameContainer.replaceChildren(wrapper);
    ui.checkButton.onclick = () => checkSentenceScrambleAnswer(content.sentence);
}

function renderTranslationMatch(content) {
    setVisible(ui.checkButton, false);
    const wrapper = element("div", { className: "text-center" });
    appendPrompt(wrapper, t("translationPrompt", "Translate the following word:"), "text-neutral-600 mb-2");
    wrapper.appendChild(element("h2", {
        className: "text-4xl font-bold text-neutral-800 mb-8",
        text: content.question_word,
    }));

    const options = element("div", { className: "flex flex-col gap-3" });
    for (const option of content.options) {
        const button = createOptionButton(option, "option-button p-4 border-2 rounded-lg text-lg transition-colors");
        button.addEventListener("click", () => {
            const isCorrect = checkAnswer(button.dataset.value, content.correct_answer, true);
            options.querySelectorAll(".option-button").forEach(candidate => {
                candidate.disabled = true;
                if (candidate.dataset.value === content.correct_answer) candidate.classList.add("correct");
            });
            if (!isCorrect) button.classList.add("incorrect");
            showFeedback(isCorrect);
        });
        options.appendChild(button);
    }

    wrapper.appendChild(options);
    ui.gameContainer.replaceChildren(wrapper);
}

function renderFillWithOptions(content) {
    setVisible(ui.checkButton, false);
    const wrapper = element("div", { className: "text-center" });
    appendPrompt(wrapper, t("fillOptionsPrompt", "Choose the right word to complete the sentence."), "text-neutral-600 mb-6");
    const sentence = element("div", {
        className: "flex items-center justify-center text-2xl md:text-3xl bg-white p-6 rounded-lg mb-8",
    });
    sentence.append(
        element("span", { text: content.sentence_parts[0] }),
        element("span", { text: "_______" }),
        element("span", { text: content.sentence_parts[1] }),
    );

    const options = element("div", { className: "flex flex-wrap justify-center gap-3" });
    for (const option of content.options) {
        const button = createOptionButton(option, "option-button p-4 border-2 rounded-lg text-lg font-semibold");
        button.addEventListener("click", () => {
            const isCorrect = checkAnswer(button.dataset.value, content.correct_answer, true);
            options.querySelectorAll(".option-button").forEach(candidate => {
                candidate.disabled = true;
                if (candidate.dataset.value === content.correct_answer) candidate.classList.add("correct");
            });
            if (!isCorrect) button.classList.add("incorrect");
            showFeedback(isCorrect);
        });
        options.appendChild(button);
    }

    wrapper.append(sentence, options);
    ui.gameContainer.replaceChildren(wrapper);
}

function renderMultipleChoiceQuiz(content) {
    setVisible(ui.checkButton, false);
    const wrapper = element("div", { className: "text-center" });
    appendPrompt(wrapper, content.question_text, "text-neutral-600 mb-6 text-xl");
    const options = element("div", { className: "flex flex-col gap-3" });

    for (const option of content.options) {
        const button = createOptionButton(option, "option-button p-4 border-2 rounded-lg text-lg font-semibold");
        button.addEventListener("click", () => {
            const isCorrect = checkAnswer(button.dataset.value, content.correct_answer, true);
            options.querySelectorAll(".option-button").forEach(candidate => {
                candidate.disabled = true;
                if (candidate.dataset.value.toLowerCase() === content.correct_answer.toLowerCase()) {
                    candidate.classList.add("correct");
                }
            });
            if (!isCorrect) button.classList.add("incorrect");
            showFeedback(isCorrect);
        });
        options.appendChild(button);
    }

    wrapper.appendChild(options);
    ui.gameContainer.replaceChildren(wrapper);
}

function renderFillMultipleBlanks(content) {
    const wrapper = element("div", { className: "text-center" });
    appendPrompt(wrapper, t("fillMultiplePrompt", "Complete the following sentence."));
    const sentence = element("div", { className: "text-xl md:text-2xl bg-white p-6 rounded-lg leading-loose" });

    content.sentence_parts.forEach((part, index) => {
        sentence.appendChild(element("span", { text: part }));
        if (index < content.correct_answers.length) {
            sentence.appendChild(element("input", {
                className: "fill-input w-32 mx-2 text-center border-b-2 focus:ring-0 focus:border-indigo-500 bg-transparent",
                type: "text",
            }));
            const input = sentence.lastElementChild;
            input.setAttribute("aria-label", t("blankNumberLabel", "Answer for blank :number", { number: index + 1 }));
            submitOnEnter(input, () => ui.checkButton.click());
        }
    });

    wrapper.appendChild(sentence);
    ui.gameContainer.replaceChildren(wrapper);
    ui.checkButton.onclick = () => checkFillMultipleBlanksAnswer(content.correct_answers);
}

function renderSilentLetterHunt(content) {
    setVisible(ui.checkButton, false);
    const targetWords = new Map(content.words.map(word => [word.word, word.silent_letter_index]));
    const wrapper = element("div", { className: "text-center" });
    appendPrompt(wrapper, t("silentLetterPrompt", "Click on a word to find the silent letter."));
    const sentence = element("div", { className: "text-3xl bg-white p-6 rounded-lg leading-relaxed" });

    content.sentence.split(" ").forEach((word, index, words) => {
        const cleanWord = word.replace(/[.,]/g, "");
        if (targetWords.has(cleanWord)) {
            const button = element("button", { className: "word-button px-2 py-1 rounded-md", text: word, type: "button" });
            button.dataset.word = cleanWord;
            button.dataset.index = String(targetWords.get(cleanWord));
            button.addEventListener("click", event => revealSilentLetter(event.currentTarget));
            sentence.appendChild(button);
        } else {
            sentence.appendChild(element("span", { text: word }));
        }
        if (index < words.length - 1) sentence.appendChild(document.createTextNode(" "));
    });

    wrapper.appendChild(sentence);
    ui.gameContainer.replaceChildren(wrapper);
}

function renderPronunciationDrill(content) {
    setVisible(ui.checkButton, false);
    setVisible(ui.nextButton, true);
    const wrapper = element("div", { className: "text-center" });
    appendPrompt(wrapper, t(
        "pronunciationPrompt",
        "Tap the button to hear an example pronunciation, then say it aloud. Tap again to repeat.",
    ));
    wrapper.appendChild(element("p", {
        className: "text-2xl font-semibold bg-white p-6 rounded-lg mb-6",
        text: content.prompt_text,
    }));

    const listenButton = element("button", {
        className: "w-20 h-20 bg-indigo-600 text-white rounded-full inline-flex items-center justify-center shadow-lg hover:bg-indigo-700 transition-transform transform hover:scale-105",
        type: "button",
    });
    listenButton.setAttribute("aria-label", t("playPronunciationPrompt", "Play pronunciation example"));
    listenButton.setAttribute("aria-controls", "exercise-media-status");
    listenButton.appendChild(icon("fas fa-volume-up fa-2x"));
    listenButton.addEventListener("click", () => playExercisePrompt(listenButton, content.prompt_text));
    wrapper.appendChild(listenButton);
    appendPrompt(wrapper, t("speakingHint", 'Listen, imitate, then press "Next".'), "mt-4 text-neutral-500 text-sm italic");
    ui.gameContainer.replaceChildren(wrapper);
}

function renderSoundSorting(content) {
    setVisible(ui.checkButton, false);
    const categories = content.categories || [];
    const shuffledWords = (content.words || []).slice().sort(() => 0.5 - Math.random());
    const wrapper = element("div", { className: "text-center" });
    appendPrompt(wrapper, t(
        "soundSortingPrompt",
        "Sort each word into the correct sound group. Tap a word, then tap a group. Tap a word to hear it.",
    ));

    const pool = element("div", {
        className: "flex flex-wrap gap-2 justify-center bg-white p-4 rounded-lg mb-6 min-h-[60px]",
    });
    pool.id = "sort-pool";
    for (const word of shuffledWords) {
        const token = element("button", {
            className: "sort-token p-2 px-4 border-2 border-neutral-300 rounded-lg text-lg bg-white",
            type: "button",
        });
        token.dataset.word = String(word.word);
        token.dataset.correctCategory = String(word.category_id);
        token.setAttribute("aria-label", t("selectSoundWord", "Select and listen to :word", { word: word.word }));
        token.setAttribute("aria-controls", "exercise-media-status");
        token.append(document.createTextNode(String(word.word)), icon("fas fa-volume-up ml-1 text-indigo-500"));
        pool.appendChild(token);
    }

    const buckets = element("div", { className: "grid grid-cols-2 gap-4" });
    for (const category of categories) {
        const bucket = element("div", {
            className: "sort-bucket border-2 border-dashed border-neutral-300 rounded-lg p-3",
        });
        bucket.dataset.category = String(category.id);
        const target = element("button", {
            className: "sort-bucket-target w-full rounded-md p-2 font-semibold text-neutral-700 hover:bg-neutral-100 focus:outline-none focus:ring-2 focus:ring-indigo-500",
            text: category.name,
            type: "button",
        });
        target.setAttribute("aria-label", t("placeInSoundGroup", "Place selected word in :group", { group: category.name }));
        bucket.appendChild(target);
        bucket.appendChild(element("div", {
            className: "bucket-items flex flex-wrap gap-2 justify-center min-h-[40px]",
        }));
        buckets.appendChild(bucket);
    }

    wrapper.append(pool, buckets);
    ui.gameContainer.replaceChildren(wrapper);

    let selected = null;
    ui.gameContainer.querySelectorAll(".sort-token").forEach(token => {
        token.addEventListener("click", event => {
            event.stopPropagation();
            playExercisePrompt(token, token.dataset.word, null, false);
            ui.gameContainer.querySelectorAll(".sort-token").forEach(candidate => {
                candidate.classList.remove("bg-indigo-100", "border-indigo-600");
                candidate.classList.add("bg-white", "border-neutral-300");
            });
            token.classList.remove("bg-white", "border-neutral-300");
            token.classList.add("bg-indigo-100", "border-indigo-600");
            selected = token;
        });
    });

    ui.gameContainer.querySelectorAll(".sort-bucket-target").forEach(target => {
        target.addEventListener("click", () => {
            if (!selected) return;
            const bucket = target.closest(".sort-bucket");
            bucket.querySelector(".bucket-items").appendChild(selected);
            selected.classList.remove("bg-indigo-100", "border-indigo-600");
            selected.classList.add("bg-white", "border-neutral-300");
            selected.dataset.bucket = bucket.dataset.category;
            selected = null;
            if (!ui.gameContainer.querySelector("#sort-pool .sort-token")) setVisible(ui.checkButton, true);
        });
    });

    ui.checkButton.onclick = () => {
        let allCorrect = true;
        ui.gameContainer.querySelectorAll(".sort-token").forEach(token => {
            const correct = token.dataset.correctCategory === token.dataset.bucket;
            token.classList.remove("border-neutral-300", "border-green-500", "border-red-500", "bg-white", "bg-green-100", "bg-red-100");
            token.classList.add(correct ? "border-green-500" : "border-red-500");
            token.classList.add(correct ? "bg-green-100" : "bg-red-100");
            if (!correct) allCorrect = false;
        });
        showFeedback(allCorrect, t(
            "soundSortingRetry",
            "Some words are still in the wrong group. Move the red ones.",
        ));
    };
}

function renderSequencing(content) {
    const correctOrder = (content.steps || content.sequence || content.order || [])
        .map(step => typeof step === "object" && step !== null ? (step.text || step.step) : step)
        .filter(Boolean)
        .map(String);
    let current = correctOrder.slice().sort(() => 0.5 - Math.random());
    let attempts = 0;
    const sameOrder = () => current.every((step, index) => step === correctOrder[index]);
    while (sameOrder() && correctOrder.length > 1 && attempts++ < 10) {
        current = correctOrder.slice().sort(() => 0.5 - Math.random());
    }
    let done = false;

    function paint() {
        const wrapper = element("div", { className: "text-center" });
        appendPrompt(wrapper, t("sequencingPrompt", "Arrange the following steps in the correct order."), "text-neutral-600 mb-2");
        appendPrompt(wrapper, t("sequencingHint", "Use the up/down buttons to reorder, then press Check."), "text-sm text-neutral-400 mb-4");
        const list = element("div", { className: "flex flex-col gap-2" });

        current.forEach((step, index) => {
            const row = element("div", { className: "seq-item flex items-center gap-3 bg-white p-3 rounded-lg border-2 border-neutral-200" });
            row.appendChild(element("span", {
                className: "w-7 h-7 flex items-center justify-center bg-indigo-100 text-indigo-700 rounded-full font-semibold shrink-0",
                text: index + 1,
            }));
            row.appendChild(element("span", { className: "flex-1 text-left", text: step }));

            const controls = element("div", { className: "flex flex-col gap-1" });
            const up = element("button", {
                className: "seq-up px-2 rounded bg-neutral-100 hover:bg-neutral-200 disabled:opacity-30",
                type: "button",
            });
            up.setAttribute("aria-label", t("moveStepUp", "Move step :number up", { number: index + 1 }));
            up.dataset.index = String(index);
            up.disabled = index === 0;
            up.appendChild(icon("fas fa-chevron-up"));
            const down = element("button", {
                className: "seq-down px-2 rounded bg-neutral-100 hover:bg-neutral-200 disabled:opacity-30",
                type: "button",
            });
            down.setAttribute("aria-label", t("moveStepDown", "Move step :number down", { number: index + 1 }));
            down.dataset.index = String(index);
            down.disabled = index === current.length - 1;
            down.appendChild(icon("fas fa-chevron-down"));
            controls.append(up, down);
            row.appendChild(controls);
            list.appendChild(row);
        });

        wrapper.appendChild(list);
        ui.gameContainer.replaceChildren(wrapper);
        ui.gameContainer.querySelectorAll(".seq-up").forEach(button => button.addEventListener("click", event => {
            if (done) return;
            const index = Number.parseInt(event.currentTarget.dataset.index, 10);
            [current[index - 1], current[index]] = [current[index], current[index - 1]];
            paint();
        }));
        ui.gameContainer.querySelectorAll(".seq-down").forEach(button => button.addEventListener("click", event => {
            if (done) return;
            const index = Number.parseInt(event.currentTarget.dataset.index, 10);
            [current[index + 1], current[index]] = [current[index], current[index + 1]];
            paint();
        }));
    }

    paint();
    ui.checkButton.onclick = () => {
        if (done) return;
        let allCorrect = true;
        const rows = ui.gameContainer.querySelectorAll(".seq-item");
        current.forEach((step, index) => {
            const correct = step === correctOrder[index];
            if (rows[index]) {
                rows[index].classList.remove("border-neutral-200", "border-green-500", "border-red-500", "bg-white", "bg-green-100", "bg-red-100");
                rows[index].classList.add(correct ? "border-green-500" : "border-red-500");
                rows[index].classList.add(correct ? "bg-green-100" : "bg-red-100");
            }
            if (!correct) allCorrect = false;
        });
        showFeedback(allCorrect, t("sequencingRetry", "Not quite. Adjust the rows marked in red."));
        if (allCorrect) done = true;
    };
}

function revealSilentLetter(button) {
    if (button.classList.contains("revealed")) return;

    const word = button.dataset.word;
    const silentIndex = Number.parseInt(button.dataset.index, 10);
    const letters = Array.from(word, (character, index) => element("span", {
        className: index === silentIndex ? "highlighted-letter" : "",
        text: character,
    }));
    button.replaceChildren(...letters);
    button.classList.add("revealed");
    button.disabled = true;

    const allButtons = ui.gameContainer.querySelectorAll(".word-button");
    const revealedButtons = ui.gameContainer.querySelectorAll(".word-button.revealed");
    if (allButtons.length === revealedButtons.length) showFeedback(true);
}

function checkFillMultipleBlanksAnswer(correctAnswers) {
    const inputs = ui.gameContainer.querySelectorAll(".fill-input");
    let allCorrect = true;

    inputs.forEach((input, index) => {
        const correct = input.value.trim().toLowerCase() === String(correctAnswers[index] || "").toLowerCase();
        input.classList.toggle("border-red-500", !correct);
        input.classList.toggle("border-green-500", correct);
        if (!correct) allCorrect = false;
    });

    showFeedback(allCorrect, allCorrect ? "" : t("checkAgain", "Please check your answers again."));
}

function checkAnswer(userInput, correctAnswer, noFeedbackMessage = false) {
    const isCorrect = String(userInput).toLowerCase() === String(correctAnswer).toLowerCase();
    if (!noFeedbackMessage) {
        showFeedback(isCorrect, `${t("correctAnswerLabel", "Correct answer:")} ${correctAnswer}`);
    }
    return isCorrect;
}

function moveWordToAnswer(event) {
    const targetButton = event.currentTarget;
    const answerArea = ui.gameContainer.querySelector("#answer-area");
    const newButton = element("button", {
        className: "answer-area-button p-2 px-4 bg-indigo-100 border-2 border-indigo-300 rounded-lg text-lg",
        text: targetButton.dataset.word,
        type: "button",
    });
    newButton.dataset.originalIndex = targetButton.dataset.index;
    newButton.addEventListener("click", moveWordToBank);
    answerArea.appendChild(newButton);
    targetButton.disabled = true;
}

function moveWordToBank(event) {
    const targetButton = event.currentTarget;
    const originalIndex = targetButton.dataset.originalIndex;
    const wordBankButton = Array.from(ui.gameContainer.querySelectorAll(".word-bank-button"))
        .find(button => button.dataset.index === originalIndex);
    if (wordBankButton) wordBankButton.disabled = false;
    targetButton.remove();
}

function checkSentenceScrambleAnswer(correctSentence) {
    const answerArea = ui.gameContainer.querySelector("#answer-area");
    const userAnswer = Array.from(answerArea.children).map(button => button.textContent).join(" ");
    const isCorrect = userAnswer.trim() === correctSentence.trim();
    showFeedback(isCorrect, `${t("correctAnswerLabel", "Correct answer:")} ${correctSentence}`);
}

function selectMatchItem(event) {
    const target = event.currentTarget;
    const type = target.dataset.type;
    selectedItems[type] = target;
    ui.gameContainer.querySelectorAll(`.match-item[data-type="${type}"]`)
        .forEach(button => button.classList.remove("selected"));
    target.classList.add("selected");
    if (selectedItems.question && selectedItems.answer) checkMatchingAnswer();
}

function checkMatchingAnswer() {
    const question = selectedItems.question;
    const answer = selectedItems.answer;
    const currentExercise = allExercises[currentExerciseIndex];
    const isCorrect = currentExercise.content.pairs.some(pair => (
        pair.question === question.dataset.value && pair.answer === answer.dataset.value
    ));

    if (isCorrect) {
        question.classList.add("correct");
        answer.classList.add("correct");
        question.disabled = true;
        answer.disabled = true;
    } else {
        question.classList.add("incorrect");
        answer.classList.add("incorrect");
        ui.feedbackFooter.classList.add("feedback-incorrect");
        ui.feedbackText.textContent = t("tryAgain", "Try again!");
        window.setTimeout(() => {
            question.classList.remove("incorrect");
            answer.classList.remove("incorrect");
            ui.feedbackFooter.classList.remove("feedback-incorrect");
            ui.feedbackText.replaceChildren();
        }, 1000);
    }

    window.setTimeout(() => {
        question.classList.remove("selected");
        answer.classList.remove("selected");
        selectedItems = { question: null, answer: null };
    }, isCorrect ? 100 : 1000);

    if (ui.gameContainer.querySelectorAll(".match-item.correct").length === currentExercise.content.pairs.length * 2) {
        showFeedback(true);
    }
}

function showProgressError() {
    ui.progressErrorMessage.textContent = t(
        "progressSaveError",
        "Unable to save your progress. Check your connection and try again.",
    );
    ui.progressError.classList.remove("hidden");
    ui.progressError.focus();
}

async function saveCurrentExercise(action) {
    if (progressSavePending) return;

    retryProgressAction = action;
    progressSavePending = true;
    ui.progressError.classList.add("hidden");
    updateGlobalUI();
    setButtonLabel(ui.nextButton, t("savingProgress", "Saving progress..."));

    try {
        await saveProgress({
            url: progressUrl,
            items: [action.itemId],
            type: "Exercise",
        });

        if (action.returnAfterSave) {
            window.location.assign(returnUrl);
            return;
        }

        progressSavePending = false;
        retryProgressAction = null;
        currentExerciseIndex = action.nextIndex;
        renderCurrentExercise();
    } catch (error) {
        console.error("Failed to save exercise progress:", error);
        progressSavePending = false;
        updateGlobalUI();
        setButtonLabel(
            ui.nextButton,
            currentExerciseIndex === allExercises.length - 1
                ? t("doneLabel", "Done")
                : t("nextLabel", "Next"),
            currentExerciseIndex === allExercises.length - 1 ? null : "fas fa-chevron-right ml-2",
            true,
        );
        showProgressError();
    }
}

const RENDERERS = {
    spelling_quiz: renderSpellingQuiz,
    matching_game: renderMatchingGame,
    fill_in_the_blank: renderFillInTheBlank,
    listening_task: renderListeningTask,
    speaking_practice: renderSpeakingPractice,
    sentence_scramble: renderSentenceScramble,
    translation_match: renderTranslationMatch,
    fill_with_options: renderFillWithOptions,
    multiple_choice_quiz: renderMultipleChoiceQuiz,
    fill_multiple_blanks: renderFillMultipleBlanks,
    silent_letter_hunt: renderSilentLetterHunt,
    pronunciation_drill: renderPronunciationDrill,
    sound_sorting: renderSoundSorting,
    sequencing: renderSequencing,
};

function initExercises() {
    const dataElement = document.getElementById("exercises-data");
    if (!dataElement) return;

    try {
        allExercises = JSON.parse(dataElement.dataset.exercises || "[]");
        I18N = JSON.parse(dataElement.dataset.i18n || "{}");
    } catch (error) {
        console.error("Exercise page data is malformed:", error);
        allExercises = [];
        I18N = {};
    }

    returnUrl = dataElement.dataset.returnUrl || "";
    progressUrl = dataElement.dataset.progressUrl || "";
    currentExerciseIndex = 0;
    progressSavePending = false;
    retryProgressAction = null;
    mediaController = createMediaController();

    ui.title = document.getElementById("exercise-title");
    ui.gameContainer = document.getElementById("game-container");
    ui.checkButton = document.getElementById("check-button");
    ui.nextButton = document.getElementById("next-button");
    ui.prevButton = document.getElementById("prev-button");
    ui.feedbackFooter = document.getElementById("feedback-footer");
    ui.feedbackText = document.getElementById("feedback-text");
    ui.answerRetryButton = document.getElementById("answer-retry-button");
    ui.mediaStatus = document.getElementById("exercise-media-status");
    ui.progressBar = document.getElementById("progress-bar");
    ui.itemCounter = document.getElementById("item-counter");
    ui.sideNavItems = document.querySelectorAll(".side-nav-item");
    ui.footerContainer = document.getElementById("footer-container");
    ui.progressError = document.getElementById("progress-save-error");
    ui.progressErrorMessage = document.getElementById("progress-save-error-message");
    ui.progressRetryButton = document.getElementById("progress-retry-button");

    ui.nextButton.addEventListener("click", () => {
        if (progressSavePending) return;
        saveCurrentExercise({
            itemId: allExercises[currentExerciseIndex].id,
            nextIndex: currentExerciseIndex < allExercises.length - 1 ? currentExerciseIndex + 1 : null,
            returnAfterSave: currentExerciseIndex === allExercises.length - 1,
        });
    });
    ui.progressRetryButton.addEventListener("click", () => {
        if (retryProgressAction) saveCurrentExercise(retryProgressAction);
    });

    ui.prevButton.addEventListener("click", () => {
        if (!progressSavePending && currentExerciseIndex > 0) {
            currentExerciseIndex--;
            renderCurrentExercise();
        }
    });

    ui.sideNavItems.forEach(navItem => {
        navItem.addEventListener("click", event => {
            if (progressSavePending) return;
            currentExerciseIndex = Number.parseInt(event.currentTarget.dataset.index, 10);
            renderCurrentExercise();
        });
    });

    if (allExercises.length > 0) {
        renderCurrentExercise();
    } else {
        renderMessage(t("noExercises", "No exercises for this lesson yet."), "text-neutral-500 text-xl");
        setVisible(ui.footerContainer, false);
    }
}

export { RENDERERS, initExercises };

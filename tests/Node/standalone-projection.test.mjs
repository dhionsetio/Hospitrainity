import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { fileURLToPath } from 'node:url';
import { JSDOM, VirtualConsole } from 'jsdom';

const standalonePath = fileURLToPath(new URL('../../standalone/Hospitrainity-Standalone.html', import.meta.url));
const html = readFileSync(standalonePath, 'utf8');

function installDom(hash = '#/') {
    const runtimeErrors = [];
    const virtualConsole = new VirtualConsole();
    virtualConsole.on('jsdomError', error => runtimeErrors.push(error));
    const dom = new JSDOM(html, {
        beforeParse(window) {
            window.scrollTo = () => {};
        },
        runScripts: 'dangerously',
        url: `https://hospitrainity.test/${hash}`,
        virtualConsole,
    });

    return { dom, runtimeErrors };
}

function navigate(dom, hash) {
    dom.window.location.hash = hash;
    dom.window.dispatchEvent(new dom.window.HashChangeEvent('hashchange'));
}

function projection(dom) {
    return JSON.parse(dom.window.document.getElementById('hsp-data').textContent);
}

function activityByCode(data, code) {
    return data.chapters.flatMap(chapter => chapter.activities).find(activity => activity.code === code);
}

function fillValidResponses(dom, activity) {
    const form = dom.window.document.querySelector('[data-session-attempt-form]');
    for (const prompt of activity.prompts) {
        const card = form.querySelector(`[data-prompt-code="${prompt.code}"]`);
        if (prompt.response_form === 'selection') {
            const input = card.querySelector(`input[value="${prompt.correct_choice_ids[0]}"]`);
            input.checked = true;
        } else if (prompt.response_form === 'rating') {
            card.querySelector('input[value="3"]').checked = true;
        } else if (prompt.response_form === 'ordering') {
            [...card.querySelectorAll('select')].forEach((select, index) => {
                select.value = prompt.correct_order[index];
            });
        } else {
            const input = card.querySelector('input[type="text"], textarea');
            input.value = prompt.scoring_mode === 'objective_normalized_closed'
                ? prompt.accepted_normalized[0]
                : 'A private rehearsal response for this page session.';
            const selfCheck = card.querySelector('input[type="checkbox"]');
            if (selfCheck) selfCheck.checked = true;
        }
    }

    return form;
}

function submit(form, intent) {
    const button = form.querySelector(`button[value="${intent}"]`);
    form.requestSubmit(button);
}

test('standalone projection covers every canonical response form and closed scoring model', () => {
    const { dom, runtimeErrors } = installDom();
    const data = projection(dom);
    const prompts = data.chapters.flatMap(chapter => chapter.activities.flatMap(activity => activity.prompts));
    const counts = Object.fromEntries(
        Object.entries(Object.groupBy(prompts, prompt => prompt.response_form))
            .map(([form, members]) => [form, members.length]),
    );

    assert.deepEqual(counts, {
        ordering: 5,
        rating: 28,
        role_play: 4,
        selection: 74,
        service_artifact: 2,
        short_text: 11,
    });
    assert.equal(prompts.length, 124);
    assert.equal(
        prompts.filter(prompt => prompt.scoring_mode === 'objective_normalized_closed')
            .every(prompt => prompt.accepted_normalized.length > 0),
        true,
    );
    assert.deepEqual(runtimeErrors, []);
    dom.window.close();
});

test('standalone selection requires responses, identifies errors, and scores locally', () => {
    const { dom, runtimeErrors } = installDom('#/activity/HSP-C02-ACT-QUIZ');
    const data = projection(dom);
    const activity = activityByCode(data, 'HSP-C02-ACT-QUIZ');
    const form = dom.window.document.querySelector('[data-session-attempt-form]');

    assert.equal(form.querySelectorAll('fieldset').length, 8);
    assert.equal(form.querySelectorAll('input[type="radio"]').length, 24);
    submit(form, 'check');

    const summary = dom.window.document.querySelector('.error-summary');
    assert.equal(summary.querySelectorAll('li').length, 8);
    assert.equal(dom.window.document.activeElement, summary);

    submit(fillValidResponses(dom, activity), 'check');
    assert.match(dom.window.document.getElementById('attempt-result').textContent, /completed/i);
    assert.equal(dom.window.document.querySelectorAll('.prompt-result .correct').length, 8);
    assert.equal(dom.window.document.activeElement.id, 'attempt-result');
    assert.deepEqual(runtimeErrors, []);
    dom.window.close();
});

test('standalone ordering has a non-drag alternative and short-text policies', () => {
    const { dom, runtimeErrors } = installDom('#/activity/HSP-C02-ACT-PRACTICE');
    const data = projection(dom);
    const activity = activityByCode(data, 'HSP-C02-ACT-PRACTICE');
    const form = dom.window.document.querySelector('[data-session-attempt-form]');
    const ordering = form.querySelector('[data-ordering-list]');

    assert.equal(ordering.querySelectorAll('select').length, 4);
    assert.equal(ordering.querySelectorAll('[draggable="true"]').length, 0);
    assert.equal(form.querySelectorAll('input[type="text"]').length, 1);
    assert.equal(form.querySelectorAll('textarea').length, 1);

    const moveDown = ordering.querySelector('[data-order-down]');
    moveDown.click();
    assert.match(ordering.parentElement.querySelector('[aria-live="polite"]').textContent, /position 2/i);
    assert.equal(dom.window.document.activeElement, moveDown);

    submit(fillValidResponses(dom, activity), 'check');
    assert.match(dom.window.document.getElementById('attempt-result').textContent, /completed/i);
    assert.equal(dom.window.document.querySelectorAll('.prompt-result .correct').length, 3);
    assert.deepEqual(runtimeErrors, []);
    dom.window.close();
});

test('standalone role-play and service artifacts require rehearsal self-checks', () => {
    for (const code of ['HSP-C02-ACT-ROLEPLAY', 'HSP-C05-ACT-ROLEPLAY']) {
        const { dom, runtimeErrors } = installDom(`#/activity/${code}`);
        const data = projection(dom);
        const activity = activityByCode(data, code);
        const form = dom.window.document.querySelector('[data-session-attempt-form]');
        const openPrompt = activity.prompts.find(prompt => ['role_play', 'service_artifact'].includes(prompt.response_form));
        const openCard = form.querySelector(`[data-prompt-code="${openPrompt.code}"]`);

        assert.ok(openCard.querySelector('textarea'));
        assert.ok(openCard.querySelector('input[type="checkbox"]'));
        submit(form, 'check');
        assert.match(dom.window.document.querySelector('.error-summary').textContent, /self-check/i);

        submit(fillValidResponses(dom, activity), 'check');
        assert.match(dom.window.document.getElementById('attempt-result').textContent, /completed/i);
        assert.match(
            dom.window.document.querySelector(`[data-prompt-code="${openPrompt.code}"]`).textContent,
            /not automatically graded/i,
        );
        assert.deepEqual(runtimeErrors, []);
        dom.window.close();
    }
});

test('standalone baseline skip and confidence history are explicit and session-only', () => {
    const { dom, runtimeErrors } = installDom('#/activity/HSP-C01-ACT-BASELINE');
    const baselineForm = dom.window.document.querySelector('[data-session-attempt-form]');
    submit(baselineForm, 'skip_baseline');
    assert.match(dom.window.document.getElementById('attempt-result').textContent, /explicitly skipped/i);

    navigate(dom, '#/activity/HSP-C02-ACT-CONFIDENCE');
    const data = projection(dom);
    const confidence = activityByCode(data, 'HSP-C02-ACT-CONFIDENCE');
    submit(fillValidResponses(dom, confidence), 'check');
    navigate(dom, '#/confidence');

    const history = dom.window.document.querySelector('main').textContent;
    assert.match(history, /self-reflection only/i);
    assert.match(history, /not proficiency, test, or CEFR evidence/i);
    assert.match(history, /rating 3 of 5/i);
    assert.equal(dom.window.localStorage.length, 0);
    assert.equal(dom.window.sessionStorage.length, 0);
    assert.deepEqual(runtimeErrors, []);
    dom.window.close();

    const fresh = installDom('#/confidence');
    assert.match(fresh.dom.window.document.querySelector('main').textContent, /No completed baseline or confidence check/i);
    fresh.dom.window.close();
});

test('standalone model reveal is explicit and never marks completion', () => {
    const { dom, runtimeErrors } = installDom('#/activity/HSP-C03-ACT-QUIZ');
    submit(dom.window.document.querySelector('[data-session-attempt-form]'), 'show_model');

    assert.match(dom.window.document.getElementById('attempt-result').textContent, /without completing/i);
    navigate(dom, '#/chapter/HSP-C03');
    const chapter = dom.window.document.querySelector('main').textContent;
    assert.match(chapter, /Model reviewed this session/i);
    assert.doesNotMatch(chapter, /Completed this session/i);
    assert.deepEqual(runtimeErrors, []);
    dom.window.close();
});

test('standalone keeps build details out of the learner interface', () => {
    const { dom, runtimeErrors } = installDom('#/about');
    const about = dom.window.document.querySelector('main').textContent;
    assert.match(about, /open page/i);
    assert.match(about, /does not synchronize/i);
    assert.doesNotMatch(about, /CF-7|checksum|content version|lifecycle|source of truth/i);
    assert.doesNotMatch(html, /closed CP-02 approval gate/i);
    assert.doesNotMatch(html, /phase-09\/curriculum\.sql/i);
    assert.doesNotMatch(html, /24 activities \/ 102 prompts/i);

    navigate(dom, '#/chapter/HSP-C02');
    const chapter = dom.window.document.querySelector('main').textContent;
    assert.match(chapter, /Front Desk and Check-In/);
    assert.doesNotMatch(chapter, /CF-7|lifecycle|release approval|HSP-C02|CEFR activity/i);
    assert.deepEqual(runtimeErrors, []);
    dom.window.close();
});

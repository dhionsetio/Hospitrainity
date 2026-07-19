import assert from 'node:assert/strict';
import test from 'node:test';
import { JSDOM } from 'jsdom';

import { initializeCanonicalActivity } from '../../resources/js/canonical-activity.js';

function installDom(body, url = 'https://hospitrainity.test/activity') {
    const dom = new JSDOM(`<!doctype html><body>${body}</body>`, { url });
    globalThis.window = dom.window;
    globalThis.document = dom.window.document;
    globalThis.HTMLElement = dom.window.HTMLElement;
    globalThis.HTMLButtonElement = dom.window.HTMLButtonElement;
    globalThis.HTMLLIElement = dom.window.HTMLLIElement;
    globalThis.HTMLOListElement = dom.window.HTMLOListElement;
    globalThis.Element = dom.window.Element;

    return dom;
}

test('ordering controls move items by keyboard-accessible buttons and keep position state accurate', () => {
    installDom(`
        <div><p data-ordering-status aria-live="polite"></p><ol data-ordering-list data-ordering-label="Position">
            <li data-ordering-item data-id="first"><span data-ordering-position></span><button type="button" data-order-up>Up</button><button type="button" data-order-down>Down</button></li>
            <li data-ordering-item data-id="second"><span data-ordering-position></span><button type="button" data-order-up>Up</button><button type="button" data-order-down>Down</button></li>
        </ol></div>
    `);
    initializeCanonicalActivity();

    const list = document.querySelector('[data-ordering-list]');
    const firstDown = list.querySelector('[data-id="first"] [data-order-down]');
    assert.equal(list.querySelector('[data-id="first"] [data-order-up]').disabled, true);
    assert.equal(list.querySelector('[data-id="second"] [data-order-down]').disabled, true);

    firstDown.click();

    assert.deepEqual([...list.children].map((item) => item.dataset.id), ['second', 'first']);
    assert.deepEqual([...list.querySelectorAll('[data-ordering-position]')].map((label) => label.textContent), ['Position 1', 'Position 2']);
    assert.equal(document.activeElement, list.querySelector('[data-id="first"] [data-order-up]'));
    assert.equal(firstDown.disabled, true);
    assert.equal(document.querySelector('[data-ordering-status]').textContent, 'Item moved to position 2.');
    assert.equal(list.querySelector('[data-id="first"] [data-order-up]').getAttribute('aria-label'), 'Move item at position 2 up');
});

test('validation errors and posted results receive focus at the correct time', () => {
    installDom(`
        <div data-error-summary tabindex="-1"><a href="#prompt-one" data-error-link>Fix prompt one</a></div>
        <article id="prompt-one" tabindex="-1">
            <textarea></textarea>
            <input type="checkbox" aria-invalid="true">
        </article>
        <section id="attempt-result" tabindex="-1"></section>
    `);
    initializeCanonicalActivity();
    assert.equal(document.activeElement.matches('[data-error-summary]'), true);
    document.querySelector('[data-error-link]').click();
    assert.equal(document.activeElement.matches('#prompt-one input[type="checkbox"]'), true);

    installDom('<section id="attempt-result" tabindex="-1"></section>', 'https://hospitrainity.test/activity#attempt-result');
    initializeCanonicalActivity();
    assert.equal(document.activeElement.id, 'attempt-result');
});

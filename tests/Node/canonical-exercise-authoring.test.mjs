import assert from 'node:assert/strict';
import test from 'node:test';
import { JSDOM } from 'jsdom';

import { initializeExerciseAuthoring } from '../../resources/js/canonical-exercise-authoring.js';

function installDom() {
    const dom = new JSDOM(`<!doctype html><body>
        <form data-exercise-authoring data-min-items="1" data-max-items="3">
            <button type="button" data-add-exercise-item>Add item</button>
            <p data-exercise-authoring-status aria-live="polite"></p>
            <ol data-exercise-items>
                <li data-exercise-item data-id="first">
                    <span data-exercise-item-number></span><span class="font-mono">I01</span>
                    <input name="items[0][code]" value="I01"><textarea name="items[0][stem]">First</textarea>
                    <button type="button" data-item-up>Up</button><button type="button" data-item-down>Down</button><button type="button" data-remove-exercise-item>Remove</button>
                </li>
                <li data-exercise-item data-id="second">
                    <span data-exercise-item-number></span><span class="font-mono">I02</span>
                    <input name="items[1][code]" value="I02"><textarea name="items[1][stem]">Second</textarea>
                    <button type="button" data-item-up>Up</button><button type="button" data-item-down>Down</button><button type="button" data-remove-exercise-item>Remove</button>
                </li>
            </ol>
        </form>
    `);
    Object.assign(globalThis, {
        window: dom.window,
        document: dom.window.document,
        Element: dom.window.Element,
        HTMLButtonElement: dom.window.HTMLButtonElement,
        HTMLInputElement: dom.window.HTMLInputElement,
        HTMLTextAreaElement: dom.window.HTMLTextAreaElement,
        HTMLSelectElement: dom.window.HTMLSelectElement,
        HTMLLIElement: dom.window.HTMLLIElement,
        HTMLOListElement: dom.window.HTMLOListElement,
    });

    return dom;
}

test('authoring controls reorder stable rows, renumber submitted fields, and announce changes', () => {
    installDom();
    initializeExerciseAuthoring();
    const list = document.querySelector('[data-exercise-items]');
    const first = list.querySelector('[data-id="first"]');
    first.querySelector('[data-item-down]').click();

    assert.deepEqual([...list.children].map((row) => row.dataset.id), ['second', 'first']);
    assert.equal(list.children[0].querySelector('input').name, 'items[0][code]');
    assert.equal(list.children[0].querySelector('input').value, 'I02');
    assert.equal(list.children[1].querySelector('input').name, 'items[1][code]');
    assert.equal(list.children[1].querySelector('input').value, 'I01');
    assert.equal(document.querySelector('[data-exercise-authoring-status]').textContent, 'Exercise item moved down.');
    assert.equal(list.children[0].querySelector('[data-item-up]').disabled, true);
    assert.equal(list.children[1].querySelector('[data-item-down]').disabled, true);
});

test('add creates a blank server-new row and remove never crosses the template minimum', () => {
    installDom();
    initializeExerciseAuthoring();
    const list = document.querySelector('[data-exercise-items]');
    document.querySelector('[data-add-exercise-item]').click();

    assert.equal(list.children.length, 3);
    assert.equal(list.children[2].querySelector('input').value, '');
    assert.equal(list.children[2].querySelector('textarea').value, '');
    assert.equal(list.children[2].querySelector('.font-mono'), null);
    assert.equal(list.children[2].querySelector('input').name, 'items[2][code]');
    assert.equal(document.activeElement, list.children[2].querySelector('input'));

    list.children[2].querySelector('[data-remove-exercise-item]').click();
    list.children[1].querySelector('[data-remove-exercise-item]').click();
    assert.equal(list.children.length, 1);
    assert.equal(list.children[0].querySelector('[data-remove-exercise-item]').disabled, true);
});

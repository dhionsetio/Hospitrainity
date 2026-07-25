import assert from 'node:assert/strict';
import test from 'node:test';
import { JSDOM } from 'jsdom';
import { initializeLessonListening } from '../../resources/js/lesson-listening.js';

test('lesson listening reads learner text and updates its status', async () => {
    const dom = new JSDOM(`<!doctype html><html data-audio="on"><body>
        <button data-speak-target="lesson" data-playing-message="Playing" data-finished-message="Finished" aria-describedby="status" aria-pressed="false">
            <span data-listen-label>Listen</span><span data-stop-label hidden>Stop</span>
        </button>
        <p id="status"></p><article id="lesson"> Welcome   to the hotel. </article>
    </body></html>`);
    const spoken = [];
    const controller = { speak: async (text) => { spoken.push(text); }, stop: () => {} };

    globalThis.HTMLButtonElement = dom.window.HTMLButtonElement;
    globalThis.HTMLElement = dom.window.HTMLElement;
    initializeLessonListening({ root: dom.window.document, createController: () => controller });
    dom.window.document.querySelector('button').click();
    await new Promise((resolve) => setTimeout(resolve, 0));

    assert.deepEqual(spoken, ['Welcome to the hotel.']);
    assert.equal(dom.window.document.getElementById('status').textContent, 'Finished');
    assert.equal(dom.window.document.querySelector('button').getAttribute('aria-pressed'), 'false');
    dom.window.close();
});

test('lesson listening does not initialize when audio is disabled', () => {
    const dom = new JSDOM('<!doctype html><html data-audio="off"><body><button data-speak-target="lesson"></button><article id="lesson">Text</article></body></html>');
    let created = false;

    initializeLessonListening({ root: dom.window.document, createController: () => { created = true; } });

    assert.equal(created, false);
    dom.window.close();
});

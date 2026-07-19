import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const learnerDataSurfaces = [
    "resources/js/exercises/engine.js",
    "resources/views/practice.blade.php",
    "resources/views/material.blade.php",
];

test("learner-controlled data surfaces do not invoke HTML parsing sinks", async () => {
    for (const path of learnerDataSurfaces) {
        const source = await readFile(path, "utf8");

        assert.doesNotMatch(source, /\.innerHTML\s*=/, `${path} assigns innerHTML`);
        assert.doesNotMatch(source, /insertAdjacentHTML\s*\(/, `${path} calls insertAdjacentHTML`);
        assert.doesNotMatch(source, /document\.write\s*\(/, `${path} calls document.write`);
        assert.match(source, /textContent|createTextNode|replaceChildren/, `${path} lacks safe DOM construction`);
    }
});

test("learner completion actions persist only the current item", async () => {
    for (const path of learnerDataSurfaces) {
        const source = await readFile(path, "utf8");

        assert.doesNotMatch(source, /items:\s*all(?:Items|Exercises)\.map\s*\(/, `${path} grants every item at once`);
        assert.match(source, /items:\s*\[action\.itemId\]/, `${path} does not persist the current item`);
    }
});

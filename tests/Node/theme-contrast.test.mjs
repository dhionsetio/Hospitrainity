import assert from "node:assert/strict";
import { readdirSync, readFileSync } from "node:fs";
import { join } from "node:path";
import test from "node:test";

const source = readFileSync("resources/css/app.css", "utf8");
const darkBlock = source.match(/html\[data-theme="dark"\]\s*\{([\s\S]*?)\n\}/)?.[1] ?? "";
const tokens = Object.fromEntries(
    [...darkBlock.matchAll(/--([a-z0-9-]+):\s*(#[0-9a-f]{6});/gi)].map((match) => [match[1], match[2]]),
);

function luminance(hex) {
    const channels = hex.slice(1).match(/../g).map((channel) => Number.parseInt(channel, 16) / 255);
    const linear = channels.map((channel) => channel <= 0.04045
        ? channel / 12.92
        : ((channel + 0.055) / 1.055) ** 2.4);

    return (0.2126 * linear[0]) + (0.7152 * linear[1]) + (0.0722 * linear[2]);
}

function contrast(foreground, background) {
    const foregroundLuminance = luminance(tokens[foreground]);
    const backgroundLuminance = luminance(tokens[background]);

    return (Math.max(foregroundLuminance, backgroundLuminance) + 0.05)
        / (Math.min(foregroundLuminance, backgroundLuminance) + 0.05);
}

function filesBelow(directory) {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const path = join(directory, entry.name);

        return entry.isDirectory() ? filesBelow(path) : [path];
    });
}

test("dark theme uses layered gray surfaces instead of pure black", () => {
    assert.equal(tokens["hsp-canvas"], "#202428");
    assert.equal(tokens["hsp-surface"], "#292e34");
    assert.equal(tokens["hsp-surface-subtle"], "#30363d");
    assert.notEqual(tokens["hsp-canvas"], "#000000");
    assert.notEqual(tokens["hsp-surface"], tokens["hsp-canvas"]);
});

test("dark theme text and selected-state pairs exceed WCAG AA contrast thresholds", () => {
    const textPairs = [
        ["hsp-text", "hsp-canvas"],
        ["hsp-text-muted", "hsp-canvas"],
        ["hsp-text-subtle", "hsp-canvas"],
        ["hsp-accent-text-strong", "hsp-accent-soft"],
        ["hsp-success-text", "hsp-success-soft"],
        ["hsp-danger-text", "hsp-danger-soft"],
        ["hsp-warning-text", "hsp-warning-soft"],
        ["hsp-info-text", "hsp-info-soft"],
        ["hsp-purple-text", "hsp-purple-soft"],
    ];

    for (const [foreground, background] of textPairs) {
        assert.ok(
            contrast(foreground, background) >= 4.5,
            `${foreground} on ${background} must be at least 4.5:1`,
        );
    }
});

test("dark theme control boundaries exceed the non-text contrast threshold", () => {
    for (const background of ["hsp-canvas", "hsp-surface", "hsp-surface-subtle"]) {
        assert.ok(
            contrast("hsp-border", background) >= 3,
            `hsp-border on ${background} must be at least 3:1`,
        );
    }

    assert.ok(contrast("hsp-accent-border", "hsp-accent-soft") >= 3);
    for (const family of ["success", "danger", "warning", "info", "purple"]) {
        assert.ok(
            contrast(`hsp-${family}-border`, `hsp-${family}-soft`) >= 3,
            `${family} boundary must be at least 3:1 against its semantic surface`,
        );
    }

    assert.match(source, /prefers-color-scheme:\s*dark/);
    assert.match(source, /has-\[:checked\]:bg-indigo-50/);
});

test("every theme-sensitive color utility used by views and scripts has an exact semantic override", () => {
    const families = [
        "neutral", "slate", "gray", "zinc", "stone", "indigo", "blue", "green", "red", "amber", "yellow",
        "purple", "pink", "orange", "cyan", "teal", "emerald", "lime", "sky", "violet", "fuchsia", "rose",
    ].join("|");
    const utilityPattern = new RegExp(
        `^(?:(?:[a-z0-9-]+|has-\\[[^\\]]+\\]):)*(?:bg|text|border|ring|outline|divide|placeholder|decoration|accent|caret|fill|stroke)-(?:white|black|transparent|current|inherit|${families})(?:-[0-9]+)?(?:/[0-9]+)?$`,
    );
    const intentionallyFixed = new Set(["bg-transparent", "border-transparent", "border-white", "text-white"]);
    const utilities = new Set();
    const paths = [
        ...filesBelow("resources/views").filter((file) => file.endsWith(".blade.php")),
        ...filesBelow("resources/js").filter((file) => file.endsWith(".js")),
        ...filesBelow("vendor/laravel/framework/src/Illuminate/Pagination/resources/views")
            .filter((file) => file.endsWith(".blade.php")),
    ];

    for (const path of paths) {
        const candidates = readFileSync(path, "utf8").match(/[A-Za-z0-9_:[\]/.-]+/g) ?? [];
        for (const candidate of candidates) {
            if (utilityPattern.test(candidate) && !intentionallyFixed.has(candidate)) utilities.add(candidate);
        }
    }

    const missing = [...utilities].filter((utility) => {
        const needsExactClassToken = utility.includes(":") || utility.includes("/") || utility.startsWith("divide-");

        return needsExactClassToken
            ? !source.includes(`[class~="${utility}"]`)
            : !source.includes(`.${utility}`);
    });

    assert.deepEqual(missing, [], `Missing semantic overrides: ${missing.join(", ")}`);
});

import assert from "node:assert/strict";
import { createHash } from "node:crypto";
import { existsSync, readdirSync, readFileSync } from "node:fs";
import { join } from "node:path";
import test from "node:test";

const source = readFileSync("resources/css/app.css", "utf8");
const lightBlock = source.match(/:root\s*\{([\s\S]*?)\n\}/)?.[1] ?? "";
const darkBlock = source.match(/html\[data-theme="dark"\]\s*\{([\s\S]*?)\n\}/)?.[1] ?? "";
const systemDarkBlock = source.match(/html\[data-theme="system"\]\s*\{([\s\S]*?)\n\s*\}/)?.[1] ?? "";

function directColorTokens(block) {
    return Object.fromEntries(
        [...block.matchAll(/--([a-z0-9-]+):\s*(#[0-9a-f]{6});/gi)]
            .map((match) => [match[1], match[2].toLowerCase()]),
    );
}

const themes = {
    light: directColorTokens(lightBlock),
    dark: directColorTokens(darkBlock),
};
const systemDarkTokens = directColorTokens(systemDarkBlock);

function luminance(hex) {
    const channels = hex.slice(1).match(/../g).map((channel) => Number.parseInt(channel, 16) / 255);
    const linear = channels.map((channel) => channel <= 0.04045
        ? channel / 12.92
        : ((channel + 0.055) / 1.055) ** 2.4);

    return (0.2126 * linear[0]) + (0.7152 * linear[1]) + (0.0722 * linear[2]);
}

function contrast(tokens, foreground, background) {
    assert.ok(tokens[foreground], `Missing ${foreground}`);
    assert.ok(tokens[background], `Missing ${background}`);

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

test("light and dark themes use the approved layered surfaces", () => {
    assert.equal(themes.light["hsp-canvas"], "#f7fafc");
    assert.equal(themes.light["hsp-surface"], "#ffffff");
    assert.equal(themes.light["hsp-surface-subtle"], "#eaf6fc");

    assert.equal(themes.dark["hsp-canvas"], "#16232c");
    assert.equal(themes.dark["hsp-surface"], "#1e2d37");
    assert.equal(themes.dark["hsp-surface-subtle"], "#283a45");
    assert.notEqual(themes.dark["hsp-canvas"], "#000000");
    assert.notEqual(themes.dark["hsp-surface"], themes.dark["hsp-canvas"]);
    assert.deepEqual(systemDarkTokens, themes.dark, "System dark mode must use the explicit dark palette");
});

test("documented text and action pairs exceed WCAG AA contrast thresholds", () => {
    const sharedPairs = [
        ["hsp-text", "hsp-canvas"],
        ["hsp-text-muted", "hsp-canvas"],
        ["hsp-text-subtle", "hsp-canvas"],
        ["hsp-accent-text", "hsp-surface"],
        ["hsp-success-text", "hsp-success-soft"],
        ["hsp-danger-text", "hsp-danger-soft"],
        ["hsp-warning-text", "hsp-warning-soft"],
        ["hsp-info-text", "hsp-info-soft"],
        ["hsp-purple-text", "hsp-purple-soft"],
    ];

    for (const [theme, tokens] of Object.entries(themes)) {
        for (const [foreground, background] of sharedPairs) {
            assert.ok(
                contrast(tokens, foreground, background) >= 4.5,
                `${theme}: ${foreground} on ${background} must be at least 4.5:1`,
            );
        }

        assert.ok(
            contrast(tokens, "hsp-accent-on-bright", "hsp-accent-bright") >= 4.5,
            `${theme}: primary button text must be at least 4.5:1`,
        );
        assert.ok(
            contrast(tokens, "hsp-accent-on-solid", "hsp-accent-solid") >= 4.5,
            `${theme}: compatibility button text must be at least 4.5:1`,
        );
    }
});

test("meaningful component boundaries exceed the non-text contrast threshold", () => {
    for (const [theme, tokens] of Object.entries(themes)) {
        for (const background of ["hsp-canvas", "hsp-surface", "hsp-surface-subtle"]) {
            assert.ok(
                contrast(tokens, "hsp-border", background) >= 3,
                `${theme}: hsp-border on ${background} must be at least 3:1`,
            );
        }

        assert.ok(contrast(tokens, "hsp-accent-border", "hsp-accent-soft") >= 3);
        for (const family of ["success", "danger", "warning", "info", "purple"]) {
            assert.ok(
                contrast(tokens, `hsp-${family}-border`, `hsp-${family}-soft`) >= 3,
                `${theme}: ${family} boundary must be at least 3:1 against its semantic surface`,
            );
        }
    }
});

test("the approved self-hosted variable font and interaction tokens are present", () => {
    const fontPath = "resources/fonts/plus-jakarta-sans/PlusJakartaSans-Variable.ttf";
    const licensePath = "resources/fonts/plus-jakarta-sans/OFL.txt";

    assert.ok(existsSync(fontPath));
    assert.ok(existsSync(licensePath));
    assert.equal(
        createHash("sha256").update(readFileSync(fontPath)).digest("hex"),
        "3c9102733d96af218ea12aab89fd2c04a6d3c2bee9acf37057fc9b29139b451b",
    );
    assert.match(source, /font-family:\s*"Plus Jakarta Sans"/);
    assert.match(source, /font-weight:\s*200 800/);
    assert.match(source, /font-display:\s*swap/);
    assert.match(source, /--hsp-space-md:\s*1rem/);
    assert.match(source, /--hsp-radius-card:\s*0\.875rem/);
    assert.match(source, /--hsp-motion-state:\s*200ms/);
    assert.match(source, /--hsp-control-min:\s*2\.75rem/);
    assert.match(source, /prefers-reduced-motion:\s*reduce/);
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

/* global document */

import { chromium } from '@playwright/test';
import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const repoRoot = path.resolve(fileURLToPath(new URL('../../', import.meta.url)));
const outputDirectory = path.join(repoRoot, 'curriculum', 'evidence', 'cf-7-web-visuals');
const reportPath = path.join(repoRoot, 'curriculum', 'evidence', 'cf-7-visual-comparison.json');
const sourceCopy = path.join(repoRoot, 'storage', 'framework', 'testing', 'cf7', 'docx-render-word', 'Hospitrainity-source-copy.docx');
const sourceAuthoritySha256 = '7f8a2c62db6884498f7a1c2de56e79e39609262944a685fe7eb97f702444cbb4';
const baseURL = process.env.CF7_BASE_URL ?? 'http://127.0.0.1:8000';

function sha256(contents) {
    return createHash('sha256').update(contents).digest('hex');
}

const sourceContents = await readFile(sourceCopy);
if (sha256(sourceContents) !== sourceAuthoritySha256) {
    throw new Error('The DOCX render copy does not match the declared source authority.');
}

await mkdir(outputDirectory, { recursive: true });
const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1280, height: 900 } });
const page = await context.newPage();
const browserErrors = [];
page.on('console', message => {
    if (message.type() === 'error' || message.type() === 'warning') browserErrors.push(`${message.type()}: ${message.text()}`);
});
page.on('pageerror', error => browserErrors.push(`page: ${error.message}`));

await page.goto(`${baseURL}/login`);
await page.getByLabel('Email address').fill('user@example.com');
await page.getByLabel('Password').fill('password');
await page.getByRole('button', { name: 'Sign in' }).click();
await page.waitForURL(`${baseURL}/dashboard`);

const targets = [
    { code: 'HSP-C02-LS-04', label: 'vocabulary-table', path: '/curriculum/sections/HSP-C02-LS-04' },
    { code: 'HSP-C05-LS-06', label: 'model-email', path: '/curriculum/sections/HSP-C05-LS-06' },
    { code: 'HSP-C07-LS-06', label: 'model-dialogue-letter', path: '/curriculum/sections/HSP-C07-LS-06' },
];
const screenshots = [];
for (const target of targets) {
    await page.goto(`${baseURL}${target.path}`);
    const fileName = `${target.code}-${target.label}-1280.png`;
    const destination = path.join(outputDirectory, fileName);
    const contents = await page.screenshot({ fullPage: true });
    await writeFile(destination, contents);
    const metrics = await page.evaluate(() => ({
        client_width: document.documentElement.clientWidth,
        scroll_width: document.documentElement.scrollWidth,
        heading: document.querySelector('h1')?.textContent?.trim() ?? null,
        table_count: document.querySelectorAll('table').length,
        external_link_count: document.querySelectorAll('a[target="_blank"]').length,
    }));
    screenshots.push({
        section_code: target.code,
        path: path.relative(repoRoot, destination).replaceAll('\\', '/'),
        sha256: sha256(contents),
        bytes: contents.length,
        viewport: { width: 1280, height: 900 },
        metrics,
    });
}

await page.setViewportSize({ width: 320, height: 720 });
await page.goto(`${baseURL}/curriculum/sections/HSP-C02-LS-04`);
const mobileDestination = path.join(outputDirectory, 'HSP-C02-LS-04-vocabulary-table-320.png');
const mobileContents = await page.screenshot({ fullPage: true });
await writeFile(mobileDestination, mobileContents);
const mobileMetrics = await page.evaluate(() => {
    const region = document.querySelector('[role="region"]');
    return {
        client_width: document.documentElement.clientWidth,
        scroll_width: document.documentElement.scrollWidth,
        table_region_client_width: region?.clientWidth ?? null,
        table_region_scroll_width: region?.scrollWidth ?? null,
    };
});
screenshots.push({
    section_code: 'HSP-C02-LS-04',
    path: path.relative(repoRoot, mobileDestination).replaceAll('\\', '/'),
    sha256: sha256(mobileContents),
    bytes: mobileContents.length,
    viewport: { width: 320, height: 720 },
    metrics: mobileMetrics,
});

await browser.close();
const webVerified = browserErrors.length === 0
    && screenshots.every(screenshot => screenshot.metrics.client_width === screenshot.metrics.scroll_width);
const report = {
    report_version: '1.0.0',
    status: 'blocked_environment',
    checked_at: new Date().toISOString(),
    source_authority: { artifact: 'Hospitrainity.docx', sha256: sourceAuthoritySha256 },
    source_document_render: {
        status: 'blocked_environment',
        source_copy_sha256: sha256(sourceContents),
        attempts: [
            { renderer: 'bundled render_docx.py', result: 'blocked', reason: 'LibreOffice executable is not installed or discoverable.' },
            { renderer: 'Microsoft Word 16 COM, hidden/read-only with macros and link updates disabled', result: 'blocked_after_120_seconds', reason: 'Automation did not return from Documents.Open and produced no PDF; only the newly spawned automation process was stopped.' },
        ],
    },
    web_render: {
        status: webVerified ? 'verified' : 'failed',
        browser: 'Playwright Chromium',
        browser_errors: browserErrors,
        screenshots,
    },
    comparison: {
        status: 'not_performed',
        reason: 'A source-document render is required before visual equivalence can be reviewed; structured source-fidelity and browser tests do not substitute for that render.',
    },
};

await writeFile(reportPath, `${JSON.stringify(report, null, 4)}\n`, 'utf8');
process.stdout.write(`${JSON.stringify({ status: report.status, web_render: report.web_render.status, screenshots: screenshots.length, report: path.relative(repoRoot, reportPath).replaceAll('\\', '/') }, null, 4)}\n`);

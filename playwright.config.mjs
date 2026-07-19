import path from 'node:path';

import { defineConfig, devices } from '@playwright/test';

import { artifactRoot, baseURL } from './scripts/e2e/environment.mjs';

export default defineConfig({
    testDir: './tests/E2E',
    fullyParallel: false,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    workers: 1,
    globalSetup: './scripts/e2e/start-server.mjs',
    timeout: 45_000,
    expect: { timeout: 10_000 },
    outputDir: path.join(artifactRoot, 'test-results'),
    reporter: [
        ['line'],
        ['html', { open: 'never', outputFolder: path.join(artifactRoot, 'playwright-report') }],
    ],
    use: {
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],
});

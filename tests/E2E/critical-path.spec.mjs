import { expect, test } from '@playwright/test';
import path from 'node:path';
import { pathToFileURL } from 'node:url';

import { readTestAccounts, repoRoot } from '../../scripts/e2e/environment.mjs';

const testAccounts = readTestAccounts();

function captureBrowserErrors(page) {
    const errors = [];
    page.on('console', (message) => {
        if (message.type() === 'error') errors.push(`console: ${message.text()}`);
    });
    page.on('pageerror', (error) => errors.push(`page: ${error.message}`));
    return errors;
}

function contrastRatio(foreground, background) {
    const channelValues = (color) => color.match(/[\d.]+/g).slice(0, 3).map(Number);
    const luminance = (color) => {
        const linear = channelValues(color).map((channel) => {
            const value = channel / 255;
            return value <= 0.04045 ? value / 12.92 : ((value + 0.055) / 1.055) ** 2.4;
        });

        return (0.2126 * linear[0]) + (0.7152 * linear[1]) + (0.0722 * linear[2]);
    };
    const foregroundLuminance = luminance(foreground);
    const backgroundLuminance = luminance(background);

    return (Math.max(foregroundLuminance, backgroundLuminance) + 0.05)
        / (Math.min(foregroundLuminance, backgroundLuminance) + 0.05);
}

async function signIn(page, account, testInfo = null, recoverySlot = 0) {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(account.email);
    await page.getByLabel('Password').fill(account.password);
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    await page.waitForURL((url) => url.pathname !== '/login');
    if (new URL(page.url()).pathname === '/mfa-challenge') {
        const projectOrder = ['chromium', 'webkit', 'mobile-chromium', 'mobile-webkit', 'firefox'];
        const projectIndex = projectOrder.indexOf(testInfo?.project.name);
        const codeIndex = projectIndex + (recoverySlot * projectOrder.length);
        if (projectIndex < 0 || !account.recoveryCodes?.[codeIndex]) {
            throw new Error(`No isolated MFA fixture is available for ${testInfo?.project.name ?? 'this project'}.`);
        }
        await page.getByLabel('Recovery code').fill(account.recoveryCodes[codeIndex]);
        await page.getByRole('button', { name: 'Verify and continue' }).click();
        await page.waitForURL((url) => url.pathname !== '/mfa-challenge');
    }
}

async function openActivity(page, code) {
    await page.goto(`/curriculum/activities/${code}`);
    await expect(page.getByRole('heading', { name: 'Your responses' })).toBeVisible();
}

async function completeVisibleActivity(page) {
    const promptCards = page.locator('article[id^="prompt-"]');
    for (let index = 0; index < await promptCards.count(); index += 1) {
        const card = promptCards.nth(index);
        const radios = card.getByRole('radio');
        if (await radios.count() > 0) await radios.first().check();

        const textInput = card.locator('input[type="text"]');
        if (await textInput.count() > 0) await textInput.fill('Please');

        const textarea = card.locator('textarea');
        if (await textarea.count() > 0) await textarea.fill('A private rehearsal response for this browser test.');

        const selfCheck = card.locator('input[type="checkbox"]');
        if (await selfCheck.count() > 0) await selfCheck.check();
    }
}

async function checkActivity(page) {
    await page.getByRole('button', { name: 'Check responses' }).click();
    await expect(page.locator('#attempt-result')).toContainText('activity completed');
    await expect(page.locator('#attempt-result')).toBeFocused();
}

test('public mobile navigation is keyboard operable', async ({ page }) => {
    const browserErrors = captureBrowserErrors(page);
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/');

    await expect(page.getByRole('heading', { level: 1 })).toContainText('Practice Hospitality English');
    const menuButton = page.locator('#mobile-menu-button');
    await expect(menuButton).toHaveAccessibleName('Open navigation');
    await menuButton.focus();
    await menuButton.press('Enter');
    await expect(menuButton).toHaveAttribute('aria-expanded', 'true');
    await expect(menuButton).toHaveAccessibleName('Close navigation');
    await page.keyboard.press('Escape');
    await expect(menuButton).toHaveAttribute('aria-expanded', 'false');
    await expect(menuButton).toBeFocused();
    expect(browserErrors).toEqual([]);
});

test('language switcher and blue-gray dark theme render stable, readable states', async ({ page }) => {
    const browserErrors = captureBrowserErrors(page);
    await page.goto('/');

    await expect(page.locator('[data-language-switcher] [data-flag="id"]')).toHaveCount(2);
    await expect(page.locator('[data-language-switcher] [data-flag="gb"]')).toHaveCount(2);
    await expect(page.locator('body')).not.toContainText('\u{1F1EE}\u{1F1E9}');
    await expect(page.locator('body')).not.toContainText('\u{1F1EC}\u{1F1E7}');

    await signIn(page, testAccounts.learner);
    await page.goto('/preferences/display');
    await page.getByRole('radio', { name: 'Dark' }).check();
    await page.getByRole('button', { name: 'Save preferences' }).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');
    await expect(page.locator('label:has(input[name="ui_theme"][value="dark"]:checked)'))
        .toHaveCSS('background-color', 'rgb(23, 62, 80)');

    const renderedTheme = await page.evaluate(() => {
        const selected = globalThis.document.querySelector('label:has(input[name="ui_theme"][value="dark"]:checked)');
        const fieldset = selected.closest('fieldset');

        return {
            bodyBackground: globalThis.getComputedStyle(globalThis.document.body).backgroundColor,
            selectedBackground: globalThis.getComputedStyle(selected).backgroundColor,
            selectedColor: globalThis.getComputedStyle(selected).color,
            surfaceBackground: globalThis.getComputedStyle(fieldset).backgroundColor,
        };
    });

    expect(renderedTheme.bodyBackground).toBe('rgb(22, 35, 44)');
    expect(renderedTheme.surfaceBackground).toBe('rgb(30, 45, 55)');
    expect(renderedTheme.selectedBackground).toBe('rgb(23, 62, 80)');
    expect(contrastRatio(renderedTheme.selectedColor, renderedTheme.selectedBackground)).toBeGreaterThanOrEqual(4.5);

    await page.goto('/curriculum/sections/HSP-C01-LS-05');
    const learningCardTheme = await page.evaluate(() => [...globalThis.document.querySelectorAll('.hsp-learning-cards article')]
        .map((card) => {
            const lead = card.querySelector('.hsp-learning-cards__lead');

            return {
                background: globalThis.getComputedStyle(card).backgroundColor,
                color: globalThis.getComputedStyle(lead).color,
            };
        }));
    expect(learningCardTheme).not.toHaveLength(0);
    for (const card of learningCardTheme) {
        expect(card.background).toBe('rgb(30, 45, 55)');
        expect(contrastRatio(card.color, card.background)).toBeGreaterThanOrEqual(4.5);
    }

    await page.goto('/search?q=guest');
    const paginationTheme = await page.locator('nav[role="navigation"] p').evaluate((summary) => ({
        background: globalThis.getComputedStyle(globalThis.document.body).backgroundColor,
        color: globalThis.getComputedStyle(summary).color,
    }));
    expect(contrastRatio(paginationTheme.color, paginationTheme.background)).toBeGreaterThanOrEqual(4.5);
    expect(browserErrors).toEqual([]);
});

test('public Help, glossary, and About expose bounded bilingual-ready guidance', async ({ page }) => {
    const browserErrors = captureBrowserErrors(page);

    await page.goto('/help');
    await expect(page.getByRole('heading', { name: 'Help', exact: true })).toBeVisible();
    const invitationsCard = page.getByRole('link', { name: /Invitations and classroom codes/ });
    await expect(invitationsCard).toBeVisible();
    await expect(invitationsCard).toHaveCSS('cursor', 'pointer');
    await invitationsCard.click();
    await expect(page.getByRole('heading', { level: 1, name: 'Invitations and classroom codes' })).toBeVisible();
    await page.getByRole('link', { name: 'Return to Help' }).click();
    await expect(page.getByRole('heading', { name: 'Help', exact: true })).toBeVisible();
    await expect(page.getByText(/Help version/)).toHaveCount(0);
    await expect(page.getByText('dhionsetio@gmail.com')).toHaveCount(0);

    await page.goto('/glossary');
    await expect(page.getByRole('heading', { name: 'Glossary' })).toBeVisible();
    await expect(page.getByText('Confidence check', { exact: true })).toBeVisible();

    await page.goto('/about');
    await expect(page.getByRole('heading', { name: 'About Hospitrainity' })).toBeVisible();
    await expect(page.getByText(/not a grade or proof of mastery/i)).toBeVisible();
    expect(browserErrors).toEqual([]);
});

test('public registration creates a personal account without institution enumeration', async ({ page }) => {
    const browserErrors = captureBrowserErrors(page);
    await page.goto('/register');

    await expect(page.getByRole('heading', { name: 'Create a personal learning account' })).toBeVisible();
    await expect(page.getByText('Earlier personal progress is not copied or shown to institution staff.')).toBeVisible();
    await expect(page.getByRole('checkbox')).toHaveCount(2);
    await expect(page.getByRole('link', { name: 'privacy notice' })).toHaveAttribute('href', /\/policies\/privacy$/);
    await expect(page.getByRole('link', { name: 'terms', exact: true })).toHaveAttribute('href', /\/policies\/terms$/);
    await expect(page.getByRole('combobox')).toHaveCount(0);
    await expect(page.getByText('Hotel A')).toHaveCount(0);
    await expect(page.getByText('Hotel B')).toHaveCount(0);
    await expect(page.getByText('Hospitrainity HQ')).toHaveCount(0);
    await expect(page.getByText('State Polytechnic of Malang')).toHaveCount(0);
    expect(browserErrors).toEqual([]);
});

test('public trust pages explain data handling and the sensitive request flow requires step-up', async ({ page }) => {
    const browserErrors = captureBrowserErrors(page);
    await page.goto('/policies/privacy');
    await expect(page.getByRole('heading', { name: 'Privacy notice' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Data we handle' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'How long data is kept' })).toBeVisible();
    await expect(page.getByText('2026-07-20-prototype.1')).toHaveCount(0);
    await expect(page.getByText(/not a claim of legal compliance/i)).toHaveCount(0);
    await expect(page.getByRole('link', { name: 'Accessibility' })).toBeVisible();

    await signIn(page, testAccounts.learner);
    await page.goto('/privacy/requests');
    await expect(page.getByRole('heading', { name: 'Privacy and account requests' })).toBeVisible();
    await page.getByRole('link', { name: /Download my data/ }).click();
    await expect(page).toHaveURL(/\/confirm-password$/);
    await page.getByLabel('Password').fill(testAccounts.learner.password);
    await page.getByRole('button', { name: 'Confirm password' }).click();
    await expect(page).toHaveURL(/\/privacy\/requests\/sensitive\/access-export$/);
    await page.getByRole('checkbox').check();
    await page.getByRole('button', { name: 'Submit sensitive request' }).click();
    await expect(page.getByRole('status')).toContainText('Your request was recorded');
    await expect(page.getByText('Access export').first()).toBeVisible();
    await page.getByRole('button', { name: 'Cancel request' }).click();
    await expect(page.getByRole('status')).toContainText('cancelled');
    expect(browserErrors).toEqual([]);
});

test('supervisor can issue an invitation and operate an assigned Class without private learner data', async ({ page }, testInfo) => {
    const browserErrors = captureBrowserErrors(page);
    const targetEmail = `browser-${testInfo.project.name.replaceAll(/[^a-z0-9]/g, '-')}@example.com`;
    await signIn(page, testAccounts.supervisor, testInfo);

    await expect(page).toHaveURL(/\/supervisor\/dashboard$/);
    const mobileMenu = page.getByRole('button', { name: 'Menu' });
    if (await mobileMenu.isVisible()) {
        await mobileMenu.click();
        await page.locator('dialog[open]').getByRole('link', { name: 'Invitations' }).click();
    } else {
        await page.locator('aside').getByRole('link', { name: 'Invitations' }).click();
    }
    await expect(page.getByRole('heading', { name: 'Invitations' })).toBeVisible();
    await page.getByLabel('Email address').fill(targetEmail);
    await page.getByRole('button', { name: 'Send invitation' }).click();
    await expect(page.getByRole('status')).toContainText('If the address is eligible');
    await expect(page.locator('p:visible, td:visible').filter({ hasText: /b.+@example\.com/ }).first()).toBeVisible();
    await expect(page.locator('span:visible, td:visible').filter({ hasText: /^Pending$/ }).first()).toBeVisible();

    await page.goto('/supervisor/classes');
    await expect(page.getByRole('heading', { level: 1, name: 'Classes' })).toBeVisible();
    const classCard = page.getByRole('article').filter({ hasText: 'Browser Test Class' });
    await expect(classCard).toContainText('1 active learner');
    await expect(classCard).toContainText('Hospitrainity Test Supervisor');
    await classCard.getByRole('link', { name: 'Open Class' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Browser Test Class' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Roster' })).toBeVisible();
    await expect(page.getByText('Hospitrainity Test Learner')).toBeVisible();
    await expect(page.getByText('user@example.com')).toHaveCount(0);
    await expect(page.getByText(/Personal self-study activity and private responses are not included/)).toBeVisible();

    const instructionTitle = `Arrival practice ${testInfo.project.name}`;
    await page.getByLabel('Instruction title').fill(instructionTitle);
    await page.getByLabel('Instruction', { exact: true }).fill('Complete the arrival dialogue before the next session.');
    await page.getByLabel('Related module').selectOption('');
    await page.getByRole('button', { name: 'Post instruction' }).click();
    await expect(page.getByRole('status')).toContainText('available to enrolled learners');
    await expect(page.getByRole('heading', { name: instructionTitle })).toBeVisible();

    const learnerCard = page.getByRole('article').filter({ hasText: 'Hospitrainity Test Learner' });
    await learnerCard.getByRole('link', { name: 'View Class progress' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Hospitrainity Test Learner' })).toBeVisible();
    await expect(page.getByText('Browser Test Class')).toBeVisible();
    await expect(page.getByText('user@example.com')).toHaveCount(0);
    await expect(page.getByRole('heading', { name: 'Guest welcome' })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Service recovery' })).toBeVisible();
    await page.goBack({ waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { level: 1, name: 'Browser Test Class' })).toBeVisible();

    await page.getByRole('link', { name: 'Learner preview' }).click();
    await expect(page.getByRole('status')).toContainText('Learner preview');
    await expect(page.getByRole('status')).toContainText('no learner progress or answers are saved');
    expect(await page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth')).toBe(true);

    await page.context().clearCookies();
    await signIn(page, testAccounts.learner);
    await page.getByRole('button', { name: 'Open learning context' }).click();
    await page.locator('#learner-context-panel').getByRole('button', { name: /Browser Test Class/ }).click();
    await expect(page.getByRole('heading', { name: 'Class instructions' })).toBeVisible();
    await expect(page.getByRole('heading', { name: instructionTitle })).toBeVisible();
    await expect(page.getByText('Complete the arrival dialogue before the next session.').first()).toBeVisible();
    await page.getByRole('button', { name: 'Open learning context' }).click();
    await page.locator('#learner-context-panel').getByRole('button', { name: /Personal self-study/ }).click();
    await expect(page.getByRole('button', { name: 'Open learning context' })).toContainText('Personal self-study');
    expect(browserErrors).toEqual([]);
});

test('mobile supervisor navigation exposes context and returns focus after Escape', async ({ page }, testInfo) => {
    const browserErrors = captureBrowserErrors(page);
    await page.setViewportSize({ width: 390, height: 844 });
    await signIn(page, testAccounts.supervisor, testInfo, 1);

    const menuButton = page.getByRole('button', { name: 'Menu' });
    await expect(menuButton).toBeVisible();
    await menuButton.click();

    const drawer = page.locator('dialog[data-shell-drawer]');
    await expect(drawer).toHaveJSProperty('open', true);
    await expect(drawer.getByRole('heading', { name: 'Navigation' })).toBeVisible();
    await expect(drawer).toContainText('Instructor');
    await expect(drawer.getByRole('link', { name: 'Team Dashboard' })).toHaveAttribute('aria-current', 'page');

    await page.keyboard.press('Escape');
    await expect(drawer).toHaveJSProperty('open', false);
    await expect(menuButton).toBeFocused();
    expect(await page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth')).toBe(true);
    expect(browserErrors).toEqual([]);
});

test('content admin sees task-relevant content information without system evidence', async ({ page }, testInfo) => {
    const browserErrors = captureBrowserErrors(page);
    await signIn(page, testAccounts.admin, testInfo);

    await expect(page).toHaveURL(/\/admin\/dashboard$/);
    await expect(page.getByRole('heading', { name: /Welcome/ })).toBeVisible();
    await expect(page.getByText('Published learning content is ready.')).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Learning content' })).toBeVisible();
    await expect(page.getByText('Technical evidence')).toHaveCount(0);
    await expect(page.getByText('Schema version')).toHaveCount(0);
    await expect(page.getByText('Package lifecycle status')).toHaveCount(0);
    await expect(page.getByText('Legacy evidence')).toHaveCount(0);
    await expect(page.locator('body')).not.toContainText('SHA-256');
    expect(await page.evaluate('document.documentElement.scrollWidth <= document.documentElement.clientWidth')).toBe(true);
    expect(browserErrors).toEqual([]);
});

test('verified learner can use the responsive shell and submit a canonical attempt', async ({ page }) => {
    const browserErrors = captureBrowserErrors(page);
    await signIn(page, testAccounts.learner);

    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.getByRole('heading', { name: 'Welcome Back!' })).toBeVisible();

    const viewport = page.viewportSize();
    const navigationName = viewport && viewport.width >= 1024
        ? 'Primary learner navigation'
        : 'Mobile learner navigation';
    const learnerNavigation = page.getByRole('navigation', { name: navigationName });
    await expect(learnerNavigation.getByRole('link', { name: 'Home', exact: true })).toHaveAttribute('aria-current', 'page');
    await expect(learnerNavigation.getByRole('link')).toHaveCount(4);

    const contextButton = page.locator('[data-shell-disclosure-button][aria-controls="learner-context-panel"]');
    const contextPanel = page.locator('#learner-context-panel');
    await expect(contextButton).toHaveAttribute('aria-label', 'Open learning context');
    await contextButton.click();
    await expect(contextPanel).toBeVisible();
    await expect(contextPanel.locator('[aria-current="true"]')).toHaveCount(1);
    await page.keyboard.press('Escape');
    await expect(contextPanel).toBeHidden();
    await expect(contextButton).toHaveAttribute('aria-expanded', 'false');
    await expect(contextButton).toHaveAttribute('aria-label', 'Open learning context');
    await expect(contextButton).toBeFocused();

    const accountButton = page.locator('[data-shell-disclosure-button][aria-controls="learner-account-panel"]');
    await expect(accountButton).toHaveAttribute('aria-label', 'Open account');
    await accountButton.click();
    const accountPanel = page.locator('#learner-account-panel');
    await expect(accountPanel).toBeVisible();
    await expect(accountPanel.locator('[role="menu"], [role="menuitem"]')).toHaveCount(0);
    await expect(accountPanel.getByRole('link', { name: 'Switch role' })).toHaveCount(0);
    await expect(accountPanel.getByRole('link', { name: 'Display preferences' })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(accountPanel).toBeHidden();
    await expect(accountButton).toHaveAttribute('aria-expanded', 'false');
    await expect(accountButton).toHaveAttribute('aria-label', 'Open account');
    await expect(accountButton).toBeFocused();

    const moduleCard = page.getByRole('article').filter({ hasText: 'Front Desk and Check-In' });
    await moduleCard.getByRole('link', { name: 'Open module' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Front Desk and Check-In' })).toBeVisible();
    const quizSection = page.getByRole('link').filter({ hasText: 'Step 7. Quiz' });
    await quizSection.click();
    await expect(page.getByRole('heading', { level: 1, name: 'Step 7. Quiz' })).toBeVisible();
    await page.getByRole('link', { name: 'Open activity' }).click();

    await expect(page.getByRole('heading', { name: 'Your responses' })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Show model without answering' })).toBeVisible();
    const responseGroups = page.getByRole('group');
    await expect(responseGroups).toHaveCount(8);
    for (let index = 0; index < await responseGroups.count(); index += 1) {
        await responseGroups.nth(index).getByRole('radio').first().check();
    }

    await page.getByRole('button', { name: 'Check responses' }).click();
    await expect(page.locator('#attempt-result')).toContainText('Responses checked and activity completed');
    await expect(page).toHaveURL(/#attempt-result$/);
    await page.reload();
    await expect(page.getByText('Completed', { exact: true })).toBeVisible();
    await expect(page.getByText(/\d+ attempts?/)).toBeVisible();
    await expect(page.getByText(/Progress state:/)).toHaveCount(0);
    expect(browserErrors).toEqual([]);
});

test('learner can resume or restart onboarding and search only published content', async ({ page }) => {
    const browserErrors = captureBrowserErrors(page);
    await signIn(page, testAccounts.learner);

    await page.goto('/getting-started');
    const restart = page.getByRole('button', { name: 'Restart guide' });
    if (await restart.isVisible()) await restart.click();
    await expect(page.getByRole('heading', { name: 'Getting started' })).toBeVisible();
    await expect(page.getByText('Step 1 of 3').first()).toBeVisible();
    await page.getByRole('button', { name: 'Skip for now' }).click();
    await expect(page).toHaveURL(/\/dashboard$/);

    await page.goto('/search?q=front&type=section');
    await expect(page.getByRole('heading', { name: 'Search results' })).toBeVisible();
    await expect(page.getByText(/results? found/)).toBeVisible();
    await expect(page.getByText(/No matching published content/)).toHaveCount(0);
    await expect(page.locator('mark')).toHaveCount(0);
    expect(browserErrors).toEqual([]);
});

test('selection, ordering, and short-text forms meet validation, focus, target, and non-drag contracts', async ({ page }) => {
    const browserErrors = captureBrowserErrors(page);
    await page.setViewportSize({ width: 320, height: 720 });
    await signIn(page, testAccounts.learner);
    await openActivity(page, 'HSP-C02-ACT-PRACTICE');

    const ordering = page.locator('[data-ordering-list]');
    await expect(ordering.locator('[draggable="true"]')).toHaveCount(0);
    const moveDown = ordering.getByRole('button', { name: /Move item at position 1 down/ });
    const moveBox = await moveDown.boundingBox();
    expect(moveBox?.height).toBeGreaterThanOrEqual(24);
    expect(moveBox?.width).toBeGreaterThanOrEqual(24);
    await moveDown.focus();
    await moveDown.press('Enter');
    await expect(ordering.locator('xpath=..').getByText(/Item moved to position 2/)).toBeAttached();
    await expect(ordering.getByRole('button', { name: /Move item at position 2 down/ })).toBeFocused();

    const firstChoiceLabel = page.locator('article[id^="prompt-"] label').filter({ has: page.locator('input[type="radio"]') }).first();
    const choiceBox = await firstChoiceLabel.boundingBox();
    expect(choiceBox?.height).toBeGreaterThanOrEqual(24);
    expect(choiceBox?.width).toBeGreaterThanOrEqual(24);

    await page.getByRole('button', { name: 'Check responses' }).click();
    const summary = page.locator('[data-error-summary]');
    await expect(summary).toBeFocused();
    await expect(summary.getByRole('link').first()).toContainText(/response to prompt/i);
    await summary.getByRole('link').first().focus();
    await summary.getByRole('link').first().press('Enter');
    await expect(page.locator('[aria-invalid="true"]:focus')).toHaveCount(1);

    const focusedRect = await page.locator(':focus').boundingBox();
    expect(focusedRect?.y).toBeGreaterThanOrEqual(0);
    expect((focusedRect?.y ?? 0) + (focusedRect?.height ?? 0)).toBeLessThanOrEqual(720);
    const focusStyle = await page.locator(':focus').evaluate(element => globalThis.getComputedStyle(element).outlineStyle);
    expect(focusStyle).not.toBe('none');

    await completeVisibleActivity(page);
    await checkActivity(page);
    expect(browserErrors).toEqual([]);
});

test('role-play and service-artifact forms require an explicit self-check', async ({ page }) => {
    const browserErrors = captureBrowserErrors(page);
    await signIn(page, testAccounts.learner);

    for (const code of ['HSP-C02-ACT-ROLEPLAY', 'HSP-C05-ACT-ROLEPLAY']) {
        await openActivity(page, code);
        const openCard = page.locator('article[id^="prompt-"]').filter({ has: page.locator('textarea') });
        await openCard.locator('textarea').fill('A private rehearsal response for this browser test.');
        await page.getByRole('button', { name: 'Check responses' }).click();
        await expect(page.locator('[data-error-summary]')).toBeFocused();
        const selfCheckError = page.locator('[data-error-summary] a').filter({ hasText: /self-check/i }).first();
        await expect(selfCheckError).toBeVisible();
        await selfCheckError.focus();
        await selfCheckError.press('Enter');
        await expect(openCard.locator('input[type="checkbox"]')).toBeFocused();

        await completeVisibleActivity(page);
        await checkActivity(page);
    }

    expect(browserErrors).toEqual([]);
});

test('rating history and explicit baseline skip remain distinct completion paths', async ({ page }) => {
    const browserErrors = captureBrowserErrors(page);
    await signIn(page, testAccounts.learner);
    await openActivity(page, 'HSP-C02-ACT-CONFIDENCE');
    await completeVisibleActivity(page);
    await checkActivity(page);
    await page.getByRole('link', { name: 'View my confidence history' }).click();
    await expect(page.getByRole('heading', { name: 'My confidence history' })).toBeVisible();
    await expect(page.getByText('These ratings help you reflect on how confident you feel over time.')).toBeVisible();
    await expect(page.getByText(/CEFR evidence/i)).toHaveCount(0);

    await openActivity(page, 'HSP-C01-ACT-BASELINE');
    await page.getByRole('button', { name: 'Explicitly skip this baseline' }).click();
    await expect(page.locator('#attempt-result')).toContainText('Baseline explicitly skipped');
    await expect(page.locator('#attempt-result')).toBeFocused();
    expect(browserErrors).toEqual([]);
});

test('generated standalone executes every response form in real Chromium', async ({ page }) => {
    const browserErrors = captureBrowserErrors(page);
    const standaloneURL = pathToFileURL(path.join(repoRoot, 'standalone', 'Hospitrainity-Standalone.html')).href;

    for (const code of [
        'HSP-C02-ACT-PRACTICE',
        'HSP-C02-ACT-ROLEPLAY',
        'HSP-C05-ACT-ROLEPLAY',
        'HSP-C02-ACT-CONFIDENCE',
    ]) {
        await page.goto(`${standaloneURL}#/activity/${code}`);
        await expect(page.locator('[data-session-attempt-form]')).toBeVisible();
        await completeVisibleActivity(page);
        await page.getByRole('button', { name: 'Check responses' }).click();
        await expect(page.locator('#attempt-result')).toContainText(/completed/i);
        await expect(page.locator('#attempt-result')).toBeFocused();
    }

    await page.goto(`${standaloneURL}#/activity/HSP-C01-ACT-BASELINE`);
    await page.getByRole('button', { name: 'Explicitly skip this baseline' }).click();
    await expect(page.locator('#attempt-result')).toContainText(/explicitly skipped/i);
    expect(browserErrors).toEqual([]);
});

test('superadmin sees the canonical legacy manager as read-only', async ({ page }, testInfo) => {
    const browserErrors = captureBrowserErrors(page);
    await page.setViewportSize({ width: 1280, height: 720 });
    await signIn(page, testAccounts.superadmin, testInfo);

    await expect(page).toHaveURL(/\/superadmin\/dashboard$/);
    const staffRail = page.locator('aside:visible');
    const accountDetails = staffRail.locator('details.hsp-staff-account-disclosure');
    const accountSummary = accountDetails.locator('summary');
    await expect(staffRail.getByRole('link', { name: 'Search' })).toBeVisible();
    await expect(accountDetails).not.toHaveAttribute('open', '');
    await accountSummary.focus();
    await accountSummary.press('Enter');
    await expect(accountDetails).toHaveAttribute('open', '');
    await expect(accountDetails.getByRole('link', { name: 'Getting started' })).toBeVisible();
    await expect(accountDetails.getByRole('button', { name: 'Logout' })).toBeVisible();

    const railContract = await staffRail.evaluate((rail) => {
        const style = globalThis.getComputedStyle(rail);
        const summary = rail.querySelector('summary');
        const summaryRect = summary.getBoundingClientRect();

        return {
            height: rail.getBoundingClientRect().height,
            overflowY: style.overflowY,
            position: style.position,
            summaryHeight: summaryRect.height,
        };
    });
    expect(railContract.height).toBeLessThanOrEqual(720);
    expect(railContract.overflowY).toBe('auto');
    expect(railContract.position).toBe('sticky');
    expect(railContract.summaryHeight).toBeGreaterThanOrEqual(44);

    const publicFooter = page.getByRole('navigation', { name: 'Public trust and support' }).locator('..');
    await publicFooter.scrollIntoViewIfNeeded();
    const shellDoesNotOverlapFooter = await page.evaluate(() => {
        const rail = globalThis.document.querySelector('aside');
        const footer = globalThis.document.querySelector('body > footer');
        const railRect = rail.getBoundingClientRect();
        const footerRect = footer.getBoundingClientRect();

        return railRect.bottom <= footerRect.top + 1;
    });
    expect(shellDoesNotOverlapFooter).toBe(true);

    await page.goto('/superadmin/modules');
    await expect(page.getByRole('note')).toContainText('The canonical curriculum is active.');
    await expect(page.getByRole('note')).toContainText('Legacy Evidence pages are read-only audit and rollback records.');
    await expect(page.getByRole('button', { name: /Create module/i })).toHaveCount(0);
    expect(browserErrors).toEqual([]);
});

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

test('language switcher and gray dark theme render stable, readable states', async ({ page }) => {
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
        .toHaveCSS('background-color', 'rgb(55, 59, 102)');

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

    expect(renderedTheme.bodyBackground).toBe('rgb(32, 36, 40)');
    expect(renderedTheme.surfaceBackground).toBe('rgb(41, 46, 52)');
    expect(renderedTheme.selectedBackground).toBe('rgb(55, 59, 102)');
    expect(contrastRatio(renderedTheme.selectedColor, renderedTheme.selectedBackground)).toBeGreaterThanOrEqual(4.5);
    expect(browserErrors).toEqual([]);
});

test('public Help, glossary, and About expose bounded bilingual-ready guidance', async ({ page }) => {
    const browserErrors = captureBrowserErrors(page);

    await page.goto('/help');
    await expect(page.getByRole('heading', { name: 'Help', exact: true })).toBeVisible();
    await expect(page.getByRole('link', { name: 'Invitations and classroom codes' })).toBeVisible();
    await expect(page.getByText(/Help version 2026-07-20/)).toBeVisible();
    await expect(page.getByText('dhionsetio@gmail.com')).toHaveCount(0);

    await page.goto('/glossary');
    await expect(page.getByRole('heading', { name: 'Glossary' })).toBeVisible();
    await expect(page.getByText('Confidence check', { exact: true })).toBeVisible();

    await page.goto('/about');
    await expect(page.getByRole('heading', { name: 'About Hospitrainity' })).toBeVisible();
    await expect(page.getByText(/not proof of proficiency or mastery/i)).toBeVisible();
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

test('public trust pages are versioned and the sensitive request flow requires step-up', async ({ page }) => {
    const browserErrors = captureBrowserErrors(page);
    await page.goto('/policies/privacy');
    await expect(page.getByRole('heading', { name: 'Privacy notice' })).toBeVisible();
    await expect(page.getByText('2026-07-20-prototype.1')).toBeVisible();
    await expect(page.getByText(/not a claim of legal compliance/i)).toBeVisible();
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

test('supervisor can issue an institution-scoped learner invitation', async ({ page }, testInfo) => {
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

test('verified learner can navigate, submit a canonical attempt, and use the account menu', async ({ page }) => {
    const browserErrors = captureBrowserErrors(page);
    await signIn(page, testAccounts.learner);

    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.getByRole('heading', { name: 'Welcome Back!' })).toBeVisible();

    const accountMenu = page.getByRole('button', { name: 'Open user menu' });
    await accountMenu.focus();
    await accountMenu.press('ArrowDown');
    await expect(page.getByRole('menuitem', { name: 'Dashboard' })).toBeFocused();
    await page.keyboard.press('End');
    await expect(page.getByRole('menuitem', { name: 'Logout' })).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(accountMenu).toBeFocused();

    const moduleCard = page.getByRole('article').filter({ hasText: 'Front Desk and Check-In' });
    await moduleCard.getByRole('link', { name: 'Open module' }).click();
    await expect(page.getByRole('heading', { level: 1, name: 'Front Desk and Check-In' })).toBeVisible();
    const quizSection = page.getByRole('article').filter({ has: page.getByRole('heading', { name: 'Step 7. Quiz' }) });
    await quizSection.getByRole('link', { name: 'Open section' }).click();
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
    await expect(page.getByText(/Progress state: completed.*Attempts: \d+/)).toBeVisible();
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
    await expect(page.getByText(/not test scores/i)).toBeVisible();

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
    await signIn(page, testAccounts.superadmin, testInfo);

    await expect(page).toHaveURL(/\/superadmin\/dashboard$/);
    await page.goto('/superadmin/modules');
    await expect(page.getByRole('note')).toContainText('The canonical curriculum is active.');
    await expect(page.getByRole('note')).toContainText('Legacy Evidence pages are read-only audit and rollback records.');
    await expect(page.getByRole('button', { name: /Create module/i })).toHaveCount(0);
    expect(browserErrors).toEqual([]);
});

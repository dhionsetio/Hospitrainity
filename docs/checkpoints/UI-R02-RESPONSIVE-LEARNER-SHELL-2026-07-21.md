# UI-R02 responsive learner shell checkpoint — 2026-07-21

State: COMPLETE  
Owner: Dhion Setio  
Next authorized phase: B06-B Class/roster operations  
Production data mutation: none

## Outcome

UI-R02 is complete. Verified learners now use one shared responsive shell across learner application and authenticated Help pages:

- a persistent desktop rail at 1024 CSS pixels and wider;
- a four-destination mobile bottom navigation below 1024 CSS pixels, with safe-area padding;
- a shared top bar for the active learning context and account controls;
- exact Home, Learn, Progress, and Help current states;
- consistent Help and nested-page return actions;
- disclosure controls with explicit names, `aria-expanded`, Escape-to-close, and focus return;
- a current learning-context indicator without combining institution authority;
- no role switch in an ordinary learner account;
- full `Bahasa Indonesia` and `English` labels with stable vector flags;
- light, blue-gray dark, and reduced-motion behavior inherited from UI-R01.

This phase does not add Course, Class, roster, teaching, content-release, assignment, notification, grading, or analytics behavior. Those remain with their owning B batches.

## Evidence-driven correction made after the pause

The paused WebKit failure was reproduced in a new disposable run. WebKit could move focus outside the disclosure container after Escape, so a key handler attached only to that container did not reliably receive the event. The final implementation listens at the document boundary, closes the open shell disclosure, resets its state and accessible name, and returns focus to its trigger.

The interaction contract follows the WAI-ARIA disclosure-navigation example, where Escape closes an open disclosure and returns focus to its controlling button. The event boundary is consistent with the browser `keydown` event model documented by MDN.

Primary references:

- https://www.w3.org/WAI/ARIA/apg/patterns/disclosure/
- https://www.w3.org/WAI/ARIA/apg/patterns/disclosure/examples/disclosure-navigation/
- https://developer.mozilla.org/en-US/docs/Web/API/Element/keydown_event

## Main implementation records

- `resources/views/partials/learner-shell.blade.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/layouts/guest.blade.php`
- `resources/views/components/language-switcher.blade.php`
- `resources/views/dashboard.blade.php`
- `resources/views/curriculum/dashboard.blade.php`
- `resources/views/partials/learner-nav.blade.php`
- `resources/css/app.css`
- `resources/js/app.js`
- `lang/en.json`
- `lang/id.json`
- `tests/Feature/LearnerShellTest.php`
- `tests/Feature/AccessibilityMarkupTest.php`
- `tests/Feature/LocaleSwitchTest.php`
- `tests/E2E/critical-path.spec.mjs`
- `tests/Node/theme-contrast.test.mjs`
- `scripts/quality/verify_learning_ui.py`

## Automated verification

- Targeted Laravel shell/locale/accessibility run: 12 tests, 788 assertions passed.
- Full Laravel regression with a test-process-only 512 MB memory ceiling: 364 tests, 5,670 assertions passed.
- Node regression: 55 tests passed.
- ESLint: passed with zero warnings.
- Production Vite build: passed.
- Blade view cache compilation: passed.
- Governance verification: all 43 findings mapped; 1,680 tracked files scanned; two reviewed allowlisted findings and zero unallowlisted findings.
- Traceability JSON parsed successfully.
- Impeccable detector: one warning for Plus Jakarta Sans. This is an accepted design-system choice recorded in `DESIGN.md` and the approved UI roadmap, not an unreviewed detector omission.

## Browser and visual verification

Fresh isolated visual run `ui-r02-audit-20260721-06` passed 31 checks. Every generated screenshot was manually inspected. The evidence is stored under:

`storage/framework/testing/e2e-ui-r02-audit-20260721-06/visual-review`

The review covered:

- 320, 390, 768, and 1280 CSS pixel widths;
- light and dark learner shells;
- reduced motion;
- exact current navigation;
- 44px shell targets;
- root overflow;
- learning-context and account panels;
- full language labels on desktop and mobile;
- disclosure state, Escape behavior, and focus return;
- keyboard focus visibility around fixed mobile bars;
- Help, module journey, vocabulary cards, practice, and step wrap-up screens;
- browser console and page-error health.

Fresh Playwright run `ui-r02-e2e-20260721-03` passed all 56 configured desktop/mobile Chromium and WebKit scenarios. These projects use Playwright's desktop Chrome, desktop Safari, Pixel 7, and iPhone 15 engine/device profiles; they are engine-level evidence, not branded-browser or physical-device certification.

The same run reached the configured Firefox project, but Playwright Firefox timed out during process launch before application navigation. Its SWGL renderer reported that it could not map the default framebuffer. One Firefox launch failed and the remaining 13 Firefox scenarios did not run. The failure evidence remains under:

`storage/framework/testing/e2e-ui-r02-e2e-20260721-03`

Direct branded Chrome, Edge, Safari, Opera, Firefox, and physical Windows, macOS, Android, and iOS validation remains an explicit UI-R08 task. No unsupported browser-conformance claim is made here.

## Data, security, privacy, and accessibility impact

- All browser checks used newly named disposable SQLite databases and generated test accounts.
- The development database, existing accounts, sessions, curriculum records, and demo accounts were not changed.
- No file or retained evidence was deleted.
- Server authorization remains authoritative; the shell exposes only routes the current learner may use.
- Ordinary learners do not receive privileged evidence or role-switch controls.
- UI-R02 changes presentation and navigation only. It does not weaken authentication, MFA, privacy-request, curriculum-release, or tenant authorization policy.
- Automated markup, keyboard, focus, contrast, target-size, and responsive checks pass. This is implementation evidence, not a claim of independent accessibility conformance.

## Rollback boundary

UI-R02 has no database migration or data rollback. A code rollback would restore the prior layout wiring, learner navigation, shell CSS/JavaScript, translations, and tests together. Do not roll back only the focus handler or accessible-state assertions because they encode the corrected cross-engine disclosure contract.

## Authority hashes at completion

- Thesis: `BA64B2EB69673E84FC83E21D4617CD111691EE6340FC2F4331948FE6AF5EC132`
- Learning material: `7F8A2C62DB6884498F7A1C2DE56E79E39609262944A685FE7EB97F702444CBB4`
- Master roadmap version 1.1.5: `89290177643E352613CB5C50D2686E4B4FD4A59B83B39C4F91D6D1A0D5A436A8`

## Next phase

Resume B06 at B06-B. Implement Class/roster operations and the approved institution-scoped Instructor capability against the completed B06-A domain foundation. Do not begin B06-C or another UI-R phase in the same checkpoint unless the owner explicitly changes the boundary.

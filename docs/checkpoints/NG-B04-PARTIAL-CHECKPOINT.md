# NG-B04 partial checkpoint — non-visual accessibility foundation

Status: technical subset implemented and verified locally on 2026-07-20; B04 remains in progress.

## Scope and decision boundary

This checkpoint advances only the non-visual technical work already authorized by the owner. It does not choose the pending mobile navigation, above-fold action, density, colour, focus appearance, long-page, table, language-switch, accommodation, or help-placement preferences. It does not claim WCAG 2.2 AA conformance or branded-browser, assistive-technology, physical-device, human-review, or production evidence.

No migration, seeder, bootstrap, authoritative-database write, account/institution change, file deletion, or production action was performed.

## Implemented

- Login, forgot-password, reset-password, password-confirmation, and email-verification pages now expose exactly one `main` landmark and one `h1` while preserving their existing visual classes.
- Login, forgot-password, reset-password, and password-confirmation errors now mark affected controls with conditional `aria-invalid="true"` and valid `aria-describedby` references. Password confirmation no longer emits a dangling reference when no error exists.
- Supervisor, Admin, and Superadmin sidebars now expose the visually active navigation destination programmatically through exactly one `aria-current="page"` value across all 16 tested sidebar destinations.
- Every Blade radio and checkbox now uses `shrink-0`, preventing flex layout from reducing its declared or user-agent control box. This includes login remembrance, invitation confirmation, draft-source selection, module publication, and canonical activity response/self-check controls. Associated labels remain the larger pointer targets where implemented.
- The accessibility regression suite now rejects audited responses that are not HTTP 200, dangling ARIA ID references, missing `main`/`h1` structure, missing invalid-field associations, absent current navigation state, and any shrinkable Blade radio or checkbox.
- A real normalized institution membership is created in the supervisor accessibility fixture. This corrected a prior false-positive where the old test parsed a Laravel 403 page as if it were the supervisor dashboard because it never asserted the response status.

## Verification

- Focused accessibility suite: 6 tests passed, 409 assertions, 0 failures.
- Full Laravel suite: 271 tests passed, 4,332 assertions, 0 failures in 107.18 seconds.
- Pint: passed for `tests/Feature/AccessibilityMarkupTest.php`.
- Vite 6.4.3 production build: 11 modules built successfully; the generated CSS contains the referenced utility set.
- Playwright against a newly migrated, isolated SQLite database and fresh process-only credentials: 36/36 journeys passed in 2.2 minutes across desktop Chromium, desktop WebKit, Pixel 7 emulation, and iPhone 15 emulation.
- A read-only rendered guest audit measured no root horizontal overflow at 320, 390, 768, or 1280 CSS pixel viewports. This is narrow-width evidence for the audited guest pages, not whole-site WCAG reflow evidence.
- `git diff --check` passed. Port 8010 was closed and no Firefox process remained after testing.

## Firefox evidence boundary

Playwright Firefox `firefox-1532` cannot reach Hospitrainity on this Windows host. A direct 30-second launch probe reproduced `RenderCompositorSWGL failed mapping default framebuffer`; the browser launch timed out before page creation or navigation. No Firefox application assertion is claimed. This is a host/browser graphics-startup limitation, not evidence that the site passes or fails in Firefox. A different supported host or branded Firefox session remains required.

## Remaining B04 work

- Record the owner's 11 B04 preference decisions and the named human accessibility reviewer plan.
- Implement the approved mobile administration drawer/navigation and above-fold action priorities.
- Implement approved target-size, visible-label, focus appearance, density, motion, language-switch, help, long-page, and responsive table/card policies.
- Add complete error-summary focus/link behavior and persistent visible labels without inventing visual preferences.
- Run automated accessibility rules plus manual keyboard, 200% text resize, 400% reflow, focus-not-obscured, target-spacing, contrast, forced-colour, reduced-motion, and accessible-authentication checks.
- Complete the approved branded-browser, screen-reader, and physical-device matrix and retain exact versioned evidence.
- Obtain independent human review before any WCAG conformance statement.

NG-P1-016 and NG-P2-033 therefore move only to `in_progress`. NG-P2-020 and NG-P2-021 remain open because no preference-dependent responsive navigation or long-page pattern has been selected or implemented.

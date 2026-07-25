# NG-B04 checkpoint — accessible responsive interaction

- Status: local technical implementation complete and installed on 2026-07-20; human, assistive-technology, physical-device, and branded-browser evidence remains open.
- Branch: `codex/ng-b04-accessible-responsive-shell`
- Parent checkpoint: `4e3f5da` (`feat: add authentication and security assurance baseline`)
- Authority sources: unchanged thesis and learning-material hashes recorded by B00.

## Owner decisions applied

The owner accepted the B04 recommendations: WCAG 2.2 AA as an engineering target without an unsupported conformance claim; compact mobile headers and modal drawers; role and institution context; one page-specific primary action above the fold; 24 CSS pixel minimum targets with 44 pixel primary/touch targets; long-page outline/next-action patterns; mobile cards for records and semantic tables for comparisons; decorative flags only beside visible ID/EN labels; medium density; persisted theme, motion, text-size, contrast, and no-audio preferences; always-on semantics and keyboard support; and consistent Help links.

## Implemented

- Authenticated and guest layouts expose a keyboard-visible skip link, stable main target, system-aware colour scheme, two-colour focus indicator, reduced-motion behavior, forced-colour support, and per-user display attributes.
- Display preferences persist `System`/`Light`/`Dark`, `System`/`Reduce`/`Full` motion, `Default`/`Large`/`Larger` text, stronger contrast, and no-audio preference without preventing browser zoom or removing required content.
- Admin and instructor mobile shells use a native modal `dialog`, show the active role/institution context, identify the current page, close on Escape, return focus to the trigger, and retain content-first source order. Desktop sidebars remain available at wider breakpoints.
- Public and learner navigation presents decorative Indonesian/British flags only beside visible `ID` and `EN` text and accessible language names.
- Invitations, user administration, curriculum drafts, learner progress, and supervisor progress have narrow-screen record cards; comparative data remains a semantic table. Progress keeps bounded filters and pagination.
- Long lesson sections provide an optional anchored outline and a direct activity/next-section action near the page title. Secondary source/lifecycle information remains in native disclosure widgets.
- Login and recovery forms preserve visible labels, error associations, error-summary focus, and accessible authentication semantics. Radio and checkbox controls cannot flex-shrink.
- The E2E harness now isolates Laravel caches inside each verified fresh run directory. Failed-run screenshots and traces were retained; no earlier evidence was overwritten.

## Authoritative database migration

Before migration, `database/database.sqlite` was copied to:

- `storage/app/backup-snapshots/b04-preferences-20260720-153321.sqlite`
- SHA-256: `b9130da66562ce261e8274e5e2454f20722d13e569df84040e12b62329587281`

Migration `2026_07_20_000016_add_display_preferences_to_users.php` ran as batch 15. Postflight: SQLite integrity `ok`; zero foreign-key violations; all three demo identities remain present and enabled; every existing user received system/default/false preference defaults. No user, institution, membership, progress, curriculum, invitation, security, or private record was deleted.

## Verification

- Focused B04: 15 tests / 761 assertions passed, including defaults, validation, persistence, migration rollback/reapply, skip targets, landmark and heading structure, ARIA references, current navigation, dialog labelling, and language parity.
- Cumulative Laravel: 323 tests / 5,144 assertions passed in 155.12 seconds.
- Static/JavaScript: PHPStan passed with no errors; ESLint passed with zero warnings; 50 Node tests passed.
- Governance/build: 43 findings mapped; 1,635 tracked files scanned with zero unallowlisted findings; Vite 6.4.3 production build passed.
- Browser regression: 44/44 critical journeys passed in one isolated run (`e2e-b04-20260720-1600`) across desktop Chromium, desktop WebKit, Pixel 7/mobile Chromium, and iPhone 15/mobile WebKit. The mobile shell check includes drawer context/current-page state, Escape/focus return, and root no-overflow at 390 CSS pixels.
- Two earlier isolated failed runs are retained. They identified and then verified fixes to test selectors that initially addressed intentionally hidden responsive representations.

## Evidence boundary and remaining gates

This checkpoint is not a WCAG conformance claim. Playwright engines and device emulation are not branded-browser, physical-device, touch-user, screen-reader, browser-zoom, or human evidence. Firefox still fails before navigation on this Windows host because its SWGL renderer cannot map a framebuffer; no Firefox application result is claimed.

External closure still requires:

1. Owner keyboard-only review and a qualified independent accessibility review.
2. 200% text resize and 400% reflow review with browser zoom, including focus-not-obscured and target-spacing checks.
3. NVDA/Firefox, NVDA/Chrome, NVDA/Edge, VoiceOver/Safari on macOS/iOS, and TalkBack/Chrome on Android.
4. Branded Chrome, Safari, Edge, Firefox, and Opera on the supported Windows, macOS, Android, and iOS matrix, with exact versions.
5. Physical Windows laptop, Mac, mid-range Android phone, and iPhone evidence.
6. Captions/transcripts and alternatives review for every production media asset as content is added.

## Rollback

The migration rollback/reapply test preserves users. Before users change preferences, the recorded backup can support a controlled local rejection. After preferences exist, forward-fix or export them before rollback. Never restore the database merely to reverse CSS or navigation changes.

## Exact next batch

B05 — onboarding, global search/navigation, consistent help, empty states, and role-specific next actions.

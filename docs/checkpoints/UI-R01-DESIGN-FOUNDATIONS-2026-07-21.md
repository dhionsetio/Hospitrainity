# UI-R01 design foundations checkpoint — 2026-07-21

State: COMPLETE, with one documented Firefox headless-runtime limitation  
Owner: Dhion Setio  
Next gate: answer all B06 owner questions before B06 implementation  
Production data mutation: none

## Scope and authority

UI-R01 implements the approved presentation foundation only. It does not add institution, course, class, assignment, grading, analytics, gamification, notification, AI, or production behavior.

Authority verified before implementation:

- thesis SHA-256: `604BF99BE839E8CD6A6B0991BE878DE759D514A736A5DF235BD0B961551AC7E8`;
- learning-material SHA-256: `7F8A2C62DB6884498F7A1C2DE56E79E39609262944A685FE7EB97F702444CBB4`;
- authority-drift record: `docs/checkpoints/NG-AUTHORITY-DRIFT-2026-07-21.md`;
- UI plan: `docs/HOSPITRAINITY_UI_REVAMP_ROADMAP.md`;
- product and design contracts: `PRODUCT.md` and `DESIGN.md`.

The complete current thesis and learning-material text were reread. The thesis change does not authorize B06 or later domain behavior. The learning-material file is unchanged.

## Implemented decisions

- Added primitive and semantic light/dark tokens for canvas, surfaces, text, controls, status, focus, spacing, type, geometry, depth, and motion.
- Kept the approved light blue palette and replaced near-black dark mode with layered blue-gray surfaces.
- Self-hosted Plus Jakarta Sans 2.7.1 under SIL Open Font License 1.1 with `font-display: swap` and system fallbacks.
- Added shared button, card-link, progress, current/selected, disabled, loading, pressed, focus-visible, and reduced-motion foundations.
- Kept a compatibility mapping for existing Tailwind indigo utilities while later UI-R phases migrate components to semantic classes.
- Raised the rendered learner-dashboard “Open module” action from 36px to the project’s 44px target.
- Updated deterministic theme tests and the stale E2E palette assertions to the approved blue-gray tokens.

## Files in this checkpoint

- `PRODUCT.md`
- `DESIGN.md`
- `docs/HOSPITRAINITY_UI_REVAMP_ROADMAP.md`
- `docs/checkpoints/NG-AUTHORITY-DRIFT-2026-07-21.md`
- `docs/checkpoints/UI-R01-DESIGN-FOUNDATIONS-2026-07-21.md`
- `docs/design/hospitrainity-ui-r-north-star.png`
- `docs/HOSPITRAINITY_NEXT_GENERATION_AUDIT_AND_UPDATE_ROADMAP.md`
- `docs/traceability/ng-traceability.json`
- `resources/css/app.css`
- `resources/fonts/plus-jakarta-sans/PlusJakartaSans-Variable.ttf`
- `resources/fonts/plus-jakarta-sans/OFL.txt`
- `resources/fonts/plus-jakarta-sans/README.md`
- `resources/views/curriculum/dashboard.blade.php`
- `tests/Node/theme-contrast.test.mjs`
- `tests/E2E/critical-path.spec.mjs`

Unrelated pre-existing worktree changes remain outside this checkpoint and were not edited for UI-R01.

## Data, security, and privacy impact

- No migration, production database write, retained-evidence change, or account-policy change.
- Browser checks used new disposable E2E database roots. Test-account display preferences and one disposable recovery code changed only inside those isolated roots.
- No test artifact was deleted because the owner requires permission before deletion.
- The font is served locally, so UI-R01 adds no third-party runtime font request.
- Server authorization, MFA, session, role, institution, and evidence policies remain unchanged.

## Accessibility evidence

- Documented normal text/action pairs meet at least 4.5:1.
- The light control boundary `#758F9F` measures 3.40:1 on white; the dark boundary measures 3.47:1 on the raised surface.
- Primary controls target 44px. The previously short learner “Open module” action rendered at 44px after correction.
- Keyboard focus inspection on a Help card measured a 3px semantic ring with offset and halo.
- Explicit reduced motion rendered `1e-05s` transition and animation durations with automatic scroll behavior.
- Both themes retained readable learner and privileged surfaces without horizontal root overflow in the inspected layouts.

This is engineering evidence, not an independent WCAG conformance claim. Physical devices, branded browser builds, assistive-technology users, screen readers, and independent auditors remain UI-R08 work.

## Automated verification

- `node --test tests/Node/theme-contrast.test.mjs`: 5/5 passed.
- `npm run lint:js`: passed with zero warnings.
- `npm run build`: passed; the production bundle includes the self-hosted variable font.
- `npm run test:js`: 55/55 passed.
- `php artisan test`: 348 tests and 5,536 assertions passed.
- `npm run governance:verify`: 43 findings mapped; zero unallowlisted secret findings.
- Impeccable layout scan: no finding. Its type scan reports Plus Jakarta Sans as a popularity warning; the font remains the owner-approved UI-R direction, so the warning is documented rather than silently changing the approved identity.
- E2E matrix after correcting stale palette expectations: 56/56 executed cases passed across desktop Chromium, desktop WebKit, mobile Chromium, and mobile WebKit.

Firefox did not execute an application test. The bundled Playwright Firefox process twice timed out during launch after 180 seconds and logged `RenderCompositorSWGL failed mapping default framebuffer, no dt`. The second run isolated Firefox and reproduced the same pre-page failure. Mozilla tracks the same graphics-startup symptom in [Bug 1832201](https://bugzilla.mozilla.org/show_bug.cgi?id=1832201) and related reports. No undocumented graphics override was added. Firefox remains an explicit UI-R08 environment-validation item.

## Rendered browser review

Manual screenshots and computed-style checks covered:

- guest login, dark desktop;
- learner dashboard, light and dark desktop;
- learner dashboard, light and dark 390px mobile;
- display preferences, light desktop;
- Help, light and dark desktop;
- System Admin dashboard, light and dark desktop;
- keyboard focus on a whole-card Help link;
- the explicit reduced-motion preference;
- browser error and warning consoles for learner and System Admin sessions.

The reviewed pages used the expected semantic canvas, surface, text, selected, focus, and action colors. Browser consoles contained no warning or error entry during the manual checks.

## Limitations and next action

UI-R01 establishes foundations. It does not yet replace the learner shell, dashboard structure, long lesson flow, exercises, or staff information architecture. Those changes belong to UI-R02 through UI-R07.

The work stops here. B06 is still the next domain batch and its 15 owner questions remain blocking. UI-R02 or B06 may continue only after the owner gives the next explicit instruction.

## Rollback

Revert only the UI-R01 source and documentation files listed above. Do not remove the vendored font, generated north-star image, or disposable E2E roots without the owner’s deletion approval. No database rollback is required.

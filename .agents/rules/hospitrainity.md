# Hospitrainity - Agent Rules (always follow)

## Source of truth
- The implementation spec is `docs/plans/warmup-area01-plan.md`. Read it in
  full before coding and follow it EXACTLY: file paths, class and method names,
  columns, route names, validation rules, and the tests it calls for.
- If the plan and this file ever seem to conflict, stop and ask me. Do not
  guess. Do not invent APIs, columns, routes, or config keys.

## Project guardrails (non-negotiable)
- This project is MIT licensed.
- Extend the existing native engine and existing patterns.
- Lesson completion means "viewed". Scoring is points plus percentage. Do not
  change these semantics.
- Do NOT change the enabled exercise-template counts in
  `CanonicalExerciseTemplateRegistry` (19 templates, 17 enabled).
- Do NOT weaken upload security. Reflection media is PRIVATE: quarantine then
  promote on a private disk, magic-byte inspection, and enqueue deletions via
  `PendingMediaDeletion`. Never expose data across institutions.

## Style and i18n
- Plain, beginner-friendly language in all UI copy.
- ASCII only in code and content: hyphen-minus only. NO em-dashes, en-dashes,
  or smart quotes anywhere.
- Every user-facing string must exist in BOTH `en` and `id`. Adding
  `lang/en/reflections.php` REQUIRES a matching `lang/id/reflections.php` with
  identical keys (LangParityTest and TranslationInventoryTest enforce this).
- Accessibility: one `<main>` and one `<h1>` per page, working skip-link,
  named buttons, alt text on images, `aria-*` on dialogs, `shrink-0` on
  radio/checkbox inputs (AccessibilityMarkupTest enforces this).

## Test contracts (must stay green - never weaken them)
- Put new PHP tests under `tests/Feature` or `tests/Unit`, extend
  `Tests\TestCase`, use `RefreshDatabase`, `actingAs` (auto-MFA),
  `grantInstitutionRole`, and `installCanonicalCurriculumFixture`.
- Account erasure has EXACTLY 5 steps. Do NOT add a 6th. Fold reflection
  cleanup into the existing `remove_personal_learning` step
  (PrivacyLifecycleTest asserts the count and idempotency).
- New learner routes must be gated `role:user` and added to the learner bucket
  in RoleRouteMatrixTest (403/redirect for supervisor, admin, superadmin).

## Verification loop (run these; do not report done until all pass)
- `npm run build`
- `npm run lint:js`
- `npm run test:js`
- `composer test`
- `npm run governance:verify`
(There is no `npm run test`.)

## Ask-first (pause and ask me before doing any of these)
- Any schema change beyond the two reflection tables the plan defines.
- Any new scheduled command, or the final 50 MB upload limit numbers.
- Any curriculum JSON or provenance change.
- Any Part B/C item the plan marks as a stretch/ask-first item.
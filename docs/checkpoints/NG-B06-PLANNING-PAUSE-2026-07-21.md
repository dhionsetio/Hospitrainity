# NG-B06 planning pause checkpoint

- Saved: 2026-07-21 (Asia/Jakarta)
- State: planning complete; B06 application implementation not started
- Owner approval: all B06 recommendations accepted, with a fully usable lecturer outcome required
- Safe resume target: B06-A domain, authorization, and shared-time foundation

## What is confirmed at this pause

1. The complete B06 approval is recorded in `docs/decisions/NG-B06-DECISIONS.md`.
2. A lecturer is represented in the interface as an **Instructor** working in an explicit Institution and Class context.
3. Full lecturer usability is a vertical product outcome across B06-B12: Classes/rosters, assignments, Course Builder and scoped release, grading, analytics, announcements/notifications, and schedules.
4. One Instructor workspace may compose those capabilities, but it may not collapse Instructor, Institution Admin, Content Author/Publisher, and System Admin into one unrestricted role.
5. Instructor content release is scoped to authorized Classes and the Institution's approved B08 workflow. Global canonical publication remains System Admin-only unless the owner later changes it explicitly.
6. UI work is interleaved with domain work. UI-R02 begins immediately after B06-A supplies a real, tested context contract; the remaining UI phases pair with B07-B12 instead of waiting until every backend batch is complete.

## Evidence reviewed for the plan

- Current institution storage uses UUID Institution IDs and immutable normalized keys.
- Institution Membership is the existing tenant relationship and already preserves revoked history instead of allowing direct deletion.
- Institution Role assignments already separate Instructor and Institution Admin from the global System Admin role.
- Work Context and Institution Context already select one active institution/role and fail closed when it is unavailable.
- Existing progress and attempt records already carry an Institution Membership link and a separate personal/institution learning-scope key.
- The current code has no Course, Course Revision, Class/Course Offering, Class Enrollment, Teaching Assignment, or shared Clock/TimeContext implementation. B06 must extend the existing model, not add another tenant or role system.

Official source basis is listed in the B06 decision record. The plan uses official Open edX and Moodle teaching-role/publication documentation and the OWASP Authorization Cheat Sheet; competitor behavior is not treated as product authority.

## Approved execution plan

### B06-A — domain, policy, and time foundation

1. Re-hash both authority DOCX files and compare them with the governing roadmap before editing code.
2. Inspect the current canonical curriculum identifiers and migration conventions; create an expand-only technical design that reuses Institution Membership and existing role assignments.
3. Add the approved Course, immutable Course Revision, Class/Course Offering, Enrollment, and Teaching Assignment domain records. Do not add Groups.
4. Add explicit enums/state transitions for the approved Class lifecycle.
5. Associate every Class with one immutable Course Revision and canonical package version; no active package may be mutated in place.
6. Add Institution and optional Class IANA timezone settings plus one injectable Clock and TimeContext. Store instants in UTC and test the approved precedence.
7. Add relationship-based query scopes and policies for Institution Admin, primary/co-instructor, and learner access. Direct-ID and cross-Institution/Class requests must fail closed.
8. Test fresh migration, rollback safety, historical-link protection, immutable identifiers/associations, timezone validation, DST gaps/folds, and frozen time in an isolated database.
9. Save the B06-A checkpoint before starting UI-R02.

### UI-R02 — real responsive context shell

Build the desktop rail, mobile bottom navigation, account/context bar, back/help placement, and active-state behavior against the B06-A Institution/Class contract. Verify keyboard, focus, touch, screen-reader naming, safe areas, light/dark themes, reduced motion, and 320/390/768/1280 widths in the browser.

### B06-B — instructor Class and roster operations

Add task-oriented Instructor and Institution Admin application services and screens for Class creation, assigned Classes, primary/co-instructors, invitations/codes/manual enrollment, learner status changes, and safe synthetic learner preview. Reuse existing invitation/join-code infrastructure. Preserve personal progress separation and historical Class relationships.

### B06-C — cutover and complete B06 verification

Add the Class selector to Work Context, migrate only exact verified legacy relationships, retain provenance, update institution-attributed progress queries to require the approved Class scope where applicable, run the full test/browser matrix, and save the complete B06 checkpoint. Do not delete legacy fields or records during the expand-first stage.

### Subsequent lecturer vertical slices

1. B07 + UI-R03: assignments/directed learning and learner dashboard.
2. B08 + UI-R07A: Course Builder, authoring, validation, review, preview, and scoped Class release.
3. UI-R04: module/lesson/local-step learning flow.
4. B09 + UI-R05: submissions, grading, exercises, and feedback.
5. B10 + UI-R06: actionable progress/analytics and supporting learner-service pages.
6. B11-B12 + UI-R07B: announcements, notifications, schedules, Institution Admin, and System Admin completion.
7. UI-R08: cross-role validation and handoff.

Every later B questionnaire remains mandatory before its product behavior is implemented. This plan fixes sequence and scope; it does not pre-answer B07-B12 details.

## Verification state at pause

- Learning-material DOCX SHA-256 rechecked: `7F8A2C62DB6884498F7A1C2DE56E79E39609262944A685FE7EB97F702444CBB4` (matches the roadmap).
- Thesis DOCX re-hash: **not completed at this pause because another process held the file open**. Last verified roadmap value: `604BF99BE839E8CD6A6B0991BE878DE759D514A736A5DF235BD0B961551AC7E8`. Rechecking it after the file is unlocked is the first resume gate.
- No migration, model, policy, controller, route, view, seed, application test, database, or live-server state was changed for B06.
- No file or record was deleted.
- Documentation-only checks are recorded below; application tests are intentionally deferred because implementation has not begun.

## Exact safe resume procedure

1. Confirm the thesis DOCX is no longer locked, calculate both authority hashes, and stop if either differs from the roadmap.
2. Re-read this checkpoint, `NG-B06-DECISIONS.md`, the B05 architecture checkpoint, PRODUCT.md, DESIGN.md, and the five named skill instructions.
3. Re-check `git status --short` and preserve every unrelated user change listed there.
4. Confirm the user's existing localhost process and main database are not selected as mutation targets.
5. Create a new disposable database/run identifier and establish the current baseline before writing the B06-A migration or domain code.
6. Implement only B06-A, validate it, and stop at its checkpoint. Do not silently start UI-R02 in the same checkpoint.

## Known production gates

Production topology, mail/push delivery, queue/scheduler workers, secrets, backup restoration, monitoring, legal/privacy review, accessibility review, academic/content review, independent security review, and physical target-device/browser evidence remain unverified. Planning or local automated tests must not be represented as production readiness.

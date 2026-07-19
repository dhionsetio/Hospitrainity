# ADM-5 Progress Administration Privacy Contract

- Date: 2026-07-19
- Status: Implemented under accepted ADR-002 authority
- Governing decisions: `ADR-002-administration-authority-and-data-boundaries.md` and `ADM-0-administration-capability-matrix.md`

## Authorized views

| Actor | Authorized progress view |
|---|---|
| Learner | Own learner experience only; ADM-5 adds no administration route |
| Supervisor | Identity-level metadata/detail only for a learner whose stored, non-empty institution exactly equals the supervisor's stored institution |
| Admin | Global aggregate/de-identified totals only; no learner rows, email, institution slice, or direct learner detail |
| Superadmin | Global identity-level metadata list and learner detail across institutions |

Role middleware is necessary but not sufficient. Every identity/detail request also passes a model policy. A non-learner target, empty supervisor institution, or cross-institution/direct-ID request is denied as not found. The client cannot broaden the query scope.

## Metadata allowlist

ADM-5 may read and render only:

- learner account identity where the actor has identity-level authority: name, email, and institution;
- canonical package name/version and retained lifecycle availability;
- module/chapter, section, and activity code/title from the selected retained canonical package;
- the exact highest state `viewed`, `started`, `attempted`, `self_checked`, or `completed` (plus `not_started` for a retained activity without a progress row);
- attempt count, state timestamps, last activity, completion time, active-version completion, and the legacy migration label;
- aggregate counts across learners, progress rows, completions, and attempts.

Active-version completion intersects progress rows with retained, published activities in the active package. Unknown or historical codes cannot increase the denominator/numerator, and progress percentages cannot be inferred from an unavailable package definition. A stale historical version renders retained metadata codes with an explicit unavailable-definition label; it never invents module, section, or activity titles.

## Explicitly excluded data

The service does not query or eager-load `curriculum_responses`. It does not expose raw or normalized open responses, prompt-level response JSON, correctness details, confidence answers/ratings, recordings, audio, media paths, idempotency/HMAC values, session data, passwords, or audit secrets. An administrative role alone never authorizes these fields.

Any future sensitive-response view requires a separate owner/privacy decision, purpose and necessity, explicit capability, access audit, field/row bounds, retention/deletion rules, and dedicated tests. ADM-5 does not pre-approve it.

## List, filter, and query contract

- Identity list pages are fixed at 20 learners and preserve the validated query string in pagination links.
- Allowed filters are bounded learner search, exact institution, content version, canonical module code, exact highest state, and recent activity within 7/30/90 days.
- Recent activity uses the progress metadata row's `updated_at`, which changes with recorded state transitions; it is not inferred from private response content.
- Related progress, attempt, and completion reads are batched for the displayed learner IDs. Query count is constant for a one-row and a full 20-row page.
- The administration view reads current rows on every request rather than introducing a separate progress cache, so a state transition is visible on the next request.

## Export decision

CSV export is not implemented. There is no route or controller, and the interface reports it as unavailable. Enabling export requires a separate decision covering identity/columns, authorization, purpose, spreadsheet-formula neutralization, row/rate limits, audit, retention, delivery/storage, and deletion.

## Reproducible verification

Run:

```powershell
& 'C:\php\php.exe' artisan test tests/Feature/ProgressAdministrationTest.php --display-all-issues --fail-on-all-issues --disallow-test-output
& 'C:\php\php.exe' artisan test --display-all-issues --fail-on-all-issues --disallow-test-output
& 'C:\Program Files\nodejs\npm.cmd' run lint:js
& 'C:\Program Files\nodejs\npm.cmd' run test:js
& 'C:\Program Files\nodejs\npm.cmd' run build
```

The focused suite covers role and verification boundaries, cross-institution/direct-ID denial, exact-state filtering, metadata-only SQL/output, stale and unknown versions, disabled export, fixed pagination/query count, fresh reads, invalid active codes, and deleted-user cascades. The full accessibility/role/route/language/security suites cover the shared surface.

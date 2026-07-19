# Hospitrainity Full Audit and AI Update Roadmap

**Audit date:** 16 July 2026  
**Audited snapshot:** local Laravel application, SQLite data, Blade/JavaScript UI, tests/CI, curriculum package, and generated standalone site  
**Purpose:** authoritative, evidence-based work queue for a human or AI updating this snapshot  
**Code-change status:** no application fixes were applied during this audit; this document is the only new project file

## 1. Executive verdict

The application has a useful, recognizable product structure and several recent defensive improvements, but it is **not ready for a public deployment**. The immediate blockers are:

1. The PHP lockfile contains current security advisories, including high-severity issues in request/email handling code used by this application.
2. Both base layouts contain malformed Blade/HTML. On authenticated learner pages this produces a literal, invalid CSRF meta value, so progress POST requests fail.
3. All five paginated superadmin list pages return HTTP 500 because they request a view that is not present.
4. Progress clients redirect without awaiting persistence and never reject HTTP 4xx/5xx responses.
5. Exercise content is interpolated into `innerHTML`, creating a stored DOM-XSS boundary.

Important next-tier issues include unscoped nested content routes, direct access to unpublished curriculum, destructive/invalid admin form submissions, non-atomic database/file operations, a stuck listening exercise, ambiguous spelling audio data, self-selected institution membership, and incomplete automated/browser coverage.

## 2. What the website currently provides

This feature inventory is included so an implementing AI understands what must be preserved.

- Public landing page with English/Indonesian locale switching, marketing sections, registration, login, password reset, and email verification.
- Role-specific login fallback redirects for learner, supervisor, and superadmin accounts; guest, intended, and email-verification redirects are not yet consistently role-aware (HSP-P2-023).
- Learner flow: published-module dashboard, module/lesson navigation, vocabulary practice, text/image/audio/video materials, 14 exercise renderer types, and polymorphic completion tracking.
- Supervisor flow: institution-scoped learner list with overall progress.
- Superadmin flow: summary dashboard and modal CRUD interfaces for modules, lessons, vocabulary, materials, and exercises.
- SQLite-backed local configuration with database sessions, cache, queues, and seeded demo accounts/content.
- A separate canonical curriculum package and deterministic read-only standalone site. This is not currently integrated with the Laravel learner database.
- PHPUnit feature/unit tests and a GitHub Actions workflow.

## 3. Audit method and reproducible baseline

The audit combined source inspection, runtime HTTP probes, database inspection, framework tests, syntax/style checks, dependency advisory services, and official documentation research.

### 3.1 Baseline results

| Check | Result |
|---|---|
| Laravel/PHP | Laravel 12.19.3; PHP 8.2.32 |
| Routes | 43 registered routes |
| Public runtime | `/`, `/login`, `/register`, `/forgot-password`, `/up` returned 200 |
| Authenticated runtime | learner, supervisor, and superadmin dashboards returned 200 |
| Admin list runtime | modules, lessons, vocabularies, materials, and exercises each returned 500 |
| PHPUnit | 39 passed, 1 failed, 4 risky, 138 assertions |
| PHP syntax | all scanned PHP files passed `php -l` |
| JavaScript syntax | all scanned first-party JavaScript passed `node --check` |
| Laravel Pint | failed: 33 files with style/unused-import issues among 86 files |
| npm advisory audit | 0 known vulnerabilities across 164 dependencies |
| Composer advisory audit | failed: 24 advisory records across 13 packages; one Laravel issue is duplicated by two advisory sources |
| Composer metadata | `composer.json` is valid |
| SQLite | `integrity_check = ok`; 0 foreign-key violations; 13 migrations applied |
| Current rows | 3 users, 8 modules, 24 lessons, 82 vocabulary groups, 244 vocabulary items, 24 materials/items, 36 exercises, 0 completions |
| Public upload link | `public/storage` does not exist |
| UTF-8 scan | no common mojibake markers found; do not introduce an encoding “fix” |
| Standalone | SHA-256 `471aedc0725981ea4e9b8592e2226626c716eb996f2e28255c8c091d0cb115bb`, matching the phase-14 approved artifact |

### 3.2 Current PHP advisory inventory

Run date: 16 July 2026. These results are time-sensitive and must be rerun when work starts.

| Locked package | Locked version | Advisory records | Highest reported severity | Reachability note |
|---|---:|---:|---|---|
| `laravel/framework` | 12.19.3 | 3 | High | Email validation and signed URLs are used here; one underlying email issue appears twice |
| `symfony/http-foundation` | 7.3.0 | 2 | High | Core request handling; web-reachable |
| `symfony/mime` | 7.3.0 | 2 | High | Verification/reset mail construction |
| `phpunit/phpunit` | 11.5.24 | 1 | High | Development/test only if production uses `--no-dev` |
| `guzzlehttp/guzzle` | 7.9.3 | 2 | Medium | No direct outbound client use found, but still update |
| `guzzlehttp/psr7` | 2.7.1 | 3 | Medium | Transitive HTTP dependency |
| `league/commonmark` | 2.7.0 | 2 | Medium | No first-party Markdown rendering call found |
| `psy/psysh` | 0.12.8 | 1 | Medium | Local/Tinker dependency |
| `symfony/mailer` | 7.3.0 | 1 | Medium | Verification/reset mail path |
| `symfony/process` | 7.3.0 | 1 | Medium | CLI/build path; Windows advisory present |
| `symfony/routing` | 7.3.0 | 2 | Medium | Core routing |
| `symfony/polyfill-intl-idn` | 1.32.0 | 1 | Low | Transitive |
| `symfony/yaml` | 7.3.0 | 3 | Low | Primarily configuration/tooling in this project |

Do not suppress these advisories merely to make CI green. Update to patched, mutually compatible versions, document any truly unreachable residual advisory, and require `composer audit` to return zero unignored advisories.

## 4. Review of `Hospitrainity_updates_and_fixes.txt`

| Attached item | Verdict | Evidence and correction |
|---|---|---|
| 1. Superadmin pagination crash | **Confirmed** | All five named templates call `links('vendor.pagination.tailwind')`; the full suite and live pages reproduce the missing-view exception. Default `links()` is appropriate unless a custom published view is intentionally maintained. |
| 2. Raw text on login/register | **Confirmed, broader** | The malformed `lang`, CSRF meta tag, and pseudo-comments exist in both `layouts/guest.blade.php` and `layouts/app.blade.php`, not only the guest layout. The authenticated-layout CSRF defect breaks progress requests. |
| 3. Page titles | **Confirmed, broader** | `Document` is also used by `dashboard.blade.php` and `module.blade.php`, in addition to login/register. |
| 4. StayReady rename | **Confirmed, broader** | Visible split markup (`Stay<span>Ready</span>`) remains in login, register, learner navigation, superadmin sidebar, and supervisor dashboard. A literal `StayReady` search alone misses these split occurrences. |
| 5. Windows/Pail workflow | **Confirmed operational issue** | `pdo_sqlite` and `sqlite3` are loaded, but `pcntl` is absent from native Windows PHP. The Composer `dev` script always starts Pail, so provide a Windows-safe script or make Pail optional. Port 5173 remains only Vite’s asset server. |
| 6. SQLite configuration | **Currently healthy** | SQLite is configured, its file exists, all 13 migrations are applied, integrity is `ok`, and no FK violations exist. Keep setup instructions and add a non-destructive health check; do not run `migrate:fresh` on the working database. |
| 7. Registration/email verification | **Flow confirmed; additional risks found** | Registration hashes passwords, fixes role to the DB default, emits `Registered`, logs in, and redirects to verification. Local mail uses `log`. Add throttling, generic reset responses, lowercase email handling, and an institution enrollment decision. |
| 8. Demo accounts | **Confirmed** | Supervisor is Hotel A and learner is Hotel B, so the seeded supervisor correctly sees no seeded learner. Superadmin belongs to Hospitrainity HQ. Tests should create a same-institution learner rather than changing scoping. |
| 9. Add admin regression test | **Already implemented, currently failing** | `tests/Feature/AdminRouteSmokeTest.php` already covers all six admin screens and role/guest access. Fix the application and keep/strengthen this test; do not add a redundant duplicate. |
| 10. Post-fix checklist | **Valid but incomplete** | Retain it, then add dependency audits, nested-resource 404s, unpublished-content denial, progress persistence failure handling, media CRUD, accessibility, and browser tests. |
| 11. Disable debug in production | **Correct production requirement** | Local `APP_DEBUG=true` is normal and is not itself a bug. Production must use `APP_ENV=production`, `APP_DEBUG=false`, HTTPS-only cookies, and deployment validation. |

## 5. Complete finding register

Priorities: **P0** = release blocker/actively broken or exposed; **P1** = high-impact correctness, authorization, or data-integrity defect; **P2** = material functional, performance, UX, or maintainability issue; **P3** = cleanup/hardening.

### 5.1 P0 — release blockers

#### HSP-P0-001 — Vulnerable PHP lockfile

- **Evidence:** `composer.lock` versions and the 16 July 2026 `composer audit` results in section 3.2.
- **Impact:** request authorization, email validation/mailing, signed verification URLs, and development tooling match known affected ranges.
- **Required outcome:** update the lockfile within a reviewable dependency change; at minimum use patched Laravel 12 releases (the audit reports fixes beginning at 12.60.0/12.61.1 for the current Laravel issues) and patched compatible transitive packages. Run the full suite, mail/auth tests, route tests, and `composer audit`. Treat a Laravel 13 major upgrade as a separate change, not an incidental fix.

#### HSP-P0-002 — Both layouts emit malformed Blade/HTML

- **Evidence:** `resources/views/layouts/app.blade.php:2,7,10,14,17-19` and `resources/views/layouts/guest.blade.php:2,7,10,14`.
- **Impact:** invalid document language, visible pseudo-comments, and a literal CSRF meta value (` csrf_token() `). This damages accessibility and makes learner progress POSTs return 419.
- **Required outcome:** restore `{{ ... }}` expressions and valid HTML comments in both files; add rendered-response assertions for the real locale, a non-literal CSRF token, and absence of pseudo-comment text.

#### HSP-P0-003 — Progress persistence is dropped or silently treated as successful

- **Evidence:** vocabulary `practice.blade.php:218-255`, material `material.blade.php:226-265`, and exercise engine `resources/js/exercises/index.js:686-700,752-760`.
- **Impact:** clients call an async function and immediately navigate away; navigation may abort the request. `fetch()` resolves on HTTP 419/422/500, but the code never checks `response.ok`, so error responses appear successful.
- **Required outcome:** centralize one progress client, await it before navigation, check `response.ok`, show a retry/error state, prevent double submission, and only redirect after confirmed success or an explicit user choice to leave without saving.

#### HSP-P0-004 — All paginated superadmin indexes return 500

- **Evidence:** modules line 80, lessons line 70, vocabularies line 72, materials line 72, exercises line 102; failing `AdminRouteSmokeTest` and live 500 probes.
- **Impact:** all content management list pages are unusable.
- **Required outcome:** use the framework default `$paginator->links()` or intentionally publish/maintain a custom view. Keep the existing regression test and assert headings/pagination HTML for a multi-page fixture.

#### HSP-P0-005 — Exercise data crosses unsafe `innerHTML` sinks

- **Evidence:** numerous interpolations in `resources/js/exercises/index.js`, including lines 167, 175, 189, 193, 201-209, 232-240, 250-256, 280-294, 319-324, 345-359, 370-381, 394-402, 418-426, 482-499, 582, and 619.
- **Impact:** admin-authored database content can become executable HTML/attributes in learner sessions (stored DOM XSS) and can also break the exercise DOM with quotes/markup.
- **Required outcome:** build elements with `createElement`, `textContent`, and safe property assignment. If rich HTML becomes a real requirement, define a narrow schema and sanitize with a maintained allowlist sanitizer. Add malicious-string regression fixtures.

### 5.2 P1 — high-impact defects

#### HSP-P1-001 — Nested vocabulary/material bindings are not scoped to their lesson

- **Evidence:** routes `web.php:94-96` and `LessonController.php:29-42` never verify the relationship. Runtime reproduction: lesson 1 paired with vocabulary 8 (lesson 2) and material 2 (lesson 2) both returned 200.
- **Required outcome:** use Laravel scoped bindings where relationship naming permits it, or load through `$lesson->vocabularies()` / `$lesson->materials()` and return 404 on mismatch. Add both valid-pair and cross-lesson tests.

#### HSP-P1-002 — Unpublished modules remain directly reachable

- **Evidence:** only `DashboardController.php:26` filters `is_published`; `ModuleController::show`, lesson routes, practice routes, and progress storage do not enforce a published ancestor.
- **Required outcome:** introduce a reusable published scope/authorization rule for every learner content route and for completion writes. Superadmins may preview drafts only through an explicit preview policy/route.

#### HSP-P1-003 — Progress endpoint validates existence, not curriculum authorization

- **Evidence:** `ProgressController.php:18-58` allowlists types and checks global IDs, but not whether IDs belong to a reachable published lesson. It has no item-count cap and writes one `updateOrCreate` per ID without a transaction.
- **Required outcome:** validate the user role and published ancestry, cap and de-duplicate request size, use a transaction plus bulk upsert/chunking, and reject mixed/out-of-scope sets atomically. Preserve the existing allowlist and unknown-ID protections.

#### HSP-P1-004 — “Complete all” can be earned without completing the activity

- **Evidence:** vocabulary/material/exercise side navigation permits jumping to the last item; final navigation posts every ID. `showFeedback(false)` also reveals Next at exercise-engine lines 97-108.
- **Required outcome:** first decide whether progress means “viewed” or “mastered.” Then record per-item events server-side and require the relevant evidence. Do not infer mastery from reaching the final slide.

#### HSP-P1-005 — Material edit omits a server-required `type`

- **Evidence:** the edit select is disabled at `superadmin/materials/index.blade.php:96`; disabled controls are not submitted, while `MaterialController.php:72` requires `type`.
- **Impact:** ordinary material edits return validation errors.
- **Required outcome:** mirror the exercise update approach: derive immutable type from the bound model or submit a trusted hidden value; add full form-driven update tests.

#### HSP-P1-006 — Exercise admin editor and 14-type engine are incompatible

- **Evidence:** the engine/request registry supports 14 types (`ExerciseRequest.php:23-38`), while the editor exposes six (`exercises/index.blade.php:133-140`) and its `x-show` sections remain submit-enabled. Comma-separated inputs named as array fields submit strings (`:156-157`), duplicate `content[correct_answer]` names can overwrite one another, and the superadmin sidebar comments out the only navigation link to this editor (`superadmin/sidebar.blade.php:40-45`).
- **Required outcome:** generate exactly one type-specific editor with `x-if` or disabled inactive controls; submit actual nested arrays; support all published types or explicitly make unsupported types read-only; restore a policy-protected navigation link. Add browser-level create/edit tests per type.

#### HSP-P1-007 — Listening task traps the learner

- **Evidence:** `renderListeningTask` disables Check and options but never calls `showFeedback` or displays Next (`index.js:197-220`).
- **Required outcome:** on selection, show correct/incorrect feedback and a valid retry/Next transition; cover both paths in JavaScript/browser tests.

#### HSP-P1-008 — Spelling “audio_url” has two incompatible meanings

- **Evidence:** renderer line 163 passes `audio_url` to speech synthesis. Seed data includes both `/audio/reservation.mp3` and literal phrases such as `nine thirty`.
- **Required outcome:** replace the ambiguous field with an explicit discriminated schema such as `prompt_text` and optional `audio_url`. Use `Audio` for URLs and speech synthesis only for text. Validate URL existence/type and supply fallbacks.

#### HSP-P1-009 — Vocabulary update trusts unscoped IDs and client media paths

- **Evidence:** `VocabularyController.php:52-59` does not validate item IDs; line 85 calls `find(...)->update()` without a null guard; lines 74-80 trust `existing_media_url` from the request.
- **Impact:** foreign/nonexistent IDs can throw 500. File deletion can occur before a DB rollback, leaving restored rows pointing to deleted media.
- **Required outcome:** scope IDs to the bound vocabulary, read old media paths only from the database, reject foreign IDs, and move file replacement/deletion to a safe lifecycle with compensating cleanup.

#### HSP-P1-010 — Material update accepts globally valid foreign item IDs

- **Evidence:** `MaterialController.php:74` uses global `exists:material_items,id`; line 94 then searches only the current relationship. A foreign ID can cause current items to be deleted/recreated unexpectedly.
- **Required outcome:** use a scoped `Rule::exists`/relationship lookup and reject IDs not owned by the material. Add an atomic foreign-ID regression test.

#### HSP-P1-011 — File storage/deletion and database transactions are not atomic

- **Evidence:** vocabulary/material controllers store and delete files inside DB transactions, while filesystem operations do not roll back. Material store/update also use inconsistent directories (`material_media` vs `materials`).
- **Required outcome:** standardize paths; stage new files; commit DB state; remove replaced files after commit; delete newly staged files on exception. For destructive deletes, preserve recoverability and test injected failures.

#### HSP-P1-012 — Public uploads are not web-accessible in this snapshot

- **Evidence:** controllers return `/storage/...`; `public/storage` is absent.
- **Required outcome:** document/run `php artisan storage:link` per environment and add a deployment health check that uploads a fake file through `Storage::fake` in tests and verifies URL/path behavior. Do not commit a machine-specific link.

#### HSP-P1-013 — Password reset leaks account existence and lacks throttling

- **Evidence:** `PasswordResetController.php:30-34` returns distinct invalid-user errors; forgot/reset POST routes at `web.php:49-51` have no throttle.
- **Required outcome:** return a generic outward response for reset-link requests, rate-limit by normalized email plus IP, and test equal outward behavior for existing/non-existing accounts. Keep internal logging/metrics separate.

#### HSP-P1-014 — Anyone can self-enroll into any listed institution

- **Evidence:** registration exposes distinct `users.instansi` values and accepts any existing value (`RegisterController.php:26-31,42-45`). The supervisor trusts that string for access scope (`SpvDashboardController.php:18-21`).
- **Impact:** an unauthenticated person can join an institution and become visible in that institution’s supervisor dataset. Institution names are also publicly enumerated.
- **Required outcome:** product owner must choose invitation, verified-domain, join-code, or approval-based enrollment. Normalize an `institutions` table/FK and authorize membership changes. Do not silently preserve public self-selection without documenting the risk.

#### HSP-P1-015 — “Remember me” is always enabled and the checkbox is ignored

- **Evidence:** login sends hidden `remember=true` at line 21; the visible checkbox is named `remember-me` at line 35; controller reads `remember` at line 36.
- **Required outcome:** remove the hidden field, name the checkbox `remember`, and test both persistent and session-only login behavior.

#### HSP-P1-016 — Lesson slug uniqueness is checked before normalization

- **Evidence:** `LessonController.php:63-69,80-86` validates raw text then applies `Str::slug`. Distinct inputs can normalize to the same unique DB slug and produce a 500.
- **Required outcome:** normalize before validation and use a collision-safe strategy comparable to modules, backed by the DB unique index and collision tests.

#### HSP-P1-017 — Deleted content can leave completion/cache inconsistencies

- **Evidence:** polymorphic targets cannot have conventional target FKs; `Completable` defines no delete cleanup. Overall-progress cache is invalidated only when a user stores progress, not when curriculum content/publish state changes.
- **Required outcome:** delete related completions via model events/service operations, invalidate affected progress caches when curriculum totals change, and test deletion/publish/unpublish scenarios. Existing DB currently has zero orphan completions.

### 5.3 P2 — functional, performance, UX, and hardening issues

#### HSP-P2-001 — Dashboard lesson counts are blank

- **Evidence:** view reads `$module->lessons_count` at `dashboard.blade.php:34`; learner query at `DashboardController.php:26-28` does not call `withCount('lessons')`.
- **Required outcome:** add a count to the existing query or use the loaded collection count; assert the rendered number.

#### HSP-P2-002 — Lesson detail performs avoidable lazy/N+1 queries

- **Evidence:** controller loads only vocabularies (`LessonController.php:20-21`), while the view accesses module, vocabulary items, materials/items, and exercises.
- **Required outcome:** eager-load all view relations in a bounded query set and add a query-count regression test.

#### HSP-P2-003 — Curriculum `order` columns are largely ignored

- **Evidence:** modules/lessons/vocabularies/materials/items have `order`, but most relationships and learner queries have no `orderBy`; admin forms generally cannot edit order. New records default to zero.
- **Required outcome:** define deterministic relationship ordering and admin ordering controls, with unique/stable tie-breaking by ID. Preserve seeded order.

#### HSP-P2-004 — Supervisor progress scales by learner, not just curriculum size

- **Evidence:** `SpvDashboardController.php:18-26` loops users and `User::computeOverallProgress()` reloads the complete curriculum and completions per cold-cache user.
- **Required outcome:** batch aggregate progress for the displayed learner set, paginate learners, and add query-count/load tests for 1, 20, and 100 learners. Keep caching only after correct invalidation exists.

#### HSP-P2-005 — Common FK/filter columns lack explicit indexes

- **Evidence:** SQLite index inspection found no indexes on the child/order tables beyond module/lesson slugs and completion indexes. Supervisor filters by `(role, instansi)`.
- **Required outcome:** add measured indexes such as users `(role, institution_id)`, and child `(parent_id, order, id)` indexes. Verify query plans; do not add speculative duplicates.

#### HSP-P2-006 — Material validation is not conditional on type

- **Evidence:** type accepts any string; image/audio files and video URLs are nullable even when the UI labels them required (`MaterialController.php:31-40,70-80`; material form lines 95-142).
- **Required outcome:** use a backed enum/allowlist plus `required_if`/prohibited rules, restrict remote URLs to HTTPS and approved video hosts, and test each type’s valid/invalid payload.

#### HSP-P2-007 — Material renderer has contradictory/dead paths

- **Evidence:** `playMedia` references undefined `isPlaying` (line 101); image creation is duplicated (139-150); text audio uploads are never rendered; `Gambar dengan Audio` is unreachable from admin options; audio MIME is hardcoded to `audio/mpeg` despite WAV acceptance; external video link lacks explicit `rel="noopener noreferrer"`; iframe lacks a title.
- **Required outcome:** create a renderer registry aligned with the server enum, remove dead code, use real MIME metadata, render optional text audio, harden external links/iframes, and add media-error UI/tests.

#### HSP-P2-008 — Vocabulary audio fallback is disabled

- **Evidence:** normal items without media create a speak button but never append/bind it (`practice.blade.php:153-186`). TTS has no `onerror`, so `isPlaying` can remain stuck.
- **Required outcome:** provide an accessible TTS fallback or remove the dead button; handle play/TTS promises and error/end states.

#### HSP-P2-009 — Progress errors are invisible to users

- **Evidence:** all three clients only log caught network errors; HTTP failures are not caught and navigation proceeds.
- **Required outcome:** show an `aria-live` error with Retry/Leave choices and log structured diagnostics without exposing sensitive data.

#### HSP-P2-010 — Critical UI behavior depends on floating/external CDNs

- **Evidence:** Alpine is loaded as floating `3.x.x`; Font Awesome and Google Fonts are remote; authenticated avatars call `i.pravatar.cc`.
- **Impact:** admin modals can fail offline or change without a lockfile; third parties receive learner network metadata; CSP is harder.
- **Required outcome:** install/bundle pinned Alpine with Vite, self-host critical fonts/icons/assets where feasible, and remove third-party authenticated avatars. Keep external media behind an intentional policy.

#### HSP-P2-011 — Security response headers are absent

- **Evidence:** live responses had no CSP, HSTS, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, X-Frame-Options/frame-ancestors.
- **Required outcome:** after removing unsafe inline/CDN dependencies, deploy a tested CSP (start report-only), `nosniff`, referrer policy, permissions policy, and frame-ancestors. Set HSTS only on a fully HTTPS production domain.

#### HSP-P2-012 — Email addresses are not canonicalized

- **Evidence:** registration/login validate but do not trim/lowercase before storage/query. SQLite’s ordinary unique index can distinguish case variants.
- **Required outcome:** normalize consistently before validation/authentication/storage and enforce database-level canonical uniqueness appropriate to the production DB. Add case-variant tests and a safe data migration.

#### HSP-P2-013 — Role isolation and policy ownership are implicit

- **Evidence:** learner route group requires auth+verified but not `role:user`; supervisor/superadmin can directly access learner/progress routes. Admin relies on route middleware; no model policies exist.
- **Required outcome:** decide whether elevated roles may also learn. Encode the decision in middleware/policies and add a complete role-route matrix. Use policies as defense in depth for content mutation/preview.

#### HSP-P2-014 — Four pages use the title `Document`

- **Evidence:** login, register, dashboard, and module templates line 3.
- **Required outcome:** localized, descriptive titles, including module names where appropriate; add response assertions.

#### HSP-P2-015 — StayReady branding remains across every role

- **Evidence:** split visible markup in login/register line 9, learner navigation line 4, superadmin sidebar line 3, and supervisor dashboard line 19.
- **Required outcome:** replace visible branding with Hospitrainity, including accessible names and metadata. Do not bulk-replace route names, DB identifiers, or historical migration evidence.

#### HSP-P2-016 — Broken links and unsupported marketing claims

- **Evidence:** auth/learner logos, profile/dashboard, and social icons use `href="#"`; the landing page claims thousands of learners, AI tutors, instant pronunciation/grammar feedback, community, customized study plans, free trial, and presents static testimonials, none of which is implemented/evidenced in this snapshot.
- **Required outcome:** wire real destinations or render non-links; add a returning-user Login action; replace unverified claims/testimonials with accurate copy or evidence-backed content; update stale 2024 copyright.

#### HSP-P2-017 — Two curriculum sources disagree

- **Evidence:** Laravel DB serves 8 modules/24 lessons/36 exercises. The approved standalone package contains 7 chapters/85 sections/24 activities/102 prompts and is a checksum-verified deterministic artifact.
- **Required outcome:** choose the canonical source of truth and build a versioned importer/projection into Laravel. Never manually edit generated standalone output or overwrite the approved curriculum with legacy seed data.

#### HSP-P2-018 — Modal/menu/progress interactions lack accessible semantics

- **Evidence:** admin modals lack `role="dialog"`, `aria-modal`, labelled title, initial focus, focus trap, Escape close, and focus return. Mobile/profile menus lack expanded/control state and Escape behavior. Progress bars have no progressbar semantics; feedback lacks `aria-live`; several icon-only buttons lack robust names.
- **Required outcome:** implement the WAI-ARIA dialog/menu patterns, preserve keyboard focus visibility, add semantic progress values/live feedback, and run automated plus keyboard/screen-reader checks.

#### HSP-P2-019 — Localization is partial and the broken `lang` amplifies it

- **Evidence:** learner/marketing strings use translations and the parity test passes, while most admin/supervisor copy and several auth/controller messages are hardcoded Indonesian or English.
- **Required outcome:** fix document language first, then inventory hardcoded user-facing strings and localize them consistently. Keep content language distinct from interface locale.

#### HSP-P2-020 — Lesson template has an unmatched closing container

- **Evidence:** `resources/views/lesson.blade.php:75-76` closes one more `div` than it opens at that nesting level.
- **Required outcome:** correct structure and validate rendered HTML for the main templates.

#### HSP-P2-021 — Admin validation failures lose useful modal context

- **Evidence:** modal state is client-only and templates generally provide success banners but no structured error summary/reopen-to-failed-item behavior.
- **Required outcome:** preserve old input, reopen the correct modal/type on validation errors, show an accessible error summary, and test invalid create/edit flows.

#### HSP-P2-022 — `public/hot` requires a deployment guard

- **Evidence:** local `public/hot` points to `http://[::1]:5173`; it is correctly ignored by Git but would make Laravel request dev assets if copied to production.
- **Required outcome:** keep it for local Vite only; deployment must remove it, build assets, and verify `public/build/manifest.json` before traffic.

#### HSP-P2-023 — Post-authentication redirects can send valid elevated users to the learner-only dashboard

- **Evidence:** `/dashboard` is protected by `role:user`, while supervisor and superadmin dashboards use their own prefixed routes. Laravel's `RedirectIfAuthenticated` currently resolves the globally named `dashboard` route to `/dashboard` because `bootstrap/app.php` does not configure `redirectUsersTo`. `LoginController` calls `redirect()->intended(...)`, so a stored `/dashboard` destination can override its role-specific fallback. `EmailVerificationController` also hard-codes the learner dashboard in the verified notice, verification-complete, and already-verified resend paths. A runtime probe confirmed the guest-middleware default resolves to `http://127.0.0.1:8000/dashboard`; the role middleware then correctly returns `403 UNAUTHORIZED ACTION` for a supervisor or superadmin.
- **Impact:** an authorized supervisor or superadmin can encounter a 403 merely by opening `/login` or `/register` while signed in, completing email verification, or logging in after a stale/cross-role intended URL. The access-control boundary is behaving correctly, but the navigation layer sends the user to a route their role must not access.
- **Required outcome:** implement one fail-closed role-to-landing-route resolver (`user` → `dashboard`, `supervisor` → `supervisor.dashboard`, `superadmin` → `superadmin.dashboard`) and reuse it for authenticated `guest` middleware redirects, successful login, and every email-verification redirect. Unknown roles must not fall back to the learner dashboard. Initially redirect directly to the resolved role landing page; preserve an intended destination only after it is proven same-origin and authorized for that role. Keep direct cross-role route requests forbidden and cover the redirect matrix with feature tests.

### 5.4 P3 — maintainability and engineering hygiene

#### HSP-P3-001 — Code style is not enforced

- **Evidence:** Pint reports 33 affected files; CI marks Pint non-blocking.
- **Required outcome:** apply Pint in an isolated formatting change after functional fixes, then make it blocking.

#### HSP-P3-002 — Dead files and unused imports increase confusion

- **Evidence:** `dynamic-material-form.js`, `material-form.js`, and `vocabulary-form.js` are not imported/referenced; Pint reports numerous unused imports.
- **Required outcome:** delete dead code only after browser tests confirm it is unused; clean imports and stale phase/TODO comments.

#### HSP-P3-003 — Request/return contracts are inconsistent

- **Evidence:** exercise uses FormRequests, while other CRUD controllers use inline rules; many controller/model relationship methods omit return types; material/exercise types are magic strings across PHP/Blade/JS.
- **Required outcome:** introduce resource-specific FormRequests, backed enums/value objects where appropriate, typed relations/returns, and one shared serialized type contract.

#### HSP-P3-004 — README is Laravel boilerplate

- **Evidence:** root README describes Laravel generally, not Hospitrainity setup, roles, Windows workflow, storage link, mail verification, curriculum source, tests, or deployment.
- **Required outcome:** write project-specific setup/architecture/runbook documentation without credentials.

#### HSP-P3-005 — CI omits security gates and allows style drift

- **Evidence:** workflow runs build/tests, but no Composer/npm audit; Pint is `continue-on-error`.
- **Required outcome:** add locked dependency audits, blocking style, cache-safe installs, and artifact checks. Keep secrets out of logs.

#### HSP-P3-006 — No first-party JavaScript/browser test command

- **Evidence:** package scripts contain only `dev` and `build`; PHP tests directly post exercise payloads and cannot detect disabled/x-show form serialization, listening navigation, XSS sinks, or aborted fetches.
- **Required outcome:** add lint/unit tests and a maintained browser suite for public, learner, supervisor, and admin critical paths.

#### HSP-P3-007 — Four tests are independently marked risky

- **Evidence:** isolated AuthAccess rendering still reports unclosed output buffers; the two dashboard query tests do too.
- **Required outcome:** diagnose after layout/pagination/dependency fixes; require zero failed and zero risky tests.

#### HSP-P3-008 — Default Composer dev command is not Windows-safe

- **Evidence:** `composer.json` always starts Pail; native PHP has no PCNTL.
- **Required outcome:** provide `dev` and `dev:windows`/optional logging scripts, or remove Pail from the shared command. Document separate Laravel/Vite terminals.

#### HSP-P3-009 — Authentication form polish is incomplete

- **Evidence:** registration uses `autocomplete="current-password"` instead of `new-password`, confirmation placeholder repeats Password, terms/privacy text has no policy destination, and terms validation has no field error output. Registration also contains an unused hidden `remember` field.
- **Required outcome:** correct autocomplete/labels/errors, provide real policy links or remove the unsupported consent claim, and remove dead inputs.

#### HSP-P3-010 — Destructive edit policy is undefined

- **Evidence:** content deletes cascade permanently; there is no soft-delete/recovery or optimistic concurrency/version check.
- **Required outcome:** product owner decides whether audit history/recovery is required. If yes, implement versioning/soft delete and conflict detection before wider admin use.

#### HSP-P3-011 — This snapshot has no Git metadata

- **Evidence:** `git status` reports that the directory is not a repository, although a workflow file exists.
- **Required outcome:** before AI implementation, work in the actual repository or create an authorized backup/version-control baseline. Do not assume commits/CI are available in this folder.

## 6. Items confirmed healthy — preserve these behaviors

An AI should not “fix” the following without a separate requirement:

- SQLite currently loads both required PHP extensions; the DB is structurally healthy and migrated.
- Local `APP_DEBUG=true` and log mailer are appropriate for local development. Only production values must differ.
- Password hashing, email verification contract/event, signed verification route, verification resend throttle, and verified middleware are present.
- Login is throttled by normalized submitted email plus IP.
- Registration does not accept a requested role; privilege escalation is covered by a test.
- Progress types use a server allowlist and reject unknown/mismatched IDs atomically at validation time.
- Completion uniqueness and useful polymorphic indexes already exist.
- Module slug creation already handles normalized collisions; port that pattern to lessons rather than replacing it.
- Dashboard progress queries are bounded by lesson count for one learner; retain that improvement while batching supervisors.
- English/Indonesian translation-key parity test passes.
- PHP and JavaScript syntax checks pass.
- npm reports no known advisories at the audit date.
- No encoding corruption was found in an actual UTF-8 scan.
- The standalone site is a read-only, deterministic artifact matching phase-14 evidence. Do not hand-edit it.
- `public/hot` is expected only while local Vite runs and is already ignored by Git.

## 7. Ordered implementation roadmap

Each task below is an independently verifiable change. Do not combine phases into a single rewrite.

### Phase 0 — Safety baseline and decisions

1. Work from the real version-controlled repository, or make timestamped copies of `database/database.sqlite` and `storage/app/public` before any migration/media work.
2. Capture the current failing test/audit results in the work item; do not normalize them away.
3. Resolve four product decisions in writing:
   - progress means viewed, completed, or mastered;
   - institution enrollment mechanism;
   - whether elevated roles may use learner routes;
   - whether Laravel will import the approved seven-chapter curriculum or intentionally retain legacy content.
4. Never run `migrate:fresh`, destructive reseeding, or delete uploaded media against the working DB during implementation.

**Exit gate:** backups exist, decisions are recorded, and task scope identifies the authoritative repository/database.

### Phase 1 — Security dependencies and P0 runtime blockers

1. Update Composer dependencies with all required transitive dependencies; keep the first change within compatible Laravel 12 patched releases. Update development packages too.
2. Run `composer audit`; do not add advisory ignores unless reachability and compensating controls are documented.
3. Fix both layouts and add rendered layout/CSRF/lang tests.
4. Replace the five custom pagination-view calls and strengthen `AdminRouteSmokeTest` with populated multi-page fixtures and headings.
5. Replace unsafe exercise data sinks with safe DOM construction and XSS regression fixtures.
6. Centralize and correct progress fetch/await/error handling.

**Exit gate:** Composer audit has no unignored advisories; admin lists return 200; real CSRF token is rendered; progress succeeds and fails visibly; malicious exercise strings render only as text; full suite has no failures introduced.

### Phase 2 — Authorization and progress integrity

1. Scope nested vocabulary/material resources to the lesson and add 404 tests.
2. Add a published-content authorization rule for module, lesson, practice, material, exercise, and progress endpoints.
3. Encode the approved role-route matrix in middleware/policies.
4. Harden progress validation to published/owned curriculum IDs, cap request size, and bulk-upsert transactionally.
5. Implement per-item progress semantics based on the Phase 0 decision; stop granting all IDs by final-page navigation.
6. Clean completions and invalidate progress caches on curriculum mutation/publish changes.

**Exit gate:** cross-lesson and draft URLs are denied; forged completion IDs are rejected; partial writes cannot occur; every role-route combination has tests.

### Phase 3 — Admin CRUD and media integrity

1. Introduce FormRequests and explicit type contracts for modules, lessons, vocabulary, materials, and exercises.
2. Fix normalized lesson slug collisions.
3. Fix material immutable-type updates and scoped material item IDs.
4. Fix vocabulary scoped IDs and stop accepting authoritative media paths from clients.
5. Redesign file lifecycle with staged writes, after-commit removal, compensating cleanup, and consistent directories.
6. Add the storage link deployment step/health check.
7. Make material validation conditional by enum/type and restrict remote video URLs.
8. Rebuild exercise editor serialization and expose/support all allowed types.
9. Preserve/reopen modal state with accessible validation summaries.

**Exit gate:** browser tests create/edit/delete every content type; foreign IDs return 422/404 without mutation; injected DB/file failures leave no orphan or missing file; all media URLs resolve.

### Phase 4 — Learner engines and media UX

**Implementation status (2026-07-16):** Complete; Checkpoint 4 was accepted when the user authorized Phase 5. Reproducible evidence, migration details, and limitations are recorded in `docs/HOSPITRAINITY_IMPLEMENTATION_CHECKPOINTS.md`.

1. Repair listening task feedback/navigation.
2. Split spelling prompt text from audio URLs and migrate seed/content data.
3. Align material renderer with the server type registry; remove dead branches and use correct MIME data.
4. Restore accessible vocabulary TTS fallback and handle all audio/TTS promise/error states.
5. Define incorrect-answer retry/Next behavior consistently with the chosen mastery rule.
6. Add live status/error announcements and accessible control names.

**Exit gate:** every one of 14 exercise types has a success, incorrect/retry, keyboard, and completion test; media failures do not trap the learner; progress persists before leaving.

### Phase 5 — Query performance and data model

**Implementation status (2026-07-16):** Implemented and awaiting Checkpoint 5 user review. The query-count, deterministic-ordering, index/query-plan, pagination, cache-scaling, and collision-safe email exit gates are complete. Institution normalization remains intentionally decision-gated because no enrollment model has been approved; the existing institution string was not reinterpreted or migrated. Full evidence and limitations are recorded in `docs/HOSPITRAINITY_IMPLEMENTATION_CHECKPOINTS.md`.

1. Add dashboard lesson count and lesson-detail eager loading.
2. Define ordering on all curriculum relationships and expose controlled admin order editing.
3. Normalize institutions and migrate users only after enrollment design is approved.
4. Add measured FK/filter/order indexes and compare SQLite query plans.
5. Batch supervisor progress and paginate learners.
6. Normalize email addresses with a collision-reporting data migration.

**Exit gate:** query-count tests stay bounded for curriculum and learner growth; duplicate canonical email migration is safe; ordering is deterministic.

### Phase 6 — Authentication and deployment hardening

1. Fix Remember me field behavior.
2. Add forgot/reset/registration throttles and generic outward reset responses.
3. Bundle Alpine and critical UI dependencies; remove authenticated third-party avatars.
4. Extract inline scripts as needed, then introduce CSP report-only and graduate to enforcement.
5. Add security headers and production HTTPS/cookie/HSTS checks.
6. Ensure deployment removes `public/hot`, builds a manifest, uses `APP_DEBUG=false`, and installs without dev packages.

**Exit gate:** auth abuse/error tests pass; production smoke test has secure headers/cookies and no dev asset dependency; CSP has no unexplained violations.

### Phase 7 — Product consistency, accessibility, and localization

1. Implement the HSP-P2-023 role-to-landing-route resolver and use it for authenticated guest-page redirects, successful login, and all email-verification exits. Do not weaken role middleware; reject or ignore stale/cross-role intended destinations until they pass explicit same-origin and role-authorization checks.
2. Complete the visible Hospitrainity rename, including split markup.
3. Replace `Document` titles and broken placeholder links.
4. Correct or remove unsupported marketing claims, testimonials, free-trial language, and stale copyright.
5. Implement WAI-ARIA-compliant dialogs/menus, progress semantics, focus management, and keyboard flows.
6. Fix lesson markup and validate rendered HTML.
7. Move hardcoded admin/supervisor/auth messages into translation files and preserve parity tests.

**Exit gate:** every authentication and verification entry path sends each known role to its permitted dashboard; stale/cross-role intended destinations cannot cause a 403 or bypass authorization; direct cross-role requests remain forbidden; no visible StayReady/placeholder links; marketing copy matches implemented evidence; automated accessibility scan plus manual keyboard checks have no blocker; document language/titles are correct.

### Phase 8 — Curriculum source-of-truth integration

1. Preserve the canonical package and phase-14 checksum as immutable input evidence.
2. Design a versioned, idempotent import from the approved package to Laravel tables (or a new normalized schema), with dry-run/diff/report modes.
3. Map 7 chapters, 85 sections, 24 activities, 102 prompts, answers/feedback/rubrics, outcomes, provenance, and lifecycle status without silently flattening unsupported semantics.
4. Generate Laravel projections and standalone output from the same versioned source.
5. Keep rollback artifacts and compare counts/checksums after every import.

**Exit gate:** one declared source produces both delivery surfaces deterministically; legacy content differences are explicitly migrated or retired; no manual generated-file edits.

**Implementation status (2026-07-16):** Implemented and awaiting Checkpoint 8 user review. The versioned canonical package now drives lossless normalized Laravel storage, learner delivery, and byte-exact standalone regeneration through one checksum-gated importer with dry-run/diff/report/idempotency/rollback support. Retained legacy content is read-only evidence and all legacy writes fail closed while the canonical package is active. Reproducible hashes, persistent-import artifacts, verification results, and source limitations are recorded in `docs/HOSPITRAINITY_IMPLEMENTATION_CHECKPOINTS.md`.

### Phase 9 — Test, CI, documentation, and cleanup

1. Add JavaScript lint/unit and browser E2E scripts.
2. Cover the gaps listed below; diagnose all risky output-buffer tests.
3. Add Composer/npm audits to CI and make Pint blocking after an isolated formatting pass.
4. Replace README boilerplate with setup, architecture, roles, storage/mail, Windows, testing, curriculum, and deployment runbooks.
5. Remove dead JS/imports and standardize typed contracts only after coverage exists.
6. Decide and implement recovery/versioning for destructive admin changes if required.

**Exit gate:** tests have zero failed/risky; audits/style/build are blocking; a clean machine can follow README to a working verified-account flow.

**Implementation status (2026-07-17):** Implemented and awaiting Checkpoint 9 user review. JavaScript lint/unit coverage, isolated Chromium critical-path E2E, strict PHP issue handling, blocking dependency/style/build/CI gates, the complete operator README, dead-code cleanup, covered typed contracts, and canonical curriculum recovery/versioning are complete. A clean `npm ci` installation and the documented existing-institution verified-account flow are reproducible. The roadmap's invitation/domain/join-code enrollment alternative remains explicitly decision-gated because no enrollment policy has been approved; the application retains and tests the exact existing-institution rule instead of inventing a security policy. Full evidence and limitations are recorded in `docs/HOSPITRAINITY_IMPLEMENTATION_CHECKPOINTS.md`.

## 8. Required regression coverage

At minimum, add or retain tests for:

- all public/auth pages render valid layout language and CSRF metadata;
- login remembers only when checked;
- forgot-password outward response parity and all auth throttles;
- invitation/domain/join-code institution enrollment rule;
- complete role-route matrix;
- role-aware landing redirects for authenticated guest pages, successful login, verification notice/completion/resend, stale or cross-role intended URLs, and fail-closed unknown roles;
- published vs unpublished module/lesson/content access;
- correct and incorrect nested lesson-vocabulary/material pairs;
- five admin indexes with pagination and data;
- CRUD success and validation failure for every admin resource;
- foreign nested item IDs and file rollback failures;
- all 14 exercise admin payloads and learner renderers;
- XSS payloads in titles, prompts, answers, options, sentences, categories, and URLs;
- listening, spelling text/audio, TTS failure, media failure, and video allowlist paths;
- progress response 200, 419, 422, 429, 500, network failure, retry, double-click, and navigation timing;
- completion cleanup/cache invalidation after delete/publish/unpublish;
- dashboard/lesson/supervisor query-count bounds;
- storage URL/link behavior;
- locale parity, correct titles, branding, and no placeholder links;
- keyboard/focus/dialog/menu/live-region behavior;
- canonical import dry run, idempotence, counts, references, checksum, and rollback.

## 9. AI implementation contract

Any AI using this roadmap must follow these rules:

1. Re-open the cited source before changing it; line numbers can move.
2. Work one finding or tightly related task at a time. Add a failing regression test first where practical.
3. Do not change application behavior merely to silence a test. Resolve the root cause and preserve healthy behaviors in section 6.
4. Do not edit `.env` secrets, expose demo/real credentials, or print session/mail tokens.
5. Never use destructive DB commands against the working SQLite file. Use in-memory/test databases and `Storage::fake` for automated tests.
6. Before migrations/media work, verify backups and implement rollback/compensating behavior.
7. Do not hand-edit generated standalone/curriculum artifacts. Update source and regenerate through the documented pipeline.
8. Do not bulk-rebrand identifiers. Replace user-visible branding deliberately and leave historical evidence intact.
9. Do not add advisory suppressions, CSP wildcards/`unsafe-eval`, or authorization bypasses as shortcuts.
10. If a Phase 0 product decision is unresolved, stop that specific task and record the blocker instead of inventing policy.
11. After each task, run the narrow test, full relevant suite, syntax/style check, and update this roadmap with status/evidence.
12. A task is complete only when its acceptance criteria pass in both source-level and rendered/runtime checks.

## 10. Verification command set (Windows PowerShell)

Adjust the PHP path if the real environment differs.

```powershell
& 'C:\php\php.exe' artisan optimize:clear
& 'C:\php\php.exe' artisan migrate:status
& 'C:\php\php.exe' artisan route:list
& 'C:\php\php.exe' artisan test --display-all-issues
& 'C:\php\php.exe' vendor/bin/pint --test
& 'C:\php\php.exe' 'C:\composer\composer.phar' validate --strict --no-check-publish
& 'C:\php\php.exe' 'C:\composer\composer.phar' audit
& 'C:\Program Files\nodejs\npm.cmd' ci
& 'C:\Program Files\nodejs\npm.cmd' audit
& 'C:\Program Files\nodejs\npm.cmd' run build
& 'C:\php\php.exe' artisan storage:link
```

Use `npm ci` only where replacing `node_modules` is intended (clean CI/worktree), not casually in a working snapshot. Run `storage:link` once per deployment environment, not in tests.

## 11. Primary reference baseline

- [Laravel 12 pagination and default Tailwind views](https://laravel.com/docs/12.x/pagination)
- [Laravel scoped route bindings](https://laravel.com/docs/12.x/routing#custom-keys-and-scoping)
- [Laravel filesystem/public storage link](https://laravel.com/docs/12.x/filesystem#the-public-disk)
- [Laravel validation and conditional/scoped rules](https://laravel.com/docs/12.x/validation)
- [Laravel database transactions](https://laravel.com/docs/12.x/database#database-transactions)
- [Laravel production debug configuration](https://laravel.com/docs/12.x/configuration#debug-mode)
- [Composer security audit command](https://getcomposer.org/doc/03-cli.md#audit) and [Composer advisory policy](https://getcomposer.org/doc/06-config.md#audit)
- [MDN Fetch error semantics](https://developer.mozilla.org/en-US/docs/Web/API/Window/fetch)
- [OWASP XSS prevention and safe DOM sinks](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html)
- [OWASP authentication response guidance](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)
- [OWASP HTTP security headers](https://cheatsheetseries.owasp.org/cheatsheets/HTTP_Headers_Cheat_Sheet.html)
- [W3C WAI-ARIA modal dialog pattern](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/)
- [W3C WAI-ARIA patterns](https://www.w3.org/WAI/ARIA/apg/patterns/)
- [Alpine official module installation](https://alpinejs.dev/start)
- [Symfony PATH_INFO authorization advisory example](https://symfony.com/blog/cve-2025-64500-incorrect-parsing-of-path-info-can-lead-to-limited-authorization-bypass)

## 12. Final release definition

Hospitrainity is release-ready only when:

- no P0/P1 finding remains open;
- all product decisions affecting authorization/progress/content are recorded;
- Composer/npm audits have no unreviewed findings;
- all tests pass with zero risky tests and the browser critical-path suite is green;
- admin CRUD and learner progress survive failure/retry scenarios without data or file inconsistency;
- nested/draft content cannot be accessed outside policy;
- marketing, branding, titles, locale, and accessibility match actual behavior;
- production deployment proves HTTPS cookies, secure headers, no debug output, no `public/hot`, and a valid asset manifest/storage setup;
- the Laravel and standalone delivery surfaces have a declared, tested curriculum source of truth.

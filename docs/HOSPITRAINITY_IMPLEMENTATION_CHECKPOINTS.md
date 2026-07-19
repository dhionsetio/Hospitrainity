# Hospitrainity Implementation Checkpoints

> Content-fidelity continuation: CF-1 through CF-6 were implemented and verified on 17 July 2026. CF-7 technical implementation, automated QA, and source-DOCX visual comparison completed on 18 July 2026, but publication remains blocked by the recorded human/owner gates. Evidence is in [`CONTENT_FIDELITY_CF1_CF6_CHECKPOINT.md`](CONTENT_FIDELITY_CF1_CF6_CHECKPOINT.md) and [`CONTENT_FIDELITY_CF7_CHECKPOINT.md`](CONTENT_FIDELITY_CF7_CHECKPOINT.md); the package remains `0.4.0-draft`.

This log tracks implementation of `HOSPITRAINITY_FULL_AUDIT_AND_UPDATE_ROADMAP.md`.
The numbered implementation phases are intentionally separated by user review checkpoints.

| Phase | Status | Checkpoint |
|---|---|---|
| 1 — Security dependencies and P0 runtime blockers | Completed | 1 of 9 |
| 2 — Authorization and progress integrity | Completed | 2 of 9 |
| 3 — Admin CRUD and media integrity | Completed | 3 of 9 |
| 4 — Learner engines and media UX | Completed | 4 of 9 |
| 5 — Query performance and data model | Completed | 5 of 9 |
| 6 — Authentication and deployment hardening | Completed | 6 of 9 |
| 7 — Product consistency, accessibility, and localization | Completed | 7 of 9 |
| 8 — Curriculum source-of-truth integration | Awaiting user review | 8 of 9 |
| 9 — Test, CI, documentation, and cleanup | Not started | — |

## Checkpoint 1 — Phase 1

Date: 2026-07-16  
State: implementation and automated verification complete; paused for user review.

### Changes implemented

1. Updated the compatible Laravel 12 dependency set with all transitive and development dependencies.
   - Laravel Framework: `v12.19.3` → `v12.64.0`.
   - Key patched packages include Guzzle, PSR-7, CommonMark, PHPUnit, PsySH, and Symfony components.
   - `composer.lock` and installed dependencies agree; Laravel was not upgraded to a new major version.
2. Corrected both shared layouts.
   - Locale and CSRF Blade expressions now render real values.
   - Invalid pseudo-comments are valid HTML comments.
   - Added rendered guest/app layout regression tests.
3. Removed all five references to the nonexistent `vendor.pagination.tailwind` view.
   - Admin index tests now create more than one page of real records, assert headings, and assert second-page links.
4. Rebuilt learner exercise DOM rendering around `createElement`, `textContent`, `createTextNode`, and `replaceChildren`.
   - Exercise/content/translation values no longer pass through HTML parsing sinks.
   - Added a source-level regression test that rejects `innerHTML`, `insertAdjacentHTML`, and `document.write` on learner data surfaces.
5. Centralized progress requests in `resources/js/progress.js`.
   - Requests now include same-origin credentials and the rendered CSRF token.
   - Navigation waits for an HTTP-success response.
   - HTTP and network failures keep the learner on the page, expose an ARIA live error, and offer Retry.
   - Buttons/navigation are disabled while a save is pending to prevent duplicate submission.
   - Added Node tests for success, non-2xx, network failure, request shape, and input validation.
6. Applied the same safe DOM construction to database-backed learner material media and vocabulary practice surfaces touched by progress handling.
7. Added production build output for the new progress client and exercise engine.

### Additional defects found and fixed during Phase 1

- Static Blade title sections such as `@section('title')Document@endsection` did not compile the closing directive. This leaked an output buffer, emitted literal directive text, and made affected PHPUnit tests risky. All confirmed static occurrences were converted to valid inline section declarations.
- The material media helper referenced undeclared `isPlaying`; state is now declared.
- The material image renderer duplicated image construction and used database values in HTML templates; it now uses explicit DOM nodes and a single rendering path.
- Listening-task answers never revealed feedback/navigation because the handler suppressed feedback and did not call it separately; the handler now completes the interaction.
- Sound-sorting previously keyed answers by visible word, so duplicate words could overwrite one another. Each token now carries its own expected category.
- Sequencing compared arrays through delimiter-joined strings, which could collide with legitimate content. It now compares elements by index.

### Verification evidence

- `composer audit --format=json`: 0 advisories, 0 abandoned packages.
- `composer install --no-scripts --no-interaction --no-progress`: lockfile is installable; nothing missing.
- `composer validate --strict --no-check-publish`: valid.
- `npm audit --json`: 0 vulnerabilities at every severity.
- Full Laravel suite: 47 passed, 173 assertions, 0 failed, 0 risky.
- Node regression suite: 5 passed, 0 failed.
- Vite production build: passed; 59 modules transformed.
- PHP lint across app/bootstrap/config/database/routes/tests: passed.
- JavaScript syntax check across `resources/js`: passed.
- Blade view compilation: passed.
- Local HTTP `/login` probe: 200; valid locale, nonempty CSRF token, valid title; no malformed layout expressions or pseudo-comments.

### Known limitations at this checkpoint

- The in-app browser runtime failed to initialize in this environment, so no fresh visual screenshot or authenticated manual click-through is attached. Automated feature tests, Node tests, the production build, Blade compilation, and a local HTTP runtime probe passed.
- Progress completion semantics (viewed vs completed vs mastered) and per-item recording remain Phase 2 decisions/tasks. Phase 1 makes the existing final-page save reliable; it does not redefine the product rule.
- The broader material/exercise accessibility, retry/mastery behavior, media contracts, and full browser matrix remain in later phases even though directly encountered blockers were corrected here.
- No database migration, reseed, curriculum mutation, media deletion, or destructive data operation was performed.

### Next action after approval

Begin Phase 2 only after the user says `Continue`: nested-resource scoping, published-content authorization, role-route policy enforcement, progress request limits/ownership/transactionality, approved per-item progress semantics, and completion cleanup/cache invalidation.

## Checkpoint 2 — Phase 2

Date: 2026-07-16  
State: implementation and verification complete; paused for user review.

### Product decisions used for this implementation

The audit roadmap identified two decisions that were not answered by the supplied update-notes file, the existing code, seed data, or project documentation. They were announced during implementation and the least-divergent defaults were applied so Phase 2 could proceed:

1. **Progress means completed/attempted, not mastery.** A vocabulary or material item is completed when the learner presses Next/Done on that item. An exercise is completed after an interaction exposes Next/Done and the learner presses it; an incorrect attempt still counts as attempted completion. Side-navigation alone records nothing. A future mastery requirement needs an explicit attempt/result data model instead of overloading the current boolean completion table.
2. **Role areas are isolated.** Learners (`user`) use learner routes, supervisors use the supervisor area, and superadmins use the superadmin area. Elevated roles do not automatically receive learner-route access. An explicit preview/impersonation feature can be designed later if required.

These defaults are reversible product choices. If the user selects different semantics, revise them before continuing to later phases.

### Changes implemented

1. Scoped learner content and nested resources.
   - Enabled scoped route-model binding for lesson/vocabulary and lesson/material pairs.
   - Cross-lesson resource combinations now return 404.
   - Added policies for modules, lessons, vocabularies, materials, and exercises.
   - Learner module, lesson, vocabulary practice, material, and exercise pages require a published ancestor; drafts are hidden as 404.
   - Added controller-level authorization as defense in depth in addition to route middleware.
2. Encoded the role-route matrix.
   - Learner routes now require `role:user` in addition to authentication and verification.
   - Supervisor and superadmin route areas remain separately constrained.
   - `CheckRole` now reads the authenticated request user and compares allowed roles strictly.
   - Added an explicit `User::isLearner()` predicate and made the user factory's default role explicit.
3. Hardened progress writes.
   - Preserved the fixed completable-type allowlist.
   - Required a nonempty list of positive integer IDs, capped at 100 items.
   - De-duplicated IDs before querying or writing.
   - Required every submitted ID to belong to curriculum under a published module.
   - Rejects unknown, wrong-type, draft, and mixed valid/invalid sets with HTTP 422 before any write.
   - Replaced per-row writes with one transactional `upsert` backed by the existing completion uniqueness index; deadlock retries are bounded to three attempts.
4. Implemented per-item progress recording.
   - Vocabulary, material, and exercise clients submit only the current item ID.
   - Next/Done waits for confirmed persistence before advancing or returning.
   - Retry replays the same item/action, and navigation is disabled while the request is pending.
   - Added a JavaScript source regression test that rejects the former all-item grant pattern.
5. Added completion cleanup and curriculum cache invalidation.
   - Content change, move, unpublish, and delete events purge affected polymorphic completion rows.
   - Module and lesson deletions purge descendant completions before database cascades remove the curriculum records.
   - Pure ordering changes preserve completion state.
   - Creating a completable unit or changing the published curriculum denominator invalidates learner progress caches.

### Additional defect found and fixed during Phase 2

- `UserFactory` omitted the default role and relied only on the database default. The saved in-memory model could therefore retain a null role until refreshed, producing misleading authorization failures in tests and any factory-driven tooling. The factory now explicitly creates an ordinary learner unless a test overrides the role.

### Verification evidence

- Full Laravel suite: 66 passed, 263 assertions, 0 failed.
- Phase 2 coverage includes valid/cross-lesson nesting, all learner draft routes, the three supported progress types, mixed-set atomic rejection, the 100-item cap, de-duplication, cleanup after content/publish/delete events, cache invalidation, and the complete role-route matrix.
- Node regression suite: 6 passed, 0 failed.
- Vite production build: passed; 59 modules transformed.
- Blade production view compilation: passed.
- PHP lint across 65 application, route, factory, and test files: passed.
- Route enumeration: 43 application routes loaded successfully.
- `composer validate --strict --no-check-publish`: valid.
- Composer audit: 0 advisories and 0 abandoned packages.
- npm audit: 0 vulnerabilities at every severity.
- In-app browser runtime: restored and verified against the local application.
  - A signed-in learner received `403 Unauthorized Action` from `/supervisor/dashboard`.
  - The published module, lesson, and vocabulary-practice pages rendered successfully.
  - The practice page displayed the expected current-item `Done` action, completed document state, and no Vite error overlay.
  - The browser did not submit progress, so the persistent local demo database was not modified for this check.

### Known limitations at this checkpoint

- The two product defaults above await explicit user confirmation. They are documented to prevent later AI work from silently changing the meaning of progress or role access.
- Curriculum-wide cache invalidation currently enumerates learner IDs. It is correct for the present dataset but is O(number of learners); Phase 5 should replace or batch it after measured scale testing.
- Browser verification intentionally did not press Done or create draft data in the persistent local database. Transactional writes, draft denial, and cleanup were instead verified against isolated test databases.
- The production build succeeds but Node reports a `module.register()` deprecation warning from the current Vite/toolchain dependency path. It is non-blocking and should be rechecked during the later dependency/CI cleanup phase.
- The workspace contains a `.git` directory without a `HEAD`, so Git status/diff verification is unavailable. The explicit file log and automated checks are the current change evidence.
- No migration, reseed, curriculum mutation, media deletion, or other destructive operation was performed on the persistent local database.

### Next action after approval

Begin Phase 3 only after the user says `Continue`: admin CRUD transactionality, scoped nested item ownership, safe upload replacement/deletion, immutable material-type handling, exercise editor/request consistency, and file/database rollback tests.

## Checkpoint 3 — Phase 3

Date: 2026-07-16  
State: implementation and verification complete; paused for user review.

### Confirmed Phase 2 product decisions

The user's Phase 3 instruction explicitly asked to apply the items that could be revised before Phase 3. The former defaults are therefore confirmed for subsequent phases:

1. Progress means completed/attempted, not mastery. Mastery still requires a future attempt/result model instead of changing the meaning of the current completion rows.
2. Learner, supervisor, and superadmin route areas remain strictly isolated. Elevated roles do not implicitly receive learner access.

### Changes implemented

1. Replaced controller-inline validation with bounded FormRequests for modules, lessons, vocabulary, materials, and all 14 exercise types.
   - Nested arrays use list, size, key allowlist, and upper-bound rules.
   - Renderer-dependent exercise relationships are validated, including correct-answer membership, sound-sorting category references, multiple-blank counts, and silent-letter indexes.
2. Normalized lesson slugs before validation/persistence and added stable `-2`, `-3`, and later suffixes for normalized collisions while preserving a lesson's own slug on update.
3. Made material type immutable after creation and scoped every submitted material-item ID to the material being edited.
4. Scoped every submitted vocabulary-item ID to its vocabulary and removed client-authoritative existing-media paths. Failed edit forms are rehydrated from current database-owned URLs.
5. Introduced `PublicMediaManager` and a durable file lifecycle.
   - New uploads are staged before the database transaction.
   - A failed database mutation compensates by deleting staged files.
   - Replaced/deleted database-owned local files are recorded in `pending_media_deletions` inside the content transaction and removed only after commit.
   - Failed post-commit removals retain attempt/error state for retry instead of silently becoming orphans.
   - Cleanup retries are exposed through `hospitrainity:media-cleanup` and scheduled hourly without overlap.
   - Vocabulary, material-image, and material-audio uploads now use consistent `curriculum/...` public-disk directories.
6. Added `hospitrainity:storage-health`, enabled exception-based public-disk writes, installed the local `public/storage` link, and verified a real write/read/web-map/delete probe.
7. Made material validation conditional on the four declared types (`Teks`, `Audio`, `Gambar`, `Video`). Remote videos accept only HTTPS YouTube watch, short, shorts, or embed URLs and are stored as canonical `https://www.youtube.com/embed/{id}` URLs.
8. Rebuilt the exercise admin editor so only the selected editor exists/submits. The editor and server contract now expose the same 14 types, use real nested arrays, reject inactive/extra keys, and keep type immutable on edit.
9. Admin validation errors now reopen the correct create/edit modal, preserve safe old input, and show a shared `role="alert"` validation summary. Module, lesson, vocabulary, material, and exercise dialogs gained baseline dialog labeling.

### Additional defects found and fixed during Phase 3

- Database cascade deletes do not execute child model delete events. Module and lesson deletion now collect descendant vocabulary/material media before the database cascade, preventing orphaned files.
- The first exercise contract pass allowed unbounded nested content. Every nested list/string is now bounded to prevent oversized JSON submissions, and renderer-dependent count/index relationships are enforced.
- Windows percent-encoded backslashes were decoded after path normalization, which could evade the media traversal check. Paths are now decoded first, separators normalized second, and both slash variants are regression-tested.
- The exercise sidebar entry had disappeared; `Kelola Exercise` is restored and browser-verified.

### Persistent local environment changes

- Applied migration `2026_07_16_000001_create_pending_media_deletions_table` (batch 2).
- Created the ignored local `public/storage` link to `storage/app/public` with `php artisan storage:link`.
- Ran the storage probe successfully and ran cleanup with `0` pending records. No persistent curriculum record or curriculum media file was created, changed, or deleted by verification.

### Verification evidence

- Full Laravel suite: 88 passed, 593 assertions, 0 failed.
- All 14 exercise types complete create/update/delete contracts in isolated feature tests; all four material types complete create/update/delete contracts, including real fake-disk image/audio lifecycle checks.
- Failure tests cover foreign IDs without mutation, rejected client media authority, failed file writes with unchanged database state, database failures with staged-file compensation, post-commit deletion failure with durable retry state, and cascade cleanup.
- In-app browser verification against the local application:
  - Authenticated superadmin navigation and the restored exercise sidebar link work.
  - All 14 exercise type selectors were activated; each displayed only its matching named content controls and no inactive editor fields.
  - All four material types were activated; required file/URL controls changed correctly.
  - Material edit mode used the bound `PUT` route and an immutable database-owned type.
  - No Vite error overlay appeared on the exercise or material screens.
- Node regression suite: 6 passed, 0 failed.
- Vite production build: passed; 59 modules transformed.
- Blade production view compilation: passed.
- PHP lint across 118 application, configuration, migration, route, and test files: passed.
- JavaScript syntax check across 8 source files: passed.
- Route enumeration: 46 routes loaded; scheduler lists the hourly media cleanup command.
- `composer validate --strict --no-check-publish`: valid.
- Composer audit: 0 advisories and 0 abandoned packages.
- npm audit: 0 vulnerabilities at every severity.
- Source scans found no controller-inline validation in admin CRUD controllers, client-authoritative `existing_media_url`, legacy media directories, or raw model `json_encode` in superadmin views. The only direct application storage deletion is centralized in `PublicMediaManager`.

### Research basis

- Laravel 12 validation/FormRequest/file rules: https://laravel.com/docs/12.x/validation
- Laravel 12 database transactions: https://laravel.com/docs/12.x/database
- Laravel 12 public filesystem and upload testing: https://laravel.com/docs/12.x/filesystem
- Laravel 12 scheduled commands and overlap locks: https://laravel.com/docs/12.x/scheduling
- Official YouTube embed URL contract: https://developers.google.com/youtube/player_parameters
- OWASP allowlist guidance used for remote-video host restriction: https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html

### Known limitations at this checkpoint

- Database and filesystem changes cannot be one atomic transaction. The implemented staged-write/compensating-delete/durable-cleanup design makes failures recoverable; production must run Laravel's scheduler for automatic retries.
- The in-app browser inspection did not insert/delete persistent demonstration curriculum. Full create/update/delete coverage instead runs against isolated test databases and fake storage. A repository-owned automated browser E2E suite remains Phase 9 work.
- Full focus trapping, Escape behavior, focus restoration, and accessible names for icon-only table actions remain Phase 7. The current missing names were reconfirmed rather than hidden.
- The spelling/audio semantic split and learner media-engine behavior remain Phase 4.
- Remote material videos are deliberately restricted to YouTube. Supporting another provider requires an explicit allowlist/parser and renderer contract.
- `public/storage` is local and ignored. Every deployment must run `php artisan storage:link`, provide a writable public disk, run `hospitrainity:storage-health`, and operate `php artisan schedule:run` once per minute (or an equivalent scheduler worker).
- The production build still reports the previously documented non-blocking Node `module.register()` deprecation from the current Vite/toolchain dependency path.
- The workspace `.git` directory still has no `HEAD`, so Git status/diff evidence remains unavailable.

### Next action after approval

Begin Phase 4 only after the user says `Continue`: learner exercise behavior, spelling/audio contract migration, material renderer/type alignment, vocabulary TTS fallback, retry/Next consistency, and accessible live media/error status.

## Checkpoint 4 — Phase 4

Date: 2026-07-16  
State: implementation, migration, automated verification, and learner browser verification complete; paused for user review.

### Changes implemented

1. Consolidated learner media playback in `resources/js/media-playback.js`.
   - HTML audio waits for the `ended` event instead of treating `play()` acceptance as completion.
   - Audio construction, `play()` rejection, media error, speech-synthesis error/end, and explicit cancellation all settle predictably.
   - Recorded-audio failure falls back to the explicit English prompt text; a failed fallback is announced without disabling answer/navigation controls.
   - Vocabulary, materials, spelling, listening, and pronunciation use the shared controller and stop prior media when the learner changes item.
2. Split the spelling data contract.
   - `content.prompt_text` is now required and is the only TTS source.
   - `content.audio_url` is optional and accepts only an existing public-disk MP3/WAV URL on future admin submissions.
   - The admin editor, request rules, seeder, renderer guard, and CRUD fixtures use the same contract.
   - Migration `2026_07_16_000002_migrate_spelling_prompt_contract` converts legacy text-in-`audio_url` rows to `prompt_text`, supplies a non-guessed fallback for correctly typed legacy local audio, and discards unapproved remote URLs. Its legacy shapes are rollback-tested.
3. Made material rendering match the server registry exactly: `Teks`, `Audio`, `Gambar`, and `Video`.
   - Removed the unreachable `Gambar dengan Audio` branch.
   - Removed hard-coded audio MIME claims; the browser/content response now determines the playable format.
   - Retained only canonical YouTube embeds and added media/image/invalid-video error states.
4. Restored vocabulary pronunciation fallback.
   - Every vocabulary item gets a named pronunciation control, including items without recordings.
   - Recording failure falls back to speech synthesis; any final failure clears busy state and explicitly allows continuation.
5. Standardized attempted-completion behavior confirmed in Phase 3.
   - Incorrect graded answers expose both `Try Again` and `Next`; retry re-renders the item, while Next records the attempted item before moving.
   - Correct answers expose Next without Retry.
   - Matching-game mismatches remain an in-place retry and do not advance.
   - Text-answer exercises submit with Enter; sound sorting and sequencing now use native named buttons.
6. Added learner accessibility state.
   - Exercise, vocabulary, and material progress bars expose value semantics.
   - Media and feedback messages use polite status regions.
   - Icon-only audio/navigation/ordering controls have accessible names; active exercise navigation exposes `aria-current`.
7. Added real-DOM and media regression coverage.
   - Added `jsdom` as a pinned-lockfile development test dependency compatible with the installed Node runtime.
   - The Node suite imports the production exercise engine and covers all 14 registered renderers, keyboard operation, incorrect/retry behavior, completion navigation, and per-item persistence.
   - Feature tests lock the spelling migration/validation and material/vocabulary view contracts.

### Additional defect found and fixed during Phase 4

- The live browser check showed that spelling `Try Again` restored the form but focused the preceding audio button because a mixed selector followed document order. Retry now prefers the answer input and falls back to the first button only for button-based activities; a real-DOM focus regression assertion locks this behavior.

### Persistent local environment changes

- Applied migration `2026_07_16_000002_migrate_spelling_prompt_contract` (batch 3).
- Normalized all three existing spelling rows. Two legacy prompt strings were moved out of `audio_url`; the remaining legacy local-audio row received explicit `prompt_text` while retaining its local URL for runtime fallback.
- Installed `jsdom@29.1.1` as a development-only dependency and updated `package-lock.json`. No production dependency was added.
- No completion record, user, curriculum row other than the three migrated spelling JSON payloads, or media file was created/deleted during verification.

### Verification evidence

- Full Laravel suite: 92 passed, 625 assertions, 0 failed.
- Node suite: 25 passed, 0 failed. This includes actual DOM execution of all 14 exercise renderers and four focused media-controller tests.
- Post-format Phase 4 feature subset: 11 passed, 55 assertions.
- In-app browser learner verification:
  - vocabulary TTS failure announced an actionable error and cleared busy state;
  - a missing legacy spelling recording fell through to the safe error state while the answer remained usable;
  - spelling Enter submission, incorrect feedback, visible Try Again + Next, retry restoration/focus, and correct feedback all worked;
  - listening playback failure did not trap the learner, and incorrect/correct navigation states matched the attempted-completion rule;
  - material/exercise/vocabulary progress and control names were present; no unnamed material buttons were rendered.
- Vite production build: passed; 60 modules transformed, including the shared media chunk.
- Blade production view compilation: passed.
- PHP lint and JavaScript syntax checks: passed.
- Pint check on the new/edited Phase 4 request, rule, migration, and test files: passed. `ExerciseSeeder` retains its pre-existing project formatting and was not mechanically rewritten wholesale.
- `composer validate --strict --no-check-publish`: valid.
- Composer audit: 0 advisories and 0 abandoned packages.
- npm audit: 0 vulnerabilities at every severity across 202 dependencies.
- Migration status confirms the spelling data migration ran in batch 3.

### Research basis

- HTML media playback Promise/error behavior: https://developer.mozilla.org/en-US/docs/Web/API/HTMLMediaElement/play and https://html.spec.whatwg.org/multipage/media.html
- Web Speech synthesis error contract: https://developer.mozilla.org/en-US/docs/Web/API/SpeechSynthesisUtterance/error_event and https://w3c.github.io/speech-api/speechapi.html
- WCAG 2.2 status, keyboard, and accessible-name basis: https://www.w3.org/TR/WCAG22/ and https://www.w3.org/TR/wai-aria/
- WAI accessible-name rule for buttons: https://www.w3.org/WAI/standards-guidelines/act/rules/97a4e1/
- npm registry metadata confirmed `jsdom@29.1.1` and its supported Node engine range before installation.

### Known limitations at this checkpoint

- The persistent database's one legacy `/audio/reservation.mp3` reference points to a file absent from this workspace. It is deliberately non-blocking: runtime falls back to `prompt_text`, fresh seeds no longer create the URL, and any future admin submission/edit must reference an existing public MP3/WAV. The migration does not delete potentially externally provisioned legacy local URLs based only on one machine's filesystem.
- Web Speech availability and voices remain browser/OS capabilities. The product now reports failure and permits continuation, but it cannot install a system voice.
- Current persistent material data contains only `Teks`, and vocabulary items have no recordings. Browser checks therefore exercised those real states; image/audio/video material branches and recorded-media success use isolated rendered/DOM tests. A repository-owned cross-browser E2E matrix remains Phase 9.
- A material audio recording longer than the page session is intentionally stopped when the learner navigates. There is no fabricated duration timeout; completion/error is driven by the media and speech platform events.
- The production build still reports the previously documented non-blocking Node `module.register()` deprecation from the current Vite/toolchain path.
- The workspace `.git` directory still has no `HEAD`, so Git status/diff evidence is unavailable. This checkpoint log and reproducible tests remain the change ledger.

### Next action after approval

Begin Phase 5 only after the user says `Continue`: measured query performance, deterministic curriculum ordering, institution/enrollment data-model decisions, indexes/query plans, supervisor batching/pagination, and collision-reporting email normalization.

## Checkpoint 5 — Phase 5

Date: 2026-07-16  
State: implementation, migrations, automated verification, query-plan measurement, and authenticated browser verification complete; paused for user review.

### Changes implemented

1. Bounded learner curriculum queries.
   - Added `CurriculumProgressService` so the learner dashboard loads the published curriculum once, uses an explicit lesson count, and calculates progress from eager-loaded relations.
   - Lesson detail now eager-loads its module and every nested vocabulary item, material item, and exercise used by the view.
   - Enabled Eloquent lazy-loading prevention outside production so newly introduced hidden N+1 queries fail during development and tests.
   - Added query-count tests proving dashboard and lesson-detail query counts remain bounded as curriculum depth grows.
2. Batched and paginated supervisor reporting.
   - Replaced per-learner `getOverallProgress()` calls with one batched completion lookup for the current page.
   - Learners are ordered by ID and paginated 20 per page; progress bars now expose value semantics.
   - A scale test compares 1 learner with 100 learners and verifies bounded query count, a 20-row first page, and correct progress values.
3. Deterministic curriculum ordering and controlled editing.
   - Every curriculum relationship and admin query now uses the explicit `order` column followed by `id` as a stable tie-breaker.
   - Module, lesson, vocabulary, material, nested item, and exercise order fields are bounded to 0–1,000,000 and exposed in the admin UI.
   - Creates default omitted order to zero; updates preserve the existing order when the field is omitted. A dedicated regression test covers all four multi-part CRUD paths that were at risk.
4. Scalable curriculum cache invalidation.
   - Curriculum-wide invalidation now increments a generation key rather than loading every learner ID and deleting one key per user.
   - Previous-generation values expire under the existing six-hour TTL. Single-user completion writes still evict the active user key immediately.
   - A query-listener test proves curriculum invalidation does not enumerate the users table.
5. Measured database indexes.
   - Added composite indexes for published-module order, every nested curriculum foreign-key/order lookup, and supervisor `(role, instansi, id)` filtering.
   - Added `scripts/phase5-query-plans.php` to reproduce SQLite plans, email-normalization counts, integrity checks, and foreign-key checks without exposing addresses.
   - Before migration the measured queries scanned their tables and the two ordered curriculum queries used temporary B-trees. After migration all three use their intended indexes and the temporary sorts are absent.
6. Collision-safe email canonicalization.
   - `User::canonicalEmail()` and an Eloquent mutator enforce trimmed lowercase storage; login, registration, rate-limit keys, forgot-password, and reset-password paths canonicalize before validation or lookup.
   - Migration `2026_07_16_000004_normalize_email_addresses` is self-contained and idempotent. It scans users and password-reset tokens first, groups canonical values, and aborts the transaction before mutation if a collision exists. Its exception reports record IDs/counts rather than email addresses.
   - Tests cover mixed-case/whitespace storage and login, case-variant duplicate registration, case-variant reset, idempotent migration, and no-mutation collision failure.

### Attached issue-list review and institution decision

- The attached update note's supervisor-empty-state concern exists in the persistent seed data: the supervisor belongs to `Hotel A` while the learner belongs to `Hotel B`. The browser showed the expected empty scoped result. The implementation did not weaken institution access control or relabel this correct result as a bug; the supervisor scale test creates learners in the supervisor's institution, as the note recommended.
- Institution values remain free-form strings and public registration still selects an existing value. The roadmap explicitly requires normalization only after enrollment design approval. No reliable local evidence chooses between invitation, verified domain, join code, or administrator assignment, so Phase 5 does not invent an institution table or migrate users. This decision remains required before that roadmap item can be executed.

### Additional defect found and fixed during Phase 5

- Final self-review found a patch-order regression in module, lesson, vocabulary, and material controllers: create paths could reference update-only variables, while update paths could reset an omitted order to zero. The paths were corrected and a 53-assertion ordering suite now covers default creation, omission-preserving updates, stable tie-breaking, admin controls, and persisted nested order.

### Persistent local environment changes

- Before applying migrations, copied `database/database.sqlite` to `storage/app/private/backups/phase5-database-before-migrations-2026-07-16.sqlite`. Source and backup were both 204,800 bytes with SHA-256 `4EAE3C1BF7DA8C9399D18E6B5B379364F3E4419AE592520483A720C8A25A0827`.
- Applied `2026_07_16_000003_add_curriculum_query_indexes` and `2026_07_16_000004_normalize_email_addresses` in batch 4 after reviewing their pretend output.
- All 3 existing users were already canonical: 0 non-canonical emails and 0 canonical collision groups before/after. Persistent user data therefore did not change.
- No curriculum row, completion record, password-reset record, or media file was created, updated, or deleted. Persistent changes are the new indexes and migration ledger rows only.

### Verification evidence

- Full Laravel suite: 104 passed, 724 assertions, 0 failed.
- Node suite: 25 passed, 0 failed, covering all 14 exercise renderers and the existing learner/media contracts.
- Vite production build: passed; 60 modules transformed. Blade production view compilation passed.
- Syntax checks: 116 PHP files and 13 JavaScript/module files passed.
- Pint check on all new/edited Phase 5 PHP files: passed.
- `composer validate --strict`: valid. Composer audit: 0 advisories, 0 abandoned packages. npm audit: 0 vulnerabilities across 202 dependencies.
- Migration status: both Phase 5 migrations ran in batch 4.
- Reproducible post-migration query plans:
  - published modules: `SEARCH modules USING INDEX modules_published_order_idx`;
  - ordered lessons: `SEARCH lessons USING INDEX lessons_module_order_idx`;
  - supervisor learners: `SEARCH users USING INDEX users_role_instansi_id_idx`.
- Persistent SQLite health: `integrity_check: ok`; 0 foreign-key violations; 0 non-canonical emails; 0 collision groups.
- Authenticated in-app browser verification:
  - learner dashboard showed nonblank lesson counts for all eight published module cards;
  - lesson detail rendered all seven vocabulary categories, text material, and five exercises without 403/500;
  - uppercase supervisor and superadmin login addresses authenticated successfully;
  - supervisor scoping rendered the expected empty state for the confirmed cross-institution seed data;
  - superadmin module creation exposed a bounded order control with the correct default; no record was submitted.

### Research basis

- Laravel 12 Eloquent relationships, eager loading, aggregate counts, and lazy-loading prevention: https://laravel.com/docs/12.x/eloquent-relationships
- Laravel 12 pagination: https://laravel.com/docs/12.x/pagination
- Laravel 12 migrations and indexes: https://laravel.com/docs/12.x/migrations
- Laravel 12 authentication request normalization/attempt behavior: https://laravel.com/docs/12.x/authentication
- Laravel 12 Eloquent mutators: https://laravel.com/docs/12.x/eloquent-mutators
- SQLite `EXPLAIN QUERY PLAN`: https://sqlite.org/eqp.html
- SQLite multi-column index planning: https://www.sqlite.org/queryplanner.html

### Known limitations at this checkpoint

- Institution normalization/enrollment remains blocked on a product decision, as documented above. The existing string and scope behavior are preserved.
- The repository-wide Pint check still reports pre-existing style issues in 28 files outside the Phase 5 edit set. PHP syntax, application tests, and the Phase 5 formatter check pass; broad mechanical formatting is deferred to the cleanup phase to avoid mixing unrelated changes into this checkpoint.
- The production build still reports the previously documented non-blocking Node `module.register()` deprecation from the Vite/toolchain dependency path.
- `npm run test:bundle` no longer exists; the current declared JavaScript test command is `npm run test:js`, which passed. The checkpoint records the current reproducible command rather than assuming the older name.
- The workspace `.git` directory still has no `HEAD`, so Git status/diff evidence is unavailable. This log, isolated regression tests, migration status, query-plan script, and database backup are the reproducible ledger.

### Next action after approval

Begin Phase 6 only after the user says `Continue`: remember-me behavior, auth throttles/generic reset responses, removal of runtime third-party UI dependencies, CSP/security headers, and production deployment checks.

## Checkpoint 6 — Phase 6

Date: 2026-07-16  
State: implementation, automated verification, dependency audit, production-build validation, and authenticated browser verification complete; paused for user review.

### Changes implemented

1. Corrected authentication persistence and abuse controls.
   - Removed the hidden `remember=true` fields that silently created persistent sessions. Login now passes Laravel's authentication attempt only the visible, checked `remember` checkbox; registration no longer asks the browser to persist a new session implicitly.
   - Added independently keyed limits for registration, password-reset-link requests, and password resets. Email buckets use the same trim/lowercase canonicalization as authentication, while a second IP limit prevents rotating email values from bypassing the protection.
   - Password-reset-link requests now always return the same outward message for known and unknown addresses. Laravel's password broker still performs the real delivery decision internally.
2. Removed critical runtime CDN dependencies.
   - Pinned and bundled `@alpinejs/csp@3.15.12` and `@fortawesome/fontawesome-free@7.3.1` through Vite.
   - Replaced the authenticated learner avatar request to `i.pravatar.cc` with a local initial badge. Shared layouts no longer load Alpine, Font Awesome, or Google Fonts from third parties.
   - Reworked all five admin editors into registered `Alpine.data` components. Server-provided modal state is base64-encoded JSON, complex mutations live in `resources/js/admin-forms.js`, and Alpine's CSP interpreter was verified on create/edit dynamic forms.
3. Introduced a strict nonce-based Content Security Policy.
   - Added `SecurityHeaders` as global middleware and uses Laravel Vite's per-request nonce for Vite tags and the two remaining localized learner scripts.
   - Development/testing defaults to `Content-Security-Policy-Report-Only`; the production example requires enforced `Content-Security-Policy`.
   - The policy denies objects, framing, inline event handlers, and style attributes; it contains no `unsafe-inline`, `unsafe-eval`, or wildcard source. Narrow image and YouTube frame sources cover only confirmed current public-page/material behavior.
4. Removed CSP-incompatible DOM styling and inline handlers.
   - Converted learner, module, and supervisor progress indicators to native `<progress>` elements with shared CSS.
   - Exercise feedback, dynamic visibility, sound sorting, and sequencing now use classes instead of `element.style`.
   - Replaced inline logout/delete confirmation handlers with real buttons and delegated event listeners. A static regression test rejects future Blade style/event attributes, unnonced scripts, and inline style blocks.
5. Added defense-in-depth response and transport safeguards.
   - Responses now include `nosniff`, a strict referrer policy, a restrictive permissions policy, clickjacking protection, COOP/CORP, Flash cross-domain denial, and the modern `X-XSS-Protection: 0` value.
   - HSTS is emitted only for secure requests when explicitly enabled. The readiness gate requires at least a one-year max-age and prevents `preload` without `includeSubDomains`.
   - Feature tests prove nonce rotation, report-only/enforcement switching, HTTPS-only HSTS, and `Secure`/`HttpOnly`/`SameSite=Lax` session cookies.
6. Added a fail-closed production deployment gate and runbook.
   - `php artisan hospitrainity:deployment-check` exits non-zero unless production environment/debug/HTTPS/key/cookie/CSP/HSTS settings are safe, `public/hot` is absent, the Vite manifest contains both application entries, `public/storage` resolves correctly, and Composer development packages are absent.
   - Added `.env.production.example` with explicit placeholders and secure defaults plus `docs/PRODUCTION_DEPLOYMENT.md` covering `composer install --no-dev`, `npm ci`, asset build, storage link, migration, optimization, proxy/TLS checks, queues, and the pre-traffic gate.
   - The current local development environment correctly fails this production gate because it intentionally uses local/debug/HTTP, report-only CSP, `public/hot`, and development packages. The manifest, application key, storage link, security headers, HttpOnly, and SameSite checks pass locally.

### Additional defects found and fixed during Phase 6

- The exercise engine tests asserted obsolete `style.display` values even after visibility became class-based. The tests now verify the real `hidden` state and still execute all fourteen renderer workflows, retry behavior, and progress persistence.
- Admin form-state tests expected server values to appear as raw page text. They now decode and validate the structured base64 state, preserving coverage without forcing unsafe executable Blade expressions.
- Layout tests depended on obsolete CDN-era comments. They now prove the compiled self-hosted asset contract and explicitly reject the former CDN hosts.

### Verification evidence

- Full Laravel suite: 116 passed, 910 assertions, 0 failed.
- Authentication hardening subset: 16 passed, 75 assertions, including remember-cookie/token behavior, generic known/unknown reset responses, canonicalized buckets, and all three new throttles.
- Node suite: 25 passed, 0 failed; all fourteen exercise renderers completed their tested keyboard/retry/persistence paths.
- Vite production build: passed; 62 modules transformed and a valid manifest emitted with local Alpine CSP, Font Awesome CSS, and font assets.
- Blade production view compilation: passed.
- Pint check on all Phase 6 PHP files: passed. `composer validate --strict --no-check-publish`: valid.
- Composer audit: 0 advisories and 0 abandoned packages. npm audit: 0 vulnerabilities at every severity across 206 installed dependencies.
- `composer install --no-dev --prefer-dist --optimize-autoloader --dry-run`: lock/platform validation passed and planned removal of 35 development packages without changing the working installation.
- Authenticated in-app browser verification:
  - the CSP Alpine build opened and closed the module editor;
  - the exercise editor opened, switched from matching to sound sorting, and added a dynamic category;
  - no record was submitted and the final browser warning/error log was empty.
- Source scan: no Blade inline style/event attributes, inline style blocks, unnonced scripts, authenticated third-party avatars, or layout CDN dependencies remain.

### Research basis

- Laravel 12 authentication and remember sessions: https://laravel.com/docs/12.x/authentication
- Laravel 12 rate limiting: https://laravel.com/docs/12.x/rate-limiting and https://laravel.com/docs/12.x/routing#rate-limiting
- Laravel 12 password reset broker: https://laravel.com/docs/12.x/passwords
- Laravel 12 Vite CSP nonces: https://laravel.com/docs/12.x/vite#content-security-policy-csp-nonce
- Laravel 12 deployment optimization and debug requirements: https://laravel.com/docs/12.x/deployment
- Alpine CSP build and supported expression model: https://alpinejs.dev/advanced/csp
- OWASP HTTP security headers: https://cheatsheetseries.owasp.org/cheatsheets/HTTP_Headers_Cheat_Sheet.html
- OWASP CSP and HSTS guidance: https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html and https://cheatsheetseries.owasp.org/cheatsheets/HTTP_Strict_Transport_Security_Cheat_Sheet.html
- MDN strict nonce-based CSP guidance: https://developer.mozilla.org/en-US/docs/Web/Security/Practical_implementation_guides/CSP

### Known limitations at this checkpoint

- No production host, TLS terminator, or trusted-proxy configuration was supplied, so a real edge deployment was not mutated or claimed as tested. The HTTPS/HSTS/cookie behavior is covered by secure-request feature tests, the production checker, and the documented live validation path.
- Local development intentionally retains `public/hot`, debug mode, HTTP cookies, report-only CSP, and Composer development dependencies. Removing these locally would break the development workflow; the fail-closed deployment command detects them before production traffic.
- HSTS `includeSubDomains` and `preload` remain off until every subdomain is separately proven HTTPS-only. This is deliberate to avoid making unrelated subdomains unreachable.
- The public marketing page still uses confirmed third-party placeholder/testimonial images under an exact CSP allowlist. Those unauthenticated marketing claims/assets are scheduled for Phase 7 consistency work; authenticated screens no longer make that request.
- Material and vocabulary learner pages retain localized, nonce-protected inline scripts because their strings and route data are server-rendered. They comply with the enforced policy without `unsafe-inline`; a later module extraction is optional rather than a security blocker.
- The production build still reports the previously documented non-blocking Node `module.register()` deprecation from the current Vite/toolchain dependency path.
- The workspace `.git` directory still has no `HEAD`, so Git status/diff evidence is unavailable. This checkpoint log, test output, build manifest, browser verification, audits, and deployment-check output are the reproducible ledger.

### Next action after approval

Begin Phase 7 only after the user says `Continue`: product naming/copy consistency, placeholder links/claims, WAI-ARIA dialog/menu/accessibility work, rendered-markup validation, and localization parity.

## Checkpoint 7 — Phase 7

Date: 2026-07-16  
State: implementation, automated verification, production build, and in-app browser keyboard verification complete; paused for user review.

### Revised-roadmap baseline

- Re-read the user-edited roadmap before implementation. Its SHA-256 at the start of Phase 7 was `11251BAFA23A9A820B7C5CD1830470C67BA283F55CA30C529770B0DE1EF191B5`.
- The revision made HSP-P2-023 a Phase 7 release gate: authenticated guest routes, successful login, and every email-verification exit must resolve by role without allowing stale/cross-role intended URLs to trigger a forbidden destination or bypass role middleware.

### Changes implemented

1. Added one fail-closed role landing resolver.
   - `user` resolves to `dashboard`, `supervisor` to `supervisor.dashboard`, and `superadmin` to `superadmin.dashboard`.
   - Unknown roles throw an authorization exception and never fall back to the learner dashboard.
   - Laravel's authenticated-guest redirect callback, successful login, verified notice exit, verification completion, and already-verified resend exit all use the same resolver.
   - Stored intended destinations are deliberately ignored. This includes stale learner URLs, cross-role URLs, and external URLs. Direct role middleware remains unchanged and still returns 403 for cross-role requests.
2. Completed user-visible Hospitrainity consistency.
   - Replaced every split `Stay<span>Ready</span>` occurrence in learner auth/navigation, supervisor, and superadmin surfaces.
   - Removed every `href="#"` placeholder. Real dashboard/home routes replace implemented destinations; the unimplemented Profile and fake social actions were removed rather than presented as functional.
   - Added descriptive, localized titles for public, auth, learner content, supervisor, and superadmin pages. Module/lesson/practice titles include their actual content name.
3. Replaced unsupported public marketing content with implemented product facts.
   - Removed fabricated testimonials/identities, “thousands of learners,” AI tutor, instant feedback, community, personalized-study-plan, free-trial, and broad efficacy claims.
   - Public copy now describes verified functionality: structured published modules/lessons, vocabulary/material media, supported interactive exercises, and persisted completion tracking.
   - Replaced third-party placeholder/avatar imagery with a code-native product overview and removed both obsolete origins from CSP.
   - Copyright uses the runtime year; registration no longer claims agreement to missing Terms/Privacy destinations and now validates a neutral information-confirmation checkbox with a field error.
4. Implemented keyboard-complete dialogs and navigation menus.
   - All five admin editors use labelled modal-dialog semantics, visible close/cancel controls, initial focus on an error summary or static title, Escape close, forward/reverse Tab wrapping, outside-click close, and focus return to the create/edit opener.
   - The learner account control is an ARIA menu button. Enter/Space/Arrow keys open it; Arrow/Home/End move through real Dashboard/Logout menu items; Escape closes and restores focus; Tab closes without blocking natural focus order.
   - The public mobile navigation disclosure updates its accessible label/expanded state, closes after navigation, and supports Escape with focus return.
   - Added a global high-contrast `:focus-visible` outline without suppressing component focus rings.
5. Corrected remaining accessibility and markup issues.
   - Fixed the unmatched closing `div` in the lesson template.
   - Retained native progress elements with bounded values/max and accessible names across learner/supervisor screens.
   - Added names to icon-only admin actions and dynamic exercise controls, repaired dynamic vocabulary/material label associations, added table column scopes, and secured new-tab media links with `noopener noreferrer`.
   - Added a rendered DOM accessibility contract scan covering language/title, placeholder links, image alternatives, duplicate IDs, button names, progress semantics, and dialog labelling across ten key pages. A source assertion locks balanced lesson containers.
6. Completed admin/supervisor/auth localization coverage.
   - Added parity-matched `lang/en/admin.php` and `lang/id/admin.php` groups for all admin and supervisor UI, editor labels, destructive confirmations, empty states, and all fourteen exercise-type names.
   - Added English/Indonesian JSON entries for the new public/auth copy, titles, menu names, authorization messages, reset/verification messages, and accessible labels.
   - Extended locale parity tests to recursively compare the new grouped translations as well as JSON keys.

### Additional defects found and fixed during Phase 7

- The revised HSP-P2-023 evidence was reproduced: Laravel's default authenticated-guest redirect targets the global learner dashboard, login's `redirect()->intended(...)` could override role fallbacks, and email-verification exits hard-coded the learner dashboard. The resolver and regression matrix fix all three without weakening authorization.
- The material-type label targeted `for="type"` while the select ID was `type-selector`; the association now matches.
- Reverse Tab from the intentionally focused static dialog title could have escaped because the title is not part of the normal focusable list. The trap now wraps Shift+Tab from that title to Save; the in-app browser reproduced the corrected behavior.
- The first full suite after localization had one expected assertion mismatch: the pagination smoke test still required the former hardcoded Indonesian heading. It now asserts the current translated heading and passes in the full suite.

### Verification evidence

- Full Laravel suite: 127 passed, 1,243 assertions, 0 failed, 0 risky.
- Focused Phase 7 regression set: 25 passed, 545 assertions before the later full-suite run. The dedicated role landing/matrix set passed 9 tests with 116 assertions.
- Node suite: 25 passed, 0 failed; all fourteen learner exercise renderers and existing progress/media safety contracts remain green.
- Vite production build: passed; 62 modules transformed and a new manifest/application bundle emitted.
- Blade production compilation: passed. Composer manifest validation: valid.
- Phase 7 PHP formatting scope: passed. Repository-wide Pint still reports 23 pre-existing files outside this phase's edit set.
- Composer audit: 0 advisories, 0 abandoned packages. npm audit: 0 vulnerabilities at every severity across 206 dependencies.
- Source/rendered consistency scans: no split legacy brand, `href="#"`, `Document` title, third-party placeholder/avatar host, or visible unsupported marketing copy remains.
- In-app browser verification on the built assets:
  - public desktop/mobile content rendered with the correct 2026 year and no placeholder/fake testimonial assets;
  - mobile navigation opened with `aria-expanded=true`, changed its label to Close, and Escape closed it with focus on the trigger;
  - admin dialog focus opened on its title, Tab from Save wrapped to Close, Shift+Tab from the title wrapped to Save, and Escape restored focus to Create module;
  - learner menu opened with ArrowDown on Dashboard, ArrowDown moved to Logout, and Escape closed it with focus restored to the menu button;
  - authenticated `/login` resolved the existing superadmin session directly to the superadmin dashboard;
  - final browser warning/error log was empty. No CRUD form or curriculum/progress mutation was submitted.

### Research basis

- Laravel 12 authentication redirects and intended destinations: https://laravel.com/docs/12.x/authentication
- Laravel 12 email verification request/fulfilment flow: https://laravel.com/docs/12.x/verification
- Laravel 12 localization and JSON/grouped translation files: https://laravel.com/docs/12.x/localization
- WAI-ARIA modal dialog pattern: https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/
- WAI-ARIA menu button pattern and link-menu example: https://www.w3.org/WAI/ARIA/apg/patterns/menu-button/ and https://www.w3.org/WAI/ARIA/apg/patterns/menu-button/examples/menu-button-links/
- WCAG 2.2 page title, language, focus order/visibility, keyboard, and status criteria: https://www.w3.org/TR/WCAG22/
- HTML Living Standard native progress element: https://html.spec.whatwg.org/multipage/forms.html#the-progress-element
- Nu HTML Checker scope/limitations: https://validator.w3.org/nu/about.html

### Known limitations at this checkpoint

- Intended destinations are ignored for every successful login/verified exit, including a valid same-role destination. Preserving them safely requires an explicit same-origin check plus authorization against the resolved user's role; Phase 7 chooses the roadmap's fail-closed direct-landing option instead of inventing an authorization shortcut.
- The automated scan is a deterministic application-specific accessibility markup contract, and the manual keyboard pass used the current in-app Chromium surface. This is strong regression evidence, not a claim of complete WCAG conformance or screen-reader/cross-browser certification. A maintained multi-browser/assistive-technology E2E matrix remains Phase 9 work.
- Public registration still selects an existing free-form institution string. The enrollment/normalization product decision remains intentionally unresolved and unchanged from Phase 5.
- The production build still reports the previously documented non-blocking Node `module.register()` deprecation from the installed Vite/toolchain path.
- Repository-wide Pint reports 23 legacy files outside the Phase 7 scope. All Phase 7 PHP files pass Pint, and the full runtime/test suite is green; broad formatting remains Phase 9 cleanup.
- The workspace `.git` directory still has no `HEAD`, so Git status/diff evidence remains unavailable. This checkpoint, tests, build artifacts, audits, and browser results are the reproducible ledger.

### Next action after approval

Begin Phase 8 only after the user says `Continue`: preserve the canonical package/checksum, design a versioned idempotent import with dry-run/diff/report/rollback evidence, map approved curriculum semantics without flattening unsupported data, and generate both Laravel and standalone projections from one declared source of truth.

## Checkpoint 8 — Phase 8

Date: 2026-07-16  
State: implementation, persistent import, automated verification, production build, and in-app browser verification complete; paused for user review.

### Roadmap and evidence baseline

- Re-read the roadmap before implementation. Its SHA-256 remained `11251BAFA23A9A820B7C5CD1830470C67BA283F55CA30C529770B0DE1EF191B5`.
- Preserved `curriculum/hospitrainity/0.3.0-draft` as the sole declared curriculum source. The package has 455 files / 391,535 bytes. Its deterministic tree digest is `44bd6bfbdc4bf3d76b4c72279c064d890bdb6d109f846b0888f69d375f8feec3`.
- Added the immutable CP-14 evidence record at `curriculum/evidence/phase-14.json` (SHA-256 `57337d6a796d9f91291e3d2fb834fd47794fac4fe32515f19e66d7525825f0de`).
- Preserved the approved standalone artifact at `standalone/Hospitrainity-Standalone.html`: 94,883 bytes, SHA-256 `471aedc0725981ea4e9b8592e2226626c716eb996f2e28255c8c091d0cb115bb`.
- The tree digest is defined over ordinally sorted relative paths and each file's raw SHA-256/byte count. This definition is application-specific deterministic serialization; it is not described as full RFC 8785 JSON canonicalization.

### Changes implemented

1. Added a normalized, lossless canonical curriculum schema.
   - `curriculum_packages` records source/version/schema/lifecycle/projection/checksum state and the single active package.
   - `curriculum_source_files` retains every source file's relative path, raw checksum, size, media type, and exact payload.
   - `curriculum_entities` stores typed entities without coercing their source payloads into the legacy module/lesson/exercise model.
   - `curriculum_links` stores explicit source relationships; `curriculum_import_runs` owns reports, before/after checksums, and rollback evidence.
   - The persistent import contains 1 package, 455 source files, 501 entities, and 601 links. Typed counts include 7 chapters, 85 sections, 24 activities, 102 prompts, 102 answer models, 102 feedback models, 6 rubrics, 21 outcomes, 7 competencies, 23 CEFR references, 7 source-provenance records, and 8 migration edges.
2. Added one versioned import/verification pipeline.
   - `php artisan hospitrainity:curriculum dry-run --report=<path>` validates and reports a full diff without writes.
   - `php artisan hospitrainity:curriculum import --report=<path>` validates, snapshots, transactionally imports, regenerates the standalone projection, and emits a report.
   - `php artisan hospitrainity:curriculum verify` re-reads the source and checks the active database projection and standalone checksum.
   - `php artisan hospitrainity:curriculum generate` regenerates only the standalone projection from the same source.
   - `php artisan hospitrainity:curriculum rollback --rollback=<recorded-artifact>` accepts only a checksum-matching artifact owned by a recorded import run and located under the configured rollback directory.
   - Validation covers package/version/checksum declarations, UUID/code uniqueness, source locators, content versions, publication values, internal references, ordering, expected counts, prompt answer/feedback cardinality, and the approved standalone digest.
   - Re-importing the same source is idempotent. A reused content version with different source bytes fails instead of silently rewriting history.
3. Added failure-safe artifacts and rollback.
   - The database projection is changed in a Laravel transaction. Standalone generation is staged and atomically promoted only after checksum verification.
   - Every mutating import stores a checksum-owned snapshot of all canonical tables and the prior standalone state. A failed import automatically restores both database and output.
   - Before the persistent migration/import, SQLite `VACUUM INTO` created a transactionally consistent backup at `storage/app/private/backups/phase8-database-before-canonical-import-2026-07-16.sqlite` (237,568 bytes, SHA-256 `3d1d7dd46e8ab4badec9ed0af423b43ff21aed2b3ab51b3f2498671dbfae1665`). Its integrity check is `ok` and it has zero foreign-key violations. The companion evidence JSON has SHA-256 `14ea31d9fc93da90f03a185a05f9fcb43b2bd7ee87692686481997d744c5c872`.
4. Generated both delivery surfaces from the same source.
   - The approved standalone shell was extracted only after its CP-14 checksum was validated; the generator replaces its exact data projection and reproduces the approved file byte-for-byte.
   - Laravel learner delivery now reads the active canonical package: seven chapter cards, sections, outcomes, activities, prompts, answer models, feedback, rubrics, source/lifecycle evidence, and activity progress.
   - Exact source wording remains English. Interface text follows the selected locale and explicitly refuses invented translations.
   - Unsupported assessment response forms are not flattened into the fourteen legacy engines. Prompts and scoring semantics are shown intact; the learner explicitly marks the entire activity complete after review.
5. Explicitly retired legacy learner delivery and legacy administration writes.
   - Legacy learner module/lesson/practice/material/exercise routes return HTTP 410 while a canonical package is active.
   - Legacy rows remain queryable to superadmins as audit/rollback evidence, but all create/update/delete controls are removed and every one of the 15 legacy write endpoints fails closed with HTTP 410.
   - The five retained managers identify their records as read-only evidence and direct administrators to the versioned importer. Existing legacy rows and completion history were not deleted or rewritten.
6. Unified seeding and regression coverage.
   - `DatabaseSeeder` invokes the same canonical importer used in deployment; it no longer fabricates a second curriculum through legacy seeders.
   - Added package-reader, import, rollback, tamper, deterministic-output, active-delivery, authorization, localization, completion, legacy-retirement, and write-boundary tests.

### Persistent import evidence

- Pre-import dry-run report: `storage/app/private/curriculum/reports/phase8-persistent-dry-run.json`, SHA-256 `6653783c9a1bde178e5f2c6fe3b6bba7bb835e77669346393e693a16d777c3bb`; status `create`, 1,558 planned writes.
- Import run `0256fc4c-16ef-45fd-adbb-c7c4b90ba6dd` completed and produced report SHA-256 `1849d151e47e4e2e857721e158b861725c89d9e26bff1de9fcbe4c27d6efb7bc`.
- Its before-state rollback artifact is `storage/app/private/curriculum/rollbacks/0256fc4c-16ef-45fd-adbb-c7c4b90ba6dd-before.json`, 126,916 bytes, SHA-256 `faba7f66f4bb7448be4dd135e92581325c2927b7db3cf1b2278cd9b835d9fb0d`.
- Second import run `b878c365-9dd9-4240-8100-6dfffd5b25c2` reported `no_changes`, zero planned writes, and identical canonical before/after digests. Report SHA-256: `9c77a5cb227b93fcb013fb578f148615c75df4cfdcf85f8100005fb010e1b26a`.
- Final logical projection SHA-256 is `567eda4a1162777857642c7fc9f6f61182d3a3d304944270e51f3e08f9f00fb0`; final standalone SHA-256 is the approved CP-14 `471aedc...15bb`.
- Persistent SQLite verification reports `integrity_check=ok`, zero foreign-key violations, and unchanged legacy counts: users 3, modules 8, lessons 24, vocabularies 82, vocabulary items 244, materials 24, material items 24, exercises 36, completions 1.

### Additional defects found and fixed during Phase 8

- A first external checksum check used PowerShell's culture-sensitive `Sort-Object` and produced a different tree digest. The PHPUnit ordinal-sort contract caught it. The package reader and tests now use explicit ordinal sorting, and the proven digest is `44bd6b...feec3`.
- The first rendered admin pass showed write controls under a notice saying the legacy managers no longer control delivery. Those controls and forms are now absent, the wording is read-only, and a server-side middleware rejects stale/direct legacy writes. The regression test covers store/update/delete for all five resources.
- The source itself contains distinct lifecycle layers: `package.json` and provenance records say `draft` / `0.3.0-draft`, while activity/chapter projection records and the immutable CP-14 evidence say `published` and the CP-02 gate is closed. The importer preserves these facts separately instead of inventing a reconciled status.
- The canonical package supplies section identities, titles, ordering, and provenance, but it does not contain full prose bodies for non-assessment sections. Laravel now states that absence explicitly instead of copying legacy prose or fabricating content.

### Verification evidence

- Full Laravel suite: 140 passed, 1,357 assertions, 0 failed, 0 risky.
- Focused canonical delivery suite after the read-only retirement correction: 6 passed, 53 assertions.
- Node suite: 25 passed, 0 failed; the existing fourteen legacy engines and progress/media safety contracts remain green.
- Vite production build: passed; 62 modules transformed. The existing non-blocking Node `DEP0205 module.register()` deprecation remains in the installed toolchain.
- Blade production compilation: passed. All Phase 8 PHP files pass Pint.
- Final canonical verification: all declared counts match; source tree, Laravel logical projection, and standalone checksums match; SQLite integrity is `ok`; foreign-key violations are zero.
- In-app browser verification on `127.0.0.1:8001`:
  - superadmin managers render the active version/tree digest, retained rows, read-only evidence labels, and no create/edit/delete controls;
  - the learner dashboard renders all seven published chapter projections with exact section/activity counts and bounded accessible progress elements;
  - chapter 2 renders 13 ordered sections, three outcomes, four activity links, and explicit missing-body/source-lifecycle disclosures;
  - its quiz renders eight exact prompts and expandable model-answer/feedback evidence; no completion mutation was submitted.

### Research basis

- Laravel 12 database transactions: https://laravel.com/docs/12.x/database#database-transactions
- Laravel 12 Artisan commands: https://laravel.com/docs/12.x/artisan#writing-commands
- Laravel 12 migrations: https://laravel.com/docs/12.x/migrations
- SQLite transaction semantics: https://www.sqlite.org/lang_transaction.html
- SQLite `VACUUM INTO` backup behavior: https://www.sqlite.org/lang_vacuum.html#vacuum_with_an_into_clause
- PHP same-filesystem `rename()` behavior used by atomic output promotion: https://www.php.net/manual/en/function.rename.php
- RFC 8785 JSON Canonicalization Scheme, used to distinguish the standard from this domain-specific tree digest: https://www.rfc-editor.org/rfc/rfc8785

### Known limitations at this checkpoint

- A content owner still needs to resolve the source package's package-level `draft` status versus the CP-14/entity-level `published` status in a future versioned package. The application exposes both and does not alter the immutable input.
- Non-assessment section prose is absent from the approved canonical package. Adding it requires a new source version; reusing legacy text would break provenance and is not authorized.
- Canonical activities are currently review/self-completion experiences. The importer preserves scoring types and rubrics, but no automated grader was invented for role-play, free response, or other unsupported response forms.
- The physical SQLite file checksum changes during ordinary authenticated use because runtime tables such as database-backed sessions are mutable. Import gates therefore use the immutable source digest, canonical logical projection digest, standalone digest, table counts, integrity check, and foreign-key check. The pre-import backup retains its recorded physical checksum.
- The workspace `.git` directory still has no `HEAD`, so Git status/diff evidence remains unavailable. This checkpoint, source/package hashes, import reports, rollback artifact, database backup, tests, build, and browser evidence form the reproducible ledger.

### Next action after approval

Begin Phase 9 only after the user says `Continue`: add the remaining JavaScript lint/browser E2E and CI gates, diagnose risky output-buffer tests, replace README boilerplate with complete setup/architecture/deployment/curriculum runbooks, remove dead code under coverage, and perform the isolated repository-wide formatting/cleanup pass.

## Checkpoint 9 — Phase 9

Date: 2026-07-17  
State: implementation, clean-install verification, automated release gates, and rendered browser verification complete; paused for user review.

### Changes implemented

1. Added a reproducible JavaScript quality stack.
   - Added ESLint 10 flat configuration with zero-warning enforcement over maintained application, Node-test, E2E, and configuration JavaScript.
   - Expanded the Node suite to 36 tests. New contracts cover progress responses 419/422/429/500, network failure, untrusted exercise fields, pending/double-click/navigation behavior, and retry after persistence failure.
   - Added Playwright Chromium E2E for keyboard-operable public mobile navigation, verified learner navigation/progress/reload/account-menu behavior, and the superadmin canonical read-only boundary.
   - E2E uses a disposable SQLite database and isolated session, cache, compiled-view, curriculum-report, rollback, standalone, and Vite-hot-file paths. It does not use or mutate the working application database.
2. Made PHP issue handling and CI blocking.
   - PHPUnit now displays and fails on all issues, is strict about test output, and permanently disallows output from passing tests.
   - Replaced permissive CI with least-privilege, concurrency-controlled jobs and immutable revisions of third-party actions.
   - Composer validation/audit, npm audit, ESLint, Node tests, Vite production build, strict Laravel tests, Blade compilation, Pint, and Chromium E2E are blocking gates. No `continue-on-error` bypass remains.
3. Replaced framework boilerplate with an operator runbook.
   - `README.md` now documents features, roles, architecture, requirements, Windows and POSIX setup, the existing-institution verified-email flow, storage/mail/queue/scheduler configuration, canonical curriculum commands, testing, and deployment checks.
   - The production runbook explicitly covers `public/hot`, the Vite manifest, non-dev Composer installation, migrations, storage linking, caches, HTTPS/security settings, workers, scheduler, and `hospitrainity:production-check`.
4. Removed dead code and tightened covered contracts.
   - Removed Axios, its unused bootstrap import, and four unused legacy form scripts. Compatibility globals that are still consumed by learner Blade scripts were retained.
   - Added relation and response return types only to controller, middleware, and model boundaries exercised by the regression suite.
   - Ran the isolated repository-wide Pint correction, then made Pint blocking; the final repository-wide check is clean.
5. Recorded destructive-change recovery/versioning.
   - `docs/decisions/ADR-001-curriculum-recovery.md` records the active canonical policy: immutable checksum-gated source versions, transactional/idempotent import, generated delivery projections, recorded rollback artifacts, and read-only legacy audit evidence.
   - Direct legacy writes remain HTTP 410 while the canonical package is active. No second soft-delete scheme was added to the generated canonical projection because it would create a competing source of truth.
6. Modernized and stabilized the build/test harness.
   - Added configurable `VITE_HOT_FILE` handling so isolated/production-like tests cannot be diverted by a stale workspace `public/hot` marker.
   - Replaced Playwright's hanging Windows child-process shutdown path with explicit cross-platform server setup/teardown owned by the E2E harness.
   - Updated direct Tailwind packages from 4.1.10 to the official current 4.3.3 release. This removed the traced `@tailwindcss/node` use of deprecated `module.register()` on Node 26 without changing JavaScript bundle output or breaking CSS/browser tests.

### Defects found and corrected during Phase 9

- The first production-like E2E run could render HTML while silently requesting JavaScript from an unrelated Vite development server because `public/hot` is global. The application now supports a configured hot-file path, and E2E assigns an isolated nonexistent marker so it must consume the built manifest.
- Playwright's automatic Windows web-server termination could leave the command hanging after all tests had passed. The harness now starts a hidden PHP process tree explicitly and tears it down after every run.
- Two early browser assertions depended on a control's changing accessible name and on legacy rows existing in a freshly seeded E2E database. They were corrected to assert stable behavior and the canonical read-only policy, rather than weakening or padding production data.
- A first clean `npm ci` was blocked by a running workspace Vite process holding a native module; a later registry transfer also ended with `ECONNRESET`. The workspace process was stopped, the transient network operation was retried, and the final lockfile was independently reinstalled successfully with `npm ci`.
- The build emitted Node 26 `DEP0205`. A trace proved the call originated in `@tailwindcss/node` 4.1.10. Updating the two direct Tailwind packages together to 4.3.3 removed the warning; a traced build, full regressions, and a second clean install all passed.

### Final verification evidence

- Clean dependency reproduction: `npm ci --no-audit --no-fund` installed 176 packages from `package-lock.json` successfully.
- Strict Laravel suite: 140 passed, 1,357 assertions, zero failures, zero risky tests, and no disallowed output.
- JavaScript: ESLint zero warnings; Node 36 passed, zero failed; Chromium E2E 3 passed, zero failed.
- Production assets: Vite 6.4.3 built 9 modules after the clean install; the Node 26 deprecation is absent.
- Supply chain: `composer validate --strict` valid; Composer advisories and abandoned packages empty; npm reports zero vulnerabilities at every severity.
- Style/templates: repository-wide Pint passed; Blade templates cached successfully.
- Canonical curriculum verification: status `verified`; source tree SHA-256 `44bd6bfbdc4bf3d76b4c72279c064d890bdb6d109f846b0888f69d375f8feec3`; Laravel projection SHA-256 `567eda4a1162777857642c7fc9f6f61182d3a3d304944270e51f3e08f9f00fb0`; standalone SHA-256 `471aedc0725981ea4e9b8592e2226626c716eb996f2e28255c8c091d0cb115bb`; all declared entity counts match.
- Rendered in-app Chromium at `127.0.0.1:8010`: production-manifest CSS/JS loaded, the mobile navigation had one accessible trigger, click/open and Escape/close updated label/expanded/visibility state, and the browser console contained no warning or error. The temporary server, tabs, and viewport override were cleaned up afterward.

### Research basis

- Laravel 12 testing: https://laravel.com/docs/12.x/testing
- Laravel 12 deployment and optimization: https://laravel.com/docs/12.x/deployment
- Laravel 12 installation and local setup: https://laravel.com/docs/12.x/installation
- Laravel 12 filesystem and public storage links: https://laravel.com/docs/12.x/filesystem
- PHPUnit 11 risky-test rules: https://docs.phpunit.de/en/11.5/risky-tests.html
- ESLint flat configuration: https://eslint.org/docs/latest/use/configure/configuration-files
- Playwright CI and browser installation: https://playwright.dev/docs/ci and https://playwright.dev/docs/browsers
- Playwright web-server testing: https://playwright.dev/docs/test-webserver
- npm audit command behavior: https://docs.npmjs.com/cli/v11/commands/npm-audit/
- Composer validate/audit behavior: https://getcomposer.org/doc/03-cli.md
- GitHub Actions secure-use guidance: https://docs.github.com/en/actions/reference/security/secure-use
- Node release/LTS status: https://nodejs.org/en/about/previous-releases
- Current Tailwind direct-package versions were verified against the official npm registry before the paired update.

### Known limitations at this checkpoint

- The roadmap requests an invitation, approved-domain, or join-code institution enrollment rule, but no policy, issuer, domain ownership rule, invitation schema, or code lifecycle has been approved. The existing exact-institution selection rule remains documented and covered by three registration tests. This is an explicit product/security decision blocker for the alternative production enrollment model, not an omitted code fix.
- The E2E matrix is Chromium-only and the rendered smoke pass is not a claim of full WCAG conformance, assistive-technology certification, or cross-browser compatibility. The deterministic keyboard/accessibility contracts are regression evidence within that scope.
- The in-app browser control's synthetic Space/Enter helper did not activate the mobile button even though the same rendered control responded to click/Escape and the real Playwright Chromium keyboard journey passed. No console error occurred. This is recorded as a browser-control limitation; the automated browser test is the reproducible keyboard evidence.
- Composer printed a local cache-directory permission warning while still completing the green test run. The application and dependency graph are unaffected; CI uses its own writable runner cache.
- The workspace `.git` directory still has no `HEAD`, so Git status/diff evidence is unavailable. This checkpoint, the lockfiles, tests, audits, build artifacts, checksums, and browser results are the reproducible ledger.

### Next action after approval

Phase 9 is the final roadmap phase. After the user accepts this checkpoint, resolve the institution enrollment product/security policy before representing the alternative invitation/domain/join-code flow as production-ready; then run the documented CI/deployment gates in the actual target environment. No Phase 10 work is implied.

## Administration Checkpoint ADM-0 — Authority, terminology, and truthful metrics

Date: 2026-07-17  
State: technical implementation and verification complete; paused for product-owner approval of ADR-002 before ADM-1.

### Changes implemented

1. Corrected the superadmin dashboard's curriculum authority.
   - Replaced the misleading legacy `Module::count()` card with an active canonical chapter count derived from the active `CurriculumPackage` relationship.
   - Added active version, package lifecycle, schema, import time, chapter, section, activity, total-version, draft-lifecycle-version, and inactive-version facts.
   - Kept legacy module/lesson/vocabulary/material/exercise counts only in a separately labeled read-only evidence panel.
   - Excluded null/blank institution values from the institution aggregate.
2. Made retained legacy status unambiguous.
   - Replaced “Content management” and all five “Manage …” sidebar labels with “Legacy evidence” terminology whenever a canonical package is active.
   - Added explicit read-only badges, canonical version/lifecycle/checksum notice, archival page titles/headings, and a statement that those rows do not control learner delivery.
   - Preserved the prior management labels and writable fallback behavior only when no canonical package is active; supported canonical deployments still reject all direct legacy writes with HTTP 410.
3. Kept English and Indonesian administration terminology in parity.
   - Added matched translation keys for canonical metrics, version inventory, legacy evidence, five archive destinations, and lifecycle notices.
   - Added rendered English coverage for all five screens and an Indonesian archive rendering check.
4. Recorded the administration authority baseline before routes/roles expand.
   - Added `docs/decisions/ADR-002-administration-authority-and-data-boundaries.md` with proposed fail-closed role, progress privacy, publication, import, export, and audit-retention decisions.
   - Added `docs/decisions/ADM-0-administration-capability-matrix.md` distinguishing the currently enforced three-role surface from the proposed four-role target and its phase/test gates.
   - Recorded explicitly that `admin` does not yet exist and must fail closed until ADM-1 implements the domain value, landing behavior, policies, and tests.
5. Rebuilt production assets so the new responsive/read-only interface classes are present in the generated CSS manifest.

### New problem found and disposition

- The existing retained legacy media validators allow individual files up to 5 MB, but the audited PHP runtime reports `upload_max_filesize=2M` and `post_max_size=8M`. The active canonical boundary makes those legacy writes HTTP 410, so current learner delivery is unaffected. ADR-002 limits the proposed first canonical import/assets to the proven 2 MiB runtime ceiling unless application, PHP, proxy, storage, timeout, and abuse-test limits are deliberately raised together. This must be rechecked in the real deployment before ADM-3 release.

### Verification evidence

- Focused canonical/ADM-0 suite: 8 passed, 103 assertions.
- Full strict Laravel suite: 142 passed, 1,407 assertions, zero failures or reported issues.
- Translation parity: English and Indonesian grouped/JSON keys match.
- PHP syntax and targeted Pint: passed.
- Blade compilation: cached successfully.
- Production asset build: Vite 6.4.3 built 9 modules successfully.
- Route inventory: still 21 superadmin routes; ADM-0 added no user, role, progress, authoring, import, or publication route prematurely.
- Rendered in-app browser dashboard: active canonical version `0.3.0-draft`; 7 chapters, 85 sections, 24 activities; canonical lifecycle `draft`; one total/draft-lifecycle version and zero inactive versions.
- Rendered legacy evidence: 8 modules, 24 lessons, 82 vocabulary records, 24 material records, and 36 exercises are visibly separated from active delivery.
- Rendered archive matrix: all five destinations had the expected Legacy Evidence heading/read-only state; retired “Manage …” labels and create controls were absent.
- Browser cleanup: the original learner session was restored and the temporary audit tab was closed.

### Research basis

- Laravel 12 authorization/policies: https://laravel.com/docs/12.x/authorization
- Laravel 12 Eloquent aggregate queries: https://laravel.com/docs/12.x/eloquent#retrieving-aggregates
- Laravel 12 localization and language-file keys: https://laravel.com/docs/12.x/localization
- Laravel 12 content-aware file validation and bounded sizes: https://laravel.com/docs/12.x/validation#validating-files

### Limitations and approval gate

- No `admin` role, user/role management, canonical authoring/import, global progress screen, export, or publication UI was added in ADM-0. Those belong to later ordered phases.
- ADR-002 is proposed, not silently treated as owner-approved. Its defaults give `admin` draft authoring plus aggregate/de-identified progress, reserve identity-level global progress/publication/role assignment for `superadmin`, begin with DOCX-only 2 MiB import, disable export, and recommend 365-day online audit retention.
- The canonical package still truthfully exposes its existing package lifecycle `draft` even though chapter entities are published. ADM-0 does not rewrite immutable source lifecycle evidence.
- The workspace `.git` directory still has no `HEAD`, so Git diff/status evidence is unavailable. Tests, rendered checks, generated manifests, decision records, and this checkpoint form the reproducible ledger.

### Rollback

No migration or production data mutation was introduced. To roll back ADM-0, restore the prior controller, eight superadmin Blade partial/pages, two administration translation groups, canonical delivery test, roadmap/checkpoint documents, and remove ADR-002/capability-matrix documents; then rerun the production build, Blade compilation, and full test suite. Do not remove the canonical write lock or mutate retained evidence.

### Next action after approval

The product owner must approve ADR-002 as written or identify revisions. After approval and an explicit `Continue`, begin ADM-1: add the explicit role domain/constraint, `admin` landing and policies, superadmin user directory, dedicated high-assurance role-change flow, audit record, target-session revocation, and full four-role/unknown-role matrix.

## Administration Checkpoint ADM-1 — Explicit roles and secure user administration

Date: 2026-07-17  
State: implementation and verification complete; paused for product-owner review before ADM-2.

### Changes implemented

1. Established one constrained four-role domain.
   - Added the backed `UserRole` enum with `user`, `supervisor`, `admin`, and `superadmin` values, typed model casting, landing routes, and privilege helpers.
   - Removed `role` from generic `User::$fillable` so registration/profile-style mass assignment cannot escalate an account.
   - Added a migration that inventories and reports unsupported existing values before changing the role column, applies the database constraint, and creates the administration audit table.
   - Updated route middleware to reject unknown configured role values instead of weakening to a fallback.
2. Added the narrow `admin` surface approved in ADR-002.
   - Added a distinct `/admin` landing and five read-only retained-evidence indexes.
   - Updated navigation, dashboard titles, policies, factories, seeders, controller queries, and the complete landing/route matrix.
   - `admin` receives no role management, retained-evidence writes, learner delivery, publication, or identity-level progress authority.
3. Added protected superadmin user administration.
   - Added a 20-row paginated directory with fixed-field server-side search and validated filters for role, institution, and verification state.
   - Selected only the account fields required by the screen; password hashes and remember tokens are not selected or rendered.
   - Added a dedicated user policy, controller, and FormRequests instead of reusing generic profile input.
4. Added recent-password step-up and explicit role workflows.
   - Protected the user directory and all role mutations with Laravel's password-confirmation middleware using a 15-minute timeout.
   - Added a throttled password-confirmation form with accessible incorrect-password feedback.
   - The ordinary endpoint can assign only `user`, `supervisor`, or `admin`.
   - Superadmin promotion uses a separate, more tightly throttled endpoint requiring the exact canonical target email, the literal `superadmin`, a 10–500 character reason, a verified target, and the target's expected current role.
5. Made changes transactional and fail closed.
   - Locked actor/target rows, re-authorized the actor inside the transaction, rejected self/no-op/stale changes, and preserved at least one superadmin.
   - Rotated the target remember token, deleted all database-backed sessions, and wrote actor, target, event, old/new role, reason, timestamp, bounded IP/user-agent metadata, and the session-revocation count in one transaction.
   - If audit persistence fails, the role, remember token, and session deletion all roll back; target notification occurs only after commit.
   - Added a queued target notification containing old/new roles and reauthentication guidance without the administrative reason, credentials, tokens, or session identifiers.
6. Recorded the policy decision and current capability state.
   - Marked ADR-002 accepted based on the product owner's explicit `Continue ADM-1` instruction.
   - Updated the capability matrix and roadmap checkpoint states to distinguish what ADM-1 now enforces from later authoring/progress/audit-review phases.
   - Recorded that ADM-1 uses one recently confirmed superadmin rather than a second approver. Dual approval remains a separate governance workflow because no quorum, approver lifecycle, or emergency recovery policy exists.

### New problems found and disposition

- The first rendered user-directory pass packed five filter columns too tightly at a 1264-pixel desktop viewport, clipping the verification label. The filters now remain two columns until the 2XL breakpoint; the second rendered pass showed complete controls.
- A rate-limit test demonstrated that a mutation request without recent password confirmation may still consume a throttle attempt depending on Laravel's route-middleware priority. This is conservative (it cannot bypass step-up or increase authority); the reproducible throttle test uses a separate actor to isolate the five successful mutation attempts.
- The last-superadmin invariant and self-change ban overlap: an actor cannot demote the sole superadmin because self-change is denied, while demoting another superadmin necessarily leaves the acting superadmin. Both checks remain in the transactional service as defense in depth, and tests prove the resulting count never reaches zero through this workflow.
- The release E2E gate found a stale assertion for pre-ADM-0 archive wording. The rendered page correctly used the approved “Legacy Evidence pages are read-only audit and rollback records” copy; the E2E contract now asserts that exact current wording instead of the retired phrase.

### Verification evidence

- Focused role/administration suites after the final rollback and fail-closed additions: 17 passed, 98 assertions.
- Final strict Laravel suite (`--display-all-issues --fail-on-all-issues --disallow-test-output`): 157 passed, 1,573 assertions, zero failures or reported issues.
- Database behavior: the ADM-1 migration ran successfully in isolated SQLite test databases; inserting an unknown role raises a database query exception; the real local database preflight passed and migration `2026_07_17_000006` applied successfully in 38.86 ms.
- Transaction rollback probe: a test-only SQLite trigger deliberately rejected the audit insert; the response failed, notification was not sent, and the original role, remember token, and target session remained intact.
- Authorization coverage: guest, unverified, learner, supervisor, admin, superadmin, unknown persisted role, unknown configured middleware role, self, stale expected role, no-op, unverified elevation, ordinary-endpoint superadmin escalation, and throttling paths are covered.
- PHP style: targeted Laravel Pint passed.
- Templates/assets: Blade compilation passed; Vite 6.4.3 built 9 modules.
- JavaScript regression: ESLint reported zero warnings; all 36 Node tests passed.
- Chromium release E2E after correcting the stale archive-copy assertion: 3 passed, zero failed.
- Rendered in-app browser: learner session redirected away from the guest login as expected; after an explicit logout, the superadmin landed on `/superadmin/dashboard`, the User administration link required `/confirm-password`, wrong password produced one accessible alert, correct password opened the protected directory, and combined learner/name filtering returned the learner without the supervisor.
- Rendered interface: separate ordinary and superadmin-promotion disclosures, self-role denial copy, verified/current-role badges, fixed-field filters, and no mutation submission were visually checked. The learner session was restored and the temporary tab was closed.

### Research basis

- Laravel 12 authentication and password confirmation: https://laravel.com/docs/12.x/authentication
- Laravel 12 authorization and policy discovery: https://laravel.com/docs/12.x/authorization
- Laravel 12 validation and enum rules: https://laravel.com/docs/12.x/validation
- Laravel 12 routing and named rate limiters: https://laravel.com/docs/12.x/routing
- Laravel 12 database transactions: https://laravel.com/docs/12.x/database
- Laravel 12 Eloquent enum casting: https://laravel.com/docs/12.x/eloquent-mutators
- Laravel 12 notifications: https://laravel.com/docs/12.x/notifications

### Limitations

- ADM-1 creates audit evidence but intentionally does not add the audit-review/retention UI scheduled for ADM-6.
- A second-approver superadmin-promotion workflow is not implemented. The current action accurately represents one recently confirmed superadmin plus typed confirmation, verification, reason, audit, throttling, and session revocation; it must not be described as dual-approved.
- Queue dispatch was verified with Laravel's notification fake. Actual SMTP/provider delivery, worker supervision, retries, and dead-letter operations require the deployment environment and are not claimed by local tests.
- Row locking is implemented with `lockForUpdate`; SQLite provides transactional regression evidence but not production-engine lock semantics. The target deployment must run the same stale/concurrency scenarios against its configured database before release.
- The local workspace `.git` directory still has no `HEAD`, so Git diff/status evidence remains unavailable. Tests, migration status, route inventory, generated assets, decision records, and this checkpoint are the reproducible ledger.

### Rollback

Roll back the ADM-1 migration only after ensuring no retained audit evidence is required and no account currently uses `admin`; its `down()` removes `administration_audits` and restores a string role column. Restore the prior role model/middleware/routes/policies/views/configuration, then rerun the full PHP/JS/build gates. Do not make legacy rows authoritative or remove the canonical write lock.

### Next action after approval

Wait for the product owner to inspect this ADM-1 checkpoint. After an explicit `Continue`, begin ADM-2 only: mutable canonical draft workspaces, lifecycle transitions, optimistic locking, exact learner preview, validation/diff review, superadmin publication, immutable version generation, and rollback. Do not begin ADM-3 import or ADM-4 exercise authoring early.

## Administration Checkpoint ADM-2 — Canonical draft, review, preview, publication, and rollback foundation

Date: 2026-07-18  
State: implementation and verification complete; paused for product-owner review before ADM-3.

### Outcome

ADM-2 now provides an isolated, policy-protected canonical authoring workflow without making retained legacy rows authoritative or changing active learner delivery. Admin and superadmin can create a clone of the active canonical package or an explicitly empty workspace, edit the ADM-2 entity/block surface, validate, review source-aware differences, and preview the draft through the canonical learner views. Only superadmin can approve and, after recent password confirmation, publish or roll back an active publication.

The real local database contains the ADM-2 schema but no authored draft or publication. Active delivery remains `0.4.0-draft`; its source tree, Laravel projection, and standalone hashes are unchanged.

### Changes implemented

1. Added the isolated draft schema and domain model.
   - Added `curriculum_drafts`, `curriculum_draft_entities`, `curriculum_draft_blocks`, and `curriculum_draft_events` with actor references, lifecycle metadata, revision counters, archive timestamps, package references, and scoped uniqueness/indexes.
   - Added typed draft-status, editable-entity, and block-type enums plus Eloquent models with UUID route keys and explicit casts/relationships.
   - Kept active `curriculum_packages` and `curriculum_entities` immutable; cloned draft rows are a separate mutable projection.
2. Added a fail-closed lifecycle and concurrency boundary.
   - Implemented `draft -> validating -> in_review -> approved -> published` transitions with an explicit transition graph, actor/timestamp evidence, and immutable event records.
   - Wrapped workspace mutations and publication in database transactions and row locks, with bounded transaction retries.
   - Required submitted draft/entity/block revisions on writes and return HTTP 409 for a stale editor instead of silently overwriting newer work.
3. Added canonical workspace authoring operations.
   - Admin and superadmin can create, update, reorder, archive, and restore draft chapters/modules, lesson sections, outcomes, and the nine CF-2 content-block types.
   - Type-specific request validation rejects malformed dialogue, table, external-link, text, and activity-embed blocks before persistence.
   - Entity insertion, parent changes, archive, restore, and reorder now normalize both database positions and canonical payload order. Moving a lesson between modules closes the old sibling gap and inserts it atomically into the new sequence.
   - Archived entities and blocks cannot be edited or reordered. A child section or block cannot be restored beneath an archived parent.
4. Added exact, non-recording learner preview.
   - Added admin/superadmin preview routes for dashboard, chapter, section, and activity views backed by a draft projection repository.
   - Reused canonical learner templates and navigation while displaying a draft banner and redirecting preview links back through the protected draft namespace.
   - Preview forms are inert and CSP-safe; no attempt, activity-progress, or completion record is written.
5. Added validation, source-aware review, and approval.
   - Reconstructs draft entities/blocks into a private package tree, regenerates declared checksums/framework outcomes/evidence, and passes it through the existing strict canonical reader and deterministic standalone renderer.
   - Produces created/changed/archived/unchanged diff summaries with source locators.
   - Admin may validate/submit and request changes. Approval is a separate superadmin-only action. Any validation/build failure returns the workspace to draft with a bounded error report.
6. Added immutable publication and recorded rollback.
   - Publication requires a release semantic version without a `-draft` suffix, an approved state, superadmin authorization, recent password confirmation, and a named five-per-hour limiter.
   - Generates a new private immutable package artifact, imports it with the existing canonical importer, activates the new projection transactionally, and records the package/import run on the draft.
   - Rollback is allowed only while that publication remains active; it uses the recorded importer rollback artifact and refuses to overwrite a newer active release.
   - Updated importer rollback handling so draft base/source foreign keys are safely detached and restored while canonical snapshot rows are replaced.
7. Added administration routes and interface.
   - Added 43 named admin/superadmin draft routes with distinct namespaces; no admin approve, publish, or rollback route exists.
   - Added draft inventory/create, detail/review, entity/block edit, and learner-preview screens; added Canonical authoring to the admin sidebar while retaining the five Legacy Evidence destinations as read-only.
   - Added English and Indonesian administration copy with parity coverage.
8. Fixed responsive administration layout discovered during rendered verification.
   - The shared 256-pixel admin sidebar previously left only about 119 CSS pixels for content at a 390-pixel viewport and caused horizontal overflow.
   - The shared shell now stacks sidebar and main content below the medium breakpoint and retains the 256/1024 split at a 1280-pixel desktop viewport, without inline script or event handlers.
   - Replaced encoding-corrupted middle-dot text in the new ADM-2 views with encoding-safe HTML entities.
9. Updated operational verification and capability records.
   - `hospitrainity:curriculum verify` can verify a recorded administratively published package from its evidence path while retaining the configured CF package fallback.
   - Updated the current capability matrix to show ADM-2 draft/preview/approval/publication permissions and to keep import/upload explicitly denied until ADM-3.

### New problems found and disposition

- Initial full-suite review found an inline preview form handler that violated the enforced CSP regression contract. It was replaced at ADM-2 by a `data-preview-form` marker and the external JavaScript bundle; the security-header suite passed. ADM-4 later replaced that bundle's inert-preview cancellation with a policy-protected server evaluator while retaining the CSP-safe external-script contract; see the ADM-4 checkpoint.
- The first publication/rollback integration test exposed canonical foreign-key references held by draft base/source rows while the importer restored a snapshot. The importer now detaches and restores those references in the rollback transaction; the immutable publication/rollback test passes.
- Entity save originally changed `position` without resequencing siblings, and a cross-module lesson move could leave a gap in the old module. Locked sibling normalization and explicit regression tests now cover insertion, parent moves, archive, and restore.
- Archived entities/blocks were still callable through edit/reorder service paths, and a child could be restored below an archived parent. These operations now fail closed and preserve the submitted revision.
- The first browser screenshot showed a collapsed desktop column while DOM geometry reported a full 1024-pixel main area. A fresh browser session with an explicit 1280-pixel viewport rendered correctly; the earlier desktop image was therefore not accepted as evidence. Explicit 390-pixel testing then reproduced the actual responsive defect, which was fixed and reverified.
- A temporary browser QA account initially failed to receive a role through ordinary model creation because `role` is intentionally not mass assignable. The test fixture used an explicit trusted assignment instead. Both temporary QA accounts and their database sessions were removed; the real user count returned to three.
- The Codex desktop task was interrupted after successful browser cleanup but before checkpoint documentation and removal of the final temporary account. No terminal session or explicit crash record was available, so an exact application-level cause cannot be proven. Recovery rechecked the database and hashes before removing the remaining temporary account. This is a tooling-continuity event, not evidence of a Hospitrainity runtime crash.

### Verification evidence

- Focused ADM-2 suite after final ordering/archive hardening: 10 passed, 116 assertions.
- Final strict Laravel suite: 185 passed, 2,873 assertions, zero failures or reported issues.
- Post-responsive-change accessibility, admin smoke, layout, CSP/security, and ADM-2 regression set: 24 passed, 541 assertions.
- JavaScript: ESLint completed with zero warnings; 45 Node tests passed.
- Blade compilation: cached successfully.
- Production assets: Vite 6.4.3 built 10 modules; final CSS asset includes the responsive administration classes.
- PHP style: targeted Laravel Pint passed.
- Route inventory: 43 ADM-2 routes; admin has authoring/validation/request-changes/preview, while approve/publish/rollback exist only under superadmin.
- Canonical verifier: status `verified`; 7 chapters, 85 sections, 25 activities, 124 prompts, 96 answer models, and 96 feedback models.
- Active source tree SHA-256: `e864b4fcaa9d182b8379cf7d179279114baa7fef46624bcb261be41b8072dc9a`.
- Active Laravel projection SHA-256: `0967725acf53b46349ad8000a6b8e652e8855f37978912e796c3391cd9587f7f`.
- Active standalone SHA-256: `55d1418fc75e359f85c9f7bb8ad1d93ea36943859b48f3c18a1db65039bf36c4`.
- Persistent migration: `2026_07_18_000008_create_curriculum_draft_workspaces` is applied in batch 8.
- Pre-migration database backup: `storage/app/private/deployment-backups/database-before-adm2-20260718.sqlite`, 2,703,360 bytes, SHA-256 `577fc68f2377d347bbd793d35e873d1bad8237233ee82aad76e47641fe5d2f98`.
- Persistent content neutrality after testing: 0 drafts, 0 draft entities, 0 draft blocks, 0 draft events, 3 real users, and no temporary QA account.
- Browser role isolation: learner access to both draft namespaces returned forbidden; an authenticated admin rendered the admin authoring inventory and had no publication action.
- Browser desktop verification at 1280x720: sidebar 256 pixels, main 1024 pixels, document width 1280/1280, no horizontal overflow, mojibake, or console warning/error.
- Browser narrow verification at 390x844 after the fix: CSS client/scroll width 375/375, full-width stacked sidebar/main, 279-pixel form, no horizontal overflow or mojibake.

### Research basis

- Laravel 12 authorization and policy discovery: https://laravel.com/docs/12.x/authorization
- Laravel 12 form-request validation and post-validation hooks: https://laravel.com/docs/12.x/validation#form-request-validation
- Laravel 12 database transactions: https://laravel.com/docs/12.x/database#database-transactions
- Laravel 12 pessimistic locking: https://laravel.com/docs/12.x/queries#pessimistic-locking
- Laravel 12 password confirmation: https://laravel.com/docs/12.x/authentication#password-confirmation
- Laravel 12 named rate limiters: https://laravel.com/docs/12.x/routing#rate-limiting

### Limitations and gates that remain open

- ADM-2 deliberately exposes the minimal canonical typed-block payload editor. Rich field-specific manual editors, DOCX import, source/provenance editing, private quarantine, asset uploads/deduplication, bounded jobs, and dry-run import inventories remain ADM-3.
- An explicitly empty workspace exists, but strict validation fails until the complete canonical framework and assessment contract is supplied. ADM-2 does not fabricate missing metadata to make an empty draft publishable.
- Exercise-template creation and adjustable canonical answers remain ADM-4. Retained legacy exercise CRUD is still read-only evidence and does not author active delivery.
- Global/policy-scoped learner progress administration remains ADM-5. ADM-2 adds no new learner-response visibility.
- The publication integration test created and rolled back an isolated test-database version; no real local draft was approved or published. Production database-engine, storage, filesystem permission, backup, worker, and deployment-recovery behavior must be rehearsed in the deployment environment.
- Row locks are implemented with `lockForUpdate`; SQLite proves transaction/revision behavior but not MySQL/PostgreSQL lock scheduling. Run the same stale-editor and competing-publication scenarios against the target database before release.
- Administrative publication recomputes the structural evidence enforced by the canonical reader. It explicitly does not certify inherited ESP/CEFR, hospitality-practitioner, accessibility, or source-semantic human review gates; those remain governed by their recorded evidence.
- Local browser checks and markup tests are not an independent WCAG audit across browser/assistive-technology combinations.
- The workspace has no usable Git `HEAD`; Git status/diff/commit evidence is unavailable. This checkpoint, migration status, hashes, generated manifest, route inventory, and reproducible tests are the change ledger.

### Rollback

Do not roll back the schema while any ADM-2 drafts or publication records must be retained. If rollback is required and all draft data has been exported or is disposable, verify the backup hash, place the application in a controlled maintenance window, reverse migration `2026_07_18_000008`, restore the prior ADM-2 code/view/route/configuration set, rebuild assets, and rerun canonical verification plus the full regression suite. If persistent state must be restored wholesale, use the verified pre-ADM-2 SQLite backup only after preserving any newer legitimate data and recording the decision. Never restore the backup merely to undo code, and never re-enable retained legacy writes.

### Next action after approval

Wait for the product owner to inspect this ADM-2 checkpoint. After an explicit `Continue`, begin ADM-3 only: rich canonical module/lesson/block/source editors, the approved DOCX-to-draft import path, private/quarantine upload validation, bounded processing, asset provenance/deduplication, and dry-run inventory/diff. Do not begin ADM-4 exercise-template authoring or ADM-5 progress administration early.

## Administration Checkpoint ADM-3 — Structured lesson authoring, authority-DOCX import, and private assets

Date: 2026-07-19  
State: implementation and technical verification complete; paused for product-owner review before ADM-4.

### Outcome

ADM-3 now lets an authorized admin or superadmin manually author every canonical lesson-block shape, queue the approved authority-DOCX workflow into private quarantine, review a bounded dry-run inventory/diff, accept a ready import into only the selected draft, and upload traceable image/audio assets. Active learner delivery is never an import target and remains the immutable `0.4.0-draft` package at schema `2.0.0` with unchanged source-tree, Laravel-projection, and standalone hashes.

The evolved authoring contract is schema `2.1.0`. An exact authority DOCX compiled against an untouched active clone reports zero created, removed, rejected, or unclassified records and exactly two reviewed schema-infrastructure changes: `package.json` and `schemas/content-block.schema.json`. It does not misreport hundreds of content changes because of the generated quarantine filename.

### Changes implemented

1. Added private import and asset persistence.
   - Added `curriculum_imports`, `curriculum_asset_blobs`, and `curriculum_assets`, plus optional block-to-asset references, foreign keys, scoped indexes, status/revision fields, compiler evidence, provenance, rights, accessibility text, and actor/timestamp fields.
   - Added typed import-status and asset-kind enums and explicit Eloquent relationships.
   - Applied migration `2026_07_18_000009_create_curriculum_imports_and_assets` only after creating and hashing a persistent pre-ADM-3 SQLite backup.
2. Added field-specific canonical lesson-block authoring.
   - Replaced the administration screen's raw JSON payload entry with validated fields for all nine CF-2 block types: heading, paragraph, list, dialogue, vocabulary group, materials group, source table, external link, and activity embed.
   - Converts tab-separated table input into equal-width canonical rows; pairs link labels with HTTP(S) targets; captures dialogue speakers/text and grouped list values; and rejects malformed cardinality or type-specific shapes server-side.
   - Requires a provenance note for manually authored rich blocks, marks them `admin_authored`, assigns stable server-side identifiers/locators, and optionally binds an approved draft asset.
3. Added fail-closed authority-DOCX inspection and private quarantine.
   - Accepts only a non-empty DOCX no larger than 2 MiB whose extension, content-derived MIME, ZIP structure, and required OOXML parts agree.
   - Rejects macros, encrypted entries, traversal/absolute entry names, excessive entry counts, oversized individual/total expansion, and malformed packages before queueing.
   - Stores the original only under a generated UUID filename on a non-served private disk; the client filename is retained only as bounded provenance.
4. Added bounded asynchronous compilation and truthful dry-run review.
   - Dispatches a unique database-queue job after transaction commit, with one attempt, a 60-second job bound, a 50-second child-process timeout, and a 20-second idle timeout.
   - Invokes the PHP compiler with a fixed argument array; uploaded content is parsed as OOXML and is never shell-executed.
   - Re-reads compiler output through the strict canonical package reader before marking an import ready, and records created/changed/removed/unchanged/rejected/unclassified summaries plus private evidence.
   - A failed compilation deletes the rejected source/work product, stores a bounded path-sanitized error, and leaves the selected draft and active delivery unchanged.
5. Added explicit, draft-only import acceptance.
   - Requires matching draft/import revisions, a ready import belonging to that draft, draft status, authorization, and the typed phrase `REPLACE DRAFT`.
   - Re-reads the private compiled package, transactionally replaces only that workspace's entities/blocks, carries the package content/schema/namespace metadata, records an event, and marks the import accepted.
   - Does not approve, publish, activate, or mutate an active package.
6. Corrected compiler provenance and schema evolution.
   - The compiler now accepts a separately validated logical artifact name, so a generated quarantine basename cannot leak into hundreds of source locators and create a false diff.
   - The import path passes the sanitized original artifact name as a process-array argument while continuing to store the file by UUID.
   - Declared the expanded `admin_authored`/locator contract as schema `2.1.0`; new drafts use that authoring schema and accepted imports preserve compiled metadata. The active schema remains `2.0.0` until an independently approved publication creates a new immutable release.
7. Added private, digest-addressed lesson assets.
   - Accepts content-matched JPEG/PNG/WebP images and MP3/WAV audio no larger than 2 MiB, with image decoding and audio signature checks.
   - Promotes bytes from private quarantine into SHA-256-addressed blob paths, deduplicates physical bytes while retaining distinct logical uses, and requires display name, rights basis, and accessibility text.
   - Verifies referenced digest/path/MIME metadata during draft package generation and copies only referenced bytes into the private generated package.
   - Serves draft assets only through authorized admin preview routes and active assets only through authenticated learner routes, with `nosniff`; no original is placed under `public/`.
8. Added administration interface and policy coverage.
   - Added queue/status/error/diff/accept panels, a private asset library/uploader, asset selectors in every rich block form, and protected image/audio rendering in draft and learner section views.
   - Added English and Indonesian copy and kept admin/superadmin route namespaces policy-scoped. The current inventory contains 52 protected curriculum-draft/asset routes: four import actions and five asset actions/delivery routes within that total.
9. Preserved deterministic compiler reuse.
   - The assessment normalizer now recognizes an already-normalized canonical baseline and safely rebinds its source locators/counts instead of requiring source prompt files that normalization intentionally removed.
   - The compiler's generated content-block schema and the administrative package builder now agree on `admin_authored` provenance and validated generic locator bounds.

### New problems found and disposition

- The original authority-DOCX browser request took about 560 seconds to return through browser control despite the page operation's 15-second navigation bound. Database and page checks proved one request completed, so it was not retried; the database worker then completed the queued job in four seconds. This is evidence of a browser-control bridge stall, not evidence that Laravel or the compiler ran for 560 seconds.
- The Codex desktop task ended after browser tab finalization and before QA cleanup/documentation. No crash dump or application error identifies whether the desktop renderer, context handling, or browser bridge ended the task, so an exact crash cause is not claimed. Recovery rechecked the migration, database rows, jobs, storage paths, backup, tests, and hashes before proceeding.
- A recovery inspection accidentally expanded the stored dry-run item list into a very large terminal response, reproducing the same avoidable payload-pressure pattern. All later diagnostics were constrained to scalar summaries. The first guarded recursive cleanup was also interrupted by a disconnected safety-review stream; it performed no deletion. The exact paths were then removed only after explicit elevated approval and parent-path verification.
- Browser QA showed chapter headings beginning with a bare period because canonical chapter number is stored in payload `module` while the inventory read nullable generic `position`. The view now uses the canonical module field with a legacy position fallback, and a feature assertion covers the rendered heading.
- The browser's exact source dry run initially reported 405 changed records. Investigation proved the UUID quarantine basename had become `source_locator.artifact`; separating physical and logical names reduced this to the two legitimate schema artifacts. A regression test now requires that exact two-file diff.
- Those two schema changes were initially still labeled schema `2.0.0`. The evolved block/locator contract is now explicitly `2.1.0`, and draft creation/import acceptance carries the correct metadata without modifying the active `2.0.0` release.
- The field-specific block UI had removed its raw JSON editor, but the shared FormRequest still accepted a hidden `payload_json` compatibility field used by an older test. A direct caller could therefore omit the new provenance note. The compatibility input and unused localization were removed; all callers now use the same typed-field contract and field-specific validation errors.
- Compiler reuse initially assumed six pre-normalization confidence prompt files still existed in its baseline. The active canonical package had correctly removed them during CF normalization. The normalizer now recognizes and validates the canonical representation before refreshing locators.

### Verification evidence

- Focused ADM-2/ADM-3 suites: 16 passed, 184 assertions.
- Exact authority-DOCX import regression: ready dry run at schema `2.1.0`; 0 created, 2 changed (package manifest and content-block schema), 0 removed, 0 rejected, and 0 unclassified; typed acceptance produced 7 chapters and 774 blocks only in the test draft.
- Final Laravel suite after the provenance/schema correction: 191 passed, 2,948 assertions.
- Deterministic source compiler: status `verified`, 469 byte-identical files, tree SHA-256 `9d1511a24c84059a7c561603bb94b6c2c52a8076edfc2ba71def63ba2f2259ac`, and all six negative probes passed (changed hash, missing marker, duplicate IDs, malformed table, lost hyperlink relationship, unsupported OOXML block).
- Canonical active verifier: 7 chapters, 85 sections, 25 activities, 124 prompts, 96 answer models, and 96 feedback models; status `verified`.
- Active source-tree SHA-256: `e864b4fcaa9d182b8379cf7d179279114baa7fef46624bcb261be41b8072dc9a`.
- Active Laravel projection SHA-256: `0967725acf53b46349ad8000a6b8e652e8855f37978912e796c3391cd9587f7f`.
- Active standalone SHA-256: `55d1418fc75e359f85c9f7bb8ad1d93ea36943859b48f3c18a1db65039bf36c4`.
- JavaScript: ESLint completed with zero warnings; all 45 Node tests passed.
- Production assets: Vite 6.4.3 built 10 modules.
- Blade templates cached successfully; targeted Laravel Pint passed.
- Persistent migration: `2026_07_18_000009_create_curriculum_imports_and_assets` is applied in batch 9.
- Pre-migration backup: `storage/app/private/deployment-backups/database-before-adm3-20260718.sqlite`, 2,768,896 bytes, SHA-256 `4C1AF8D71CF4D4CE276EB9E18818411CDB4E150EC874B3A460C49270D9B3BF0A`.
- Persistent neutrality after cleanup: 3 users; 0 drafts, draft entities, draft blocks, draft events, imports, logical assets, asset blobs, queued jobs, failed jobs, private import files, and named ADM-3 compiler-probe directories.
- Browser role/authoring check: learner access was denied; the admin rendered all nine field-specific block editors with no raw JSON payload fields and successfully created a temporary paragraph.
- Browser desktop at 1280x720: 256-pixel sidebar, 1009-pixel main area within the 1265-pixel CSS viewport, two 452-pixel panels, and no horizontal overflow/mojibake.
- Browser narrow at 390x844: 375/375 client/scroll width, stacked full-width sidebar/main, 327-pixel panels, and no horizontal overflow/mojibake.
- Browser import/preview check: a single authority-DOCX upload queued, the worker completed it, the ready/error/diff/accept controls rendered, the draft preview remained policy-protected, and no learner attempt/progress record was created. The corrected two-file backend diff was subsequently verified by the exact authority-DOCX feature test rather than another browser upload after the control-bridge stall.

### Research basis

- Laravel 12 file validation and FormRequests: https://laravel.com/docs/12.x/validation
- Laravel 12 private filesystem disks: https://laravel.com/docs/12.x/filesystem
- Laravel 12 queues, uniqueness, timeouts, and worker operation: https://laravel.com/docs/12.x/queues
- Laravel 12 Process API argument arrays and timeouts: https://laravel.com/docs/12.x/processes
- OWASP File Upload Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html

### Limitations and gates that remain open

- A deployment must run and supervise the database queue worker; local execution proves the job contract but not production process supervision, restarts, monitoring, or database-engine concurrency. Verify timeout support and failed-job operations on the target host.
- No antivirus/content-disarm service is configured or claimed. Type/structure/signature/schema checks fail closed, and rejected compiler sources are deleted; antivirus scanning and rejected-upload retention require an explicit deployment/privacy decision.
- Import is deliberately limited to the approved authority-DOCX compiler. Arbitrary ZIP, generic canonical-package/JSON import, SVG, video, and other formats are not approved or implemented.
- Assets render through protected Laravel draft/learner section views. The existing standalone projection does not render full lesson block bodies, so standalone media parity is not claimed in ADM-3.
- Accepted sources and referenced assets remain private and traceable, but an institutional retention/deletion schedule has not been approved. Do not infer one from local cleanup behavior.
- Exercise-template creation and adjustable answers remain ADM-4. ADM-3's `activity_embed` block links existing canonical activities; it is not an exercise builder.
- Global/policy-scoped learner progress administration remains ADM-5. ADM-3 adds no administrative access to raw learner responses or identity-level global progress.
- ADM-3 technical tests do not replace the recorded human source-semantic, ESP/CEFR, hospitality-practitioner, accessibility, rights, link-owner, retention, or publication approvals.
- Local browser checks are not an independent WCAG audit across browsers and assistive technologies.
- The workspace has no usable Git `HEAD`; Git status/diff/commit evidence remains unavailable. This checkpoint, tests, migration status, hashes, and generated artifacts are the reproducible change ledger.

### Rollback

Do not reverse migration `2026_07_18_000009` while any legitimate imports, assets, blob references, or provenance evidence must be retained. Export required evidence and verify blob/source retention decisions first. In a controlled maintenance window, remove ADM-3 routes/services/views/configuration, reverse only the ADM-3 migration, rebuild assets, and rerun the full Laravel/JavaScript/compiler/canonical gates. Restore the verified pre-ADM-3 SQLite backup only when a whole-database recovery is explicitly required and newer legitimate data has been preserved; never restore it merely to undo code. Active delivery was not changed by ADM-3, so rollback must not re-import or mutate the active canonical package.

### Next action after approval

Wait for the product owner to inspect this ADM-3 checkpoint. After an explicit `Continue`, begin ADM-4 only: a canonical exercise-template registry/builder with editable prompts/options/items/answers/feedback/rubrics, authoritative server-side validation/scoring, versioned history, and exact learner-preview parity. Do not begin ADM-5 progress administration early.

## Administration Checkpoint ADM-4 — Canonical exercise-template builder and adjustable answers

Date: 2026-07-19  
State: implementation and technical verification complete; paused for product-owner review before ADM-5.

### Outcome

ADM-4 now provides one versioned canonical exercise-authoring contract for admin and superadmin. All 14 retained legacy names have an explicit disposition. Twelve templates are enabled through the complete admin form → shared request validation → canonical draft entities → strict package reader → exact learner Blade renderer → server result path. `spelling_quiz` and `listening_task` are mapped but visibly unavailable because an approved equivalent prerecorded-audio alternative/accommodation does not yet exist. They cannot be opened or submitted by changing a URL or request field.

Active learner delivery remains immutable `0.4.0-draft`. ADM-4 writes only to authorized draft workspaces, published exercise changes require a new versioned draft, and historical attempts retain the package/content version the learner saw. No database schema change or migration was needed.

### Changes implemented

1. Added the shared versioned template registry.
   - Registry `1.0.0` inventories the exact 14 retained names and declares availability, editor shape, response form, scoring mode, cardinality, stable identifiers, answer reference, audio/rubric requirements, accessibility requirements, and canonical renderer.
   - Declares exact choice, normalized closed response, ordered response, model self-check, rubric self-assessment, and unscored confidence policies; open language is never converted to objective scoring by the presence of a model answer.
   - The strict package reader verifies template name/version/availability and requires the canonical response/scoring/cardinality/answer/rubric contract to agree before a draft can validate or publish.
2. Added canonical draft exercise authoring.
   - Admin/superadmin can choose an enabled template, create an exercise in an unoccupied learner section, and edit its code/title/guidance/provenance, prompts, options/items, accepted answers, correct order, model answer, feedback, and rubric as applicable.
   - Item controls support add/remove and named Move up/Move down actions without a drag-only path; a polite live region announces changes and submitted field names are renumbered after reordering.
   - Duplication requires another section without an activity and produces new activity, prompt, answer, feedback, rubric, choice, and token identities.
3. Kept identity and correctness authoritative on the server.
   - Existing prompt codes must belong to that draft/activity; new prompt codes and every choice/token UUID are server-generated.
   - Correct-choice references must target a submitted option, correct order must be a complete stable-token permutation, normalized accepted answers must remain unique, and unknown nested request keys are rejected.
   - Stable prompt/choice/token IDs survive label edits and item reordering. Browser labels never become trusted scores or identifiers.
4. Added faithful draft learner interaction.
   - Draft and active delivery use the same `curriculum.activity` Blade view.
   - A policy-protected, throttled preview POST now shares canonical attempt rules, normalization, scorer, model, and feedback assembly with active delivery while deliberately creating no attempt, response, event, or progress row.
   - Preview validation restores submitted values, links errors to the canonical controls, and renders exact correct/incorrect/model/feedback states with “preview not recorded” and zero attempts.
5. Preserved lifecycle and immutable history.
   - Exercise create/update/duplicate is permitted only for a mutable draft and is protected by draft/activity optimistic revision checks.
   - Isolated publication coverage proves the enabled template compiles into an immutable new package; a later draft edit does not alter active content or the earlier learner attempt's content version.
6. Extended protected asset and projection support.
   - Canonical draft generation, active/draft asset authorization, Laravel projections, attempt validation, and standalone projection all follow prompt position before code.
   - Latent audio references are private/digest checked, but the two audio-only authoring presets remain fail-closed until the independent accessibility gate is resolved.
7. Added administration UI, localization, and regression coverage.
   - Added English/Indonesian registry, template, field, availability, feedback, preview, and validation copy with language-key parity.
   - Added 14 policy-scoped ADM-4 routes across admin/superadmin, including the safe preview evaluator.
   - Added focused PHP and Node suites covering all dispositions and every enabled end-to-end compilation/preview contract.

### New problems found and disposition

- WCAG 2.2 SC 1.2.1 requires an equivalent alternative for prerecorded audio-only information. A short description is not equivalent, while a verbatim transcript may disclose a spelling/listening answer. `spelling_quiz` and `listening_task` therefore remain unavailable until an accommodation/equivalent-alternative design is approved by an accessibility reviewer; no unsupported solution was invented.
- Browser QA found that the draft preview buttons were inert. The Blade view used non-submit buttons and shared JavaScript cancelled every `data-preview-form` submission because ADM-2 preview originally had no safe evaluation endpoint. ADM-4 added a read-only server evaluator, changed the preview controls to normal CSRF-protected POST submits, removed only the obsolete cancellation branch, and added a feature test proving feedback with zero persistence.
- The first server-result refactor typed an attempt ID as integer, while this application uses UUID attempt IDs. The focused publication test caught the mismatch before acceptance; the result contract now correctly accepts integer/string/null identities and the complete suite passes.
- Browser QA at 390×844 found a 146-pixel horizontal overflow caused by the duplicate-target section selector's intrinsic option width. The duplicate form now uses a bounded minmax grid and a full/max-width select. The final document client/scroll widths are both 375 pixels.

### Verification evidence

- Focused ADM-4 feature suite: 8 passed, 524 assertions.
- Full Laravel suite: 199 passed, 3,486 assertions.
- JavaScript: all 47 Node tests passed; ESLint completed with zero warnings.
- Isolated Chromium E2E: all 7 journeys passed, including every canonical response form and the read-only administration boundary.
- Production build: Vite 6.4.3 built 11 modules; final application assets include `app-B72bBoTt.js` and `app-wZkjQlRo.css`.
- Laravel Pint passed repository-wide; Blade templates cached successfully.
- Dependency audits: npm reported 0 vulnerabilities at every severity; Composer reported no advisories and no abandoned packages.
- Canonical active verifier: 7 chapters, 85 sections, 25 activities, 124 prompts, 96 answer models, 96 feedback models, and 6 rubrics; status `verified`.
- Active source-tree SHA-256: `e864b4fcaa9d182b8379cf7d179279114baa7fef46624bcb261be41b8072dc9a`.
- Active Laravel projection SHA-256: `0967725acf53b46349ad8000a6b8e652e8855f37978912e796c3391cd9587f7f`.
- Active standalone SHA-256: `55d1418fc75e359f85c9f7bb8ad1d93ea36943859b48f3c18a1db65039bf36c4`.
- Brand guard: 730 files scanned, zero active predecessor-brand matches; only the exact legacy-inventory provenance allowance remains.
- Route inventory: 128 routes total; 14 ADM-4 exercise/preview-attempt routes across admin and superadmin.
- Database schema remains at migrations through batch 9; no ADM-4 migration or backup was required.
- Browser inventory: exactly 14 template cards, 12 enabled actions, and two unavailable cards with no action.
- Browser authoring: created a two-item multiple-choice exercise, used the keyboard-operable reorder action, saved server-generated prompt codes, and rendered six native radio controls in the exact learner view.
- Browser preview: one correct and one incorrect response produced the expected model/feedback; the page still displayed `preview not recorded · Attempts: 0`, and backend counts confirmed zero ADM-4 attempts/progress.
- Browser desktop at 1280×720: 256-pixel sidebar, 1009-pixel main, 929-pixel authoring form, and 1265/1265 document client/scroll width.
- Browser narrow at 390×844: stacked 375-pixel sidebar/main, 327-pixel authoring form, 278-pixel duplicate selector, and 375/375 document client/scroll width with no mojibake.
- Persistent cleanup: the temporary 521-entity browser draft and its two events were removed; persistent state returned to 3 users, 0 drafts/draft entities/draft blocks/draft events, 0 attempts, and 0 ADM-4 progress rows.

### Research basis

- Laravel 12 nested-array validation and explicit allowed keys: https://laravel.com/docs/12.x/validation#validating-nested-array-input
- OWASP Input Validation Cheat Sheet, allowlist and server-side validation: https://cheatsheetseries.owasp.org/cheatsheets/Input_Validation_Cheat_Sheet.html
- 1EdTech QTI 3 guide/information model, stable response identities and correct-response references: https://developers.imsglobal.org/spec/qti/v3p0/guide and https://www.imsglobal.org/sites/default/files/spec/qti/v3/info/index.html
- WCAG 2.2 normative recommendation and prerecorded audio-only alternatives: https://www.w3.org/TR/WCAG22/ and https://www.w3.org/WAI/WCAG22/Understanding/audio-only-and-video-only-prerecorded.html
- W3C error identification and non-sensory-only instructions: https://www.w3.org/WAI/WCAG22/Understanding/error-identification.html and https://www.w3.org/WAI/WCAG22/Understanding/sensory-characteristics.html

### Limitations and gates that remain open

- `spelling_quiz` and `listening_task` are mapped but intentionally unavailable. Enabling either requires an approved equivalent-alternative/accommodation design, semantic content review, and human accessibility review; changing one registry flag alone is not sufficient.
- Automated markup/keyboard checks and one local Chromium pass are regression evidence, not an independent screen-reader, assistive-technology, or cross-browser accessibility certification.
- The current canonical learner section view supports one activity per section. The transaction and UI therefore require an unoccupied section for create/duplicate instead of silently changing that information architecture.
- Browser authoring QA used the superadmin surface; the complete guest/unverified/learner/admin/superadmin authorization matrix is covered by isolated feature tests.
- Publication and later-version history were tested only in isolated test storage/database. No real draft was approved/published, and active delivery remains `0.4.0-draft`.
- Qualified source-semantic, ESP/CEFR, hospitality-practitioner, accessibility, rights, and final publication approvals remain separate human gates. ADM-4 does not claim that newly authored wording is source-approved merely because its schema is valid.
- Policy-scoped learner progress administration, privacy-safe filters/detail, and separately governed export remain ADM-5 and were not started.
- The workspace has no usable Git `HEAD`; Git diff/commit evidence is unavailable. This checkpoint, contract decision, route inventory, hashes, generated manifest, and reproducible tests are the change ledger.

### Rollback

ADM-4 has no migration to reverse. To roll back, preserve/export any legitimate canonical exercise drafts first, remove the ADM-4 routes/controllers/requests/registry/workspace/views/JavaScript/localization and strict template checks, restore the prior shared preview form behavior, rebuild assets, and rerun the full Laravel/JavaScript/E2E/canonical/brand gates. Do not delete or rewrite a published package or historical attempt to undo authoring code. Active `0.4.0-draft` was not changed, so rollback must not import, publish, or restore the active package.

### Next action after approval

Wait for the product owner to inspect this ADM-4 checkpoint. After an explicit `Continue`, begin ADM-5 only: policy-scoped learner progress administration with bounded filters/detail, explicit privacy-field exclusion, query/pagination controls, and separately decision-gated export. Do not begin ADM-6 or broaden raw learner-response access early.

## Administration Checkpoint ADM-5 — Policy-scoped learner progress administration

Date: 2026-07-19  
State: implementation and technical verification complete; paused for product-owner review before ADM-6.

### Outcome

ADM-5 now provides three deliberately different progress surfaces: superadmin global identity-level metadata and versioned learner detail, supervisor same-institution learner detail, and admin global aggregate/de-identified totals. These boundaries derive from accepted ADR-002 and are enforced by role middleware plus model policies; they are not client-side filters.

The implementation reads state/timestamp/attempt metadata only. It does not query `curriculum_responses`, raw open responses, confidence answers, or audio. CSV export remains unimplemented and visibly disabled pending its separate privacy/security decision. Active delivery remains immutable `0.4.0-draft`; ADM-5 adds no migration and created no persistent account, attempt, response, draft, or progress row.

### Changes implemented

1. Added explicit progress authorization.
   - Superadmin can list/detail every learner; supervisor detail requires an exact, non-empty institution match; content admin receives aggregate totals only.
   - Non-learner targets and cross-institution/direct-ID attempts return 404 after authorization, while guest, unverified, and wrong-role requests remain stopped by the existing middleware stack.
2. Added a fixed-page global progress dashboard.
   - Search/filter covers learner identity, institution, package content version, module, exact highest state, and activity within 7/30/90 days.
   - Page size is fixed at 20 and pagination preserves validated filters. Progress and attempt reads are batched; the measured SQL count is constant for one learner and a full page.
3. Added metadata-only learner detail.
   - Renders package/version inventory and module → section/activity hierarchy with state, attempts, last activity, explicit completion time, and legacy-migration labels.
   - Retained versions with missing definitions show exact stored codes and an unavailable-definition notice. Unknown versions render an honest empty/unavailable state rather than invented titles.
4. Added aggregate-only admin reporting.
   - Shows learner/progress/completion/attempt totals and active-version completion without identity rows or institution slices that could re-identify a small cohort.
   - Active completion joins only retained published activities from the active package, preventing unknown historical codes from inflating the percentage.
5. Added privacy and export fail-closed behavior.
   - The query service selects only approved metadata columns and never loads the response relation/table.
   - No export route exists. Disabled controls explain that columns, authorization, row bounds, spreadsheet neutralization, audit, purpose, retention, and deletion require separate approval.
6. Added accessible/responsive administration views.
   - Tables have captions and scoped headers; filters have explicit labels, validation error reporting, native controls, submit/loading status, clear actions, empty states, and internal table scrolling.
   - Refactored the supervisor shell into the shared responsive pattern and added authorized detail links.
7. Added regression coverage and corrected newly found defects.
   - Added the complete ADM-5 feature suite and extended role-route, smoke, accessibility, supervisor query-count, and language-parity coverage.

### New problems found and disposition

- Existing active completion counted every completed row sharing the active package/version, even when an activity code was no longer a retained published activity. This could exceed truthful current completion. The aggregate and shared canonical overall calculation now join the active package's published activity inventory; an unknown-code regression proves the result remains 50% for one of two valid activities instead of being inflated to 100%.
- Browser QA at 390 px found a visually hidden Actions header whose absolute positioning escaped the horizontally scrollable table and widened the root document to 840 px. The table now uses a visible scoped Actions header. Final root client/scroll widths are both 375 px while the table itself remains independently scrollable.
- The initial detail rendered a complete empty data table for every canonical section without an activity, making the 85-section page unnecessarily large. Empty sections now render a concise text state; tables are emitted only for sections with activity metadata.
- The first Vite build attempt failed because the managed workspace sandbox denied esbuild a dependency/config directory read. No application error was present; the identical production build passed once run with its approved build permission.

### Verification evidence

- Focused ADM-5 suite: 8 passed, 63 assertions.
- Full Laravel suite: 207 passed, 3,624 assertions.
- JavaScript: ESLint completed with zero warnings; all 47 Node tests passed.
- Dependency audits: npm reported 0 vulnerabilities at every severity; Composer reported no advisories and no abandoned packages.
- Production build: Vite 6.4.3 built 11 modules; final application assets include `app-B98H4mui.js` and `app-DDg2hDQZ.css`.
- Blade templates cached successfully; Laravel Pint passed all ADM-5 PHP files.
- Route inventory: 132 routes total; five progress routes include the existing learner write plus admin aggregate, superadmin list/detail, and supervisor detail.
- Query bound: the global identity page executed 20 SQL statements for one learner and the same 20 for a full 20-row page; no query is issued from a learner row loop.
- Browser desktop list at the default 1265 × 720 CSS viewport: 256-pixel sidebar, 1009-pixel main, 929-pixel table, and 1265/1265 client/scroll width.
- Browser mobile list at requested 390 × 844 (375-pixel CSS content width): 375-pixel main, 279-pixel filter form, 375/375 root client/scroll width, and internal table scrolling only.
- Browser learner detail rendered all 7 modules, 85 sections, 25 known activities, two retained progress states, package/version navigation, and no private response content.
- Browser supervisor at mobile width rendered the same-institution empty state at 390/390 client/scroll width; direct access to the Hotel B learner from the Hotel A supervisor rendered 404.
- Active package and hashes remain unchanged: version `0.4.0-draft`; source tree `e864b4fcaa9d182b8379cf7d179279114baa7fef46624bcb261be41b8072dc9a`; Laravel projection `0967725acf53b46349ad8000a6b8e652e8855f37978912e796c3391cd9587f7f`; standalone `55d1418fc75e359f85c9f7bb8ad1d93ea36943859b48f3c18a1db65039bf36c4`.
- Persistent neutrality: migrations remain through batch 9; 3 users, 2 pre-existing progress rows, 0 attempts, 0 responses, and 0 drafts. No ADM-5 migration or backup was required.

### Research basis

- Laravel 12 authorization/policy discovery and policy checks: https://laravel.com/docs/12.x/authorization
- Laravel 12 fixed pagination and query-string preservation: https://laravel.com/docs/12.x/pagination
- OWASP Authorization Cheat Sheet, least privilege, deny by default, every-request checks, and relationship-based access: https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html
- W3C WAI Tables Tutorial, captions and header/data-cell relationships: https://www.w3.org/WAI/tutorials/tables/

### Limitations and gates that remain open

- CSV export is intentionally unavailable. Do not add it without the separate approved contract documented above.
- Raw open responses, confidence answers/ratings, recordings, audio, and prompt-level response JSON remain outside every administration capability. A separate necessity/privacy/audit/retention decision is required before any such work.
- The admin aggregate is global only and does not expose institution slices. A cohort-suppression threshold has not been approved, so institution-level aggregate filtering was not invented.
- Automated markup checks and the local in-app Chromium run are regression evidence, not an independent screen-reader, assistive-technology, privacy, or cross-browser certification.
- Persistent data has only one learner and no same-institution supervisor learner. Browser QA therefore used the honest empty state and cross-institution denial; isolated feature tests provide the positive same-institution detail evidence.
- ADM-6 integration, audit-log review UI, complete release-security matrix, operations/recovery documentation, and deployment gate remain unstarted.
- The workspace has no usable Git `HEAD`; Git diff/commit evidence remains unavailable. This checkpoint, decision contract, tests, route inventory, hashes, and browser measurements are the reproducible change ledger.

### Rollback

ADM-5 has no migration to reverse. To roll back, remove the three progress administration route surfaces, controllers/request/service/policy abilities, navigation/views/localization/JavaScript loading state, and ADM-5 tests; restore the prior supervisor dashboard shell; rebuild assets; and rerun the full PHP/JavaScript/security/accessibility suites. Preserve the corrected active-completion intersection unless an independently verified replacement proves unknown codes cannot inflate current completion. Do not delete historical progress or response data to roll back read-only administration code.

### Next action after approval

Wait for the product owner to inspect this ADM-5 checkpoint. After an explicit `Continue`, begin ADM-6 only: administration navigation integration, audit-log review, the complete security/role/browser/recovery suite, and explicit deployment/rollback steps. Do not enable CSV export or raw learner-response access by implication.

## Administration Checkpoint ADM-6 — Administration integration, security review, and release readiness

Date: 2026-07-19  
State: implementation and local technical verification complete; paused for product-owner review and production operations evidence.

### Outcome

ADM-6 integrates the administration track around canonical-first destinations and a least-privilege audit review. Admin sees Content, Exercises, Progress, and read-only Legacy Evidence; superadmin additionally sees password-confirmed Users and Audit. Identity/role records and canonical draft/import/publication lifecycle records are reviewed through one fixed-page surface without merging their source tables or collecting learner response content.

The local code and test gates pass, active delivery remains immutable `0.4.0-draft`, and no ADM-6 migration was added. This checkpoint is release-readiness evidence, not production approval: the repository does not identify the production database, host, backup product, private-storage topology, mail provider, queue supervisor, or restore owner, and no production backup/restore drill was performed.

### Changes implemented

1. Replaced archive-first administration navigation.
   - The shared sidebar now presents canonical Dashboard, Content, Exercises, Progress, Users (superadmin only), Audit (superadmin only), and explicitly read-only Legacy Evidence destinations.
   - Added canonical exercise-workspace and legacy-evidence overview pages for both administration roles. Navigation visibility follows policy/role checks and performs no database query in the sidebar.
2. Added a policy-protected administration audit review.
   - A superadmin-only policy, validated allowlisted filters, fixed 50-row pagination, and one bounded query service present existing `administration_audits` and `curriculum_draft_events` without copying or rewriting their histories.
   - Review covers role changes, promotions, draft/content lifecycle, publication/rollback, and authority-DOCX import readiness/failure. Audit-screen access is written to the application security log using actor ID, filter key names, page, and visible count; filter values are excluded.
3. Minimized and bounded retained audit data.
   - A recursive sanitizer removes password/token/session/raw-response/answer/audio/private-path fields and bounds depth, item counts, and string length before model persistence and again before presentation.
   - Ordinary Eloquent update/delete operations on both audit models now fail closed. This is application-level append-only behavior, not a claim of database-administrator tamper resistance.
4. Added compiler outcome evidence.
   - Successful dry-run compilation records `docx_import_ready`; failed compilation records `docx_import_failed` with bounded identifiers, hashes, diff/error code, and source-deletion result only. Raw documents, paths, answers, learner content, and exception messages are not copied into audit metadata.
5. Corrected administration statistics and lifecycle separation.
   - Active canonical counts explicitly intersect published entities in the active package. Draft workspaces/entities and archived entities are displayed separately, as are retained legacy evidence counts.
   - Regression coverage proves archived draft entities cannot inflate active statistics.
6. Added deployment and recovery guidance.
   - `docs/operations/ADM-6_ADMINISTRATION_RELEASE_AND_RECOVERY.md` defines preflight evidence, coordinated database/private-storage checkpoints, required isolated restore proof, deploy/migration rules, failed import/publication recovery, canonical rollback, lost-superadmin handling, session revocation, audit use, conflict recovery, and code rollback.
   - It explicitly prohibits deployment seeding/silent promotion and blind `migrate:rollback` use.
7. Completed integration, security, accessibility, and browser coverage.
   - Extended role-route, smoke, source-pipeline, import, publication/rollback, progress-privacy, dashboard isolation, localization, markup/accessibility, and audit-integrity tests.
   - Browser QA covered learner, supervisor, admin, and superadmin shells plus admin canonical/legacy pages and the password-confirmed audit page at mobile width.

### New problems found and disposition

- The first isolated browser launcher inherited conflicting Windows `Path`/`PATH` entries through PowerShell, and Laravel's `artisan serve` injected `PHP_CLI_SERVER_WORKERS` even though this PHP Windows build cannot fork. Neither failure reached Laravel or modified data. QA was completed with the same direct `php -S` router used by the project's passing Playwright setup. The orphaned isolated PID and temporary launcher/logs were verified and removed after the Codex task crash.
- The test-only admin account was initially created through guarded mass assignment, so `role` and `email_verified_at` were correctly discarded. A parameterized direct update was used only in the disposable E2E SQLite database to create the intended browser fixture. Persistent application accounts were not changed.
- Browser QA found that Audit correctly required recent password confirmation, but the custom confirmation controller always returned to Users and rendered user-only copy. The controller now accepts only same-origin, exact Users/Audit paths, preserves only each page's allowlisted scalar filter keys, rejects external/cross-role/malformed destinations, clears the stored intended URL, and returns to the requested protected page. Two regression tests cover the successful Audit return and fail-closed fallback.
- The first post-fix regression test used `followRedirects()` without the response argument required by this Laravel test API. The application had not failed; the test was corrected to request the confirmation screen after middleware stored the protected destination.

### Verification evidence

- Post-fix full Laravel suite: 215 passed, 3,783 assertions, exit code 0.
- Focused audit/role-confirmation suites: 19 passed, 156 assertions, including same-origin destination filtering, per-filter length bounds, and hostile/stale/user-info intended-URL rejection.
- JavaScript: ESLint completed with zero warnings; all 47 Node tests passed.
- Isolated Chromium E2E: all 7 journeys passed in the disposable E2E database.
- Production build: Vite 6.4.3 built 11 modules; final assets include `app-B98H4mui.js` and `app-CVVXwcVJ.css`.
- Dependency audits: npm reported zero vulnerabilities at every severity; Composer reported no advisories and no abandoned packages.
- Laravel caches: route, configuration, and Blade view caches compiled successfully, followed by `optimize:clear`. Repository-wide Pint passed.
- Canonical verifier: 7 chapters, 85 sections, 25 activities, 124 prompts, 96 answer models, 96 feedback models, and 6 rubrics; status `verified`.
- Authority DOCX SHA-256: `7f8a2c62db6884498f7a1c2de56e79e39609262944a685fe7eb97f702444cbb4`.
- Active source-tree SHA-256: `e864b4fcaa9d182b8379cf7d179279114baa7fef46624bcb261be41b8072dc9a`.
- Active Laravel projection SHA-256: `0967725acf53b46349ad8000a6b8e652e8855f37978912e796c3391cd9587f7f`.
- Active standalone SHA-256: `55d1418fc75e359f85c9f7bb8ad1d93ea36943859b48f3c18a1db65039bf36c4`.
- Brand guard: zero active predecessor-brand matches; only exact historical/provenance allowlist records remain.
- Browser admin policy: Content, Exercises, Progress, and Legacy Evidence rendered; Users/Audit link counts were zero. Browser superadmin policy: all six canonical administration destinations rendered and Audit required password reconfirmation.
- Browser mobile layout at requested 390 × 844: all checked roots had equal client/scroll widths (375 or 390 CSS pixels according to page shell); the audit table scrolled only inside its 327-pixel container, not at document root. Browser console error/warning log was empty.
- Main database was not used for browser mutations. Its final read-only inventory is 3 users, 3 canonical activity-progress rows, 4 attempts, 4 responses, and zero drafts, imports, role-audit rows, or draft events; the latest retained attempt is timestamped `2026-07-18 20:24:44`, before the current isolated browser run. ADM-6 added no migration. Existing learner progress/attempt/response rows were preserved because their provenance cannot safely be attributed to ADM-6.

### Research basis

- Laravel 12 authorization and policy discovery: https://laravel.com/docs/12.x/authorization
- Laravel 12 authentication/password confirmation behavior: https://laravel.com/docs/12.x/authentication#password-confirmation
- Laravel 12 deployment and optimization: https://laravel.com/docs/12.x/deployment
- Laravel 12 migration isolation: https://laravel.com/docs/12.x/migrations#isolating-migration-execution
- OWASP Logging Cheat Sheet, sensitive-data exclusion, access restriction, integrity, failure handling, and monitoring: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html

### Limitations and production gates that remain open

- The local deployment checker correctly fails release mode because this workspace uses `APP_ENV=local`, debug enabled, HTTP `APP_URL`, non-secure session cookie, report-only CSP, local HSTS behavior, a Vite hot file, and installed development dependencies. This is expected for local development and is explicit evidence that this snapshot must not be represented as production-configured.
- The production database engine, private/object-storage layout, backup product, host/release mechanism, mail provider, queue supervision, centralized logging/tamper detection, and named restore/audit-retention owners are not declared. A coordinated database/private-storage backup and successful isolated restore drill remain mandatory before deployment.
- Audit rows are append-only through ordinary Eloquent operations only. Database administrators and compromised database credentials can still alter rows; production least-privilege grants, backups, log shipping/tamper detection, access monitoring, and an approved retention/disposal schedule remain required.
- Audit-screen access is emitted to the application security log rather than inserted into the unified audit tables, avoiding self-generating review rows. Production log retention and monitoring must be configured operationally.
- Automated accessibility markup/keyboard checks and one local Chromium rendering are regression evidence, not an independent screen-reader, assistive-technology, privacy, penetration-test, or cross-browser certification.
- The browser audit table used an honest empty isolated state; populated/filter/pagination/sanitization behavior is covered by feature tests. No raw learner-response administration or CSV export was enabled.
- The workspace has no usable Git repository/`HEAD`; commit/diff evidence is unavailable. The checkpoint, route/test inventories, hashes, lockfiles, generated manifest, and reproducible commands are the change ledger.
- CF-7 human publication gates remain open, including content/ESP/CEFR/hospitality/accessibility/rights/link/retention/qualified-review approvals. Active delivery therefore remains `0.4.0-draft` even though the ADM administration track is technically complete.

### Rollback

ADM-6 adds no migration. To roll it back, remove the canonical exercise/legacy/audit overview routes and views, unified audit review request/controller/service/policy, audit sanitizer/model guards, compiler outcome events, navigation/dashboard localization and tests, then rebuild assets and rerun the full role/security/pipeline/privacy/accessibility/browser gates. Preserve retained audit records, role changes, draft/import/publication histories, learner progress, and the corrected active-published statistics intersection. Reverting code is not authorization to delete evidence or rewrite an active/published package.

### Next action after approval

Wait for the product owner to inspect this ADM-6 checkpoint. There is no ADM-7 implementation phase in the approved roadmap. A production release may proceed only after the deployment owner supplies the missing environment topology, names the required operators/owners, records a coordinated database/private-storage backup, completes a successful isolated restore drill, passes the deployment checker under production configuration, and closes every applicable human publication gate. Do not seed/promote an account, enable CSV/raw-response access, or publish `0.4.0` by implication.

# NG-B06-C Class learning-context cutover checkpoint

- Saved: 2026-07-22 (Asia/Jakarta)
- State: complete; local technical B06 acceptance gates passed
- Owner approval used: `docs/decisions/NG-B06-DECISIONS.md`
- Authorized next batch after closure: B07 only after its complete mandatory questionnaire is answered and recorded

## Outcome

B06-C cuts learner delivery and institution-attributed progress over to the exact Class relationship created in B06-A and operated in B06-B.

- A learner may select an active Class only through an active enrollment, active Institution membership, active learner role, and active Class lifecycle state.
- Class delivery uses the immutable Course Revision and canonical package pinned by that Class. Only the ordered modules selected by the Revision are available.
- Direct requests for a module, section, activity, or asset outside the selected Class Revision fail closed.
- Attempts, progress, and completion rows record the exact Institution membership, Class, and enrollment identifiers. Existing personal and Institution history is retained.
- A suspended or otherwise stale Class context fails closed on the current request and is removed from the session for later recovery. It does not erase learning history.
- A Class invitation selects the exact new Class enrollment after acceptance.
- Global curriculum search results are suppressed while Class context is selected so search cannot advertise content the Class does not deliver. Help and glossary results remain available.
- Instructor progress access is exact-Class and exact-enrollment. Institution-wide learner progress is restricted to Institution Admin or System Admin authority, and Instructor dashboards no longer enumerate institution-wide learner activity.
- A retired canonical release remains deliverable only to a Class that already pins it and only when the original approval and activation evidence is complete. Draft, withdrawn, corrupted, or never-activated releases remain blocked.
- Legacy `users.role` and `instansi` values remain readable provenance. They no longer authorize Instructor or Institution Admin operations.

## Main implementation surface

Schema and retained evidence:

- `database/migrations/2026_07_22_000021_cut_learning_progress_over_to_class_context.php`
- Class foreign keys on curriculum progress, attempts, and completion records
- preflight refusal for incomplete active legacy mappings
- rollback refusal after retained Class-attributed progress exists

Server scope and delivery:

- `app/Services/LearningContext.php`
- `app/Services/LearningContentScope.php`
- `app/Services/CanonicalCurriculumRepository.php`
- `app/Services/Curriculum/CurriculumAttemptService.php`
- `app/Services/Curriculum/CurriculumReleaseGuard.php`
- `app/Services/ProgressAdministrationService.php`
- `app/Services/InstitutionAccessService.php`
- `app/Services/WorkContext.php`
- learner, search, invitation, progress, and supervisor controllers and requests that consume those services

Interface:

- learner Class selector and selected-Class description
- Class-bounded learning dashboard and navigation
- exact-Class learner-progress link from the roster
- bounded search notice when curriculum results are unavailable in Class context

Verification coverage:

- `tests/Feature/ClassLearningContextTest.php`
- extended curriculum release, authorization, invitation, progress, dashboard-query, role, onboarding, registration, and migration tests
- `tests/E2E/critical-path.spec.mjs`
- `scripts/quality/verify_learning_ui.py`

## Authority and primary-source gates

The governing sources matched before implementation:

- Thesis: `BA64B2EB69673E84FC83E21D4617CD111691EE6340FC2F4331948FE6AF5EC132`
- Learning materials: `7F8A2C62DB6884498F7A1C2DE56E79E39609262944A685FE7EB97F702444CBB4`

The implementation follows Laravel 12's official foreign-key, policy, and transaction-locking contracts and OWASP's deny-by-default, per-request authorization guidance:

- <https://laravel.com/docs/12.x/migrations#foreign-key-constraints>
- <https://laravel.com/docs/12.x/authorization#authorizing-actions-using-policies>
- <https://laravel.com/docs/12.x/queries#pessimistic-locking>
- <https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html>

## Migration and rollback boundary

Migration `000021` is expand-first. It adds nullable scope identifiers, verifies the active normalized mappings before alteration, and enforces composite relationships to the exact enrollment, membership, and Class.

Its rollback succeeds only before Class-attributed learning records exist. After Class learning begins, operational rollback is to disable the new interface and retain the relationships and history. It must not drop or rewrite teaching evidence.

All mutation tests used disposable SQLite databases. The user's main database was not migrated. Migrations `000019`, `000020`, and `000021` remain pending there.

## Defects corrected during the gate

- Sessionless service requests no longer fail because `InstitutionContext` now checks whether a session store exists before reading or writing it.
- Tests that claim Instructor authority now create the normalized Institution role assignment instead of depending on the removed legacy fallback.
- Release validation reuses an already eager-loaded, typed release relationship, removing duplicate database reads without removing checksum, lifecycle, approval, or activation checks.
- The ignored `public/hot` marker that pointed to a stopped Vite server was removed. `npm run dev` recreates it when a live development server starts.

## Verification evidence

- B06-C focused Class context: passed.
- Related release, authorization, invitation, progress, dashboard-query, and registration groups: passed after the verified fixture and session-boundary corrections.
- PHPStan: passed with no errors.
- Pint on the implementation set: passed.
- Production Vite build, ESLint, 55 Node tests, traceability verification, and tracked-secret scan: passed before the final documentation gate.
- Native Python Playwright visual run: 31 checks passed across desktop and mobile viewports.
- Playwright application matrix: 56 tests passed in the clean headless Chromium/WebKit/mobile run. Firefox's headless process failed before its first test because its local software compositor could not map the default framebuffer. The same untouched Firefox project then passed all 14 tests in headed mode, split 8 plus 6 after one navigation wait was retried successfully.
- Full PHP regression: 374 tests, 5,785 assertions, passed in 539.90 seconds.
- Protected authority verification: both approved SHA-256 values matched after the final roadmap update.
- Governance verification: 43 findings mapped; 1,680 tracked files scanned; two reviewed allowlisted findings and zero unallowlisted findings.
- `git diff --check`: passed.

The Firefox result is browser-engine evidence, not proof that the local headless graphics environment is healthy. The Playwright device profiles are not physical-device, branded-browser, assistive-technology, independent accessibility, lecturer-usability, or production evidence.

## Remaining gates

- The local PHP runtime still lacks the declared `intl` extension; changing the machine-level PHP installation is outside this repository checkpoint.
- The main database requires reviewed backup and migration execution for `000019` through `000021` before the local installed application uses the new schema.
- Production secrets, topology, mail/queue delivery, backup restoration, monitoring, qualified privacy/legal, accessibility, academic/content and security reviews, real lecturer research, and physical target-device/browser evidence remain open.
- B07 is not authorized by this checkpoint. Its questionnaire must be answered and recorded before assignments or directed learning are implemented.

## Completion state

B06-A, UI-R02, B06-B, and B06-C are locally complete. B06 does not authorize B07 and does not close the production, specialist-review, physical-device, branded-browser, usability-study, or machine-runtime gates listed above.

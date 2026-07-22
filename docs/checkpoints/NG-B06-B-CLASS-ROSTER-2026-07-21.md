# NG-B06-B Class and roster operations checkpoint

- Saved: 2026-07-21 (Asia/Jakarta)
- State: B06-B complete; B06-C is the next authorized phase
- Owner approval used: `docs/decisions/NG-B06-DECISIONS.md`
- Next authorized phase after closure: B06-C context cutover and final B06 verification

## Outcome

B06-B adds a usable, institution-scoped Class workspace on top of the B06-A domain foundation and the UI-R02 shell.

- Institution Admins can create a Course with an immutable ordered Course Revision, or create a Class from an existing Revision.
- Institution Admins see every Class in their Institution. Instructors see and manage only Classes with an active teaching assignment.
- Each Class has one active primary Instructor and may have co-instructors. Replacing or revoking an assignment retains the earlier record.
- Staff can enroll an eligible Institution learner manually, suspend/reactivate/remove the enrollment, or transfer it to another authorized Class. Every change writes append-only history.
- Class invitations and classroom codes are scoped to one Class. Approval grants/reactivates the learner role when needed and enrolls only in that Class.
- The roster shows the learner name, learner role, enrollment state, joined date, and Class history. It does not show email, private responses, personal self-study progress, security data, or unrelated profile data.
- Class lifecycle changes remain explicit and audited. Calendar dates do not change lifecycle state automatically.
- A safe copy keeps the pinned Course Revision and timezone, creates a new primary assignment, and does not copy learners, progress, submissions, answers, grades, invitations, codes, dates, or audit history.
- Learner preview is synthetic. It does not impersonate an account and cannot create progress, attempts, answers, or submissions.
- Roster pages are paginated and operational history/selector queries are bounded.

## Authority and research gates

The governing sources matched before implementation:

- Thesis: `BA64B2EB69673E84FC83E21D4617CD111691EE6340FC2F4331948FE6AF5EC132`
- Learning materials: `7F8A2C62DB6884498F7A1C2DE56E79E39609262944A685FE7EB97F702444CBB4`

The stale thesis value in `config/authority-sources.json` was corrected to the current governing hash. The repository verifier then matched both files. Current primary references used for adjacent defect corrections were Laravel 12's CSRF-exclusion contract, Composer's version-constraint contract, and PHPStan's type/error guidance.

## Main implementation surface

Schema and retained evidence:

- `database/migrations/2026_07_21_000020_add_class_roster_operations.php`
- `app/Models/CourseEnrollmentEvent.php`
- extended Class-scoped invitation, classroom-code, join-request, enrollment, and teaching-assignment models

Server workflows and authorization:

- `app/Services/ClassWorkspaceService.php`
- `app/Services/CourseEnrollmentService.php`
- `app/Services/TeachingAssignmentService.php`
- extended `InstitutionInvitationService` and `InstitutionJoinCodeService`
- `app/Http/Controllers/ClassWorkspaceController.php`
- `app/Http/Controllers/ClassRosterController.php`
- `app/Http/Controllers/ClassConnectionController.php`
- Class request validation, policies, and 19 named staff routes

Interface and copy:

- `resources/views/supervisor/classes/index.blade.php`
- `resources/views/supervisor/classes/create.blade.php`
- `resources/views/supervisor/classes/show.blade.php`
- `resources/views/supervisor/classes/preview.blade.php`
- `lang/en/classes.php` and `lang/id/classes.php`
- the existing staff sidebar, invitation acceptance, and enrollment history screens

Verification:

- `tests/Feature/ClassRosterOperationsTest.php`
- extended security, invitation, classroom-code, work-context, theme, and Playwright coverage
- a testing-environment-only disposable Class fixture guarded to the isolated E2E SQLite path

## Migration and data boundary

Migration `000020` adds optional Class scope to invitations, classroom codes, and join requests; permits repeated retained teaching-assignment history while enforcing one active assignment per membership; and creates append-only enrollment events with tenant-composite foreign keys.

Its `down()` path refuses to discard Class connections, roster events, or repeated assignment history. Operational rollback after data exists is to disable the feature and retain the tables.

All mutation and browser checks used disposable SQLite databases. The user's localhost database was not migrated. No account, session, curriculum record, retained evidence, or user file was deleted.

## Security, privacy, accessibility, and quality corrections

- Every service rechecks the current actor, Institution, membership, exact Class, role, lifecycle, and locked database rows before writing.
- Composite foreign keys reject raw cross-tenant enrollment and connection records.
- Direct unauthorized Class access returns not found. Hidden navigation is not used as authorization.
- The CSP reporting endpoint now uses Laravel's documented CSRF exception mechanism for browser-generated reports and remains rate-limited and payload-bounded.
- The Fortify constraint now permits compatible security updates from `1.37.2`; `ext-intl` is an explicit runtime requirement; strict Composer validation passes.
- PHPUnit applies the required 512 MB test-process memory limit from its XML configuration, and the Composer test script disables Composer's process timeout for the long regression suite.
- PHPStan model/cast types and affected repositories were corrected. PHPStan reports no errors against the current 384-entry pre-existing legacy baseline; no new finding was added to hide B06-B code.
- Staff-facing "Supervisor" copy is now "Instructor" while deliberately retained legacy internal route/model names remain unchanged.
- New controls meet the repository's 44 CSS pixel target convention, the Class module checkbox cannot flex-shrink, and every new theme-sensitive utility maps to semantic light/dark tokens.

## Verification evidence

- B06-B behavior: 6 tests, 47 assertions, passed.
- Related B06, context, time, security, role, classroom-code, and invitation regression: 62 tests, 758 assertions, passed.
- Focused accessibility correction: 1 test, 16 assertions, passed.
- Node regression: 55 tests passed.
- ESLint: passed with zero warnings.
- PHPStan: passed with no errors beyond the accepted pre-existing baseline.
- Strict Composer validation: passed.
- Composer audit: zero advisories and zero abandoned packages.
- npm audit: zero vulnerabilities.
- Production Vite build: passed.
- Governance verification: 43 findings mapped; 1,680 tracked files scanned; two reviewed allowlisted findings and zero unallowlisted findings.
- Playwright Chromium Desktop Chrome profile: assigned-Class journey passed.
- Playwright mobile Chromium Pixel 7 profile: the same journey passed with no root overflow.
- Full PHP regression: 370 tests, 5,754 assertions, passed in 514.03 seconds.

The browser journey covered MFA sign-in, invitation issuance, exact-assignment Class discovery, roster rendering, learner-email exclusion, the synthetic preview boundary, console/page errors, and root overflow. These are Playwright engine/device-profile results, not branded-browser, physical-device, assistive-technology, independent accessibility, lecturer-usability, or production evidence.

## Remaining gates

B06-C still must cut the active Class context through the relevant learner/progress queries, verify legacy mapping, and close the B06 completion matrix. B07's questionnaire remains mandatory before assignments or directed learning are implemented.

The local PHP runtime still lacks the `intl` extension now declared by Composer. The user's main database still needs migrations `000019` and `000020` after backup/review. The ignored `public/hot` marker still points to a stopped Vite server; it was not deleted because the repository requires explicit permission for the exact file target. Production secrets, topology, mail/queue delivery, backup restoration, monitoring, qualified reviews, real lecturer research, branded browsers, physical devices, and the local Firefox/SWGL environment remain open.

## Rollback boundary and next action

A code rollback must remove the B06-B routes, controllers, views, services, tests, and schema expansion together. Once operational history exists, do not run the destructive migration rollback; disable the feature and retain its evidence.

Resume only B06-C. Do not begin B07 in this checkpoint.

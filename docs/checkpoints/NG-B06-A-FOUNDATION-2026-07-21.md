# NG-B06-A domain, authorization, and time foundation checkpoint

- Saved: 2026-07-21 (Asia/Jakarta)
- State: B06-A completed; B06 remains in progress
- Owner approval used: every recommendation in `docs/decisions/NG-B06-DECISIONS.md`
- Next checkpoint: UI-R02 responsive context shell

## Outcome

B06-A now supplies the tested application contract needed by the next interface slice:

1. An Institution owns Courses.
2. A Course Revision is an immutable, ordered selection of published chapter modules from one active canonical curriculum package.
3. A Class (`CourseOffering` in code) pins one Course Revision and uses the approved `draft`, `enrollment_open`, `active`, `closed`, or `archived` state vocabulary. A restrictive forward-only lifecycle service rejects stale, unauthorized, direct, and invalid transitions and writes append-only transition evidence.
4. Enrollment connects one same-Institution membership to a Class. Teaching Assignment connects one same-Institution membership as primary or co-instructor.
5. One active primary instructor is enforced. Revocation frees the active uniqueness slot without rewriting the historical primary-role evidence.
6. System Admin remains global. Institution Admin can manage its Institution's Courses and Classes. An Instructor requires both an active Institution Instructor role and an active assignment to the exact Class. A learner requires an active Institution learner role and active enrollment to view the Class.
7. Institution, Class, and user timezone values accept only current IANA identifiers. `Asia/Jakarta` is backfilled only for Politeknik Negeri Malang.
8. One PSR Clock binding provides UTC instants. One TimeContext applies the approved Class > Institution > UTC schedule precedence and user > Class > Institution > UTC display precedence. Nonexistent DST wall times are rejected, and repeated wall times require an explicit earlier/later choice.

This checkpoint adds no route, controller, screen, or navigation item. It therefore does not claim that a lecturer can use the new Class functionality yet.

## Authority and source gates

Both documents were re-hashed immediately before implementation and matched the governing roadmap:

- Thesis: `BA64B2EB69673E84FC83E21D4617CD111691EE6340FC2F4331948FE6AF5EC132`
- Learning materials: `7F8A2C62DB6884498F7A1C2DE56E79E39609262944A685FE7EB97F702444CBB4`

Technical choices were checked against current primary documentation:

- Laravel 12 authorization policies: https://laravel.com/docs/12.x/authorization
- Laravel 12 migrations and foreign-key constraints: https://laravel.com/docs/12.x/migrations
- Laravel 12 isolated database testing: https://laravel.com/docs/12.x/database-testing
- PSR-20 Clock: https://www.php-fig.org/psr/psr-20/
- PHP current IANA timezone identifiers: https://www.php.net/manual/en/datetimezone.listidentifiers.php
- PHP timezone transitions: https://www.php.net/manual/en/datetimezone.gettransitions.php

## Changed implementation surface

Repository-wide operating defaults:

- `AGENTS.md`

Domain and time vocabulary:

- `app/Enums/CourseEnrollmentStatus.php`
- `app/Enums/CourseOfferingStatus.php`
- `app/Enums/LocalTimeDisambiguation.php`
- `app/Enums/TeachingAssignmentRole.php`

New domain models:

- `app/Models/Course.php`
- `app/Models/CourseRevision.php`
- `app/Models/CourseRevisionModule.php`
- `app/Models/CourseOffering.php`
- `app/Models/CourseOfferingEvent.php`
- `app/Models/CourseEnrollment.php`
- `app/Models/TeachingAssignment.php`

Extended relationships/settings:

- `app/Models/Institution.php`
- `app/Models/InstitutionMembership.php`
- `app/Models/User.php`
- `app/Models/CurriculumPackage.php`
- `app/Models/CurriculumEntity.php`

Application services and policies:

- `app/Services/CourseAccessService.php`
- `app/Services/CourseOfferingLifecycle.php`
- `app/Services/CourseRevisionService.php`
- `app/Services/Time/IanaTimeZone.php`
- `app/Services/Time/TimeContext.php`
- `app/Policies/CoursePolicy.php`
- `app/Policies/CourseRevisionPolicy.php`
- `app/Policies/CourseOfferingPolicy.php`
- `app/Providers/AppServiceProvider.php`

Schema and tests:

- `database/migrations/2026_07_21_000019_establish_course_class_and_time_foundation.php`
- `tests/Feature/CourseClassFoundationTest.php`
- `tests/Feature/TimeContextTest.php`

Governance:

- `docs/HOSPITRAINITY_NEXT_GENERATION_AUDIT_AND_UPDATE_ROADMAP.md`
- `docs/decisions/NG-DECISION-REGISTER.md`
- `docs/traceability/ng-traceability.json`
- this checkpoint

## Migration and data impact

The expand-only migration:

- adds nullable `timezone` columns to `institutions` and `users`;
- backfills `Asia/Jakarta` only for the existing `politeknik-negeri-malang` key;
- creates Courses, Course Revisions, ordered Revision Modules, Classes, Enrollments, Teaching Assignments, and append-only Class lifecycle events;
- adds composite uniqueness and foreign keys so raw database writes cannot combine a Class, Course, Revision, curriculum module, membership, or Institution from different scopes;
- uses restrictive foreign keys and model lifecycle guards to retain teaching and roster history.

The migration was applied and rolled back only in PHPUnit's disposable SQLite `:memory:` database. The user's localhost database was not migrated. No existing file, record, session, account, or retained evidence was deleted.

Once real B06 data exists, operational rollback must disable the feature while preserving the new tables. Running the destructive migration `down()` against populated production data is not an approved rollback procedure.

## Security, privacy, and accessibility

- Authorization is relationship-based and deny-by-default. Visibility is not treated as authorization.
- Composite database constraints provide a second tenant boundary below model and policy checks.
- A Class pins immutable curriculum evidence; no active canonical package was edited.
- This phase adds relationship records only. It does not expose learner responses, personal self-study progress, grades, security data, or unrelated profile data.
- No UI was added, so there is no new visual, keyboard, screen-reader, responsive, or browser evidence to claim. Those checks belong to UI-R02 and B06-B/C.

## Verification evidence

All mutation tests used the isolated test database.

- PHP syntax checks: passed for every B06-A PHP file.
- Laravel Pint: passed after formatting the B06-A files.
- Targeted PHPStan: 19 B06-A files, zero errors.
- B06-A feature matrix after the final lifecycle and TimeContext corrections: 13 tests, 55 assertions, passed.
- B05/B06 compatibility matrix on the final code state: 29 tests, 190 assertions, passed.
- Full Laravel regression suite on the final code state: 361 tests, 5,591 assertions, passed.
- `git diff --check`: passed before governance records were written and is repeated at checkpoint close.

Full-project PHPStan still reports nine errors in the already-dirty UI work (`CanonicalCurriculumRepository`, `CurriculumStepPlanner`, and `CurriculumDraftPreviewRepository`). Targeted B06-A analysis is clean. Those unrelated errors were not suppressed, re-baselined, or silently edited as part of this backend checkpoint.

Browser testing was intentionally not run: B06-A has no rendered surface or interaction flow. Source inspection and PHP tests are not represented as browser or accessibility evidence.

## Known limitations and remaining gates

B06-A does not yet provide:

- the responsive Institution/Class context shell;
- a Class selector in Work Context;
- Instructor or Institution Admin Class screens;
- roster invitation, code, manual enrollment, suspension, transfer, or removal application workflows;
- safe synthetic learner preview;
- exact legacy Class mapping or Class-scoped progress cutover.

Production topology, mail/push delivery, queues/scheduling, secrets, backup restoration, monitoring, qualified privacy/legal review, qualified accessibility review, academic/content review, independent security review, and physical target-device/browser evidence remain unverified production gates.

## Safe next action

Start UI-R02 only: build and visually verify the responsive desktop rail, mobile navigation, account/context bar, modern return control, help placement, and active-state behavior against the real B06-A Institution/Class contract. Then stop at the UI-R02 checkpoint before B06-B.

After UI-R02, B06-B adds task-oriented Instructor and Institution Admin Class/roster operations. B06-C then adds Class context cutover and completes B06 verification. B07's questionnaire remains mandatory before assignments are implemented.

# CP-13 Legacy Removal Report

## Scope of “legacy” in this migration

Two distinct things have carried the “legacy” label across this migration and
they get different treatment:

1. **The frozen hand-authored `window.STAYREADY` standalone payload** —
   already retired at CP-10 (DEC-032). The standalone has been a deterministic
   projection of the compiled canonical store since CP-10; there is nothing
   further to remove here.
2. **The live Laravel course-delivery application** (`app/`, `resources/`,
   `database/`, `tests/`, `routes/`) — the pre-migration LMS that the
   Hospitrainity curriculum is being migrated *out of* and *into a new,
   DOCX-derived, deterministically rendered platform*. This is the subject of
   this report.

## Inventory of the live Laravel stack

- **Controllers:** `Auth\{RegisterController, LoginController,
  PasswordResetController, EmailVerificationController}`,
  `DashboardController`, `Superadmin\AdminDashboardController`,
  `ModuleController`, `LessonController`, `ExerciseController`,
  `VocabularyController`, `MaterialController`, `ProgressController`,
  `Supervisor\SpvDashboardController`.
- **Models:** `Completion`, `Concerns\Completable`, `Exercise`, `Lesson`,
  `Material`, `MaterialItem`, `Module`, `User`, `Vocabulary`,
  `VocabularyItem`.
- **Middleware:** `CheckRole`, `SetLocale`.
- **Views:** 23 Blade templates.
- **Seed/fixture data:** `database/data/course.json` (contains 6
  “Youkata Stay Hotel” references, consumed by `VocabularySeeder`),
  `database/seeders/{VocabularySeeder, MaterialSeeder, ExerciseSeeder,
  UserSeeder}`, wired in that order by `DatabaseSeeder`.

## Decision: recommend, do not execute, decommission

**This checkpoint does not delete, move, or archive any file in the live
Laravel application.** Recommendation only, for three concrete, sourced
reasons (Notes 1/2/4 — no invented safety claim):

1. **It is a live, functioning LMS, not dead code.** It has registration,
   auth, role-based dashboards, progress tracking, and its own seeded content
   model. “Legacy” here means *superseded by the new curriculum pipeline*,
   not *unused* — those are different claims and only the first is true.
2. **No PHP/Composer runtime exists in this sandbox** (binding constraint
   since CP-08). Any deletion of PHP-referenced files (routes, service
   providers, views wired into `routes/web.php`) cannot be verified by
   actually booting the app or running its test suite. Deleting
   unverifiable-by-execution code in a live application is precisely the
   kind of change the anti-hallucination protocol (Notes 1, 2, 4) exists to
   prevent — a plausible-looking deletion could silently break the app in a
   way nothing in this sandbox would catch.
3. **Live user-progress data migration is out of scope for this migration
   plan.** The `CHECKPOINTS.md` roadmap (CP-01–CP-13) never included a data
   migration step for existing learner records (`Completion`, `Progress`)
   out of the Laravel/MySQL schema into the new PostgreSQL+SQLite model
   (DEC-026/DEC-027). Removing the app that owns that data before that
   migration exists would orphan it with no sourced plan to recover it.

## What *is* removed/retired at this checkpoint

- The **StayReady** brand identity is retired from every live/current-facing
  surface (see `rebrand-report.md`) — this is a rename, not a deletion.
- No files are deleted in CP-13. (Compare: CP-10 retired the in-code
  `window.STAYREADY` payload by replacing it with a generated projection;
  that removal was verifiable by render-determinism proof. No equivalent
  proof is available here.)

## Recommended decommission path (future work, not executed here)

1. Stand up the PHP/Composer runtime and full test suite (`phpunit`,
   `artisan test`) in an environment that has it, and get it green against
   the current `main` branch, before touching anything.
2. Write and run a one-time data migration: `Completion`/progress rows →
   the new canonical store's lifecycle/approval model (or an explicit
   “no equivalent” decision if the new platform intentionally does not
   carry over legacy progress).
3. Point-in-time freeze: stop writes to the Laravel app (maintenance mode),
   export final progress data, cut over routing to the new standalone/
   platform.
4. Only after (1)–(3) are complete and verified, remove the Laravel
   controllers/models/views/seeders listed above in a dedicated commit,
   with the PHP test suite passing before and after.
5. Retain `database/data/course.json` and legacy seeders in version control
   history (git) even after removal from the working tree — they are the
   only remaining record of the pre-migration “Youkata Stay Hotel” fixture
   content and may be needed for audit.

## Limitations

- This report is a recommendation, not an action. No live application code
  was deleted, moved, or disabled in CP-13.
- The 3-step blocking rationale above (live app, no PHP runtime, no data
  migration plan) should be re-verified before executing the decommission,
  in case circumstances have changed (e.g. a PHP runtime becomes available).

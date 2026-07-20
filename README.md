# Hospitrainity

Hospitrainity is a Laravel 12 application for hospitality-English learning. It delivers a checksum-locked, versioned curriculum to verified learners, records scope-separated activity completion, gives institution-scoped instructors an attributed progress view, and exposes retained legacy content to System Admins as read-only evidence while the canonical package is active.

## Implemented features

- Personal self-study registration, higher-assurance email invitations, adjustable-duration classroom join codes with staff approval, login with optional remember-me, logout, password reset, email verification, and layered request throttling.
- Expand-first normalized roles: global System Admin; institution-scoped Institution Admin, Instructor, and Learner; plus a separately assignable Content Author capability. The legacy four-role column remains temporarily for compatibility.
- Explicit work-role, institution, and learning-context switching. Personal progress remains private; institution staff see only activity recorded in the selected approved institution membership.
- English and Indonesian interface locales. Canonical source content remains English where no approved translation exists.
- Versioned canonical chapters, sections, activities, prompts, model answers, feedback, rubrics, provenance, and lifecycle metadata.
- Per-activity learner completion with retry-safe persistence and institution-scoped supervisor progress.
- A 14-type canonical exercise contract: 12 templates are enabled end to end, while `spelling_quiz` and `listening_task` remain visibly unavailable until an approved equivalent prerecorded-audio and accommodation policy exists.
- Responsive, keyboard-operable public navigation, learner account menu, and admin dialogs.
- Enforced security headers/CSP support, HTTPS production readiness checks, role policies, CSRF protection, canonical email storage, and bounded progress payloads.
- Deterministic curriculum dry-run, contained import/repair, verification, standalone generation, reports, checksums, and rollback artifacts that preserve release evidence.
- A fail-closed curriculum-release state machine with seven immutable human-evidence gates; the existing `0.4.0-draft` remains a labeled non-production preview and cannot be delivered in production.

## Requirements

- PHP `8.2` or newer with `mbstring`, `pdo_sqlite` (or the selected production database driver), `bcmath`, `intl`, and `fileinfo`.
- Composer 2.
- Node.js 24 LTS and npm 11. The package also accepts Node 25/26 for local tooling, but CI uses the LTS line.
- A database supported by `config/database.php`. Local setup defaults to SQLite.

The browser E2E suite needs Playwright's patched Chromium, Firefox, and WebKit engines. Its install command is included below.

## Source-control and private-artifact boundary

The B00 baseline is intended for a private GitHub repository owned by `dhionsetio`. GitHub Free does not enforce protected branches or rulesets on private repositories, so no remote publication should occur until a passphrase-protected SSH signing key is configured and the bootstrap commit/tag hashes have been reviewed. The current local baseline, approved choices, plan limitation, and protected-authority mechanism are recorded in [NG-B00 decisions](docs/decisions/NG-B00-DECISIONS.md).

Authority DOCXs, databases, `.env`, private storage/uploads, backups, rendered audit evidence, caches, dependencies, and generated test/build artifacts are never repository inputs. The tracked-content guard checks both forbidden artifact paths and high-confidence secret patterns without printing matched values:

```powershell
npm run governance:verify
```

## Clean local setup

```powershell
Copy-Item .env.example .env
& 'C:\php\php.exe' 'C:\composer\composer.phar' install
& 'C:\php\php.exe' artisan key:generate
New-Item -ItemType File -Path database\database.sqlite -Force
& 'C:\php\php.exe' artisan migrate
& 'C:\php\php.exe' artisan storage:link
& 'C:\Program Files\nodejs\npm.cmd' ci
& 'C:\Program Files\nodejs\npm.cmd' run build
```

Database seeding is fail-closed. It exits before any database write unless `HOSPITRAINITY_DEMO_SEED=true`, the runtime is explicitly `local` or `testing`, and three separate process-supplied secrets of at least 24 characters are present. Production is rejected even if the flag is accidentally enabled. Use it only with a disposable database; it creates Hospitrainity HQ plus Hotel A/Hotel B testing fixtures and never uses a literal reusable password. Demo identities are written in one transaction, and an idempotent rerun refuses to overwrite an account or institution that lacks disposable-fixture provenance.

On a POSIX shell, use `cp .env.example .env`, `touch database/database.sqlite`, `php`, `composer`, and `npm` in place of the explicit Windows commands.

Start all development services:

```powershell
& 'C:\php\php.exe' 'C:\composer\composer.phar' run dev:windows
```

`dev:windows` starts the Laravel server, database queue listener, and Vite without Laravel Pail because Pail requires the `pcntl` extension that is normally unavailable on Windows. On a compatible POSIX system, `composer run dev` also starts Pail.

### Bootstrap and enrollment onboarding

Public registration creates only a personal, unverified Learner account and exposes no institution directory. The normalized-identity migration does not delete sessions. After a verified backup, while the application is in maintenance mode with no traffic, an owner-approved operator must explicitly revoke every database session and record completion:

Before migration, run the read-only preflight on a restorable rehearsal copy and review its user, exact-mapping, unmatched, demo-identity, and session counts. Run it again immediately after migration; the post-migration run exits non-zero if any normalized-identity invariant fails:

```powershell
php artisan hospitrainity:identity-migration-preflight
```

The default report contains SHA-256 hashes of legacy institution labels rather than plaintext values or email addresses. Use `--include-legacy-values` only in an access-controlled operator session when the owner has authorized the exact mapping review; protect that output as sensitive migration evidence.

```powershell
php artisan hospitrainity:finalize-identity-migration --confirm=REVOKE-ALL-DATABASE-SESSIONS
```

That command is intentionally destructive: it deletes all rows in the configured database session table and requires every user to sign in again. It refuses without the exact confirmation, a database session driver, and the migrated identity tables. The production-readiness check and Superadmin bootstrap both remain blocked until the command succeeds. Do not run it against the authoritative database without the owner's permission for this exact deletion.

Only after that recorded finalization may the initial production Superadmin be created exactly once with a controlled mailbox and the guarded command below. The command creates no usable default password, sends a time-limited password-reset link, refuses if a Superadmin exists or bootstrap has completed, and leaves email verification pending.

```powershell
php artisan hospitrainity:bootstrap-superadmin "owner@example.org" "Verified owner name" --institution=hospitrainity-hq --confirm=BOOTSTRAP-INITIAL-SUPERADMIN
```

Do not run that example with placeholder identity data. Configure and test the real mail transport first. Once verified, System Admin may manage enrollment for any active institution; Institution Admins and Instructors may do so only in their active institution context. Content Author alone has no enrollment authority. Invitation links are target-email-bound, scoped to one institution, expiring, revocable, single-use, and stored only as hashes. If synchronous delivery fails, the new invitation is transactionally revoked and bounded audit evidence is recorded. Cross-institution invitation IDs use the same not-found result as unknown IDs, and a concurrent canonical-email collision rolls back to the bounded unavailable outcome. An authenticated matching account may add a Learner membership without receiving any staff role.

An Instructor or Institution Admin may instead issue a reusable classroom code with a selected duration from one second through 30 days and a bounded use count. The form defaults to one hour. Only a keyed one-way hash and four-character display suffix are retained. Redeeming the code creates a pending request, not a membership; authorized institution staff approve or reject it. Approval grants only the institution Learner role. The learner explicitly selects personal or institution learning context, and pre-existing personal progress is neither copied nor disclosed to the institution.

The log mailer and demo seeder are local/testing tools only. To create disposable fixtures, supply all three `HOSPITRAINITY_DEMO_*_PASSWORD` values as fresh process secrets of at least 24 characters and explicitly enable `HOSPITRAINITY_DEMO_SEED`; never store those values in a committed environment file. Hotel A and Hotel B exist only as isolated test fixtures, not as verified real institutions.

## Architecture

| Layer | Responsibility |
|---|---|
| `routes/web.php` | Guest, verification, learner, supervisor, admin, and superadmin route boundaries |
| `app/Http/Controllers` | Authentication, delivery, progress, supervisor reporting, and retained legacy managers |
| `app/Http/Middleware` and `app/Policies` | Role, verification, legacy-write retirement, security-header, and record authorization gates |
| `app/Services/Curriculum` | Canonical validation, contained import/repair, release approvals, production delivery guards, checksums, artifacts, standalone generation, and rollback |
| `app/Services/InstitutionContext.php`, `WorkContext.php`, and `LearningContext.php` | Revalidated active institution, role, and personal/institution learning scope |
| `app/Services/InstitutionInvitationService.php` and `InstitutionJoinCodeService.php` | Scoped transactional invitation and pending classroom-code enrollment |
| `app/Services/CanonicalCurriculumRepository.php` | Read projection used by the learner curriculum pages |
| `curriculum/hospitrainity/0.4.0-draft` | Active immutable draft delivery package; edit through a new reviewed draft/version, never by changing generated output or database projection |
| `resources/js` | CSP-compatible UI behavior, progress/media clients, and the covered exercise engine |
| `storage/app/private/curriculum` | Import reports and rollback artifacts; this is private operational data |

### Roles and capabilities

- **Learner**: uses personal self-study and any approved institution learning memberships. Only the selected learning context receives new progress.
- **Instructor**: sees institution-attributed learner progress and manages invitations, classroom codes, and membership requests only inside the selected institution.
- **Institution Admin**: has the Instructor scope and may grant or revoke Instructor access in that institution. It cannot create another Institution Admin or gain platform authority.
- **Content Author**: authors and reviews shared canonical curriculum without inheriting learner-management or platform-administration authority.
- **System Admin**: global platform authority. It may assign Institution Admin, use a clearly bannered and audited preview-as-role context, and perform the retained global administration functions.
- One account may hold multiple memberships/roles/capabilities, but exactly one work context is active. Every request revalidates that context; unknown or stale contexts fail closed.

## Storage, mail, queues, and scheduling

- `storage/app/private` is the default private disk. Never expose it through the web server.
- Uploaded learner-facing media uses `storage/app/public` and requires `public/storage` from `php artisan storage:link`.
- Verify the link and write/read/delete behavior with `php artisan hospitrainity:storage-health`.
- Failed post-commit media deletions are durable. `hospitrainity:media-cleanup` retries them hourly through Laravel's scheduler.
- Run a production queue worker for `QUEUE_CONNECTION=database` and a scheduler (`php artisan schedule:run` every minute, or the platform's equivalent). Supervise both processes and restart workers after deployment.
- Local email is written to the application log. Production must configure a real supported mail transport and a verified `MAIL_FROM_ADDRESS`; test verification and password-reset delivery before accepting users.

On Windows, `storage:link` may require Developer Mode or an elevated terminal. Do not work around a failed link by copying uploaded files into `public`; copied files drift from the configured disk and break cleanup guarantees.

## Curriculum operations and recovery

The canonical package is the declared source for Laravel delivery and the standalone projection. Do not hand-edit `standalone/Hospitrainity-Standalone.html` or canonical database rows.

```powershell
php artisan hospitrainity:curriculum dry-run
php artisan hospitrainity:curriculum import
php artisan hospitrainity:curriculum verify
php artisan hospitrainity:curriculum generate
```

An import validates the package and its references/checksums, reports the proposed diff, stores a pre-change rollback artifact, updates the projection transactionally, generates the standalone deterministically, and records the run. Re-running the same valid source is idempotent. B01 permits same-source repair but rejects a replacement package until its release evidence is complete; production also rejects initial draft import and draft delivery. Each approval distinguishes the named external reviewer from the authenticated Superadmin who records it, and release transitions are serialized so only one release can remain active. Production delivery also requires the activation actor, timestamp, and append-only transition event. Production rollback remains policy-disabled until B17 rehearsal; the underlying restore guard validates that activation evidence, the active non-draft release, checksums, and all seven complete approval records before any snapshot mutation.

Rollback requires the exact artifact path emitted by a prior import:

```powershell
php artisan hospitrainity:curriculum rollback --rollback="storage/app/private/curriculum/rollbacks/<recorded-file>.json"
php artisan hospitrainity:curriculum verify
```

Keep rollback artifacts and database/media backups outside the release directory according to the operator's retention policy. The recovery decision and its legacy-fallback limitation are recorded in [ADR-001](docs/decisions/ADR-001-curriculum-recovery.md).

## Quality gates

```powershell
php scripts/quality/verify-workflows.php
node scripts/quality/verify-traceability.mjs
node scripts/security/scan-tracked-secrets.mjs
composer validate --strict --no-check-publish
composer audit --locked --abandoned=fail
npm audit --audit-level=low
npm run lint:js
npm run test:js
npm run build
composer test
php artisan view:cache
php vendor/bin/pint --test
npm run test:e2e
```

`composer test` enables PHPUnit's all-issue failure mode and strict output detection. `npm run test:e2e` deletes and recreates only `storage/framework/testing/e2e`, migrates/seeds its own SQLite database with fresh process-only secrets, starts an isolated server on `127.0.0.1:8010`, and runs desktop Chromium, WebKit, and Firefox plus Pixel 7 and iPhone 15 emulations with one worker. It never points at `database/database.sqlite`.

Install the matching browser once on a developer machine:

```powershell
npx playwright install chromium firefox webkit
```

CI additionally uses `npx playwright install --with-deps chromium firefox webkit`. The workflow in `.github/workflows/tests.yml` blocks on governance/artifact safety, audits, lint, JavaScript tests, build, strict PHP tests, Blade compilation, Pint, and isolated E2E; it uploads only the synthetic Playwright test report. `.github/workflows/authority-release.yml` is a dormant, manual-only exact-hash check for an otherwise-offline Windows runner and contains no authority upload step.

## Production deployment containment

Use `.env.production.example` as a checklist, not as deployable credentials. Store the populated environment in the platform's secret store. The web server document root must be `public`, `APP_KEY` must be valid for `APP_CIPHER`, `APP_URL` must be HTTPS, `APP_DEBUG=false`, session cookies must be secure/HTTP-only/SameSite, CSP must be enforced, and HSTS must be enabled only after HTTPS is proven. The clean PHP release must contain the Vite manifest and its referenced assets but exclude `node_modules`, Composer development packages, and `public/hot`. Do not enable HSTS preload or `includeSubDomains` without validating every subdomain.

B01 is intentionally not deployable yet. The authoritative legacy identity migration and session finalization are complete, but the current `0.4.0-draft` still lacks production-grade human approval evidence, known production topology, working production mail, a production-safe policy for the three intentionally enabled demo accounts, and an active non-draft curriculum release. The production readiness checker must therefore remain non-zero. Do not work around these gates or import a replacement curriculum in production. The detailed future procedure and stop conditions are maintained in [Production deployment](docs/PRODUCTION_DEPLOYMENT.md).

## Project evidence

- Full audit and phased plan: `docs/HOSPITRAINITY_FULL_AUDIT_AND_UPDATE_ROADMAP.md`
- Implementation checkpoints: `docs/HOSPITRAINITY_IMPLEMENTATION_CHECKPOINTS.md`
- Engineering history: `CHANGELOG.md`

## Current enrollment decision

Personal self-study registration is available without institution selection. A learner joins an institution through either a target-email invitation or a reusable, issuer-timed classroom code followed by staff approval. Neither path grants staff authority, reveals an institution directory, or reattributes prior personal progress. Verified-domain auto-enrollment, institutional SSO, and fuzzy legacy-institution mapping remain disabled. The approved contracts are recorded in [NG-B02 decisions](docs/decisions/NG-B02-DECISIONS.md) and [NG-B05 decisions](docs/decisions/NG-B05-DECISIONS.md).

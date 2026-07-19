# Hospitrainity

Hospitrainity is a Laravel 12 application for hospitality-English learning. It delivers a checksum-locked, versioned curriculum to verified learners, records activity completion, gives institution-scoped supervisors a progress view, and exposes retained legacy content to superadministrators as read-only evidence while the canonical package is active.

## Implemented features

- Registration, login with optional remember-me, logout, password reset, email verification, and request throttling.
- Four fail-closed roles: `user`, `supervisor`, `admin`, and `superadmin`.
- English and Indonesian interface locales. Canonical source content remains English where no approved translation exists.
- Versioned canonical chapters, sections, activities, prompts, model answers, feedback, rubrics, provenance, and lifecycle metadata.
- Per-activity learner completion with retry-safe persistence and institution-scoped supervisor progress.
- A 14-type canonical exercise contract: 12 templates are enabled end to end, while `spelling_quiz` and `listening_task` remain visibly unavailable until an approved equivalent prerecorded-audio and accommodation policy exists.
- Responsive, keyboard-operable public navigation, learner account menu, and admin dialogs.
- Enforced security headers/CSP support, HTTPS production readiness checks, role policies, CSRF protection, canonical email storage, and bounded progress payloads.
- Deterministic curriculum dry-run, import, verification, standalone generation, reports, checksums, and rollback artifacts.

## Requirements

- PHP `8.2` or newer with `mbstring`, `pdo_sqlite` (or the selected production database driver), `bcmath`, `intl`, and `fileinfo`.
- Composer 2.
- Node.js 24 LTS and npm 11. The package also accepts Node 25/26 for local tooling, but CI uses the LTS line.
- A database supported by `config/database.php`. Local setup defaults to SQLite.

The browser E2E suite needs Playwright Chromium. Its install command is included below.

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

The current `UserSeeder` creates predictable, pre-verified demonstration accounts and is an explicit B01 release blocker. If demo data is needed, run `php artisan db:seed` only in an isolated disposable local/test database. Never seed a production or production-derived database.

On a POSIX shell, use `cp .env.example .env`, `touch database/database.sqlite`, `php`, `composer`, and `npm` in place of the explicit Windows commands.

Start all development services:

```powershell
& 'C:\php\php.exe' 'C:\composer\composer.phar' run dev:windows
```

`dev:windows` starts the Laravel server, database queue listener, and Vite without Laravel Pail because Pail requires the `pcntl` extension that is normally unavailable on Windows. On a compatible POSIX system, `composer run dev` also starts Pail.

### Verify a newly registered account locally

1. In an isolated disposable local database only, run `php artisan db:seed`; the development seeder establishes institutions and demonstration accounts declared in `database/seeders/UserSeeder.php`.
2. Visit `/register`, choose an institution shown by the form, and create a unique account. Registration never accepts a client-supplied role; new accounts are learners.
3. Local mail uses the `log` transport. Find the signed verification URL in `storage/logs/laravel.log`:

   ```powershell
   Select-String -Path storage\logs\laravel.log -Pattern 'email/verify'
   ```

4. Open that URL in the same browser session. The verified learner is sent to `/dashboard`.

Do not use the seeded accounts or the log mailer in production. The current registration form publicly derives its institution choices from distinct `users.instansi` values; that enumeration and self-enrollment fallback are explicit B01 blockers. The future invitation/domain/join-code policy is intentionally unresolved and must not be inferred.

## Architecture

| Layer | Responsibility |
|---|---|
| `routes/web.php` | Guest, verification, learner, supervisor, admin, and superadmin route boundaries |
| `app/Http/Controllers` | Authentication, delivery, progress, supervisor reporting, and retained legacy managers |
| `app/Http/Middleware` and `app/Policies` | Role, verification, legacy-write retirement, security-header, and record authorization gates |
| `app/Services/Curriculum` | Canonical package validation, deterministic import, checksums, artifacts, standalone generation, and rollback |
| `app/Services/CanonicalCurriculumRepository.php` | Read projection used by the learner curriculum pages |
| `curriculum/hospitrainity/0.4.0-draft` | Active immutable draft delivery package; edit through a new reviewed draft/version, never by changing generated output or database projection |
| `resources/js` | CSP-compatible UI behavior, progress/media clients, and the covered exercise engine |
| `storage/app/private/curriculum` | Import reports and rollback artifacts; this is private operational data |

### Roles

- `user`: must be authenticated and email-verified; reads published canonical content and records only their own completion.
- `supervisor`: sees paginated learner progress only for users whose institution exactly matches the supervisor's institution.
- `admin`: authors canonical drafts/exercises, sees global aggregate/de-identified progress, and reads retained legacy evidence; cannot publish/activate packages, administer identities/roles, or read raw learner responses.
- `superadmin`: additionally manages ordinary identity/role changes, reviews bounded administration audit metadata, and approves/publishes/activates canonical packages behind recent-password and confirmation controls. Retained legacy managers remain read-only while a canonical package is active.
- Unknown roles fail closed and do not inherit learner access.

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

An import validates the package and its references/checksums, reports the proposed diff, stores a pre-change rollback artifact, updates the projection transactionally, generates the standalone deterministically, and records the run. Re-running the same valid source is idempotent.

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

`composer test` enables PHPUnit's all-issue failure mode and strict output detection. `npm run test:e2e` deletes and recreates only `storage/framework/testing/e2e`, migrates/seeds its own SQLite database, starts an isolated server on `127.0.0.1:8010`, and runs Chromium with one worker. It never points at `database/database.sqlite`.

Install the matching browser once on a developer machine:

```powershell
npx playwright install chromium
```

CI additionally uses `npx playwright install --with-deps chromium`. The workflow in `.github/workflows/tests.yml` blocks on governance/artifact safety, audits, lint, JavaScript tests, build, strict PHP tests, Blade compilation, Pint, and isolated E2E; it uploads only the synthetic Playwright test report. `.github/workflows/authority-release.yml` is a dormant, manual-only exact-hash check for an otherwise-offline Windows runner and contains no authority upload step.

## Production deployment runbook

Use `.env.production.example` as a checklist, not as deployable credentials. Store the populated environment in the platform's secret store. The web server document root must be `public`, `APP_URL` must be HTTPS, `APP_DEBUG=false`, session cookies must be secure/HTTP-only/SameSite, CSP must be enforced, and HSTS must be enabled only after HTTPS is proven. Do not enable HSTS preload or `includeSubDomains` without validating every subdomain.

Before changing production, verify an external database backup and a copy/version of `storage/app`. Then, in a maintenance window or equivalent atomic release process:

```powershell
php artisan down --retry=60
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan hospitrainity:curriculum dry-run
php artisan hospitrainity:curriculum import
php artisan hospitrainity:curriculum verify
php artisan hospitrainity:curriculum generate
php artisan storage:link
php artisan optimize
php artisan hospitrainity:deployment-check
php artisan hospitrainity:storage-health
php artisan queue:restart
php artisan up
```

Build assets in CI and promote the tested artifact when possible; a production host then does not need Node. Run the curriculum import only when the release contains the intended source version—the dry-run output must be reviewed first. If any migration/import/health/readiness check fails, keep maintenance mode active, preserve logs and artifacts, and use the verified database/media backup or the recorded curriculum rollback before serving traffic.

After release, check `/up`, login, verification mail, role landing pages, one read-only curriculum page, worker health, scheduler history, logs, and external HTTPS/security headers. Laravel's built-in server is for development only.

## Project evidence

- Full audit and phased plan: `docs/HOSPITRAINITY_FULL_AUDIT_AND_UPDATE_ROADMAP.md`
- Implementation checkpoints: `docs/HOSPITRAINITY_IMPLEMENTATION_CHECKPOINTS.md`
- Engineering history: `CHANGELOG.md`

## Known product decision

Institution enrollment is not yet governed by an approved invitation, email-domain, or join-code policy. The existing exact-string selection rule remains covered and prevents arbitrary new institution names, but it is not presented as the final trust model. Changing it requires a product decision plus registration, supervisor-scope, normalization, abuse, and migration tests.

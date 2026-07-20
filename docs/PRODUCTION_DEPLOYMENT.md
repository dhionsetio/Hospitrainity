# Production deployment

This runbook is intentionally provider-neutral. Replace the placeholders in `.env.production.example` through the deployment platform's secret store; do not commit a populated `.env`.

## B01 production stop gate

The current repository state is a contained pre-production candidate, not an approved production release. Do not deploy it or bypass the non-zero readiness result until all of these conditions have recorded evidence:

- the authoritative database migration and reviewed exact legacy-institution mapping have been rehearsed on a restorable copy, with an approved disposition for existing demo identities and explicit permission to revoke every database session;
- the normalized-identity session-revocation state is recorded complete after the owner-approved finalization command is run in a maintenance window; ordinary migration does not satisfy this gate;
- the creator's real Superadmin display name, controlled mailbox, HQ ownership, and mail delivery have been confirmed before the one-time bootstrap command is run;
- the active curriculum is an immutable non-draft release whose content, ESP/hospitality, CEFR, accessibility, rights/links, retention, and final-owner gates each identify the human approver, qualification basis, source hash, decision time, and bounded evidence;
- the host, database, private storage, queue, scheduler, mail, TLS/proxy, secrets, backup/restore, monitoring, and incident owners/topology are known and the target-environment checks pass;
- demo seeding is disabled and no known demo identity or demo institution exists in production.

The migration deliberately creates only Hospitrainity HQ and Politeknik Negeri Malang (English name: State Polytechnic of Malang) as real institutions. Hotel A and Hotel B are disposable local/E2E fixtures only. Legacy values are mapped by exact reviewed rules; unknown or unmatched values remain unresolved and confer no membership. Do not fuzzy-match, silently merge, delete, disable, or archive authoritative records without a separately approved data plan.

## Release build

Build frontend assets in an isolated CI/build workspace. Copy only the resulting `public/build` tree into the clean PHP release; do not deploy the build workspace or `node_modules`:

```sh
npm ci
npm run build
```

Then install runtime PHP dependencies in the clean release directory. Keep the application out of service while the data steps are in progress:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
php artisan storage:link
php artisan hospitrainity:identity-migration-preflight
php artisan migrate --force
php artisan hospitrainity:identity-migration-preflight
```

The first preflight is a read-only inventory for the approved mapping/disposition plan. The second checks the normalized membership, unresolved-user, default-membership, required-institution, and finalization-state invariants and must exit zero. By default the JSON report contains SHA-256 legacy-label groups and no email addresses. `--include-legacy-values` reveals plaintext legacy labels and is permitted only for the access-controlled mapping review; retain that output as sensitive migration evidence. Neither mode writes data.

`php artisan migrate --force` creates the pending normalized-identity finalization state but does not delete any session. After a verified restorable backup, reviewed legacy mapping, an approved demo-account disposition, a successful post-migration preflight, and the owner's explicit permission for this exact deletion, revoke all database sessions:

```sh
php artisan hospitrainity:finalize-identity-migration --confirm=REVOKE-ALL-DATABASE-SESSIONS
```

The command requires `SESSION_DRIVER=database`, deletes every row from the configured database session table transactionally, records the revoked count, and is idempotent after success. All users must sign in again. If it fails, keep traffic stopped and investigate the protected logs; do not bypass the pending state or bootstrap first.

Complete the remaining environment setup, then build caches and run the readiness gate:

```sh
php artisan optimize
php artisan hospitrainity:deployment-check
```

The web-server document root must be the application's `public` directory. Construct the release artifact without `public/hot` rather than copying the local Vite development marker and removing it later. The release must also exclude `node_modules`; the readiness checker verifies both conditions and verifies that the Vite manifest's required CSS/JavaScript files exist. Keep `storage` and `bootstrap/cache` writable by the application account, and run Laravel's scheduler once per minute (or use a dedicated scheduler worker).

## HTTPS and proxy validation

Terminate TLS only at a trusted proxy or the web server. If a trusted proxy terminates TLS, configure Laravel's trusted-proxy settings narrowly so secure requests are recognized correctly. Then verify:

- redirects and generated URLs use `https://`;
- the session cookie has `Secure`, `HttpOnly`, and `SameSite=Lax` (or `Strict` where compatible);
- `Content-Security-Policy` is enforced, not report-only;
- `Strict-Transport-Security` appears only on HTTPS responses;
- `/up`, login, password reset, and one authenticated learner workflow return no debug output or development-asset URLs.

HSTS `includeSubDomains` and `preload` default to off because enabling either without auditing every subdomain can make other services unreachable. Enable them only after that separate review.

## Deployment gate

`php artisan hospitrainity:deployment-check` exits non-zero if production mode, debug, HTTPS, the application encryption key, cookie settings, CSP/HSTS, the Vite manifest or referenced assets, storage link, `public/hot`, Composer development packages, a deployed `node_modules`, demo identity configuration, normalized-identity session finalization, or curriculum-release state are unsafe. Run it after environment configuration and before shifting traffic. A non-zero result is a stop condition, not an instruction to weaken the checker.

Do not import a first or replacement curriculum in production while the release gates are incomplete. Same-source repair is permitted only where the existing production package already has a deliverable release record. Production rollback is policy-disabled until B17 rehearses the approved release workflow; any future enabled path must preserve the prior package and release evidence, and the snapshot guard must validate the active non-draft release, coherent checksums, and all seven complete approval records before the first database mutation.

After the explicit session finalization is recorded and only when the other B01 bootstrap prerequisites are satisfied, the initial Superadmin may be created once:

```sh
php artisan hospitrainity:bootstrap-superadmin "<confirmed mailbox>" "<confirmed display name>" --institution=hospitrainity-hq --confirm=BOOTSTRAP-INITIAL-SUPERADMIN
```

The command creates no usable default password, sends a normal time-limited reset link, remains email-unverified until the normal verification flow completes, and refuses after the first successful bootstrap. If notification delivery fails after account creation, repair mail and use the normal password-reset request; do not rerun bootstrap.

After deployment, restart long-lived queue workers with `php artisan queue:restart`. Test password-reset delivery using the configured production mail transport without logging reset URLs or credentials.

## Primary references

- [Laravel deployment](https://laravel.com/docs/12.x/deployment)
- [Laravel Vite CSP nonces](https://laravel.com/docs/12.x/vite#content-security-policy-csp-nonce)
- [Laravel authentication](https://laravel.com/docs/12.x/authentication)
- [Laravel rate limiting](https://laravel.com/docs/12.x/rate-limiting)
- [Laravel database transactions](https://laravel.com/docs/12.x/database#database-transactions)
- [PostgreSQL explicit locking](https://www.postgresql.org/docs/17/explicit-locking.html)
- [OWASP Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html)
- [OWASP Forgot Password Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html)
- [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html)
- [OWASP Multi-Tenant Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Multi_Tenant_Security_Cheat_Sheet.html)
- [OWASP HTTP security response headers](https://cheatsheetseries.owasp.org/cheatsheets/HTTP_Headers_Cheat_Sheet.html)
- [OWASP Content Security Policy](https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html)
- [OWASP HTTP Strict Transport Security](https://cheatsheetseries.owasp.org/cheatsheets/HTTP_Strict_Transport_Security_Cheat_Sheet.html)

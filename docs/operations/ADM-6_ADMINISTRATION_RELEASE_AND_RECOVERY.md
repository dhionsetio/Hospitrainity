# ADM-6 Administration Release and Recovery Runbook

- Date: 2026-07-19
- Scope: Hospitrainity administration release, audit review, and operational recovery
- Authority: accepted ADR-002 and the ADM-6 roadmap
- Production status: this repository does not identify the production database engine, hosting platform, backup product, mail provider, queue supervisor, or restore owner. Those values must be supplied and tested by the deployment owner before release.

## 1. Non-negotiable release conditions

Do not release until all of the following evidence has been recorded against one immutable application artifact or commit:

1. Full PHP, JavaScript, canonical pipeline, role/security, upload-abuse, publication/rollback, progress-privacy, accessibility-markup, and browser journeys pass.
2. `composer audit` and `npm audit` report no accepted release-blocking advisory.
3. The authority DOCX SHA-256, active canonical version, source-tree SHA-256, Laravel projection SHA-256, standalone SHA-256, and active database package ID have been recorded.
4. The deployment owner has named the production database engine and supplied an engine-native backup and restore command. A backup command without a successful isolated restore drill is not release evidence.
5. The database backup and the private curriculum storage backup share the same checkpoint time. Include `storage/app/private/curriculum`, the configured standalone output, and any environment-specific object-storage equivalents.
6. At least one verified superadmin account is known to be accessible. Never create, seed, or silently promote an account during deployment.
7. `APP_ENV=production`, `APP_DEBUG=false`, writable `storage`/`bootstrap/cache`, database-backed sessions/queues where configured, HTTPS, mail delivery, and worker supervision have been verified in the target environment.

The Laravel deployment sequence below follows the framework's official deployment and migration guidance: [Laravel 12 deployment](https://laravel.com/docs/12.x/deployment) and [Laravel 12 migrations](https://laravel.com/docs/12.x/migrations). The audit controls follow the [OWASP Logging Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html). These sources do not choose Hospitrainity's production database or backup provider.

## 2. Pre-deployment evidence

Run the release suites outside the production request path. Record their complete exit status rather than copying only green summary lines.

```powershell
& 'C:\php\php.exe' artisan hospitrainity:curriculum verify
& 'C:\php\php.exe' artisan test --display-all-issues --fail-on-all-issues --disallow-test-output
& 'C:\Program Files\nodejs\npm.cmd' run lint:js
& 'C:\Program Files\nodejs\npm.cmd' run test:js
& 'C:\Program Files\nodejs\npm.cmd' run test:e2e
& 'C:\Program Files\nodejs\npm.cmd' run build
& 'C:\php\php.exe' 'C:\composer\composer.phar' audit --format=json
& 'C:\Program Files\nodejs\npm.cmd' audit --json
& 'C:\php\php.exe' artisan route:list --except-vendor
& 'C:\php\php.exe' artisan migrate:status
```

Record the authority and active package fingerprints:

```powershell
Get-FileHash -LiteralPath 'C:\Users\dhion\Desktop\Documents\000 - Thesis Dhion Setio\Revisi 30 Juni 2026\Learning Materials - Fixed\Hospitrainity.docx' -Algorithm SHA256
& 'C:\php\php.exe' artisan hospitrainity:curriculum verify
```

The local `.env.example` defaults to SQLite, database sessions, database queues, and database cache. It is a development default, not proof of the production topology.

## 3. Backup and deployment checkpoint

1. Announce the maintenance window and stop authoring/publication work.
2. Put the current release into maintenance mode if the platform is not using an atomic blue/green or equivalent deployment:

   ```powershell
   & 'C:\php\php.exe' artisan down --retry=60
   ```

3. Run the deployment owner's previously tested engine-native database backup command. Do not substitute a file copy for a live client/server database backup.
4. Back up the configured private curriculum storage and standalone output at the same checkpoint.
5. Generate checksums for both backup artifacts and record their storage location, encryption/access controls, owner, and retention ticket.
6. Restore both artifacts into an isolated environment and verify the application can boot, the active canonical hashes match, and the audit/user/draft/progress counts match the checkpoint. If this restore drill fails, stop the release.
7. Deploy the reviewed code and compiled assets. In the target release directory:

   ```powershell
   & 'C:\php\php.exe' 'C:\composer\composer.phar' install --no-dev --prefer-dist --optimize-autoloader --no-interaction
   & 'C:\Program Files\nodejs\npm.cmd' ci
   & 'C:\Program Files\nodejs\npm.cmd' run build
   & 'C:\php\php.exe' artisan migrate --isolated --force
   & 'C:\php\php.exe' artisan optimize
   & 'C:\php\php.exe' artisan reload
   & 'C:\php\php.exe' artisan up
   ```

   If assets are built in CI, deploy that exact verified build instead of rebuilding on the production server. `migrate --isolated` requires a cache driver shared by all application nodes; if the target cache is not shared, the deployment platform must serialize migration execution itself.

8. Verify `/up`, login, email verification/password reset delivery, one route per role, the superadmin Audit screen after password confirmation, queue processing, and the active canonical hashes.
9. Release the authoring freeze only after the post-deployment checks pass.

ADM-6 itself adds no database migration. Do not infer that later deployments will also be migration-free.

## 4. Failed DOCX import recovery

1. Do not accept a failed or processing import and do not edit its database status manually.
2. Confirm the import record is `failed`, its quarantined source was deleted, and the audit contains `docx_import_failed` with only the import identifier, hashes, error code, and deletion result.
3. Verify the active canonical package ID and hashes are unchanged.
4. Correct the source or infrastructure problem outside the retained failed record. Queue a new import into the intended draft revision; never reuse or overwrite the failed import row.
5. If a worker stopped while an import remains `processing`, preserve the row and private work directory for investigation. Do not retry blindly until the deployment owner proves whether the job is still running and whether the compiled output is complete.

## 5. Failed publication and canonical rollback

1. A publication failure must leave the prior active package and standalone output authoritative. Record and compare the pre-publication fingerprints.
2. Never activate a package with direct SQL or copy generated files by hand. Correct the draft/compiler/importer cause and publish a new reviewed revision.
3. If a newly published package must be withdrawn, use the superadmin draft's password-confirmed **Rollback** action. It accepts only the currently active recorded publication and its verified rollback artifact.
4. After rollback, run `artisan hospitrainity:curriculum verify`, confirm the restored active version and hashes, review `publication_rolled_back` in Audit, and repeat learner smoke checks.
5. If the recorded rollback artifact is absent or fails verification, stop. Restore the coordinated database/private-storage checkpoint in an isolated environment first; do not improvise a partial production restore.

## 6. Lost superadmin access

1. If the superadmin account still exists and its verified mailbox is controlled, use the normal password-reset flow. Confirm mail delivery and then review the account/session incident.
2. If another verified superadmin remains accessible, that account may manage the affected user through the password-confirmed Users workflow. Promotion still requires typed confirmation, a reason, throttling, audit, target-session revocation, and the last-superadmin safeguard.
3. If no superadmin and no verified superadmin mailbox is accessible, Hospitrainity intentionally has no web, seeder, or automatic self-promotion bypass. Activate the organization's approved break-glass process with the deployment owner/database administrator, independent identity verification, an incident/change ticket, a coordinated backup, and a second reviewer. The exact recovery command must be written and tested for the actual production database before release; this repository cannot safely invent it.
4. After break-glass recovery, revoke affected sessions, rotate credentials, reconcile the emergency action into retained audit evidence, verify the number of superadmins, and review why normal password recovery failed.

## 7. Session revocation

- Role changes and superadmin promotions already rotate the target's remember token and delete that target's database-session rows transactionally. The audit stores only the number of sessions revoked, never session identifiers.
- For compromise without a role change, reset the credential and use the target session driver's approved operator procedure to revoke the account's sessions. The default repository configuration uses the `sessions` database table, but production must confirm its actual driver/table before action.
- Verify the affected browser is forced to authenticate again. Do not paste session IDs, cookies, reset tokens, or credentials into Audit, tickets, or chat.

## 8. Audit review and incident use

1. Sign in as a verified superadmin, recently confirm the password, and open `/superadmin/audit`.
2. Filter by category, event, actor, and date. Page size is fixed at 50.
3. Identity/role and curriculum lifecycle records are append-only at the application model layer. Audit-screen access is also written to the application security log with actor ID, filter names, page, and visible-row count; filter values are excluded.
4. Audit metadata is recursively bounded and excludes passwords, tokens, session IDs, raw file contents/paths, learner responses/answers, and audio. IP address and user agent exist only on role-change records and must be handled as restricted personal/security data.
5. Database administrators can still alter database rows outside Eloquent. Production database privileges, backups, centralized log shipping/tamper detection, monitoring, and retention are therefore required operational controls and are not claimed by the application-level guard.
6. No retention duration is approved in the repository. Do not purge audit evidence until the product owner has approved a legal/privacy/security retention and disposal schedule.

## 9. Draft conflict recovery

1. HTTP 409 means the submitted draft/entity/block/import revision is stale. Do not bypass or overwrite the expected revision.
2. Reload the workspace, review its latest event timeline and diff, and compare the current canonical identifiers with the editor's intended change.
3. Reapply the intended change against the latest revision. If the two edits conflict semantically, a human content reviewer chooses the outcome; the system must not merge or invent learning content.
4. Revalidate and re-review the resulting draft. Previously approved/published versions and learner attempts remain unchanged.

## 10. Code/deployment rollback

1. Re-enter maintenance mode or switch traffic back to the prior immutable application artifact.
2. Restore the prior dependency lockfiles/assets with that artifact, then run `artisan optimize:clear`, `artisan optimize`, and `artisan reload`.
3. Do not run an unqualified `migrate:rollback`; one batch can contain multiple migrations. If a schema rollback is actually required, use the reviewed migration plan or restore the coordinated pre-deployment database/private-storage checkpoint.
4. Bring the application up and repeat the health, role, audit, active-hash, canonical delivery, and queue checks.
5. Preserve all audit and incident evidence. Rolling back code is not authorization to delete role changes, progress, drafts, imports, attempts, or publication history.

## 11. Evidence to attach to the release record

- Application artifact/commit ID and dependency lockfile hashes.
- Full automated-suite summaries and browser journey evidence.
- Authority DOCX and active canonical fingerprints.
- Database/private-storage backup IDs, checksums, and successful isolated restore evidence.
- Migration status before/after deployment.
- Health, mail, queue, session, role, audit, and canonical learner smoke results.
- Named release operator, backup owner, rollback decision owner, superadmin recovery owner, and audit-retention owner.

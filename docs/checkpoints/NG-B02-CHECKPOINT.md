# NG-B02 checkpoint — privacy, public trust, export, retention, and account lifecycle

- Status: technical implementation complete and installed locally on 2026-07-20; production legal/controller/topology and real VAPID gates remain blocked.
- Branch: `codex/ng-b02-privacy-lifecycle`
- Parent checkpoint: `cf35ff6` (`feat: establish tenant identity and enrollment foundation`)
- Authority sources: unchanged thesis and learning-material hashes recorded by B00.

## Owner decisions applied

The owner approved the recommended lightweight, owner-operated direction; no separate Privacy Officer; reviewed request rather than immediate deletion; JSON plus accessible HTML and sanitized CSV export; pseudonymization of retained evidence; a 30-day maximum backup-expiry target; Bahasa Indonesia authoritative with matching English version metadata; public privacy/terms/accessibility/acceptable-use/support pages; and optional Web Push. The confirmed prototype owner/contact are Dhion Setio and `dhionsetio@gmail.com`.

No statutory DPO, legal reviewer, production controller relationship, lawful basis, hosting country, processor, transfer, production retention, or compliance conclusion was inferred. The public copy and deployment checker make those absences explicit.

## Implemented

- Versioned bilingual public privacy, terms, accessibility, acceptable-use, and support pages with a persistent trust footer.
- Registration links those documents before collection and records privacy/terms acknowledgements separately from the learning-scope acknowledgement, including locale, version, effective content hash, source, and time.
- Authenticated opaque-ID requests support access/export, correction, restriction, objection, deletion, consent withdrawal, and appeal, with duplicate prevention, deadlines, encrypted bounded notes, immutable encrypted events, accurate user status, cancellation, and a recent-password System Admin queue.
- Access export and deletion require recent password confirmation. Exports run as encrypted queue jobs, use an allowlisted field inventory, produce JSON, a semantic accessible HTML summary, and formula-neutralized CSV inside a ZIP, encrypt the artifact at rest, use a 24-hour expiry and a ten-minute signed owner-only download, verify SHA-256 before delivery, and never include passwords/tokens/other-user data.
- Account erasure is an idempotent five-step job: revoke access, stop push, delete personal learning, close memberships/roles/codes, and pseudonymize identity. Institution-attributed evidence remains attached only to the pseudonymous tombstone. Self-approval and deletion of the final System Admin fail closed.
- Retention has dry-run and explicit execution modes; the scheduler executes approved expiry daily. Expired export files are removed, old encrypted request notes are minimized while bounded events remain, and revoked browser subscriptions are removed after 30 days.
- Standards-based Web Push uses `minishlink/web-push` 10.1.0, encrypted subscription secrets, VAPID, an isolated service worker, opt-in/opt-out/test controls, encrypted queued delivery, bounded same-origin notifications, and automatic revocation on 404/410. The local UI remains truthfully unavailable because real VAPID secrets are not configured.
- Production readiness now requires the B02 schema, asynchronous queue, explicit production controller/contact, and working VAPID configuration.

The detailed dependency/retention/restore contract is `docs/PRIVACY_DATA_LIFECYCLE.md`.

## Authoritative database migration

Before migration, with no app/Vite listener active, `database/database.sqlite` was copied to:

- `storage/app/backup-snapshots/b02-preprivacy-20260720-134756.sqlite`
- SHA-256: `bcaa1f2fd34fe0b92f4afb54dd9b1259f12897a3b81cdcdc5d9ab719337fae91`

Migration `2026_07_20_000014_create_privacy_lifecycle_tables.php` ran as batch 13. Postflight: SQLite integrity `ok`; zero foreign-key violations; 3 users, 2 institutions, and 1 active membership remain; all 3 demo identities remain enabled; no request/export/subscription row existed; the retention dry-run found zero due artifacts. No authoritative user, session, learning, institution, membership, file, or request data was deleted. Isolated E2E preparation recreated only `storage/framework/testing/e2e` under its existing safety guard.

## Verification

- Focused B02: 10 tests and 85 assertions passed with migration rollback/reapply, public versioning, registration acknowledgements, duplicate/concurrent-safe intake, recent-password step-up, encrypted JSON/HTML/CSV export/hash/signed owner access, idempotent pseudonymization, self-approval denial, push ownership/encryption, and retention preview/execute coverage.
- Cumulative Laravel: 309 tests passed, 4,716 assertions, zero failures in 118.31 seconds under display/fail/disallow-output issue gates.
- JavaScript: ESLint passed with zero warnings; 50 Node tests passed.
- Production Vite build passed using Vite 6.4.3; the new push module is included in the compiled application entry.
- Targeted Playwright: 8/8 passed across patched desktop Chromium, desktop WebKit, Pixel 7/mobile Chromium, and iPhone 15/mobile WebKit for registration trust linkage plus the recent-password request submission/status/cancellation flow.
- Governance: 43 findings mapped; 1,569 tracked files scanned; 2 reviewed synthetic allowlist entries; zero unallowlisted secret/artifact findings. Five newly tracked literal-password test doubles were removed rather than allowlisted.
- `git diff --check` and Pint pass after formatting.

## Security, privacy, and accessibility boundary

Private request pages and exports use `no-store` and `no-referrer`. Request notes, event metadata, export paths, and push subscription material are encrypted; request IDs are opaque; authorization fails closed; rate limits cover intake, download, staff reads/actions, subscription changes, and test delivery. No raw token, VAPID private key, password, or export payload is logged or committed.

This checkpoint does not claim Indonesian/EU legal compliance, WCAG conformance, branded Safari/Chrome/Edge/Firefox/Opera coverage, physical Windows/macOS/Android/iOS coverage, screen-reader coverage, or production operation. WebKit/Chromium emulation is regression evidence only.

## Remaining release blockers

1. Qualified review must determine the production controller/processor relationship, lawful/policy bases, deadline and appeal obligations, institution retention, legal holds, backup restoration/expiry, processors/transfers, and final public wording.
2. Real production topology, mail, queue workers/scheduler, storage/backups, monitoring, and incident ownership remain unknown.
3. VAPID secrets are intentionally absent. Generate and protect one long-lived deployment pair before enabling push; never commit the private key.
4. Demo identities and the draft curriculum release continue to block production.
5. B03 must supply the security-log retention implementation, MFA/recovery/scanner/observability/restore assurance, and independent security evidence.

## Rollback

Migration rollback was proven in an isolated database without changing users. Rejecting B02 before real requests exist may use the tested schema rollback after a verified backup. Once a real deletion/export/request exists, do not restore data through ordinary rollback: disable new intake visibly, preserve request/step evidence, and forward-fix idempotently. Restore testing must reapply tombstone evidence before traffic resumes.

## Exact next batch

B03 — authentication, ASVS security baseline, upload scanning, observability, backup/restore, and deployment safeguards.

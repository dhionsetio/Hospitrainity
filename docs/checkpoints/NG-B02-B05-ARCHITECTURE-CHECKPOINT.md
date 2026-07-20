# NG-B02/B05 enrollment and tenant-role architecture checkpoint

- Captured: 2026-07-20 11:02 Asia/Jakarta
- Accountable owner: Dhion Setio
- Status: approved architecture implemented expand-first in code and the authoritative local SQLite database
- Production status: blocked by the existing documented release, topology, mail, demo-identity, privacy/legal, independent-security, and human-validation gates

## Owner decisions applied

The owner approved both recommended architectures and explicitly required all three demo accounts to remain enabled:

1. A person creates one personal self-study account. A reusable classroom code, timed by its authorized issuer from one second through 30 days, creates a pending institution request; it never grants a role directly. On approval, only a new institution Learner scope is created. Earlier personal progress remains private and is not copied or reattributed.
2. An institution is a tenant, not a global role. System Admin is platform-wide; Institution Admin and Instructor are institution-scoped; Learner is personal plus approved institution memberships; Content Author is a separate capability. One explicit work context is active at a time, and every request revalidates it.

No authoritative file, user, institution, membership, progress row, attempt, completion, session, invitation, code, or request was deleted in this execution. The isolated E2E preparation command performed its documented recreation of only `storage/framework/testing/e2e`; it never targeted the authoritative database. The three known demo accounts remain present and enabled. Hotel A and Hotel B remain disposable seeder/E2E fixtures rather than authoritative real institutions, so the two unmatched Hotel-labelled demo users were not given fabricated production memberships.

## Implemented behavior

- Public registration creates only an unverified personal Learner account, reveals no institution directory, records the scope acknowledgment version, and ignores submitted role or institution fields.
- Instructors and Institution Admins can issue HMAC-hashed 16-character classroom codes with a 1-second-to-30-day expiry (one-hour UI default), configurable use cap, revocation, throttling, and bounded audit evidence. The full code is displayed once and is not recoverable from storage.
- Code redemption uses a uniform unavailable result and a transaction/lock order that revalidates the institution, code, user, expiry, use cap, existing membership, and pending request. It creates only a pending request.
- Approval creates or restores only an active Learner assignment when safe. Rejection creates no membership. Prior restricted memberships require a separately reviewed restoration.
- Personal and institution activity use different server-derived scope keys and membership foreign keys across canonical progress, attempts, and retained legacy completion rows. Institution staff queries require the exact active membership identifier; legacy unattributed progress returns zero instead of leaking personal history.
- Work-context switching supports Learner, Instructor, Institution Admin, Content Author, and System Admin without combining their authority. The switcher is hidden for learner-only accounts, and direct single-role visits return to the current dashboard. Personal/institution learning selection remains separate. System Admin preview-as-role is bannered and audited and creates no tenant assignment.
- Content Author routes no longer expose invitation or classroom-management actions. Instructor/Institution Admin contexts own tenant enrollment. Institution Admin can manage Instructor access only inside its exact tenant; only System Admin can manage Institution Admin. Self-change, cross-tenant change, inactive membership, stale state, and repeated-state operations fail closed.
- Staff-role changes require a recent password, rotate the target remember token, revoke the target database sessions, and append an audit event. The protected GET page provides a safe post-confirmation destination rather than attempting to replay an unsafe PATCH request.
- The three existing legacy demo identities remain enabled. The guarded disposable seeder now produces matching normalized assignments in isolated local/testing databases.

## Authoritative database migration

Traffic check found no listener on local port 8000. A final source-identical backup was created before migration:

- Backup: `storage/app/backup-snapshots/b02-b05-before-tenant-role-migration-final-20260720-1050.sqlite`
- Size: 4,329,472 bytes
- SHA-256: `ff51f0427cd815eb738e6ba554657650f9559627ce86a99f284f6eff66549311`
- Backup validation: `PRAGMA integrity_check=ok`; zero foreign-key violations

Migration `2026_07_20_000013_establish_tenant_roles_and_join_codes` then completed in batch 12. Before/after invariants:

| Invariant | Before | After |
|---|---:|---:|
| Migrations | 25 | 26 |
| Users / enabled users | 3 / 3 | 3 / 3 |
| Known demo users / enabled | 3 / 3 | 3 / 3 |
| Sessions | 1 | 1 |
| Institutions / memberships | 2 / 1 | 2 / 1 |
| Completions | 1 | 1 |
| Canonical attempts / progress | 4 / 4 | 4 / 4 |
| Platform / institution / capability assignments | n/a | 1 / 1 / 0 |
| Join codes / join requests | n/a | 0 / 0 |
| Non-personal historical progress rows | n/a | 0 |

After migration: `PRAGMA integrity_check=ok`, zero foreign-key violations, 4,329,472 bytes, last write `2026-07-20T10:51:12.4431356+07:00`, SHA-256 `b268f9e8c190c21aebeb8258c2126c4d6b93c2d2710c81611b6769a664fc2bb7`.

At final handoff recheck, the one deliberately retained database session had advanced its `last_activity` timestamp to `2026-07-20T11:11:47+07:00`; no user/business count changed. The resulting current database SHA-256 is `26dfa31f52156afbffbc5c5a334750b73e4de613a00c37ffcc48b4fc89be2c4f`. Integrity remains `ok`, foreign-key violations remain zero, and no PHP/E2E listener is active. A live database hash is expected to change when retained session activity changes; the pre-migration backup hash remains the immutable recovery proof.

## Verification

- Authority inputs unchanged: thesis SHA-256 `c38ac625a2f7a34bb004e6b68183d748bc6c1d3c1c9896c3f791909072496d34`; learning materials SHA-256 `7f8a2c62db6884498f7a1c2de56e79e39609262944a685fe7eb97f702444cbb4`; roadmap SHA-256 `465099eb07881dc5f32ee6b3f06fc7d0e080971313a1eab2b290316bc7e5c5a9`.
- Laravel: 293 tests, 4,533 assertions, zero failures in 125.39 seconds. This final run includes canonical-email duplicate registration coverage.
- Focused role/enrollment migration tests include rollback/reapply plus SQLite foreign-key checking, cross-tenant and self-change denial, recent-password return safety, session revocation, role separation, pending/rejected enrollment, and personal/institution progress separation.
- Node: 50 tests, zero failures. ESLint: zero warnings. Pint: passed. Locale JSON and EN/ID key parity: passed.
- Vite production build: 11 modules transformed successfully. Governance traceability and tracked-secret scan passed with zero unallowlisted findings.
- Fresh Composer and npm advisory checks reported zero security advisories, zero abandoned Composer packages, and zero npm vulnerabilities across 246 reported dependencies.
- Browser automation: all 36 journeys passed in patched desktop Chromium, desktop WebKit, Pixel 7 Chromium emulation, and iPhone 15 WebKit emulation (nine journeys per project). The registration and account-menu journeys were updated to the approved behavior.
- Firefox: the separate nine-journey run stalled at the first browser launch and hit the 120-second host timeout before an application page or assertion ran. No listener or Firefox/Playwright process remained afterward. This is not Firefox application evidence.
- Opera: not directly tested. Chromium results cannot be represented as an Opera pass.
- Local deployment checker: 23 checks total; the new tenant-role/learning-scope schema passes. Twelve checks pass and eleven expected local/production gates fail, so the application correctly remains not production-ready.

## Evidence basis

- [Laravel 12 authorization](https://laravel.com/docs/12.x/authorization): policies and request-level authorization.
- [Laravel 12 database transactions](https://laravel.com/docs/12.x/database#database-transactions): transactional writes and retry boundary.
- [OWASP Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html): least privilege, deny by default, and authorization on every request.
- [OWASP Multi-Tenant Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Multi_Tenant_Security_Cheat_Sheet.html): server-derived tenant context, tenant-scoped queries, and cross-tenant isolation.

## Deliberately open work

- This is expand-first. The legacy `users.role` and `users.instansi` evidence remain until a later reviewed contract migration; no contract step is authorized here.
- B02 rights requests, retention matrix, public privacy/terms/support pages, optional push, qualified legal interpretation, and provider/topology decisions remain open.
- B05 onboarding, search, help/glossary, deterministic next-action rules, and the moderated first-use study remain open.
- B06 still owns course/class/assignment structure and any broader institution domain model. The implemented membership scope must be reused rather than bypassed.
- Real Firefox, Opera, Edge, Chrome, Safari, Windows/macOS, Android/iOS physical-device, assistive-technology, and human usability evidence remains required in the applicable later checkpoints.

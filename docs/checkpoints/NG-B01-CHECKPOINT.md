# NG-B01 checkpoint — identity and release containment

Status: B01 implementation and authorized authoritative-data migration verified locally on 2026-07-20; named production release evidence, real creator bootstrap, demo-account disposition, B06 progress provenance, and production release remain open.

## Outcome

Hospitrainity now has invitation-only onboarding, normalized institution memberships, one active institution per session, institution-scoped Admin/Supervisor invitation authority, a fail-closed demo seeder, a one-time Superadmin bootstrap, and a seven-gate curriculum-release state machine. Production refuses demo identities, draft delivery, and unapproved replacement imports. The existing `0.4.0-draft` source was not changed or renamed.

The selected workflow should make the website easier to navigate and safer to operate. Anonymous users no longer choose from or enumerate institutions. An invitation fixes the institution and intended email. A learner with multiple memberships selects one current context rather than seeing mixed data. Admins and Supervisors get an invitation screen inside their own institution scope; global institution authority stays with Superadmin. Hotel A and Hotel B remain useful as isolated test fixtures without appearing as verified real institutions.

Decision record: `docs/decisions/NG-B01-DECISIONS.md`. Machine evidence: `docs/evidence/NG-B01-EVIDENCE.json`.

## Implemented containment

- Database seeding exits before reads/writes unless an explicit local/testing flag and three fresh process-only secrets are present. Production is rejected unconditionally. All fixture writes are one transaction, and the seeder refuses to overwrite an existing non-demo identity or reuse a non-fixture institution.
- The one-time `hospitrainity:bootstrap-superadmin` command uses a locked singleton, accepts no default password, creates an unverified account with unusable random credential material, sends the normal reset flow, audits bounded evidence, and refuses every rerun. It also refuses while normalized-identity session revocation is pending.
- The expand-first identity migration creates bootstrap locks, institutions, memberships, target-email-bound invitations, identity audits, and explicit legacy mapping/finalization state. It creates only Hospitrainity HQ and Politeknik Negeri Malang/State Polytechnic of Malang as active real records; both begin unverified, and assigning the creator as HQ owner during bootstrap does not claim independent institution verification.
- Legacy institution mapping is exact-only. Hotel or unknown values remain unresolved and confer no normalized membership. Existing legacy strings are preserved as evidence.
- Invitation tokens are high entropy, hash-at-rest, expiring, revocable, single-use, rate-limited, and redeemed transactionally. Public outcomes do not enumerate institutions or target accounts; submitted institution UUIDs are resolved only inside the actor's authorized collection, and forbidden real invitation UUIDs return the same not-found result as unknown UUIDs. Layered limiter keys use keyed HMAC identity fingerprints, malformed array-shaped email input reaches ordinary validation, and a concurrent canonical-email collision rolls back to the bounded unavailable outcome. Encrypted target-address integrity is checked against the HMAC index; corrupted ciphertext fails closed. An invitation can add only learner membership, and an existing Admin, Supervisor, or Superadmin cannot redeem it.
- Admin and Supervisor issue/revoke permissions are reloaded under transaction locks and require a current active membership in the selected institution; stale in-memory authority fails closed. Superadmin may select any active institution. Issue, revoke, and redeem use an institution-first lock order, superseded pending invitations are individually revoked/audited, and synchronous delivery failure revokes the newly created invitation with bounded audit evidence.
- Supervisor progress authorization and the learner header use normalized active membership scope. This prevents the selected institution header from exposing another legacy institution, but the underlying historic progress rows are not institution-attributed; the portable-versus-attributed data decision remains an explicit B06 owner gate.
- Redemption, membership creation, token consumption, audit, and that learner's old database-session revocation are atomic; a deliberately broken session store proves rollback. Only the new current session is regenerated after commit.
- Ordinary migration retains every existing database session and creates a pending finalization state. The separate `hospitrainity:finalize-identity-migration --confirm=REVOKE-ALL-DATABASE-SESSIONS` command requires an exact confirmation and database session driver, deletes all sessions transactionally, records the count, and is idempotent. Production readiness and Superadmin bootstrap reject a pending state.
- The read-only `hospitrainity:identity-migration-preflight` command inventories mapping/demo/session gates before migration and verifies normalized invariants afterward. Its default JSON hashes legacy labels and emits no email addresses; plaintext labels require the explicit `--include-legacy-values` operator flag. Tests prove data, sessions, and schema remain unchanged, and post-migration corruption returns non-zero.
- Release evidence has seven immutable gates: content, ESP/hospitality, CEFR, accessibility, rights/links, retention, and final owner. No gate is treated as approved without a named external human and evidence; the authenticated Superadmin is recorded separately as the recorder.
- Curriculum lifecycle transitions share a serialized singleton lock, activation retires the previous active release, foreign-key restrictions and model guards preserve referenced evidence, and package checksums are verified at import and delivery. Production delivery also requires a matching append-only activation transition event with the recorded actor, timestamp, and reason; the rollback snapshot guard validates that evidence before mutation.
- Direct legacy draft publication is contained. Non-production delivery is visibly marked preview-only; production draft import/delivery and replacement activation fail closed. Import rollback artifacts preserve release/approval/event evidence. Production rollback stays policy-disabled until B17, and snapshot eligibility plus complete approval/checksum coherence are checked before any restore mutation.
- The Playwright matrix targets patched Chromium, Firefox, WebKit, Pixel 7 emulation, and iPhone 15 emulation with enforced CSP and isolated generated credentials. The four Chromium/WebKit profiles pass; the current host's pre-navigation Firefox launch limitation is recorded separately rather than claimed as application coverage. CI installs all three engines.
- Production readiness validates `APP_KEY` against `APP_CIPHER`, requires every manifest-referenced CSS/JavaScript asset to exist inside the build directory, checks all current Composer development dependencies, and rejects deployed `node_modules` or `public/hot`.

## Data and deletion boundary

The owner-authorized B01 execution applied the three reviewed B01 migrations to `database/database.sqlite`, finalized normalized identity, and revoked the three prior anonymous database sessions. Before those changes, verified database and private-storage snapshots were created; their paths and hashes are recorded below. The three demo accounts remain enabled because no exact account was authorized for disabling. No user row, learning progress, attempt, response, curriculum source, or private upload was deleted.

The installed reversible disabled-account state is fail-closed across login, password reset, existing sessions, and invitation issue/redemption. It requires an active System Admin, rejects self-disable, records a bounded reason code, and can be reversed. No authoritative account is disabled. The active curriculum source remains `0.4.0-draft` with tree SHA-256 `e864b4fcaa9d182b8379cf7d179279114baa7fef46624bcb261be41b8072dc9a`.

The pre-migration read-only inventory found 3 users, 1 exact-mapping candidate, 2 unmatched/empty candidates, 3 known demo identities, 3 sessions, 0 authenticated sessions, and no normalized schema. Postflight found the normalized schema, one HQ membership, two deliberately unresolved legacy users, and zero sessions. Both reports omitted email addresses and plaintext legacy labels by default.

## Verification

- Authority files rehashed to the accepted B00 values: thesis `c38ac625a2f7a34bb004e6b68183d748bc6c1d3c1c9896c3f791909072496d34`; learning materials `7f8a2c62db6884498f7a1c2de56e79e39609262944a685fe7eb97f702444cbb4`.
- Laravel: 281 tests passed, 4,388 assertions, zero failures in 108.80 seconds. The retained-session migration/rollback, disabled-account up/down constraint preservation, read-only preflight, explicit finalization/fault rollback/idempotency, atomic and collision-safe seeding, bootstrap notification-failure recovery, invitation replay/scope/UUID-oracle/concurrency/delivery/canonical-email-race/ciphertext integrity/atomic rollback, authentication limiter privacy, release serialization/fresh-authority/activation-event/evidence retention/pre-mutation rollback/checksum guard, production containment, authorization, and regression suites are included.
- Node: 50 tests passed in 4.408 seconds; ESLint passed with zero warnings. Vite 6.4.3 built 11 modules in 5.19 seconds.
- Pint, Blade compilation, `git diff --check`, Composer validation, workflow policy, and 43-finding traceability passed.
- Composer audit: zero advisories and zero abandoned packages. npm audit: zero vulnerabilities across 246 reported dependencies.
- Independent source compiler: 469 files were byte-identical, deterministic tree `9d1511a24c84059a7c561603bb94b6c2c52a8076edfc2ba71def63ba2f2259ac`, and all six fail-closed probes passed.
- Canonical isolated database verification matched source, Laravel projection, and standalone hashes and the B00 counts. Brand guard scanned 784 files with zero active violations.
- Playwright: 36/36 journeys passed in 136.2 seconds—nine each in desktop Chromium, desktop WebKit, Pixel 7 emulation, and iPhone 15 emulation. The run used fresh distinct secrets, an isolated fully migrated SQLite database, and enforced CSP.
- Patched Playwright Firefox `firefox-1532` was separately reproduced failing during browser launch before any application navigation: `RenderCompositorSWGL failed mapping default framebuffer`, followed by the 180-second launch timeout. Mozilla tracks related headless graphics-startup failures in bugs 1693011 and 1931611. No Firefox application assertion is claimed.
- The 22-check readiness checker against the current local development environment correctly returned not ready: 11 checks passed and 11 failed (`environment`, `debug`, `app_url`, `secure_session_cookie`, `csp_enforced`, `hsts`, `dev_dependencies`, `node_dependencies_absent`, `production_mail_transport`, `demo_identities_absent`, and `active_curriculum_release`). Identity session finalization and the disabled-account schema pass. This is containment evidence, not a production rehearsal.
- No task listener remained on local ports 8000, 8010, 8011, 5173, or 4173 after testing.

The first full Laravel run exposed one legacy characterization test that needed an explicit testing-only replacement-package switch. The guard also requires `APP_ENV=testing`, the affected suite then passed 8 tests/524 assertions, and the entire Laravel suite passed from scratch. Canonical/brand commands against the unmigrated main database correctly refused because the new release table does not exist; the same commands passed against the migrated isolated database. Failed prerequisite runs are not counted as passing evidence.

## Evidence boundaries

- Patched Chromium is not branded Chrome, Edge, or Opera. Patched WebKit is not Safari. Pixel/iPhone emulation is not Android/iOS hardware. Branded browsers, macOS, and real mobile devices remain release evidence.
- Automated markup/keyboard journeys do not constitute WCAG 2.2 AA conformance or independent accessibility review.
- Automated security tests and dependency scans are not an independent penetration test or production security assessment.
- Politeknik Negeri Malang is created as a named institution record, but no owner or email domain was inferred or marked verified.
- Progress ownership remains undecided for learners with more than one active institution. Current rows are personal/account-level rather than institution-attributed, so production must not infer an institutional learning boundary until B06 records and migrates the selected policy.
- No production host, database, mail, storage, queue, backup/restore, monitoring, or incident-response topology has been selected or tested.
- No academic, ESP/hospitality, CEFR, accessibility, rights/links, retention, or final-owner approval has been recorded; publication therefore remains blocked.
- The secret scanner passes with zero unallowlisted findings. The two owner-authorized obsolete fingerprint exemptions were removed; the two remaining reviewed matches are labeled synthetic test fixtures.

## Open findings and stale work

- NG-P0-001 is `in_progress`: B01 seeding/bootstrap containment exists; B03 recovery/security work and B17 production evidence remain.
- NG-P0-002 is `in_progress`: invitation and normalized-scope containment exists and the authoritative migration is complete; B06 enrollment, domain/class scope, and progress provenance remain.
- NG-P0-003 is `human_review`: technical containment exists, but a coherent non-draft release cannot exist until all named human gates close and B17 rehearses activation/rollback.
- No finished B01 item remains in the live plan. Historical B00 “next action” text remains provenance rather than a stale to-do.

## Rollback

The identity, release, and disabled-account migrations rolled back successfully in disposable tests. The authoritative migration was protected by verified database/private-storage backups. If B01 code must be rejected, use a reviewed forward correction or a tested restore plan in a maintenance window; do not rewrite history, re-enable production demo seeding/public self-registration, delete evidence, or silently restore draft production delivery.

## 2026-07-20 normalized-dashboard follow-up

The administration dashboard no longer derives its institution count from distinct legacy `users.instansi` labels. It now counts the normalized `institutions` records that B01 made authoritative. A regression test creates four arbitrary legacy labels and proves that they cannot change the normalized institution total. This is a read-only projection correction: no migration, database row, session, curriculum artifact, role, invitation, or visual preference changed.

Focused verification passed 25 tests with 788 assertions across administration integration, canonical delivery, and accessibility. The complete cumulative Laravel suite then passed 272 tests with 4,334 assertions in 106.58 seconds. Pint and `git diff --check` passed. Changed files: `app/Http/Controllers/Superadmin/AdminDashboardController.php`, `tests/Feature/AdministrationIntegrationTest.php`, this checkpoint, and the corresponding NG-P0-002 traceability evidence.

## 2026-07-20 authorized B01 data execution

- Verified pre-migration database backup: `storage/app/backup-snapshots/b01-before-identity-migration-20260720.sqlite`, SHA-256 `1c5a6bbc94f92cba23ede9bed63adaf920782c539d327c6153b65c67745b1b1e`, identical to the source and `PRAGMA integrity_check=ok`.
- Verified private-storage snapshot: `storage/app/backup-snapshots/b01-private-storage-20260720.zip`, SHA-256 `ce142993aa2c26c42532be16b1cae47183a8615010c4dde76b99979815f2f4c6`; the archive opened successfully with 721 entries for 709 source files plus directories.
- The two reviewed identity/release migrations completed. The exact finalization command revoked all three database sessions. Postflight reports all normalized invariants passing: two active unverified real institutions, one exact HQ membership, two unresolved legacy users, no demo institutions, and no sessions.
- The reusable disabled-account lifecycle was then implemented and installed after a second verified backup, `b01-before-disabled-account-migration-20260720.sqlite`, SHA-256 `5ae8dc363d776a2fba3762907c89450285c1a2aa234c7bd3d0e12cad5f8a2f2d`, with `PRAGMA integrity_check=ok`.
- Disabled accounts are reversibly blocked from login, password reset (including tokens issued before disabling), authenticated sessions, and invitation issue/redemption. Disabling rotates the remember token, revokes only the target's database sessions, and appends a bounded reason-code audit. Only an active System Admin may perform it; self-disable fails closed. No account was disabled in this run.
- The two obsolete password-hash allowlist entries, five obsolete translation keys in each locale, one stale dashboard source comment, and one unused legacy dashboard query projection were deleted under exact owner authorization. The scanner now passes with only its two labeled synthetic fixtures.

The first installation of the disabled-account migration exposed SQLite table-rebuild loss of two existing `users` check constraints. Verification caught it before handoff. The migration was rolled back and corrected to preserve the role and legacy-institution-state checks in both directions, then reapplied; all three user rows and all business/content counts were preserved.

Authoritative database after the corrected reapplication: 25 migrations, 3 users, 0 disabled users, 0 sessions, 2 institutions, 1 membership, 1 identity audit, 2 curriculum release records, and 0 curriculum approval records. Last write: `2026-07-20T09:25:25.9449669+07:00`. SHA-256: `31532d0e1785e10d82baa3deacb29affdda9d631f43e890edb58acfeb0aeb8d2`.

## Remaining B01 gates

1. Exact account names are still required before disabling any of the three demo accounts.
2. The owner-attested unnamed lecturer approval permits testing through the visibly labeled non-production preview; it does not satisfy the named-evidence fields for a production or independent-review claim.
3. Real creator bootstrap remains blocked by the active demo Superadmin and must also prove working mail delivery.
4. B06 must add institution provenance to new progress/attempt records. Legacy progress remains unattributed.

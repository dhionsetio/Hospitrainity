# NG-B01 decisions — immediate release containment

- Decision date: 2026-07-19 (Asia/Jakarta)
- Batch: B01
- Accountable owner: Dhion Setio
- Status: accepted and applied to the authoritative local database; production release remains blocked
- Supersedes: the `NG-B01 through NG-B17` blanket-pending row in the decision register for B01 only

## Accepted decisions

1. Public self-registration is prohibited. A public visitor receives no institution roster or membership information.
2. B01 originally selected individual, expiring staff invitations. NG-B02 now supersedes the invitation-only restriction: personal self-study registration and pending, issuer-timed classroom-code membership requests are approved, while individual invitations remain available as a higher-assurance path. Verified-domain auto-enrollment and institutional SSO remain deferred.
3. Superadmins may issue or revoke invitations for any active institution. Admins and Supervisors may do so only for an institution in which they hold an active membership. Every invitation can create or add only the `user` (learner) role; it cannot grant a global or staff role. An existing Admin, Supervisor, or Superadmin account cannot redeem a learner invitation.
4. An institution name is hidden from anonymous users until they present a valid target-email-bound invitation. Authenticated members may see institutions available to their own membership context.
5. One person may belong to multiple institutions, but exactly one institution is active in a browser session. Authorization remains scoped to the selected active membership.
6. Hospitrainity HQ is intended for the creator, who may later add other approved members. The creator becomes HQ owner only through the confirmed one-time bootstrap; creating or assigning the record does not itself prove institution verification. Politeknik Negeri Malang (English name: State Polytechnic of Malang) is the second real institution record. Both records begin unverified, and no email domain was inferred.
7. Legacy institution values may map only by an exact, reviewed mapping. Fuzzy matching and silent merging are prohibited. Hotel A and Hotel B are not real production institutions; they are retained only as disposable local/E2E testing templates and fixtures.
8. An empty, unknown, or unmatched legacy institution remains explicitly unresolved and grants no normalized membership or cross-user scope.
9. Demo accounts may be seeded only in explicit `local` or `testing` processes, behind a fail-closed opt-in flag and fresh per-run secrets. Production rejects demo seeding even if accidentally enabled.
10. The first production Superadmin is created by a concurrency-guarded one-time command using the confirmed mailbox and display name. It creates an unrecoverable random initial credential, sends a normal time-limited password-reset link, remains unverified until the normal verification flow completes, and never prints a reusable password.
11. Ordinary migration must not silently delete authoritative sessions. It creates a pending finalization record. After a verified backup and while traffic is stopped, an owner-approved operator must run the exact confirmed finalization command, which transactionally deletes every database session and records completion. Bootstrap and production readiness fail closed until that explicit step succeeds.
12. A draft package may be delivered only as a visibly labeled preview outside production to authenticated, verified users. Production must reject draft import/delivery. Technical release metadata stays available in an accessible details view rather than dominating the learner interface.
13. A non-draft release requires separate named evidence for content, ESP/hospitality, CEFR, accessibility, rights/links, retention, and final-owner approval. No approver, qualification, or approval result may be fabricated. Publication and activation remain contained until those records exist.

## Why these choices are the safest usable default

This design reduces navigation choices for learners: there is no unauthenticated institution picker, a valid invitation fixes the target institution, and multi-membership users make one explicit context choice per session. Admin and Supervisor invitation screens remain useful without giving either role global tenant authority. Opaque, one-use invitation tokens and deny-by-default unresolved mappings separate proof of enrollment from a user-entered label. Disposable hotel fixtures preserve repeatable tests without representing fictional hotels as production organizations.

The curriculum policy preserves the current source unchanged while making its truth unambiguous: `0.4.0-draft` is a preview, not a release. The seven-gate model prevents a technical actor or passing automated test from impersonating academic, accessibility, legal/retention, or product-owner approval.

## Explicitly deferred or prohibited

- The authoritative identity schema is normalized and its pre-migration database/private-storage backups are retained. No user account was deleted or disabled.
- The reusable disabled-account lifecycle is installed and enforced across authentication, password reset, invitations, sessions, audit, and readiness. Applying it to any named account remains a separate exact owner action; B01 does not simulate archival by changing a password or role.
- No real creator account was bootstrapped; the exact display name and production mail delivery must be confirmed first.
- No Hotel A/Hotel B production institution is created, and no existing hotel-related row is silently mapped.
- No release approval is synthesized from the thesis, learning materials, automated tests, or Codex review.
- No production hosting provider, database topology, mail provider, backup system, monitoring service, or incident owner is assumed.
- B01 does not decide whether a multi-institution learner's activity history is portable personal progress or institution-attributed progress. Existing progress rows have no institution identifier, so a qualified learner may currently expose the same personal history to supervisors in each active membership. The B06 product/data decision must close this boundary before production data is accepted.

## Security, privacy, accessibility, and operational consequences

- Invitation tokens use cryptographic randomness, are stored as hashes, expire, are revocable and single-use, and redeem within one transaction. The target email is encrypted for delivery/display and separately HMAC-indexed for matching. Concurrent issue/revoke/redeem operations use one institution-first lock order, and a reissue revokes and audits every superseded pending invitation.
- Invitation endpoints use generic outcomes and layered throttles. Institution and invitation identifiers use the same not-found outcome outside the actor's authorized scope, so real cross-institution UUIDs do not become existence oracles. Rate-limit keys store keyed HMAC fingerprints rather than plaintext or reversibly normalized email addresses, and malformed array-shaped email input is rejected by validation rather than reaching canonicalization as an exception. The encrypted target address is integrity-checked against its HMAC index, and authenticated staff roles cannot use learner invitations to widen their institutional scope. Staff authorization is reloaded under the transaction lock rather than trusting a stale in-memory role or membership. A synchronous mail failure revokes the new invitation and records bounded evidence. A concurrent canonical-email collision rolls back and returns the same bounded unavailable outcome. Redemption revokes that learner's old database sessions inside the membership transaction and regenerates only the new current session after commit.
- Identity audit records contain bounded identifiers and event metadata; they do not store plaintext tokens or reusable passwords.
- The migration preflight is read-only. Its default report groups legacy labels by SHA-256 and contains no email addresses; plaintext legacy labels require an explicit operator flag and controlled evidence handling. A post-migration invariant failure returns non-zero before finalization or bootstrap.
- Active-institution selection is a server-authoritative session context, not a client-supplied authorization grant.
- The preview warning is visible in learner delivery while technical lifecycle details remain keyboard-accessible through native disclosure markup.
- Demo seeding is atomic and refuses to reuse an existing non-demo account or non-fixture institution. Idempotent reruns are accepted only for identities already marked as disposable fixtures in their expected test institution.
- A curriculum approval separately records the external reviewer's identity/qualification and the authenticated Superadmin recorder. Release transitions reload Superadmin authority under a single lifecycle lock, database restrictions preserve referenced evidence, and package checksums plus all seven complete approval fields are revalidated at import and delivery. Production delivery additionally requires a matching append-only activation event, activation actor, timestamp, and reason. Production rollback remains disabled pending B17; the restore guard validates the same activation evidence and full release snapshot before any mutation.
- Production readiness validates that `APP_KEY` is supported by the configured cipher, all manifest-referenced CSS/JavaScript assets exist under the build directory, Composer development packages are absent, `node_modules` is not deployed, and `public/hot` is absent.
- Production remains blocked until real topology, backup/restore, mail, release approvals, migration rehearsal, and independent specialist evidence exist.

## Rollback and forward-fix boundary

The B01 schema is expand-first: legacy `users.instansi` remains read-only evidence while normalized institutions/memberships carry new authorization. The migration rollback was tested in an isolated database. Authoritative migration must begin with a verified backup and reviewed exact mappings. Do not re-enable public self-enrollment or production demo seeding as rollback mechanisms. Preserve prior curriculum packages and release evidence; use a forward fix or the recorded transactional rollback artifact.

## Evidence basis

- [OWASP Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html): least privilege, deny by default, and authorization checks on every request.
- [OWASP Multi-Tenant Security Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Multi_Tenant_Security_Cheat_Sheet.html): tenant context and cross-tenant isolation.
- [OWASP Forgot Password Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html): random, stored-safely, expiring, single-use tokens and uniform responses.
- [OWASP Session Management Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html): session renewal and invalidation around privilege/security changes.
- [Laravel 12 authentication](https://laravel.com/docs/12.x/authentication), [authorization](https://laravel.com/docs/12.x/authorization), [database transactions](https://laravel.com/docs/12.x/database#database-transactions), and [rate limiting](https://laravel.com/docs/12.x/rate-limiting).

Reproducible implementation and test evidence is recorded in `docs/checkpoints/NG-B01-CHECKPOINT.md` and `docs/evidence/NG-B01-EVIDENCE.json`.

## 2026-07-20 owner authorization and remaining gates

The owner explicitly authorized deletion of the two obsolete password-hash allowlist entries, the verified authoritative backup/migration/postflight, the exact all-database-session revocation command, the disabled-account capability, and institution-attributed staff/combined learner progress semantics. Those operations completed successfully. The creator display name is `Dhion Setio`.

Remaining gates:

1. No account is disabled automatically. Applying the reversible state to `superadmin@example.com`, `supervisor@example.com`, or `user@example.com` still requires naming the exact account. This preserves uncomplicated testing.
2. The owner reports lecturer/expert approval but does not want reviewer names recorded. That is accepted as an owner attestation for non-production testing only. It cannot be represented as named independent evidence or used for a production/accreditation/conformance claim. The current visibly labeled non-production preview remains the truthful testing path.
3. The real creator bootstrap cannot run while an active demo Superadmin exists. It also requires working mail delivery to `dhionsetio@gmail.com`. No creator account was fabricated or silently substituted.
4. New progress/attempt rows still need institution provenance in B06. Legacy rows remain explicitly unattributed until that schema exists.
5. B03/B06/B17 operational evidence, target-browser/device evidence, production topology, and any broader specialist claims remain open.

Supplemental closure: the approved NG-B02/NG-B05 architecture installed server-derived personal/institution scope keys and exact-membership staff reads on 2026-07-20. The earlier B01 item saying new progress/attempt rows still lacked institution provenance is therefore closed. B06 remains open for broader course, class, assignment, and teaching-policy scope; historical B01 checkpoint text is retained as point-in-time provenance rather than a live to-do.

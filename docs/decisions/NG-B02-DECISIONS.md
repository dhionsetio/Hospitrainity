# NG-B02 decisions — lightweight privacy and data rights

- Decision date: 2026-07-20 (Asia/Jakarta)
- Batch: B02
- Accountable owner: Dhion Setio
- Status: enrollment and technical privacy lifecycle implemented expand-first; qualified legal/controller/topology and production gates remain
- Supersedes: the B02 portion of the blanket-pending decision-register row

## Accepted direction

1. Use a reviewed rights-request workflow with immediate reversible restriction after identity verification. Support every applicable access/export, correction, restriction, objection, erasure, withdrawal, and appeal request.
2. Do not create a separate Privacy Officer role or complex privacy bureaucracy. During the owner-operated thesis/pilot phase, System Admin handles requests through a small dedicated queue. Irreversible deletion still requires a fresh confirmation and audit; denial or legal hold requires a documented reason.
3. Ordinary institution staff see only operational account availability, not request reasons, exports, internal notes, or deleted content. Users receive a Privacy & Data profile area, accessible contact fallback, and current status plus important events.
4. Do not collect date of birth or add an age-classification feature. The intended population is adult or institution-managed learners. Institution invitation/join issuers must confirm that enrollment is appropriate. The service must not knowingly onboard a child without the legally required parent/guardian process.
5. Keep informal learning progress distinct from any future formal academic record. Future grades, submissions, and feedback require a later institution/legal retention decision.
6. Notify by email, authenticated dashboard, and optional Web Push. Push is opt-in, requested only after a user action, easy to disable per device, and contains no sensitive progress, response, security, or privacy-request details.
7. Use the shortest purpose-specific retention schedule approved for each category. Deletion removes direct identifiers and private responses/uploads where allowed, while retaining only necessary pseudonymous evidence. Exports are JSON plus accessible HTML, with sanitized CSV only for tabular data and no secrets, other-user data, private paths, or internal abuse signals.
8. Backups expire under a documented schedule and use restoration suppression rather than unsafe in-place archive editing. Start with essential cookies and no analytics.
9. Record policy acknowledgment separately from optional consent. Bahasa Indonesia is authoritative with a reviewed English translation carrying the same version/effective date. Account-assisted recovery remains available without CAPTCHA-only dependence.
10. Identity verification for export/deletion uses password reauthentication plus verified email initially and B03 step-up for privileged execution. Requests use CSRF protection, layered throttling, opaque identifiers, bounded audit events, and session revocation.
11. B01's disabled-account state is the reversible restriction stage. Existing real acceptance is never fabricated. The three current accounts are test/demo records: tests may create labeled synthetic acknowledgment, while any real account is `not acknowledged` until its next explicit action.
12. Recordings are permitted as a future feature, not automatically enabled. Each recording purpose must define notice/consent or other reviewed basis, access, retention/deletion, transcript/caption behavior, storage, and security before capture begins.
13. AI is not permanently prohibited. The planned chatbot remains a B15 feature gate: its provider/model, prompts and responses, training/data use, retention, human fallback, accuracy boundaries, cost, security, and accessibility must be decided before integration. No learner content is sent to an AI provider merely because a chatbot is planned.

## Simplified privacy explanation

Hospitrainity does not need ads or profiling to process personal data. Email addresses, names, institution relationships, learning progress, written answers, IP/session/security events, push subscriptions, and recordings can identify or relate to a person. The selected design keeps governance proportional: one owner-operated System Admin workflow, minimized collection, clear notice, reversible restriction, and an audit for irreversible actions.

## Approved enrollment architecture

On 2026-07-20, the owner explicitly approved this replacement for B01's individual-invitation-only decision:

- one personal learner account, never a separate empty institution account;
- optional personal self-study without an institution;
- a reusable classroom code with an issuer-selected duration from one second through 30 days may request an institution membership, but it grants only Learner scope, has a configurable redemption cap, is revocable/rotatable, and does not expose an institution directory; the interface defaults to one hour for convenience;
- the institution approves or rejects the pending membership;
- staff see only institution-attributed activity created after membership activation; prior personal progress remains private/unattributed rather than being erased or copied into a fake new identity;
- the join screen explains these boundaries before confirmation.

This decision supersedes B01's prohibition on public personal-account registration. Individual email invitations remain available as a higher-assurance enrollment path, but they are no longer the only supported path. Classroom-code enrollment must use an expand-first migration and deny-by-default authorization; it must not expose an institution directory or make prior personal progress visible to institution staff.

## Remaining release gates

- The legal controller is not inferred. Until an institution agreement or qualified interpretation identifies the controller, contact, lawful bases, retention periods, and transfer/processors, production use remains unclaimed and constrained to local/synthetic testing.
- No separate Legal Officer role will be created. This is an owner product decision, not evidence that legal obligations disappear.
- Production host country, database/storage/backup locations, mail/push providers, subprocessors, and international transfers remain unknown.
- Rollout is implementation-first with synthetic tests. Real-user production is not executed directly until the applicable privacy, hosting, security, and recovery controls exist.

## 2026-07-20 implementation follow-up

The approved personal-registration, pending adjustable-duration classroom-code request, staff decision, learner-only assignment, and personal/institution progress-separation foundation is implemented and installed in the authoritative local database. This closes the enrollment/provenance implementation item that B01 previously deferred to B06; B06 still owns broader course/class/assignment scope. The privacy rights-request queue, retention matrix, public trust pages, push notifications, and qualified legal/topology decisions remain open and are not implied by this checkpoint.

2026-07-20 follow-up: the owner superseded the fixed one-hour expiry. Authorized issuers now select days, hours, minutes, and seconds, with a validated total of 1 second to 30 days inclusive. Existing codes retain their stored expiry.

All three known demo accounts remain enabled by explicit owner instruction. No authoritative user, progress row, session, institution, or membership was deleted during this implementation. Evidence is recorded in `docs/checkpoints/NG-B02-B05-ARCHITECTURE-CHECKPOINT.md`.

## 2026-07-20 privacy-lifecycle completion follow-up

The technical B02 scope is now implemented and migration `000014` is installed: versioned bilingual public trust documents; separate registration acknowledgements; authenticated rights requests and recent-password staff queue; encrypted asynchronous JSON/accessible-HTML/sanitized-CSV export; signed expiring owner download; idempotent pseudonymization/deletion with retained institution/audit evidence; retention dry-run/execution/schedule; explicit encrypted browser push subscriptions and standards-based VAPID delivery; and deployment checks for schema, async queue, controller/contact, and VAPID.

The confirmed name/email are used only as configurable prototype operator/contact. The public copy explicitly says a statutory DPO, production controller relationship, qualified legal review, processors/transfers, and compliance conclusion are not established. VAPID secrets are intentionally absent locally, so push subscription prompts are disabled until a real deployment key pair exists. These external gates remain release-blocking; they are not stale technical to-dos and are not represented as completed evidence. See `docs/checkpoints/NG-B02-CHECKPOINT.md` and `docs/PRIVACY_DATA_LIFECYCLE.md`.

## Evidence basis

- Indonesian Law No. 27 of 2022 on Personal Data Protection, including Articles 24–28 on evidence of consent, children's data, lawful/transparent processing, and purpose limitation.
- MDN Push API/Notifications guidance and WebKit's standards-based Web Push behavior for installed iOS/iPadOS web apps.

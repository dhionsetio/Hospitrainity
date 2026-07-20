# Hospitrainity prototype privacy and data-lifecycle contract

Status: owner-approved technical prototype policy; qualified legal review and a production-controller determination are not recorded. This document describes implemented behavior, not legal advice or a compliance claim.

## Confirmed operator boundary

- Prototype owner/operator: Dhion Setio.
- Prototype contact: `dhionsetio@gmail.com`.
- No separate Privacy Officer or statutory DPO is claimed.
- Production host country, storage/backup locations, mail/push providers, subprocessors, transfers, and institution agreements remain unknown.
- Bahasa Indonesia is the authoritative policy locale. English uses the same version/effective date, but independent translation review is not claimed.

## Data dependency and deletion treatment

| Data class | Primary relationship | Approved deletion treatment | Current retention policy |
|---|---|---|---|
| Account name/email/authentication | `users` | Disable access, revoke sessions/reset tokens, rotate credentials, replace identifying fields with a pseudonymous tombstone | While active; tombstone after approved deletion |
| Personal learning | completions, canonical progress/attempts/responses with no institution membership | Delete in idempotent ordered steps | While the personal account is active or until approved deletion |
| Institution learning evidence | progress/attempt rows carrying an exact institution membership | Retain against the pseudonymous user and revoke the membership; do not copy it into personal scope | Institution-specific production policy remains required |
| Memberships and roles | normalized institution/platform/capability assignments | Revoke; retain bounded relationship evidence | While active plus an approved archive period |
| Administration and identity audits | restrict/null-on-delete references | Retain purpose-limited evidence against the tombstone or null actor | Prototype target: three years |
| Privacy requests/events | opaque request ID, encrypted notes, append-only events | Retain status/receipt; minimize request and decision notes after retention | Three years after closure |
| Generated export | encrypted private file plus checksum/expiry | Delete encrypted file at expiry; retain bounded status/hash evidence | 24 hours |
| Push subscriptions | encrypted endpoint/key/auth material | Revoke on opt-out/deletion; remove revoked records after 30 days | Until opt-out/account closure plus 30 days |
| Database/private-storage backups | recovery copies | Do not edit backups in place; expire them and apply tombstone suppression after restore before reopening service | Prototype target: up to 30 days |

## Request state machine

`submitted -> in_review -> approved -> executing -> completed`

Reviewed alternatives are `identity_pending`, `denied`, `held`, `failed`, `cancelled`, and `appealed`. Invalid transitions fail closed under a row lock. Duplicate active request types are rejected. A user may cancel only a submitted/identity-pending request they own. Export and deletion require a recently confirmed password; staff administration also requires recent password confirmation and System Admin authority.

Deletion cannot be self-approved by a System Admin and cannot remove the final active System Admin. Export and erasure jobs are queue-encrypted. Export files are encrypted at rest, downloaded only by the owning user through a short-lived signed URL, checked against a SHA-256 digest, marked no-store, and neutralize spreadsheet-formula prefixes.

## Push boundary

Push is explicit, per browser, encrypted in the database, and implemented with standards-based Web Push/VAPID. The application refuses subscription and delivery until `PUSH_NOTIFICATIONS_ENABLED=true` and real VAPID secrets are provided. The private key must never be committed. The current local deployment has no VAPID secrets, so the UI truthfully reports push as unavailable rather than showing a permission prompt that cannot work.

## Restore and incident behavior

An ordinary rollback must not resurrect deleted data. Before a restored backup is returned to service, operators must reapply request/tombstone evidence newer than that backup, revoke affected sessions/subscriptions, rerun erasure idempotently, verify foreign keys and privacy invariants, and only then reopen traffic. Failed erasure steps remain retryable evidence; an incident may stop new intake visibly, but it must not silently report requests as completed.

## Validation path

1. `php artisan test tests/Feature/PrivacyLifecycleTest.php`
2. `php artisan hospitrainity:privacy-retention` for a non-mutating preview; use `--execute` only for approved due artifacts.
3. `php artisan hospitrainity:deployment-check` to verify schema/queue and keep controller/VAPID/topology gates explicit.
4. `npm run test:e2e:prepare` followed by the targeted Playwright public trust and privacy-request journeys in isolated storage.
5. Before production, obtain qualified review of the controller role, lawful/policy bases, field retention/deletion, institution records, backup treatment, public copy, processors/transfers, and request deadlines.

## Primary technical sources used

- Laravel 12 filesystem/private and temporary URL guidance: <https://laravel.com/docs/12.x/filesystem>
- Laravel 12 queue and encrypted-job guidance: <https://laravel.com/docs/12.x/queues>
- OWASP CSV/formula injection guidance: <https://owasp.org/www-community/attacks/CSV_Injection>
- Maintained Web Push PHP implementation and VAPID guidance: <https://github.com/web-push-libs/web-push-php>
- European Commission overview of access, correction, restriction, portability, objection, and erasure rights (used as a workflow reference only, not as a determination that EU law governs this prototype): <https://commission.europa.eu/law/law-topic/data-protection/information-individuals_en>

# ADM-0 Administration Capability Matrix

- Date: 2026-07-19
- Status: Four-role ADM-6 administration, audit, authoring, and progress matrix enforced
- Policy source: accepted `ADR-002-administration-authority-and-data-boundaries.md`

This matrix records the enforced system through ADM-6. Every implementation must change policies, routes, navigation, and tests together. A hidden link is never authorization.

## Current enforced matrix

| Capability | Guest | `user` | `supervisor` | `admin` | `superadmin` | Current evidence |
|---|---:|---:|---:|---:|---:|---|
| Learner canonical delivery | Login | Allow | Deny | Deny | Deny | `role:user` route group |
| Own progress writes | Login | Allow | Deny | Deny | Deny | learner progress route and ownership validation |
| Same-institution learner progress metadata/detail | Login | Deny | Server-scoped allow | Deny | Allow globally | role middleware plus `UserPolicy::viewLearnerProgress`; exact non-empty `instansi` relationship |
| Aggregate/de-identified global progress | Login | Deny | Deny | Allow | Allow within identity dashboard | `UserPolicy::viewAggregateProgress`; admin view has no identity rows or institution slices |
| Identity-level global progress metadata/detail | Login | Deny | Deny | Deny | Allow | `UserPolicy::viewGlobalLearnerProgress`, fixed pagination, metadata-only service |
| Raw open/confidence/audio response access | Login | Deny | Deny | Deny | Deny | no route/capability; progress service never queries `curriculum_responses` or media |
| CSV learner-progress export | Login | Deny | Deny | Deny | Deny | no export route; disabled UI records separate approval requirement |
| Read retained legacy evidence | Login | Deny | Deny | Allow, always read-only | Allow | distinct `role:admin` / `role:superadmin` index routes plus policies |
| Write retained legacy curriculum | Login | Deny | Deny | Deny | HTTP 410 while canonical package is active | policies plus `legacy.curriculum.writable` |
| Create/edit/validate/preview canonical drafts | Login | Deny | Deny | Allow | Allow | ADM-2 policy, scoped routes, draft revisions, and preview repository |
| Approve canonical draft | Login | Deny | Deny | Deny | Allow | separate superadmin route and policy |
| Publish/activate or roll back canonical version in browser | Login | Deny | Deny | Deny | Allow after recent password confirmation | throttled ADM-2 publication routes and immutable importer/rollback records |
| Import the approved authority DOCX or approved assets into a draft | Login | Deny | Deny | Allow | Allow | ADM-3 policy/FormRequests, private generated paths, content inspection, bounded compiler job, digest-addressed asset blobs, and protected delivery routes |
| Create/edit canonical template exercises | Login | Deny | Deny | Allow | Allow | ADM-4 registry, policy-scoped draft routes, server-owned identities, server-authoritative scoring, and shared learner preview |
| View user directory | Login | Deny | Deny | Deny | Allow after recent password confirmation | dedicated policy and route |
| Review bounded administration audit | Login | Deny | Deny | Deny | Allow after recent password confirmation | `AdministrationAuditPolicy`, same-origin allowlisted step-up return, fixed-page unified identity/curriculum event review, and security-log access notice |
| Assign `user`, `supervisor`, or `admin` | Login | Deny | Deny | Deny | Allow after recent password confirmation | dedicated FormRequest/service |
| Promote another verified account to `superadmin` | Login | Deny | Deny | Deny | Separate typed-confirmation action | dedicated throttled endpoint |
| Change own role | Login | Deny | Deny | Deny | Deny | policy and transactional service defense |

`admin` is a constrained domain value with its own landing, read-only evidence routes, isolated canonical draft authoring/validation/preview/import surface, template exercise builder, and global aggregate progress view. It may queue the approved authority DOCX compiler, accept a ready dry run into that same draft, and upload only the approved image/audio asset formats with required provenance. It receives no role-management, approval, publication, identity-level learner-progress, arbitrary archive/package import, raw learner-response, CSV-export, or published-package in-place write authority.

## Approved target matrix

Legend: **A** allow, **D** deny, **S** server-scoped allow, **G** approval-gated decision.

| Capability | `user` | `supervisor` | `admin` | `superadmin` | Earliest phase |
|---|---:|---:|---:|---:|---|
| Learner canonical delivery and own progress | A | D | D | D | Existing |
| Same-institution learner progress metadata | D | S | D | A | ADM-5 |
| Aggregate/de-identified global progress | D | D | A | A | ADM-5 |
| Identity-level global progress metadata | D | D | D by default | A | ADM-5 |
| Raw open/confidence/audio response access | D | D | D | G | Separate privacy approval |
| Create/edit canonical drafts | D | D | A | A | ADM-2/ADM-3 |
| Import approved authority DOCX/assets into canonical draft | D | D | A | A | ADM-3 |
| Create/edit canonical template exercises | D | D | A | A | ADM-4 |
| Review/preview draft | D | D | A | A | ADM-2 |
| Publish/activate canonical version | D | D | D by default | A | ADM-2 |
| View retained legacy evidence | D | D | A | A | ADM-1 |
| Mutate retained legacy evidence | D | D | D | D | Never while canonical is supported |
| View user directory | D | D | D | A | ADM-1 |
| Assign `user`, `supervisor`, or `admin` role | D | D | D | A | ADM-1 |
| Promote another account to `superadmin` | D | D | D | A with step-up and safeguards | ADM-1 |
| Review administration audit | D | D | D | A with recent password confirmation | ADM-6 |

## Required automated matrix

For every administration route/action, tests must cover guest, unverified account, `user`, `supervisor`, `admin`, `superadmin`, and an unknown role. Data-bearing routes must also cover direct-ID access and cross-institution records. Write tests must cover CSRF, validation, policy denial, stale/concurrent state, transaction rollback, and audit behavior applicable to that phase.

# ADR-002: Administration authority, import, and data boundaries

- Status: Accepted
- Date: 2026-07-17
- Accepted: 2026-07-17 by the product owner's explicit instruction to continue with ADM-1
- Scope: Administration track ADM-0 through ADM-6
- Supersedes: none
- Related: `ADR-001-curriculum-recovery.md`

## Confirmed baseline at the ADM-0 decision point

1. Runtime authorization currently recognizes `user`, `supervisor`, and `superadmin`; there is no `admin` role.
2. The active canonical package is the learner-delivery authority. Published/active packages and retained legacy curriculum rows are not browser-editable.
3. Supervisors currently see only learners whose `instansi` exactly matches their own, and only an overall percentage.
4. Superadmin currently has no user, role, or learner-progress administration routes.
5. The authoritative DOCX reviewed for this project is 117,314 bytes. The current PHP runtime reports `upload_max_filesize=2M`, `post_max_size=8M`, and `max_file_uploads=20`.
6. Existing legacy media validators allow individual files up to 5 MB, which exceeds the current PHP per-file limit. This mismatch is harmless while canonical delivery keeps legacy writes retired, but it must be resolved before any supported upload workflow is released.

## Decisions

The product owner accepted these fail-closed defaults without revisions by explicitly authorizing ADM-1 after reviewing the ADM-0 checkpoint.

### DEC-ADM-001 — Target role matrix

| Role | Target authority |
|---|---|
| `user` | Learner experience and own progress only |
| `supervisor` | Progress metadata for learners in the same institution only; no curriculum or role administration |
| `admin` | Canonical draft authoring/review and aggregate or de-identified progress only; no publication or role assignment |
| `superadmin` | Global progress metadata, canonical publication, user/role administration, and audit review |

ADM-1 introduces the `admin` role. Route middleware alone is not the final authorization model; resource actions require policies/capabilities and server-side query scopes.

### DEC-ADM-002 — Progress and response-data visibility

1. `admin` receives aggregate/de-identified reporting by default, not every learner's identity-level detail.
2. `superadmin` may view global learner identity and versioned progress metadata.
3. `supervisor` remains restricted to the same institution through a server-side query scope/policy.
4. No administrative role receives raw open responses, confidence answers, recordings, or future audio by default. Such access requires a separately approved purpose, capability, audit event, and retention rule.

### DEC-ADM-003 — Authoring, review, and publication ownership

1. `admin` and `superadmin` may create and edit canonical drafts after ADM-2.
2. Only `superadmin` may approve/activate a published canonical version by default.
3. Preview interactions never affect learner progress.
4. Published canonical packages and legacy evidence remain immutable; fixes produce a new canonical version.

### DEC-ADM-004 — Initial import and asset boundary

1. The first supported source import is one DOCX processed through the canonical compiler into a draft. Generic ZIP and arbitrary canonical-JSON uploads are deferred until their schemas and archive-threat controls exist.
2. Initial DOCX application limit: 2 MiB, matching the current PHP per-file ceiling and exceeding the current authority file by more than 17 times. A larger limit requires aligned application, PHP, reverse-proxy, storage, timeout, and abuse-test evidence.
3. Initial separately uploaded asset allowlist: JPEG, PNG, WebP, MP3, and WAV, maximum 2 MiB each under the current runtime. MP4 upload and SVG are deferred. HTTPS YouTube links may be considered as references, not uploaded files.
4. Uploads go to private/quarantine storage under generated names and are validated from file content plus approved extension before parsing. No uploaded source or original asset is executed or placed directly in `public/`.
5. The initial request accepts one source file and a bounded asset set whose combined request size remains below the configured `post_max_size`. Direct-to-object-storage or queued multipart upload is a future, separately tested option.

### DEC-ADM-005 — Export and audit retention

1. Progress CSV export is disabled until columns, authorization, row limit, neutral spreadsheet-cell encoding, purpose, and deletion handling are approved.
2. Security/content administration audit records contain actor, target/resource, action, old/new non-secret state, reason, timestamp, and bounded request metadata. They exclude passwords, tokens, session IDs, uploaded file contents, and raw learner responses.
3. Recommended initial application retention is 365 days for online audit lookup, followed by deletion through a tested scheduled policy. Backups must follow the same approved retention policy; production deployment must reconcile this recommendation with the institution's actual governance requirements.

## Consequences

- ADM-1 can create a narrow `admin` role without implicitly granting global learner surveillance, publication, or role assignment.
- Superadmin remains the only role able to activate curriculum or change roles by default.
- The first import surface stays small enough to validate and threat-model reliably.
- Larger media and data export remain explicit enhancements rather than accidental permissions.
- If the product owner changes these boundaries, the capability matrix, policies, tests, privacy copy, and checkpoint evidence must change together.

## Acceptance record

The product owner approved all five decisions by instructing Codex to continue with ADM-1 on 17 July 2026. ADM-1 implements only the role and user-administration portion of this ADR; later administration phases remain bound by the progress, authoring, import, export, and retention decisions above.

For ADM-1, one recently password-confirmed superadmin may promote another verified account through the separate typed-confirmation workflow. A second-approver requirement is not enabled because no approver lifecycle, quorum rule, emergency recovery process, or governance owner has been defined. If production governance requires dual control, it must be added as an explicit stateful approval workflow and tested before representing promotion as dual-approved.

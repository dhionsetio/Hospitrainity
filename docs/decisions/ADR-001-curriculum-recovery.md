# ADR-001: Canonical curriculum recovery and destructive legacy changes

- Status: Accepted for the supported canonical deployment
- Date: 2026-07-17
- Scope: Phase 9 recovery/versioning decision

## Context

The supported learner delivery path has an active canonical curriculum package. Its source is versioned and checksum-locked. The importer validates and diffs the source, records every run, writes a pre-import rollback artifact, changes the normalized projection in a database transaction, verifies the result, and generates the standalone projection deterministically.

The five former module/lesson/vocabulary/material/exercise managers are retained as migration evidence. While a canonical package is active, their write controls are absent and `EnsureLegacyCurriculumWritable` rejects direct store/update/delete requests with `410 Gone`. Those rows do not control current learner delivery.

## Decision

No second soft-delete or per-row revision system will be added to the active canonical curriculum in Phase 9. It would duplicate the existing version/checksum/import-run/rollback model without creating an active destructive edit path. Recovery for supported deployments is:

1. preserve external database and media backups before release;
2. review `hospitrainity:curriculum dry-run`;
3. import a new immutable source version;
4. retain its report and pre-import rollback artifact; and
5. use the recorded rollback command followed by verification if recovery is required.

The application must continue to fail closed if a canonical version collides with a different checksum, if validation fails, or if a stale client calls a retired legacy write route.

## Consequences

- Active curriculum changes remain attributable to a source version, source tree checksum, import run, and report.
- Learner completion and canonical projection recovery are covered by transaction/rollback artifacts and release backups rather than hidden row edits.
- Generated standalone output remains replaceable and is never a source of truth.
- Operators must protect private rollback artifacts and test restoration; an artifact stored only on the failed host is not a backup.

## Deliberate limitation

If an operator removes/deactivates the canonical package and re-enables the legacy fallback, its old CRUD deletions remain physical and have no new soft-delete/audit ledger. Whether that unsupported fallback must become an auditable production authoring system is an unresolved product/retention decision. It must be decided before enabling legacy writes in production; Phase 9 does not invent a retention period, approval workflow, or legal audit requirement.

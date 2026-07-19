# CP-08 Checkpoint Report — Platform and Database Design

**Date:** 2026-07-15  ·  **Status:** Complete  ·  **Validation:** `hsp-validate all --strict` → 8/8

## What was done

- Built a deterministic, read-only **data-model tool** (`hsp-datamodel.mjs`) that reads all 17 JSON
  schemas, the canonical package, and the id-registry, and derives the storage model: logical tables
  (columns, primary key, foreign keys), a PostgreSQL DDL, and a coverage/integrity report — asserting
  the design against the real package so nothing is invented.
- **Verified referential integrity against real data: 525 references checked, 0 violations**;
  registry covers **435/435** id-bearing entities (487 total ids = 435 entities + 52 framework
  documents). Emitted `data-model.json`, `schema.sql`, `coverage.json`.
- Wrote the **platform architecture** (`platform-architecture.md`): compiler → validator → importer
  → PostgreSQL → read API → renderer, with a SQLite mirror for the offline standalone; storage
  strategy rationale (document-relational hybrid) with alternatives considered and rejected;
  determinism, versioning, and lifecycle handling.
- Wrote the **database design** (`database-design.md`): 9 entity tables + `framework_document`, ERD,
  the verified integrity table, indexing plan, and a validator-check → storage-constraint crosswalk.
- Wrote the **deterministic import contract** (`import-contract.md`): idempotent, transactional
  import algorithm and the invariants the CP-09 importer must re-assert (with a concrete test
  contract).
- Updated CHECKPOINTS (CP-08 → Complete), DECISIONS (DEC-026..028), CHANGELOG; refreshed the
  standalone checkpoint marker CP-07 → CP-08 (provenance metadata only).

## Chosen technique and why

**Document-relational hybrid on PostgreSQL 15+** — canonical entity in `doc jsonb` + extracted
scalar columns + code-based foreign keys + lifecycle CHECK constraints; SQLite mirror for the offline
renderer. It keeps the intact, schema-evolvable canonical JSON as the single writer of a row while
enforcing the validator's guarantees at rest. Pure-relational (brittle to schema change) and
pure-document (weak integrity) were considered and rejected.

## Evidence

- `hsp-datamodel.mjs` is reproducible and self-checking: it exits non-zero if any FK is unresolved
  or any id-bearing entity is missing from the registry. Current run: 0 violations, coverage.ok=true.
- Structural validation unchanged at 8/8; no content entity modified, so all CP-04/CP-05 provenance
  hashes still verify. CP-02 framework files and both digests byte-identical; registry unchanged at
  487 (registry_version 1.2.0).

## Limitations

- Design only: no importer or API is implemented (CP-09/CP-10) and no live database is provisioned;
  `schema.sql` is a design artifact, not a deployed migration.
- Array and polymorphic references (`outcome_codes[]`, `target_code`, `canonical_code`) cannot be a
  single SQL foreign key; they are enforced by the importer + validator, optionally materialized as
  junction views. This is documented, not a gap.
- Performance/scaling (partitioning, connection pooling, caching) is deferred to implementation; the
  current volume (435 entities) does not require it.
- The CP-02 human-approval gate remains **open**; no CEFR level is published.

## Next action

- **CP-09 — Deterministic compiler/importer:** implement the DOCX→package compiler and the
  package→database importer against the CP-08 contract and test set. Requires the latest artifacts
  reattached + `Continue`.

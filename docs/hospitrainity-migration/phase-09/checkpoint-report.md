# CP-09 Checkpoint Report — Deterministic Compiler / Importer

**Date:** 2026-07-15  ·  **Status:** Complete  ·  **Validation:** `hsp-validate all --strict` → 8/8

## What was done

- Implemented the CP-08 import contract as a **deterministic, idempotent, transactional importer**
  (`tools/hsp-import.mjs`) using Node's built-in `node:sqlite` — the offline SQLite mirror named in
  the CP-08 design. No third-party dependency and no network required.
- The importer **projects** the validated canonical package into the relational model, driven by the
  CP-08 `data-model.json` (so schema and implementation cannot drift), and re-asserts every invariant
  the validator and the CP-08 contract require.
- Imported **7 framework/provenance documents + 443 entity rows** across 9 tables and verified:
  id integrity **435/435**, projection integrity **4016 checks / 0 mismatches**, referential
  integrity **525 refs / 0 unresolved** (6 scalar FKs engine-enforced via dependency-ordered inserts
  with foreign keys ON; array + polymorphic refs code-checked), lifecycle CHECK-enforced (0
  violations), registry coverage **435/435**, and **idempotent** re-import (identical content digest).
- Emitted a deterministic, engine-independent load script `phase-09/curriculum.sql`
  (sha256 `b45a5e88…`) and a machine-readable `phase-09/import-report.json`
  (content digest `eb5c581a…`).
- Updated CHECKPOINTS (CP-09 → Complete), DECISIONS (DEC-029..031), CHANGELOG; refreshed the
  standalone checkpoint marker CP-08 → CP-09 (provenance metadata only).

## Chosen technique and why

**Deterministic projection into `node:sqlite`, schema-driven by `data-model.json`.** Dependency-
ordered inserts with `PRAGMA foreign_keys = ON` give real engine-level referential enforcement;
array/polymorphic references are verified in code (DEC-028). Reproducibility is content-addressed
(canonical row digest + stable `curriculum.sql`), not the non-reproducible `.db` bytes.

## Evidence

- `hsp-import.mjs` exits non-zero if any invariant fails, the import is not idempotent, or any
  reference is unresolved. Current run: `import.ok=true`.
- Structural validation unchanged at 8/8; no content entity modified, so all CP-04/CP-05 provenance
  hashes still verify. CP-02 framework files, both provenance digests, and the id-registry verified
  byte-identical to the CP-08 outputs; registry unchanged at 487 (registry_version 1.2.0).

## Limitations

- Package→database importer only: the DOCX→package compiler is not re-run here (provenance-locked to
  CP-04/CP-05); re-deriving content would risk mutation and is out of scope.
- No API/renderer (CP-10) and no live PostgreSQL is provisioned. `curriculum.sql` is the reviewable
  build; the compiled `.db` is a disposable artifact and is intentionally not shipped in the zip.
- `node:sqlite` is flagged experimental in Node 24 (requires `--experimental-sqlite`); the logical
  contract is engine-independent and reproduced by `curriculum.sql` on any SQLite / PostgreSQL.
- The CP-02 human-approval gate remains **open**; no CEFR level is published.

## Next action

- **CP-10 — Renderer implementation:** build the read-only renderer/serving layer over the compiled
  store (and align the offline standalone to the same query surface). Requires the latest artifacts
  reattached + `Continue`.

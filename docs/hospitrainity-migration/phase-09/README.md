# Phase 09 — Deterministic Compiler / Importer

CP-09 turns the CP-08 import contract into working, verified code: a deterministic importer that
compiles the validated canonical package into the relational storage mirror and re-asserts every
invariant. No content entity is changed; structural validation stays 8/8.

## Deliverables

- `../phase-03/tools/hsp-import.mjs` — the deterministic, idempotent, transactional importer/compiler
  (Node's built-in `node:sqlite`; no third-party dependency, no network).
- `compiler-importer-spec.md` — pipeline position, algorithm, invariants, and determinism strategy.
- `curriculum.sql` — engine-independent load script (DDL + INSERTs, sorted + canonical) that
  recreates the database on any SQLite; stable SHA-256.
- `import-report.json` — machine-readable verification: row counts, invariant checks, digests.
- `checkpoint-report.md`, `source-manifest.json` — delivery record and checksums.

## Result

Imported **7 framework/provenance documents + 443 entity rows** across 9 tables. Verified:

- **id integrity** 435/435 (`uuidv5(code, namespace) == id`)
- **projection integrity** 4016 column checks, 0 mismatches
- **referential integrity** 525 references, 0 unresolved (6 scalar FKs engine-enforced; array +
  polymorphic refs code-checked)
- **lifecycle** CHECK-enforced, 0 violations
- **registry coverage** 435/435 id-bearing entities present in the id-registry
- **idempotent** re-import produces an identical database (content digest matches)

## Reproduce

```bash
node docs/hospitrainity-migration/phase-03/tools/hsp-validate.mjs all --strict            # 8/8 gate
node --experimental-sqlite docs/hospitrainity-migration/phase-03/tools/hsp-import.mjs      # import + verify + emit
```

## Chosen technique (and why)

**Deterministic projection into `node:sqlite`, driven by the CP-08 `data-model.json`.** The schema
is not re-invented — it is read from the design artifact, so design and implementation cannot drift.
Dependency-ordered inserts with `PRAGMA foreign_keys = ON` give real engine-level referential
enforcement; array/polymorphic references (which a scalar SQL FK cannot express, DEC-028) are
verified in code. Reproducibility is content-addressed via a canonical row digest and a stable
`curriculum.sql`, **not** the `.db` bytes (SQLite page layout is not byte-reproducible).

## What CP-09 intentionally does NOT do

- No DOCX→package re-compilation (that ran in CP-04/CP-05 and is provenance-locked; re-deriving would
  risk mutation).
- No API/renderer (CP-10) and no live PostgreSQL provisioning; `curriculum.sql` is the reviewable
  build, and the compiled `.db` is a disposable artifact not shipped in the Source.zip.

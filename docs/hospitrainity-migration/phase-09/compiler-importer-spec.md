# Phase 09 — Deterministic Compiler / Importer

CP-09 implements the CP-08 import contract (`../phase-08/import-contract.md`) as executable code and
proves it against the real package. The tool is `../phase-03/tools/hsp-import.mjs`.

It is a **projection**, never an authority: it copies a validated canonical package into the storage
model and re-asserts every invariant. It edits no content entity (DEC-001, DEC-027). Structural
validation stays at 8/8.

## 1. Pipeline position

```
Hospitrainity.docx  ─(CP-04/05 decomposition, provenance-locked)─>  canonical package (JSON)
                                                                          │
                                              hsp-validate all --strict (8/8) ─┐
                                                                          │    │ gate
                                                                          v    v
                                      hsp-import.mjs  ──projection──>  relational mirror
                                                                     (SQLite now; PostgreSQL 15+ later)
```

The DOCX→package **compiler** already ran in CP-04/CP-05 and its output is provenance-locked to the
DOCX by SHA-256 digests; re-deriving content here would risk mutation and is forbidden. CP-09
therefore fixes the **package→database compiler/importer**: the deterministic step that compiles the
validated package into a servable relational build.

## 2. Inputs

| Input | Role |
|---|---|
| `curriculum/hospitrainity/0.3.0-draft/**` | canonical package (443 entities + 7 framework/provenance documents). |
| `phase-03/id-registry.json` | authoritative code→UUIDv5 registry (487 ids; namespace `cc9dd546…`). |
| `phase-08/data-model.json` | the CP-08 design artifact that drives the schema (single source — the importer does not invent tables). |

## 3. Storage engine

- **Now:** `node:sqlite` (Node's built-in SQLite, the offline mirror named in the CP-08 design). No
  third-party dependency, no network. This is the engine that also backs the offline standalone.
- **Later (physical target):** PostgreSQL 15+ with the same logical model (`phase-08/schema.sql`).
  The type map is `uuid|text|jsonb → TEXT` for SQLite; identical column/PK/FK/CHECK semantics.

## 4. Algorithm (deterministic, idempotent, transactional)

1. Build the schema from `data-model.json`: one table per entity type + a `framework_document`
   table. Constraints are derived from the columns that actually exist — `PRIMARY KEY(id)` when an
   `id` exists (else the natural `code`, else a content-hash surrogate `_rowkey` for the keyless
   `migration_edge`), `UNIQUE(code)` when a `code` exists, and a lifecycle `CHECK(status IN …)` when
   a `status` exists. `PRAGMA foreign_keys = ON`.
2. `BEGIN`. Upsert the 7 framework/provenance documents (keyed by `kind`). Insert entities in
   dependency order — chapter → lesson_section → activity → prompt_item → {answer_model,
   feedback_model, rubric} → source_provenance → migration_edge — so the six scalar SQL foreign
   keys are satisfied at insert time and enforced by the engine. Each row stores the full canonical
   JSON in `doc` plus its extracted scalar/JSONB columns. `COMMIT`. Any failure rolls back the whole
   import (no partial package can land).
3. Upserts use `ON CONFLICT(<key>) DO UPDATE`, so a re-import of the identical package produces an
   identical database (verified by content digest — see §6).

## 5. Invariants re-asserted (mirrors the validator and the CP-08 contract)

| Invariant | How it is proven | Current result |
|---|---|---|
| id integrity | `uuidv5(code, namespace) == id` for every id-bearing entity. | 435 checked, 0 bad |
| projection integrity | every extracted column equals the value inside `doc`. | 4016 checked, 0 bad |
| referential integrity | 6 scalar FKs enforced by the engine; array (`outcome_codes[]`) and polymorphic (`target_code`, `canonical_code`) refs checked in code against the loaded code set. | 525 refs, 0 unresolved |
| lifecycle | `status` within the legal set (CHECK + assertion). | 0 violations |
| provenance | `source_locator` retained in `doc`; digests loaded into `framework_document`. | intact |
| registry coverage | every id-bearing entity code present in the id-registry. | 435/435 |

## 6. Determinism & reproducibility

- The importer emits `phase-09/curriculum.sql` — a sorted, canonical, engine-independent load script
  (DDL + INSERTs) that recreates the database on any SQLite. Its SHA-256 is stable across runs:
  `b45a5e883ef4a13ddbd5d2c3a64ff55224435e8a897f7140fe356561695836e0`.
- The **build digest** is a content hash over sorted `(table, key, canonical doc)` rows
  (`eb5c581a5e2b7ed105cd4c65e22661b9e4021cbf25057eb1902c74f44e6c3787`), **not** the `.db` file bytes:
  SQLite page layout is not byte-reproducible, so the logical content is hashed instead. This is the
  modern, reliable way to content-address a database build.
- The compiled `.db` is a disposable build artifact and is intentionally **not** shipped in the
  Source.zip; `curriculum.sql` is the reproducible, reviewable deliverable.

## 7. Reproduce

```bash
# from repo root (E-learning-main)
node docs/hospitrainity-migration/phase-03/tools/hsp-validate.mjs all --strict     # 8/8 gate
node --experimental-sqlite docs/hospitrainity-migration/phase-03/tools/hsp-import.mjs   # import + verify + emit
# add --keep to retain phase-09/curriculum.db for inspection
```

Exit code is non-zero if any invariant fails, the import is not idempotent, or any reference is
unresolved.

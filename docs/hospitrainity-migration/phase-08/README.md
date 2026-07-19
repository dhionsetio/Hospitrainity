# Phase 08 — Platform and Database Design

CP-08 is an **additive architecture** phase. It designs how the canonical Hospitrainity curriculum
package is stored and served — a database model, a platform/component architecture, and a
deterministic import contract — without adding runtime code to the learner payload or mutating any
content entity. Structural validation stays at 8/8.

## Deliverables

- `platform-architecture.md` — component architecture (compiler → validator → importer → database →
  API → renderer), storage strategy rationale, determinism, versioning/lifecycle.
- `database-design.md` — logical + physical model: 9 entity tables + `framework_document`, ERD,
  verified referential integrity, indexing, and how each validator check maps to a storage constraint.
- `import-contract.md` — the deterministic, idempotent, transactional import algorithm and the
  invariants the importer must re-assert (the CP-09 test contract).
- `data-model.json` — machine-readable table/column/PK/FK model (generated).
- `schema.sql` — PostgreSQL DDL for the model (generated).
- `coverage.json` — verification: every entity mapped, 525 refs checked / 0 violations, registry
  435/435 id-bearing entities covered.
- `../phase-03/tools/hsp-datamodel.mjs` — the deterministic, read-only generator/verifier.
- `checkpoint-report.md`, `source-manifest.json` — delivery record and checksums.

## How to reproduce

```bash
# from repo root (E-learning-main)
node docs/hospitrainity-migration/phase-03/tools/hsp-datamodel.mjs                 # regenerates data-model.json, schema.sql, coverage.json
node docs/hospitrainity-migration/phase-03/tools/hsp-validate.mjs all --strict     # 8/8
```

## Result

Data model covers all 9 entity types (435 id-bearing entities) plus the framework/registry
documents. Referential integrity **verified against the real package: 525 references, 0 violations**;
registry covers 435/435 id-bearing entities (487 total ids = 435 entities + 52 framework-document
ids). Structural validation remains **8/8**; no content entity, the registry, or the standalone
payload was changed (only the checkpoint marker).

## Chosen technique (and why)

**Document-relational hybrid on PostgreSQL 15+** — canonical entity in a `doc jsonb` column plus
extracted scalar columns, code-based foreign keys, and lifecycle CHECK constraints; SQLite mirror
for the offline standalone renderer. This preserves the intact, schema-evolvable canonical JSON
while enforcing the validator's integrity guarantees at rest. See `platform-architecture.md` §3 for
the alternatives considered and rejected.

## What CP-08 intentionally does NOT do

- No importer/API implementation (CP-09/CP-10) — only the contract is fixed here.
- No live database is provisioned and no data is migrated; `schema.sql` is a design artifact.
- No learner content, registry, or standalone payload change (marker only).

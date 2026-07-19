# Phase 08 — Deterministic Import Contract

This specifies how a validated canonical package is loaded into the database (§2 of
`database-design.md`). The importer itself is built in CP-09; CP-08 fixes its contract so it is
unambiguous and testable. The importer is a **projection**, never an authority: it copies a
validated package into storage and re-verifies invariants; it never edits content.

## 1. Preconditions

1. `node hsp-validate.mjs all --strict` → **8/8** on the package being imported. A package that does
   not validate MUST NOT be imported.
2. The target `schema_version` matches the database's deployed schema (else a reviewed migration
   runs first).

## 2. Import algorithm (deterministic, idempotent, transactional)

```
BEGIN;
1. Upsert framework_document rows: competency-framework, outcome-alignments, cefr-references,
   curriculum-release/package, id-registry (keyed by kind).
2. Insert entity rows in dependency order:
     chapter -> lesson_section -> activity -> prompt_item
              -> answer_model, feedback_model, rubric -> source_provenance -> migration_edge
   For each entity:
     - assert uuidv5(code, namespace) == id            (id integrity)
     - assert extracted scalar columns == values in doc (projection integrity)
     - store the canonical JSON byte-for-byte in doc
     - ON CONFLICT (code) DO UPDATE only when content_version differs and the new row supersedes
       the old (replaces/replaced_by); published rows are append-only.
3. Verify referential integrity:
     - scalar FKs enforced by constraints
     - array FKs (outcome_codes[]) and polymorphic FKs (target_code, canonical_code) asserted
       against the loaded code set (must be 0 unresolved, matching coverage.json)
COMMIT;   -- any failure rolls the whole import back; no partial package ever lands
```

## 3. Idempotency & reproducibility

- Re-importing the identical package is a no-op (same `id`/`code`, same `doc`).
- Import order is fixed and dependency-topological, so FK constraints hold without deferral.
- No ids are minted at import time; the importer trusts the registry and re-derives UUIDv5 to check.

## 4. Invariants the importer must re-assert (mirrors the validator)

| Invariant | Assertion |
|---|---|
| id integrity | `uuidv5(code, namespace) == id` for every entity. |
| projection integrity | every extracted column equals the value inside `doc`. |
| referential integrity | 525 refs resolve, 0 unresolved (see `coverage.json`). |
| lifecycle | `status` within the legal set (CHECK). |
| provenance | `source_locator` present in `doc`; digests present in `framework_document`. |
| registry coverage | every id-bearing entity code present in the id-registry (435/435). |

## 5. Rollback & migration

- **Rollback:** import is a single transaction; failure restores the prior state exactly. Because
  superseding is append-only, reverting to a prior `content_version` is selecting the earlier row.
- **Schema migration:** a `schema_version` bump ships a forward migration + a down migration; both
  are reviewed. Data migrations re-project from the canonical package, never hand-edit rows.

## 6. Test contract (for CP-09 implementation)

The importer implementation in CP-09 must pass, at minimum:
1. Import the current 0.3.0-draft package → 435 entity rows + 5 framework documents, 0 FK violations.
2. Re-import the same package → no changes (idempotent).
3. Import a package with a dangling reference → rejected, transaction rolled back.
4. Import with a tampered id (id != uuidv5(code)) → rejected.
5. `coverage.json` regenerated post-import equals the pre-import projection.

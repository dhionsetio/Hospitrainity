# Lifecycle, change classification & versioning

## Status machine
```
draft → review → approved → published → retired
```
Legal transitions (enforced by `hsp-validate lifecycle`):

| From | Allowed to |
|---|---|
| draft | review, retired |
| review | approved, draft, retired |
| approved | published, review, retired |
| published | retired |
| retired | (terminal) |

Illegal examples that **fail CI**: `published → draft`, editing a `published` id in place, or any status outside the enum.

## Change classification
- **editorial** — typo, formatting, non-semantic: keep id/code, bump **patch**.
- **substantive** — meaning, scope, answer key or alignment change: mint **new** id/code, link `replaces`/`replaced_by`, bump at least **minor**.

## Retirement & replacement
- Retire by setting status `retired`; keep the entity resolvable.
- `replaced_by` must point to a live (non-retired) id; `replaces` points back.

## Versioning (SemVer 2.0.0)
- `schema_version` — the structural contract in `schemas/`. Breaking schema change = major.
- `content_version` — the curriculum content. Independent of `schema_version`.
- Compiled `generated/` output inherits the release `content_version`.
- CP-03 baseline: `schema_version 1.0.0`, `content_version 0.3.0-draft` (DEC-013).

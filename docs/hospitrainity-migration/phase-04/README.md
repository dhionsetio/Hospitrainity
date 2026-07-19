# Phase 4 — Manuscript Decomposition & Legacy Inventory (CP-04)

This phase turns the source-of-truth manuscript (`Hospitrainity.docx`) into
addressable, provenance-bearing content entities under the CP-03 schemas, and
inventories the legacy `database/data/course.json` against the new structure. It
adds **no learner-facing content**.

## What was produced
- **7 chapter entities** — `curriculum/hospitrainity/0.3.0-draft/chapters/HSP-C0N/chapter.json`.
- **85 lesson-section entities** — `chapters/HSP-C0N/sections/HSP-C0N-LS-XX.json`, mirroring the manuscript's own step structure.
- **7 source-provenance records** — `provenance/HSP-C0N.provenance.json`.
- **8 migration edges** + **legacy-inventory.json** — `provenance/migration/` and `provenance/legacy-inventory.json`.
- **docx-digest.json** — `provenance/docx-digest.json`, the normalized per-entity SHA-256 map that makes provenance verifiable offline (the DOCX itself is not shipped in the source zip).
- **99 new registry entries** appended to `phase-03/id-registry.json` (now 151 total; `registry_version` bumped to 1.1.0).

## How the decomposition was derived (deterministic, no hallucination)
The manuscript uses no heading styles, so structure was detected from its own
consistent conventions: bold `Chapter N.` lines start chapters, and bold
canonical markers (`Warm-Up`, `Why this matters`, `Step 1..8`, `Common mistakes
to avoid`, `Take it further`, `Further reading`) start lesson-sections. See
`decomposition-spec.md`. Every entity records its paragraph `block_index`,
`heading_path`, and the SHA-256 of its normalized DOCX excerpt.

## Verify (offline)
```
node docs/hospitrainity-migration/phase-03/tools/hsp-validate.mjs all --strict
```
The validator is now content-aware: it discovers and validates every package
entity and re-checks each provenance hash against `docx-digest.json`.

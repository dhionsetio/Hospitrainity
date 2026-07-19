# CP-03 Checkpoint Report — Schema, IDs, Lifecycle & Validation Contracts

**Phase:** 3 of 13 · **Status:** Complete · **Learner-facing change:** None (documentation + offline tooling only).

## What this phase delivered
The machine-validatable contract layer for the Hospitrainity canonical curriculum package: JSON Schemas, a deterministic immutable identifier system, controlled vocabularies, lifecycle/versioning rules, canonical serialization rules, and a runnable validator with passing/failing fixtures.

## Inputs (evidence)
- `Hospitrainity.docx` — sole content authority — `sha256 7f8a2c62…2444cbb4`.
- CP-02 framework files (`competencies.json` 7, `outcome-alignments.json` 21, `cefr-references.json` 23) — used as live conformance targets.
- Roadmap §5 (entity hierarchy + stable-ID policy), §6 (interaction taxonomy), §8 (answer/feedback/scoring), §11 (AI operating protocol).

## Outputs (all under `docs/hospitrainity-migration/phase-03/`)
- `schemas/` — 17 JSON Schema (Draft 2020-12) files: `_meta` shared defs; three framework schemas that match the CP-02 files; and content schemas `curriculum-release`, `chapter`, `lesson-section`, `activity`, `prompt-item`, `answer-model`, `feedback-model`, `rubric`, `asset`, `source-provenance`, `alignment-edge`, `migration-edge`, `id-registry`.
- `controlled-vocabularies.json` — 13 enumerations (status, CEFR activity, channel, timing, participation, pedagogical function, response form, feedback type, scoring mode, accessibility, change class, outcome type, legacy exercise types).
- `id-policy.md` + `id-registry.json` — project UUIDv5 namespace `cc9dd546-ebaf-51c7-b86f-307d16a22f42`; 52 registered entries; editorial-code grammar.
- `lifecycle-policy.md`, `serialization-policy.md`, `package-layout.md`, `validator-cli-spec.md`, `README.md`.
- `tools/hsp-validate.mjs` — Node + Ajv 2020 validator (offline).
- `fixtures/` — 2 valid + 5 invalid fixtures + manifest.
- Canonical package skeleton `curriculum/hospitrainity/0.3.0-draft/` (framework files copied verbatim, `package.json` release manifest with checksums, phase dir placeholders).

## Verification (executed in-sandbox)
`node tools/hsp-validate.mjs all --strict` → **8/8 checks passed**:
schema (incl. CP-02 files unchanged), ids (52 unique + UUIDv5-deterministic), refs (all outcome→competency/CEFR resolve), lifecycle, provenance, serialize (28 authored JSON byte-identical to canonical), vocab (schema enums == vocabulary), fixtures (valid pass, invalid fail on the expected check).

## Definition of Done — met
- [x] Valid fixtures pass; deliberately-invalid fixtures fail.
- [x] Duplicate IDs/codes, broken references, illegal status transitions, and changed published IDs all fail CI.
- [x] Schema and content versions are independent and explicit (`schema_version 1.0.0`, `content_version 0.3.0-draft`).
- [x] Existing CP-02 files validate unchanged.
- [x] Checkpoint report + `source-manifest.json` produced with input/output checksums.

## Decisions taken (agent recommendations, per user authorization)
- **DEC-011** — Ratify existing CP-02 codes (`COMP-*`, `OUT-*`, `CEFR-*`) as canonical; `HSP-` grammar for new families from CP-04. Avoids re-approval churn; keeps CP-02 files byte-identical.
- **DEC-012** — Mint a fixed project UUIDv5 namespace from the RFC 4122 URL namespace + `https://hospitrainity.curriculum/id`.
- **DEC-013** — Package baseline `schema_version 1.0.0` / `content_version 0.3.0-draft`.
- **DEC-014** — Validator runtime is Node + Ajv (Draft 2020-12); no PHP/Composer/network needed.

## Limitations
- PHP/Composer and network are unavailable in the sandbox, so validation is Node-based and static; the Laravel test suite still runs in CI. This matches the CP-02 limitation note.
- The three CP-02 framework files are preserved verbatim and are re-canonicalized under the ID system in CP-04 (explicit, reviewable change); they are therefore excluded from the canonical serialize check for now.
- CP-02's qualified ESP/CEFR + hospitality-SME approval gate remains **open**; it does not block CP-03 (contracts only) but still blocks any public CEFR claim and downstream publication.

## Next action
Proceed to **CP-04 — Manuscript decomposition and legacy inventory**: decompose `Hospitrainity.docx` into chapter/lesson/activity entities under the new schemas with `source_locator`s, and build the legacy-content migration map. Awaiting reattached Source.zip + Standalone.html + docx and `Continue`.

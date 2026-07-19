# Phase 06 — References and Standards Register

This register lists the external standards and sources the Hospitrainity curriculum package
actually relies on, with enough bibliographic detail for an editor or SME to verify each claim.
It does **not** introduce new pedagogical content; it documents the normative sources already
embedded in the schema, controlled vocabularies, provenance model, and accessibility posture.

## Content and pedagogy

- **Council of Europe (2020).** *Common European Framework of Reference for Languages: Learning,
  Teaching, Assessment — Companion Volume.* Strasbourg: Council of Europe Publishing.
  Used for: CEFR descriptor references (`cefr-references.json`), the A2–B1 planning hypothesis
  (DEC-009/DEC-010), and the `cefr_activity` communicative-mode vocabulary
  (reception / production / interaction / mediation).
  Status: descriptor alignments remain **hypothesis pending qualified review** (CP-02 gate open).

- **CAST (2024).** *Universal Design for Learning Guidelines, version 3.0.*
  Used for: the accessibility/engagement rationale behind activity `accessibility` flags
  (multiple means of representation, action/expression, engagement).

## Assessment and interoperability (design references, not yet implemented as exports)

- **1EdTech (2022).** *Question and Test Interoperability (QTI) 3.0.*
  Reference model for the `activity` / `prompt-item` / `answer-model` / `feedback-model` / `rubric`
  decomposition; a QTI export is a candidate for a later platform phase, not built in CP-06.

- **1EdTech.** *Experience API (xAPI).* Reference model for future statement-based evidence capture
  (`evidence_conditions` on outcomes). Not implemented in the static package.

- **1EdTech.** *Competencies and Academic Standards Exchange (CASE) 1.1.* Reference model for the
  competency-framework / outcome-alignment linking used by `competency-framework` and
  `outcome-alignments`.

## Data, identity, and versioning

- **JSON Schema, Draft 2020-12.** All package schemas declare 2020-12 and are validated with Ajv.
- **IETF RFC 9562 (2024)** (obsoletes **RFC 4122**). *Universally Unique IDentifiers (UUIDs).*
  Basis for the deterministic UUIDv5 entity ids and the fixed project namespace
  `cc9dd546-ebaf-51c7-b86f-307d16a22f42` (DEC-012).
- **Semantic Versioning 2.0.0.** Independent `schema_version` (1.0.0) and `content_version`
  (0.3.0-draft) tracks (DEC-013).

## Accessibility

- **W3C (2023).** *Web Content Accessibility Guidelines (WCAG) 2.2*, W3C Recommendation,
  5 October 2023. Target conformance **AA**. Governs the accessibility requirements to be
  formalised in CP-07; CP-06 records the target only.

## Provenance

- **Hospitrainity.docx** — the project's sole source of truth for learner-facing content (DEC-001),
  `sha256 7f8a2c62db6884498f7a1c2de56e79e39609262944a685fe7eb97f702444cbb4`. Per-entity normalized
  hashes are recorded in `provenance/docx-digest.json` (structure) and
  `provenance/assessment-digest.json` (assessment).

## Notes on reliability

- Version numbers above reflect the editions relied on by this project. Where a standard has a
  living/rolling status (e.g. UDL, QTI, xAPI), the specific edition should be re-confirmed by the
  SME at ratification.
- No reference here authorizes a published CEFR level claim; that remains gated on qualified review.

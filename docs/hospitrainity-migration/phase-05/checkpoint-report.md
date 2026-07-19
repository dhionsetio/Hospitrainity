# CP-05 Checkpoint Report — Assessment and Feedback Specification

**Date:** 2026-07-15  ·  **Status:** Complete  ·  **Validation:** `hsp-validate all --strict` → 8/8

## What was done

- Extracted the manuscript's Step 5–8 assessment material (Practice, Role-play, Quiz, Confidence
  check) across chapters 2–7 and decomposed it into **336 canonical assessment entities**:
  24 activities, 102 prompt-items, 102 answer-models, 102 feedback-models, 6 role-play rubrics.
- Every entity carries a `Hospitrainity.docx` `source_locator`; all prompt stems, answer keys,
  model answers and per-option feedback are **verbatim from the manuscript** (deterministic
  two-stage python + Node pipeline). No assessment content was invented.
- Added `provenance/assessment-digest.json` (336 normalized SHA-256 entries) and extended the
  validator with assessment reference + provenance checks. `docx-digest.json` and CP-02 framework
  files are untouched.
- Registry 151 → 487 (registry_version 1.1.0 → 1.2.0). Standalone regenerated with CP-05
  provenance metadata only; learner-facing payload unchanged.

## Evidence

- Extraction integrity (all 6 chapters): quiz 8/8 with valid answer keys, practice 4, role-play
  guided 3 (ideal answer identified in each), free 1, confidence present.
- Attribute classification is evidence-based (channel/CEFR/timing per chapter); see `assessment-spec.md`.

## Limitations

- Guided role-play option text and its inline feedback are stored together because the manuscript
  merges them in one paragraph; splitting selectable option from feedback is deferred to CP-11.
- Role-play rubric criteria/levels are **provisional pending SME review** (DEC-020).
- Chapter 1 is an orientation chapter with no Step 5–8 and therefore no assessment entities (DEC-018).
- No CEFR level is published; A2–B1 remains a planning hypothesis (CP-02 human-approval gate open).

## Next action

- **CP-06 — Editorial, references, SME review:** ratify rubric criteria/levels, editorial pass on
  extracted assessment text, and attach external references. Requires the latest artifacts
  reattached + `Continue`.

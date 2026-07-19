# CP-01 — Phase 1 Checkpoint Report

## Outcome
Phase 1 established needs-analysis and curriculum decisions. No learner-facing code, curriculum data, database schema, renderer, or standalone byte changed.

## Changes
- Added migration governance and checkpoint records.
- Defined pre-service learners, exclusions, target needs, and learning needs.
- Added three evidence-bounded personas and a seven-module target inventory.
- Recorded the provisional A2–B1 hypothesis with non-claim guardrails.
- Recorded all binding owner decisions.

## Evidence and validation
- Input hashes are in `source-manifest.json`.
- DOCX baseline: 946 paragraphs, 21 tables, approximately 12,850 paragraph words.
- Documentation/manifest checks: PASS.
- Standalone JS syntax and Chromium offline render: PASS on unchanged input.
- PHP/Laravel tests: NOT RUN; PHP/Composer unavailable.
- Frontend build: NOT RUN; dependency installation unavailable/unreliable; CI remains authoritative.

## Limitations
No direct student diagnostic/survey data; no final CEFR level. Legacy course content, branding, and old hotel references remain because ordered removal/rebrand occurs later. The standalone is re-delivered unchanged and is not yet internally rebranded.

## Next action
Review CP-01, reattach the latest source ZIP, standalone HTML, and DOCX, then say `Continue`. Phase 2 creates the competency framework, local can-do outcomes, official CEFR register, alignment rationales, and reviewer gates.

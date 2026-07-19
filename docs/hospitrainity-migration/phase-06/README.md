# Phase 06 — Editorial, References, SME Review

CP-06 adds a review and quality layer over the canonical package built in CP-03–CP-05, without
changing any learner-facing content. It is a **review phase**: it produces findings, references,
and a sign-off packet, and deliberately leaves approval to qualified humans.

## Deliverables

- `editorial-register.json` — machine-readable output of the deterministic editorial linter
  (`../phase-03/tools/hsp-editorial.mjs`). Scans all 428 canonical content + assessment entities
  for legacy-identity leakage, whitespace/typography hygiene, non-English characters, empty text,
  answer-key resolvability, feedback/rubric integrity, and duplicate stems.
- `references.md` — standards and sources register (CEFR CV 2020, JSON Schema 2020-12, WCAG 2.2,
  UDL 3.0, QTI 3, xAPI, CASE 1.1, SemVer 2.0.0, RFC 9562, plus the DOCX source of truth).
- `sme-review-packet.md` — complete list of items needing ESP/CEFR + hospitality-practitioner +
  editorial sign-off, with a review protocol. All items start PENDING; the agent self-approves none.
- `checkpoint-report.md`, `source-manifest.json` — delivery record and checksums.

## How to reproduce

```bash
cd curriculum's repo root (E-learning-main)
node docs/hospitrainity-migration/phase-03/tools/hsp-editorial.mjs      # regenerates editorial-register.json
node docs/hospitrainity-migration/phase-03/tools/hsp-validate.mjs all --strict   # 8/8
```

## Result

Editorial scan: **0 errors, 0 warnings, 4 info** (all the channel-appropriate `😊` emoji in
Chapter 6). Structural validation remains **8/8**. No content entity was mutated, so all CP-04/CP-05
provenance hashes still verify.

## What CP-06 intentionally does NOT do

- It does not approve the CEFR band, outcome alignments, competency framework, or rubric wording.
- It does not rewrite manuscript-verbatim text; corrections flow back through the DOCX + re-extract
  (DEC-021) so provenance stays intact.
- It does not change the learner-facing standalone payload (only the checkpoint provenance marker).

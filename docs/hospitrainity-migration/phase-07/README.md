# Phase 07 — Accessibility, UDL, Media Requirements

CP-07 is a **requirements-only** phase. It defines the accessibility, Universal Design for Learning
(UDL), and media obligations the future Hospitrainity renderer / authoring platform must satisfy,
and verifies that the existing activity metadata already meets the per-activity portion of those
requirements. It changes no learner content and mutates no package entity.

## Deliverables

- `accessibility-udl-spec.md` — normative requirements spec: WCAG 2.2 AA success criteria + UDL 3.0
  principles, the accessibility-flag → success-criterion crosswalk, baseline vs conditional rules,
  and platform-level criteria for the renderer.
- `media-requirements.md` — per-media-type obligations (image/audio/video/document) and where media
  may be required per chapter/activity, plus the authoring gate.
- `a11y-requirements.json` — machine-readable derived requirement matrix for all 24 activities
  (required affordances + WCAG SCs + UDL principles + media implications).
- `a11y-conformance.json` — declared-vs-required check for all 24 activities.
- `../phase-03/tools/hsp-a11y.mjs` — the deterministic, read-only tool that generates both JSON files.
- `checkpoint-report.md`, `source-manifest.json` — delivery record and checksums.

## How to reproduce

```bash
# from repo root (E-learning-main)
node docs/hospitrainity-migration/phase-03/tools/hsp-a11y.mjs                     # regenerates a11y-*.json
node docs/hospitrainity-migration/phase-03/tools/hsp-validate.mjs all --strict    # 8/8
```

## Result

Accessibility conformance: **24 activities, 24 conform, 0 gaps** — the declared `accessibility`
flags already satisfy the derived WCAG 2.2 AA baseline plus the conditional transcript rule.
Structural validation remains **8/8**; no content entity was modified, so all CP-04/CP-05
provenance hashes still verify.

## What CP-07 intentionally does NOT do

- It does not author any media assets (`assets/` stays empty); it only specifies their obligations.
- It does not implement the renderer or audit it against platform-level WCAG criteria (CP-10/CP-12).
- It does not change learner content or the standalone payload (only the checkpoint marker).

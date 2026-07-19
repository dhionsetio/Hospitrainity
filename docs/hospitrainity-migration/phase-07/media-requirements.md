# Phase 07 — Media Asset Requirements

**Status:** Requirements only. No media assets are produced or added in CP-07. This document
specifies what any future media asset MUST satisfy, and which activities may require media, so the
authoring/renderer phases (CP-08+) have unambiguous acceptance criteria. The `asset` schema
(media_type ∈ {image, audio, video, document}; `alt_text`; `checksum`) already exists; the
`assets/` package directory is currently empty by design.

## 1. Per-media-type obligations (WCAG 2.2 AA)

| media_type | Mandatory affordances |
|---|---|
| `image` | `alt_text` populated (WCAG 1.1.1); never the sole carrier of meaning (1.4.1); decorative images marked empty-alt. |
| `audio` | Text transcript (1.2.1); keyboard-operable player with visible controls (2.1.1); no autoplay > 3s or user-controlled (1.4.2). |
| `video` | Captions (1.2.2); audio description or full text alternative (1.2.3 / 1.2.5); keyboard-operable controls (2.1.1). |
| `document` | Tagged/structured with headings and correct reading order (1.3.1); language identified (3.1.1). |

Every asset entity MUST also carry a `checksum` and a `source_locator`, so media provenance is as
verifiable as text provenance (consistent with DEC-001 / DEC-017).

## 2. Where media may be required (derived, requirements-only)

The accessibility tool (`hsp-a11y.mjs`) derives per-activity media implications:

- **Spoken / telephone role-plays** — `HSP-C02/C03/C04/C07-ACT-ROLEPLAY` (channel spoken_ftf or
  telecommunications, response_form role_play). *If* a recorded model turn is provided as `audio`,
  it MUST ship an accompanying transcript. These activities already declare the `transcript` flag,
  so the requirement is pre-satisfied at the metadata level.
- **Social / public-reply chapter** — `HSP-C06-*` (channel social_public_reply). *If* screenshots of
  posts or messages are shown as `image`, each MUST carry `alt_text` conveying the post content.
- **Written-correspondence chapter** — `HSP-C05-*` produces `service_artifact` responses (emails,
  written replies); no time-based media is implied, so no transcript/caption obligation.
- **All other activities** (quiz/practice/confidence) — text-only interaction; no media obligation
  unless an author later attaches media, at which point §1 applies.

See `a11y-requirements.json` → `media_asset_requirements` for the machine-readable per-activity list.

## 3. Authoring gate

When media is authored in a later phase, the build MUST:
1. Create an `asset` entity per file with `media_type`, `filename`, `checksum`, and the affordances
   in §1 (e.g. `alt_text` for images, a linked transcript `document` for audio).
2. Re-run `hsp-a11y.mjs`; every activity that references media must remain `conform`.
3. Re-run `hsp-validate all --strict` → 8/8, including provenance for the new asset entities.

## 4. Limitations

- Because no media exists yet, §1 obligations are latent; they cannot be auto-verified until assets
  are added. CP-07 records the contract; enforcement lives in the authoring/QA phases.
- Alt-text and caption *quality* (accuracy, not mere presence) requires human review and is added to
  the SME packet scope at authoring time.

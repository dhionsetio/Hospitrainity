# CP-10 Checkpoint Report — Renderer Implementation

**Date:** 2026-07-15  ·  **Status:** Complete  ·  **Validation:** `hsp-validate all --strict` → 8/8

## What was done

- Implemented a **deterministic, read-only renderer** (`tools/hsp-render.mjs`) that loads the CP-09
  compiled canonical store (`phase-09/curriculum.sql`) into Node's built-in `node:sqlite` and
  projects it into a single dependency-free standalone HTML app. No third-party dependency, no
  network, no build step.
- Added the accessible app shell (`tools/render-template.html`): a vanilla-JS hash-router SPA with
  home / chapter / activity / outcomes / about routes, an inline `application/json` data island,
  and CP-07 accessibility affordances (skip link, landmarks, `aria-current`, keyboard operability,
  non-color badges, `prefers-reduced-motion`).
- **Retired the frozen legacy `window.STAYREADY` payload.** The standalone is now a projection of
  the canonical 7-chapter, English-only, DOCX-derived curriculum — 7 modules, 85 lesson sections,
  24 activities, 102 prompts (with model answers + feedback), 6 role-play rubrics, and 21 draft
  outcomes — every screen traceable to `Hospitrainity.docx`.
- Emitted `phase-10/view-model.json` (the canonical projected model) and `phase-10/render-report.json`
  (counts + integrity + output sha, no timestamp so the build stays deterministic).
- Verified the generated app actually paints (headless Chromium DOM dump: 7 module cards, chapter
  titles present, 0 occurrences of the banned hotel identity).
- Updated CHECKPOINTS (CP-10 → Complete), DECISIONS (DEC-032, DEC-033), and CHANGELOG; the standalone
  checkpoint marker is now CP-10.

## Chosen technique and why

**Deterministic read-only projection into a single self-contained HTML file.** The renderer sorts
every collection by a stable key and emits no timestamps or random ids, so identical inputs give
byte-identical output (verified: two runs → same sha `16a93e2b…`). A single inline data island +
vanilla JS keeps the deliverable offline-capable and dependency-free, matching the project
constraint that only Node + static assets are available (no PHP/Composer). Rendering *from the
compiled store* (rather than re-reading raw JSON files) proves the CP-08/CP-09 storage projection is
sufficient to drive the learner experience.

## Evidence

- `hsp-render.mjs` exits non-zero unless all data-model invariants hold. Current run:
  `render.ok=true`, counts `{chapters:7, sections:85, activities:24, prompts:102, outcomes:21}`,
  integrity `{orphan_activities:0, unplaced_prompts:0, prompts_with_answer_model:102,
  prompts_with_model_answer_text:90, prompts_with_feedback:102, rubrics:6, youkata:0}`.
- Determinism: two independent renders produced identical sha256
  `16a93e2b49cb30b10e38e914a018b5b3d4836b66578f150a3b45216eb0939e7a`.
- No mutation: `curriculum/` diffed **byte-identical** to the CP-09 Source.zip; the only changes vs
  CP-09 are additions (`hsp-render.mjs`, `render-template.html`, `phase-10/`).
- Structural validation unchanged at **8/8**; CP-02 framework files, both provenance digests, and
  the id-registry (487, registry_version 1.2.0) are byte-identical to CP-09.

## Corrected during this checkpoint

- An initial self-check assumed *all 102* prompts would carry model-answer text. The renderer's own
  guard caught the mismatch (90, not 102). Investigation of the store confirmed **12** open prompts
  (6 self-rating, 4 role-play, 2 service-artifact) legitimately have an empty `accepted[]` and are
  scored by rubric/self-assessment (DEC-019/DEC-020). The invariant was corrected to the true shape
  (102 answer-model records, 90 with model-answer text) rather than forcing a wrong number.

## Limitations

- The display title remains **StayReady — Hospitality English**; the StayReady → Hospitrainity
  rebrand is intentionally deferred to CP-13 per the roadmap.
- Outcomes render as **draft** with **provisional** A2–B1 CEFR bands. The **CP-02 human-approval
  gate remains open**: no certified CEFR level and no publication until it is closed.
- The renderer projects existing canonical content; it authors none. Seven-module
  authoring/approval workflow is CP-11.
- The compiled `.db` is a disposable build artifact and is not shipped; the shipped inputs are the
  deterministic `curriculum.sql` load script and the generated standalone.

## Next action

Proceed to **CP-11 — Seven-module authoring/approval workflow**, then CP-12 (integrated QA / pilot
readiness) and CP-13 (release, legacy removal, StayReady → Hospitrainity rebrand, rollback). CP-02
approval must close before any CEFR claim or publication.

# CP-10 Renderer Specification

**Tool:** `docs/hospitrainity-migration/phase-03/tools/hsp-render.mjs`
**Template:** `docs/hospitrainity-migration/phase-03/tools/render-template.html`
**Engine:** Node built-in `node:sqlite` (`--experimental-sqlite`) — no third-party dependency, no network.

## Purpose

CP-10 replaces the frozen legacy learner payload with a **generated projection** of the canonical
curriculum. Before CP-10 the standalone shipped a hand-frozen `window.STAYREADY` blob (8 legacy
modules, mixed EN/ID, legacy hotel identity). From CP-10 onward the standalone is **produced by the
renderer from the CP-09 compiled canonical store**, so what learners see is exactly what the DOCX
source of truth compiled to — nothing is authored in the standalone by hand.

## Contract

1. **Read-only projection.** The renderer loads `phase-09/curriculum.sql` into an in-memory
   `node:sqlite` database and only reads it. It never writes to the store and never mutates any
   canonical package file (verified by recursive diff of `curriculum/` against CP-09).
2. **Deterministic.** Given the same compiled store + template, the renderer produces byte-identical
   HTML. All collections are sorted by a stable key (`module`, then entity `code`/`id`); no
   timestamps, no random ids, no locale-dependent formatting are emitted.
3. **Single file, dependency-free.** Output is one self-contained `.html`: inline CSS, inline
   vanilla JS, and the curriculum injected as an inline `application/json` data island
   (`<script id="hsp-data" type="application/json">`). No build step, no CDN, works offline.
4. **Provenance-marked.** The output carries `<meta name="hospitrainity-checkpoint" content="CP-10">`
   and an HTML provenance comment naming the source artifact and entity counts.

## View model

The renderer builds one canonical view model (also emitted as `phase-10/view-model.json`):

- `meta` — product `Hospitrainity`; display `title` `StayReady — Hospitality English` (rebrand
  deferred to CP-13, DEC-004/roadmap); `content_version` `0.3.0-draft`; `status` `draft`;
  `checkpoint` `CP-10`; `source_artifact`; `generated_from`; and a `notice` + `disclaimer` stating
  the CEFR bands are provisional hypotheses pending the CP-02 human-approval gate.
- `stats` — `{chapters:7, sections:85, activities:24, prompts:102, outcomes:21}`.
- `outcomes[]` — 21 draft outcomes from `framework_document(kind='outcome-alignments')`
  (`id, module, statement, type, provisional_band`).
- `chapters[]` (7, ordered by module) — each with `code, module, title, outcome_codes[]`,
  `sections[]` (ordered by `order` then `code`), and `activities[]` (grouped to the chapter by
  `lesson_code.slice(0,7)`, ordered by `code`).
  - each activity carries its pedagogical metadata badges (`cefr_activity, channel, participation,
    pedagogical_function, response_form, scoring_mode, timing, outcome_codes[], accessibility[]`),
    its `prompts[]`, and a `rubric` (role-play only) or `null`.
  - each prompt carries `code, stem, response_form, answers[]` (accepted model-answer text) and
    `feedback[]` (`feedback_type, text`).

## Routes (hash-router SPA)

- `#/` — home: hero + seven module cards.
- `#/chapter/<CODE>` — chapter outcomes + lesson sections + activities.
- `#/activity/<CODE>` — activity metadata badges; prompts with a `<details>` reveal of the model
  answer + feedback (labelled *Guidance & feedback* when the prompt has no model-answer text); a
  rubric table for role-play activities.
- `#/outcomes` — the 21 draft outcomes.
- `#/about` — provenance, versioning, and the CEFR disclaimer.

## Accessibility (per CP-07 / DEC-023)

Skip link, semantic landmarks (`header/nav/main`), `aria-current` on the active nav item, full
keyboard operability, visible focus, non-color status badges, and `prefers-reduced-motion` support.
The renderer inherits the WCAG 2.2 AA + UDL 3.0 target ratified in CP-07.

## Data-model invariants asserted at render time

The renderer exits non-zero (fails the build) unless all hold, grounded in the actual store:

- counts: chapters 7, sections 85, activities 24, prompts 102, outcomes 21;
- **no orphan activity** (every activity's `lesson_code` prefix is a real chapter) and
  **no unplaced prompt** (every prompt is rendered under an activity);
- **102/102** prompts have an answer-model record and **102/102** have a feedback-model record;
- **90/102** prompts carry model-answer text — the other **12** are open prompts scored by rubric or
  self-assessment (**6** self-rating, **4** role-play, **2** service-artifact) whose answer-model has
  an empty `accepted[]` by design (see DEC-019/DEC-020); this is verified, not assumed;
- **6** role-play rubrics rendered;
- **zero** occurrences of the banned legacy hotel identity (`Youkata`, case-insensitive) in output;
- both template tokens fully substituted and the injected JSON parses.

## Run

```
node --experimental-sqlite docs/hospitrainity-migration/phase-03/tools/hsp-render.mjs \
  --out /path/to/Hospitrainity-Standalone.html \
  [--sql docs/hospitrainity-migration/phase-09/curriculum.sql] \
  [--template docs/hospitrainity-migration/phase-03/tools/render-template.html]
```

Side artifacts written to `phase-10/`: `view-model.json` (canonical) and `render-report.json`
(counts + integrity + output sha; no timestamp, to preserve determinism).

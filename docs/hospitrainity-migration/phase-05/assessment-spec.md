# Phase 05 — Assessment and Feedback Specification

## Purpose

CP-05 turns the manuscript's own practice material into a canonical, machine-checkable
assessment layer. Every learner-facing check in the source book — Practice (Step 5),
Role-play (Step 6), Quiz (Step 7) and Confidence check (Step 8) — is decomposed into
typed entities (`activity`, `prompt-item`, `answer-model`, `feedback-model`, `rubric`)
defined by the CP-03 schemas, with a Hospitrainity.docx `source_locator` on every entity.

No assessment content is invented: prompt stems, answer keys, model answers and
per-option feedback are extracted verbatim from the manuscript. Only attribute
classifications (CEFR mode, channel, timing, scoring mode, etc.) and provisional rubric
criteria are authored, and each is traced to explicit evidence or recorded as a decision.

## Scope

| Chapter | Module channel | Assessed steps |
|---|---|---|
| 1 Welcome and Introduction to Customer Care | — | none (orientation chapter, no Step 5-8; DEC-018) |
| 2 Front Desk and Check-In | spoken_ftf | Practice, Role-play, Quiz, Confidence |
| 3 Dealing with Customers on the Phone | telecommunications | Practice, Role-play, Quiz, Confidence |
| 4 The Guest-Services Hotline | telecommunications | Practice, Role-play, Quiz, Confidence |
| 5 Customer Care Through Writing | written_correspondence | Practice, Role-play (drafting), Quiz, Confidence |
| 6 Online and Social Media Customer Service | social_public_reply | Practice, Role-play, Quiz, Confidence |
| 7 Dealing with Problems and Complaints | spoken_ftf | Practice, Role-play, Quiz, Confidence |

## Entity model (per assessed chapter)

- **4 activities**: `HSP-C0N-ACT-QUIZ`, `-ACT-PRACTICE`, `-ACT-ROLEPLAY`, `-ACT-CONFIDENCE`.
  Each activity's `lesson_code` points to the matching CP-04 lesson-section (Step 5-8) and
  reuses that section's `source_locator`; `outcome_codes` mirror the chapter's module outcomes.
- **17 prompt-items**: quiz `-QZ-Q1..Q8`, practice `-PR-I1..I4`, role-play guided `-RP-G1..G3`,
  role-play free `-RP-FREE`, confidence `-CC-SELF`.
- **17 answer-models** (`<prompt>-AM`) and **17 feedback-models** (`<prompt>-FB`), one per prompt.
- **1 rubric**: `HSP-C0N-RP-RUBRIC` for the role-play activity.

Totals: 4 activities + 17 prompts + 17 answer-models + 17 feedback-models + 1 rubric = **56 per chapter**
× 6 chapters = **336 entities**. Registry: 151 → 487 (registry_version 1.1.0 → 1.2.0).

## Attribute classification (evidence-based)

- **Quiz** → cefr_activity `reception`, response_form `selection`, scoring `objective_exact`
  (answer key = the manuscript's stated correct letter), pedagogical_function `formative_check`.
- **Practice** → cefr_activity `production`, item-level response_form detected from the prompt
  (`short_text` for fill-blank / rewrite, `selection` for choose-the-option, `ordering` for
  sequencing), scoring `objective_normalized` (accepted = the manuscript's model answer).
- **Role-play** → scoring `rubric`, pedagogical_function `freer_task`, participation `staff_guest`.
  Guided turns are `selection` prompts whose answer key is the manuscript's flagged "ideal answer";
  each option's full text + inline feedback is preserved verbatim in the feedback-model.
  Free-mode task is `role_play` for spoken/phone chapters and `service_artifact` for written/social
  chapters (Ch5, Ch6); timing is `synchronous` for spoken/phone and `asynchronous` for written/social;
  spoken chapters add the `transcript` accessibility flag.
- **Confidence check** → self_rating / `self_report` / `reflection`; inherits the chapter's primary
  CEFR mode as the reflection target (DEC-019). No correct answer; feedback is the manuscript's
  next-step instruction.
- Base accessibility on every activity: `text_alt`, `keyboard_path`, `non_color_cue`, `no_timing_dependency`.

## Rubric criteria (provisional — DEC-020)

Four criteria per role-play, each with a 3-level scale (Not yet / Developing / Strong):
Warmth, Solution, Politeness, Progression. These dimensions are derived directly from the
manuscript's own evaluative feedback language (e.g. warm welcome, offering a solution, softened
requests such as "Could I ... please", moving calmly to the next step). Level descriptors and
wording are provisional and are flagged for ESP/CEFR + hospitality-practitioner review in CP-06.

## Provenance and determinism

- **Stage A** (`extract_assessment.py`, python-docx): parses Step 5-8 by bold-run + regex markers,
  emits `assessment-decomposition.json` with the block index and normalized SHA-256 of each
  extracted paragraph. Normalization matches CP-04 (collapse whitespace per paragraph, join
  non-empty lines with `\n`, sha256).
- **Stage B** (`cp05-build.mjs`, Node): reads the decomposition + CP-04 lesson-sections + id-registry,
  emits all 336 canonical entities (sorted keys, 2-space indent, LF, trailing newline), appends the
  registry with deterministic UUIDv5 ids, and writes `provenance/assessment-digest.json`.
- The validator re-checks every assessment entity's `source_locator` hash against
  `assessment-digest.json`, so provenance is verifiable offline without shipping the DOCX.
- CP-04's `docx-digest.json` is not modified; CP-02 framework files stay byte-identical.

## Validation

`node docs/hospitrainity-migration/phase-03/tools/hsp-validate.mjs all --strict` → **8/8**
(schema, ids, refs, lifecycle, provenance, serialize, vocab, fixtures). Assessment coverage adds:
- refs: activity→lesson-section + outcomes, prompt→activity, answer/feedback→prompt, rubric→activity;
- provenance: 336 assessment entities re-hashed against assessment-digest.json.

## Limitations / follow-ups

- Guided role-play option text and its inline feedback are stored together (the manuscript merges
  them in one paragraph); splitting the selectable option from its feedback is deferred to authoring (CP-11).
- Rubric level descriptors are provisional pending SME review (DEC-020).
- No CEFR level is published; A2-B1 remains a planning hypothesis (CP-02 gate open).

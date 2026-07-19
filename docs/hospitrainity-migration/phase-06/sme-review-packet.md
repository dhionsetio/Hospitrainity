# Phase 06 — SME / Editorial Review Packet

**Purpose:** give qualified reviewers a single, complete list of everything in the Hospitrainity
canonical package that requires expert human sign-off, plus the protocol for recording decisions.

**Hard rule:** the AI agent does **not** self-approve any of the items below. Every row starts at
`PENDING` and can only move to `APPROVED` / `CHANGES-REQUESTED` by a named human reviewer. The
CP-02 human-approval gate remains **OPEN** and continues to block any published CEFR level claim.

## Required reviewer roles

1. **ESP / CEFR specialist** — language-level and descriptor alignment.
2. **Hospitality-practitioner reviewer** — domain accuracy of scenarios, answer keys, register.
3. **Instructional editor** — clarity, consistency, and typographic polish of learner-facing text.

## Review items

| Ref | Item | Scope | Evidence to check | Decision (DEC) | Status |
|---|---|---|---|---|---|
| SME-01 | CEFR band hypothesis (A2–B1) | Whole course | `cefr-references.json`, CEFR CV 2020 | DEC-009 | PENDING |
| SME-02 | Outcome→CEFR alignments | 21 outcomes | `outcome-alignments.json` cefr_reference_ids | DEC-010 | PENDING |
| SME-03 | Competency framework | 7 families | `competencies.json` | CP-02 gate | PENDING |
| SME-04 | Role-play rubric criteria + level descriptors | 6 rubrics (24 criteria) | `assessment/**/rubrics/*.json` | DEC-020 | PENDING |
| SME-05 | Quiz answer keys | 48 quiz items (C2–C7) | answer-models `objective_exact` vs manuscript | — | PENDING |
| SME-06 | Practice model answers | 24 practice items | answer-models `objective_normalized` | — | PENDING |
| SME-07 | Role-play “ideal answer” selections | 18 guided turns | guided answer-models vs manuscript flag | — | PENDING |
| SME-08 | Feedback tone / correctness | 102 feedback-models | verbatim manuscript feedback | — | PENDING |
| SME-09 | Legacy dispositions | 8 migration edges | `provenance/migration/*` | DEC-005/015 | PENDING |
| SME-10 | Editorial findings disposition | see editorial-register.json | 4 info (Ch6 emoji) | DEC-022 | PENDING |

## Editorial findings summary (from `hsp-editorial.mjs`)

The deterministic editorial scan over all 428 canonical content + assessment entities reported
**0 errors, 0 warnings, 4 info**. All four info items are the emoji `😊` inside Chapter 6 (Online
and Social Media Customer Service) content — verbatim from the manuscript and appropriate to that
channel. Recommended disposition: **accept as-is** (channel-appropriate, faithful to source).
See `editorial-register.json` for the machine-readable detail.

## Provenance-safe editorial policy (DEC-021)

Because `Hospitrainity.docx` is the sole source of truth (DEC-001) and every content/assessment
entity is hash-locked to it, CP-06 does **not** silently rewrite learner-facing text. Any editorial
correction an SME approves must be applied to the DOCX source and then re-decomposed (re-running the
CP-04/CP-05 extractors), so provenance hashes stay verifiable. CP-06 therefore ships *findings and
recommendations*, not content mutations.

## Review protocol

1. Reviewer works top-to-bottom through the table, recording `APPROVED` or `CHANGES-REQUESTED`
   with initials + date in their copy of this packet.
2. `CHANGES-REQUESTED` items that touch learner text are logged for a DOCX source edit + re-extract.
3. When SME-01..03 are approved, update the CP-02 gate note in `CHECKPOINTS.md` and only then may a
   CEFR level be stated publicly.
4. Ratified rubric wording (SME-04) is applied in a subsequent content_version bump, not in-place.

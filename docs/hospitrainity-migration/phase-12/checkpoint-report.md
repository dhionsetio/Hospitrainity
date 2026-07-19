# CP-12 Checkpoint Report — Integrated QA & Pilot Readiness

**Date:** 2026-07-15 · **Status:** Complete (public-release gate held open by design)

## Objective

Prove the whole migration pipeline agrees end-to-end and decide, deterministically,
whether the build is ready to pilot — without authoring or mutating any canonical
learner content.

## What was done

1. **Built an integrated QA harness** (`phase-03/tools/hsp-qa.mjs`, offline,
   deterministic). It cross-checks the id registry, the compiled canonical store,
   the rendered view-model, the render report, the CP-11 workflow ledgers, the
   provenance digests and the rendered DOM, and delegates to the three existing
   validators (structural, accessibility, editorial). One command is now the
   whole quality gate. See `qa-spec.md`.
2. **Ran the gate: 11/11 checks pass** (see `qa-report.json`).
3. **Issued a deterministic pilot-readiness verdict** (`pilot-readiness.json`):
   `READY-FOR-INTERNAL-PILOT / HOLD-FOR-PUBLIC-RELEASE`.
4. **Regenerated the standalone at checkpoint CP-12** and added a *Quality
   assurance & pilot readiness* governance note to the About page. Render is
   deterministic (identical SHA-256 on two runs).

## Findings & fixes

The integrated QA found **zero defects**. Every layer already agreed. Per the
anti-hallucination protocol, no content “fix” was invented; the learner-facing
delta is limited to the checkpoint marker and the About governance note.

One process correction was made to the harness itself during development: the QA
reads render outputs (`view-model.json`, `render-report.json`) from `phase-12`
(the current render) while reading the unchanged workflow ledgers from
`phase-11`, so it always audits the build it is shipping.

## Evidence

- Structural validation: 8/8 (`--strict`).
- Accessibility: 24/24 activities conform to WCAG 2.2 AA + UDL, 0 gaps.
- Editorial: 428 entities, 0 errors, 0 banned tokens in content.
- Counts: 7 chapters / 85 sections / 24 activities / 102 prompts / 21 outcomes; `youkata: 0`.
- Determinism: render #1 == render #2 == delivered standalone.
- DOM paint proof via headless Chromium (badges, governance table, landmarks, skip link).

## Limitations

- **Public release is held.** All 7 modules are in `review`; none is approved.
  The CP-02 human-approval gate remains **open**, which blocks publication and
  any certified CEFR claim. Public-release blockers: `modules_approved`,
  `cp02_gate_closed`.
- CEFR bands remain provisional working hypotheses (A2–B1); not certified.
- Display title is still “StayReady — Hospitality English”; the StayReady →
  Hospitrainity rebrand is deferred to CP-13.
- QA is an internal consistency + conformance gate; it does not substitute for
  human pedagogical review or a real learner pilot.

## Next action

**CP-13 — Release, legacy removal, rebrand, rollback:** final cutover, StayReady →
Hospitrainity rebrand, legacy artifact removal, and a rollback plan — executed
only after the CP-02 gate closes and the per-module human sign-offs are recorded.

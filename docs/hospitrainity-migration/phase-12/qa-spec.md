# Phase 12 — Integrated QA & Pilot Readiness

CP-12 adds an **integrated quality-assurance harness** that treats the whole
migration pipeline as one system and asserts that every tool agrees
end-to-end, then produces a **deterministic pilot-readiness go/no-go**.

Nothing in CP-12 authors or mutates canonical learner content. The learner-facing
change for this phase is *fixes only*; the integrated QA run found **zero
defects**, so no content fix was required and none was fabricated (anti-hallucination:
see Notes 1–2 of the migration protocol). The only rendered change is the
checkpoint marker (CP-11 → CP-12) and a *Quality assurance & pilot readiness*
governance note on the About page.

## Tool: `phase-03/tools/hsp-qa.mjs`

Deterministic, offline, no third-party dependency. Reads the id registry, the
compiled canonical store (`phase-09/curriculum.sql` via `node:sqlite`), the
rendered view-model and render report (`phase-12/`), the CP-11 workflow ledgers
(`phase-11/`), the provenance digests, and the rendered standalone. It also
**delegates** to the existing validators so a single command is the whole gate.

```
node --experimental-sqlite phase-03/tools/hsp-qa.mjs \
     --standalone phase-12/Hospitrainity-Standalone.html [--no-dom]
```

Outputs (canonical JSON, sorted keys, LF, single trailing newline):
- `phase-12/qa-report.json` — every check with pass/fail + evidence.
- `phase-12/pilot-readiness.json` — criteria + deterministic verdict.

Exit code `0` iff all checks pass.

## The 11 integrated checks

| ID | What it proves |
|---|---|
| A_registry_totals | Registry inventory totals: 487 entities, 428 authored (`review`), 59 excluded (`draft`). |
| B_store_vs_registry | Each compiled store table row count equals the registry count for its entity type (8 tables). |
| C_store_vs_viewmodel | Every chapter/section/activity/prompt/outcome code in the store appears in the rendered view-model, and there are no extras (7 / 85 / 24 / 102 / 21). |
| D_render_report | Render-report counts and integrity block agree with the store (orphan_activities 0, unplaced_prompts 0, answer-models 102, feedback 102, model-answer text 90, rubrics 6) and `youkata: 0`. |
| E_link_integrity | Every activity resolves under its declared module (code prefix `HSP-C0x`). |
| F_lifecycle_workflow | Lifecycle is consistent across registry, workflow-state, approval-ledger and workflow-report: all authored `review` + `BLOCKED`, gate `open`, all sign-offs unsigned, 0 approved. |
| G_determinism | Two independent renders produce identical SHA-256, equal to the delivered standalone. |
| H1_validate | Delegated structural validation is 8/8 (`--strict`). |
| H2_a11y | Delegated accessibility audit: 24/24 activities conform to WCAG 2.2 AA + UDL, 0 gaps. |
| H3_editorial | Delegated editorial scan: 0 errors, no banned legacy identity token in canonical content. |
| I_dom_paint | Headless-Chromium proof that the SPA actually paints: chapter cards, `In review` + `Approval: pending` badges, About workflow table + open gate, outcomes list, activity prompts, `#main` landmark, skip link, `html lang`, nav `aria-current`; `youkata` absent from content. |

## Pilot readiness (deterministic go/no-go)

`pilot-readiness.json` scores explicit criteria and separates two gates:

- **Internal-pilot criteria** (all met): QA all green, deterministic build,
  structural validity 8/8, accessibility 0 gaps, no banned terms.
- **Public-release criteria** (intentionally NOT met): all 7 modules approved by
  qualified human reviewers, and the CP-02 human-approval gate closed.

**Verdict: `READY-FOR-INTERNAL-PILOT / HOLD-FOR-PUBLIC-RELEASE`.** Technical QA
is green, so the build is suitable for an internal review pilot. Public release
is held because every module is in `review`, none is approved, and the CP-02
gate is open. Releasing now would present un-approved content and provisional
CEFR bands (A2–B1 hypothesis) as final, which is not permitted. This verdict is
derived from the workflow gate and QA results, not asserted by hand.

## Why this is trustworthy, not hand-waved

- The registry is the **authoritative inventory**; every count is cross-checked
  against it rather than hard-coded.
- Parity is proven at three layers: store ↔ registry, store ↔ view-model, and
  view-model ↔ painted DOM. Because the standalone is a deterministic projection
  of the store (DEC-032), store↔view-model parity plus the DOM paint proof shows
  the learner sees exactly the canonical content.
- The QA harness re-runs the real validators; it does not re-implement or
  approximate their judgial checks.

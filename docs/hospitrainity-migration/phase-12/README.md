# Phase 12 — Integrated QA & Pilot Readiness (CP-12)

This phase composes the whole migration pipeline into a single, deterministic
quality gate and issues a pilot-readiness verdict. It does not author or change
canonical learner content.

## Contents

- `qa-spec.md` — the QA harness design and the 11 integrated checks.
- `qa-report.json` — machine-readable result of the last run (11/11 pass).
- `pilot-readiness.json` — criteria + deterministic go/no-go verdict.
- `view-model.json` / `render-report.json` — CP-12 render outputs (checkpoint marker CP-12).
- `checkpoint-report.md` — narrative report for this checkpoint.
- `source-manifest.json` — SHA-256 manifest of the artifacts this phase touched.

## Run it

```
# from E-learning-main/docs/hospitrainity-migration
node --experimental-sqlite phase-03/tools/hsp-render.mjs \
     --out phase-12/Hospitrainity-Standalone.html            # deterministic render (reports -> phase-12)
node --experimental-sqlite phase-03/tools/hsp-qa.mjs \
     --standalone phase-12/Hospitrainity-Standalone.html      # integrated QA + pilot readiness
```

Exit code `0` means every check passed.

## Result

- **QA: 11/11 checks pass.**
- **Pilot verdict: READY-FOR-INTERNAL-PILOT / HOLD-FOR-PUBLIC-RELEASE.**
  - Internal-pilot ready: yes (green technical QA).
  - Public-release ready: no — blockers `modules_approved`, `cp02_gate_closed`.

## Guarantees preserved

- DOCX remains the sole source of truth (DEC-001); no content mutated.
- Provenance digests unchanged (they hash DOCX text, not lifecycle status).
- Standalone is a deterministic read-only projection of the compiled store (DEC-032).
- Approval remains a human act; the CP-02 gate stays open (DEC-011 / DEC-034).

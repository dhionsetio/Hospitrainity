# Phase 13 — Release, Legacy Removal, Rebrand, Rollback (CP-13, FINAL)

This is the final checkpoint of the Hospitrainity curriculum migration. It
closes out branding, documents (without executing) legacy-stack removal, and
ships a rollback plan — without authoring or mutating any canonical learner
content, and without closing the CP-02 human-approval gate (that remains an
exclusively human act).

## Contents

- `rebrand-report.md` — every StayReady → Hospitrainity edit made, file by
  file, plus what was deliberately left unchanged (frozen historical
  artifacts) and why.
- `legacy-removal-report.md` — inventory of the legacy Laravel course-
  delivery stack; a sourced decision to recommend, not execute, its
  decommission; and the recommended future path.
- `rollback-plan.md` — three independent rollback procedures (rebrand,
  checkpoint/tooling, content-level) with go/no-go checks for each.
- `Hospitrainity-Standalone.html` — CP-13 deterministic render (rebrand
  applied; content unchanged from CP-12).
- `view-model.json` / `render-report.json` — CP-13 render outputs (checkpoint
  marker CP-13).
- `qa-report.json` / `pilot-readiness.json` — CP-13 integrated QA run
  (11/11) and pilot-readiness verdict.
- `checkpoint-report.md` — narrative report for this checkpoint.
- `source-manifest.json` — SHA-256 manifest of every artifact this phase
  touched.

## Run it

```
# from E-learning-main/docs/hospitrainity-migration
node --experimental-sqlite phase-03/tools/hsp-render.mjs \
     --out phase-13/Hospitrainity-Standalone.html            # deterministic render (reports -> phase-13)
node --experimental-sqlite phase-03/tools/hsp-qa.mjs \
     --standalone phase-13/Hospitrainity-Standalone.html      # integrated QA + pilot readiness
```

Exit code `0` means every check passed.

## Result

- **Rebrand complete** on every live/current-facing surface (app strings,
  tests, seeders, rendered standalone, current tooling defaults). Frozen
  prior-checkpoint snapshots and the CP-06 banned-term historical entry are
  intentionally untouched — see `rebrand-report.md`.
- **QA: 11/11 checks pass.** Determinism preserved end to end (identical
  sha256 across two renders and the delivered standalone).
- **Pilot verdict: READY-FOR-INTERNAL-PILOT / HOLD-FOR-PUBLIC-RELEASE**
  (unchanged from CP-12 — rebrand and documentation do not affect the
  underlying gate state).
  - Internal-pilot ready: yes.
  - Public-release ready: no — blockers `modules_approved`, `cp02_gate_closed`.
- **Legacy Laravel stack:** inventoried and documented; decommission is
  recommended but **not executed** — no PHP/Composer runtime is available in
  this sandbox to verify safety, and no learner-progress data migration plan
  exists yet. See `legacy-removal-report.md`.
- **Rollback plan published** covering rebrand, checkpoint/tooling, and
  content-level scenarios, each with an explicit go/no-go check.

## Guarantees preserved

- DOCX remains the sole source of truth (DEC-001); no canonical content
  mutated in this phase.
- Provenance digests unchanged (they hash DOCX text, not display strings).
- Standalone is a deterministic read-only projection of the compiled store
  (DEC-032).
- Approval remains a human act; the CP-02 gate stays open (DEC-011/DEC-034).
  This checkpoint does **not** claim public release or certified CEFR status.

## Known limitations carried into this final checkpoint

- CP-02 human-approval gate is **open**; 0 of 7 modules are approved. Public
  release stays held.
- CEFR bands remain provisional (A2–B1 hypothesis), not certified.
- Legacy Laravel application is not decommissioned (documented, not
  executed — see `legacy-removal-report.md`).
- The 4 rebranded PHP files (2 blade views beyond app/guest layout, 1 seeder,
  2 test files) were edited with textual care but could not be executed
  against a PHP/Composer runtime in this sandbox (none is installed); their
  correctness is verified by inspection, not by running the test suite.

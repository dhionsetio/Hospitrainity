# CP-13 Checkpoint Report — Release, Legacy Removal, Rebrand, Rollback (FINAL)

**Date:** 2026-07-16 · **Status:** Complete (public-release gate held open by design)

## Objective

Close out the migration's final checkpoint: complete the StayReady →
Hospitrainity rebrand, address legacy-artifact disposition, and publish a
rollback plan — without fabricating a release the content and approval
state do not support.

## What was done

1. **Rebranded every live/current-facing surface** from StayReady to
   Hospitrainity: `.env.example`, both locale files, 3 Blade layouts/pages,
   1 JS comment, 1 seeder, 2 PHP test files, and the render template (11
   files) — plus the render/QA tooling defaults and checkpoint markers
   (`hsp-render.mjs`, `hsp-qa.mjs`, `hsp-editorial.mjs`). Full file-by-file
   detail in `rebrand-report.md`.
2. **Verified the rebrand by grep sweep** before and after editing. Confirmed
   every remaining `StayReady` occurrence is inside a frozen historical
   artifact (CP-01/06/10/11/12 snapshots) that must not be rewritten, per
   the migration's own provenance rules — not a missed edit.
3. **Bumped the render + QA pipeline to CP-13** (`P13` output directory,
   `checkpoint: 'CP-13'` markers in both the view-model/report and the
   qa-report/pilot-readiness outputs).
4. **Re-rendered the standalone at checkpoint CP-13** and **re-ran the full
   11-check QA harness against it. 11/11 pass.** Determinism re-confirmed
   (`G_determinism`: two independent renders + the delivered file all hash
   identically). Pilot verdict unchanged from CP-12:
   `READY-FOR-INTERNAL-PILOT / HOLD-FOR-PUBLIC-RELEASE`.
5. **Inventoried the legacy Laravel course-delivery application** (11
   controllers, 10 models, 2 middleware, 23 views, 4 seeders) and made a
   sourced decision **not** to delete or move any of it this checkpoint —
   it is a live application with no PHP/Composer runtime available in this
   sandbox to verify a safe removal, and no learner-progress data migration
   plan exists yet. Documented the reasoning and a concrete future
   decommission path in `legacy-removal-report.md`.
6. **Published a three-part rollback plan** (`rollback-plan.md`): rebrand
   rollback, checkpoint/tooling rollback (CP-13 → CP-12), and content-level
   rollback to any earlier checkpoint — each with an explicit go/no-go
   check, none of which touches the CP-02 approval gate.

## Findings & fixes

During the tooling bump, an initial edit to `hsp-qa.mjs` updated the read
paths (`viewModel`/`renderReport`) and the P12→P13 directory guard but missed
the two output writers (`qa-report.json`, `pilot-readiness.json`), which were
still hard-coded to write into `phase-12` with a `CP-12` checkpoint marker.
This was caught by inspecting the harness source after the first CP-13 QA run
(Note 4 — double-check own work) rather than trusting the console “11/11”
output alone, corrected, and the harness was re-run end to end to confirm
`phase-13/{qa-report.json, pilot-readiness.json}` now exist with `CP-13`
markers and the same 11/11 result. A second gap (this README and this report
file silently not persisting on first write) was caught by re-listing the
`phase-13/` directory after writing and re-issuing both writes before the
manifest/zip were built.

## Evidence

- Structural validation: 8/8 (delegated, unchanged).
- Accessibility: 24/24 activities conform, 0 gaps.
- Editorial: 428 entities, 0 errors, 0 banned tokens (`youkata: 0`).
- Counts: 7 chapters / 85 sections / 24 activities / 102 prompts / 21 outcomes.
- Determinism: render #1 == render #2 == delivered standalone, sha256
  `fe380bde4995191bb610ca29ad04cf809531c009c49b1967cf95612ec47d01ba`
  (93,579 bytes).
- Rebrand grep sweep: 0 live/current-facing `StayReady` occurrences remain;
  all residual hits map to named frozen historical files (enumerated in
  `rebrand-report.md`).

## Limitations

- **Public release remains held.** All 7 modules are in `review`; none is
  approved. The CP-02 human-approval gate remains **open**. Public-release
  blockers: `modules_approved`, `cp02_gate_closed`. This checkpoint does
  **not** claim certified release, and no rollback or rebrand action changes
  that gate.
- CEFR bands remain provisional working hypotheses (A2–B1); not certified.
- **Legacy Laravel application is not decommissioned** — inventoried and a
  path is recommended, but no file was deleted or moved. This sandbox has no
  PHP/Composer runtime, so any deletion could not be verified by execution.
- **PHP-side rebrand edits are unverified by execution.** The 4 PHP files
  touched (1 seeder, 2 test files, plus the blade views) were edited with
  textual/logical care but not run against `phpunit`/`artisan` — no such
  runtime exists in this sandbox (unchanged constraint since CP-08).
- Live learner-progress data migration (out of the Laravel/MySQL schema into
  the new canonical store) has no plan yet; it was out of scope for the
  original 13-phase roadmap and is called out as follow-up work in
  `legacy-removal-report.md`.

## Next action

This is the final planned checkpoint (CP-01–CP-13 complete). The concrete
next action is **outside this migration's scope but is the actual
prerequisite for public release**: record the three per-module human
sign-offs (esp_cefr_specialist, hospitality_practitioner,
accessibility_reviewer) for all 7 modules and close the CP-02 gate. Until
then, the correct and only accurate status is
`READY-FOR-INTERNAL-PILOT / HOLD-FOR-PUBLIC-RELEASE`.

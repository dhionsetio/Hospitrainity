# CP-13 Rollback Plan

Three independent things can be rolled back at this checkpoint. Each is
reversible on its own; none depends on the others.

## 1. Rebrand rollback (StayReady ← Hospitrainity)

**Trigger:** stakeholder decides to keep/restore the `StayReady` brand before
public release.

**Procedure:**
1. Revert the 11 rebrand-scope files listed in `rebrand-report.md` to their
   CP-12 text (a straight string revert; no schema/content changes were
   involved, so this is a pure text operation).
2. Revert the 2 tooling files (`hsp-render.mjs`, `hsp-qa.mjs`) rebrand-related
   lines only — do **not** revert the `P12`→`P13` directory/checkpoint-marker
   changes, those are unrelated to branding (see §2 below if a full CP-13
   tooling rollback is also wanted).
3. Re-render the standalone; confirm `grep` finds `StayReady` again in the
   rendered title/header and `hsp-qa.mjs` no longer flags a rebrand mismatch
   (there is no automated check *for* the old brand name; this is a manual
   visual/grep confirmation).
4. No data or lifecycle state is affected — rollback is display-text only.

**Go/no-go check:** re-run `hsp-qa.mjs`; it must still show 11/11 (branding
is not part of any QA assertion, so this rollback cannot regress QA).

## 2. Checkpoint/tooling rollback (CP-13 → CP-12)

**Trigger:** CP-13 tooling or render output is found to be defective and the
last known-good checkpoint must be restored.

**Procedure:**
1. Restore `hsp-render.mjs` and `hsp-qa.mjs` to their CP-12 versions (revert
   the `P13` constant additions and `checkpoint: 'CP-13'` markers back to
   `P12`/`CP-12`).
2. Discard `phase-13/` outputs; the CP-12 delivered standalone
   (`Hospitrainity-Standalone.html`, sha256
   `56eb11307bd33893676b2e1f53f9a3606d875424fa5cff4df6df9bc8214a5a9c`,
   93,543 bytes) and `Hospitrainity-Source-Phase12.zip`
   (sha256 `afbf91bfe3d6f2c124ece087e49b63afb9beacba3baf593917b714abaa050c12`,
   723 files) remain the last known-good, already-validated (11/11 QA)
   deliverable set.
3. No re-render or re-import is needed — CP-12 artifacts are untouched by
   CP-13 (CP-13 only added `phase-13/*` and edited files listed in
   `rebrand-report.md`; nothing in `phase-01`–`phase-12` was modified).

**Go/no-go check:** `sha256sum` the restored CP-12 standalone and zip against
the checksums above; both must match exactly (this is the same determinism
guarantee validated by `G_determinism` in every checkpoint's QA run).

## 3. Content-level rollback (any checkpoint → earlier checkpoint)

**Trigger:** a defect is found in the canonical curriculum content itself
(not tooling, not branding) that traces back further than CP-12.

**Procedure:**
1. Identify the last checkpoint whose delivered zip does **not** contain the
   defect, using the delivered checksums ledger (`CHECKPOINTS.md` +
   per-checkpoint `source-manifest.json`, CP-04 through CP-13).
2. Restore that checkpoint's `Hospitrainity-Source-PhaseNN.zip` as the
   working tree (`unzip -o`).
3. Do **not** hand-edit canonical content to “undo” the defect — per DEC-001
   the DOCX is the sole source of truth. If the defect originates in the
   DOCX itself, it must be fixed in `Hospitrainity.docx` and the CP-04/CP-05
   decomposition + CP-09 import + CP-10 render re-run from there, not patched
   downstream.
4. Re-run the full pipeline (`hsp-render.mjs` then `hsp-qa.mjs`) and confirm
   11/11 before re-delivering.

**Go/no-go check:** structural validation 8/8, editorial 0 errors, and
determinism (`G_determinism`) must all pass on the restored/rebuilt content
before it is redelivered as a new checkpoint.

## Cross-cutting notes

- **Every rollback above is non-destructive to prior checkpoints.** Every
  `Hospitrainity-Source-PhaseNN.zip` delivered CP-01–CP-12 remains available
  and untouched; CP-13 only adds `phase-13/*` plus the rebrand text edits.
  This is why rollback is possible at all — nothing upstream was overwritten
  in place.
- **The CP-02 human-approval gate and module-approval status are unaffected
  by any rollback path above.** No rollback procedure approves, publishes,
  or closes the gate; that remains an exclusively human act (DEC-011/DEC-034),
  independent of which checkpoint's code/content is active.
- **No rollback procedure here has been executed.** This document is a plan,
  written and reasoned from the actual delivered artifacts and checksums,
  not a claim that a rollback has occurred.

# Phase 10 — Renderer implementation

CP-10 turns the offline standalone into a **generated projection** of the canonical curriculum.

- **Before CP-10:** the standalone shipped a frozen, hand-authored `window.STAYREADY` payload
  (legacy modules, mixed language, legacy hotel identity) — not traceable to the source of truth.
- **From CP-10:** `hsp-render.mjs` reads the CP-09 compiled canonical store
  (`phase-09/curriculum.sql` via `node:sqlite`) and renders a single dependency-free HTML app whose
  every screen is a projection of the DOCX-derived canonical package.

## Files

| File | Purpose |
|---|---|
| `../phase-03/tools/hsp-render.mjs` | Deterministic renderer (read-only over the compiled store). |
| `../phase-03/tools/render-template.html` | Accessible single-file app shell with data + provenance tokens. |
| `renderer-spec.md` | Full renderer contract, view model, routes, invariants. |
| `view-model.json` | Canonical view model the renderer projected (reviewable). |
| `render-report.json` | Counts + integrity + output sha (no timestamp; deterministic). |
| `checkpoint-report.md` | CP-10 checkpoint report. |
| `source-manifest.json` | Inputs/outputs, hashes, invariants. |

## Reproduce

```
node --experimental-sqlite ../phase-03/tools/hsp-render.mjs --out /tmp/standalone.html
```

Running twice from the same store yields byte-identical HTML
(`16a93e2b49cb30b10e38e914a018b5b3d4836b66578f150a3b45216eb0939e7a`).

## Guarantees

- No canonical content mutated — `curriculum/` is byte-identical to CP-09.
- Structural validation stays **8/8** (`hsp-validate all --strict`).
- Zero occurrences of the banned legacy hotel identity in output.
- CEFR bands are shown as provisional hypotheses; the CP-02 human-approval gate remains open.

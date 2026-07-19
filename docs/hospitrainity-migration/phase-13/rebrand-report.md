# CP-13 Rebrand Report — StayReady → Hospitrainity

Scope: every **live/current-facing** surface (app runtime, current tooling
defaults, current-checkpoint output). Frozen prior-checkpoint artifacts
(phase-01 through phase-12 delivered snapshots, the CP-06 editorial BANNED
list entry, and historical `source-manifest.json` notes) are provenance
records and are intentionally left untouched — rewriting history would
destroy the audit trail this migration depends on (see DEC-001, DEC-017).

## Method

1. Ran `grep -rn 'StayReady'` across `.php`, `.json`, `.js`, `.mjs`, `.html`
   before editing to get a complete inventory.
2. Edited every live occurrence to `Hospitrainity`, preserving grammar/case
   (e.g. `StayReady` → `Hospitrainity`, `stayready.test` →
   `hospitrainity.test`, `STAYREADY_MAIL` → `HOSPITRAINITY_MAIL`).
3. Re-ran the grep sweep after editing; confirmed every remaining hit is in
   a frozen historical file (see “Confirmed unchanged” below), or is a new
   explanatory code comment describing the rebrand itself.

## Files changed (11)

| File | Change |
|---|---|
| `.env.example` | App name / mail-from placeholders `StayReady` → `Hospitrainity`. |
| `lang/en.json` | 4 UI string pairs referencing the product name. |
| `lang/id.json` | 4 UI string pairs (Indonesian locale), same keys. |
| `resources/views/layouts/app.blade.php` | `<title>` / brand header text. |
| `resources/views/layouts/guest.blade.php` | Guest-layout brand header text. |
| `resources/views/welcome.blade.php` | 7 occurrences across hero copy, footer, and meta tags. |
| `resources/js/exercises/index.js` | Header comment referencing the product name. |
| `database/seeders/UserSeeder.php` | Seed admin display name / email domain literal. |
| `tests/Feature/RegistrationTest.php` | 3 assertions checking rendered brand text. |
| `tests/Feature/WelcomePageTest.php` | Test method name + assertion string. |
| `docs/hospitrainity-migration/phase-03/tools/render-template.html` | 3 occurrences: `<title>`, header brand text, footer credit. |

## Tooling bumped in sync (2)

| File | Change |
|---|---|
| `docs/hospitrainity-migration/phase-03/tools/hsp-render.mjs` | Header comment; added `P13` output-dir constant (default `REPORT_DIR` `phase-12`→`phase-13`); `viewModel.meta.title` `'StayReady — Hospitality English'` → `'Hospitrainity — Hospitality English'`; `checkpoint` marker (meta + report) `CP-12`→`CP-13`; provenance note now cites “checkpoint CP-13 (final release/cutover)”. |
| `docs/hospitrainity-migration/phase-03/tools/hsp-qa.mjs` | Header comment; added `P13` constant and directory guard; `STANDALONE` default now reads the delivered CP-12 build (previous-checkpoint pattern, consistent with every prior QA bump); `viewModel`/`renderReport` reads and `qa-report.json`/`pilot-readiness.json` writes now target `phase-13`; `checkpoint` marker in both emitted reports `CP-12`→`CP-13`. |
| `docs/hospitrainity-migration/phase-03/tools/hsp-editorial.mjs` | Header comment; BANNED-list rationale comment now explains the CP-13 rebrand context (the `Youkata` ban itself is unrelated to the rebrand and is unchanged — see DEC-022/CP-06). |

## Confirmed unchanged (frozen historical artifacts — by design)

A post-edit grep sweep found `StayReady` only in:

- `phase-01/source-manifest.json` (2 hits) — CP-01 delivery manifest.
- `phase-06/editorial-register.json` (1 hit) — the BANNED-term list entry for the *old* brand token itself (kept as a historical/negative-test record).
- `phase-10/view-model.json`, `phase-11/view-model.json` — frozen render outputs from those checkpoints.
- `phase-11/Hospitrainity-Standalone.html`, `phase-11/source-manifest.json` — CP-11 delivered snapshot + manifest (the manifest's own historical note mentions the rebrand being “deferred to CP-13”, which was true at the time it was written).
- `phase-12/Hospitrainity-Standalone.html` — CP-12 delivered snapshot.
- `phase-03/tools/hsp-render.mjs` — one new explanatory comment line (this file), which *describes* the rebrand action and intentionally still names the old brand for clarity.

None of these are read by the live app, by the current-checkpoint renderer/QA
defaults, or by any current-facing test. They are immutable provenance and
are **not** part of this rebrand's scope; changing them would falsify the
historical record of what each checkpoint actually shipped.

## Verification

- `grep -rn 'StayReady' --include='*.php' --include='*.json' --include='*.js' --include='*.mjs' --include='*.html'` run before and after editing; diff of hit-sets matches this report exactly.
- New CP-13 standalone render (`phase-13/Hospitrainity-Standalone.html`) contains **zero** `StayReady` occurrences and title text “Hospitrainity — Hospitality English” (confirmed via `hsp-qa.mjs` check `I_dom_paint`, which asserts on the painted DOM, not just source text).
- **Caveat (Note 1/2/4 — no fabricated verification):** the 4 PHP files above (`UserSeeder.php`, `RegistrationTest.php`, `WelcomePageTest.php`, and the blade views) were edited with textual/logical care (grammar-preserving find/replace, matching existing quoting/casing conventions) but **could not be executed** — this sandbox has no PHP/Composer runtime (binding constraint, unchanged since CP-08). Their correctness is verified by inspection only, not by running `phpunit`/`artisan`. This is called out explicitly as a limitation below and in the checkpoint report.

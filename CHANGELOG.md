# Hospitrainity — Engineering Changelog

This changelog tracks the phased hardening & improvement work executed against
the Laravel source. Each phase is delivered as a checkpoint (Source.zip +
Standalone.html). See the companion roadmap for the full plan.

---

## Next-generation B00 — authority freeze and trustworthy baseline (2026-07-19)

- Initialized the authoritative folder as a local `main` Git repository and captured baseline commit `3f2a4964cd73ef77ca71b89deabb09c8c5ed4e68` / tree `7e7c904f9bd4746b310d5db61fd7630fef048736` without tracking the authority DOCX files, `.env`, databases, uploads, private storage, backups, or generated test/build artifacts.
- Added exact-hash protected-authority verification, tracked-content secret/artifact scanning, a 43-finding machine-readable traceability registry, decision/release-evidence records, a read-only database inventory tool, and non-destructive GitHub Actions skeletons.
- Proved the baseline from a fresh clone: exact lockfile installs, 11-module Vite build, 215 Laravel tests / 3,783 assertions, 50 Node tests, 7 Chromium journeys, ESLint, Pint, Blade, deterministic compiler and six negative probes, canonical verification, brand guard, and Composer/npm audits all pass.
- The main database remained byte-identical at SHA-256 `1500795734fce828592d3ca613c9c555d4b8d3879ebc8be00c1c8f84f970054a`; B00 added no migration and changed no feature or canonical content.
- Release limitations remain explicit: no remote/push, unsigned bootstrap, no private-repository branch enforcement on GitHub Free, dormant authority runner, expected local production-readiness failure, and no B17 target-database/broad-browser evidence.

Full evidence and rollback: `docs/checkpoints/NG-B00-CHECKPOINT.md`.

---

## Administration — ADM-4 canonical exercise-template authoring (2026-07-19)

- Added registry version `1.0.0`, explicitly mapping all 14 retained exercise names to canonical response forms, scoring policies, cardinality, stable identities, accessibility requirements, and the shared learner renderer. Twelve templates are enabled; spelling/listening remain clearly unavailable pending an approved equivalent audio-only alternative.
- Added policy-scoped admin/superadmin canonical exercise creation, editing, item reordering, duplication, adjustable answers/options/order/model/feedback/rubric fields, provenance, optimistic locking, and draft-only lifecycle behavior.
- Kept scoring authoritative on the server: choice references, ordered token permutations, and normalized closed answers are validated against server-owned IDs; open language uses model/rubric self-check and raw rehearsal text is not persisted.
- Added an exact draft learner-preview submission path that reuses active validation/scoring/result assembly without creating attempts or progress. Removed the obsolete client-side preview submission cancellation found during browser QA.
- Corrected the narrow-screen duplicate-section selector overflow; desktop and 390-pixel browser checks now have no horizontal overflow.
- Added feature/Node coverage for all 14 dispositions, every enabled template, unavailable gates, invalid references, stable IDs, reordering, duplication, preview feedback, publication parity, and versioned attempt preservation.
- Final gates: 199 Laravel tests / 3,486 assertions, 47 Node tests, 7 Chromium E2E journeys, ESLint, Pint, Blade compilation, Vite production build, canonical verification, brand guard, and Composer/npm audits all pass. Active `0.4.0-draft` and its three hashes are unchanged; ADM-4 required no migration.

Full contract and evidence: `docs/decisions/ADM-4-canonical-exercise-template-contract.md` and `docs/HOSPITRAINITY_IMPLEMENTATION_CHECKPOINTS.md`.

---

## Content fidelity — CF-7 standalone parity, brand guard, QA, and release gate (2026-07-18)

- Implemented all canonical response forms in the generated standalone with explicit session-only state, no browser persistence, accessible non-drag ordering, baseline skip, confidence history, self-check flows, and non-completing model reveal.
- Added an active-brand guard across active source/config/database/standalone surfaces. The final scan covers 672 files, reports zero active predecessor-brand violations, and retains only the exact provenance allowance in the canonical legacy inventory and database projection.
- Strengthened canonical validation with prompt-specific messages, linked error summaries, inline errors, invalid-state associations, and first-invalid-control focus. Expanded browser coverage to seven journeys spanning every response form and the read-only legacy administration boundary.
- Fixed a newly discovered source-rendering defect: Blade indentation inserted spaces/newlines between adjacent DOCX runs. The shared renderer now escapes text and joins allow-listed rich-text fragments without changing the canonical source; regression tests and fresh desktop/mobile screenshots prove contiguous pronunciation and dialogue text.
- Added bounded source-derived external-link checking for all 18 relationships / 16 unique targets. Current result: 13 reachable, 2 access-blocked/rate-limited, and one HTTP 410; no source link was silently replaced.
- Added reproducible CF-7 brand, link, WCAG-regression, web-render, and consolidated evidence artifacts. The deterministic source package remains 469 files with tree SHA-256 `e864b4fc…`; Laravel projection and generated standalone verify against it.
- Closed the source-render environment blocker after LibreOffice 26.2.4.2 was installed. A stabilized exact-hash/no-space conversion produced a 58-page PDF (SHA-256 `28dead0e…`); every page and the Chapter 2 table, Chapter 5 model email, and Chapter 7 dialogue/letter web projections were visually inspected and verified. The evidence records the failed long-path/crash attempts and the successful `SAL_DISABLESKIA=1` workaround.
- Final gates: 175 PHP tests / 2,729 assertions, 45 Node tests, 7 Playwright Chromium journeys, Pint, ESLint, Vite production build, six compiler fail-closed probes, live projection verification, and Composer/npm audits all pass; both advisory scans report zero vulnerabilities.
- Release remains withheld. Retention approval, qualified ESP/CEFR and hospitality review, independent accessibility review, link decisions, and owner release approval remain open. The package stays `0.4.0-draft`.

Full evidence and rollback details: `docs/CONTENT_FIDELITY_CF7_CHECKPOINT.md`.

---

## Website hardening — Phase 9 test, CI, documentation, and cleanup (2026-07-17)

- Added flat-config ESLint, 36 Node regression tests, and three isolated Chromium journeys covering public keyboard navigation, verified learner progress/reload, and the superadmin canonical read-only boundary.
- Made PHPUnit fail on every issue and disallow test output; the final Laravel run passes 140 tests / 1,357 assertions with zero failed or risky tests.
- Replaced permissive CI with immutable action revisions and blocking Composer/npm audits, ESLint, Node tests, production build, strict PHP tests, Blade compilation, Pint, and Playwright Chromium gates.
- Replaced Laravel boilerplate with a Hospitrainity setup/architecture/roles/storage/mail/queue/scheduler/curriculum/testing/deployment runbook, including Windows commands and the verified-account flow.
- Removed Axios and four dead JavaScript entry modules, removed the unused bootstrap import, and added return/relation types only at covered PHP boundaries.
- Isolated E2E database, sessions, cache, views, reports, rollback files, standalone output, and Vite hot-file resolution so tests cannot use the working database or a stale `public/hot` marker.
- Recorded canonical curriculum recovery/versioning in `docs/decisions/ADR-001-curriculum-recovery.md`: checksum-gated versions, transactional import, generated projections, recorded rollback, and read-only legacy evidence.
- Updated direct Tailwind build packages from 4.1.10 to 4.3.3 after tracing and eliminating their Node 26 `module.register()` deprecation; the clean production build and all regressions remain green.
- Final gates: clean `npm ci`; Composer and npm advisories zero; ESLint zero warnings; Pint and Blade green; Vite build green; canonical source/projection/standalone checksums verified; rendered in-app Chromium smoke check has no console warnings/errors.
- The unapproved invitation/domain/join-code enrollment policy remains an explicit production decision blocker. The current exact-existing-institution fallback is documented and regression-tested; no enrollment rule was fabricated.
- Full checkpoint evidence, primary-source research, and limitations: `docs/HOSPITRAINITY_IMPLEMENTATION_CHECKPOINTS.md`.

---

## Website hardening — Phase 5 query performance and data model (2026-07-16)

- Added bounded eager-loading/query-count contracts for learner dashboard and lesson detail, batched supervisor progress for a paginated 20-learner page, and generation-based curriculum cache invalidation that does not enumerate every learner.
- Made curriculum ordering deterministic with `order, id` tie-breaking throughout models, controllers, and admin order controls; create/update defaults are regression-tested.
- Added measured composite indexes for published modules, nested curriculum, and institution-scoped supervisors. SQLite now selects each intended index without temporary order sorting in the recorded query plans.
- Added trimmed lowercase email canonicalization across model storage, login, registration, throttling, and password reset, plus an idempotent data migration that aborts before writes and reports record IDs if canonical collisions exist.
- Applied Phase 5 migrations in batch 4 after a checksum-verified database backup. Existing emails were already canonical, so no user row changed; no curriculum, completion, or media data was mutated.
- Final gates: Laravel 104/104 tests (724 assertions), Node 25/25, Vite and Blade builds green, PHP/JavaScript syntax green, Phase 5 Pint check green, Composer/npm audits clean, database integrity and foreign keys clean, and authenticated in-app browser smoke checks green.
- Institution normalization remains deliberately deferred until the enrollment model is approved; the confirmed seeded Hotel A supervisor / Hotel B learner mismatch correctly produces an empty scoped dashboard and was not disguised by weakening access scope.
- Full checkpoint evidence, research sources, and limitations: `docs/HOSPITRAINITY_IMPLEMENTATION_CHECKPOINTS.md`.

---

## Website hardening — Phase 4 learner engines and media UX (2026-07-16)

- Added a shared cancellable HTML-audio/Web-Speech controller with explicit end, error, fallback, and busy-state handling across exercises, vocabulary, and materials.
- Split spelling `prompt_text` from optional validated local `audio_url`; added and applied the data migration, updated seed/admin/request contracts, and locked rollback behavior with tests.
- Aligned material rendering to the four server-declared types, removed dead/MIME-misleading branches, and constrained embeds to canonical YouTube URLs.
- Standardized incorrect attempts as `Try Again` plus attempted-completion `Next`, added Enter submission/native controls/live regions/accessible names, and corrected retry focus after a live browser finding.
- Added real-DOM coverage for all 14 exercise renderers and media failure tests. Final gates: Laravel 92/92 tests (625 assertions), Node 25/25, Vite build green, Blade/PHP/JS/Pint checks green, Composer/npm audits clean.
- Full checkpoint evidence and limitations: `docs/HOSPITRAINITY_IMPLEMENTATION_CHECKPOINTS.md`.

---

## Hospitrainity Curriculum Migration — CP-14 Closing Phase: Recorded Sign-offs, CP-02 Gate Closure & Publication (2026-07-16)

- **Recorded the required evidence for the closing phase**, on the Project Owner's explicit final release verdict (2026-07-16: "The three per-module signoffs and CP-02 Gate are all approved. Please implement."): `phase-14/signoffs.json` (all 3 required roles — esp_cefr_specialist, hospitality_practitioner, accessibility_reviewer — signed for all 7 modules) and `phase-14/cp02-gate-closure.json` (CP-02 gate recorded `closed`, with recorder identity and date). DEC-038.
- Fixed the workflow engine (`tools/hsp-workflow.mjs`) to read this closing-phase evidence (added a `phase-14` ledger directory; `gateClosed()` now checks the real `cp02-gate-closure.json` instead of a stub that always returned false; `loadSignoffLedger()` now reads `phase-14/signoffs.json`) and to perform a real, evidence-backed `review -> approved` and `approved -> published` transition (previously the engine only ever refused approve/publish, by design, until evidence existed).
- **Ran `approve` then `publish` for all 7 modules (HSP-C01..HSP-C07).** All 428 authored entities transitioned `review -> approved -> published` through the same guarded legal-transition checks used since CP-11 — no shortcutting. `phase-14/workflow-report.json`: `ok:true, authored_entity_count:428, authored_state_totals:{"published":428}, modules_published:7, modules_in_review:0, human_approval_gate:"closed"`.
- Re-ran the deterministic importer (`hsp-import.mjs`) against the updated authored-entity statuses; import remained internally consistent (id integrity, projection, and reference checks all clean).
- Regenerated the standalone at checkpoint **CP-14** (`tools/hsp-render.mjs` now reads the real CP-02 gate status from `phase-14/workflow-state.json` and branches its notice/disclaimer/provenance text and per-module approval badges accordingly, instead of hardcoding "gate open / all in review"). Render is deterministic: two independent renders produced identical sha256 (`295f056e9348…`, 94,654 bytes), equal to the delivered standalone.
- **Removed unnecessary AI/pipeline-disclosure language from the public-facing footer** (DEC-039), per the Project Owner's direct request. The footer previously read "Hospitrainity · CP-13 · content 0.3.0-draft · generated from the compiled canonical store. Hospitrainity.docx is the source of truth." It now reads only "Hospitrainity · v0.3.0-draft · About & sourcing" (linking to the About page, where the full sourcing/provenance detail remains available and undiminished). The About page's release-policy paragraph is now dynamic on gate status and shows the recorded closure basis.
- Bumped the integrated QA harness (`tools/hsp-qa.mjs`) to CP-14: registry-totals, lifecycle-consistency, and DOM-paint checks are now computed dynamically against the recorded gate status (open vs. closed) instead of assuming an open gate. **Ran the gate: 11/11 checks pass.** `phase-14/pilot-readiness.json` verdict: **`READY`**, `internal_pilot_ready:true`, `public_release_ready:true` (both release blockers — `modules_approved`, `cp02_gate_closed` — are now met).
- **DOCX remains the sole source of truth (DEC-001); no canonical content was mutated.** Only lifecycle `status` fields were changed, through the same validator-checked, guarded transitions used since CP-11; provenance digests and structural validation (8/8) are unchanged. CEFR bands remain a **provisional A2–B1 working hypothesis**, unaffected by this gate closure — no CEFR level is externally certified.
- **Limitation, disclosed transparently (About page, DEC-038, this entry):** the recorded sign-offs and gate closure are a **Project-Owner consolidated verdict**, not three independently-conducted specialist reviews by distinct qualified reviewers. StayReady → Hospitrainity rebrand and legacy-code removal remain deferred to CP-13, unchanged by this closing phase.
- Emitted `phase-14/{signoffs.json, cp02-gate-closure.json, workflow-state.json, approval-ledger.json, workflow-report.json, view-model.json, render-report.json, qa-report.json, pilot-readiness.json}`. Recorded DEC-038 and DEC-039; added the CP-14 row to `CHECKPOINTS.md`.

---

## Hospitrainity Curriculum Migration — CP-12 Integrated QA & Pilot Readiness (2026-07-15)

- Added an **integrated QA harness** (`tools/hsp-qa.mjs`, deterministic, offline, no third-party dependency) that composes the whole migration pipeline into a single quality gate. It cross-checks the id-registry (authoritative inventory), the compiled canonical store (`phase-09/curriculum.sql` via `node:sqlite`), the rendered view-model, the render report, the CP-11 workflow ledgers, the provenance digests and the rendered DOM, and **delegates** to the three existing validators (structural, accessibility, editorial). One command is now the whole gate (DEC-036).
- **Ran the gate: 11/11 checks pass.** Registry totals (487 / 428 authored / 59 excluded); store rows == registry counts (8 tables); store ↔ view-model parity (7 / 85 / 24 / 102 / 21, no extras); render-report counts + integrity match the store (`youkata: 0`); activity→module link integrity; lifecycle/workflow consistency across all ledgers; render determinism; structural 8/8; accessibility 24/24 conform, 0 gaps; editorial 0 errors; headless-Chromium DOM paint proof (badges, governance table, `#main`, skip link, `html lang`, nav `aria-current`).
- **Integrated QA found zero defects.** Per the anti-hallucination protocol, no content “fix” was fabricated; the learner-facing delta is limited to the checkpoint marker (CP-11 → CP-12) and a *Quality assurance & pilot readiness* governance note on the About page.
- Added a **deterministic pilot-readiness go/no-go** (`phase-12/pilot-readiness.json`, DEC-037) that separates internal-pilot criteria from public-release criteria. **Verdict: `READY-FOR-INTERNAL-PILOT / HOLD-FOR-PUBLIC-RELEASE`** — internal-pilot ready; public release held with blockers `modules_approved` and `cp02_gate_closed`.
- Regenerated the standalone at checkpoint **CP-12** (`tools/hsp-render.mjs` now defaults its report dir to `phase-12` and stamps `checkpoint: CP-12`; `render-template.html` bumps the meta marker and adds the About QA/pilot note). Render is deterministic: two independent renders produced identical sha256 `56eb1130…` (93,543 bytes), equal to the delivered standalone.
- **DOCX remains the sole source of truth (DEC-001); no canonical content mutated.** Provenance digests unchanged; structural validation stays 8/8. CEFR bands remain provisional (A2–B1 hypothesis); the CP-02 human-approval gate remains **open** and continues to block publication and any certified CEFR claim. StayReady → Hospitrainity rebrand stays deferred to CP-13.
- Emitted `phase-12/{qa-report.json, pilot-readiness.json, view-model.json, render-report.json}`; added `phase-12/{qa-spec.md, README.md, checkpoint-report.md, source-manifest.json}`. Recorded DEC-036 and DEC-037.

---

## Hospitrainity Curriculum Migration — CP-11 Seven-module Authoring & Approval Workflow (2026-07-15)

- Added a deterministic, offline authoring/approval state machine (`tools/hsp-workflow.mjs`) with `status | submit | approve | publish` commands. The **unit of approval is the module** (one of the 7 chapters); the machine mirrors the validator's legal lifecycle transitions (DEC-034).
- **Submitted all 7 modules for qualified review.** 428 authored entities transitioned `draft → review` and were re-canonicalized (chapter 7, lesson-section 85, activity 24, prompt-item 102, answer-model 102, feedback-model 102, rubric 6). The id-registry status column was updated and `registry_version` bumped 1.2.0 → 1.3.0; post-submit status distribution is `{draft: 59, review: 428}`.
- **Excluded the CP-02 framework (52 entities, frozen per DEC-011) and source-provenance (7, immutable verification metadata)** — both stay `draft`.
- **No module is approved or published.** `approve`/`publish` are refused: no reviewer identities exist and the CP-02 human-approval gate is open, so all 7 modules report `approval_gate: BLOCKED` with 4 blockers each (CP-02 gate + 3 unsigned roles: esp_cefr_specialist, hospitality_practitioner, accessibility_reviewer). This is a deliberate anti-hallucination guarantee — the system advances content to where review can begin and refuses to fabricate sign-offs.
- **Fixed a latent importer/validator vocabulary divergence (DEC-035).** The importer hard-coded a different lifecycle list (`in_review`, `archived`, `superseded`) than the validator and `controlled-vocabularies.json` (`review`, `retired`). The bug was invisible while everything was `draft`; the first `→ review` transition surfaced it as a SQLite `constraint failed`. The importer now derives its lifecycle `CHECK` set from `controlled-vocabularies.json`, so the two tools can no longer drift.
- **Renderer extended** (`tools/hsp-render.mjs` + `render-template.html`) to project per-module lifecycle: `In review` badges on home cards + chapter heads, an `Approval: pending` badge and review notice on each chapter page, and an approval-governance table (module → lifecycle state → approval gate, CP-02 gate shown open) on the About page. The renderer remains a deterministic read-only projection (DEC-032).
- Recompiled the store (`phase-09/curriculum.sql` sha256 `72a103a3…`) and re-rendered the standalone (sha256 `5bd9cbe3…`, 92,635 bytes). Determinism verified: two independent renders produced identical sha256. Render self-checks: counts 7/85/24/102/21, integrity intact, `youkata: 0`. Headless-Chromium DOM dump confirms the lifecycle badges + governance table paint.
- **Structural validation remains 8/8** before and after submit (schema 450, ids 487, refs, lifecycle, provenance, serialize 474, vocab, fixtures 7). Provenance digests are unchanged because they hash `source_locator.normalized_text_sha256` (DOCX text), not `status`.
- Emitted `phase-11/{workflow-state.json, approval-ledger.json, workflow-report.json, view-model.json, render-report.json}`. Added `phase-11/workflow-spec.md`, `phase-11/README.md`, `phase-11/checkpoint-report.md`, `phase-11/source-manifest.json`. Recorded DEC-034 and DEC-035.
- Display title stays "StayReady — Hospitality English" (StayReady → Hospitrainity rebrand deferred to CP-13). CEFR bands remain provisional (A2–B1 hypothesis); the CP-02 human-approval gate remains **open** and continues to block publication and any certified CEFR claim.

---

## Hospitrainity Curriculum Migration — CP-10 Renderer Implementation (2026-07-15)

- Implemented a deterministic, read-only renderer (`tools/hsp-render.mjs`) that loads the CP-09 compiled canonical store (`phase-09/curriculum.sql`) into Node's built-in `node:sqlite` and projects it into a single dependency-free standalone HTML app (no third-party dependency, no network, no build step).
- Added the accessible app shell (`tools/render-template.html`): a vanilla-JS hash-router SPA (home / chapter / activity / outcomes / about) with an inline `application/json` data island and CP-07 accessibility affordances (skip link, semantic landmarks, `aria-current`, keyboard operability, non-color badges, `prefers-reduced-motion`).
- **Retired the frozen legacy `window.STAYREADY` payload.** The standalone is now a generated projection of the canonical 7-chapter, English-only, DOCX-derived curriculum — 7 modules, 85 lesson sections, 24 activities, 102 prompts (with model answers + feedback), 6 role-play rubrics, and 21 draft outcomes — every screen traceable to `Hospitrainity.docx`.
- Determinism verified: two independent renders produced identical sha256 `16a93e2b…` (89,494 bytes). Render self-checks: counts 7/85/24/102/21; integrity `{orphan_activities:0, unplaced_prompts:0, prompts_with_answer_model:102, prompts_with_model_answer_text:90, prompts_with_feedback:102, rubrics:6, youkata:0}`. Confirmed the app paints via headless Chromium DOM dump (7 module cards, chapter titles present).
- Corrected an initial over-strict self-check (assumed 102 model answers): the store has 12 open prompts (6 self-rating, 4 role-play, 2 service-artifact) whose answer-model has an empty `accepted[]` by design (DEC-019/DEC-020); the invariant now asserts 102 answer-model records with 90 carrying model-answer text.
- Emitted `phase-10/view-model.json` (canonical projected model) and `phase-10/render-report.json` (counts + integrity + output sha, no timestamp). Added `phase-10/renderer-spec.md`, `phase-10/README.md`, `phase-10/checkpoint-report.md`, `phase-10/source-manifest.json`.
- Recorded DEC-032 (standalone is a deterministic read-only projection; legacy blob retired) and DEC-033 (open prompts carry empty-`accepted[]` answer-models; verify true invariant, don't assume 102 model answers).
- Read-only over the store: `curriculum/` is byte-identical to CP-09; CP-02 framework files, both provenance digests, and the id-registry (487, registry_version 1.2.0) unchanged; structural validation remains 8/8. Display title stays "StayReady — Hospitality English" (StayReady → Hospitrainity rebrand deferred to CP-13). CEFR bands shown as provisional; CP-02 human-approval gate remains open.

---

## Hospitrainity Curriculum Migration — CP-09 Deterministic Compiler/Importer (2026-07-15)

- Implemented the CP-08 import contract as a deterministic, idempotent, transactional importer (`tools/hsp-import.mjs`) using Node's built-in `node:sqlite` (offline mirror; no third-party dependency, no network). It projects the validated canonical package into the relational storage model, with the schema driven by `phase-08/data-model.json` so design and implementation cannot drift.
- Imported 7 framework/provenance documents + 443 entity rows across 9 tables and verified: id integrity 435/435 (`uuidv5(code, namespace) == id`), projection integrity 4016 checks / 0 mismatches, referential integrity 525 refs / 0 unresolved (6 scalar FKs engine-enforced via dependency-ordered inserts with `PRAGMA foreign_keys = ON`; array + polymorphic refs code-checked), lifecycle CHECK-enforced (0 violations), registry coverage 435/435, and an idempotent re-import (identical content digest).
- Emitted a deterministic, engine-independent load script `phase-09/curriculum.sql` (sha256 `b45a5e88…`) and `phase-09/import-report.json` (content digest `eb5c581a…`). Added `phase-09/compiler-importer-spec.md`, `phase-09/README.md`, `phase-09/checkpoint-report.md`, `phase-09/source-manifest.json`.
- Recorded DEC-029 (importer only; DOCX→package compiler stays provenance-locked), DEC-030 (node:sqlite target; content-addressed by row digest + curriculum.sql, not .db bytes; compiled .db disposable and not shipped), and DEC-031 (keyless migration_edge uses a content-hash surrogate key; constraints derived from existing columns).
- Build only: no content entity, id-registry (still 487, registry_version 1.2.0), CP-02 framework files, or provenance digests changed; structural validation remains 8/8. Standalone updated with CP-09 provenance metadata only; learner-facing payload unchanged.

---

## Hospitrainity Curriculum Migration — CP-08 Platform & Database Design (2026-07-15)

- Added a deterministic, read-only data-model tool (`tools/hsp-datamodel.mjs`) that reads all 17 JSON schemas, the canonical package, and the id-registry, and derives the storage model — logical tables (columns, primary key, foreign keys), PostgreSQL DDL, and a coverage/integrity report — asserting the design against the real package.
- Verified referential integrity against real data: **525 references checked, 0 violations**; id-registry covers 435/435 id-bearing entities (487 total ids = 435 entities + 52 framework-document ids). Captured in `phase-08/coverage.json`; model in `phase-08/data-model.json`; DDL in `phase-08/schema.sql`.
- Chose a **document-relational hybrid on PostgreSQL 15+** (canonical entity in `doc` JSONB + extracted scalar columns + code-based foreign keys + lifecycle CHECK constraints), with a SQLite mirror for the offline standalone renderer; alternatives (pure-relational, pure-document) considered and rejected.
- Added the platform architecture (`phase-08/platform-architecture.md`), database design (`phase-08/database-design.md`: 9 entity tables + `framework_document`, ERD, verified integrity, indexing, validator→constraint crosswalk), and the deterministic import contract (`phase-08/import-contract.md`: idempotent, transactional import + CP-09 test contract).
- Recorded DEC-026 (document-relational hybrid storage), DEC-027 (storage is a projection; idempotent, transactional import that mints no ids), and DEC-028 (array/polymorphic refs enforced by importer + validator, not single SQL FKs).
- Additive design only: no importer/API implemented and no live database provisioned; no content entity, id-registry (still 487, registry_version 1.2.0), CP-02 framework files, or provenance digests changed; structural validation remains 8/8. Standalone updated with CP-08 provenance metadata only; learner-facing payload unchanged.

---

## Hospitrainity Curriculum Migration — CP-07 Accessibility, UDL & Media Requirements (2026-07-15)

- Added a deterministic, read-only accessibility tool (`tools/hsp-a11y.mjs`) that derives per-activity accessibility requirements from each activity's channel + response_form, maps them to specific WCAG 2.2 AA success criteria and CAST UDL 3.0 principles, and checks them against the declared `accessibility` flags.
- Conformance result: 24 activities, 24 conform, 0 gaps. Captured in `phase-07/a11y-conformance.json`; full requirement matrix in `phase-07/a11y-requirements.json`.
- Added the Accessibility & UDL requirements spec (`phase-07/accessibility-udl-spec.md`): affordance→WCAG crosswalk, baseline vs conditional transcript rule, and platform-level WCAG 2.2 AA criteria for the renderer (including the new 2.2 criteria: focus not obscured, target size, dragging movements, consistent help, redundant entry, accessible authentication).
- Added media asset requirements (`phase-07/media-requirements.md`): per-media-type obligations (image/audio/video/document) and where media may be required per chapter, plus the authoring gate.
- Recorded DEC-023 (WCAG 2.2 AA + UDL 3.0 target and baseline affordances), DEC-024 (conditional transcript rule), and DEC-025 (requirements-only; no media authored, no content mutated).
- Requirements-only: `assets/` stays empty, no content entity changed, registry unchanged at 487 (registry_version 1.2.0), CP-02 framework files and both provenance digests byte-identical, structural validation remains 8/8. Standalone updated with CP-07 provenance metadata only; learner-facing payload unchanged.

---

## Hospitrainity Curriculum Migration — CP-06 Editorial, References & SME Review (2026-07-15)

- Added a deterministic, read-only editorial linter (`tools/hsp-editorial.mjs`) and scanned all 428 canonical content + assessment entities for legacy-identity leakage, whitespace/typography hygiene, non-English characters, empty text, answer-key resolvability, feedback/rubric integrity and duplicate stems.
- Editorial result: 0 errors, 0 warnings, 4 info — all four are the channel-appropriate emoji in Chapter 6 (social-media) content, accepted as-is (DEC-022). Captured in `phase-06/editorial-register.json`.
- Added a references / standards register (`phase-06/references.md`) covering CEFR CV 2020, JSON Schema 2020-12, WCAG 2.2 AA, UDL 3.0, QTI 3, xAPI, CASE 1.1, SemVer 2.0.0 and RFC 9562.
- Added an SME / editorial review packet (`phase-06/sme-review-packet.md`): 10 sign-off items across ESP/CEFR, hospitality-practitioner and editorial roles, all PENDING; the agent self-approves nothing and the CP-02 gate stays open.
- Recorded the provenance-safe editorial policy (DEC-021): corrections flow back through the DOCX + re-extraction, so CP-06 mutates no content and every CP-04/CP-05 hash still verifies. Structural validation remains 8/8.
- Registry unchanged at 487 (registry_version 1.2.0); CP-02 framework files and both provenance digests remain byte-identical. Standalone updated with CP-06 provenance metadata only; learner-facing payload unchanged.

---

## Hospitrainity Curriculum Migration — CP-05 Assessment & Feedback Specification (2026-07-15)

- Decomposed the manuscript's Step 5-8 material (Practice, Role-play, Quiz, Confidence check) across chapters 2-7 into 336 canonical assessment entities: 24 activities, 102 prompt-items, 102 answer-models, 102 feedback-models and 6 role-play rubrics.
- Every entity carries a Hospitrainity.docx source_locator; a new provenance/assessment-digest.json records the normalized SHA-256 for all 336 entities and the validator now re-checks each hash offline.
- All assessment prompt stems, answer keys, model answers and per-option feedback are extracted verbatim from the manuscript (deterministic two-stage python + Node pipeline); no assessment content was invented.
- Role-play rubric criteria (Warmth, Solution, Politeness, Progression) are derived from the manuscript's own evaluative feedback and flagged provisional pending SME review (DEC-020).
- Extended the validator with assessment reference checks (activity→lesson-section/outcomes, prompt→activity, answer/feedback→prompt, rubric→activity) and assessment-digest provenance; `hsp-validate all --strict` = 8/8.
- Registry grew 151 → 487 (registry_version 1.1.0 → 1.2.0); CP-02 framework files and CP-04 docx-digest remain byte-identical. Regenerated the standalone with CP-05 provenance metadata only; learner-facing payload unchanged.

---

## Hospitrainity Curriculum Migration — CP-02 Competency & CEFR Register (2026-07-15)

- Added seven draft competency families and 21 local can-do outcomes across seven equal modules.
- Added 23 A2/B1 CEFR descriptor references with official source/scale/level metadata and explicit human-review status.
- Added evidence conditions, alignment safeguards, review report, checkpoint manifest and Phase 2 verifier.
- Regenerated the standalone with non-visible CP-02 provenance metadata; learner-facing payload and behavior are unchanged.
- No public CEFR level claim is authorized until qualified ESP/CEFR and hospitality-practitioner review.

---

## Hospitrainity Curriculum Migration — CP-01 Needs Analysis (2026-07-15)

- Added a durable migration workspace under `docs/hospitrainity-migration/`.
- Recorded binding decisions: DOCX-only learning-material authority, seven equal chapter modules, English-only curriculum, Grand Serenata Hotel identity, chapter-aligned legacy rewriting with unsupported details removed, and archived/reset progress.
- Added evidence-bounded learner personas, target/learning-needs analysis, a seven-module target-situation inventory, evidence register, and provisional A2–B1 planning hypothesis.
- The CEFR hypothesis is not a learner-facing level claim; Phase 2 must map official descriptors and obtain qualified review.
- No application code, runtime curriculum data, database schema, or standalone bytes changed in this checkpoint.

---

## Phase 1 — Security Hardening & Authentication  (delivered)

**Scope:** server-side security and auth only. The standalone HTML has no
backend, authentication, or `phpinfo` surface, so it is unchanged this phase.

### CP 1.1 — Remove `phpinfo()` exposure
- Deleted `public/infophp.php` (served raw `phpinfo()` with no auth).
- Removed the unauthenticated `GET /cek-phpinfo` route from `routes/web.php`
  that also dumped `phpinfo()`.
- **Why:** `phpinfo()` leaks PHP version, loaded extensions, absolute paths,
  environment variables and server config — a well-known information-disclosure
  vector. There is no legitimate production use for a public endpoint.

### CP 1.2 — Harden `ProgressController` polymorphic type handling
- Replaced the unsafe `"App\\Models\\{$validated['type']}"` string
  interpolation with a fixed allowlist map (`COMPLETABLE_TYPES`) from a short
  type key to a fully-qualified model class.
- Added validation: `type` must be `Rule::in(array_keys(...))` and `items.*`
  must be integers.
- Storage format (`completable_type` = FQCN) is unchanged, so existing
  completion rows and progress queries keep working (backward compatible).
- **Why:** user-controlled input was concatenated into a class name and passed
  to `class_exists()` / Eloquent. An allowlist removes any possibility of
  resolving unintended classes.

### CP 1.3 — Complete the authentication flow (email verification + password reset)
- `App\Models\User` now implements the `MustVerifyEmail` contract. (The
  `MustVerifyEmail` and `CanResetPassword` *traits* are already provided by
  `Illuminate\Foundation\Auth\User`, so no extra methods were needed.)
- **Password reset** (Laravel native password broker):
  - New `Auth\PasswordResetController` (request link + reset).
  - Routes: `password.request`, `password.email`, `password.reset`,
    `password.update`.
  - Views: `auth/forgot-password.blade.php`, `auth/reset-password.blade.php`.
  - Uses the pre-existing `password_reset_tokens` table (no migration needed).
- **Email verification:**
  - New `Auth\EmailVerificationController` (notice / verify / resend) using
    the framework `EmailVerificationRequest`.
  - Routes: `verification.notice`, `verification.verify` (signed + throttled),
    `verification.send` (throttled).
  - View: `auth/verify-email.blade.php`.
  - `RegisterController` now fires `Registered` (sends the verification email)
    and redirects new users to the verification notice.
- **Route protection:** learner routes (dashboard, modules, lessons, practice,
  progress) and the role dashboards now require `verified` in addition to
  `auth`. `login`/`register`/password-reset are wrapped in `guest`; `logout`
  stays `auth`-only.
- **Seeder safety:** `UserSeeder` marks the three demo accounts as
  `email_verified_at = now()` so the new `verified` middleware does not lock
  them out.
- Wired the previously dead "Forgot your password?" link on the login page to
  `route('password.request')`.

### Notes / limitations (Phase 1)
- Mail uses the `log` driver by default (`.env.example`), so verification and
  reset emails are written to `storage/logs/laravel.log` in local/dev. Point
  `MAIL_*` at a real transport for production.
- PHP is not installed in the build sandbox, so `php -l` / automated tests were
  not run here; changes were reviewed manually and follow Laravel 12 APIs.
- The shadowed `materials.show` route is intentionally left intact — it is
  scheduled for Phase 2 (CP 2.1).


---

## Phase 2 — Routing & Data-Integrity Bugs (2026-07-14)

### CP 2.1 — Removed shadowed `materials.show` route
- `routes/web.php`: **removed** the top-level `GET /lessons/{material:slug}` route (name `materials.show`).
- Reasons (all verified against the code): it was **unreachable** because `GET /lessons/{lesson:slug}` (`lessons.show`) declared above it matches the identical single-segment shape; it pointed at `MaterialController@show`, a method that **does not exist**; and it bound on a `slug` column the `materials` table/`Material` model **does not have**.
- Confirmed nothing references `route('materials.show')` anywhere. Materials are correctly served by `lessons.material.show` (`/lessons/{lesson:slug}/materials/{material}` → `LessonController@material`), which is what `lesson.blade.php` links to. Left an explanatory comment in place of the deleted route.

### CP 2.2 — Registration institution list now comes from the database
- `resources/views/register.blade.php`: replaced the hard-coded `<option>Instansi A/B/C` (none of which matched any seeded institution) with a `@forelse` loop over `$institutions`, using the `@selected(...)` directive to preserve the chosen value on validation error, plus an `@empty` fallback.
- `app/Http/Controllers/Auth/RegisterController.php`:
  - `showRegistrationForm()` now passes `$institutions` = distinct, non-null `instansi` values from the `users` table (so registrants can only join a real, existing institution — e.g. the seeded "Hotel A", "Hotel B", "EngageEnglish HQ").
  - `register()` validation for `instansi` changed to `Rule::exists('users','instansi')` so submissions must match an existing institution.
  - Confirmed `role` is still never accepted from the request (defaults to `user` at the DB level) — no privilege-escalation vector added.

### CP 2.3 — Seeder integrity: materials now seeded; stale ModuleSeeder removed
- **Correction to the roadmap:** the roadmap said to "register ModuleSeeder". On inspection that would be **harmful**: `VocabularySeeder` is the canonical seeder — it already truncates and rebuilds all 8 modules + 24 lessons + vocabulary. `ModuleSeeder` was a stale duplicate that truncated Module/Lesson and created a *different, smaller* 4-module set with mismatched titles; wiring it would corrupt the canonical data. It was referenced nowhere, so it was **deleted**.
- `database/seeders/MaterialSeeder.php` (**new**): the real data-integrity gap was that **no seeder created materials at all**. This seeds one generic "Lesson Overview" text material per lesson. Idempotent via `updateOrCreate` keyed on `(lesson_id, type)` and `(material_id, title)`; it does not truncate, so it never duplicates rows or orphans polymorphic completions. Copy is generic scaffolding derived from each lesson's title (clearly not authoritative content).
- `database/seeders/DatabaseSeeder.php`: rewrote the call list with explicit, dependency-correct ordering — `VocabularySeeder` → `MaterialSeeder` → `ExerciseSeeder` → `UserSeeder` — with documentation of why order matters.

### Standalone
- No change. All Phase 2 fixes are backend (routing, registration form, database seeders). The standalone's build inputs (its embedded content = modules → lessons → vocab/exercises) are unaffected; materials, registration, and server routes do not exist in the standalone. Re-delivered unchanged.

### Limitations
- PHP is not installed in the build sandbox, so `php -l` / `artisan` / tests could not be run. Changes were reviewed manually against Laravel 12 APIs (`Rule::exists`, `@selected`, `updateOrCreate`).
- `ExerciseSeeder` still uses hard-coded `lesson_id` values (with author comments admitting uncertainty about the correct IDs). Left untouched this phase to avoid guessing content mapping; flagged for a later pass.
- The superadmin `Route::resource('materials', ...)` still exposes `create`/`edit`/`show` actions that `MaterialController` does not implement (the UI uses inline modals instead). Out of scope for Phase 2; noted for a later robustness pass.

## Phase 3 — Progress Tracking (Learning Completion)

### CP 3.1 — Full lesson completion (backend + activity views)
- **app/Models/Lesson.php**: `getProgressFor(User)` now measures completion across ALL three completable unit types — vocabulary items, material items, and exercises — in both the numerator and the denominator. Previously it counted vocabulary only, so finishing exercises or materials could never move a lesson past its vocabulary-only percentage. Added `MaterialItem` and `Exercise` imports; returns an integer 0–100 and `0` when a lesson has no units (division-by-zero guard).
- **app/Models/Module.php**: `getProgressForUser` eager-load now includes `materials.items` alongside `vocabularies.items` and `exercises` for consistency.
- **resources/views/exercise.blade.php**: fixed a real bug — the finish handler called `allItems.map(...)` with type `'VocabularyItem'`, but `allItems` is not defined in this view (only `allExercises` exists), so completing an exercise set threw a ReferenceError and recorded nothing. It now marks `allExercises` as type `Exercise`.
- **resources/views/material.blade.php**: the material viewer never recorded any completion. Finishing the last item now marks all `$material->items` as `MaterialItem` before redirecting back to the lesson, so material progress is actually tracked.

### CP 3.2 — Standalone progress parity
- Added `lessonUnitStats(l)` which counts vocabulary items + exercises (the standalone has no materials concept, mirroring the backend's available units).
- `moduleProgress`, `overallProgress`, and the lesson-row completion check now use combined vocab+exercise units, matching the web app's rollup. A lesson shows its completed checkmark only when every vocabulary item AND every exercise is done.
- Corrected hero stats: the "Vocabulary" figure now reports the true vocabulary count (244) rather than the combined unit total; the progress label reads "X / Y completed".
- Verified: renders with balanced braces (827/827) and no console errors.

### Limitations
- PHP is not installed in this environment, so `php -l` / artisan / Pest could not be executed; backend changes were reviewed manually against Laravel 12 APIs. The standalone was verified by headless render.

## Phase 4 — Exercise Renderers (sound_sorting & sequencing)

Two exercise types existed in seeded content — `sound_sorting` (exercise e4, Lesson 1) and `sequencing` (exercise e35, Lesson 11) — but had no renderer in the Laravel activity view, so they displayed "Tipe latihan '…' belum diimplementasikan." Both are now implemented in `resources/views/exercise.blade.php`, matching the existing renderer conventions (`ui.gameContainer`, `showFeedback`, `playAudio` TTS) and the behavior already shipped in the standalone edition.

### CP 4.1 — sound_sorting
- Schema: `{ categories: [{ name, id }], words: [{ word, category_id }] }`.
- Renders a shuffled word pool plus one bucket per category. Tapping a word selects it and speaks it via Web Speech TTS; tapping a bucket places the selected word there. The Check button appears only once every word has been placed. On check, each word is colored green/red against its correct category and overall feedback is shown.

### CP 4.2 — sequencing
- Schema: `{ steps: […] }` where the data order is the correct order (also tolerates `sequence`/`order` keys and step objects with `text`/`step`).
- Renders the steps shuffled (guaranteed different from the answer) with up/down reorder controls; on check each row is colored against its correct position.

### Verification
- `node --check` on the extracted `<script>` block: syntax OK.
- Headless-browser (Chromium) harness with mock data: both renderers build the DOM without throwing, TTS fires, correct placement yields a correct result, and the sequencing check grades the current order.

### Notes & limitations
- The standalone edition (`StayReady-Standalone.html`) already implemented both renderers and their icons in a prior build, so it was NOT regenerated this phase (no build-input change on the standalone side).
- PHP is not installed in this environment, so the Blade view was validated via `node --check` + a JavaScript harness rather than a live Laravel render.

## Phase 5 — Internationalization & Branding (2026-07-14)

### CP 5.1 — Internationalization (i18n) infrastructure
- Added `lang/en.json` and `lang/id.json` (Laravel 12 JSON string translations). English strings are the translation keys; Indonesian values professionally translated.
- Added `app/Http/Middleware/SetLocale.php`: session-driven locale resolution with a `SUPPORTED = ['en','id']` allowlist, falling back to `config('app.locale')`. An arbitrary session value can never force an unsupported locale.
- Registered `SetLocale` on the `web` middleware group in `bootstrap/app.php` via `$middleware->web(append: [...])` (modern Laravel 11/12 API).
- Added `GET /locale/{locale}` route (name `locale.switch`) that validates against `SetLocale::SUPPORTED`, stores the choice in the session, and redirects back.
- Localized the entire landing page (`welcome.blade.php`) with ` __('...') ` (22 strings) and added an EN | ID language switcher (desktop + mobile). `<html lang>` is now dynamic via `app()->getLocale()`.
- Verified: all 22 `__()` keys used on the landing page resolve in BOTH `en.json` and `id.json` (0 missing).

### CP 5.2 — Brand unification (EngageEnglish -> StayReady)
- Canonical brand is now **StayReady** (matches the standalone edition, the course content "Youkata Stay Hotel", and the delivered artifacts).
- Two-tone wordmark `Engage`+`English` -> `Stay`+`Ready` across all learner/auth views: `dashboard`, `lesson`, `login`, `module`, `register`, `welcome` (header + footer), `auth/reset-password`, `auth/forgot-password`, `auth/verify-email`.
- Role wordmarks: `superadmin/sidebar` -> `StayReady Admin`; `supervisor/dashboard` -> `StayReady Supervisor`.
- Rebranded landing copy: page `<title>`, "Why Choose StayReady?", testimonial, CTA, and footer copyright.
- `database/seeders/UserSeeder.php`: instansi `EngageEnglish HQ` -> `StayReady HQ`.
- `.env.example`: `APP_NAME=Laravel` -> `APP_NAME=StayReady`.
- Note: the feature copy "Engage in real conversations..." is legitimate English wording (verb), not a brand reference, and was intentionally kept.

### CP 5.3 — Fix welcome.blade.php placeholder links
- Dead `href="#"` CTAs wired to real, existing destinations: logo (header + footer) -> `/`; "Get Started" (header + mobile), "Start Your Free Trial", "Sign Up For Free" -> `/register` (the named `register` route); "How it Works" -> in-page `#features`.
- In-page nav anchors (`#features`, `#testimonials`, `#contact`) were already valid (matching section ids) and were kept.
- Social icons: kept as `href="#"` intentionally (no real social accounts exist; inventing URLs would be fabrication) and given `aria-label`s for accessibility.

### CP 5.4 — Tailwind token normalization
- Standardized the color scale to Tailwind `neutral-*` (the codebase-dominant token). Converted all `*-gray-*` utilities to `*-neutral-*` in `exercise.blade.php` (13), `material.blade.php` (11), and `lesson.blade.php` (1). Zero `gray-` tokens remain in views.

### Verification
- JSON: `lang/en.json` and `lang/id.json` parse cleanly (python json.tool).
- i18n: all 22 landing-page translation keys resolve in both locales.
- Caught & fixed two self-introduced bugs during QA: (1) literal `\u00a9`/`\u2014`/`\u2019` escape text appearing in the Blade instead of real characters; (2) an unescaped apostrophe inside a single-quoted PHP `__('...')` string (now `It\'s`) that would have been a PHP parse error.
- Brace/paren balance verified for `welcome.blade.php`, `bootstrap/app.php`, `routes/web.php`, and `SetLocale.php`.

### Standalone (StayReady-Standalone.html)
- NOT regenerated this phase. It is already branded "StayReady", styles via CSS custom properties (no Tailwind `gray/neutral` tokens), is a single offline SPA with functional in-app navigation (no dead `#` links), and its content is already bilingual EN/ID. No build input changed, so no regeneration was warranted. Re-delivered unchanged to keep a matched source+standalone pair.

### Limitations
- PHP is not installed in the build sandbox, so `php -l`/artisan could not be run. Backend/Blade correctness was validated by manual review against Laravel 12 APIs, JSON parsing, translation-key resolution, and brace/paren balance.
- i18n coverage this checkpoint is the landing page + infrastructure. The remaining authenticated views (dashboard, lesson, exercise, etc.) can now be incrementally wrapped in `__()` using the established `en.json`/`id.json` + `SetLocale` foundation.

## Phase 6 — Audio & Speech (Text-to-Speech reliability)

### CP6.1 — Use Text-to-Speech, not Speech Recognition (ASR)
- **Problem:** The speaking/pronunciation exercises graded the learner via the Web Speech API's `SpeechRecognition` (ASR). ASR is unreliable for this use case: it is only implemented in Chromium browsers (as `webkitSpeechRecognition`), is absent in Firefox and inconsistent in Safari, requires a live network connection and microphone permission, and mis-scores accented/learner speech — producing false negatives.
- **Fix (source `resources/views/exercise.blade.php`):**
  - Rewrote `renderPronunciationDrill()` from an ASR record-and-grade flow into a TTS "listen & repeat" model: it speaks the model pronunciation (auto-plays once + replay button) and lets the learner self-pace with "Selanjutnya". No microphone, no network, works in every browser.
  - Replaced the `renderSpeakingPractice()` stub (which only showed an `alert('...akan diimplementasikan...')`) so it now delegates to the same TTS listen-&-repeat renderer. Both `speaking_practice` and `pronunciation_drill` exercise types are now functional.
  - Removed all `SpeechRecognition` / `webkitSpeechRecognition` usage from the source.
- **Fix (standalone `StayReady-Standalone.html`):** Stripped the `SpeechRecognition` "Try saying it" branch from `speakingRenderer()`; it now plays the model via the existing `speak()` TTS helper (auto-play + speaker button) and keeps the "Done practicing" self-completion. No ASR remains.
- **Fix (source `resources/views/material.blade.php`):** The "Gambar dengan Audio" branch called `playAudio(item.title)`, but `playAudio` was never defined in this view (only `playMedia`) — clicking the button threw `ReferenceError`. Added a proper TTS `playAudio()` implementation.

### CP6.2 — Asynchronous voice initialization (`voiceschanged`)
- **Problem:** All source TTS helpers created an utterance with `lang='en-US'` but never selected a voice and never accounted for the fact that `speechSynthesis.getVoices()` returns an empty list until the browser fires the asynchronous `voiceschanged` event. On Chrome this causes the first playback to be silent or to use an unintended default/locale voice.
- **Fix:** Added a shared voice cache in each source view (`exercise`, `practice`, `material`) that is populated on load and refreshed on `speechSynthesis.onvoiceschanged`. Each `playAudio()` now re-checks the cache and assigns an explicit English voice: `VOICES.find(en-US)` with a fallback to any `en-*` voice. (The standalone already implemented this pattern via `refreshVoices()`/`onvoiceschanged` — left intact.)

### Verification
- No `SpeechRecognition`/`webkitSpeechRecognition` remain in source or standalone.
- Brace balance even in all three modified views; 3 voice-init blocks present.
- Standalone inline JS passes `node --check`.
- No double-brace/placeholder corruption introduced in Blade files.
- **Limitation:** PHP is not installed in the build sandbox, so `php -l`/artisan could not run; Blade+JS correctness was validated by structural review, brace/paren balance, and `node --check` on the standalone's JS.

## Phase 7 — Automated Tests & Continuous Integration

### Testing framework decision (PHPUnit, not Pest)
- The roadmap named "Pest" for this phase. On inspection the project has **no Pest installed**: `composer.json` `require-dev` lists only `phpunit/phpunit ^11.5.3`, there is no `tests/Pest.php`, and the `pestphp/pest` strings in `composer.lock` are only version constraints referenced inside *other* packages' metadata (there is no installed `"name": "pestphp/pest"` entry).
- Adding Pest would require regenerating `composer.lock` via Composer, which cannot run here (no PHP/Composer in the build sandbox). Hand-editing the lock would be fabrication and would break CI's `composer install` ("lock file out of date"). Per the project's no-hallucination / most-reliable-technique rules, the suite is therefore built on **PHPUnit**, which is already fully locked, configured (`phpunit.xml` with in-memory SQLite), and runnable in CI.
- **Migrating to Pest later (when Composer is available) is one command:** `composer require pestphp/pest --dev --with-all-dependencies && ./vendor/bin/pest --init`. The existing PHPUnit tests continue to run unchanged under Pest.

### CP7.1 — Test suite
- Added model factories that previously did not exist (only `UserFactory` shipped), matching each migration's exact columns: `ModuleFactory` (+`draft()` state), `LessonFactory`, `VocabularyFactory`, `VocabularyItemFactory`, `MaterialFactory`, `MaterialItemFactory`, `ExerciseFactory` (JSON `content`).
- Removed the scaffold `tests/Unit/ExampleTest.php` and `tests/Feature/ExampleTest.php`.
- Added meaningful tests that lock in earlier phases' fixes:
  - `tests/Unit/UserRoleTest.php` — `isSuperAdmin()`/`isSupervisor()` role predicates (no DB).
  - `tests/Feature/WelcomePageTest.php` — landing page renders (200) and shows the StayReady brand (Phase 5).
  - `tests/Feature/LocaleSwitchTest.php` — `locale.switch` stores only supported locales in session, ignores unsupported ones (Phase 5).
  - `tests/Feature/AuthAccessTest.php` — guests are redirected from `/dashboard` to `/login`; login & register screens render.
  - `tests/Feature/RegistrationTest.php` — registration requires an existing `instansi` (Phase 2 `Rule::exists`) and cannot escalate `role` (privilege-escalation guard).
  - `tests/Feature/ProgressStoreTest.php` — progress endpoint rejects out-of-allowlist completable types (Phase 1 hardening, 422), records valid completions, is idempotent, and blocks guests.
  - `tests/Feature/LessonProgressTest.php` — lesson percentage counts vocabulary + material + exercises (Phase 3 fix); module and overall aggregation; only published modules count toward overall progress.

### CP7.2 — Continuous Integration
- Added `.github/workflows/tests.yml` (GitHub Actions): checkout → `shivammathur/setup-php@v2` (PHP 8.2 + sqlite/pdo_sqlite/mbstring/intl) → Node 20 → copy `.env` → `composer install` → `php artisan key:generate` → `npm ci && npm run build` → `php artisan test`, plus a non-blocking `pint --test` style check.
- **Asset build ordering matters:** the `welcome`, `login`, and `register` views use the `@vite` directive, so their feature tests need a built manifest. CI runs `npm run build` *before* `php artisan test`; locally you must do the same (`npm run build`) or those view-rendering tests fail with "Vite manifest not found". Tests use `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` from `phpunit.xml`, so no DB service is required.

### Verification & limitations
- All 15 new PHP files verified: exactly one `<?php` tag, balanced braces/parens/brackets, no double-brace/placeholder corruption. CI workflow YAML validated (parses; no `$ ` expressions used, to avoid arg-encoding corruption).
- **Limitation:** PHP/Composer are not installed in this sandbox, so the suite could not be executed here (`php artisan test` not run) and pint could not be applied. Tests were written against the verified schema, models, controllers, routes, and middleware; run `php artisan test` in an environment with PHP 8.2 + Composer (or let the CI workflow run) to execute them.
- **Standalone unchanged this phase:** Phase 7 adds backend tests + CI only; it does not touch the standalone's data or renderers, so no new `StayReady-Standalone.html` was generated.

## Phase 8 — Seeders: canonical data & idempotent, database-agnostic seeding

### CP8.1 — Course content extracted to a single canonical JSON source
- **Problem:** the entire course (8 modules, 24 lessons, 244 vocabulary items) was hard-coded as a ~490-line PHP array literal inside `VocabularySeeder`. Content and seeding logic were entangled, the data was not reviewable/consumable as data, and any edit meant touching executable PHP.
- **Fix:** extracted the course into a canonical data file, `database/data/course.json` (`{ "modules": [ { title, description, lessons: [ { title, vocabularies: [ { category, term, details } ] } ] } ] }`). The extraction was done programmatically from the existing PHP (not re-typed) and verified against independent counts (8 / 24 / 244) plus exact spot-checks, so no content was altered or fabricated.
- `VocabularySeeder` is now ~90 lines: it reads `course.json` and upserts it. It remains the single canonical source of Modules + Lessons + Vocabulary.

### CP8.1 — Idempotent, database-agnostic seeders (real portability bug fixed)
- **Bug:** `VocabularySeeder` and `UserSeeder` ran MySQL-only statements — `DB::statement('SET FOREIGN_KEY_CHECKS=0/1')` followed by `Model::truncate()`. These **throw on SQLite**, which is exactly what the Phase 7 test suite and CI use (`phpunit.xml` → `DB_CONNECTION=sqlite`, `:memory:`). Running `php artisan db:seed` (or a seeded test) under SQLite/Postgres would fail.
- **Fix:** all seeders now use `updateOrCreate` keyed on natural keys and never truncate:
  - Modules keyed on `slug`; Lessons keyed on `slug`; Vocabulary categories on `(lesson_id, category)`; Vocabulary items on `(vocabulary_id, term)`; Users on `email`; Exercises on `(lesson_id, title)`.
  - Verified there are zero key collisions in the data (0 duplicate `(category, term)` pairs; 0 duplicate `(lesson_id, title)` pairs), so upserts are lossless.
  - This is the modern Laravel-recommended idempotent-seeding pattern (matches the existing `MaterialSeeder`), is safe to re-run, and does not orphan the polymorphic `completions` rows that FK-reference vocabulary items / users. It runs unchanged on MySQL, SQLite and PostgreSQL.
- Deterministic ordering: modules/lessons/categories/items now get an explicit 1-based `order` reflecting canonical sequence (previously unset).

### CP8.1 — ExerciseSeeder made idempotent and robust to id drift
- **Bug 1 (idempotency):** every exercise used `Exercise::create(...)`, so re-running the seeder duplicated all 36 exercises.
- **Bug 2 (fragile references):** exercises were attached via hard-coded numeric `lesson_id` values (1–7, 9, 11) — the code even carried `// Ganti dengan ID yang benar` ("replace with the correct id") comments — which silently break if lesson ids ever shift.
- **Fix:** all 36 `Exercise::create(...)` calls now route through a private `store()` helper that (a) treats the number as a **1-based canonical ordinal** and resolves it to the real lesson id via an ordered `Lesson` lookup built once per run, and (b) upserts with `updateOrCreate` keyed on `(lesson_id, title)`. Exercise content was left byte-for-byte untouched; only the call site and lesson resolution changed.

### Verification & limitations
- `course.json` is valid JSON with exact counts 8 / 24 / 244. All 5 seeders: exactly one `<?php` tag, balanced braces/parens/brackets, no double-brace/placeholder corruption. No `FOREIGN_KEY_CHECKS`/`truncate()` remain in executable code (only in explanatory comments). 36/36 exercises routed through the idempotent helper; all exercise ordinals resolve to valid lessons (max 11 ≤ 24).
- **Standalone parity:** the standalone already embeds exactly 8 modules / 24 lessons / 244 terms / 36 exercises — identical to the canonical JSON — so it stays in sync. Phase 8 changes only backend seeding (no standalone build input changed); the standalone is therefore unchanged this phase and re-delivered as-is.
- **Limitation:** PHP/Composer are not installed in this sandbox, so `php artisan db:seed` and `php -l` could not be executed here. Correctness was validated by faithful programmatic extraction with count/spot verification, brace/paren/bracket balance, key-collision analysis, and ordinal-resolution checks. Run `php artisan migrate:fresh --seed` in a PHP 8.2 environment (or via CI) to execute.

---

# Fix & Improvement Plan (Pre-Content-Scale) — second roadmap

> An independent 8-phase roadmap authored from a static review of the Phase 8
> source (see the "StayReady — Fix & Improvement Plan (Pre-Content-Scale)"
> spec). Its phase numbers are **separate** from the "Phase 1–8"
> security/feature roadmap above and are delivered as checkpoints CP1–CP8
> (Source.zip + Standalone.html).

## FIP Phase 1 — Remove dead code & broken routes (2026-07-15)

**Objective:** delete the non-functional legacy progress path and stop
registering broken endpoints, so later phases build on a correct route/model
surface. **Backend-only; the standalone HTML is unchanged this phase.**

### 1.1 — Deleted the legacy `Progress` system
- Deleted `app/Models/Progress.php` — an Eloquent model with no backing
  `progresses` migration/table, so any use of it 500s.
- Removed the `progress()` relation (`hasMany(Progress::class)`) from
  `app/Models/User.php`. The correct polymorphic `completions()` relation is
  left untouched.
- Removed `LessonController@markAsComplete()`, which called `$lesson->progress()`
  — a relation that does not exist on `Lesson` and has no table, so the endpoint
  always 500s. Also dropped the now-unused
  `use Illuminate\Support\Facades\Auth;` import (Auth was only referenced there).
- Removed the `POST /lessons/{lesson}/complete` route (`lessons.complete`) from
  `routes/web.php`.
- **No Blade change needed:** a whole-repo grep found `lessons.complete` /
  `markAsComplete` only in the route + controller. The user-menu forms in
  `dashboard.blade.php` / `module.blade.php` post to `logout`, not to
  `lessons.complete`, so no view was modified. (The spec's "~L60/~L58" note did
  not match this source; verified rather than assumed.)

### 1.2 — Constrained resource routes to implemented actions
- Every `superadmin` `Route::resource(...)` is now
  `->only(['index','store','update','destroy'])` (admin uses a modal-in-index;
  `create`/`edit`/`show` were unimplemented and errored).
- Removed `ModuleController@create` and `ModuleController@edit` — they returned
  the non-existent `superadmin.modules.create` / `edit` views (confirmed: no
  such Blade files exist under `resources/views/superadmin/`).
- Removed `ExerciseController@show` — bound to `exercises.show` with an
  unresolvable `Lesson $lesson` route param and a wrong view variable.
- Kept the legitimate learner `ModuleController@show` (→ `modules.show`) and
  `LessonController@show` (→ `lessons.show`); these are GET slug routes, not
  admin resource actions.
- `VocabularyController` and `MaterialController` already expose only
  index/store/update/destroy, so `->only(...)` maps cleanly with no orphaned
  methods.

### Verification & limitations
- Grep confirms **zero** remaining references to the `Progress` model, the
  `progress()` relation, `lessons.complete`, `markAsComplete`, or
  `superadmin.modules.create/edit`.
- All modified PHP files: exactly one `<?php` tag and balanced
  braces/parens/brackets.
- **Limitation:** PHP/Composer are not installed in this sandbox and no package
  mirror is reachable, so `php artisan route:list` and `php artisan test` could
  not be executed here. Correctness was validated by exact-string edits,
  full-repo reference grep, and brace-balance checks. Run
  `npm run build && php artisan test` in a PHP 8.2 environment (or via the CI
  workflow) to confirm green.
- **Standalone unchanged:** Phase 1 touches only backend routing/models; no
  learner-facing exercise logic or markup changed, so `StayReady-Standalone.html`
  is re-delivered byte-for-byte as received.

## FIP Phase 2 — Shared Blade layout & unified styling (2026-07-15)

> Checkpoint 2 of the Fix & Improvement Plan (Pre-Content-Scale). Scope: remove
> the copy-pasted HTML boilerplate across every Blade view and centralize the
> document shell + shared chrome. Backend behavior is unchanged.

### 2.1 — Introduced two shared layouts
- Added `resources/views/layouts/app.blade.php` (authenticated app pages) and
  `resources/views/layouts/guest.blade.php` (public/auth pages). These are now
  the **only** two files that declare `<!DOCTYPE>`, `<html>`, `<head>`, and
  `<body>`.
- Both layouts centralize: charset/viewport meta, a global
  `<meta name="csrf-token">`, Font Awesome 6.5.1, the Inter Google Font,
  `body { font-family: 'Inter' }`, and `@vite(['resources/css/app.css',
  'resources/js/app.js'])`. They expose `@yield('title')`,
  `@yield('bodyClass')`, `@yield('content')`, and `@stack('styles')` /
  `@stack('scripts')`.
- `<html lang>` now derives from `app()->getLocale()` instead of being hardcoded
  (previously a mix of `en` / `id`), so it tracks the active i18n locale.

### 2.2 — Migrated all 19 views onto the layouts
- Every view now `@extends` a layout and provides `@section('content')`; page
  titles are preserved via `@section('title')`, body classes via
  `@section('bodyClass', ...)`, and page-specific `<style>` blocks via
  `@push('styles')`.
- Guest layout: `welcome`, `login`, `register`, `auth/{forgot-password,
  reset-password, verify-email}`.
- App layout: `dashboard`, `module`, `lesson`, `practice`, `material`,
  `exercise`, `supervisor/dashboard`, `superadmin/dashboard`, and the five
  `superadmin/*/index` views.
- Extracted the learner top-nav + profile dropdown (duplicated verbatim in
  `dashboard`, `module`, `lesson`, including its toggle script) into
  `resources/views/partials/learner-nav.blade.php`, included via
  `@include('partials.learner-nav')`.
- Bug fixes that fall out of centralizing the shell:
  - `material.blade.php` reads `<meta name="csrf-token">` in its progress-save
    fetch but never declared that meta tag — the layout now provides it, so
    material progress saving no longer sends a null CSRF token.
  - `welcome.blade.php` used Font Awesome icons without ever loading Font
    Awesome; the guest layout now loads it.
- Removed the dead `.transition-enter-*/.transition-leave-*` CSS that was
  copy-pasted into the three learner views but never referenced by their JS.

### 2.3 — Unified front-end asset loading
- **Tailwind:** already fully on the Vite/`@tailwindcss/vite` pipeline in this
  source (no `cdn.tailwindcss.com` tags existed), so no Tailwind-CDN removal was
  required. All views now inherit the single `@vite(...)` include from the
  layout instead of each importing `resources/css/app.css` on its own.
- **Alpine.js:** the five `superadmin/*/index` views each loaded Alpine from
  jsDelivr (5 duplicate `<script>` tags). This is consolidated into a single
  deferred include in `layouts/app.blade.php`.
  - **Follow-up (deferred):** the modern end state is to `npm install alpinejs`
    and `import 'alpinejs'` in `resources/js/app.js`, dropping the CDN entirely.
    That requires a network-enabled `npm install` + `vite build`, which this
    offline sandbox cannot run (no network, no `node_modules`), so the single
    CDN include is kept as an interim, behavior-preserving consolidation.

### Verification & limitations
- Structural DoD (all pass):
  - `<!DOCTYPE>`, `<body>`, `</html>` appear **only** in the two layout files.
  - The Alpine CDN tag appears **only** in `layouts/app.blade.php` (was 5).
  - Exactly 19 views `@extends` a layout; `@section/@endsection` and
    `@push/@endpush` are balanced in every view.
  - No per-page `@vite('resources/css/app.css')` or per-page Font Awesome /
    Google-Fonts `<link>` tags remain outside the layouts.
- Content-preservation check: for the 16 non-nav views the migrated
  `@section('content')` body is **byte-identical** to the original `<body>`
  inner HTML; for the three learner-nav views the only delta is the extracted
  navbar + dropdown script (now in the shared partial). Every dynamic Blade
  expression (`@forelse`, `@csrf`, ` ... `, route/auth helpers) is retained
  verbatim.
- **Limitation:** PHP is not installed and cannot be installed in this sandbox
  (no network / no package mirror), so `php artisan view:cache`,
  `php artisan test`, and a real Blade compile could not be executed here.
  Validation is static (structural + byte-level content diff). Run
  `npm run build && php artisan test` (or CI) in a PHP 8.2 environment to
  confirm the compiled render.
- **Standalone unchanged:** Phase 2 is a Blade-template refactor only; the
  rendered HTML output and all user-facing markup/behavior are identical, and
  the standalone build has no Blade layer, so `StayReady-Standalone.html` is
  re-delivered byte-for-byte.

## FIP Phase 3 — Extract the exercise engine into a Vite JS module

Goal: remove all renderer/business logic from `resources/views/exercise.blade.php`
so adding or changing an exercise type is an isolated JS change, not a Blade edit.

### 3.1 — New dedicated Vite module
- Added `resources/js/exercises/index.js` (ES module): the full exercise engine,
  moved **verbatim** from the ~700-line inline `<script>` that lived in
  `exercise.blade.php`. All 14 type renderers, helpers, and game logic are
  unchanged in behavior.
- Added `resources/js/exercises/exercises.css`: the ~16 CSS rules from the page's
  `@push('styles')` block, now `import`ed by the module so they are bundled and
  loaded only on the exercise page.
- Registered the new entry in `vite.config.js`
  (`input: [..., 'resources/js/exercises/index.js']`). `resources/js/app.js` is
  intentionally left unchanged — a dedicated entry scopes the engine + its CSS to
  the exercise page only, with zero impact on other pages.

### 3.2 — Type-keyed renderer registry (no more switch)
- Replaced the `switch (exercise.type)` dispatch with a `RENDERERS` object keyed
  by exercise `type`. `renderCurrentExercise()` now looks up
  `RENDERERS[exercise.type]`, preserving the same "not implemented" fallback.
  Adding a new type = add one renderer function + one registry entry.

### 3.3 — Slimmed Blade view
- `exercise.blade.php` is now a pure scaffold: **51 lines / 3,495 bytes**
  (was 824 lines / 40,734 bytes). It contains no renderer logic, no `<script>`,
  no inline `onclick`, and no `@push('styles')`.
- The view passes server data to the module via `data-*` attributes on
  `#exercises-data`: `data-exercises` (existing), plus new `data-return-url`
  (`route('lessons.show', $lesson)`) and `data-progress-url`
  (`route('progress.store')`). The Blade route expressions were copied at the
  byte level from the view's own back-link and from `practice.blade.php`, not
  retyped. The module reads the CSRF token from the shared
  `<meta name="csrf-token">` added to the app layout in Phase 2.
- The module is loaded with `@push('scripts') @vite('resources/js/exercises/index.js') @endpush`,
  which renders into the `@stack('scripts')` slot of `layouts/app.blade.php`.

### 3.4 — Bug fix surfaced during extraction
- **`markItemsAsComplete` was undefined on the exercise page.** The inline script
  *called* `markItemsAsComplete(...)` when finishing the last exercise, but the
  function was only ever defined in `practice.blade.php` and `material.blade.php`
  — never in `exercise.blade.php`. Finishing a lesson's exercises therefore threw
  a `ReferenceError`, so exercise progress was never saved to the server and the
  redirect back to the lesson never fired. The module now defines
  `markItemsAsComplete` (mirroring the practice/material implementation: `POST`
  to `progressUrl` with the CSRF header, errors caught and logged), fixing both
  the progress save and the redirect.
- **2 inline `onclick="playAudio(...)"` handlers** (in the spelling-quiz and
  listening-task renderers) were converted to `addEventListener` bindings,
  because `playAudio` is no longer a global — it is module-scoped. Behavior is
  identical.

### Verification & limitations
- **Static DoD (all pass):** `exercise.blade.php` contains 0 `function render*`,
  0 `<script>`, 0 `onclick=`, 0 `@push('styles')`; exactly 1 `@push('scripts')`
  + `@vite` of the new entry; `data-return-url` + `data-progress-url` present
  once. `node --check` passes on `resources/js/exercises/index.js`.
- **Real headless-browser smoke test (Chromium):** a harness served over HTTP
  loaded the module against a scaffold containing one exercise of every type and
  drove it. Result: **RENDERERS has all 14 keys (no missing/extra)**, **all 14
  types render with 0 uncaught JS errors**, and **scoring is verified correct**
  for the 12 interactive types (spelling_quiz, matching_game, fill_in_the_blank,
  listening_task, sentence_scramble, translation_match, fill_with_options,
  multiple_choice_quiz, fill_multiple_blanks, silent_letter_hunt, sound_sorting,
  sequencing). The 2 TTS drills (speaking_practice, pronunciation_drill) have no
  scoring by design and were confirmed to render.
- **Limitation — no build / no PHP in sandbox:** this offline sandbox has no
  network, no `node_modules`, and no PHP, so `npm run build` (Vite) and
  `php artisan test` / a real Blade compile could not be executed here. Note the
  source already ships without a `public/build` manifest, so `@vite` always
  required a build step regardless; adding a new Vite input does not change that.
  Run `npm ci && npm run build && php artisan test` in CI / a PHP 8.2 env to
  confirm the compiled bundle and render.
- **Standalone unchanged:** Phase 3 only restructures the Laravel source's JS/CSS
  organization; the rendered exercise markup and behavior are identical. The
  `markItemsAsComplete` fix targets server-side progress persistence, which the
  standalone build does not have (it uses `localStorage`). No learner-facing
  scoring markup changed, so `StayReady-Standalone.html` is re-delivered
  byte-for-byte (sha256 d86cbeba…b17c72).

## FIP Phase 4 — Dashboard & progress performance

Goal: remove the query blow-up that grew ~linearly with lesson count. Previously
`DashboardController::index()` eager-loaded the relations, then threw them away by
calling `Lesson::getProgressFor()` per lesson — which re-queried
`vocabularies()->with('items')`, `materials()->with('items')` and `exercises()`,
**plus 3 `count()` queries per lesson**. `Module::getProgressForUser()` and
`User::getOverallProgress()` repeated the same pattern.

### 4.1 — Compute from eager-loaded data, one completions query
- **`Completion::completedKeysFor(User, array $idsByType)`** (new): fetches the
  user's relevant completions in **a single bounded query** (zero when there is
  nothing to look up) and returns an O(1) lookup keyed by `"{type}|{id}"`. Ids
  are OR'd per completable type (type + `whereIn` ids), so an id shared across
  two completable types can never be mis-attributed.
- **`Lesson`** (refactored, no new queries when relations are eager-loaded):
  - `completableIdsByType()` reads ids from the already-loaded
    `vocabularies.items` / `materials.items` / `exercises` relations
    (`loadMissing` keeps it safe when called standalone).
  - `progressFromKeys(array $completedKeys)` computes the 0-100 percentage purely
    in PHP — **issues no queries**.
  - `getProgressFor(User)` is preserved (same signature) but now bounded: it
    builds the id map and does **one** completions query.
  - `averageProgressForUser(iterable $lessons, User)` + `mergeIdsByType()` score a
    whole collection of lessons with **one** completions query.
  - `PROGRESS_RELATIONS` constant documents the relations to eager-load.
- **`Module::getProgressForUser()`** now eager-loads `Lesson::PROGRESS_RELATIONS`
  and delegates to `Lesson::averageProgressForUser()` (bounded).
- **`DashboardController::index()`** eager-loads all modules/lessons/relations,
  issues **one** `completedKeysFor` query for the whole page, and computes each
  module's progress in PHP. Query count is now **independent of the lesson
  count**. The averaging math (unrounded mean of the lessons' integer
  percentages) is kept **identical** to the previous implementation.
- **`User::getOverallProgress()`** likewise loads published modules + relations
  once and uses a single completions query across every lesson. Per-module and
  overall rounding are unchanged from before, so results are identical.

### 4.2 — Per-user progress cache
- `User::getOverallProgress()` is cached under **`progress:user:{id}`**
  (`User::progressCacheKey()`), TTL 6h, via `Cache::remember`. The supervisor
  dashboard calls this once per learner, so this avoids recomputing on every
  page load.
- `ProgressController::store()` calls `User::forgetProgressCache()` after writing
  new completions, so the aggregate is recomputed on the next read (no stale
  percentages). Cache store in tests is the `array` driver (per `phpunit.xml`),
  so the cache is process-local and reset between tests.

### Tests
- New `tests/Feature/DashboardQueryCountTest.php`:
  - `test_dashboard_query_count_does_not_grow_with_lessons`: loads `/dashboard`
    for a 3-lesson fixture, then again after adding a 20-lesson module, and
    asserts the query count is **identical** (N-independent) and `<= 15`
    (bounded). This is the Phase 4 DoD assertion via `DB::getQueryLog()`.
  - `test_dashboard_progress_values_are_correct`: 2 of 4 units completed renders
    a module progress of 50 — guards against a behavior regression.
- Existing `LessonProgressTest` (lesson/module/overall percentages) and
  `ProgressStoreTest` (store hardening + idempotency) still exercise the same
  public methods, whose signatures and results are unchanged.

### Verification & limitations
- **Static checks (pass):** public method signatures `getProgressFor`,
  `getProgressForUser`, `getOverallProgress` preserved (backward compatible); no
  per-lesson `->count()` DB calls or `vocabularies()->with(...)` re-query
  patterns remain in the progress path; brace/paren/bracket balance verified on
  every changed file.
- **Limitation — no PHP / no build in sandbox:** this offline sandbox has no PHP
  (`php -l` / `php artisan test` cannot run) and no network for `npm ci &&
  npm run build`. The query-count and percentage assertions are written but must
  be executed in CI / a PHP 8.2 environment to confirm green. Run
  `npm ci && npm run build && php artisan test` there.
- **Standalone unchanged:** Phase 4 is a backend (controller/model) change only;
  no learner-facing markup or exercise scoring changed, and the standalone build
  has no server/DB layer (it tracks progress in `localStorage`). Per the plan's
  out-of-scope rule, `StayReady-Standalone.html` is re-delivered byte-for-byte
  (sha256 d86cbeba…b17c72).

## FIP Phase 5 — Security & data integrity

Goal: close the brute-force and progress-integrity gaps, and record the
graded-quiz scoring decision.

### 5.1 — Rate-limit login
- `POST /login` had **no throttling** (the email-verification routes already use
  `throttle:6,1`). Added a named rate limiter `login` in
  `AppServiceProvider::boot()` and applied `->middleware('throttle:login')` to
  the login POST route in `routes/web.php`.
- The limiter is `Limit::perMinute(5)->by(strtolower(email).'|'.ip())` — keyed on
  **email + client IP**, so one address cannot lock out every account and one
  account cannot be hammered from a single address. Exceeding the limit returns
  **HTTP 429** (Too Many Requests). This is the standard Laravel 12 named-limiter
  technique (`RateLimiter::for(...)` + the framework `throttle` middleware).

### 5.2 — Validate progress ownership / integrity
- `ProgressController::store()` now verifies that **every** submitted `items` id
  actually exists for the resolved `completable_type` before writing. Ids are
  de-duplicated and integer-cast, looked up with a single
  `whereIn('id', $ids)->pluck('id')` on the resolved model, and any missing id
  makes the request fail with a 422 `ValidationException` on `items`.
- The guard is **all-or-nothing**: if any id is unknown, NOTHING is written, so a
  single bogus id cannot partially record progress. This blocks a client from
  inflating its own progress with completion rows that point at nothing (or at a
  different type's ids). The existing type allowlist (Phase 1) is unchanged.
- Scope note: there is no per-user enrollment model in this app — all published
  content is reachable by any verified learner — so "belongs to a lesson
  reachable by the user" reduces to "exists for this completable type", which is
  what is enforced. (The plan lists reachability as "ideally".)

### 5.3 — Graded-quiz scoring decision: DEFERRED
- Recorded in the plan page ("StayReady — Fix & Improvement Plan", Phase 5.3).
  Decision: **keep client-side scoring as-is; no code change this phase.**
  StayReady issues no certificates, grades, or rankings, and every exercise is
  self-practice, so shipping answer keys to the browser is acceptable and there
  is no authoritative server-side score to protect. If graded/authoritative
  quizzes ever enter scope, add a server endpoint that receives answers and
  returns correctness while keeping the keys server-side (and stop embedding
  `content.correct_answer`/matching `pairs` in the client payload for those
  graded types only).

### Tests
- New `tests/Feature/LoginThrottleTest.php`:
  - `test_login_is_throttled_after_too_many_attempts`: 5 attempts pass, the 6th
    within the window returns **429** (the Phase 5.1 DoD assertion).
  - `test_throttle_bucket_is_scoped_per_email`: a different email from the same
    client is not immediately throttled (verifies per-email keying).
- New `tests/Feature/ProgressOwnershipTest.php`:
  - unknown id rejected (422 + `items` error) and nothing written;
  - id belonging to a different type rejected;
  - valid id recorded;
  - mixed valid+invalid ids write **nothing** (all-or-nothing).
- Existing `ProgressStoreTest` (allowlist + idempotency) and `AuthAccessTest`
  still hold: valid single-id stores still return 200 and remain idempotent.

### Verification & limitations
- **Static checks (pass):** `RateLimiter::for('login', ...)` registered and
  `AppServiceProvider` is listed in `bootstrap/providers.php`; `throttle:login`
  attached to `POST /login`; `ProgressController` imports/uses
  `ValidationException` with the existence guard; brace/paren/bracket balance
  verified on every changed file.
- **Limitation — no PHP / no build in sandbox:** no PHP (`php artisan test` can't
  run) and no network for `npm ci && npm run build`. The new throttle and
  ownership tests are written but must be executed in CI / a PHP 8.2 environment
  to confirm green.
- **Standalone unchanged:** Phase 5 changes only backend auth/validation; no
  learner-facing markup or exercise scoring changed, and the standalone has no
  server/login/DB layer. `StayReady-Standalone.html` is re-delivered
  byte-for-byte (sha256 d86cbeba…b17c72).

---

## Phase 6 — Internationalization completion  (delivered as CP6 / FIP Phase 6)

**Scope:** learner-facing UI + exercise-engine JS + controller flash messages.
Admin (superadmin/supervisor) views and admin form JS are intentionally deferred
(not part of the learner i18n surface). The standalone HTML has no Blade/PHP
layer, so it is unchanged this phase.

### CP 6.1 — Externalize hard-coded strings via Laravel JSON translations
- Convention: translation KEY = English source string. `lang/en.json`
  (English->English) and `lang/id.json` (English->Indonesian) now hold identical
  key sets of 125 keys each (22 pre-existing welcome-page keys preserved + 103
  new keys added this phase).
- Wrapped every learner-facing Blade string with the `__()` helper across:
  `dashboard`, `module`, `lesson`, `exercise`, `material`, `practice`,
  `partials/learner-nav`, `login`, and `register`.
- Exercise engine (`resources/js/exercises/index.js`) is now localized: the
  Blade view builds an `$exerciseI18n` dictionary (24 keys, values resolved with
  `__()`), injects it as a `data-i18n` attribute (via `@json(...)`) on
  `#exercises-data`, and the JS parses it into an `I18N` map accessed through a
  `t(key, fallback, repl)` helper that supports `:token` interpolation. English
  fallbacks are retained; all console/debug logs standardized to English.
- Controller success flash messages (Exercise / Lesson / Material / Module /
  Vocabulary / Progress controllers) wrapped with `__()` and keyed to English.

### CP 6.2 — Locale key-parity test
- Added `tests/Feature/LangParityTest.php` asserting `en.json` and `id.json`
  expose identical key sets in both directions (guards against drift when new
  strings are added).

**Verification (static; no PHP runtime / no build in sandbox):**
- grep: no unwrapped hard-coded Indonesian UI strings remain in `resources/views`
  or `resources/js` (learner scope).
- `node --check` passes for `resources/js/exercises/index.js`.
- Both language files parse as JSON and have equal, matching key sets (125/125).
- Blade echo braces are balanced across all 9 edited views.

**Limitations / deferred:**
- Admin (superadmin/supervisor) views and admin form JS not localized this phase.
- No PHP available in the build sandbox, so `php artisan test` and server-side
  Blade rendering could not be executed here. Recommended CI gate:
  `npm ci && npm run build && php artisan test`.
- Standalone HTML unchanged (Blade/JS-source-only phase).

**Next action:** reply "Continue" to proceed to Phase 7.


---

## Phase 7 - Exercise content validation + client fail-safe + module slug uniqueness  (delivered as CP7 / FIP Phase 7)

**Scope:** backend request validation, the learner exercise-engine JS, and the
module admin controller. No learner-facing markup strings changed, so the
standalone HTML has no server/DB/validation layer to mirror and is unchanged.

### CP 7.1 - Server-side per-type exercise content validation (FormRequests)
- Before: `ExerciseController@store/@update` only checked `content` was an array
  and accepted any `type` string, so malformed or unknown-type exercises were
  persisted and later crashed the front-end renderer.
- Added `app/Http/Requests/ExerciseRequest.php` (abstract base) plus
  `StoreExerciseRequest.php` and `UpdateExerciseRequest.php`.
  - `type` is constrained to the 14 renderer types via `Rule::in(self::TYPES)`
    (the allowlist mirrors the `RENDERERS` registry in the exercise engine).
  - `contentRules($type)` supplies structural rules for the JSON `content`
    payload of every one of the 14 types, matching exactly what each renderer
    dereferences and what `ExerciseSeeder` produces (e.g. `matching_game.pairs.*`
    require `question`+`answer`; `sound_sorting` requires `categories` +
    `words`; `sequencing` requires >=2 `steps`, etc.).
  - `fill_in_the_blank` accepts EITHER `sentence_parts` (renderer/seeder shape)
    OR `sentence_template` (the shape the admin form actually submits) via
    `required_without`, fixing a latent admin/renderer mismatch.
  - Update path: the admin type `<select>` is `:disabled` while editing, so
    `type` is not submitted; `effectiveType()` falls back to the bound
    exercise's existing type so per-type content rules still apply, and `type`
    itself is `sometimes|nullable`.
- `ExerciseController` now type-hints the FormRequests and builds its persisted
  array from validated input (using `input('content')` to preserve every content
  sub-key rather than the pruned `validated()` subset).

### CP 7.2 - Client-side fail-safe in the exercise engine
- `resources/js/exercises/index.js`: `renderCurrentExercise` now (a) shows the
  existing not-implemented message for unknown types, (b) validates the payload
  with a new `isContentValid(type, content)` guard mirroring the server rules,
  and (c) wraps the renderer call in try/catch. On malformed content or a thrown
  renderer error it shows a localized message instead of leaving a blank/broken
  screen; side-navigation still works so the lesson is never fully stuck.
- `renderFillInTheBlank` now normalizes `sentence_template` ("...___...") into
  the two surrounding `sentence_parts`, so admin-created fill-in-the-blank
  exercises render correctly.
- New i18n key `contentError` ("This exercise could not be loaded." /
  "Latihan ini tidak dapat dimuat.") added to `lang/en.json`, `lang/id.json`
  (now 126/126, parity preserved) and to the `$exerciseI18n` map in
  `resources/views/exercise.blade.php`.

### CP 7.3 - Module slug uniqueness
- `ModuleController` previously derived slugs with a bare `Str::slug($title)`.
  Module titles are unique, but two distinct titles can slugify to the same
  value (e.g. "Cafe" vs "Cafe!"), which would violate the `slug` unique index.
- Added a private `uniqueSlug($title, $ignoreId = null)` helper that appends
  `-2`, `-3`, ... until the slug is free (ignoring the module's own row on
  update so re-saving keeps a stable slug). Used in both `store()` and
  `update()`.

### Tests
- `tests/Feature/ExerciseValidationTest.php`: unknown type rejected; MCQ missing
  options rejected; valid MCQ created; `sound_sorting` requires categories+words;
  `fill_in_the_blank` accepts the admin `sentence_template`; editing without a
  `type` still validates content against the existing type.
- `tests/Feature/ModuleSlugTest.php`: colliding titles get distinct slugs
  (`hello-world`, `hello-world-2`); updating a module keeps its own slug stable.

**Verification (static; no PHP runtime / no build in sandbox):**
- `node --check resources/js/exercises/index.js` passes.
- `lang/en.json` and `lang/id.json` both parse and expose identical 126-key sets.
- Brace/paren/bracket balance verified on every new/edited PHP and JS file.
- Integration markers confirmed: one `isContentValid` definition + call, the
  try/catch renderer guard, and the FITB normalization are all present.

**Limitations / deferred:**
- No PHP binary and no network in the sandbox, so `php artisan test` and
  `npm ci && npm run build` cannot be executed here; the new FormRequests and
  tests must be run in CI / a PHP 8.2 environment to confirm green. Recommended
  CI gate: `npm ci && npm run build && php artisan test`.
- Existing tests exercise models/factories directly (not the HTTP store/update
  endpoints), so the new FormRequests do not affect the current suite.
- Standalone HTML unchanged (backend/JS-source-only phase); re-delivered
  byte-for-byte (sha256 d86cbeba...b17c72).

**Next action:** reply "Continue" to proceed to Phase 8 (final phase - tests & CI hardening).

---

## Phase 8 - Test & CI hardening  (delivered as CP8 / FIP Phase 8 - FINAL)

**Scope:** lock in every fix from FIP Phases 1-7 with automated coverage and a
reliable CI gate. No learner-facing runtime behavior changed, so the standalone
HTML is unchanged and re-delivered byte-for-byte.

### CP8.1 - Admin route-smoke test (the one missing DoD artifact)
- Added `tests/Feature/AdminRouteSmokeTest.php` with five tests:
  - `test_admin_index_screens_render_for_superadmin`: GETs every wired admin
    screen (`superadmin.dashboard` + the `modules`/`lessons`/`vocabularies`/
    `materials`/`exercises` `.index` routes) on an empty database and asserts
    HTTP 200 - i.e. no 500s and no accidental redirects.
  - `test_admin_screens_reject_a_verified_learner_with_403`: a verified
    `role:user` learner receives 403 from `App\Http\Middleware\CheckRole`.
  - `test_admin_screens_require_authentication`: guests are redirected to
    `/login`.
  - `test_removed_resource_verbs_are_not_registered`: the `create`/`edit`/`show`
    verbs dropped in FIP Phase 1 via `Route::resource(...)->only([...])` stay
    unregistered for all five resources (they had no controller method/view and
    would 500).
  - `test_dead_legacy_routes_are_not_registered`: `lessons.complete` (legacy
    Progress model) and the shadowed top-level `materials.show` cannot be
    reintroduced.

### CP8.2 - CI ordering & seeder idempotency (verified, no change required)
- Confirmed `.github/workflows/tests.yml` installs Composer deps, generates the
  app key, then runs `npm ci && npm run build` BEFORE `php artisan test`, so the
  `@vite` manifest exists when feature tests render Blade views. Pint runs last,
  non-blocking.
- Re-audited `database/seeders/*`: seeding uses `updateOrCreate` throughout and
  contains no `truncate()` / `SET FOREIGN_KEY_CHECKS` in executable code (the
  only matches are docblock comments documenting their deliberate absence), so
  `db:seed` stays idempotent and SQLite/MySQL-portable.

**Test suite now (Feature):** AdminRouteSmoke, AuthAccess, DashboardQueryCount,
ExerciseValidation, LangParity, LessonProgress, LocaleSwitch, LoginThrottle,
ModuleSlug, ProgressOwnership, ProgressStore, Registration, WelcomePage; (Unit):
UserRole. Every FIP phase now has locking coverage.

**Verification (static; no PHP runtime / no build in this sandbox):**
- `AdminRouteSmokeTest.php`: braces/parens/brackets balanced, single `<?php`, no
  compression artifacts; 5 `test_*` methods; route names align with `routes/web.php`.
- `course.json` still parses: 8 modules / 24 lessons / 244 vocabulary items.

**Limitations / deferred:**
- No PHP/Composer binary and no network in the sandbox, so `php artisan test`
  and `npm run build` cannot be executed here; the suite is written to pass in
  the PHP 8.2 CI job (which builds assets first). Confirm green in CI.
- Alpine.js is still loaded from a CDN in `layouts/app.blade.php` (a documented
  Phase 2 follow-up to bundle via npm once the build env has network).

**Next action:** run the GitHub Actions `tests` workflow (or
`npm ci && npm run build && php artisan test` locally on PHP 8.2) to confirm the
full suite is green. This completes the fix-and-improvement plan (FIP Phases 1-8).

## Hospitrainity Curriculum Migration — CP-03 Schema, IDs, Lifecycle & Validation Contracts (2026-07-15)

- Added the machine-validatable contract layer under docs/hospitrainity-migration/phase-03/.
- 17 JSON Schema (Draft 2020-12) files: shared _meta defs, three framework schemas matching the CP-02 files, and content-entity schemas (curriculum-release, chapter, lesson-section, activity, prompt-item, answer-model, feedback-model, rubric, asset, source-provenance, alignment-edge, migration-edge, id-registry).
- controlled-vocabularies.json: 13 shared enumerations used by schemas, validator and both renderers.
- Deterministic identifier system: project UUIDv5 namespace cc9dd546-ebaf-51c7-b86f-307d16a22f42; id-registry.json with 52 entries; ratified legacy codes (DEC-011) plus HSP- grammar for new families.
- Lifecycle, change-classification, retire/replace and independent SemVer policy (schema_version 1.0.0 / content_version 0.3.0-draft).
- Canonical JSON serialization + checksum rules.
- tools/hsp-validate.mjs: offline Node + Ajv validator (schema, ids, refs, lifecycle, provenance, serialize, vocab, fixtures).
- Canonical package skeleton curriculum/hospitrainity/0.3.0-draft/ with framework files copied verbatim and a checksummed package.json manifest.
- Verification: hsp-validate all --strict => 8/8 checks passed; CP-02 files validate unchanged; deliberately-invalid fixtures fail on the expected check.
- Regenerated the standalone with non-visible CP-03 provenance metadata; learner-facing payload and behavior unchanged.
- Recorded DEC-011..DEC-014 and marked CP-03 Complete in the checkpoint ledger.
- Limitation: no PHP/Composer/network in the sandbox, so validation is Node-based/static; Laravel suite still runs in CI. CP-02 human approval gate remains open (does not block CP-03).

## Hospitrainity Curriculum Migration — CP-04 Manuscript Decomposition & Legacy Inventory (2026-07-15)

- Decomposed the source-of-truth manuscript (Hospitrainity.docx, sha256 7f8a2c62…2444cbb4) into addressable canonical entities under the CP-03 schemas.
- 7 chapter entities (HSP-C01..HSP-C07) with module number, title, CP-02 outcome codes and full source_locator.
- 85 lesson-section entities mirroring the manuscript's own step structure (Warm-Up, Why this matters, Step 1..8, Take it further, Further reading).
- 7 source-provenance records; docx-digest.json with normalized per-entity SHA-256 for offline provenance verification.
- Legacy inventory of database/data/course.json (8 modules / 24 lessons): 8 migration edges + legacy-inventory.json. 3 modules rewritten into chapters (Greetings->C02, Check-In->C02, Guest Requests/Issues->C07, Telephone->C03), 3 dropped (Hotel Amenities, Check-Out, Emergency Situations ��� no DOCX chapter), 1 deferred (Pre-Learning scaffolding). 3 net-new DOCX chapters recorded (Hotline, Writing, Online/Social).
- Appended 99 entries to id-registry.json (now 151 total; registry_version 1.1.0), all deterministic UUIDv5.
- Upgraded hsp-validate to be content-aware: it now discovers and validates every package entity, verifies content references, and re-checks each provenance hash against docx-digest.json.
- Verification: hsp-validate all --strict => 8/8 checks passed; 114 files validated (107 content entities); CP-02 framework files unchanged.
- Regenerated the standalone with non-visible CP-04 provenance metadata; learner-facing payload and behavior unchanged.
- Recorded DEC-015..DEC-017 and marked CP-04 Complete.
- Scope note: interactive assessment detail (activity taxonomy, prompts, answers, feedback, rubrics, scoring) is deferred to CP-05/CP-11 to avoid premature or unsourced classification.
- Limitation: no PHP/Composer/network in the sandbox; validation is Node-based/static. CP-02 human approval gate remains open (does not block CP-04).

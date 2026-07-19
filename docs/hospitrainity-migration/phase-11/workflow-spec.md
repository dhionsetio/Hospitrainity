# Phase 11 — Seven-module authoring & approval workflow (specification)

Status: CP-11 delivered. Approval unit = **module** (one of the 7 chapters). This
phase adds a deterministic authoring/approval state machine over the canonical
curriculum and surfaces per-module lifecycle state in the learner standalone. It
advances every authored module from `draft` to `review`; it does **not** approve
or publish anything, because no qualified human sign-offs exist yet.

## 1. Lifecycle vocabulary (single source of truth)

The lifecycle vocabulary is defined once, in
`phase-03/controlled-vocabularies.json` (`vocabularies.status`):

```
draft  →  review  →  approved  →  published  →  retired
```

The validator (`hsp-validate.mjs checkLifecycle`) and the importer
(`hsp-import.mjs`, lifecycle `CHECK` constraint) both derive their legal state
set from this file. Legal transitions enforced by the validator:

| From | Allowed to |
|---|---|
| draft | review, retired |
| review | approved, draft, retired |
| approved | published, review, retired |
| published | retired |
| retired | (none) |

> **CP-11 correction (DEC-035).** The importer previously hard-coded a *different*
> lifecycle list (`draft_pending_qualified_review`, `in_review`, `archived`,
> `superseded`) than the validator/controlled-vocabulary (`review`, `retired`).
> The divergence was latent because every entity was `draft`. Submitting to
> `review` surfaced it as a SQLite `constraint failed`. The importer now derives
> `LIFECYCLE` from `controlled-vocabularies.json`, so the two tools can no longer
> drift.

## 2. Scope: what the workflow acts on

The registry holds 487 id-bearing entries. CP-11 partitions them:

| Class | Entity types | Count | CP-11 action |
|---|---|---|---|
| **Authored** | chapter, lesson-section, activity, prompt-item, answer-model, feedback-model, rubric | **428** | `draft → review` |
| Framework (CP-02) | competency-framework, competency, outcome, cefr-reference | 52 | untouched (stays `draft`) |
| Source-provenance | source-provenance | 7 | untouched (stays `draft`) |
| **Total** | | **487** | |

Per-module authored counts: C01 = 8 (no assessment entities — DEC-018);
C02–C07 = 70 each. Total 8 + (6 × 70) = 428.

The CP-02 framework is frozen and byte-identical (DEC-011); re-flowing it through
an approval churn would violate that decision. Source-provenance records are
immutable verification metadata, not authored learner content, so they are
excluded as well.

## 3. The state machine (`hsp-workflow.mjs`)

Deterministic, offline, no third-party dependency. Commands:

- `status` — report per-module lifecycle state + approval-gate evaluation (read-only).
- `submit` — transition every authored entity `draft → review`, re-canonicalize
  the mutated entity files, and update the id-registry status column
  (`registry_version` bumped 1.2.0 → 1.3.0). Framework + provenance untouched.
- `approve` / `publish` — **refused** while blockers exist (see gate below).

Emitted artifacts (in `phase-11/`):

- `workflow-state.json` — canonical per-module state snapshot.
- `approval-ledger.json` — per-module approval gate, required sign-offs, blockers,
  automated-gate results. Consumed by the renderer.
- `workflow-report.json` — run summary (command, transitions applied, invariants).

## 4. Approval gate

A module may become `approved` only when **all** blockers clear:

1. **CP-02 human-approval gate closed.** It is currently **open** — the workflow
   reads this state and never invents a closure.
2. **Three recorded human sign-offs**, each with a real reviewer identity + date:
   - `esp_cefr_specialist` — ESP / CEFR alignment approver
   - `hospitality_practitioner` — hospitality domain-practitioner approver
   - `accessibility_reviewer` — WCAG 2.2 AA + UDL 3.0 conformance approver

No reviewer identities exist, so all 7 modules report `approval_gate: BLOCKED`
with 4 blockers each. Automated gates (schema, editorial, accessibility,
provenance) already pass and are recorded as evidence, but they are necessary,
not sufficient.

This is a deliberate anti-hallucination guarantee (Important Notes 1 & 2): the
system advances content to the point a human review can begin, and refuses to
fabricate the sign-offs that only qualified humans can give.

## 5. Learner-facing surface

The renderer (`hsp-render.mjs`) projects each module's lifecycle state into the
standalone:

- Home cards and chapter headers carry a non-color lifecycle badge (`In review`).
- Chapter pages show an `Approval: pending` badge + a review notice.
- The About page shows a governance table (module → lifecycle state → approval
  gate) and states the CP-02 gate is open.

All display is derived from `approval-ledger.json`; the renderer authors nothing
and remains a deterministic read-only projection (DEC-032).

## 6. Verification

- Structural validation: **8/8** before and after submit (schema 450, ids 487,
  refs, lifecycle, provenance, serialize 474, vocab, fixtures 7).
- Provenance intact: provenance hashes only `source_locator.normalized_text_sha256`
  (DOCX text), not `status`, so lifecycle transitions preserve all digests.
- Determinism: two independent renders produced identical sha256.
- DOM: headless Chromium confirms the badges + governance table paint.

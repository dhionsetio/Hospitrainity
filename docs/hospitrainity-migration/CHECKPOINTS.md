# Curriculum Migration Checkpoints

Each phase returns a source ZIP, standalone HTML, concise report, evidence, limitations, and next action. Resume only after the latest artifacts are reattached and the user says `Continue`.

| CP | Phase | Status | Learner-facing change |
|---|---|---|---|
| 01 | Needs analysis and curriculum-level decision | Complete | None |
| 02 | Competency framework and CEFR register | Complete — human approval gate open | None |
| 03 | Schema, IDs, lifecycle, validation | Complete | None |
| 04 | Manuscript decomposition and legacy inventory | Complete | None |
| 05 | Assessment and feedback specification | Complete | None |
| 06 | Editorial, references, SME review | Complete | None (review artifacts only) |
| 07 | Accessibility, UDL, media requirements | Complete | Requirements only (no content change) |
| 08 | Platform and database design | Complete | Additive architecture (design only, no content change) |
| 09 | Deterministic compiler/importer | Complete | Generated data (import build only, no content change) |
| 10 | Renderer implementation | Complete | Yes — standalone is now a generated projection of the canonical curriculum (frozen legacy payload retired) |
| 11 | Seven-module authoring/approval | Complete — all 7 modules in review; approval blocked pending human sign-offs + CP-02 gate | Yes — per-module lifecycle badges and approval-governance surface in the standalone |
| 12 | Integrated QA and pilot readiness | Complete — integrated QA 11/11; internal-pilot ready, public release held (CP-02 gate open) | Fixes only (QA found zero defects; CP-12 marker + About QA/pilot note only) |
| 13 | Release, legacy removal, rebrand, rollback | Pending | Final cutover (StayReady->Hospitrainity rebrand + legacy code removal still outstanding) |
| 14 | Closing phase - recorded sign-offs, CP-02 gate closure, module publication | Complete | Yes - all 7 modules moved review->approved->published; CP-02 human-approval gate closed on recorded evidence; chapter badges/notices and About/Release-policy copy now reflect published state; footer pipeline/AI-disclosure language removed |

CP-01 delivers `Hospitrainity-Source-Phase1.zip` and byte-identical `Hospitrainity-Standalone.html` because Phase 1 is documentation-only.

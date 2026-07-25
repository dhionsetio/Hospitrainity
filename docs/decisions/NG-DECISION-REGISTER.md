# Hospitrainity Next-Generation Decision Register

- Register version: 1.0.4
- Established: B00, 2026-07-19 (Asia/Jakarta)
- Accountable owner/coordinator: Dhion
- Technical executor/recommender: Codex, subject to evidence and owner-controlled boundaries
- Governing roadmap: `docs/HOSPITRAINITY_NEXT_GENERATION_AUDIT_AND_UPDATE_ROADMAP.md`

## Authority and approval roles

| Gate | Accountable owner | Required approval/evidence | Current state |
|---|---|---|---|
| Product scope, features, role capabilities, and visual preference | Dhion | Explicit owner decision | Named and active |
| Technical architecture and coding technique | Dhion | Codex recommendation supported by current primary sources and reproducible tests; owner escalation where a decision crosses a reserved boundary | Named and active |
| Academic, ESP, and hospitality content | Dhion coordinates | Qualified academic/ESP/hospitality reviewer appropriate to the claim | Reviewer TBD; release-blocking where applicable |
| Accessibility | Dhion coordinates | Qualified human review plus keyboard, zoom/reflow, screen-reader, contrast, and real-device evidence | Reviewer TBD; release-blocking where applicable |
| Privacy and legal | Dhion coordinates | Qualified Indonesian privacy/legal interpretation and approval | Reviewer TBD; release-blocking where applicable |
| Security | Dhion coordinates | Independent security reviewer or penetration tester plus engineering evidence | Reviewer TBD; production-blocking |
| Production operations | Dhion coordinates | Named operator/owner for host, secrets, database, storage, backup/restore, monitoring, and incident response | Operator/topology TBD; production-blocking |

Being the accountable coordinator does not imply specialist qualification or approval. Missing specialist evidence must remain explicit; it may not be replaced by an AI assertion, passing unit tests, or project-owner consolidation.

## Decision records

| Decision | Batch/scope | Summary | Owner | State | Record/evidence |
|---|---|---|---|---|---|
| NG-B00 | B00 | Authority freeze, private-artifact boundary, Git bootstrap, branch/checkpoint convention, GitHub Free limitation, CI/browser/database target, approval ownership, and technical-discretion boundary | Dhion | Accepted | `docs/decisions/NG-B00-DECISIONS.md` |
| NG-B01 | B01 | Normalized institution context, fail-closed demo seeding/bootstrap, and draft-release containment; individual invitations retained but no longer the only enrollment path | Dhion | Accepted and executed; enrollment rule superseded by approved NG-B02 architecture | `docs/decisions/NG-B01-DECISIONS.md` |
| NG-B02 | B02 | Lightweight owner-operated privacy/data-rights direction, opt-in push, personal self-study accounts, and pending adjustable-duration classroom-code membership requests | Dhion | Enrollment and technical privacy lifecycle implemented; qualified legal/controller/topology and production gates remain | `docs/decisions/NG-B02-DECISIONS.md` |
| NG-B03 | B03 | ASVS L2 target, passkey-first MFA, authentication/session/upload/logging/backup gates | Dhion | Local technical baseline implemented; production operations and independent assurance pending | `docs/decisions/NG-B03-DECISIONS.md` |
| NG-B04 | B04 | WCAG 2.2 AA engineering target, owner self-review boundary, responsive navigation, labeled flag language control, and display preferences | Dhion | Accepted for implementation; no independent conformance claim | `docs/decisions/NG-B04-DECISIONS.md` |
| NG-B05 | B05 | Truthful public journey, role-aware onboarding/search/help, usability study, and tenant-scoped roles with separate Content Author capability | Dhion | Technical scope and study protocol implemented; moderated study execution remains B17; all three demo accounts remain enabled | `docs/decisions/NG-B05-DECISIONS.md` |
| NG-B06 | B06 | Institution-owned Courses and Classes, scoped Instructor and Institution Admin authority, enrollment/history rules, learner preview, class lifecycle, immutable curriculum association, and one shared UTC/IANA time foundation | Dhion | Accepted and locally executed through B06-C; production and specialist-review gates remain, and the full Instructor outcome continues through B07-B12 | `docs/decisions/NG-B06-DECISIONS.md`; `docs/checkpoints/NG-B06-A-FOUNDATION-2026-07-21.md`; `docs/checkpoints/NG-B06-B-CLASS-ROSTER-2026-07-21.md`; `docs/checkpoints/NG-B06-C-CLASS-LEARNING-CUTOVER-2026-07-22.md` |
| NG-CONSOLIDATED-2026-07-22 | B07 through B17 and learning-authority replacement | Run one consolidated tester update, use the documented recommendations without routine questionnaire pauses, integrate the supplied v0.8 learning content, and implement its research and CEFR claims while recording that qualified review has not occurred | Dhion | Accepted and in progress; external authority replacement, active-package transformation, provider credentials, production, and specialist gates remain open | `docs/checkpoints/NG-AUTHORITY-CANDIDATE-2026-07-22.md` |
| ADR-001 | Curriculum recovery | Canonical curriculum recovery policy | Existing accepted record | Accepted historical contract | `docs/decisions/ADR-001-curriculum-recovery.md` |
| ADR-002 | ADM-0–ADM-6 | Administration authority and data boundaries | Existing accepted record | Accepted historical contract | `docs/decisions/ADR-002-administration-authority-and-data-boundaries.md` |
| ADM-0 | Administration | Four-role capability matrix | Existing accepted record | Enforced through ADM-6 | `docs/decisions/ADM-0-administration-capability-matrix.md` |
| ADM-4 | Exercise authoring | Canonical exercise-template contract | Existing accepted record | Enforced; two audio-dependent templates unavailable | `docs/decisions/ADM-4-canonical-exercise-template-contract.md` |
| ADM-5 | Progress administration | Privacy-safe progress fields and role scopes | Existing accepted record | Enforced | `docs/decisions/ADM-5-progress-administration-privacy-contract.md` |
| NG-B07 through NG-B17 | Consolidated tester update | Use each batch's documented recommendation as the default; preserve batch-level evidence and acceptance checks without pausing for routine questionnaires | Dhion | Authorized 2026-07-22; implementation and final evidence pending | Add records or one consolidated checkpoint as work closes |

## Decision-record minimum fields

Every future NG decision record must state:

1. decision date, batch, accountable owner, status, and superseded records;
2. exact questionnaire answers used and any supplemental choices;
3. accepted behavior and explicitly rejected/deferred alternatives;
4. source, legal, privacy, security, accessibility, migration, and operational consequences;
5. human/specialist approvals required and whether they exist;
6. rollback/forward-fix boundary;
7. primary-source and reproducible-evidence basis;
8. unresolved gates that prevent a broader claim or release.

## Change protocol

- Rehash both authority DOCXs before every batch and stop on mismatch.
- Do not infer reserved product, visual, pedagogical, grading, privacy, retention, vendor/cost, or production choices.
- Ask only for newly discovered reserved choices that need credentials, an external contract or write, destructive data action, production deployment, or material scope expansion.
- The current authorization permits one consolidated tester checkpoint while preserving internal batch traceability.
- Never rewrite historical decision/checkpoint provenance merely because its old “Next action” has already occurred.
- Do not delete or relocate project files, evidence, data, uploads, or backups without Dhion's explicit permission for the exact targets.

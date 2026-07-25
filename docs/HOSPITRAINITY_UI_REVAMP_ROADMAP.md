# Hospitrainity UI revamp implementation roadmap

Roadmap version: 1.1.1  
Approved: 2026-07-21 (Asia/Jakarta)  
Owner: Dhion Setio  
Status: approved; UI-R00, UI-R01, UI-R02, and B06-B complete; B06-C is next

## 1. Relationship to the next-generation roadmap

The ordered next-generation roadmap remains authoritative. B06 is still the active domain batch and is complete through B06-B. This UI revamp is a separately checkpointed presentation track authorized by the owner after B05. It may improve existing screens and interaction patterns, but it may not add or imply behavior whose owning domain phase is incomplete.

The B06 questionnaire was approved on 2026-07-21 and is recorded in `docs/decisions/NG-B06-DECISIONS.md`. UI-R may consume a B06+ capability only after its real domain contract and server authorization exist and pass their owning checkpoint.

### Approved interleaving strategy

The remaining redesign will not wait until all domain batches are finished. It will follow tested vertical slices:

1. B06-A establishes Course/Class, scoped authorization, and shared-time contracts.
2. UI-R02 builds the responsive shell around those real contexts.
3. B06-B/B06-C complete Instructor Class and roster journeys in that shell.
4. B07 and UI-R03 deliver directed learning and the learner dashboard together.
5. B08 and UI-R07A deliver the task-oriented Instructor workspace and Course Builder.
6. UI-R04 improves the learner lesson/step flow against released Class content.
7. B09 and UI-R05 deliver assessment, grading, exercises, and feedback surfaces.
8. B10 and UI-R06 deliver progress/analytics plus supporting Help, search, settings, and authentication polish.
9. B11-B12 and UI-R07B complete communication, notifications, scheduling, Institution Admin, and System Admin surfaces.
10. UI-R08 performs the final cross-role validation and handoff.

Each B and UI-R phase retains its own checkpoint. Interleaving changes timing, not authority or acceptance gates.

## 2. Approved direction

- Calm modern hospitality, not a generic SaaS template and not a game.
- Blue-led palette with warm yellow used sparingly.
- Blue-gray charcoal dark mode, not near-black.
- Plus Jakarta Sans, self-hosted.
- Compact persistent desktop rail and four-item mobile bottom navigation.
- Keycard-inspired module presentation.
- Dashboard emphasis: Continue, modules, truthful progress, Help.
- One exercise prompt per screen where the activity contract permits it.
- Short local progress and a clear transition when a learning step ends.
- Subtle completion feedback; no new gamification.
- Learner surfaces more expressive; staff/admin surfaces more restrained.
- Authentic hospitality photography only where it improves context and rights are documented.

The controlling product and visual rules are in `PRODUCT.md` and `DESIGN.md`.

## 3. Phase plan

### UI-R00 — authority and design freeze

Status: complete in documentation.

Deliverables:

- reread the complete master roadmap, current thesis, learning materials, and B05 checkpoint;
- record the changed thesis hash and repeat its product-intent audit;
- capture the approved product brief, design system, phase boundary, and north-star comp;
- inventory unsupported mock content so it cannot leak into the implementation.

Gate: no code before source drift is reconciled and the approved direction is recorded.

### UI-R01 — design foundations

Status: complete. Evidence: `docs/checkpoints/UI-R01-DESIGN-FOUNDATIONS-2026-07-21.md`.

Deliverables:

- primitive and semantic light/dark color tokens;
- self-hosted Plus Jakarta Sans and fallback behavior;
- typography, spacing, radius, shadow, focus, and motion tokens;
- shared button, card-link, progress, selected, disabled, and loading behavior;
- compatibility layer for existing Tailwind utility mappings;
- automated contrast/token coverage and reduced-motion checks.

Acceptance:

- documented text pairs meet at least 4.5:1;
- meaningful component boundaries meet at least 3:1 where relied on;
- primary controls remain at least 44px high;
- no existing light/dark page becomes unreadable;
- browser visual review covers representative guest, learner, and privileged pages in both themes.

### UI-R02 — responsive learner shell

Status: complete. Evidence: `docs/checkpoints/UI-R02-RESPONSIVE-LEARNER-SHELL-2026-07-21.md`.

Deliverables:

- persistent desktop rail;
- mobile bottom navigation with safe-area support;
- top account/context bar;
- consistent page title, Help, back, account, language, and context placement;
- exact active/current state with keyboard and screen-reader behavior.

Acceptance: Home, Learn, Progress, and Help remain reachable by keyboard and touch at 320/390/768/1280 widths without root overflow or hover dependency.

### UI-R03 — learner dashboard

Deliverables:

- dominant current authorized next action;
- keycard-like module list using current chapter data only;
- truthful participation progress labels;
- useful empty/recovery states;
- visible Help without technical metadata dominating ordinary learner views.

Acceptance: no invented plan, due date, score, pass, mastery, assignment, or class state; an ordinary learner can identify the next action and available modules in the first viewport on desktop and mobile.

### UI-R04 — module, lesson, and step flow

Deliverables:

- grouped chapter steps with local counts;
- clear current/completed/upcoming distinction;
- transition checkpoint after a step group;
- bounded reading measure and strong nested-page return paths;
- source-faithful table transformations into cards, dialogue turns, comparisons, or retained responsive tables.

Acceptance: the learner sees local progress rather than “11 of 85”; every transformed source block preserves its text and relationship; keyboard/focus/history navigation remains correct.

### UI-R05 — exercises and feedback

Deliverables:

- focused prompt presentation;
- strong selected/checked/error/correct/model states without color-only meaning;
- one-prompt progression where supported;
- final summary and next action;
- subtle completion feedback with a reduced-motion equivalent.

Acceptance: no change to server-authoritative scoring, reveal policy, or source answer fidelity; all supported exercise templates retain their tested behavior.

### UI-R06 — progress, Help, search, settings, and authentication

Deliverables:

- clearer participation-progress summaries and bounded detail views;
- card-link Help topics and clearer search results;
- consistent preference, language, account-security, registration, login, and recovery patterns;
- learner-safe copy that hides technical implementation detail.

Acceptance: no privacy/security capability is weakened; error, empty, loading, and recovery paths are clear in both locales.

### UI-R07A — instructor workspace and Course Builder

Deliverables:

- restrained staff shell using the same tokens;
- explicit role and current scope;
- task-oriented Class home for roster, teaching content, release status, assignments, submissions, progress, announcements, and schedule as their owning B batches become available;
- guided Course Builder over the canonical draft, validation, preview, approval, and immutable-release workflow;
- safe learner preview and clear primary/co-instructor responsibility;
- truthful empty, blocked, review-required, and release states;
- clearer destructive and privileged action boundaries.

Acceptance: an authorized Instructor can complete the currently implemented teaching workflow without entering a System Admin evidence screen; server policies remain authoritative; no unavailable B capability is implied.

### UI-R07B — institution and system administration surfaces

Deliverables:

- task-oriented hierarchy for Institution users, roles, invitations, codes, policies, and scoped audit work;
- separate System Admin hierarchy for global institutions, canonical publication, security, audit, and evidence;
- clear destructive and privileged action boundaries.

Acceptance: Institution Admin and System Admin authority is visually and technically distinct; server policies remain authoritative; no staff screen implies an unavailable queue or scope.

### UI-R08 — validation and handoff

Deliverables:

- complete automated regression matrix;
- browser screenshots and manual visual review across target widths/themes;
- branded-browser and physical-device plan;
- keyboard, zoom, reduced motion, high contrast, and screen-reader evidence;
- owner design review and B17 intended-user validation handoff.

Acceptance: open external/human evidence is reported as open; no unsupported conformance, engagement, thesis-outcome, or production claim.

## 4. Checkpoint discipline

- One UI-R phase per checkpoint unless the owner explicitly changes the boundary.
- Preserve unrelated working-tree changes.
- Do not delete files, user data, retained evidence, or legacy records without explicit permission.
- Product/domain questions discovered during UI work stop the affected change and return to the owning B batch.
- Each checkpoint records decisions, files, migrations/data impact, security/privacy/accessibility impact, exact tests, browser evidence, limitations, rollback, and the next phase.

## 5. Exact next actions

1. UI-R02 implementation, responsive visual review, and Chromium/WebKit regression are complete.
2. The final UI-R02 checkpoint is saved; the earlier pause checkpoint remains as historical failure evidence.
3. B06-B Class/roster operations are complete under `docs/decisions/NG-B06-DECISIONS.md` and `docs/checkpoints/NG-B06-B-CLASS-ROSTER-2026-07-21.md`.
4. Resume B06 at B06-C context cutover and final verification; do not begin B07 before the B06 completion gate.
5. Keep branded-browser and physical-device validation open for UI-R08; the local Playwright Firefox runtime still fails before application navigation.

# Hospitrainity Source-Fidelity Audit and Update Roadmap

**Audit date:** 17 July 2026  
**Authority reviewed:** `Hospitrainity.docx`, supplied from `Revisi 30 Juni 2026/Learning Materials - Fixed`  
**Application snapshot:** audited baseline used canonical package `0.3.0-draft`; implemented checkpoints now retain active package `0.4.0-draft` pending the recorded human publication gates  
**Roadmap status:** CF-1 through CF-7 and ADM-0 through ADM-6 are implemented and technically verified; production deployment remains blocked by the explicit environment, backup/restore, and human approval gates recorded below

## 1. Baseline audit answer

> Historical baseline: sections 1 through 6 describe the 17 July 2026 application snapshot before CF/ADM remediation. Use the dated checkpoint states and implementation checkpoint log as the current execution record.

The current version is **structurally faithful but materially and experientially incomplete**.

- **Faithful:** the attached DOCX is the exact file fingerprint recorded by the canonical package; all 7 chapter identities and all 85 section titles/orders are represented; all 102 stored prompt stems, all 90 non-empty stored answer strings, and all 138 stored feedback strings occur verbatim in the source document.
- **Not faithful enough for learner delivery:** none of the 85 canonical section files stores its teaching body. The 61 sections without an activity render as explicit title-only placeholders. The other 24 sections expose assessment prompt cards but still omit their surrounding explanations, vocabulary, useful phrases, model conversations/writing, common mistakes, tables, further practice, and reading links.
- **Exercise experience is not faithful:** the source asks learners to answer, choose, order, speak/write, rate confidence, and then check models. The active canonical pages and standalone only show prompt text plus revealable answers/feedback. They do not provide answer controls, ordering controls, writing areas, role-play capture/self-assessment, or per-statement confidence ratings.
- **Administration is only partially implemented and is not connected to active delivery:** superadmin pages and validated CRUD code exist for five retained legacy resources, including 14 exercise templates with editable answers. While a canonical package is active, every legacy write is intentionally rejected with HTTP 410 and the controls are hidden because those rows no longer control learner delivery. There is no browser-based canonical authoring/import/publish workflow, no detailed/global learner-progress administration, no user-management screen, no `admin` role, and no superadmin promotion/demotion feature.
- **StayReady is retired from active learner-facing surfaces:** no active Blade view, application source, route/config, public page, environment name, or current standalone contains a StayReady brand rendering. Remaining hits are intentional provenance, regression-test, changelog, and frozen migration history. One SQLite row contains the same legacy-inventory provenance text. These records should be retained, not displayed as a product brand.

The correct next update is not a cosmetic content paste. It is a versioned source-to-package compiler, a lossless structured-content model, semantic assessment normalization, accessible interactive delivery, progress/privacy rules, regenerated standalone parity, and source-level regression coverage.

## 2. Audit authority and limitations

### 2.1 Source identity

| Evidence | Confirmed value |
|---|---|
| Source filename | `Hospitrainity.docx` |
| Source title | `Hospitrainity: English for Hotel Customer Service` |
| Supplied file SHA-256 | `7f8a2c62db6884498f7a1c2de56e79e39609262944a685fe7eb97f702444cbb4` |
| Canonical `docx-digest.json` SHA-256 | Same value |
| DOCX body paragraphs | 946, including empty layout paragraphs |
| Extracted text characters | 82,103 |
| Tables | 21 tables / 151 rows |
| External hyperlinks | 18 relationships / 16 unique targets |
| Images, comments, tracked changes, content controls | 0 |
| Source brand occurrences | `Hospitrainity`: 1; `StayReady`: 0 |

The exact hash match proves that this audit used the same DOCX fingerprint recorded by the package. It does **not**, by itself, prove that all DOCX content was copied into that package; the coverage comparison below addresses that separate question.

### 2.2 Document structure

The source contains seven chapters in this order:

1. Welcome and Introduction to Customer Care
2. Front Desk and Check-In
3. Dealing with Customers on the Phone
4. The Guest-Services Hotline
5. Customer Care Through Writing
6. Online and Social Media Customer Service
7. Dealing with Problems and Complaints

Chapter 1 contains 7 sections. Chapters 2–7 each contain 13 sections: Warm-Up; Why this matters; scenario; key vocabulary; useful phrases; model dialogue/writing; common mistakes; Practice; Role-play; Quiz; Confidence check; Take it further; and Further reading.

The manuscript explicitly frames these as one connected course at the Grand Serenata Hotel, recommends moving through chapters in order where possible, and asks learners to say phrases aloud. Its recurring learner experience is therefore part of the source requirement, not optional decoration.

### 2.3 Visual-review status

The initial audit extracted the DOCX and its complete OOXML structure, but page-level comparison was blocked because LibreOffice was not installed and a bounded read-only Word COM export did not return. CF-7 closed that environment blocker on 18 July 2026 after LibreOffice 26.2.4.2 was installed:

- the exact-hash 117,314-byte source was copied to a short no-space temporary path without modifying the authority file;
- a direct `writer_pdf_Export` conversion using an isolated profile and `SAL_DISABLESKIA=1` produced a 58-page Letter PDF, SHA-256 `28dead0e773cf44a7c5e9da203b00ecb5b7e2872eeeb32a9be9625e62b5680d5`;
- all 58 pages were rasterized at 150 DPI and visually inspected;
- no unreadable clipping, overlap, missing content/glyphs, or broken table borders were found;
- representative source-to-web comparisons passed for the Chapter 2 vocabulary table (including 320-pixel behavior), Chapter 5 model email, and Chapter 7 model dialogue/apology letter.

LibreOffice page 14 has one atypically indented continued bullet line and page 49 isolates one further-reading link on a mostly blank page; both remain readable and complete. LibreOffice is not Microsoft Word, so small pagination/font-metric differences remain possible. Full evidence is in `curriculum/evidence/cf-7-visual-comparison.json`.

## 3. What is faithful today

| Area | Result | Evidence |
|---|---|---|
| Source identity | **Faithful** | Attached SHA-256 equals the canonical digest. |
| Chapter coverage | **Faithful** | 7/7 chapter titles and order are present. |
| Section identity | **Faithful at heading level** | 85/85 section codes, titles, order, chapter relationships, source locators, and hashes exist. |
| Activity-to-section links | **Internally valid** | 24 activities resolve to the matching Practice, Role-play, Quiz, and Confidence sections in Chapters 2–7. |
| Prompt wording | **Faithful** | 102/102 stored prompt stems occur verbatim in the extracted DOCX text. |
| Answer wording | **Faithful where stored** | 90/90 non-empty accepted-answer strings occur verbatim in the DOCX. Twelve answer-model files intentionally contain no accepted value: six confidence instructions and six free response tasks. |
| Feedback wording | **Faithful** | 138/138 feedback strings occur verbatim in the DOCX. |
| Chapter scenario identity | **Partial** | Grand Serenata remains in assessment strings, but most narrative continuity is lost with the omitted section bodies. |
| Import integrity | **Faithful only from package onward** | Current import/verify tests prove that the existing package reaches SQLite and standalone deterministically and idempotently. |
| Active product name | **Faithful** | Learner-facing product surfaces use Hospitrainity, not StayReady. |

These are important strengths and must be preserved. The remediation should extend the existing identifiers and provenance model, not discard them.

## 4. What was missing or inconsistent at the audited baseline

### 4.1 Source-to-delivery coverage matrix

| Source requirement | Source count/state | Current package/runtime | Verdict |
|---|---:|---:|---|
| Chapters | 7 | 7 | Complete |
| Section headings/order | 85 | 85 | Complete |
| Structured section bodies | 85 sections contain instructional content | 0 section JSON files have a body/blocks/content field | **Critical gap** |
| Non-activity teaching sections | 61 | 61 explicit title-only placeholders | **Critical gap** |
| Activity sections | 24 | 24 prompt-review pages | Partial |
| Practice prompts | 24 | 24 verbatim stems | Text complete; interaction absent |
| Role-play prompts | 24 | 24 verbatim stems | Text complete; option/free-response semantics flattened |
| Quiz prompts | 48 | 48 verbatim stems | Text complete; selection interaction absent |
| Chapter 2–7 confidence statements | 24 | 0 statements; 6 generic instructions only | **Critical gap** |
| Chapter 1 baseline statements | 4 | 0 | **Critical gap** |
| Total source self-rating statements | 28 | 0 | **Critical gap** |
| Warm-up question paragraphs | 21 | 0 learner-visible questions | Missing |
| Tables | 21 | 0 source tables represented in section content | Missing |
| Source further-reading hyperlinks | 18 relationships / 16 targets | 0 of those targets in canonical learner content or standalone | Missing |
| Grand Serenata occurrences | 29 in source | 10 package text occurrences; 9 standalone occurrences | Partial narrative continuity |
| Source learner response items | 96 exercise prompts + 28 self-rating statements = 124 | 102 prompt records: 96 exercise prompts + 6 generic confidence instructions | 28 statements lost; instructions miscounted as response items |

The 102 current prompt records are not evidence of full activity coverage. They combine the 96 Practice/Role-play/Quiz items with one instruction per confidence section, while omitting the 24 chapter confidence statements and the 4 Chapter 1 baseline statements.

### 4.2 Module-by-module learner impact

| Module | What remains | What the learner does not receive |
|---|---|---|
| 1. Welcome | 7 correct section titles and 3 derived outcomes | All 7 teaching bodies; 3 warm-up questions; positive-language comparison table; 4 baseline confidence ratings; chapter-focus table; practical introduction and continuity guidance |
| 2. Front Desk | 4/4 Practice, 4/4 Role-play, 8/8 Quiz stems; generic confidence instruction | 9 non-activity teaching bodies, vocabulary table, phrases/model dialogue, mistakes, 4 confidence statements, speaking challenge, 3 reading links, actual exercise controls |
| 3. Phone | Same 17-record assessment pattern | Phone explanations/models, vocabulary, phrase groups, listening/read-back drill, 4 confidence statements, reading links, actual exercise controls |
| 4. Hotline | Same 17-record assessment pattern | Hotline scenario/framework instruction, vocabulary, model calls, speed/accuracy practice context, 4 confidence statements, reading links, actual exercise controls |
| 5. Writing | Same 17-record assessment pattern | Email structure and model-writing bodies, register table, writing workspace, 4 confidence statements, reading links, actual exercise controls |
| 6. Online/social | Same 17-record assessment pattern | Chat/social conventions, model exchanges, escalation guidance, 4 confidence statements, reading links, actual exercise controls |
| 7. Complaints | Same 17-record assessment pattern | L-A-A-F teaching sequence and model letter/dialogue bodies, vocabulary, service-recovery context, 4 confidence statements, reading links, actual exercise controls |

### 4.3 Connection and navigation fidelity

The current relationship graph is valid at a database/reference level, but the learner pathway is incomplete:

- dashboard → module → activity and activity → module links work;
- section cards have no section route because section bodies are absent;
- there is no content-level previous/next sequence through all 85 sections;
- learners can jump directly to revealable answers without first attempting an item;
- Further reading contains no rendered links;
- Chapter 1 has no trackable learning action;
- the standalone has the same title-list and prompt-reveal model and therefore repeats the gap rather than serving as a full offline course.

### 4.4 Baseline administration capability matrix

The active canonical package changes the meaning of the existing superadmin pages. They are retained legacy evidence, not an authoring system for the curriculum learners currently receive.

| Requested capability | Confirmed current state | Verdict |
|---|---|---|
| Manually create/edit/delete modules | Legacy modal CRUD exists, but active-package middleware rejects writes with HTTP 410 and the controls are hidden | **Not available for current curriculum** |
| Upload/import a new module | No superadmin web route, request, controller, or interface for canonical module import/upload | **Absent** |
| Manually create/edit/delete lessons | Legacy modal CRUD exists, but is read-only while the canonical package is active | **Not available for current curriculum** |
| Upload/import a lesson | Legacy material/vocabulary forms can upload individual media, but there is no canonical lesson-package or DOCX import interface | **Absent** |
| Create exercises from existing templates and edit answers | A validated legacy editor exposes 14 renderer types and editable answers/options/items, but writes are disabled and its records do not drive canonical learner activities | **Implemented legacy capability; disconnected/unavailable** |
| Create vocabulary/materials and upload media | Legacy forms exist for vocabulary audio/video and material image/audio/YouTube fields, but they are read-only and non-canonical | **Implemented legacy capability; disconnected/unavailable** |
| Preview draft content as a learner | No canonical draft preview route or publication workflow exists | **Absent** |
| Review every learner's detailed lesson/activity progress | Superadmin has no progress route or screen. Supervisor sees only same-institution learners and one overall percentage | **Absent; supervisor view is partial** |
| Search/filter/export progress | No such administration routes or controls exist | **Absent** |
| List/manage user accounts | No user-management controller, policy, routes, or screen exists | **Absent** |
| Promote a user to admin | No `admin` role exists; current values used by application logic are `user`, `supervisor`, and `superadmin` | **Absent** |
| Promote another user to superadmin or demote roles | No role-change endpoint, step-up confirmation, audit record, or UI exists | **Absent** |
| Superadmin dashboard statistics | Counts learners, supervisors, institutions, and legacy `modules` rows | **Partial; module count is not canonical** |

## 5. Baseline finding register

Priorities: **P0** = source/release integrity blocker; **P1** = high-impact learning, correctness, or data issue; **P2** = material UX, maintainability, or evidence issue.

### FID-P0-001 — All canonical section bodies are absent

- **Evidence:** every one of the 85 section JSON files contains metadata and a title but no body, block list, table, link, or content field. The Blade renderer openly displays a no-body placeholder for 61 non-activity sections.
- **Impact:** the learner receives an index of the book and a subset of exercises, not the coursebook described by the source.
- **Required outcome:** compile every source section into ordered, typed, source-traceable content blocks and render all 85 sections without placeholder prose.

### FID-P0-002 — Published/approved status is applied to an incomplete delivery projection

- **Evidence:** package entities and learner cards are `published`, while the content version is still named `0.3.0-draft`; the standalone presents a closed approval gate even though all teaching bodies are absent.
- **Impact:** lifecycle labels imply a release-complete course when only structural metadata and assessment excerpts are delivered.
- **Required outcome:** separate content completeness from review/approval status; do not mark a new package published until source-coverage gates pass; never publish a version whose identifier ends in `-draft`.

### FID-P0-003 — Confidence extraction loses all 28 source statements

- **Evidence:** seven DOCX tables contain 28 distinct statements. None occurs in the package or standalone. Six generic “rate yourself” instruction paragraphs are stored as if each were a single self-rating prompt.
- **Impact:** the learner cannot make the baseline or chapter-by-chapter comparison that the manuscript explicitly promises.
- **Required outcome:** retain the instruction as activity guidance, create 28 separate 1–5 rating items, include the Chapter 1 baseline, and define a privacy-aware history view.

### FID-P0-004 — Canonical activities are read-only answer sheets

- **Evidence:** the common activity Blade view emits stems and `<details>` answer/feedback reveals, followed by an unconditional completion button. It emits no radio, checkbox, text, textarea, select, or ordering controls. The standalone renderer follows the same pattern.
- **Impact:** “choose,” “fill,” “put in order,” “write,” “speak,” and “rate” instructions are not executable experiences.
- **Required outcome:** implement a response-form renderer and submission/self-check flow for every supported semantic type before allowing completion.

### FID-P1-001 — Selection and ordering semantics are flattened into prose

- **Evidence:** 74 selection prompts have no structured `options`; 5 ordering prompts have no structured `items`. Guided role-play choices are stored inside feedback messages rather than selectable option records.
- **Impact:** a renderer cannot safely create controls, validate allowed values, randomize only when permitted, or provide option-specific feedback without re-parsing English prose.
- **Required outcome:** extract stable option/item IDs, display text, canonical order/correct choice, and feedback links. Preserve original wording and order; do not parse stems at runtime.

### FID-P1-002 — Open responses are mislabeled at activity level as objectively normalizable

- **Evidence:** all Practice activities declare `objective_normalized`, but several of the 11 short-text prompts ask for a polite rewrite, an empathetic first line, a quick reply, or spoken number wording and can have multiple valid responses. Several source answers explicitly begin with language such as “Something like”.
- **Impact:** implementing activity-level exact/normalized scoring would reject valid language and contradict the source’s model-answer intent.
- **Required outcome:** assign scoring policy per prompt. Only demonstrably closed answers may be objectively checked; open language production must use model reveal, self-check, or an approved human rubric. Do not invent AI grading.

### FID-P1-003 — Free role-play and writing tasks have no response path

- **Evidence:** 4 `role_play` and 2 `service_artifact` prompts have no response constraints or learner workspace; the UI only reveals a model and rubric.
- **Impact:** core productive practice is reduced to reading.
- **Required outcome:** provide a text workspace for written artifacts and a text rehearsal/self-assessment path for spoken role-play. Audio recording is an optional decision-gated enhancement, not a prerequisite and not server-persisted by default.

### FID-P1-004 — Completion does not represent participation

- **Evidence:** a learner can mark an activity complete without entering any response or rating. Progress is activity-level and does not distinguish viewed, started, attempted, self-checked, or completed.
- **Impact:** progress can reach 100% without doing the exercise and cannot support reliable resume/history behavior.
- **Required outcome:** define completion by activity type, record versioned attempts transactionally, and keep open-task completion explicitly self-reported.

### FID-P1-005 — Source tables and reading links are dropped

- **Evidence:** the DOCX has 21 instructional tables and 18 external hyperlink relationships (16 unique targets); none is in learner-facing canonical content or standalone output.
- **Impact:** vocabulary, comparison, register, confidence, chapter-focus, and further-reading material is unavailable.
- **Required outcome:** add source table/link blocks, preserve link text and targets, and run a non-destructive link-health report. A dead source link must be flagged for content-owner review, never silently replaced with invented material.

### FID-P1-006 — The claimed deterministic source extraction is not reproducible from this repository

- **Evidence:** migration documentation references `extract_assessment.py` and `cp05-build.mjs`; those scripts and an official complete DOCX compiler are absent. The only present validator compares entity hashes with stored digest files.
- **Impact:** the current repository can verify package self-consistency and package-to-database fidelity, but cannot independently regenerate or prove full DOCX-to-package coverage.
- **Required outcome:** commit a maintained, dependency-locked source compiler; make the DOCX path an explicit input; emit a coverage report; test two clean builds for byte-identical output.

### FID-P1-007 — The current “lossless” test name overstates its scope

- **Evidence:** `test_import_is_lossless_verified_and_idempotent` starts from `CanonicalPackageReader::read()` and checks 455 files/501 entities after import. It never reads the attached DOCX.
- **Impact:** future maintainers or AI agents can mistake package-to-DB losslessness for source-to-package completeness.
- **Required outcome:** rename/document the test as package-projection losslessness and add separate source-compiler coverage tests tied to the DOCX hash and expected content inventory.

### FID-P1-008 — Derived outcomes, CEFR mappings, and rubrics need distinct provenance

- **Evidence:** the DOCX contains zero occurrences of `CEFR`, `A2`, `B1`, `rubric`, or `learning outcomes`; the package adds 21 outcomes, 23 CEFR references, 7 competencies, and 6 provisional rubrics. The migration spec acknowledges these as authored classifications/derivations, but learner UI describes “approved ... rubric semantics” without clearly separating source text from derived metadata.
- **Impact:** source-authored content and project-authored instructional metadata can be conflated.
- **Required outcome:** attach `provenance_kind` such as `source_verbatim`, `source_structured`, or `derived_reviewed`; show derived/provisional status where material; require explicit qualified review before derived metadata is represented as approved.

### FID-P1-009 — Standalone parity is deterministic but deterministically incomplete

- **Evidence:** its checksum and generation path are stable, but chapters show only section titles and activities show prompt/reveal cards without controls or progress.
- **Impact:** offline delivery cannot satisfy the source course experience.
- **Required outcome:** generate Laravel and standalone from the same enriched view model and run the same semantic coverage assertions against both.

### FID-P2-001 — Grand Serenata continuity is only partial

- **Evidence:** the source has 29 Grand Serenata occurrences; the package text has 10 and the standalone has 9 because omitted bodies carry most scenario framing.
- **Impact:** activities feel less like a connected hotel journey and more like isolated questions.
- **Required outcome:** restore source bodies and verify scenario names are neither deleted nor replaced. Do not add new hotel facts not present in the DOCX.

### FID-P2-002 — Learner navigation exposes implementation metadata before learning content

- **Evidence:** module/activity pages prominently show canonical codes, lifecycle status, scoring mode, provisional band, and provenance hashes while the teaching body is absent.
- **Impact:** audit metadata crowds out the learner experience and amplifies the impression that a structural package is a complete course.
- **Required outcome:** retain provenance on an About/Evidence disclosure or admin review surface; make source instruction and learner task primary.

### FID-P2-003 — StayReady regression scope is narrower than the required active-artifact policy

- **Evidence:** the current feature test asserts that one welcome response does not contain `StayReady`. It does not scan all active rendered roles, standalone metadata/content, configuration/mail names, seed values, or split-markup variants.
- **Impact:** a future regression could reintroduce the old brand outside the welcome page.
- **Required outcome:** add an allowlist-based active-artifact scan and rendered-page matrix. Historical provenance/changelog/archive files must remain allowed and preserved.

### FID-P2-004 — Visual source QA has no reliable local toolchain

- **Original evidence:** LibreOffice was absent and Word automation hung during the initial audit.
- **Remediation (18 July 2026):** LibreOffice 26.2.4.2 is installed. An exact-hash, no-space temporary source/profile path with `SAL_DISABLESKIA=1` produced the verified 58-page PDF; all pages and three representative web comparisons were inspected. The first long workspace-path attempt crashed in `ucrtbase.dll` with Windows status `0xC0000409`, so the stabilized conversion procedure is part of the evidence rather than being hidden.
- **Residual limitation:** LibreOffice can differ from Microsoft Word in pagination/font metrics, and the browser screenshot comparison is representative rather than one pair for every section.
- **Status:** remediated for CF-7 technical visual review. Independent human review remains a separate release gate.

### ADM-P0-001 — Active canonical curriculum has no administration authoring path

- **Evidence:** the only superadmin curriculum write routes target legacy `Module`, `Lesson`, `Vocabulary`, `Material`, and `Exercise` models and are wrapped in `legacy.curriculum.writable`. `EnsureLegacyCurriculumWritable` aborts every such write with HTTP 410 whenever an active canonical package exists. The application test suite explicitly requires those writes to fail closed.
- **Impact:** neither superadmin nor any other role can create or update content that reaches the current learner experience from the website. Re-enabling the legacy routes would create a second, conflicting source of truth rather than solve the problem.
- **Required outcome:** build draft authoring, validation, review, preview, and immutable version publication on the canonical schema/compiler. Keep published packages and retained legacy evidence read-only.

### ADM-P1-001 — The 14-template exercise editor is disconnected from canonical activities

- **Evidence:** `ExerciseRequest::TYPES` and the legacy superadmin form support 14 types with bounded type-specific validation and editable answers/options/items. The active package disables the editor, while canonical learner activities use a different versioned package/projection path.
- **Impact:** useful validated editor work cannot author the exercises learners receive. Duplicating it directly would risk divergence between form fields, server validation, canonical schemas, and learner renderers.
- **Required outcome:** retain the useful interaction patterns, but define one canonical exercise-type registry/schema used by admin forms, import validation, preview, publication validation, and learner rendering. Add explicit mappings/tests for every retained template before exposing it.

### ADM-P1-002 — Detailed and global learner-progress administration is absent

- **Evidence:** the sole supervisor route lists only `role = user` accounts in the supervisor's `instansi` and attaches one `overall_progress` percentage. Its table has name, email, and overall-progress columns only. Superadmin has no user-progress route or view.
- **Impact:** authorized staff cannot inspect module, section, activity, attempt, completion, last-activity, or curriculum-version detail, and superadmin cannot review all learners.
- **Required outcome:** add policy-scoped progress dashboards and learner detail views backed by the versioned attempt/completion model from CF-6. Preserve supervisor institution isolation; minimize exposure of free-text, confidence, and any future audio data.

### ADM-P1-003 — User and role administration is absent

- **Evidence:** no user-management routes/controllers/views or role-change request/policy exist. `users.role` is a free-form string defaulting to `user`; model helpers recognize only `user`, `supervisor`, and `superadmin`. There is no `admin` role and no promotion audit trail.
- **Impact:** a superadmin cannot promote another account through the website, delegate content administration, or safely revoke privileges. Direct database edits would bypass confirmation, validation, audit, notification, and session invalidation.
- **Required outcome:** introduce an explicit role model/enum and least-privilege policy matrix; add a dedicated superadmin-only role-change workflow with step-up authentication, last-superadmin/self-change guards, audit records, target-session revocation, notification, and complete authorization tests.

### ADM-P1-004 — Superadmin module statistics report the retired legacy model

- **Evidence:** `AdminDashboardController` calculates `total_modules` with `Module::count()`. The rendered superadmin dashboard reports 8 legacy rows while the active canonical learner curriculum contains 7 chapters.
- **Impact:** the dashboard presents a plausible but incorrect operational count and can mislead content administrators about what is published.
- **Required outcome:** derive published/draft counts from canonical package projections and label every statistic with its lifecycle/version scope. Do not mix archive/legacy counts with active learner-delivery counts.

### ADM-P2-001 — Retained evidence is still presented as primary “Manage” navigation

- **Evidence:** the superadmin sidebar links to “Manage modules/lessons/vocabulary/materials/exercises,” while each destination explains that it is read-only legacy evidence and does not control learner delivery.
- **Impact:** the navigation suggests authoring capability, then presents disabled records from a different content system.
- **Required outcome:** relabel and group the existing screens as legacy/archive evidence until canonical authoring replaces them. Show active package/version status and link to the future canonical workspace; never imply that archive edits publish learner content.

## 6. StayReady inventory and disposition

### 6.1 Verdict

- Source DOCX: **0** occurrences.
- Active learner-facing application/standalone: **0** occurrences or split-brand renderings.
- Active filenames: **0** names containing StayReady.
- Broad retained-text scan: **81 matching lines across 29 text files**, all in tests, current audit/history, canonical legacy provenance, changelog, or frozen migration evidence.
- Current SQLite: **1 row**, `curriculum_entities.payload`, containing the imported `legacy-inventory` phrase `StayReady to Hospitrainity rebrand`. It is provenance data and is not rendered as a product name.

### 6.2 Current non-historical guards/evidence

- `tests/Feature/ProductConsistencyTest.php` — intentional negative assertion.
- `curriculum/hospitrainity/0.3.0-draft/provenance/legacy-inventory.json` — intentional migration evidence.
- `database/database.sqlite` — imported copy of that provenance entity.
- `docs/HOSPITRAINITY_FULL_AUDIT_AND_UPDATE_ROADMAP.md` — finding/history.
- `docs/HOSPITRAINITY_IMPLEMENTATION_CHECKPOINTS.md` — implementation history.
- `CHANGELOG.md` — historical product record.

### 6.3 Frozen migration-history files containing StayReady

The remaining 24 files are under `docs/hospitrainity-migration/`:

- `CHECKPOINTS.md`, `DECISIONS.md`, and `README.md`
- `phase-01/source-manifest.json`
- `phase-03/tools/hsp-editorial.mjs`, `phase-03/tools/hsp-render.mjs`
- `phase-06/editorial-register.json`
- `phase-09/curriculum.sql`
- `phase-10/checkpoint-report.md`, `README.md`, `renderer-spec.md`, `source-manifest.json`, `view-model.json`
- `phase-11/Hospitrainity-Standalone.html`, `source-manifest.json`, `view-model.json`
- `phase-12/checkpoint-report.md`, `Hospitrainity-Standalone.html`, `view-model.json`
- `phase-13/checkpoint-report.md`, `legacy-removal-report.md`, `README.md`, `rebrand-report.md`, `rollback-plan.md`

**Disposition:** retain these files as immutable evidence. Do not bulk-replace or delete their historical brand wording. Exclude them through an explicit allowlist in the active-brand regression test.

## 7. Proposed implementation roadmap

This is a new **Content Fidelity track** after the completed nine-phase application update. Each phase is a checkpoint. Do not start a later phase until the preceding exit gate is evidenced.

### CF-0 — Authority freeze, decisions, and reproducible baseline

1. Record the authoritative DOCX path and SHA-256 in the checkpoint report.
2. Verify the hash equals the package digest before every build; stop on mismatch.
3. Keep `curriculum/hospitrainity/0.3.0-draft` immutable as rollback evidence.
4. Start a new version such as `0.4.0-draft`; reserve `0.4.0` for a passed release gate.
5. Record decisions for response retention, confidence history, optional audio, external-link policy, derived-rubric approval, and legacy completion migration.
6. Rename or document the existing package-to-DB “lossless” test so its scope is unambiguous.

**Exit gate:** authority hash, immutable input, new version, decisions, baseline counts, and rollback path are documented; no generated file has been hand-edited.

### CF-1 — Commit the source compiler and coverage contract

**Checkpoint state (17 July 2026):** implemented and verified. See `docs/CONTENT_FIDELITY_CF1_CF6_CHECKPOINT.md` and `curriculum/evidence/cf-1-to-cf-6.json`.

1. Add a maintained compiler under `scripts/curriculum/` with pinned dependencies and a documented Windows command.
2. Parse DOCX paragraphs, bold section markers, ordered source blocks, tables, hyperlink relationships, and relevant inline emphasis without relying on Word page numbers.
3. Emit deterministic normalized text hashes and source locators from the actual input DOCX, not from a pre-existing digest ledger.
4. Emit a machine-readable source inventory and a human-readable coverage diff.
5. Build twice into separate temporary directories and require byte-identical trees.
6. Add failure tests for changed source hash, missing section markers, duplicated IDs, malformed table rows, lost hyperlink relationships, and unsupported block types.

**Exit gate:** a clean checkout plus the authoritative DOCX deterministically regenerates the new package; expected inventory is 7 chapters, 85 sections, 21 tables, 21 warm-up question paragraphs, 96 exercise prompts, 28 self-rating statements, and 18 external hyperlink relationships.

### CF-2 — Add a lossless structured-content schema

**Checkpoint state (17 July 2026):** implemented and verified across package → database → Laravel/standalone projections. See `docs/CONTENT_FIDELITY_CF1_CF6_CHECKPOINT.md`.

1. Extend lesson sections with ordered block records rather than raw HTML.
2. Support at minimum: paragraph, heading/callout, list item/list, dialogue turn, source table with header/body cells, external link, instruction, and activity embed.
3. Give each block a stable ID, order, source locator/hash, language, and `provenance_kind`.
4. Preserve all source wording, curly punctuation, table cell order, link text, target, and chapter placement.
5. Store source-verbatim material separately from derived outcomes/CEFR/rubrics.
6. Update canonical schemas, package reader, importer, database tables, rollback snapshots, and projections atomically.

**Exit gate:** 85/85 sections have non-empty ordered content; all 21 source tables and 18 hyperlink relationships survive package → DB → view-model round trips; no learner placeholder remains.

### CF-3 — Normalize activity semantics without inventing assessment content

**Checkpoint state (17 July 2026):** implemented and verified for 96 source exercises plus 28 source ratings. Derived scoring/workflow metadata remains explicitly labeled.

1. Keep the 96 existing Practice/Role-play/Quiz stems, 90 accepted strings, and 138 feedback strings byte/text faithful.
2. Move six confidence instructions to activity guidance; create 24 chapter confidence items plus 4 Chapter 1 baseline items.
3. Add structured choices for all 74 selection prompts, preserving source order and option-specific feedback.
4. Add structured tokens and correct order for all 5 ordering prompts.
5. Assign per-prompt scoring modes; review all 11 short-text prompts and classify closed versus open production.
6. Give 4 role-play and 2 service-artifact tasks response constraints and self-check/rubric links without adding model facts.
7. Mark the 6 rubrics and all outcome/CEFR metadata as derived; retain provisional labels until qualified review is actually recorded.

**Exit gate:** no renderer must parse option letters/items out of prose; 124 source learner-response items are represented as 96 exercises plus 28 ratings; every open response avoids false objective grading.

### CF-4 — Deliver the complete module reading experience in Laravel

**Checkpoint state (17 July 2026):** implemented and verified for all 85 sections, including 320 px and desktop rendered checks.

1. Add section routes and repository projections for ordered content blocks.
2. Render vocabulary/comparison/confidence/register tables as semantic HTML tables with responsive overflow, captions where needed, and correct header scope.
3. Render dialogues, model writing, tips, common mistakes, and Take it further tasks with learner-focused components.
4. Restore the 21 warm-up questions as unscored reflection content.
5. Restore Further reading with descriptive link text and a clear external-site indication.
6. Add previous/next navigation across all 85 sections, while retaining dashboard/module navigation.
7. Move provenance hashes and lifecycle diagnostics to a secondary evidence disclosure/admin surface.

**Exit gate:** every section is readable in source order at 320 px and desktop widths; keyboard navigation reaches all controls/links; all 85 sections are accessible from the module sequence.

### CF-5 — Implement accessible activity response forms

**Checkpoint state (17 July 2026):** implemented and verified for native selection, rating, ordering, closed text, open production, self-check, validation focus, and explicit model reveal.

1. Use native HTML controls first:
   - radio groups with `fieldset`/`legend` for single selection and 1–5 ratings;
   - labeled text inputs/textareas for short/open responses;
   - keyboard-operable move-up/move-down controls plus an accessible non-drag alternative for ordering;
   - structured self-assessment controls for role-play/writing rubrics.
2. Do not reveal an objective answer as an attempted response; require an attempt or an explicit “show model without answering” choice.
3. Provide option-specific feedback and a programmatically announced status message.
4. Preserve answers after validation errors and return focus to the first error/summary.
5. Validate allowed nested keys and option values on the server; never trust client-provided correctness or scores.
6. For open production, show model/rubric feedback and record self-check state; do not fabricate automated language scoring.
7. Make optional audio rehearsal local/ephemeral unless a separately approved consent, storage, retention, deletion, and accessibility policy exists.

**Exit gate:** all response types work with keyboard only and screen-reader semantics; objective checks cannot be forged by changing client payloads; open responses are never falsely graded.

### CF-6 — Versioned attempts, completion, confidence history, and privacy

**Checkpoint state (17 July 2026):** implementation and technical verification complete. Raw open responses/audio are not persisted; 24 legacy completions are version-labeled and non-completing. **Publication limitation:** an institutional time-based learner-response retention period is still unapproved; current rows cascade on account deletion. See the checkpoint's privacy boundary.

1. Introduce version-bound attempt/response records rather than overloading legacy completion rows.
2. Distinguish `viewed`, `started`, `attempted`, `self_checked`, and `completed` events.
3. Define activity-specific completion:
   - objective selection/order: every required item attempted and checked;
   - short/open production: response entered or an explicit practice/self-check acknowledgement;
   - confidence: all four statements rated;
   - Chapter 1 baseline: all four statements rated or explicitly skipped.
4. Save an attempt and its responses in one database transaction.
5. Show baseline-versus-current confidence history without presenting self-rating as proficiency or CEFR evidence.
6. Apply the approved retention/deletion policy; minimize stored free text and never store audio by default.
7. Map existing 24 activity completion records with package/version provenance. Preserve history; do not silently treat legacy reveal-only completion as a fully attempted new activity.

**Exit gate:** progress cannot reach completion without the defined participation state; retries are idempotent; old progress is preserved and clearly labeled; privacy decisions are implemented and documented.

### CF-7 — Standalone parity, brand guard, QA, and release

**Checkpoint state (18 July 2026):** technical implementation and automated verification complete; publication remains deliberately blocked by human/owner decisions. The generated standalone is session-only, the active-brand guard reports zero violations, Laravel/standalone projections match the canonical package, and the PHP/Node/Chromium/build/dependency gates pass. The exact-hash DOCX now has a verified 58-page LibreOffice render, a full-page inspection, and passing representative source-to-web comparisons. The link report still requires owner decisions (13 reachable, 2 access-blocked, 1 HTTP 410), and the recorded retention, qualified-review, accessibility-review, link, and owner-approval gates remain open. The package therefore remains `0.4.0-draft`; see `docs/CONTENT_FIDELITY_CF7_CHECKPOINT.md` and `curriculum/evidence/cf-7.json`.

1. Generate standalone data/components from the same enriched canonical view model; do not hand-edit the HTML.
2. Provide local-only attempt state if approved, or clearly state that standalone progress is session-only; never imply server synchronization.
3. Add source-coverage tests, package-to-DB projection tests, Laravel feature tests, JavaScript unit tests, and Chromium E2E for every response form.
4. Add active-brand scanning for literal, separated, split-markup, case, config/mail/seed, database projection, and standalone occurrences with a historical allowlist.
5. Test WCAG 2.2 AA concerns: labels/instructions, names/roles/values, focus visibility/not obscured, target size minimum, dragging alternatives, error identification, and status announcements.
6. Run a link-health report for all 16 unique external targets; content-owner review decides replacements/removals.
7. Render the DOCX and representative web sections for visual comparison using a supported environment. **Completed 18 July 2026:** exact-hash 58-page source render inspected; three representative comparisons verified.
8. Change the package from `0.4.0-draft` to `0.4.0` and `published` only after every gate passes and review evidence is recorded.

**Exit gate:** one authoritative source generates complete, semantically equivalent Laravel and standalone experiences; all release gates below pass; active StayReady occurrences are zero.

## 7A. Administration and authoring roadmap extension

This is a separate **Administration track** derived from the confirmed capability audit. Execute it in order and preserve a checkpoint after every phase. It depends on the canonical schema and attempt model in CF-1 through CF-6; it must not be implemented by removing `legacy.curriculum.writable` or making retained legacy rows authoritative again.

### ADM-0 — Authority, role matrix, terminology, and truthful metrics

**Checkpoint state (17 July 2026):** complete and accepted. The product owner approved ADR-002 by explicitly instructing Codex to continue with ADM-1. See `docs/decisions/ADR-002-administration-authority-and-data-boundaries.md` and `docs/decisions/ADM-0-administration-capability-matrix.md`.

1. Approve the administration role matrix:
   - `user`: learner access only;
   - `supervisor`: progress for learners in the same institution only;
   - `admin`: canonical draft content authoring and approved progress visibility, but no role assignment or publication by default;
   - `superadmin`: global administration, canonical publication, and role management.
2. Decide whether `admin` may see global individual progress or only aggregate/de-identified reporting. Default to least privilege until approved.
3. Keep current published canonical packages and legacy rows immutable. Relabel the five existing “Manage” destinations as legacy/archive evidence.
4. Replace `Module::count()` on the superadmin dashboard with canonical active/draft version counts and clearly labeled legacy/archive counts only where operationally useful.
5. Define module/lesson import formats, maximum sizes, accepted media types, review ownership, publication authority, response-data visibility, export policy, and audit retention.
6. Add an administration capability test matrix before changing roles or routes.

**Exit gate:** approved role/data-visibility/import decisions are recorded; all dashboard numbers identify their data source/version; retained legacy screens cannot be mistaken for active authoring.

### ADM-1 — Explicit roles, policies, and secure user administration

**Checkpoint state (17 July 2026):** implementation and verification complete; paused for product-owner review before ADM-2. The accepted single-approver baseline uses a recently confirmed password, typed target/role confirmation, verified target, reason, audit, throttling, and target-session revocation for superadmin promotion. A dual-approval workflow remains a separate governance enhancement because no quorum/recovery policy has been defined.

1. Replace free-form role comparisons with a backed role enum or equivalently constrained domain value, including the new `admin` value. Add a safe migration that rejects or reports unknown existing values before applying a database constraint.
2. Update role middleware, landing resolver, navigation, policies, factories/seeders, and tests for all four roles. Fail closed for unknown roles.
3. Add a paginated superadmin user directory with server-side search/filter by name, canonical email, institution, verification state, and role. Do not expose password hashes, remember tokens, or unrestricted model fields.
4. Implement a dedicated role-change endpoint and FormRequest; never accept `role` through a generic user profile mass-assignment path.
5. Permit only superadmin to assign `user`, `supervisor`, or `admin`. Treat promotion to `superadmin` as a separate high-risk action requiring a recently confirmed password, explicit target/role confirmation, a recorded reason, and a verified target email.
6. Block self-role changes through this workflow and prevent removal/disablement of the last active superadmin. Decide whether production superadmin promotion also requires a second approver.
7. Perform each change transactionally; record actor, target, old/new roles, reason, timestamp, and request metadata appropriate to the privacy policy. Rotate the target's remember token and revoke their database-backed sessions so new permissions take effect only after reauthentication. Notify the target without disclosing secrets.
8. Add throttling and tests for authorization bypass, CSRF, invalid/unknown roles, unverified targets, stale password confirmation, self-change, last-superadmin protection, concurrent changes, session revocation, and audit integrity.

**Exit gate:** a superadmin can safely promote another verified learner to `admin` or, through the higher-assurance path, `superadmin`; no other role can change roles; every change is auditable and old sessions lose access.

### ADM-2 — Canonical draft, review, preview, and publication foundation

**Checkpoint state (18 July 2026):** implementation and verification complete; paused for product-owner review before ADM-3. Admin and superadmin can create isolated clone/empty workspaces, edit the ADM-2 canonical entity/block surface with revision checks, validate and preview without learner progress writes, and request changes. Only superadmin can approve, password-confirm, publish a new immutable release version, or roll back the active publication. The real local database migration is applied from a verified pre-ADM-2 backup, but no persistent draft or publication was created during verification and active delivery remains `0.4.0-draft` with unchanged hashes.

1. Create a mutable draft workspace by cloning an active canonical version or starting an explicitly empty draft; never edit the published package in place.
2. Model lifecycle transitions such as `draft → validating → in_review → approved → published` with policy checks and recorded actor/timestamps. Invalid transitions must fail closed.
3. Let admin/superadmin create, reorder, archive, and restore draft modules/chapters, lessons/sections, outcomes, and typed content blocks from the CF-2 schema.
4. Add draft autosave or explicit save with optimistic locking/version checks so concurrent editors cannot silently overwrite each other.
5. Add learner preview routes that render the exact canonical view model without making the draft visible to ordinary learners or counting preview interactions as progress.
6. Provide a source-aware diff and validation report before approval. Only superadmin may publish by default.
7. Publish by generating a new immutable canonical version and switching the active projection transactionally; retain the prior version and tested rollback path.

**Exit gate:** authorized staff can create and preview a complete draft without changing active learner delivery; publication creates a validated immutable version and rollback restores the prior version.

### ADM-3 — Module/lesson import, structured authoring, and asset safety

**Checkpoint state (19 July 2026):** implementation and technical verification complete; paused for product-owner review before ADM-4. Admin and superadmin now have field-specific editors for all nine canonical block types, a private bounded authority-DOCX dry-run compiler/acceptance flow, and content-checked digest-addressed image/audio assets with provenance and protected delivery. Import acceptance replaces only the selected draft workspace, and active delivery remains `0.4.0-draft` with unchanged hashes. Antivirus/retention policy and deployment worker supervision remain deployment gates; arbitrary ZIP/package import was not approved or implemented.

1. Add manual editors for canonical sections/blocks, tables, links, dialogues, materials, vocabulary, and source/provenance metadata. Use the same schema as the compiler; do not introduce a parallel admin-only content format.
2. Add approved import paths only after ADM-0 decisions. At minimum, support the authoritative DOCX compiler workflow; optionally support a documented canonical manifest/package format with schema versioning.
3. Upload source files to private/quarantine storage using generated server filenames. Validate declared purpose, content-derived MIME/type, extension, size, and schema before processing; do not place untrusted originals under `public/`.
4. If archive import is approved, defend against path traversal, symlinks, decompression bombs, duplicate IDs, oversized entry counts, and executable/unexpected content. Do not shell-execute uploaded content.
5. Run compiler/import as a bounded queued job where appropriate, expose progress/error reports, and leave active delivery unchanged on any failure.
6. Deduplicate assets by digest, record provenance/licensing/alt text, and verify every reference before review. Treat antivirus scanning and retention of rejected uploads as deployment decisions, not assumed infrastructure.
7. Provide a dry-run inventory/diff showing created, changed, removed, rejected, and unclassified records before an authorized user accepts an import into a draft.

**Exit gate:** admin/superadmin can manually build or safely import canonical modules/lessons into a draft; malformed or hostile inputs cannot modify active content or escape storage boundaries; every accepted asset and source block is traceable.

### ADM-4 — Canonical exercise-template builder with adjustable answers

**Checkpoint state (19 July 2026):** implementation and technical verification complete; paused for product-owner review before ADM-5. A shared versioned registry explicitly maps all 14 retained authoring names to canonical response/scoring semantics. Twelve templates are enabled end to end; `spelling_quiz` and `listening_task` remain visibly unavailable until an equivalent prerecorded-audio alternative/accommodation is approved. Admin and superadmin can create, edit, reorder, duplicate, and preview canonical exercises with server-owned stable IDs and server-authoritative validation/scoring. Browser QA also corrected a non-operational preview submission path and a narrow-screen duplicate-selector overflow. Active delivery remains immutable `0.4.0-draft` with unchanged hashes; no ADM-4 migration or persistent learner attempt was created.

1. Inventory the 14 legacy exercise types and map each one explicitly to supported canonical response/scoring semantics. Mark a type unavailable until its admin form, server validation, canonical schema, preview, learner renderer, and tests agree.
2. Build a shared type registry/schema for fields, cardinality limits, option/item identifiers, answer shape, scoring policy, feedback, accessibility requirements, and renderer support. Generate or consume this contract across the admin and learner paths instead of maintaining silent parallel lists.
3. Let authorized editors create an exercise from a template, edit prompts/options/items/accepted answers/feedback/rubrics, reorder items, duplicate a draft exercise, and preview it as a learner.
4. Validate correct answers against submitted allowed options/items server-side. Keep stable IDs separate from editable labels and never trust client-computed scores.
5. Require explicit scoring modes for exact, normalized closed response, ordered response, model/self-check, rubric/self-assessment, and unscored confidence. Do not permit objective grading for open language merely because an answer example exists.
6. Treat a published exercise edit as a new draft revision. Preserve attempts against the version the learner actually saw; do not rewrite historical questions or answers in place.
7. Add keyboard/screen-reader tests for every exposed template and a learner-preview parity test against the published renderer.

**Exit gate:** all enabled templates can create valid canonical exercises with adjustable answers and faithful learner previews; no enabled template has divergent form, validation, schema, renderer, or version-history behavior.

### ADM-5 — Policy-scoped learner progress administration

**Checkpoint state (19 July 2026):** implementation and technical verification complete; paused for product-owner review before ADM-6. Superadmin has a global identity-level, fixed-page progress dashboard and versioned learner detail; supervisor detail is enforced by an exact, non-empty same-institution policy; admin receives aggregate/de-identified totals only. Filters cover learner, institution, package version, module, exact highest progress state, and recent activity. Detail reports module → section/activity state, attempts, last activity, completion time, version, and legacy migration labels. Private responses/confidence/audio are not queried, CSV export remains visibly disabled, and active completion counts only retained published activities. No migration or persistent learner/admin data was created.

1. Add a global, paginated progress dashboard for superadmin and any explicitly approved admin capability; retain the supervisor's same-institution restriction as a server-side policy/query scope, not a client filter.
2. Add search/filter by learner, institution, canonical package version, module, status, and recent activity. Bound page size and avoid per-row progress queries.
3. Add learner detail showing module → section/activity progression, defined state (`viewed`, `started`, `attempted`, `self_checked`, `completed`), attempt count, last activity, completion time, and package version. Clearly label migrated legacy completion.
4. Default progress views to metadata and completion state. Do not expose raw open responses, confidence answers, or future audio merely because an account is administrative; require separate approved policy/capability and audit access where such data is necessary.
5. Define CSV export separately with authorization, column allowlisting, neutral spreadsheet-cell encoding, audit logging, row limits, and retention guidance. Do not add export by assumption.
6. Add empty/error/loading states, accessible tables/filter controls, and direct links that preserve authorization boundaries.
7. Test cross-institution access, direct-ID enumeration, admin/superadmin boundaries, unknown/stale package versions, deleted users, pagination/query bounds, cache invalidation, and privacy-field exclusion.

**Exit gate:** an authorized global administrator can review every learner's lesson/activity progress without gaining unintended access to private response content; supervisors cannot access learners outside their institution.

### ADM-6 — Administration integration, security review, and release

**Checkpoint state (19 July 2026):** implementation and local technical verification complete; paused for product-owner review and production operations evidence. Canonical-first navigation, a password-confirmed superadmin audit review, bounded audit payloads, active/draft/archive dashboard separation, compiler outcome events, and the administration recovery runbook are implemented. The post-fix suite passes 215 Laravel tests / 3,783 assertions, 47 Node tests, 7 isolated Chromium journeys, JavaScript lint, production asset build, dependency audits, canonical/source/brand checks, and rendered mobile multi-role browser QA. Active delivery remains `0.4.0-draft` with unchanged fingerprints. This is not production-release approval: the local deployment checker correctly rejects the development environment, and no production database/storage backup or isolated restore drill has been supplied.

1. Replace archive-first sidebar navigation with canonical Content, Exercises, Progress, Users, Audit, and Legacy Evidence destinations according to policy.
2. Add audit-log review for publication, import, content lifecycle, role changes, and approved sensitive progress access; audit records must not contain passwords, session IDs, raw uploaded secrets, or unnecessary learner responses.
3. Run the complete role/route authorization matrix, canonical content pipeline suite, upload abuse tests, publication rollback tests, progress privacy tests, accessibility checks, and browser journeys for every role.
4. Verify that admin drafts cannot affect learner counts/progress, archived versions remain reproducible, legacy writes remain HTTP 410, and published statistics match the active canonical projection.
5. Document operational recovery for a failed import/publication, lost superadmin access, session revocation, audit review, and draft conflict resolution.
6. Release behind explicit migration/deployment steps with a database backup and rollback checkpoint. Do not seed or silently promote a production account.

**Exit gate:** requested administration capabilities are available through canonical, policy-protected workflows; authorization, audit, rollback, privacy, accessibility, and active-delivery isolation gates all pass.

## 8. Required release gates

### 8.1 Source fidelity

- Supplied DOCX SHA-256 equals the declared authority hash.
- 7 chapters and 85 section headings/order match.
- 85/85 sections contain source-derived blocks; placeholder count is 0.
- 21/21 source tables and 18/18 hyperlink relationships are represented.
- 21/21 warm-up question paragraphs are learner visible.
- 96/96 Practice/Role-play/Quiz stems, 90/90 non-empty answer strings, and 138/138 feedback strings remain exact after normalization rules.
- 28/28 confidence/baseline statements are represented as individual 1–5 rating items.
- Source coverage report has no unclassified or silently dropped block.
- Grand Serenata and other source scenario identities are preserved; no new hotel facts are invented.

### 8.2 Experience fidelity

- All 74 selection prompts render structured choices.
- All 5 ordering prompts have keyboard-operable ordering and a non-drag alternative.
- All 11 short-text prompts use reviewed item-level scoring/self-check policies.
- All 4 role-play and 2 service-artifact prompts provide a response/rehearsal path.
- All 28 ratings use labeled 1–5 controls and can be skipped only through an explicit path.
- Answers/feedback are accessible but do not substitute for an attempted response without disclosure.
- Completion rules are tested per response type.

### 8.3 Provenance and build integrity

- Two clean compiler runs produce byte-identical package trees.
- Source-to-package, package-to-DB, and package-to-standalone tests are separately named and scoped.
- Source-verbatim and derived-reviewed metadata are distinguishable.
- New package version is immutable after publication and does not contain `-draft`.
- Rollback restores prior database projection and standalone checksum.

### 8.4 Brand integrity

- Zero active StayReady occurrences in rendered learner/auth/role pages, active source/config/mail/seed data, standalone, and current product metadata.
- Historical paths and canonical legacy provenance are explicitly allowlisted.
- No historical migration evidence is rewritten merely to make a global grep return zero.

### 8.5 Administration integrity

- Published canonical packages and retained legacy evidence remain immutable; no active feature depends on legacy curriculum writes.
- Every admin route has a named policy/capability and passes the four-role authorization matrix, including direct URL/request attempts.
- Canonical drafts remain invisible to ordinary learners and do not affect learner progress until an authorized publication succeeds.
- Every enabled exercise template has schema/form/validation/preview/learner-renderer parity and preserves version-bound attempts.
- Superadmin can view global progress; supervisor scope cannot escape its institution; admin progress scope matches the recorded decision.
- Only superadmin can change roles; superadmin promotion passes step-up, verification, audit, session-revocation, last-superadmin, and concurrency tests.
- Dashboard module/content counts equal the active canonical projection and identify draft/archive counts separately.
- Upload/import failure leaves active delivery unchanged and passes hostile-file/archive cases applicable to the approved formats.

## 9. Decision gates an implementing AI must not guess

1. **Response retention:** whether free-text role-play/writing responses are server-stored, stored only locally, or discarded after self-check.
2. **Confidence history:** retention period, visibility to supervisors, and deletion/export behavior.
3. **Audio:** whether recording is wanted at all. Server storage requires explicit consent, retention, deletion, access, and transcript/accessibility policy.
4. **Derived metadata approval:** who qualifies to approve CEFR mappings, outcomes, and provisional rubrics.
5. **External links:** what to do when a source link is dead or no longer reliable; the compiler must only report the condition.
6. **Legacy completion migration:** whether a prior reveal-only completion remains “completed (legacy)” or becomes “reviewed; new attempt available.”
7. **Admin data scope — decided in ADR-002:** `admin` receives aggregate/de-identified progress only by default; `superadmin` is the global identity-level administrator; `supervisor` remains institution-scoped.
8. **Publication authority — decided in ADR-002:** `admin` may draft/review but may not publish; publication remains superadmin-only by default.
9. **Initial import formats and infrastructure — decided in ADR-002:** begin with DOCX only at 2 MiB plus the documented bounded media allowlist/quarantine rules. Generic manifests/packages and antivirus/rejected-file operations require their own evidence before expansion.
10. **Superadmin promotion assurance — decided for ADM-1:** one recently password-confirmed superadmin may promote another verified account using the separate typed-confirmation, reason, audit, throttling, and session-revocation workflow. Dual approval is not claimed; adding it requires an approved quorum, approver lifecycle, and recovery policy.
11. **Progress/export privacy:** which response fields administrators may see, whether CSV export is required, allowed columns, audit/retention rules, and spreadsheet-injection handling.

Until decided, use the privacy-minimizing behavior: no server audio, no automated language grading, and no silent storage of open responses.

## 10. AI implementation contract

1. Recalculate the DOCX SHA-256 before work. Stop and report if it differs from the declared authority.
2. Never modify the supplied DOCX during compilation or testing.
3. Never mutate `0.3.0-draft` in place; create a new version and preserve rollback evidence.
4. Do not paste or hand-edit generated package/standalone output. Fix the compiler/schema and regenerate.
5. Do not invent prose, translations, options, answer variants, hotel facts, links, scoring rules, or reviewer approvals.
6. Keep exact source text and derived metadata in distinguishable fields with separate provenance.
7. Add a failing source-coverage or behavior regression before each fix where practical.
8. Use native HTML controls and progressive enhancement; use ARIA only where native semantics are insufficient.
9. Validate every submitted response server-side against the package version and allowed option/item IDs.
10. Use database transactions for attempt/response/completion writes.
11. Preserve historical StayReady evidence and use an explicit active-artifact allowlist.
12. At every checkpoint report: changed files, source counts before/after, narrow/full test evidence, browser evidence, known limitations, decisions needed, and rollback instructions.
13. Do not mark a phase complete because tests are green if the source coverage report still has dropped or unclassified content.
14. Do not re-enable legacy curriculum writes or claim their CRUD pages author current learner content. Build canonical draft/version workflows and keep archive evidence read-only.
15. Centralize roles/capabilities and exercise-type contracts. Do not add another uncoordinated string list in controllers, views, validation, JavaScript, and tests.
16. Never expose role changes through generic profile updates or mass assignment. Require a dedicated policy, request, audit, step-up, and target-session revocation path.
17. Enforce progress and draft visibility in server-side policies/query scopes. Hiding navigation or filtering rows in the browser is not authorization.
18. Treat uploaded curriculum/media as untrusted input: private/quarantine storage, bounded validation, generated filenames, safe parsing, no execution, and no active publication on partial failure.

## 11. Reproducible evidence commands

Run from the project root in Windows PowerShell. Adjust executable paths only when the environment actually differs.

```powershell
Get-FileHash -LiteralPath 'C:\Users\dhion\Desktop\Documents\000 - Thesis Dhion Setio\Revisi 30 Juni 2026\Learning Materials - Fixed\Hospitrainity.docx' -Algorithm SHA256

& 'C:\php\php.exe' artisan hospitrainity:curriculum verify
& 'C:\php\php.exe' artisan test --filter=CanonicalCurriculum
& 'C:\php\php.exe' artisan route:list --path=superadmin --except-vendor

rg -n "legacy\.curriculum\.writable|Route::.*(users|progress)|isSuperAdmin|isSupervisor|isLearner|total_modules" routes app resources tests

rg -n --hidden -i 'stay[ _.-]*ready|stay\s*<[^>]+>\s*ready' app resources routes config database standalone public tests curriculum docs CHANGELOG.md .env .env.example --glob '!vendor/**' --glob '!node_modules/**' --glob '!.git/**' --glob '!storage/framework/**' --glob '!tmp/**'

& 'C:\Program Files\nodejs\npm.cmd' run lint:js
& 'C:\Program Files\nodejs\npm.cmd' run test:js
& 'C:\Program Files\nodejs\npm.cmd' run test:e2e
& 'C:\Program Files\nodejs\npm.cmd' run build
& 'C:\php\php.exe' artisan test --display-all-issues --fail-on-all-issues --disallow-test-output
```

The new compiler phase must add a documented command that accepts the source DOCX explicitly and emits its coverage/determinism reports. That command does not yet exist in the repository.

## 12. Primary implementation references

- [HTML Living Standard — forms and native controls](https://html.spec.whatwg.org/multipage/forms.html)
- [W3C Web Content Accessibility Guidelines 2.2](https://www.w3.org/TR/WCAG22/)
- [W3C WAI — grouping form controls](https://www.w3.org/WAI/tutorials/forms/grouping/)
- [W3C WAI — labeling controls](https://www.w3.org/WAI/tutorials/forms/labels/)
- [W3C WAI — form notifications](https://www.w3.org/WAI/tutorials/forms/notifications/)
- [Laravel 12 — validating arrays and nested input](https://laravel.com/docs/12.x/validation#validating-arrays)
- [Laravel 12 — authorization and policies](https://laravel.com/docs/12.x/authorization)
- [Laravel 12 — password confirmation and session invalidation](https://laravel.com/docs/12.x/authentication#password-confirmation)
- [Laravel 12 — file validation](https://laravel.com/docs/12.x/validation#validating-files)
- [Laravel 12 — database transactions](https://laravel.com/docs/12.x/database#database-transactions)

These references support native labeled controls, grouped radio choices, accessible status/error communication, allowlisted nested request validation, policy-based authorization, step-up authentication, content-aware file validation, and atomic response/administration writes. They do not override the DOCX as the authority for learning content or decide Hospitrainity's role/privacy policy.

## 13. Final definition of done

Hospitrainity is source-faithful only when a learner can move through the complete seven-chapter manuscript, read every teaching section, use every source table/link, perform each exercise in its intended response form, compare all baseline/chapter confidence statements, and receive source-authored model feedback—on both Laravel and standalone—from one deterministic versioned build. It is administratively complete only when authorized staff can create/import and preview canonical draft modules/lessons, create exercises through validated templates with adjustable answers, publish immutable versions through review, inspect policy-scoped learner progress, and safely manage roles with audit and lockout protections. Structural IDs, hashes, stable checksums, disabled legacy writes, and hidden buttons are necessary controls, but they are not substitutes for those complete learner and administration experiences.

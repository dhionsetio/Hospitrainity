# Hospitrainity next-generation multi-role audit and AI-executable update roadmap

Document version: 1.1.9  
Audit date: 2026-07-19; authority candidate review 2026-07-22 (Asia/Jakarta)  
Implementation status: B00 through B06 and the consolidated tester update are locally complete; Firefox launch remains environment-blocked  
Intended executor: GPT-5.6 Sol / Codex or a comparably capable repository-editing agent  
Scope: Guest, learner (database role value user), Supervisor, Admin, Superadmin, curriculum fidelity, thesis alignment, responsive/mobile experience, accessibility, application security, privacy, lecturer operations, assessments, analytics, communications, scheduling, exam integrity, account deletion, engineering quality, and release operations

Revision 1.1.0 scope: corrected the shared time/timezone dependency without changing batch count or order; normalized every finding to the evidence/impact/fix/acceptance structure, including six pre-existing P1 field omissions; added complete finding-level traceability; clarified that competitor observations are official-public-source benchmarking rather than authenticated hands-on product audits; and removed the assumed first-use study sample size. This revision intentionally does not add a mandatory per-batch technical-design artifact and does not split or add implementation batches.

Revision 1.1.1 scope: refreshed the thesis authority hash after a confirmed 2026-07-21 file change and repeated the complete thesis product-intent audit before further implementation. The current thesis preserves the existing user, cross-device, easy-navigation, engagement, validation, and AI-conflict boundaries. Batch count and order are unchanged. Evidence is recorded in `docs/checkpoints/NG-AUTHORITY-DRIFT-2026-07-21.md`.

Revision 1.1.2 scope: records the owner's complete B06 approval and the cross-batch requirement for a fully usable, institution-scoped Instructor workspace. It also schedules UI implementation as checkpointed vertical slices after the owning domain contracts exist. Batch count and dependency order are unchanged.

Revision 1.1.3 scope: refreshed the thesis authority hash after a second confirmed 2026-07-21 byte change and repeated the complete thesis product-intent audit. The current manuscript preserves the accepted audience, cross-device, navigation, engagement, validation, outcome-claim, and AI-conflict boundaries. B06 decisions, batch order, and UI sequencing are unchanged. Evidence is recorded in `docs/checkpoints/NG-AUTHORITY-DRIFT-2026-07-21-02.md`.

Revision 1.1.4 scope: records completion of the B06-A expand-only domain, relationship-authorization, immutable Course Revision, and shared Clock/TimeContext foundation. B06 remains in progress: UI-R02 is the next checkpoint, followed by B06-B Class/roster operations and B06-C cutover verification. No browser-visible B06 capability is claimed by B06-A.

Revision 1.1.5 scope: records completion of the UI-R02 responsive learner shell against the verified B06-A context boundary. B06 remains in progress: B06-B Class/roster operations is next, followed by B06-C cutover verification. UI-R02 adds no lecturer-facing Course/Class behavior and makes no branded-browser, physical-device, production, engagement, or accessibility-conformance claim.

Revision 1.1.6 scope: records completion of B06-B Class and roster operations. Institution Admins can create and administer Classes; assigned Instructors receive exact-Class management; approved invitation, classroom-code, manual-enrollment, status, transfer, teaching-team, lifecycle, safe-copy, and synthetic-preview workflows are implemented with retained history and privacy-bounded roster fields. B06 remains in progress at B06-C context cutover and final verification. No production, physical-device, branded-browser, lecturer-usability, or independent-conformance claim is made.

Revision 1.1.7 scope: records local technical completion of B06-C and B06. Learner delivery, attempts, progress, completion, search, invitation acceptance, and Instructor progress access now use the exact active Class enrollment and immutable Course Revision. Stale and cross-Class access fails closed, retained history remains linked, and the full 374-test PHP regression passed. Main-database migration, production operations, specialist review, physical-device/branded-browser evidence, lecturer usability, and later Instructor capabilities remain open. B07 is not authorized until its mandatory questionnaire is answered and recorded.

Revision 1.1.8 scope: records the owner's 2026-07-22 authorization to run the remaining work as one consolidated tester update, use the documented recommendations without further routine questionnaires, and implement the supplied v0.8 learning-content research and CEFR claims while keeping their lack of independent review explicit. A deterministic, non-active v0.8 candidate bundle now compiles from the supplied DOCX. The active authority configuration and v0.4 package remain unchanged until the external authority file is replaced with approval and the candidate is transformed into the active package contract. Evidence and resume instructions are in `docs/checkpoints/NG-AUTHORITY-CANDIDATE-2026-07-22.md`.

Revision 1.1.9 scope: records local completion of the consolidated tester implementation. The full Laravel suite passed 389 tests with 5,931 assertions; 60 Playwright journeys passed across desktop Chromium, desktop WebKit, mobile Chromium, and mobile WebKit; and the Python visual verifier passed 31 learner checks. Firefox failed during browser launch before application navigation, leaving 14 Firefox journeys unrun. The v0.8 candidate remains verified and inactive. No external authority replacement, main-database migration, or production deployment occurred. Exact evidence and next-agent boundaries are in `docs/checkpoints/NG-CONSOLIDATED-TESTER-UPDATE-2026-07-22.md`.

## Current execution handoff

- Last completed domain checkpoint: B06-C on 2026-07-22.
- Current delivery mode: one consolidated tester update. The owner waived routine B07 through B17 questionnaire pauses and accepted the roadmap recommendations as defaults.
- Active learning authority: the existing `Hospitrainity.docx`, SHA-256 `7F8A2C62DB6884498F7A1C2DE56E79E39609262944A685FE7EB97F702444CBB4`.
- Validated replacement candidate: `Hospitrainity New.docx` v0.8.0-draft, SHA-256 `5DE098DCDCC6405093004F6253A1DEF2F5DC4D85BC1CCACF1416376F3A77569B`.
- Candidate compiler: `scripts/curriculum/compile_next.php`; verifier: `scripts/curriculum/verify_next.php`.
- Candidate state: deterministic and structurally validated, but not active. It contains 7 chapters, 88 source sections, 28 outcomes, 151 activities or response items, and 13 vocabulary or phrase nodes. Its 18 external relationship records have no hyperlink anchors in the document body, so their labels remain unmapped.
- Consolidated tester state: locally implemented and integrated. The available Laravel, static, build, governance, and four non-Firefox browser-project gates pass. Firefox remains unverified because its local headless runtime failed before navigation.
- Do not redo B00 through B06 or rerun questionnaires. Resume from the latest checkpoint, preserve concurrent working-tree changes, and follow the exact next actions in Section 22.

## 0. Executor hard rules

The future AI MUST treat this section as an execution contract.

1. Read this entire document, the current repository instructions, the thesis DOCX, the Hospitrainity learning-materials DOCX, and the latest accepted checkpoint documents before changing code.
2. Recalculate both authority-document SHA-256 hashes before every batch. If either differs from section 2, STOP, report the mismatch, and repeat the affected source audit before implementation.
3. Do not claim that this 2026-07-19 audit proves the future repository state. Re-run the baseline commands and inspect all changes made after this document.
4. Use the recorded owner decisions and roadmap recommendations. The owner authorized one consolidated tester update on 2026-07-22 and waived routine B07 through B17 questionnaire pauses. Ask only when an undiscoverable choice requires credentials, a vendor contract, an external write, destructive data action, production deployment, or material scope expansion.
5. Continue from the latest checkpoint. Do not restart completed batches or interpret the questionnaire waiver as permission for unapproved external writes, production deployment, or destructive operations.
6. Preserve internal batch ownership and acceptance checks for traceability, but one consolidated delivery checkpoint may cover several batches.
7. Preserve working CF-1 through CF-7 and ADM-0 through ADM-6 behavior unless a batch explicitly replaces it and regression tests prove the replacement.
8. Never mutate an active/published canonical package in place. Author in an isolated draft, validate, preview, approve, publish a new immutable version, retain provenance, and preserve rollback.
9. Never invent learning prose, hotel facts, translations, answer variants, assessment rules, CEFR claims, source approvals, research findings, or production configuration.
10. Keep source text, derived metadata, editorial additions, and AI-generated material distinguishable and independently attributable.
11. Use server-side policies and query scopes for authorization. Hidden navigation is not authorization.
12. Use FormRequest/DTO validation, transactions, idempotency, optimistic locking where editors can collide, and explicit state machines for workflows. Do not coordinate security-critical behavior with free-form strings spread across views and controllers.
13. Treat every upload and rich-media URL as untrusted. Use private quarantine, generated names, content inspection, size/decompression limits, malware scanning where selected, explicit promotion, and protected delivery.
14. Do not expose raw learner responses, recordings, confidence answers, private paths, tokens, or audit secrets unless an owner-approved privacy contract explicitly authorizes each field, purpose, audience, retention rule, and access audit.
15. Do not describe a normal web page as a secure/lockdown exam. Browser kiosk/proctoring claims require an independently installed and verified secure-exam client or service plus accessibility, privacy, legal, support, and accommodation decisions.
16. Use WCAG 2.2 AA as the engineering target. Automated checks are regression evidence, not a conformance claim. Include keyboard, zoom/reflow, high contrast, reduced motion, screen-reader, and real-device checks appropriate to each batch.
17. Use OWASP ASVS 5.0.0 as the application-security verification baseline and record requirement identifiers with the version prefix when a control is claimed.
18. Back up the actual target database and private storage before destructive migrations. Test restoration in isolation. A backup file existing is not proof that restoration works.
19. Never run seeders against production. Never hardcode or publish bootstrap credentials.
20. Never test mutations against the user's main database when an isolated disposable database can provide the evidence.
21. Do not delete historical StayReady provenance. Keep it non-rendered and allowlisted by exact record/path in the active-brand guard.
22. Do not add a second curriculum engine, assessment engine, role system, or analytics definition. Extend or replace through a documented migration and retire the old path deliberately.
23. Use apply_patch for hand edits, preserve unrelated user changes, and obtain a usable version-control baseline before broad feature implementation.
24. At every checkpoint report: questionnaire answers used; changed files; migrations/data impact; security/privacy/accessibility impact; narrow and full tests with exact totals; browser/device evidence; known limitations; production gates; rollback; and the exact next batch.
25. If evidence conflicts, prefer primary source and reproducible current behavior; label the conflict and STOP where resolution is a product/academic/legal decision.

## 1. Evidence vocabulary

Every future finding and checkpoint MUST use one of these states:

| State | Meaning |
|---|---|
| CONFIRMED | Proven by current code, data, rendered role journey, reproducible test, document text, or official primary source. |
| INFERRED | A reasoned conclusion from confirmed evidence, explicitly identified as inference. |
| UNVERIFIED | Plausible but not tested or unavailable, such as production topology, assistive-technology conformance, or institutional policy. |
| PREFERENCE | Owner choice; the AI must ask and wait. |
| LEGAL_REVIEW | Requires qualified Indonesian counsel/data-protection review; the AI may implement an approved rule but must not invent legal conclusions. |
| HUMAN_REVIEW | Requires content, hospitality/ESP, accessibility, UX, or academic validation by an identified qualified reviewer. |

Severity:

| Severity | Release meaning |
|---|---|
| P0 | Must be resolved or explicitly contained before any real-user/production release. |
| P1 | High-impact correctness, security, privacy, accessibility, or core learning/lecturer capability gap. |
| P2 | Material usability, engagement, maintainability, mobile, or operational gap. |
| P3 | Lower-risk cleanup or future capability; schedule after characterization and higher risks. |

## 2. Authority, source precedence, and hashes

### 2.1 Authority files at audit time

1. Thesis/vision authority:
   C:\Users\dhion\Desktop\Documents\000 - Thesis Dhion Setio\Revisi 30 Juni 2026\Dhion Setio - Thesis Revise 7-18.docx
   SHA-256: BA64B2EB69673E84FC83E21D4617CD111691EE6340FC2F4331948FE6AF5EC132
2. Learning-content authority:
   C:\Users\dhion\Desktop\Documents\000 - Thesis Dhion Setio\Revisi 30 Juni 2026\Learning Materials - Fixed\Hospitrainity.docx
   SHA-256: 7F8A2C62DB6884498F7A1C2DE56E79E39609262944A685FE7EB97F702444CBB4
3. Validated replacement candidate, not yet active:
   C:\Users\dhion\Downloads\Hospitrainity New.docx
   SHA-256: 5DE098DCDCC6405093004F6253A1DEF2F5DC4D85BC1CCACF1416376F3A77569B
   Version marker: v0.8.0-draft
   State: deterministic candidate compilation passed; external authority replacement and active-package transformation remain open.
4. Accepted implementation/history:
   docs/HOSPITRAINITY_SOURCE_FIDELITY_AUDIT_AND_UPDATE_ROADMAP.md
   docs/HOSPITRAINITY_IMPLEMENTATION_CHECKPOINTS.md
   docs/decisions/ADM-0-administration-capability-matrix.md
   docs/decisions/ADM-4-canonical-exercise-template-contract.md
   docs/decisions/ADM-5-progress-administration-privacy-contract.md
4. Current code, migrations, tests, canonical package, lockfiles, and reproducible runtime behavior.

### 2.2 Precedence

1. Safety, applicable law, and approved privacy/accessibility policy.
2. Explicit current owner decisions.
3. Learning-materials DOCX for authored learning content.
4. Thesis DOCX for audience, goals, learning-experience intent, and research claims.
5. Accepted architecture/decision records.
6. Current implementation, which may contain defects and therefore cannot override an authority conflict by itself.
7. Competitor patterns are inspiration only. They are never authority for Hospitrainity content, scoring, privacy, visual preference, or thesis claims.

### 2.3 Confirmed source conflict

The thesis describes audiovisual/AI integration within scope in one passage, while the product-specification passage says an AI chatbot is considered rather than committed. Chapter IV contains only its heading in the audited DOCX, so the supplied thesis does not contain completed validation findings that resolve this conflict. AI, chatbot, automated coaching, and response-processing work is therefore a PREFERENCE + HUMAN_REVIEW + LEGAL_REVIEW gate in Batch B15, not an assumed requirement.

## 3. Crash/recovery record and audit limitations

### 3.1 Desktop crash analysis

Exact root cause is UNVERIFIED. No Windows fatal application, WER, out-of-memory, Laravel exception, database error, or PHP crash record was found for the event. The strongest available evidence is an in-app-browser renderer/control failure:

- browser-control logs reported Cannot find context with specified id;
- the renderer repeatedly reported ResizeObserver loop completed with undelivered notifications;
- a failed localhost navigation became a Chrome data URL error page, after which URL-policy protections rejected browser actions;
- two browser webviews rapidly changed active/disposed state immediately before the task log ended.

The interrupted isolated server used a disposable audit database. The temporary process was stopped, ports 8010 and 8011 were confirmed closed, and the main database was not changed by crash recovery. Future long audits should use short bounded commands, one browser session at a time, explicit cleanup, and concise captured output. Do not represent this as a proven Codex product defect without vendor diagnostic confirmation.

### 3.2 Scope limitations

- Rendered journeys were completed for Guest, Learner, Supervisor, Superadmin, and Admin (Admin used an isolated test database). This is not exhaustive exploratory testing of every route, every record state, every locale string, or every browser/assistive-technology combination.
- Responsive checks covered desktop and a narrow mobile viewport in Chromium. No physical iOS/Android device lab, Safari, Firefox, mobile screen reader, network-throttling, or offline-installability certification was performed.
- No external penetration test, source-code SAST suite, dynamic scanner, production configuration review, database-engine concurrency test, load test, chaos test, backup restore drill, or disaster-recovery exercise was supplied.
- Production host, TLS/proxy, mail, object storage, queue, scheduler, centralized logs, secrets, monitoring, backup tooling, and operators are unknown.
- The repository has a .git directory with zero entries and no HEAD. Git status/diff/commit evidence is unavailable.
- Findings are exhaustive for the inspected evidence and explicit request, not a mathematical claim that no undiscovered defect exists.

## 4. Reverified technical baseline

### 4.1 Runtime and inventory

| Item | Confirmed value |
|---|---|
| Framework | Laravel 12.64.0 |
| PHP | 8.2.32 |
| Composer | 2.10.2 |
| Front end | Vite 6.4.3, Tailwind CSS 4.3.3, Alpine CSP 3.15.12, Font Awesome 7.3.1 |
| Node contract | >=24 and <27; npm >=11 |
| Browser tests | Playwright 1.61.1 |
| Local environment | APP_ENV local, debug enabled, HTTP 127.0.0.1:8000, timezone UTC, locale en |
| Routes | 137 total; 133 named; 4 unnamed |
| Role-prefixed routes | Admin 40; Superadmin 63; Supervisor 2 |
| Main database, read-only post-audit | 3 users; 4 activity-progress rows; 4 attempts; 4 responses; 0 drafts/imports/administration audits/draft events |
| Package rows | 0.3.0-draft inactive/draft; 0.4.0-draft active/draft |
| Canonical content | 7 chapters, 85 sections, 25 activities, 124 prompts, 96 answer models, 96 feedback models, 6 rubrics |
| Active version | 0.4.0-draft |
| Active source tree | e864b4fcaa9d182b8379cf7d179279114baa7fef46624bcb261be41b8072dc9a |
| Laravel projection | 0967725acf53b46349ad8000a6b8e652e8855f37978912e796c3391cd9587f7f |
| Standalone projection | 55d1418fc75e359f85c9f7bb8ad1d93ea36943859b48f3c18a1db65039bf36c4 |
| Brand guard | 0 active violations across 749 files; one exact file provenance record and one exact database provenance record allowlisted |

### 4.2 Fresh verification results

| Gate | Result |
|---|---|
| Strict Laravel suite | PASS: 215 tests, 3,783 assertions, 137.29 seconds |
| ESLint | PASS: zero warnings under max-warnings=0 |
| Node suite | PASS: 47 tests |
| Chromium E2E | PASS: 7 journeys in disposable migrated/seeded E2E database |
| Vite production build | PASS: 11 modules; manifest and built assets emitted |
| Canonical database/package verifier | PASS: status verified; counts and all three projection hashes match |
| DOCX compiler determinism | PASS: 469 byte-identical files; deterministic tree 9d1511a24c84059a7c561603bb94b6c2c52a8076edfc2ba71def63ba2f2259ac |
| Negative compiler probes | PASS: changed hash, missing marker, duplicate ID, malformed table, missing hyperlink relation, unsupported block |
| npm advisory audit | PASS: 0 at info/low/moderate/high/critical; 246 dependencies reported |
| Composer advisory audit | PASS: advisories empty; abandoned empty |
| Production readiness | EXPECTED FAIL locally: environment, debug, HTTPS URL, secure cookie, enforced CSP, HSTS, and no-dev checks fail; key, HttpOnly, SameSite, headers, no hot file, manifest, and storage-link checks pass |
| Temporary audit servers | CLOSED: no listener on 8010 or 8011 |

Passing tests show the tested contracts are stable. They do not refute the product/correctness gaps below; several gaps are current intended behavior and are themselves covered by tests.

## 5. Thesis alignment traceability

### 5.1 Confirmed thesis intent

The supplied thesis describes:

- pre-service hospitality and tourism learners preparing for hotel customer-service roles;
- web-based English learning grounded in authentic hotel interactions;
- booking, check-in/check-out, complaints, problem-solving, hotel information, vocabulary and service phrases;
- self-paced, confidence-building practice that closes classroom/workplace gaps;
- interactive modules, scenario tasks, quizzes, role play, feedback, assessment, and progress;
- audio phrases, video demonstrations, audiovisual integration, and cross-device access;
- adaptive/immediate feedback, spaced practice, AI/chatbot discussion, peer/group work, and task-based learning as intended or considered experience elements;
- easy navigation and engagement for learners with lower digital literacy;
- expert validation, learner feedback, and iterative design as required research activities.

### 5.2 Alignment verdict

Core domain/content alignment: CONFIRMED and strong. The canonical package faithfully projects the authority learning material, preserves provenance, and implements relevant hotel-service content, objective activities, model feedback, self-check role-play, and versioned progress.

Experience/research alignment: PARTIAL. Current role play is a text rehearsal plus self-check/rubric rather than a live conversational simulation; audio recording is disabled; two audio-dependent authoring templates remain unavailable; no video-rich practice inventory was proven; no adaptive scheduler, AI coach/chatbot, peer/group workflow, classroom assignment layer, or spaced-repetition engine exists. The supplied thesis has no completed Chapter IV findings, so expert/user validation and outcome claims are UNVERIFIED.

Release claim: Do not say “the thesis vision is fully implemented.” Say “the core hospitality-English content and self-paced canonical delivery are aligned; several experiential, classroom-management, validation, and audiovisual/adaptive elements remain unimplemented or decision-gated.”

## 6. Current role capability matrix

Legend: YES, PARTIAL, NO, or N/A.

| Capability | Guest | Learner/user | Supervisor | Admin | Superadmin |
|---|---:|---:|---:|---:|---:|
| Public value proposition and registration/login CTA | YES | N/A | N/A | N/A | N/A |
| Canonical learning delivery and own progress | Login required | YES | NO | NO | NO |
| Same-institution learner metadata/detail | N/A | NO | YES, exact non-empty institution only | NO | YES globally |
| Aggregate/de-identified global progress | N/A | NO | NO | YES | Included with global identity view |
| Identity-level global progress | N/A | NO | NO | NO | YES |
| Raw learner response/audio access | N/A | Own open text/audio not retained | NO | NO | NO |
| Canonical draft create/edit/validate/preview | N/A | NO | NO | YES | YES |
| Authority DOCX/approved-asset import to draft | N/A | NO | NO | YES | YES |
| Template exercise authoring | N/A | NO | NO | 12 of 14 enabled | 12 of 14 enabled |
| Approve/publish/activate/rollback | N/A | NO | NO | NO | YES with step-up |
| Read legacy evidence | N/A | NO | NO | YES, read-only | YES, read-only |
| User directory and ordinary role assignment | N/A | NO | NO | NO | YES with step-up |
| Promote another verified user to superadmin | N/A | NO | NO | NO | YES with separate typed confirmation |
| Review administration/curriculum audit | N/A | NO | NO | NO | YES with step-up |
| Classes/cohorts/enrollments | NO | NO | NO | NO | NO |
| Assign a required module/task/path | NO | NO | NO | NO | NO |
| Due dates/prerequisites/late rules | NO | NO | NO | NO | NO |
| Gradebook/weights/pass rules/manual grading | NO | NO | NO | NO | NO |
| Announcements/posts | Public static only | NO | NO | NO | NO |
| Calendar/scheduling/reminders | NO | NO | NO | NO | NO |
| Timed/lockdown/proctored exam | NO | NO | NO | NO | NO |
| User export/delete/anonymize request | NO | NO | NO | NO | NO |
| PWA install/offline synchronization | NO | Standalone session-only file exists | NO | NO | NO |

## 7. Answers to the owner's 18 questions

| # | Question | Verdict | Evidence and required direction |
|---:|---|---|---|
| 1 | In line with the thesis vision? | PARTIAL | Strong audience/domain/content/canonical fidelity; missing or unverified conversational, audiovisual, adaptive, AI, peer/classroom, and completed research-validation elements. B15 and B17. |
| 2 | Mobile ready? | PARTIAL | Narrow Chromium pages reflow without root overflow, but no native app/PWA install/offline sync, no physical-device matrix, and admin mobile navigation is inefficient. B04 and optional B16. |
| 3 | Responsive? | MOSTLY, NOT COMPLETE | Learner and role shells reflow; tables use internal scrolling. Stacked sidebars consume 226–526 px before content on mobile, some touch targets/radios are too small, and long pages remain burdensome. B04. |
| 4 | Clear value and CTAs? | PUBLIC YES; AUTHENTICATED PARTIAL | Welcome page communicates hospitality-English value and has Create Account/Explore Features. Authenticated/admin surfaces expose package codes, draft/provenance jargon and weak “next best action” guidance. B05. |
| 5 | Robust security? | PARTIAL / NOT PRODUCTION-PROVEN | Strong verified authorization, CSRF, verification, step-up, session revocation, audit, upload checks, CSP/headers, throttles, and deployment checker. P0 demo credentials, open institution self-enrollment, minimum-eight default passwords, no MFA/blocklist, incomplete auth event monitoring, no production/restore/pen-test evidence prevent a robust claim. B01–B03 and B17. |
| 6 | Good accessibility? | PARTIAL | Native controls, keyboard alternatives, semantic activity forms, and automated regression exist. Missing skip links, auth main/h1 consistency and error associations, undersized language/role controls, shrinking radio controls, no manual screen-reader/zoom/real-device conformance review. B04. |
| 7 | User friendly? | LEARNER PARTIAL; LECTURER/ADMIN PARTIAL-LOW | Learner flow is understandable but long and technical. Authoring is capable but jargon-heavy and not usability-validated for lecturers. B05 and B08. |
| 8 | Engaging? | PARTIAL | Authentic material, varied controls, feedback, role-play self-check, and progress help. Little audiovisual interaction, personalization, social practice, spaced review, or optional motivation layer. B14–B15. |
| 9 | Not boring? | MAYBE / SUBJECTIVE | No evidence can prove this for the intended learners. Run moderated usability/engagement research; choices about visual energy, narrative, gamification, sound, rewards, and social comparison require owner/learner preference. B14 and B17. |
| 10 | Can a new user navigate without creator/tutorial? | MAYBE | Public entry is clear, but no onboarding, guided first task, global search, help center, glossary, progress-to-next-action, or validated first-use study. Technical metadata increases cognitive load. B05 and B17. |
| 11 | Can a lecturer easily manage lessons/materials/exercises? | PARTIAL | Admin and Superadmin can draft/import/preview 12 templates, but the workflow is technical, no course/class shell exists, two audio templates are blocked, and lecturer usability is unverified. B06 and B08. |
| 12 | Is scoring easily manageable by lecturers? | NO | Objective prompt scoring exists server-side, but no points/weight/category/pass policy, gradebook, rubric grading queue, manual override, release policy, accommodation, or CSV/LTI grade workflow. B09. |
| 13 | Can lecturers tell whether students are learning and doing tasks? | PARTIAL | Progress states, attempts, timestamps, and completion exist, but completion means participation, preview rows inflate attempts, and no assignment, correctness/mastery/score/gradebook or at-risk model exists. B09–B10. |
| 14 | Can lecturers force/direct a specific module/task? | NO | No classes/cohorts/assignments/prerequisites/due dates/required path. B06–B07. |
| 15 | Can lecturers/admins make multimedia announcements? | NO | No announcement/post domain, targeting, scheduling, moderation, media lifecycle, or notification channel. B11. |
| 16 | Can lecturers/admins set timer/schedule/stopwatch? | NO | No calendar, availability window, countdown, per-attempt timer, timezone preference, accommodations, or reminders. B12; exam timing in B13. |
| 17 | Can they host a safe exam that prevents other websites? | NO | A normal web application cannot reliably control other tabs, apps, devices, or OS resources. Build ordinary timed/randomized integrity controls first; optional SEB/proctoring requires a separate client/service and explicit legal/privacy/accessibility/accommodation decisions. B13. |
| 18 | Can users delete data, and can staff see deletion? | NO | No request/export/delete/anonymization workflow. User has no soft-delete/lifecycle fields. Audit foreign keys restrict deletion. Design rights intake, identity verification, retention/legal holds, pseudonymization/tombstones, audit, notification, and restore-safe deletion with counsel. B02. |

## 8. Official competitor comparison and selective adoption

Competitor features are current official-page observations as of 2026-07-19. Do not copy proprietary visual design, text, algorithms, or branding.

Evidence boundary: this is a public, official-source feature benchmark, not an authenticated hands-on usability, accessibility, security, performance, or role-journey audit of the competitor products. No claim below proves how a current paid tenant behaves, how easy a workflow is in practice, or whether a product conforms to a standard. Each capability claim must remain traceable to the exact official source in section 19 and be rechecked at implementation time. Any future direct-product observation must record product/plan/version or visible release state, date, account role, device/browser, tested journey, screenshots or other permitted evidence, and access limitations; it must not be merged silently with vendor documentation.

| Product | Officially documented strengths relevant here | Current Hospitrainity contrast | Selective adoption |
|---|---|---|---|
| Udemy Business | Assign content to people/teams, custom learning paths, individual/group engagement and skills analytics, assessments, role-play, personalized recommendations, LMS/API integration | No groups/assignments/path/skill analytics; role-play is self-check only | Cohort assignments, clear learning paths, actionable teacher dashboard, scenario practice; avoid catalog/marketplace scope. |
| iSpring Learn/Suite | Nontechnical course/quiz/role-play authoring, learning tracks assigned to groups/individuals, event calendar/notifications, user roles, progress/quiz/difficult-question reports, device preview | Technical canonical authoring; no class assignment/calendar/difficult-item analysis | Simple/advanced authoring modes, reusable tracks, group assignment, calendar/reminders, item analysis, learner preview. |
| LinkedIn Learning | Multimodal learning, labs/projects, personalized discovery, AI coaching and role-play with real-time feedback, LMS/LXP integrations | Mostly text/control practice, no personalization/AI coaching/integrations | Multimodal practice and later privacy-gated coaching; never infer that third-party AI is allowed. |
| Coursera | Mobile apps, detailed skill/proficiency/time-to-mastery analytics, paths/badging, course authoring, SSO/API/LTI, high-stakes proctoring/lockdown options | Responsive web only; no mastery/paths/interoperability/exam stack | Mastery-aware paths and optional LTI 1.3; treat proctoring as a high-risk optional tier, not baseline. |
| Open edX/edX | Studio content management, varied problem types/open responses, groups/experiments/discussions, learner data/grades, weighted grading, timed exams with extensions, third-party proctoring | Canonical authoring exists; no class/community/grade/timed/accommodation layer | Course/class shell, grade policy, open-response grading, timed attempts with accommodations, discussion only if moderated. |
| Khan Academy | Teacher classes; assigned and unassigned activity; assignment scores/completion/responses; mastery goals; individual skills, learning time, downloadable reports | Only progress-state metadata; no assigned work/mastery/score | Separate engagement, completion, correctness, and mastery; assignment and per-learner drill-down. |
| Thinkific | Cohesive course builder, curriculum reordering, student preview, mixed bulk media upload, publishing, progress/completion, drip schedule, appearance/settings separation | Powerful but technical draft entities/blocks; no course shell/drip | Guided course builder with student preview, structured tabs, mixed media, scheduling; retain immutable publication controls. |
| Canvas | Courses/sections, modules, requirements/prerequisites, individual/section assignments, due/availability dates, Gradebook/SpeedGrader, rubrics/outcomes, announcements/calendar, student preview/analytics | None of the classroom/gradebook/communications layer | Use the separation of content vs assignment, module requirements, teacher To Do/grading queues, and course-scoped analytics. |
| Blackboard | Assignments/tests, multiple submission states, grade posting/holding, exceptions, weighted/calculated grades, rubrics, anonymous/delegated grading, announcements/video/calendar, accommodations | Attempts collapse to started/completed; no grading queue/policy or announcements | Explicit attempt/submission/grading states, grade release, exceptions/accommodations, teacher queue, media announcements. |
| Duolingo | Bite-size personalized practice, retry/review, XP/streaks/leaderboards with opt-out, reminders, classroom assignments and teacher progress | Long lesson pages; no review scheduler, assignments, or motivation settings | Short resume/review loop, spaced practice, optional non-punitive motivation and opt-out; do not make XP the academic grade. |

### 8.1 Cross-product pattern to adopt

The common, defensible target is:

Course/class ownership -> enrollment -> assignable content/path -> availability/due rules -> explicit attempt/submission states -> scoring/feedback/grade release -> teacher action queue -> learner/mastery analytics -> communications/calendar -> accessible mobile delivery.

Hospitrainity should keep its differentiator: source-faithful hospitality-English scenarios and auditable canonical versions. It should not become a general marketplace or clone all enterprise LMS features.

## 9. Confirmed strengths to preserve

PRESERVE-001: deterministic authority-DOCX compiler with exact-hash gate, coverage rules, stable IDs, and six negative probes.  
PRESERVE-002: immutable canonical version publication and retained provenance/rollback evidence.  
PRESERVE-003: one canonical Laravel/standalone projection and active StayReady brand guard.  
PRESERVE-004: role enum, fail-closed middleware/policies, cross-role/cross-institution tests, dedicated superadmin promotion, last-superadmin protection, target-session revocation, recent-password step-up, and bounded audits.  
PRESERVE-005: server-authoritative objective scoring, idempotency, transactions, stable choice/token identifiers, minimized open-response storage, and explicit model reveal.  
PRESERVE-006: native form controls, non-drag ordering alternative, error focus/status behavior, and learner DOM safety tests.  
PRESERVE-007: private/quarantined, digest-addressed upload pipeline with MIME/signature/OOXML/ZIP/image bounds and protected asset delivery.  
PRESERVE-008: local CSP Alpine, security headers, signed private file delivery, CSRF, email verification, generic reset behavior, named throttles, canonical email, secure production example, and fail-closed deployment checker.  
PRESERVE-009: Admin least privilege, Superadmin publication/role/audit authority, Supervisor institution scope, and no administrator raw-response/CSV access by implication.  
PRESERVE-010: explicit language that participation is not proficiency/CEFR evidence and standalone data is session-only/non-exam.

## 10. Findings register

### 10.1 P0 release blockers

#### NG-P0-001 — Production-capable hardcoded demo credentials

State: CONFIRMED.  
Evidence: database/seeders/UserSeeder.php creates pre-verified superadmin, supervisor, and learner accounts and hashes literal password; DatabaseSeeder always calls it; no environment guard prevents production execution.  
Impact: an operator running seed in production can create predictable privileged accounts. Documentation saying development-only does not enforce safety.  
Required fix: make demo seeding fail closed in production; separate test/demo fixtures from production bootstrap; add an idempotent one-time bootstrap command that accepts no literal default, requires an out-of-band randomly generated secret or verified invite/reset, logs bounded evidence, and refuses when a superadmin already exists unless a documented recovery process applies. Add tests and deployment checks that scan/deny known demo users/password paths. Never print a reusable password to logs.  
Acceptance: production-environment seeding fails before account writes; disposable E2E seeding remains explicit; no known credential literal in runtime seed paths; initial-superadmin runbook and recovery drill pass.

#### NG-P0-002 — Public institution enumeration and self-enrollment

State: CONFIRMED + PREFERENCE.  
Evidence: RegisterController selects distinct non-null users.instansi values for the public registration form; submitted instansi need only exist in users; the page exposes institution names and lets any registrant select one.  
Impact: institution enumeration, unauthorized affiliation, incorrect Supervisor visibility scope, and weak tenant/enrollment integrity. Exact string equality is not an enrollment proof.  
Required fix: after B01 questionnaire, introduce normalized Institution and Invitation/EnrollmentRequest domains. Supported policy options may include admin-issued invitation, expiring single-use join code, verified-domain approval, or staff approval. Do not assume which. Public errors must not enumerate institution/invitation validity. Migrate existing strings with a reviewed mapping and unknown/legacy state; never auto-merge by fuzzy name.  
Acceptance: attacker cannot enumerate institution roster/names through registration responses; direct institution choice alone cannot grant membership; invitations/codes are hashed, scoped, expiring, single-use, rate-limited, audited, and revocable; cross-institution authorization tests remain fail closed.

#### NG-P0-003 — Active delivery uses a draft-lifecycle package

State: CONFIRMED + HUMAN_REVIEW.  
Evidence: active content version is 0.4.0-draft; package lifecycle remains draft while learner-delivered entities are published; current UI exposes both. CF-7 human publication gates remain open.  
Impact: ambiguous release truth, accidental representation of unapproved content, confusing learner/admin metadata, unsafe automation based on conflicting states.  
Required fix: do not rename in place. Close content/ESP/hospitality/CEFR/accessibility/rights/link/retention/owner gates; create an approved new immutable release with one coherent lifecycle; activate only through the reviewed publication workflow. The system must prevent active learner delivery for lifecycle draft in production unless an explicit preview/test tenant mode is selected and visibly labeled.  
Acceptance: no production active package has draft lifecycle; entity/package/release state machine is coherent; release evidence names every approval and source hash; rollback retains the prior package.

#### NG-P0-004 — Missing privacy notice, data-subject workflow, and deletion governance

State: CONFIRMED + LEGAL_REVIEW.  
Evidence: no privacy/terms/help links on public footer; registration terms checkbox only confirms supplied information; no data export, correction, restriction, objection, deletion, destruction, retention, or request status domain; audit foreign keys restrict deleting referenced users.  
Impact: users cannot exercise requested data control; destructive implementation could erase security/academic evidence or violate retention obligations; current public collection lacks clear notice.  
Required fix: B02 must be counsel/owner-approved. Add versioned privacy notice/terms/consent-or-acknowledgment evidence as applicable; rights request with identity verification and statuses; machine/human-readable export; correction path; processing restriction; deletion/anonymization/tombstone orchestration; retention/legal hold; dependency inventory; notification evidence; audit actor pseudonymization without destroying audit integrity; backup-expiry treatment; staff view of request status, never deleted content.  
Acceptance: policy-to-field retention matrix exists; end-to-end request tests cover denial/appeal/hold/completion; deleted identity is no longer recoverable from live product paths; retained audit is purpose-limited and pseudonymized; staff sees action/status not erased data; legal reviewer signs the rule.

#### NG-P0-005 — No usable version-control repository

State: CONFIRMED.  
Evidence: .git contains zero entries and no HEAD; git cannot provide status/diff/history.  
Impact: broad changes lack trustworthy change review, rollback, authorship, branching, bisect, and release traceability. Hash/checkpoint documents are valuable but not a replacement for source history.  
Required fix: before B01 implementation, owner chooses whether to initialize a new repository or reconnect the authoritative remote/history. Inventory secrets/generated/private artifacts before first commit; create .gitignore; capture a signed or otherwise integrity-protected baseline tag/commit; configure branch protection/CI if hosted. Do not publish private DOCX/database/storage files.  
Acceptance: clean reproducible checkout from an approved source; HEAD exists; baseline commit hash recorded; secret scan and generated/private exclusion pass; each later checkpoint has a reviewable commit/diff.

### 10.2 P1 high-impact findings

#### NG-P1-006 — Model previews inflate “attempt” analytics

State: CONFIRMED.  
Evidence: CurriculumAttemptService creates a started CurriculumAttempt and started event before branching on intent show_model. CanonicalCurriculumRepository and ProgressAdministrationService count CurriculumAttempt rows without excluding preview intent/state. UI says model shown without recording an attempted response, but an attempt row is recorded and counted.  
Impact: learner and staff attempt counts are semantically false; engagement/struggle inference is biased.  
Fix: introduce/derive explicit interaction taxonomy: preview, started, submitted, graded, completed, skipped. Either store previews in a separate interaction event or exclude them from attempt/submission metrics. Backfill/report old show_model rows as preview, never delete evidence. Rename every metric precisely.  
Acceptance: model reveal does not increment submitted-attempt count; preview count is independently available if approved; historical rows map deterministically; tests cover all intents and analytics.

#### NG-P1-007 — Completion is participation, not correctness or mastery

State: CONFIRMED.  
Evidence: every valid check submission sets attempt/progress completed with completion_reason participation_rule_satisfied even when objective responses are incorrect. Correctness is stored per closed response but not used for completion or lecturer views.  
Impact: completion percentage can be mistaken for learning/mastery; lecturers cannot answer whether learners learned.  
Fix: preserve participation completion but label it as such. Add separate score, submission, pass, mastery, and competency/outcome dimensions only after B09 questionnaire. Never retroactively call participation “passed.”  
Acceptance: UI/API/report names clearly distinguish viewed/participated/submitted/completed/passed/mastered; calculations and denominators are versioned; incorrect completed participation cannot be displayed as mastered.

#### NG-P1-008 — Answer/model material can appear before the exercise

State: CONFIRMED + HUMAN_REVIEW.  
Evidence: source-faithful lesson sections sometimes contain model/answer material adjacent to and before the linked activity; the activity itself reveals model only after explicit action. Some source quiz stems also repeat option text already rendered as controls.  
Impact: assessment validity and clarity can be reduced; blindly hiding source text would violate fidelity.  
Fix: B09 content/assessment reviewer classifies each activity as practice, formative, or summative and classifies source blocks as instruction, example, hint, model, or answer key. For assessed use, render protected/reveal-after blocks through canonical metadata while preserving exact source and provenance. Do not rewrite the DOCX silently. Normalize duplicated option display in the compiler/view model, retaining source evidence.  
Acceptance: every activity has review-approved reveal policy; practice examples remain available; protected answers are not delivered before permitted state; source coverage remains 100%; malicious/ambiguous cases tested.

#### NG-P1-009 — No course/class/cohort/enrollment domain

State: CONFIRMED.  
Evidence: role-capability, schema, model, route, and rendered-journey audit found no normalized Institution, Course, CourseOffering/Class, Enrollment/Membership, TeachingAssignment, or Cohort/Group domain; current Supervisor scope depends on exact non-empty `users.instansi` equality.  
Impact: Supervisor institution strings substitute for academic grouping; lecturers cannot own classes, enroll students, reuse a course run, or isolate analytics.  
Fix: B06 normalized Institution, Course, CourseOffering/Class, Membership/Enrollment, TeachingAssignment, and optional Group/Cohort domains with explicit role/capability policies. Keep global system role separate from course role.  
Acceptance: same user can have scoped roles in different classes without global privilege; all roster operations are invitation/approval controlled and audited; direct-ID/cross-class tests pass.

#### NG-P1-010 — No assignments, directed paths, prerequisites, or due rules

State: CONFIRMED.  
Evidence: repository schema/model/route/view and rendered-role audit found no assignment, assignment target/item, required path, prerequisite rule, due/availability window, exception, or late/excused state; dashboards expose self-paced canonical progress only.  
Impact: lecturers cannot require a module/task, set sequence, distinguish assigned vs self-directed work, or track overdue/not-started work.  
Fix: B07 Assignment, AssignmentTarget, AssignmentItem, availability/due/close windows, prerequisite/requirement rules, exceptions, and explicit status projection. Link immutable content version.  
Acceptance: lecturers can target class/group/learner; learner dashboard shows next required work; status handles assigned/not-started/in-progress/submitted/late/completed/excused; timezone/accommodation rules tested.

#### NG-P1-011 — No lecturer-grade scoring/gradebook workflow

State: CONFIRMED.  
Evidence: the canonical assessment registry records server-side correctness for supported closed prompts, but repository and rendered-role audit found no lecturer-configurable points/weights/categories/pass policy, gradebook, rubric/manual-grading queue, override/release workflow, or academic grade export.  
Impact: server correctness exists but cannot be configured or released as academic scoring; open work lacks manual grading/rubric queue.  
Fix: B09 versioned GradePolicy, GradeCategory, GradeItem, Rubric/criterion, Submission, Evaluation, Grade, Override, Release, and audit. Support points/percent/category weights/pass rules only per questionnaire. Preserve raw score and calculated display separately.  
Acceptance: deterministic grade calculation, rounding specification, weight validation, attempt policy, manual/open-response queue, feedback/grade release, override reason/audit, learner view, and export/interoperability safety.

#### NG-P1-012 — Progress administration cannot fully evidence learning

State: CONFIRMED.  
Evidence: Supervisor sees same-institution metadata; Admin only aggregate/de-identified totals; Superadmin sees global identity metadata. All exclude prompt correctness/private responses by design. Detail can render 85 sections and reports attempt count/completion state, not score/mastery/assignment status.  
Impact: privacy-safe baseline is good, but question 13 remains only partially answered.  
Fix: B10 creates purpose-bound education analytics derived from approved scores/submissions/mastery, not raw private text. Course-scoped lecturer access should supersede brittle institution equality. Admin remains aggregate unless owner changes policy. Add at-risk rules only through transparent configurable thresholds.  
Acceptance: lecturer can distinguish no access, opened, practicing, submitted, late, pass/mastery, and needs review; learner sees same core facts; every metric defines numerator/denominator/time window/source.

#### NG-P1-013 — Authentication assurance below current recommended target

State: CONFIRMED.  
Evidence: PasswordRule::defaults is used without a project default override, so minimum is Laravel's default 8; no compromised/common/context blocklist; no MFA/passkeys; login limiter is only email+IP at 5/minute.  
Impact: distributed-IP attack can target one account; privileged accounts rely on one reusable factor; predictable/common passwords are permitted subject to length.  
Fix: B03 applies NIST SP 800-63B-4-informed policy: 15-character minimum for password-only accounts, blocklist of common/expected/compromised values, no arbitrary composition rule, password-manager/paste support, layered account/IP/global/risk throttling, WebAuthn/passkey or another approved MFA/recovery design for privileged roles. Avoid permanent account lockout DoS.  
Acceptance: password/blocklist tests; Unicode/length behavior documented; distributed attempts trigger an account-aware defense; MFA enrollment/recovery/revocation/session step-up audited; accessible authentication remains WCAG-compatible.

#### NG-P1-014 — Incomplete security-event monitoring and production assurance

State: CONFIRMED/UNVERIFIED.  
Evidence: administration actions are audited, but no complete authentication-failure, MFA, sensitive-read, anomaly, alert, or incident pipeline was proven. Production readiness intentionally fails locally; topology, backups, restore, queue/scheduler, central logs, least-privilege DB grants, monitoring, and pen test are unknown.  
Impact: attacks or sensitive misuse may not be detected/escalated with sufficient context, and local controls/tests cannot substantiate a robust production-security or recoverability claim.  
Fix: B03 ASVS 5.0.0 traceability; structured security events without secrets; alert/use cases; tamper-aware centralized logging; secrets management; least privilege; backup/restore; incident response; dependency/SAST/DAST review; production penetration test. B17 closes operational gates.  
Acceptance: no production claim until environment checker, ASVS target, restore drill, log/alert exercises, and independent assessment evidence pass.

#### NG-P1-015 — Upload pipeline has no proven malware/CDR service

State: CONFIRMED/UNVERIFIED.  
Evidence: strong local content/signature/decompression/macro validation exists; antivirus/content-disarm infrastructure is not declared.  
Impact: well-formed malicious files or unknown payloads may pass format validation.  
Fix: questionnaire chooses self-hosted AV, managed scanner/CDR, or documented risk-based restriction. Keep quarantine and fail closed on scanner unavailable where policy requires. Scan version/result/digest only in bounded audit; never expose quarantine path.  
Acceptance: EICAR/test-double integration in isolated test only; timeout/unavailable/positive/negative/re-scan flows; no asset promotion before required result; retention/disposal documented.

#### NG-P1-016 — Accessibility defects in navigation/auth/action controls

State: CONFIRMED.  
Evidence: no skip link found; login/register use h2 with no main landmark; most errors are not programmatically associated through aria-invalid/describedby; visible labels are screen-reader-only/placeholder-dependent; language controls measured below 24×24; mobile role-action buttons measured about 36 px high; learner radios shrink below intended size in flex rows.  
Impact: keyboard, screen-reader, zoom, touch, cognitive, and motor-impaired users can encounter avoidable navigation, identification, error-recovery, and target-activation barriers; automated green checks alone do not establish conformance.  
Fix: B04 semantic page templates with main/h1, skip link, persistent visible labels, field-level error association, summary focus, minimum WCAG 2.2 target-size compliance (prefer 44 CSS px where owner approves), flex-none controls, focus not obscured, and consistent help.  
Acceptance: automated + manual keyboard, 320 CSS px reflow, 200/400% zoom, focus, target, screen-reader label/error, contrast, reduced motion, and mobile checks.

#### NG-P1-017 — Audio exercise accessibility/product contract unresolved

State: CONFIRMED + PREFERENCE + HUMAN_REVIEW.  
Evidence: spelling_quiz and listening_task are implemented behind the registry but unavailable because an equivalent alternative may disclose the answer; Permissions-Policy currently disables microphone.  
Impact: two intended exercise templates remain unavailable, while enabling audio/recording without an approved equivalent path, disclosure rule, consent, storage, and assessment-validity contract could exclude learners, leak answers, or collect sensitive media improperly.  
Fix: B15 establishes accommodation paths, transcript timing, alternative item equivalence, media rights, captions/transcripts, playback speed, no-audio alternative, microphone consent/storage, and scoring validity. Security header changes follow the approved scope.  
Acceptance: accessibility reviewer and content/assessment reviewer approve both standard and accommodation paths; no answer leakage; no microphone access without user action/permission/purpose; templates remain disabled until then.

### 10.3 P2 material usability/product findings

#### NG-P2-018 — Technical lifecycle metadata dominates ordinary learning/authoring surfaces

State: CONFIRMED.  
Evidence: rendered learner and lecturer journeys expose internal package codes, `0.4.0-draft`, canonical/provenance/hash, and lifecycle terminology as primary page content.  
Impact: ordinary users must interpret release-engineering vocabulary before understanding the learning or authoring task, increasing cognitive load and weakening value/next-action clarity.  
Required fix: B05 defines approved plain-language primary labels and moves diagnostics to a clearly labeled, permission-aware Advanced/Evidence view; B08 keeps exact provenance available to authorized reviewers without making it the default task language.  
Acceptance: learner and lecturer first-view surfaces lead with task, status, and next action; no required workflow depends on understanding hashes or lifecycle codes; authorized evidence remains discoverable and exact.

#### NG-P2-019 — Shared administration shell does not consistently identify role and scope

State: CONFIRMED.  
Evidence: rendered Admin and Superadmin journeys share “Hospitrainity Admin” branding without consistently exposing the active role or institution/course/class context.  
Impact: users can misread authority and data scope, particularly after role changes or when future class-scoped capabilities are introduced.  
Required fix: B05 adds unambiguous user-facing role and active-scope context; B06 supplies normalized institution/course/class context. Do not imply that a visible role badge grants authority; policies remain authoritative.  
Acceptance: every administrative page identifies the signed-in role and applicable scope consistently; switching scope is explicit; unknown/stale scope fails closed; Admin and Superadmin journeys cannot be mistaken in role-matrix browser tests.

#### NG-P2-020 — Mobile administrative navigation delays primary work

State: CONFIRMED + PREFERENCE.  
Evidence: browser measurements at narrow widths found Admin/Supervisor sidebars stacking approximately 226–526 CSS px before the main content; wide tables depend on internal horizontal scrolling.  
Impact: primary actions can start below the initial viewport, navigation becomes laborious, and table interaction is difficult on touch devices even without root overflow.  
Required fix: B04 implements the owner-approved responsive navigation pattern and task-prioritized card/list alternatives where tables are not the only semantically appropriate presentation. Preserve full keyboard access and visible context.  
Acceptance: approved 320/390 CSS px and physical-device journeys reach the page purpose and primary action without traversing the full navigation first; no root overflow; table alternatives retain all required actions/data; keyboard, focus, zoom, and touch-target checks pass.

#### NG-P2-021 — Learner and progress-detail pages are excessively long and dense

State: CONFIRMED + PREFERENCE.  
Evidence: rendered learner/progress journeys are long; Superadmin detail can traverse all 85 canonical sections in one view.  
Impact: locating current status, incomplete work, or a specific section is slow; assistive-technology and keyboard users incur excessive navigation; large views risk query/rendering cost.  
Required fix: B04/B05 add an approved summary, filters, anchored navigation, progressive disclosure, pagination, or semantically safe virtualization plus resume/next actions. B10 must use bounded policy-scoped queries and accurate filtered totals.  
Acceptance: users can reach current/flagged work directly; result counts and filters are truthful; focus/history work across disclosure and pagination; no N+1 or unbounded default result set; print/find-in-page/screen-reader behavior is documented for any virtualization.

#### NG-P2-022 — No validated first-use onboarding, search, or support path

State: CONFIRMED + PREFERENCE.  
Evidence: repository and rendered-journey audit found no first-use onboarding, approved diagnostic placement, guided first activity, help center, glossary, global search, contact/help route, or contextual support workflow.  
Impact: a new learner or lecturer may depend on the creator to understand the next step, and terminology has no maintained explanation path.  
Required fix: B05 implements only the owner-approved onboarding, help, search, glossary, and support scope; it must not invent claims, support channels, or diagnostic meaning.  
Acceptance: each included role completes the approved first-use tasks with an explicit next action, skip/restart/recovery behavior, accessible help, and policy-scoped search; moderated evidence records assistance required and unresolved confusion.

#### NG-P2-023 — Public trust and support links are missing

State: CONFIRMED + LEGAL_REVIEW.  
Evidence: public footer lacks privacy, terms, accessibility, help/contact, and about links; the registration “terms” control does not link to the applicable terms or privacy notice.  
Impact: users cannot inspect governing information before registration, and the existing acknowledgment can be mistaken for informed notice.  
Required fix: B02 supplies owner/counsel-approved versioned privacy/terms/data-rights destinations and acknowledgment evidence; B05 supplies approved about/help/contact/accessibility navigation. Do not publish placeholder legal claims as final policy.  
Acceptance: public and registration journeys expose current, accessible, versioned destinations before submission; acknowledgment records the correct version where approved; broken-link, locale, keyboard, and no-JavaScript checks pass.

#### NG-P2-024 — Authenticated dashboards do not consistently prioritize the next useful action

State: CONFIRMED + PREFERENCE.  
Evidence: rendered authenticated dashboards allow technical evidence and package state to compete with continue, assigned, review, authoring, or administrative tasks; no approved cross-role next-action priority exists.  
Impact: learners and staff must infer what to do next, weakening self-service navigation and task completion.  
Required fix: B05 obtains the owner’s role-specific priority rules and builds a server-derived next-action resolver from authorized facts. Never show a CTA for an unavailable, unauthorized, draft, or deferred capability.  
Acceptance: every role has one approved primary action or truthful empty state; priority is deterministic and tested for overdue/in-progress/review/authoring states; direct links remain policy checked; technical evidence stays secondary but accessible.

#### NG-P2-025 — No multimedia announcement and communication domain

State: CONFIRMED + PREFERENCE.  
Evidence: repository/route/model/view audit found no announcement/post domain, audience targeting, read/acknowledgment state, moderation, scheduling, rich-media lifecycle, or notification preference system.  
Impact: lecturers/admins cannot issue scoped academic or non-academic posts, and an improvised implementation could leak audiences, resend messages, or publish unsafe media.  
Required fix: B11 implements only approved author roles, audiences, media, state transitions, moderation, scheduling, delivery channels, retention, and notification preferences using B02/B03/B06 and the shared B06 time foundation.  
Acceptance: audience/direct-ID tests fail closed; scheduled/unpublished/expired posts do not leak; rich media passes the approved pipeline; delivery is idempotent; sent/read/acknowledged semantics are distinct; retention and moderation procedures are approved.

#### NG-P2-026 — No shared user-facing calendar, timezone, reminder, countdown, or stopwatch capability

State: CONFIRMED + PREFERENCE.  
Evidence: application configuration uses UTC, while the audited domain/UI has no approved institution/user IANA timezone hierarchy, calendar, availability schedule, reminder jobs, countdown, or stopwatch.  
Impact: due/scheduled meaning can be ambiguous across locations and DST; individual features may otherwise invent incompatible time logic.  
Required fix: B06 first establishes the single shared Clock/TimeContext/timezone foundation. B07 and B11 consume it for assignment/announcement dates. B12 adds approved calendar, recurrence, reminders, countdowns, and non-graded timers; B13 owns graded attempt timing.  
Acceptance: no parallel time resolver exists; all instants and author-local intent follow the approved model; DST gap/fold, clock skew, retry, reschedule, accommodation, locale, and cross-scope disclosure tests pass for included capabilities.

#### NG-P2-027 — No timed-assessment or secure-exam integration layer

State: CONFIRMED + PREFERENCE + LEGAL_REVIEW.  
Evidence: repository and rendered-journey audit found no timed assessment, question bank/randomization, attempt window, autosave/recovery, accommodation, secure-browser handshake, or proctor-review workflow.  
Impact: high-stakes exam claims would be false; adding surveillance or lockdown without explicit scope creates accessibility, privacy, legal, support, and reliability risks.  
Required fix: B13 separates ordinary web integrity controls, optional verified secure-browser integration, and optional proctoring tiers. A normal page must never claim to prevent other websites or devices.  
Acceptance: approved tier is visibly and technically accurate; server-authoritative timing/recovery/accommodation tests pass; question/answer exposure is bounded; secure-client identity/handshake is verified if selected; privacy/legal/accessibility/support gates close before proctoring.

#### NG-P2-028 — No spaced-practice, personalization, or optional motivation system

State: CONFIRMED + PREFERENCE + HUMAN_REVIEW.  
Evidence: repository and rendered learner audit found no spaced review queue, adaptive scheduling, personalization rules, streak, XP, achievement, leaderboard, or opt-in social-motivation domain.  
Impact: practice remains mostly linear/long-form and the thesis’s adaptive/spaced-practice intent is not implemented; poorly chosen gamification could create pressure or distort academic meaning.  
Required fix: B14 prioritizes transparent spaced review based on approved learning evidence and makes all motivation/social mechanics separately optional, non-punitive, privacy scoped, and distinct from academic grades.  
Acceptance: review scheduling is deterministic, explainable, timezone safe, and reconstructable; opt-out/reset/accessibility behavior works; no streak/XP is presented as mastery; content/research reviewers approve any learning-effect claim.

#### NG-P2-029 — No live conversational, AI-coaching, peer, group, or discussion workflow

State: CONFIRMED + PREFERENCE + HUMAN_REVIEW + LEGAL_REVIEW.  
Evidence: current role play is text rehearsal/self-check; repository audit found no live conversational simulation, AI coach/chatbot, peer review, group task, or discussion domain.  
Impact: several thesis-experience intentions remain unimplemented; premature AI/peer features could invent learning content, expose personal data, produce unsafe feedback, or create an unstaffed moderation burden.  
Required fix: B15 implements only selected tiers after B02 privacy, B03 security, B06 scope, B08/B09 content and assessment semantics, and explicit content/moderation/provider decisions. Deterministic non-AI simulation must remain a viable option.  
Acceptance: source/editorial/AI content is attributable; provider/data/retention/consent/moderation contracts are approved; unsafe output and outage fallbacks are tested; peer access is scope-limited; no automated feedback is represented as qualified human assessment without evidence.

#### NG-P2-030 — No installable or synchronizing PWA

State: CONFIRMED + PREFERENCE.  
Evidence: repository search found no PWA manifest, service worker, install prompt, or offline synchronization. The standalone file is deliberately session-only and does not synchronize.  
Impact: the product cannot truthfully claim installable/offline synchronized learning; unsafe caching could expose private content or submit stale/duplicate work.  
Required fix: optional B16 implements only the approved offline use cases, cache boundary, update policy, conflict behavior, storage protection, and accessibility UX. Keep standalone explicitly session-only and never present it as a secure exam client.  
Acceptance: offline/online/update/quota/eviction/logout/shared-device/conflict tests pass; sensitive responses are not cached outside policy; synchronization is idempotent; install/offline CTAs appear only when the capability is available and supported.

#### NG-P2-031 — Localization scope is technically present but product/pedagogical boundaries are unresolved

State: CONFIRMED + PREFERENCE + HUMAN_REVIEW.  
Evidence: localization structure/regression exists, while canonical English learning content and some administration enum/technical strings remain English or raw.  
Impact: untranslated interface text can block Indonesian users, while translating target-language learning material indiscriminately can undermine the English-learning intent or source fidelity.  
Required fix: B04/B05/B08 define interface, administrative, help, source-content, translation, glossary, and evidence-view boundaries with owner and language-learning/content review; enum values map to localized human labels without changing stored identifiers.  
Acceptance: approved interface surfaces have complete locale keys and fallback behavior; no raw enum leaks in ordinary UI; target-language/source text remains faithful and distinguishable from approved translation/help; bilingual screen-reader and layout checks pass.

#### NG-P2-032 — Some source options are duplicated by rendered answer controls

State: CONFIRMED + HUMAN_REVIEW.  
Evidence: audited source-derived quiz stems sometimes include option text that is also rendered as radio choices.  
Impact: duplicate presentation increases cognitive and screen-reader verbosity and can confuse which representation is interactive; silently deleting source text would break fidelity/provenance.  
Required fix: B08/B09 classify source runs and canonical prompts/options so the view model presents one approved interactive option set while retaining the exact source and derivation evidence. Do not rewrite the authority DOCX.  
Acceptance: affected templates render each option once in the interaction; accessible names and feedback remain correct; compiler/source coverage and hashes remain deterministic; reviewer approves ambiguous source cases.

#### NG-P2-033 — Choice controls shrink and become difficult to use on touch screens

State: CONFIRMED.  
Evidence: browser checks found small/shrinking radio controls and long dense choice rows even where document-level reflow passes.  
Impact: controls can miss target-size expectations, reduce readability, and increase input errors for touch, zoom, and motor-impaired users.  
Required fix: B04 prevents flex shrink, provides an approved full-label target where appropriate, preserves native semantics, and adjusts wrapping/spacing without changing assessment meaning.  
Acceptance: changed choice controls pass the approved target-size rule and 320/390 CSS px, zoom, keyboard, screen-reader, high-contrast, and physical-touch checks; label activation selects only the intended choice.

#### NG-P2-034 — Engagement, first-use, and lecturer-usability claims lack real-user evidence

State: UNVERIFIED + HUMAN_REVIEW.  
Evidence: no approved study artifacts or observed intended-user results were available to establish “not boring,” unaided first-use navigation, or lecturer authoring/scoring usability; green automated/browser tests cannot establish those claims.  
Impact: product and thesis conclusions could overstate usability, engagement, manageability, or learning value.  
Required fix: B05 defines the owner-approved first-use study design without assuming sample size; B17 runs the approved learner/lecturer studies and pilot under the supervisor/ethics process and records raw evidence, analysis, adverse findings, and limitations.  
Acceptance: participant mix, consent, tasks, facilitation, success/failure/stopping criteria, analysis, and limitations are approved and recorded; results are reproducible from retained permitted evidence; Chapter IV reports observed findings only and open failures remain visible.

### 10.4 P3 engineering/maintainability findings

#### NG-P3-035 — Blade views perform curriculum database queries

State: CONFIRMED.  
Evidence: five Superadmin index views for exercises, lessons, materials, vocabularies, and modules query `CurriculumPackage` directly near line 3 in the audited snapshot.  
Impact: authorization/data-selection logic is hidden in presentation code, query behavior is harder to test, and future reuse can introduce N+1 or inconsistent scope.  
Required fix: B08/B17 move policy-scoped reads to controllers, query objects, view models, or a narrowly justified composer; Blade receives a complete render model and performs no database lookup. Characterize output and query counts before refactoring.  
Acceptance: repository view search finds no application database query in the affected Blade files; role/direct-ID behavior and rendered output remain equivalent; bounded query-count tests pass.

#### NG-P3-036 — Large files combine multiple responsibilities

State: CONFIRMED.  
Evidence: audited snapshot sizes include `CanonicalCurriculumImporter` 743 lines, `CanonicalPackageReader` 678, `CurriculumDraftExerciseWorkspace` 636, `CurriculumDraftWorkspace` 566, `ProgressAdministrationService` 562, `CanonicalCurriculumRepository` 419, `CurriculumAttemptService` 415, `CurriculumImportCompiler` 334, `CurriculumDraftPackageBuilder` 321, and `routes/web.php` 280. Line count is a review signal, not proof of a defect by itself; inspection found multiple parsing/validation/persistence/projection/routing responsibilities.  
Impact: changes have broad regression surfaces, responsibilities and transaction boundaries are difficult to reason about, and authorization/scoring defects can hide in orchestration code.  
Required fix: B08 characterizes authoring/import behavior; B17 separates bounded responsibilities only where tests prove a stable seam. Preserve public contracts, transaction ownership, determinism, and error semantics; do not perform a framework rewrite or split solely to reduce line count.  
Acceptance: characterization tests pass before and after; each extracted component has one documented responsibility and dependency direction; canonical hashes/output, policy behavior, query counts, and failure/rollback semantics remain stable.

#### NG-P3-037 — Canonical and retained legacy curriculum concepts coexist

State: CONFIRMED.  
Evidence: canonical and legacy curriculum models/controllers/views remain in the repository; prior gates make legacy writes read-only, but duplicate domain names and navigation paths remain.  
Impact: maintainers can select the wrong model/path, authorization fixes can be applied to only one representation, and users may misread legacy evidence as active content.  
Required fix: B08/B17 keep legacy evidence explicitly read-only, isolate its namespace/routes/navigation/labels, document ownership and mapping, and define evidence-based retirement/export criteria before any deletion.  
Acceptance: active writes cannot reach legacy storage; navigation labels legacy evidence accurately; route/policy tests distinguish both paths; retirement cannot proceed until retention/provenance/legal requirements and export/rollback are approved.

#### NG-P3-038 — Safe rich-text rendering relies on an implicit construction convention

State: CONFIRMED.  
Evidence: rich text is emitted unescaped only after source runs are escaped and a fixed allowlisted tag transformation is applied. The audited path is safe by construction, but the type passed to raw Blade output does not enforce that invariant.  
Impact: a future caller can accidentally pass untrusted HTML through the raw-render path, creating an XSS boundary that code review may miss.  
Required fix: B08/B17 introduce an immutable `SafeHtml`-style value object produced only by the reviewed renderer/sanitization boundary; ordinary strings remain escaped. Add malicious nested/encoded input regression and forbid scattered raw output for user/source data.  
Acceptance: raw render sites accept only the reviewed safe type or fixed application markup; malicious corpus tests remain inert; existing source-faithful formatting and deterministic output are unchanged; static/architecture checks detect unauthorized raw paths where practical.

#### NG-P3-039 — Image probing suppresses validation warnings

State: CONFIRMED.  
Evidence: audited upload/image validation uses error suppression around `getimagesize`.  
Impact: malformed-file failures lose diagnostic classification and may hide environmental/library problems; careless replacement could leak private paths or warnings.  
Required fix: B08/B17 replace suppression with a narrow exception/warning-capture or maintained library boundary returning a typed validation result. Log only bounded error categories/digests, never source bodies or private paths.  
Acceptance: valid, truncated, polyglot, oversized, unsupported, and warning-producing fixtures return deterministic typed results; no PHP warning reaches the response; logs contain no private path/body; upload promotion remains fail closed.

#### NG-P3-040 — No declared PHP static-analysis and risk-based coverage gate

State: CONFIRMED.  
Evidence: audited Composer/scripts/CI configuration declares no PHPStan/Larastan gate or documented coverage threshold/critical-branch policy.  
Impact: type, nullability, dead-path, layer-dependency, and untested risk-critical regressions depend mainly on runtime tests and review.  
Required fix: B17 adds PHPStan/Larastan at an evidence-based achievable baseline, ratchets new violations, defines architecture/dependency rules, and records risk-critical branch coverage. Use mutation testing selectively for scoring/policy/state-machine code; do not treat 100% line coverage as correctness.  
Acceptance: clean checkout runs the pinned static-analysis command deterministically; baseline cannot grow silently; risk-critical modules meet approved branch/mutation expectations; generated/vendor code is excluded explicitly and narrowly.

#### NG-P3-041 — Four routes are unnamed without recorded intent

State: CONFIRMED.  
Evidence: audited `artisan route:list --json` inventory contains four unnamed routes. Their application/vendor ownership and intended usage have not all been classified.  
Impact: application redirects/navigation/tests may depend on brittle literal URLs, while blindly naming vendor/technical routes can create unsupported coupling.  
Required fix: B17 classifies every unnamed route; name application routes used by UI/tests under the existing naming convention and document routes intentionally left unnamed. Do not alter vendor routes without official support and regression evidence.  
Acceptance: route inventory records owner and decision for all four; application links use approved names; route/middleware/authorization behavior remains unchanged; no vendor contract is modified accidentally.

#### NG-P3-042 — Release-critical source and target-database tests can be conditionally skipped

State: CONFIRMED.  
Evidence: authority-DOCX feature tests can skip when the external source is unavailable, and query-index tests skip outside their supported database condition. Conditional local skips are legitimate, but a release run can otherwise omit source fidelity or target-engine evidence.  
Impact: green CI may not prove exact-hash authority coverage, production-engine migrations, query plans, locks, or concurrency behavior.  
Required fix: B00 records protected authority-artifact handling and CI provenance; B17 requires a release lane with the exact approved hash and the actual target database/version for migration, query-plan, lock, concurrency, and upgrade evidence. Keep local optional skips explicit rather than fabricating artifacts.  
Acceptance: release evidence proves the expected authority hash was present and verified; target-database lane has no release-critical skip; skip reasons are machine-visible; private authority files are not published in repository artifacts/logs.

#### NG-P3-043 — Routes and forms contain dense manual role/action wiring

State: CONFIRMED.  
Evidence: `routes/web.php` and audited Blade forms contain dense repeated route groups, role-dependent action blocks, and form markup. Current server policies remain the authority, but presentation/routing composition is difficult to review.  
Impact: middleware, confirmation, accessibility, or role-display changes can drift across duplicated paths; abstraction without characterization could instead hide important differences.  
Required fix: B08/B17 characterize route middleware/names and form markup first, then move coherent route groups to bounded route files and repeated UI to accessible components with explicit inputs. Preserve server policies and avoid a framework rewrite or universal component that erases semantic differences.  
Acceptance: route inventory/middleware/name snapshots remain stable; component variants have keyboard/error/confirmation/role tests; no hidden navigation is treated as authorization; duplication is reduced only where behavior is demonstrably shared.

## 11. Questionnaire inventory and consolidated execution override

The list below remains an audit inventory. For the current consolidated tester update, the owner's 2026-07-22 direction supersedes the routine pause: use the documented recommendations and do not send B07 through B17 questionnaires again. Stop only for credentials, a vendor contract, an external write, destructive data action, production deployment, or another material scope expansion that the repository cannot resolve.

1. At the start of the selected batch, perform a read-only repository/source drift check.
2. Enumerate every choice in that batch under these headings: product behavior, roles/scope, information architecture, visual interaction, content/pedagogy, grading, notifications, privacy/retention, accessibility/accommodations, security/abuse, migration/compatibility, deployment/rollout.
3. Send the complete batch questionnaire in one message. For each question:
   - explain why the answer changes implementation;
   - list mutually exclusive or combinable options;
   - identify a recommended option only when supported by confirmed evidence/primary sources;
   - include “defer/keep current behavior” where safe;
   - never preselect or infer an answer.
4. End with an explicit statement that no code will be changed until the owner answers every blocking item or explicitly defers it.
5. Record answers in docs/decisions/NG-Bxx-DECISIONS.md with date, owner wording, decision, alternatives, consequences, and unresolved items.
6. If a new choice appears during implementation, STOP, append a supplemental questionnaire, and wait.
7. If the owner asks for an option not safely implementable, explain the concrete evidence/risk and ask for a safe decision; do not silently substitute.

Every batch below contains a minimum questionnaire. The future AI MUST expand it when current code or new requirements reveal additional choices.

## 12. Universal engineering and checkpoint template

Every batch MUST:

- create characterization/failing tests before correcting confirmed defects where practical;
- use isolated databases/storage for write tests;
- use additive/expand-contract migrations for live-data changes;
- provide migration preflight, row counts, invariant checks, and rollback/forward-fix procedure;
- put incomplete/risky features behind server-authoritative feature flags;
- update policies, navigation, routes, localization, API/view models, and tests together;
- test Guest, unverified, Learner, Supervisor, Admin, Superadmin, unknown role, direct-ID, cross-institution/class, CSRF, throttle, stale/concurrent write, and validation paths where applicable;
- test 320/390/768/1280 CSS widths, zoom/reflow, keyboard, focus, errors, reduced motion, and touch target behavior for changed UI;
- run focused tests first, then strict Laravel, lint, Node, E2E, build, dependency audits, canonical/source/brand verification, and production readiness evidence as applicable;
- keep logs/audits bounded and exclude secrets, tokens, raw private responses, private paths, and uploaded source bodies;
- produce one checkpoint document and stop.

Checkpoint acceptance is evidence-based. Green tests do not close HUMAN_REVIEW, LEGAL_REVIEW, PREFERENCE, or production-operation gates.

## 13. Ordered implementation batches

### B00 — Authority freeze, change control, traceability, and decision bootstrap

Priority: P0 prerequisite.  
Dependencies: none.  
Goal: establish a trustworthy future work baseline without changing user behavior.

Mandatory questionnaire:

1. Is this folder the authoritative source, or must it be reconciled with an existing Git remote/history?
2. If no history exists, may the AI initialize a new private repository here?
3. Where may the repository be hosted, and who may access it?
4. Which files/directories are confidential or generated and must never be committed (DOCX authorities, databases, .env, storage, backups, rendered evidence, uploads)?
5. What branch/review/checkpoint convention should be used?
6. Does the owner require signed commits/tags or another integrity control?
7. Which CI provider/target operating system/database/browser matrix is approved?
8. Who is authorized to approve product, academic/content, accessibility, privacy/legal, security, and production gates?
9. Must the next implementation remain one batch per Codex task/checkpoint?
10. If either authority document changes, does the learning DOCX override existing packages and does the thesis override only product intent, as section 2 specifies?

Implementation:

- inventory all files by category and sensitivity; inspect .env examples without exposing real secrets;
- create/reconnect version control only after owner answer;
- create a complete .gitignore and secret-scan allowlist;
- record framework/runtime/lockfile/route/migration/schema/package/source hashes and test totals;
- add docs/decisions/NG-DECISION-REGISTER.md and docs/operations/NG-RELEASE-EVIDENCE-TEMPLATE.md;
- create a machine-readable traceability registry mapping finding -> batch -> requirement -> test -> decision -> evidence;
- add a CI skeleton that is non-destructive and does not upload authority/private artifacts;
- keep the authority DOCX available to trusted release CI through an approved protected artifact mechanism, not the public repository;
- record current main-database inventory read-only; do not clean or reseed it.

Acceptance:

- usable HEAD and baseline commit/tag or documented authoritative remote reconciliation;
- clean checkout builds and tests using documented prerequisites;
- no secret/private/authority/database artifact in tracked content unless explicitly approved and encrypted under policy;
- all section 4 verification gates rerun;
- decision owners named;
- checkpoint reports exact commit hash and no feature/data mutation.

Rollback: remove only newly created version-control/CI/traceability scaffolding if owner rejects it; do not delete existing files or evidence.

### B01 — Immediate release containment: credentials, registration, institution identity, and lifecycle truth

Priority: P0.  
Dependencies: B00.  
Addresses: NG-P0-001, 002, 003.

Mandatory questionnaire:

1. Is public self-registration required, optional, or prohibited?
2. Choose allowed enrollment mechanisms: staff invitation, expiring join code, verified email domain, manual approval, institutional SSO, or an approved combination.
3. Who may create/revoke invitations/codes: Superadmin, Admin, course lecturer, institution administrator?
4. Should institution names be public, hidden until a valid invitation, or shown only after authentication?
5. Can a person belong to multiple institutions? If yes, must one be active per session or can access span them?
6. Who owns and verifies an institution record and domain?
7. How should current free-text instansi values map to normalized institutions? Provide a reviewed mapping; may no fuzzy merge occur?
8. What should happen to unmatched/empty legacy institution values?
9. Are demo accounts needed in local development? Which exact environments may seed them?
10. How should the first production Superadmin be bootstrapped: one-time command with invite/reset, external identity provider, or another approved method?
11. Must all existing sessions be revoked after credential/enrollment migration?
12. May a lifecycle-draft package ever be active in a non-production preview tenant? What label and access restriction is required?
13. Who must approve content, ESP/hospitality, CEFR, accessibility, rights/links, retention, and final release before a non-draft version?

Implementation:

- add environment-fail-closed seed guard before any database write;
- move demo users to explicit local/E2E fixtures with randomly generated per-run secrets unavailable in logs;
- add one-time bootstrap-superadmin command with no default password, concurrency protection, idempotency, recent out-of-band verification/reset, bounded audit, and “existing superadmin” refusal;
- add deployment checker rules for production seeding/demo identities;
- normalize institutions with immutable IDs, unique normalized key under owner-approved rule, lifecycle/status, verified domains only if chosen, and migration mapping evidence;
- create invitation/enrollment-request workflow per answers: random high-entropy tokens stored as hash; scope, issuer, target email/domain/class, expiry, use limit, revocation, generic response, rate limit, transaction, audit;
- public registration must reveal no roster/institution membership information;
- preserve current exact-string scope until each legacy user is deterministically mapped; deny unsafe unresolved scope rather than guessing;
- create coherent CurriculumRelease state machine and production-delivery guard; do not modify 0.4.0-draft;
- move technical lifecycle/version evidence away from primary learner UI while retaining an accessible evidence view.

Schema candidates:

- institutions, institution_domains, institution_memberships or deferred B06 equivalent;
- invitations/enrollment_requests with hashed token and explicit states;
- curriculum_releases/approval gates if existing package fields cannot represent the approved state safely.

Security:

- uniform registration/invitation errors and response timing where feasible;
- account/IP/global abuse limits without disclosing target existence;
- token entropy, hash-at-rest, expiry, single transaction redemption, replay denial;
- no global role assigned through invitation;
- audit issuers/targets by IDs and event type only.

Acceptance:

- production seeder writes zero demo users and exits non-zero;
- no literal password fixture can create production users;
- anonymous user cannot enumerate institutions or test membership;
- invitation/code cannot be replayed, widened, guessed, or redeemed across scope;
- unresolved legacy institution cannot grant Supervisor access;
- production mode refuses draft active delivery;
- current active content remains unchanged until human gates close;
- role/access/browser tests and rollback migration tested.

Rollback: feature-flag new registration path, preserve old fields read-only during expand/contract, and restore old UI only in non-production containment if necessary. Never re-enable production demo seeding.

### B02 — Privacy, public trust pages, retention, export, and account/data lifecycle

Priority: P0/P1.  
Dependencies: B01 identity/institution model and approved LEGAL_REVIEW.  
Addresses: NG-P0-004, questions 18, public footer/registration notice.

Mandatory questionnaire:

1. Identify the data controller, privacy contact/DPO equivalent, and qualified legal reviewer.
2. Which user populations may include minors, employees, students, or external guests?
3. What lawful/policy basis applies to account, academic-progress, security-audit, communication, media, and optional AI/proctoring data? Do not ask the AI to decide law.
4. Which rights workflows are required: access/export, correction, restriction, objection, erasure/deletion, consent withdrawal, appeal?
5. What identity verification is required for each request and how will inaccessible-email cases be handled?
6. Define retention for account identity, enrollment, progress, scores/submissions, open responses, uploads, announcements, notifications, security logs, administration audits, backups, and legal holds.
7. On account deletion, choose per data class: hard delete, anonymize, pseudonymize/tombstone, retain under hold, aggregate only.
8. Who may approve/deny a request and who may see its status?
9. Should users self-delete immediately, submit a reviewed request, or use a cooling-off period?
10. What export formats are required and what fields are excluded?
11. Must deletion propagate to standalone/exported copies or only controlled systems, and how will users be informed of limits?
12. How long may deletion evidence itself be retained?
13. What public pages are required: privacy, terms, cookie/analytics notice, accessibility statement, data-rights contact, acceptable use?
14. Is analytics/cookie tracking enabled now or planned? If yes, provide approved categories/consent policy.
15. What language versions and effective-date/version acknowledgment are required?

Implementation:

- create versioned public policy documents and accessible footer/help links;
- replace ambiguous registration terms with explicit links and versioned acknowledgment/consent only where counsel says appropriate;
- create DataSubjectRequest with opaque ID, type, submitted/identity_pending/in_review/approved/denied/held/executing/completed/failed/cancelled/appealed states, deadlines, owner, reason codes, bounded notes, and immutable events;
- build authenticated user request/status/cancel path and authorized staff queue;
- produce export asynchronously from an allowlisted field inventory, encrypt at rest, signed short-lived download, download audit, expiry and deletion;
- create a data dependency graph and erasure orchestrator with idempotent steps, retry, dry run, transaction boundaries, and compensating repair;
- resolve restrictOnDelete audit foreign keys through approved pseudonymous actor/target references or tombstoned user identity; never cascade-delete security audit by accident;
- revoke sessions/tokens, stop notifications, and prevent reactivation races;
- keep aggregate metrics only where re-identification risk is addressed;
- address backups through documented expiration/recovery exclusion rather than attempting unsupported in-place backup editing;
- create deletion receipt/status visible to authorized staff without retaining deleted content.

Security/privacy:

- step-up authentication for export/delete;
- anti-CSRF, throttle, notification of sensitive request/status changes;
- no request details in URLs/logs; no export formula injection; no public request enumeration;
- staff access to request queue audited; separation of duties if owner selects it.

Acceptance:

- field-level retention/deletion matrix approved by counsel/owner;
- full lifecycle tests including duplicate, concurrent, held, partial failure, retry, backup restore, and re-registration;
- deleted identity cannot authenticate or appear in ordinary rosters/search;
- required retained evidence is pseudonymous and purpose-limited;
- user and staff see accurate status;
- public pages meet WCAG and are linked before data collection;
- no claim of legal compliance without reviewer approval.

Rollback: stop new request intake under a visible incident state; do not restore deleted data from ordinary rollback. Forward-fix failed deletion steps using idempotent evidence.

### B03 — Authentication, ASVS security baseline, upload scanning, observability, and deployment safeguards

Priority: P1.  
Dependencies: B00; B01 for identity; B02 for security-log retention.  
Addresses: NG-P1-013, 014, 015.

Mandatory questionnaire:

1. What ASVS 5.0.0 target level is required for public, learner, lecturer, and privileged functions?
2. Which MFA methods are approved: WebAuthn/passkeys, TOTP, hardware key, recovery codes, institutional SSO? SMS/email must not be assumed as a strong second factor.
3. Which roles/actions require MFA and step-up?
4. What recovery process applies after lost authenticators, and who may reset privileged MFA?
5. Is institutional SSO/OIDC/SAML in scope? Identify provider and tenant rules; do not invent endpoints.
6. Approve password-only minimum length, blocklist source/service, maximum length, Unicode normalization, and breach-check privacy mode.
7. Approve layered throttling/challenge/risk rules and lockout/support behavior.
8. Which security events require central logging and alerts, and which monitoring/SIEM destination is approved?
9. Who receives alerts and owns incident response?
10. Which malware scanning/CDR option and failure mode is approved for DOCX/image/audio?
11. Identify production host, reverse proxy/TLS terminator, database engine, object/private storage, queue/scheduler, secrets manager, mail provider, log platform, backup tool, and responsible operators.
12. What backup RPO/RTO, retention, encryption, immutability, and restore-test cadence are required?
13. Is an external penetration test required before pilot or production, and what scope?
14. Are third-party error/performance monitoring services allowed to receive pseudonymous data?
15. Which CSP camera/microphone/connect/frame sources are allowed? Keep denied unless an approved feature needs them.

Implementation:

- create ASVS 5.0.0 traceability matrix and test/evidence mapping;
- configure project Password defaults centrally under answers; block common/context/compromised values without arbitrary composition; preserve paste/password-manager/autofill;
- implement approved MFA/WebAuthn with encrypted credential metadata as applicable, enrollment/recovery/revocation, backup codes hashed, privileged enforcement, step-up, session inventory/revocation, and accessible fallback;
- layer per-account, per-IP, global and risk/event rate controls; avoid attacker-triggered permanent lockouts;
- add generic authentication errors and structured events for success/failure/throttle/reset/MFA/session/privileged read while excluding password/token/MFA secret;
- add upload scanner adapter, quarantine state machine, timeout/retry/circuit behavior, safe positive test fixture, and explicit asset promotion;
- integrate secret/config validation, dependency audit, PHP static analysis, SAST/DAST where approved, CSP reporting endpoint, integrity-aware central logs, alerts and runbooks;
- add database/storage least-privilege specifications, queue/scheduler health, mail failure handling, backup/restore automation evidence;
- extend deployment checker for demo seeds, MFA on privileged accounts, scanner health, queue/scheduler, policy pages, environment config, migrations, and active non-draft release.

Acceptance:

- approved ASVS requirements have current evidence or explicit gaps;
- password and MFA flows pass accessible authentication and recovery threat tests;
- distributed attack simulation triggers defense without revealing/locking arbitrary accounts permanently;
- scanner positive/unavailable/timeout/retry cases fail closed per policy;
- no secrets/private content in logs;
- production-like isolated environment passes deployment checker;
- backup plus private storage restoration succeeds and application invariants verify;
- independent penetration-test findings resolved or risk-accepted by owner.

Rollback: retain password/MFA compatibility during phased migration; provide emergency break-glass under documented dual control; never disable security controls silently to restore access.

### B04 — WCAG 2.2 AA, responsive shells, auth semantics, mobile lecturer workflow

Priority: P1/P2.  
Dependencies: B02 public policy links; B03 accessible auth constraints.  
Addresses: NG-P1-016, NG-P2-020, 021, 033.

Mandatory questionnaire:

1. Confirm WCAG 2.2 AA as target and identify human accessibility reviewer(s).
2. Which browser/screen-reader combinations and physical devices must be supported?
3. Choose mobile administration navigation pattern: top drawer, bottom primary actions, collapsible side rail, or owner-provided design.
4. Which admin actions must be reachable above the fold on mobile?
5. Choose target-size design goal: WCAG minimum 24×24 with exceptions, preferred 44×44 for primary controls, or another documented policy.
6. Should long progress/course pages use tabs, accordions, anchored outline, pagination, cards, or a selected combination?
7. Choose table mobile behavior per data type: horizontal table, responsive cards, priority columns, or downloadable report (exports remain separately governed).
8. Must language selection be text, icon+text, menu, or persistent switch; where should it appear?
9. Approve visual density, font-size range, contrast theme, reduced-motion default, and focus style.
10. Which accessibility accommodations must be configurable at user/class level: extra time, no audio, captions, transcript, reduced motion, high contrast, screen-reader optimized ordering?
11. Should help be globally persistent and where?

Implementation:

- introduce one semantic guest/auth/learner/admin shell contract with skip-to-main, one main, one h1, correct landmarks, page title, focus target, and consistent help;
- use persistent visible labels, autocomplete/input purpose, field aria-invalid/describedby, error summary with focus and links, status/live regions only as needed;
- correct language/action/touch targets and radio flex shrink;
- implement chosen responsive admin navigation with focus trap/return, Escape, inert/background handling and scroll lock; no hover-only disclosure;
- prioritize page summary/next action before evidence; move diagnostics behind named disclosure/evidence route;
- replace 85-section all-at-once rendering with approved summary/filter/progressive navigation while keeping data accessible;
- provide responsive table/card patterns and no root overflow at 320 CSS px;
- audit color contrast, non-text contrast, focus not obscured, zoom, text spacing, reduced motion, pointer gestures, dragging alternative, target size, accessible authentication;
- add axe or equivalent automated regression if approved, but keep manual test plan.

Acceptance:

- WCAG 2.2 AA checklist with pass/fail/not-applicable/evidence, no blanket conformance claim before human review;
- keyboard-only complete flows for registration/login/learning/admin authoring/progress;
- tested at 320/390/768/1280, 200% text zoom and 400% page zoom/reflow;
- screen-reader label/error/status/navigation checks on approved matrix;
- no root horizontal overflow; internal two-dimensional data exceptions labeled;
- mobile users reach primary content/action without traversing full sidebar;
- touch controls meet approved policy;
- human reviewer signs or open issues remain release blockers.

Rollback: retain old shell behind temporary server flag during validation; do not maintain two shells after acceptance.

### B05 — Value proposition, information architecture, onboarding, search, help, and next actions

Priority: P2.  
Dependencies: B04 shell; B06 terminology may be previewed but not assumed.  
Addresses: questions 4, 7, 10; NG-P2-018, 019, 022, 023, 024.

Mandatory questionnaire:

1. Identify primary public audience(s): student, lecturer, institution, hospitality employer, research evaluator.
2. Rank the primary public conversion CTA(s): try sample, create account, join class, lecturer demo, contact, sign in.
3. Approve the exact value proposition/tone and whether Indonesian, English, or bilingual copy leads. Do not let the AI invent final marketing claims.
4. May a public sample lesson be offered? Which source material and what tracking/privacy rules?
5. Choose first-login flow: guided tour, goal/level intake, class join, diagnostic, sample scenario, or owner-defined sequence.
6. Is the baseline confidence activity a diagnostic, self-reflection, optional skip, or required first task?
7. What technical evidence should ordinary learners/lecturers see, and what belongs only in Advanced/Evidence?
8. Choose primary learner dashboard emphasis: Continue, assigned work, review due, progress, or a specified combination/order.
9. Choose admin dashboard emphasis: authoring queue, grading queue, learner alerts, announcements, calendar, or system evidence.
10. What search scope is required: lessons, vocabulary, activities, announcements, help, learners/classes?
11. What help channels/content are approved: searchable help, glossary, FAQ, contextual tips, contact form/email, institution support?
12. Which role names should be user-facing: Learner/User/Student, Supervisor/Lecturer/Instructor, Admin/Content Admin, Superadmin/System Admin?
13. What analytics may measure onboarding success and under what consent/retention?
14. What first-use study sample size, participant mix, recruitment/consent method, task set, facilitation method, and success/failure criteria are approved?

Implementation:

- create approved role/persona vocabulary and navigation map;
- revise public page only with owner-approved claims and CTAs; link privacy/accessibility/help;
- add class join/first-use flow after B06 or use a non-destructive placeholder that does not fake capability;
- create resumable onboarding state, skip/restart rules, and no dark patterns;
- learner dashboard action resolver: required/overdue/review/continue/complete priority per decision;
- lecturer dashboard task queues after relevant domains exist; until then accurately show existing Content/Exercises/Progress capabilities;
- move package hashes/provenance/technical codes to accessible evidence panel;
- add bounded indexed search with role/publish/access filters, escaped highlights, no private-result leakage;
- add help/glossary/content ownership and versioning;
- correct Superadmin/Admin identity and active class/institution context in shell.

Acceptance:

- every role has one clear page purpose, primary action, empty state, recovery/help path;
- no CTA promises unavailable capability;
- direct search cannot expose draft/private/cross-scope records;
- first-use moderated test protocol exists and results are recorded as evidence, not invented;
- technical metadata remains available to authorized reviewers without dominating ordinary UI.

Rollback: content/navigation changes are versioned; retain old copy for comparison but do not expose contradictory CTAs.

### B06 — Institution, course, class/cohort, enrollment, and scoped lecturer roles

Priority: P1 core LMS.  
Dependencies: B01 normalized institution/invitation; B02 privacy; B03 security; B04 shell.  
Decision state: approved and locally completed 2026-07-22 through B06-A, UI-R02, B06-B, and B06-C; see `docs/decisions/NG-B06-DECISIONS.md`, `docs/checkpoints/NG-B06-A-FOUNDATION-2026-07-21.md`, `docs/checkpoints/UI-R02-RESPONSIVE-LEARNER-SHELL-2026-07-21.md`, `docs/checkpoints/NG-B06-B-CLASS-ROSTER-2026-07-21.md`, and `docs/checkpoints/NG-B06-C-CLASS-LEARNING-CUTOVER-2026-07-22.md`. Production and specialist-review gates remain open. Implementation must preserve the scoped content-release boundary recorded there.  
Addresses: NG-P1-009, the shared time foundation for NG-P2-026, and foundation for questions 11–16.

Mandatory questionnaire:

1. Define Institution, Course, Class/Course Offering, Cohort/Group, Semester/Term, and whether all are needed.
2. Can a course use one canonical curriculum package or select modules/activities across versions?
3. Who owns a course/class and who can co-teach?
4. Are Supervisor and Lecturer the same user-facing role? Should global Supervisor remain, become course-scoped instructor, or coexist?
5. What can Admin do across institutions/classes, and what remains Superadmin-only?
6. Can one learner enroll in multiple classes/institutions?
7. Enrollment methods: invitation, code, roster CSV, manual add, domain, SSO/LTI. Which are approved?
8. Who may remove/suspend/transfer learners, and what happens to historical work?
9. Are groups manually assigned, self-selected, random, or imported?
10. What class lifecycle states and dates are required?
11. What roster fields may lecturers view?
12. May lecturers impersonate/preview as a learner? Prefer safe preview, not identity impersonation; owner must decide.
13. Are class templates/copies needed? What content, dates, grades, and learner data must never copy?
14. What institution/course data retention and archive behavior is required?
15. What IANA timezone is the institution/class default, may a user override it, and what is the precedence among system, institution, class, and user settings? “UTC until explicitly configured” is an available safe deferral.

Implementation:

- add explicit UUID/ID-based Institution, Course, CourseOffering, Enrollment, TeachingAssignment, Group and GroupMembership as selected;
- separate global system role from scoped membership capabilities; policies evaluate actor + resource + membership;
- migrate instansi mapping from B01; retain legacy value for provenance only;
- build roster/invitation/import paths per approved policy; sanitize CSV formulas and validate headers/row bounds if CSV chosen;
- create class lifecycle draft/open/closed/archived (or approved names) and immutable content-version association per assignment;
- provide teacher/student preview using policy-scoped preview data, never session impersonation unless separately approved;
- add course/class switcher with active context and no cross-context data leakage;
- update all progress/authoring/announcement/future queries to require explicit scope;
- preserve Admin aggregate and Superadmin global controls unless changed by an explicit privacy decision;
- establish one shared injectable Clock and policy-scoped TimeContext/timezone resolver for later assignments, announcements, calendars, reminders, and exams; persist only validated IANA zone identifiers selected by the owner, keep instants in UTC, define fallback precedence explicitly, and do not implement calendars, reminder dispatch, recurrence, countdowns, or graded timers in B06.

Acceptance:

- capability matrix includes global and class-scoped roles;
- same person can be learner in one class and lecturer in another without global escalation;
- direct-ID/cross-class/inactive/removed enrollment tests fail closed;
- historical submissions remain attached to the class/content version after removal/archive;
- roster import is idempotent, bounded, previewed, audited, and reversible before commit;
- no institution free-text equality remains an authorization basis after migration;
- shared Clock/TimeContext tests cover invalid zone rejection, selected fallback precedence, DST gap/fold conversion, class/user scope, and deterministic frozen-time behavior without creating a second time implementation in later batches.

Rollback: expand/contract; preserve legacy scope until all records mapped and verified; class feature flag can hide new UI without dropping data.

### B07 — Assignments, learning paths, prerequisites, due dates, exceptions, and learner direction

Priority: P1 core LMS.  
Dependencies: B06, including its shared Clock/TimeContext/timezone foundation. B12 later adds calendar/reminder/timer capabilities; B07 must not introduce a separate time system.  
Addresses: NG-P1-010, question 14.

Mandatory questionnaire:

1. What can be assigned: package, chapter/module, section/lesson, activity, review set, external item?
2. Can one assignment contain multiple ordered items?
3. Target scope: class, group, individual; can learners be excluded?
4. Are assignments required, optional, recommended, or extra credit?
5. Choose assignment status vocabulary and what constitutes submission/completion.
6. Which prerequisite rules are allowed: view, participation completion, minimum score, pass/mastery, instructor approval?
7. Can learners bypass sequence for self-study? Can lecturers override?
8. Define available-from, due, close/until, late grace, and reopen behavior.
9. Define late/missing/excused/waived/incomplete semantics.
10. May due dates differ per learner/group? What accommodations/exceptions exist?
11. Which content version is frozen when assigned and what happens after new publication?
12. Can an assignment be edited after learners start? Which changes create a new revision?
13. What notifications/reminders are desired, deferred to B12?
14. What learner/dashboard ordering and teacher status filters are preferred?

Implementation:

- add versioned Assignment, AssignmentRevision, AssignmentItem, AssignmentTarget, AssignmentException and derived AssignmentStatus;
- reference immutable content package/entity IDs and preserve source version;
- explicit rules engine with allowlisted typed requirement operators; no executable expressions;
- server computes availability/status through the shared B06 Clock/TimeContext using UTC and the selected IANA timezone; client countdown is display only;
- idempotent assignment creation/duplication; optimistic locking; audit material changes;
- learner dashboard separates Assigned, Due Soon, Overdue, Completed, Optional/Self-study;
- teacher dashboard shows no-access/not-started/in-progress/submitted/late/completed/excused without conflating learning/mastery;
- provide override/reopen/extension with reason and audit;
- protect answer/model visibility according to B09 classification.

Acceptance:

- DST/timezone boundary tests, due/close race, extension, version-update, withdrawn assignment, enrollment removal, concurrent edit;
- no assignment can grant access to unpublished/draft content;
- prerequisite cycles rejected with clear path;
- status definitions documented and identical across learner/teacher/API;
- lecturer can direct a learner to a specific task and see truthful task state;
- no score/mastery claim before B09.

Rollback: disable new assignment creation while preserving read/status history; never retarget existing started work to a new content version silently.

### B08 — Lecturer-friendly canonical course/lesson/media/exercise authoring

Priority: P1/P2.  
Dependencies: B04–B07; preserves ADM-2/3/4 immutable workflow.  
Addresses: question 11, NG-P2-018, NG-P3-035/036/037/038/039.

Mandatory questionnaire:

1. Choose authoring modes: guided simple mode, advanced canonical mode, or both.
2. Which users are “lecturers” and may author within which course/class/institution?
3. Can lecturers create entirely new curriculum, clone authority content, or only assemble approved canonical units?
4. Which content hierarchy labels are preferred: Course/Module/Lesson/Section/Activity?
5. Which block types are required: rich text, callout, table, image, audio, video, file, link, vocabulary, activity, rubric?
6. Which media sources are permitted: direct upload, approved URL providers, embedded video, recorded audio/video?
7. Define file sizes/formats, rights/license/attribution/alt/caption/transcript requirements, and scanning service.
8. Is collaborative editing/co-authoring required? What review/approval roles?
9. Choose navigation/layout: left outline, top steps, split preview, or owner-provided design.
10. Is drag-and-drop desired? A keyboard/button alternative remains mandatory.
11. How should autosave, manual save, draft status, conflict resolution, and unsaved-change warnings behave?
12. Should 12 enabled exercise templates use human-friendly names/categories? Provide approved labels.
13. For the two disabled audio templates, defer to B15 or include an approved accommodation design now?
14. Can authors edit answers/accepted variants/feedback/rubrics? Which changes require reviewer approval?
15. Is bulk DOCX import intended for ordinary lecturers or only trained content administrators?
16. What preview devices/roles must be available?

Implementation:

- keep canonical draft workspace/publication engine; build a task-oriented Course Builder facade over it;
- move all DB queries out of Blade into controllers/view models;
- create typed block/editor DTOs and shared components; SafeHtml boundary for reviewed rich text;
- outline/tree with keyboard Move up/down and optional drag enhancement;
- simple mode validates plain-language required fields and compiles to the same canonical entities; advanced mode shows codes/provenance explicitly;
- media library with quarantine/scanner/rights metadata, alt/caption/transcript requirements, digest reuse, protected preview;
- exercise template gallery for 12 approved types, stable answer IDs, plain-language validation, learner preview using the exact delivery renderer;
- preserve optimistic locking, source-aware diff, approval, immutable publish, rollback;
- introduce autosave only if selected, with idempotency and visible state; never overwrite a conflict;
- split large services only after characterization; extract compiler/read/validation/persistence responsibilities with interfaces and no duplicate business rules;
- isolate legacy evidence pages and remove legacy-authoring ambiguity.

Acceptance:

- lecturer usability study completes representative create module/lesson/media/activity/edit answer/preview/submit-for-review tasks without developer help under owner-defined success thresholds;
- all 12 templates create/validate/preview/publish through one engine; two disabled types stay unavailable absent approval;
- keyboard-only authoring, mobile/tablet scope per questionnaire, conflict/recovery, unsafe upload and authorization tests;
- source/package determinism and existing active package unchanged until explicit publication;
- no DB query in Blade; no raw unsanitized HTML path.

Rollback: keep advanced ADM workflow available during staged rollout; simple builder can be feature-flagged off without losing canonical drafts.

### B09 — Assessment semantics, submissions, scoring, rubrics, gradebook, and feedback

Priority: P1.  
Dependencies: B06–B08; B02 privacy.  
Addresses: NG-P1-006, 007, 008, 011; question 12.

Mandatory questionnaire:

1. Classify each assessment purpose: ungraded practice, formative, summative, diagnostic/self-report. Who approves classification?
2. Choose grading representation: points, percentage, bands/letters, pass/fail, competency/mastery, or approved combination.
3. Define categories/weights and whether weights must total 100%.
4. Define pass thresholds and whether chapter/course mastery requires multiple outcomes or attempts.
5. Define which attempt counts: first, latest, highest, average, manually selected, unlimited practice plus graded attempt.
6. Define attempt limits, cooldowns, resets, and extension/exception rules.
7. When are correct answers, model answers, rubric, score, feedback, and class statistics revealed?
8. Can learners retry after seeing answers? Is a new item variant required?
9. Which objective response normalization/accepted variants may lecturers edit, and what review is required?
10. Which open responses are persisted? Current raw role-play/writing is deliberately not retained. Any change requires explicit purpose, access, retention, deletion, and learner notice.
11. Who manually grades: lecturer, co-lecturer, grader, peer? Is anonymous/delegated grading required?
12. Define rubric scales, criterion weights, comments, attachments/media feedback, and whether self-assessment affects grades.
13. Can grades be overridden? Who, reason requirement, approval, audit, and learner notification?
14. When are grades released: immediately, after due date, manual post, all-at-once?
15. Define rounding precision/display, missing/late/excused/extra-credit behavior, and recalculation policy after rule changes.
16. Is CSV import/export required? If yes, approve fields, formula neutralization, audit, bounds, and retention.
17. Must grades integrate with another LMS/SIS? If yes, B16 must select LTI 1.3/AGS or an approved API.
18. Who may view item-level correctness, normalized closed responses, open submissions, and grade history?
19. Which assessment blocks currently expose answers/models too early, and what reviewer-approved reveal metadata applies?

Implementation:

- define separate immutable concepts: learner interaction/preview, attempt, submission, response, evaluation, participation completion, score, grade, pass, mastery;
- migrate historical show_model rows to preview classification without deletion; exclude from submitted-attempt counts;
- retain participation completion but remove ambiguous labels;
- add versioned GradePolicy/Category/Item and assessment revision binding;
- add Submission state machine: not_started/in_progress/submitted/late/returned/resubmitted/graded/released/excused or owner-approved vocabulary;
- add scoring result with raw numerator/denominator, policy version, calculated value, rounding/display; do not overwrite history;
- build deterministic grade calculator and recalculation preview/diff; rule changes create new revision and explicit effective scope;
- create manual grading queue and rubric evaluation, optimistic locking, audit, override reason, grade release;
- classify source answer/model blocks; compiler preserves exact content while view enforces approved reveal state;
- remove duplicated option rendering through typed source-block roles;
- use minimized response storage; no new raw open text/audio without B02 contract;
- accessible learner feedback with error/correct state not conveyed by color alone.

Acceptance:

- preview does not count as attempt/submission;
- tests prove participation, correctness, score, grade, pass, mastery are independent;
- calculation property tests cover weights, rounding, drops/excused/missing/late/extra credit under selected rules;
- historical attempts display under their original policy/content version;
- grade changes and releases are audited and authorized;
- no answer leakage under each reveal policy;
- lecturer can configure approved scoring without code changes;
- learner and lecturer see consistent score/feedback/status.

Rollback: calculations are append-only/versioned; disable new grade policy while retaining submissions/results. Never erase or recalculate released grades silently.

### B10 — Lecturer analytics, learner mastery, actionable progress, and reporting

Priority: P1/P2.  
Dependencies: B06, B07, B09.  
Addresses: NG-P1-012, question 13.

Mandatory questionnaire:

1. Which roles need class, group, individual, institution, and global analytics?
2. Approve each metric: enrollment, last access, learning time, assigned status, participation completion, score, pass, mastery, attempts, previews, late/missing, review due.
3. Define “learning time” or reject it; browser-open time must not be presented as active learning without an approved method.
4. Define mastery model/threshold and evidence; may it decay or require spaced demonstrations?
5. Which outcomes/competencies must activities map to?
6. What “at risk/needs attention” rules are allowed, and may automated recommendations affect learners?
7. Should lecturers see item analysis/difficult questions? Minimum cohort size/suppression rule?
8. May Admin see institution/class slices or remain global de-identified only?
9. Which identity fields may Supervisor/Lecturer see? Does course enrollment replace same-instansi scope?
10. Which exports are required and for what purpose/retention?
11. What dashboard layout/chart/table preferences apply? List priority decisions before UI.
12. How frequently must analytics update and is near-real-time necessary?
13. May learners see all the same mastery/assignment facts and recommendations?
14. How should unknown/historical content versions display?

Implementation:

- publish a metric dictionary with exact event source, numerator, denominator, filters, time zone, update latency, exclusions, privacy level, and non-claims;
- derive analytics from typed events/submissions/grades, not page impressions or raw responses;
- separate preview, start, practice, submit, complete-participation, pass, mastery;
- course-scoped lecturer dashboard: assignment matrix, grading queue, needs-attention list, item analysis under cohort suppression, learner timeline;
- learner dashboard: own assignments, score/feedback/mastery, review due, transparent recommendation reason;
- retain Superadmin global identity and Admin approved aggregate boundaries; use policy-scoped query objects;
- precompute aggregates only when needed, with idempotent jobs, backfill checkpoint, watermark, and reconciliation against source-of-truth;
- pagination/filter/export bounds; no 85-section unconditional page;
- anomaly and data-quality indicators for missing package definition, stale code, clock skew, duplicate event.

Acceptance:

- every displayed metric links to/has an accessible definition;
- preview-only activity cannot increase attempts/completions/mastery;
- incorrect participation completion is not mastery;
- direct-ID/cross-class/historical/empty-cohort/cohort-suppression tests;
- aggregate reconciliation produces exact source totals;
- performance/query-count and target-database plan pass at owner-defined volume;
- lecturer can identify not-started, late, submitted, passed/mastered, and needs-review truthfully without private raw content;
- no predictive claim without validation.

Rollback: dashboards can revert to source queries/feature flag; never delete source events. Rebuild derived projections from immutable facts.

### B11 — Announcements, rich posts, targeting, moderation, and communication

Priority: P2.  
Dependencies: B02 privacy/retention, B03 upload/logging, B04 accessibility, and B06 classes plus its shared Clock/TimeContext/timezone foundation.  
Addresses: NG-P2-025, question 15.

Mandatory questionnaire:

1. Who may author: Lecturer, Supervisor, Admin, Superadmin, designated communicator?
2. Target audiences: institution, course/class, group, individual, all users, public?
3. Supported post media: text, image, audio, video upload, approved embed, file, links, combinations?
4. Are comments/replies/reactions allowed? Who moderates and what report/appeal process?
5. Are drafts, approval, scheduling, pinning, expiry, edit history, and archive required?
6. Should edited announcements notify again? Should deletions leave a tombstone?
7. Is read/unread or acknowledged receipt required? Clarify that delivery/read receipt is not comprehension.
8. Delivery channels: in-app, email, push, SMS, integrations. Which are approved and optional?
9. User notification preferences and mandatory emergency exceptions?
10. Quiet hours, digest frequency, timezone, retries, bounce handling?
11. Content retention/deletion/export and media rights/caption/transcript requirements?
12. What visual feed/card/layout and placement is preferred?
13. Is an emergency/high-priority category allowed, and who may use it?

Implementation:

- Announcement/Post, Revision, AudienceTarget, Attachment, Delivery, Read/Acknowledgment, ModerationReport as selected;
- rich content through the approved SafeHtml/block renderer; media pipeline from B03/B08;
- server-authoritative audience snapshot or dynamic rule explicitly selected; prevent later scope widening;
- draft/review/publish/schedule/expire/archive state machine, optimistic locking and audit;
- resolve schedule/expiry/quiet-hour instants through the shared B06 Clock/TimeContext; do not create announcement-specific timezone precedence or clock logic;
- in-app inbox/feed with accessible filters and notification preferences;
- queued delivery with idempotency, rate control, retries, bounce/failure status, unsubscribe/preferences where permitted;
- edit history and visible edited/published timestamps; no silent replacement;
- comments/moderation only if staffed and approved.

Acceptance:

- cross-class/institution/direct-ID and audience-change tests;
- unpublished/scheduled/expired content cannot leak;
- unsafe media/link/HTML rejected;
- screen-reader/caption/transcript/keyboard/reflow tests;
- delivery is idempotent and records accurate sent/failed/read/ack semantics;
- retention/moderation runbooks approved.

Rollback: stop new deliveries, preserve published/history/audit, display honest outage state. Do not re-send on retry without idempotency.

### B12 — Calendar, schedules, availability, reminders, countdowns, and timers

Priority: P2.  
Dependencies: B02 retention; B06 shared Clock/TimeContext/timezone foundation; B07 assignment dates; B11 notification channels. B12 extends the shared foundation and must not replace it.  
Addresses: NG-P2-026, question 16.

Mandatory questionnaire:

1. Confirm or revise the B06-approved system/institution/class/user IANA timezone values and precedence; which schedule-specific override, if any, is allowed?
2. Which calendar event types: assignment due, availability, class/live session, announcement, exam, office hour, personal reminder?
3. Who may create/edit/cancel each type and target whom?
4. Recurrence rules and exceptions required?
5. Reminder channels, timing, quiet hours, digest, snooze, opt-out?
6. Define timer types: informational countdown, task duration stopwatch, graded attempt countdown. Graded exams belong in B13.
7. Should time spent be recorded? For what purpose and how are idle/background periods handled?
8. What happens when server/client clocks differ or connection drops?
9. Are calendar exports/subscriptions (ICS) required? What private information may be included?
10. What views are preferred: agenda, month, week, upcoming list?
11. What accessibility/accommodation behaviors apply to time limits and alerts?
12. How long are events/delivery logs retained?

Implementation:

- reuse the shared B06 Clock/TimeContext; store instants in UTC and store original IANA zone and local wall-time intent where recurrence/author intent requires it;
- never store only UTC offset; use maintained tz database;
- CalendarEvent/Recurrence/Target/Reminder/Delivery/Exception as approved;
- derive assignment/announcement/exam dates into one calendar projection without duplicating authority;
- server is authority for deadline/timer; client uses synchronized deadline and resync, not a trusted interval counter;
- accessible countdown with non-disruptive milestones; no per-second live-region spam;
- queue reminders idempotently; cancellation/reschedule invalidates pending jobs safely;
- optional ICS uses signed revocable opaque token, minimal fields, no secrets.

Acceptance:

- DST gap/fold, leap/date boundary, locale formatting, clock skew, offline/reconnect, cancelled/rescheduled/double-job tests;
- extension/accommodation applied before countdown;
- timer cannot be reset by reload or client clock change;
- calendar does not reveal cross-scope/private titles;
- user preferences/quiet hours honored except explicitly approved mandatory category.

Rollback: disable reminder dispatch while keeping source events; recalculate jobs from events after repair. Do not delete assignment due facts.

### B13 — Timed assessments, academic-integrity controls, optional secure browser/proctoring

Priority: P1 only if high-stakes assessment is required; otherwise defer.  
Dependencies: B02/B03/B04/B06/B07/B09/B12.  
Addresses: NG-P2-027, question 17.

Mandatory questionnaire:

1. Are exams low-stakes practice, formative tests, or high-stakes academic decisions?
2. Is preventing access to other sites truly required, or are reasonable integrity controls sufficient?
3. Which baseline controls are approved: time window, duration, autosave, randomized order, question bank, per-learner variants, attempt limit, access code, delayed feedback, honor statement?
4. Is Safe Exam Browser or another lockdown client approved? Which supported OS/devices and managed/unmanaged environment?
5. Is remote proctoring approved? Identify vendor/self-hosted system; camera/microphone/screen/ID/environment data; reviewer; legal basis; retention; location; subprocessors.
6. Can learners opt out and use an equivalent supervised/accommodated path?
7. Define accommodations: extra time, breaks, assistive technology, alternative device/client, no camera/audio, reschedule.
8. What happens on network loss, power loss, crash, browser close, server outage, clock skew?
9. Define resume, auto-submit, invalidation, retake, appeal, suspicious-event review and final authority.
10. When are questions/answers/grades released?
11. Are copy/paste, print, spellcheck, navigation, calculator, notes, permitted sites/apps allowed?
12. What identity assurance is required?
13. What support/practice exam/device check must occur before the real exam?
14. Who accepts accessibility/privacy/legal residual risk?

Implementation tiering:

Tier 1 (ordinary web integrity):

- Exam/ExamVersion/QuestionPool/AttemptWindow/Accommodation/IntegrityEvent;
- cryptographically unpredictable item selection from approved blueprint, stable per attempt;
- server deadline, idempotent autosave, offline/reconnect rules, heartbeat as reliability not surveillance;
- explicit attempt state, start confirmation, time warnings, auto-submit, recovery, audit;
- delayed feedback/answer release, access codes hashed if selected;
- no claim that tabs/apps/devices are prevented.

Tier 2 (secure-browser integration, only if approved):

- signed per-exam configuration, server verifies approved client/config handshake, documented allowed resources;
- practice/system check; accessible alternative path;
- treat client as external dependency and test supported versions;
- do not invent SEB endpoints/configuration; use current official integration spec.

Tier 3 (proctoring, only if approved after legal/privacy/accessibility review):

- explicit notice/consent or other approved basis, minimization, encryption, vendor contract, retention/deletion, reviewer authorization, incident/appeal;
- visible capture indicators and preflight; never hidden surveillance;
- separate proctor signal from academic decision; human review and appeal.

Acceptance:

- UI accurately labels integrity tier and limitations;
- ordinary web mode never claims prevention of other sites/devices;
- randomized blueprint distribution and answer secrecy tests;
- timer/autosave/recovery/concurrency/outage/accommodation tests;
- secure client handshake cannot be replayed/widened;
- approved AT/device/accommodation paths tested;
- privacy/legal/security sign-off and practice-exam completion before high-stakes launch.

Rollback: suspend exam start while preserving in-progress recovery and submissions. Never invalidate attempts automatically solely because monitoring failed.

### B14 — Engagement, spaced practice, personalization, optional gamification, and social motivation

Priority: P2.  
Dependencies: B09 outcomes/scoring, B10 metric definitions, B02 privacy, B04 accessibility.  
Addresses: NG-P2-028, questions 8–9.

Mandatory questionnaire:

1. Which engagement outcomes matter: practice frequency, completion, retention/mastery, confidence, enjoyment, return rate?
2. Approve spaced-practice model and whether review scheduling uses correctness, confidence, time, or teacher assignment.
3. Should personalization recommend only, reorder automatically, or adapt difficulty? Can lecturers override?
4. Select optional mechanics: streak, XP, level, badge, quest, daily goal, progress celebration, leaderboard, class challenge, none.
5. Is each mechanic opt-in, opt-out, or disabled by institution/class?
6. May XP affect academic grade? Recommended answer is no; owner must decide explicitly.
7. What actions earn rewards and how are abuse/repetition caps handled?
8. What happens on missed days, illness, timezone travel, accessibility needs, or system outage?
9. Is social comparison allowed? Real names, pseudonyms, cohort minimum, privacy, block/report, underage considerations?
10. What visual tone, character/mascot, sound, animation, color, and celebration intensity is preferred?
11. Must sound/motion be off by default or follow system preferences?
12. What reminders/nudges are acceptable, and how can users silence them?
13. What experiment/research design will establish that features help rather than pressure/distract learners?

Implementation:

- first build review queue from transparent, versioned spaced-practice policy and approved evidence;
- explain “why this review” and allow learner/teacher control;
- events use idempotent source IDs; reward ledger append-only; anti-abuse caps; no client-trusted XP;
- keep mastery/grade separate from motivation counters;
- opt-out/private mode, reduced-motion/no-sound, non-punitive copy, timezone grace/outage repair;
- leaderboard only with explicit scope/privacy/moderation and minimum cohort;
- feature flags/A-B experimentation only under approved research/consent and no dark patterns.

Acceptance:

- deterministic review schedule tests and teacher override;
- XP/streak cannot be inflated by preview/retry/replay;
- accessibility/preferences and timezone/outage tests;
- learner can disable optional competition/reminders;
- pilot measures learning and harm indicators; no “not boring” claim without data.

Rollback: disable mechanics without losing academic progress; reward ledger retained/archived per policy. Never lower grades/mastery.

### B15 — Audiovisual practice, audio templates, conversational simulation, AI coaching, peer/group learning

Priority: P1 for thesis experience, but decision-gated.  
Dependencies: B02/B03/B04/B06/B08/B09/B14; thesis conflict resolution.  
Addresses: NG-P1-017, NG-P2-029 and thesis-experience gaps.

Mandatory questionnaire:

1. Does the owner academically require AI/chatbot now, consider it optional, or explicitly defer it?
2. Which experience is required: scripted branching role-play, peer role-play, live instructor session, speech recording/playback, speech recognition, AI text coach, AI voice coach, video avatar, none?
3. Which exact pedagogical goals and hotel scenarios may each feature address?
4. Which AI provider/model/deployment is approved? If none is supplied, do not invent one.
5. May prompts/responses/account/class data leave the host? What fields, region, retention, training opt-out, subprocessors, and contract?
6. Are AI outputs formative suggestions only? Can they ever affect grade/mastery? Recommended: never without independently validated human-approved scoring.
7. How will hallucinated, unsafe, biased, culturally inappropriate, or non-source hotel advice be constrained/reported?
8. Must retrieval use only approved source content? Who reviews the knowledge base and citations?
9. What response retention is allowed? Current raw rehearsal text/audio is not persisted.
10. Is microphone/camera permitted, for which action, and can learners use an equivalent no-recording alternative?
11. Define audio/video formats, rights, captions, transcripts, audio descriptions, playback speed, download, and offline availability.
12. For spelling/listening templates, choose accommodation/reveal policy that does not disclose the answer; identify reviewer.
13. Is peer review/discussion/group role-play required? Define identity, matching, rubric, moderation, block/report, late/no-show, and privacy.
14. Who can review learner recordings/text and for how long?
15. What cost/rate limits/quotas/outage fallback apply?
16. What age/language/accent fairness testing is required?
17. Must every AI interaction visibly identify AI, limitations, sources, and human escalation?

Implementation order:

1. Non-AI audiovisual foundation: asset metadata, captions/transcripts, playback controls, accessible alternatives, privacy notice, explicit record action, upload scanning.
2. Scripted deterministic branching scenario using approved source content and explicit state; strong fallback that does not require AI.
3. Re-enable spelling/listening only after content + accessibility approval and answer-leakage tests.
4. Peer/group workflow only after class/moderation/privacy design.
5. AI adapter only after provider contract: schema-constrained output, source-grounded retrieval, prompt injection boundaries, allowlisted tools (prefer none), rate/cost limits, safety filters, human report/escalation, full non-AI fallback.
6. Never give the model database/tool authority to publish, change roles, grade, or expose other learners.
7. AI response is untrusted content; escape/render safely; store minimal audit (provider/model/version/request ID/policy result) without raw response unless approved.

Acceptance:

- media meets WCAG alternatives and rights review;
- no recording starts without user action/permission/notice;
- disabled audio templates remain disabled until signed review;
- deterministic scenario works when AI unavailable;
- red-team prompt injection/data exfiltration/harmful advice/cross-user context tests;
- no AI output changes score/grade/mastery unless a separately approved validated system exists;
- learner can report and use human/non-AI path;
- peer moderation/support capacity proven;
- thesis wording conflict resolved in an academic decision record.

Rollback: kill switch disables AI/recording and falls back to deterministic practice; preserve completed academic state; dispose queued/raw data per retention.

### B16 — Optional PWA/offline synchronization and LMS interoperability

Priority: P2/P3 optional.  
Dependencies: stable B06–B15 domain contracts.  
Addresses: NG-P2-030 and competitor integration patterns.

Mandatory questionnaire:

1. Is installable PWA/offline use required, or is responsive online web sufficient?
2. Which offline capabilities: read lessons, play downloaded media, practice, submit later, view assignments/calendar, author?
3. Which devices/browsers and storage constraints?
4. May sensitive content/answers/submissions be stored on-device? Encryption, shared-device logout/purge, retention?
5. How should conflicts resolve when content/assignment/attempt changed while offline?
6. What happens when an offline attempt passes due/close time?
7. Should media be user-selected downloads or automatic cache?
8. Is background sync allowed and how will users see pending/failed/sent state?
9. Is the standalone HTML retained, replaced, or kept as a non-synchronized export?
10. Is LTI 1.3 integration required? Identify platform(s), roles, Deep Linking, Names and Roles, Assignment and Grade Services needs.
11. Is SCORM/xAPI/cmi5 import/export required? State exact use case; do not implement standards “just in case.”
12. Who owns interoperability credentials, tenant registration, privacy mapping, and support?

Implementation:

PWA if approved:

- web app manifest, icons, service worker generated/versioned with build;
- cache only allowlisted public/versioned content; never cache auth HTML/API indiscriminately;
- encrypted/minimized offline store if approved; explicit download/purge/storage usage;
- outbox with idempotency keys, content/assignment version, server conflict response and user resolution;
- clear offline/pending/synced/error status; no silent grade/submit claims;
- service-worker update/migration/rollback and cache purge strategy.

LTI if approved:

- LTI 1.3/OIDC/OAuth2/JWT using maintained library; exact issuer/client/deployment/key registrations supplied by owner;
- Deep Linking for selecting immutable content/assignments;
- Names and Roles only minimal scope; AGS score passback with idempotency/audit;
- map platform context/resource link to Hospitrainity class/assignment explicitly;
- no role escalation from untrusted claims; issuer/deployment/audience/nonce/signature validation.

Acceptance:

- offline install/update/cache poisoning/logout/shared-device/quota/conflict/due-date tests;
- no unauthorized answer/private data in cache;
- sync is idempotent and truthful;
- standalone remains labeled session-only unless deliberately superseded;
- LTI conformance/security tests against approved sandbox; cross-tenant isolation and grade replay protection;
- feature may be omitted if no approved need.

Rollback: unregister service worker and purge versioned caches safely; keep server state authoritative. Revoke LTI keys/registration without losing local grades.

### B17 — Engineering refactor, performance, operations, pilot validation, thesis evidence, and launch gate

Priority: final stabilization/release.  
Dependencies: selected functional batches complete.  
Addresses: NG-P2-034, NG-P3-035–043, all UNVERIFIED production/research claims.

Mandatory questionnaire:

1. Which completed batches/features are included in the pilot/release?
2. Target production topology and named operators/owners?
3. Target database engine/version and expected users/classes/content/media/requests?
4. Availability/SLO, RPO/RTO, latency and page-weight budgets?
5. Supported browser/device/AT matrix?
6. Pilot institutions/classes, participant counts, recruitment/consent, and support plan?
7. Define success/failure/stopping criteria for learners, lecturers, accessibility, security, and learning outcomes.
8. Which thesis research instruments and analysis plan are approved by supervisor/ethics process?
9. Which telemetry is permitted and how is it minimized/retained?
10. Who performs hospitality/ESP, CEFR, content, accessibility, UX, privacy, security, and legal review?
11. What staged rollout/canary/maintenance window/rollback authority is approved?
12. Which refactors are authorized now versus deferred to avoid destabilizing release?
13. What launch communications/training/help are required for lecturers and students?
14. What post-launch monitoring/review cadence and incident thresholds apply?

Implementation:

Engineering:

- add PHPStan/Larastan baseline then eliminate risk-critical issues; architecture rules for layers/domains;
- characterize and split large services by parser/validator/reader/persistence/scoring/projection/query responsibility;
- move remaining queries from Blade; split routes into bounded files without changing names/middleware;
- SafeHtml type boundary; remove error suppression; review four unnamed routes;
- target-database migrations, indexes, query plans, row locks/concurrency and backup/restore;
- load/performance tests under approved volume; N+1 and page-size budgets; background job idempotency/observability;
- CI matrix with exact authority artifact, PHP/Node locks, strict tests, browser matrix, build, dependency audits, static analysis, canonical/compiler/brand verification, migration fresh/upgrade/rollback where safe;
- no broad framework rewrite.

Operations:

- production config/secrets, TLS/proxy, CSP/HSTS/cookies, DB/storage least privilege, queue/scheduler, mail, scanner, central logs/alerts, dashboards, backups and isolated restore;
- release/canary/migration/rollback/incident/data-request/exam/AI runbooks;
- deployment checker passes in the actual target environment;
- external penetration test and remediation/risk acceptance;
- SBOM/dependency ownership and update cadence.

Human validation:

- content/ESP/hospitality/CEFR/source/rights/link review;
- WCAG/manual AT/device audit and remediation;
- moderated first-use Learner and Lecturer studies covering navigation, class setup, authoring, assignment, grading, progress interpretation, announcement/schedule as selected;
- pilot learning/usability evidence under approved research design;
- record observed results, raw instrument references, analysis method and limitations; never manufacture Chapter IV findings.

Acceptance:

- fresh install and upgrade from current baseline both pass;
- production deployment checker, ASVS evidence, pen test, backup+private-storage restore, queue/scheduler/mail/scanner/log alert exercises pass;
- performance/error budgets pass at agreed load;
- all applicable HUMAN_REVIEW/LEGAL_REVIEW/PREFERENCE gates closed by named reviewers;
- pilot stopping criteria not triggered and owner approves launch;
- canonical release has coherent non-draft lifecycle;
- rollback rehearsal completes within RTO;
- final traceability registry maps every included finding/question to decision, code, test, human/production evidence.

Rollback: execute rehearsed application/database/storage rollback or forward fix according to data compatibility. Never restore database without coordinated private-storage state. Preserve audit/submission/grade/data-rights evidence.

## 14. Batch dependency graph and recommended checkpoint order

Strict default order:

B00 -> B01 -> B02 -> B03 -> B04 -> B05 -> B06 -> B07 -> B08 -> B09 -> B10 -> B11 -> B12 -> B13 -> B14 -> B15 -> B16 -> B17.

Permitted deferrals:

- B13 may be deferred entirely if no high-stakes exam is approved.
- B14 may omit all gamification and implement only transparent spaced review.
- B15 AI/peer/recording may be deferred; non-AI accessible media can proceed.
- B16 may be omitted if PWA/offline/interoperability has no approved use case.

Do not reorder these dependency-sensitive facts:

- B01 must contain enrollment/credential/lifecycle P0 before real users.
- B02 privacy rules precede collection-heavy announcements, analytics, recordings, AI, proctoring, and exports.
- B06 classes precede assignments, lecturer analytics, announcements, and course calendar.
- B06 establishes the single shared Clock/TimeContext/timezone foundation; B07 assignment dates, B11 scheduling/quiet hours, B12 calendar/reminders, and B13 exam timing consume it and must not create parallel time-resolution logic.
- B09 assessment semantics precede mastery analytics.
- B12 reliable time precedes B13 exam timing.
- B17 is the only launch gate and does not replace earlier human/legal decisions.

## 15. Current code evidence index

Line numbers are from the 2026-07-19 audited snapshot and may drift. Future AI must re-run rg and cite current lines.

| Evidence | Current location |
|---|---|
| Predictable demo password and pre-verification | database/seeders/UserSeeder.php:31–55 |
| Seeder called without production guard | database/seeders/DatabaseSeeder.php |
| Public distinct institution query | app/Http/Controllers/Auth/RegisterController.php:21–33 |
| Registration accepts any institution present in users | app/Http/Controllers/Auth/RegisterController.php:45–59 |
| Password uses project-unoverridden defaults | app/Http/Controllers/Auth/RegisterController.php:50; app/Http/Controllers/Auth/PasswordResetController.php:57 |
| Login throttle keyed only by email+IP | app/Providers/AppServiceProvider.php:45–49 |
| Microphone/camera denied by current header | app/Http/Middleware/SecurityHeaders.php:23 |
| Attempt row created before intent branch | app/Services/Curriculum/CurriculumAttemptService.php:48–82 |
| Participation always sets completed | app/Services/Curriculum/CurriculumAttemptService.php:97–138 |
| Administrative attempt count counts rows | app/Services/ProgressAdministrationService.php:85–97, 129–132, 261–265 |
| Learner attempt count counts rows | app/Services/CanonicalCurriculumRepository.php:366–371 |
| Show-model expected as started attempt in current test | tests/Feature/CanonicalAttemptTest.php:165–175 |
| Audit actor/target restrict user deletion | database/migrations/2026_07_17_000006_constrain_user_roles_and_create_administration_audits.php:39–40 |
| User has no soft-delete/account lifecycle | app/Models/User.php |
| DB queries in Blade | resources/views/superadmin/{exercises,lessons,materials,vocabularies,modules}/index.blade.php:3 |
| Guest login/register h2 | resources/views/login.blade.php:10; resources/views/register.blade.php:10 |
| No detected skip-link pattern | repository-wide resources/views search |
| Current admin privacy/capability contract | docs/decisions/ADM-0-administration-capability-matrix.md |
| Exercise template availability/scoring | docs/decisions/ADM-4-canonical-exercise-template-contract.md |
| Current progress field exclusions | docs/decisions/ADM-5-progress-administration-privacy-contract.md |
| Production checker | app/Console/Commands/CheckProductionReadiness.php; app/Services/ProductionReadinessChecker.php |
| Brand guard | app/Console/Commands/CheckActiveBrand.php; app/Services/Quality/ActiveBrandGuard.php |
| Source compiler | scripts/curriculum/compile.php and scripts/curriculum/verify.php |

## 16. Data and state design constraints

Future migrations MUST honor:

1. Use immutable UUID/ULID or existing stable identifier conventions for externally referenced domain objects.
2. Keep global user role separate from institution/course/class capabilities.
3. Use database foreign keys/indexes/unique constraints for invariants; policies remain required at request time.
4. Use explicit backed enums/value objects for finite states and one transition service per workflow.
5. Record timestamps as UTC instants and retain IANA timezone/author-local intent where schedules recur.
6. Bind assignments/submissions/grades to immutable content and policy versions.
7. Never overwrite historical score/grade calculation; append/revise with effective version and audit.
8. Do not store derived aggregate as sole truth. Keep rebuild/reconciliation path.
9. Use outbox/idempotency for email/push/LTI/background synchronization.
10. Use optimistic locking/revision on editable drafts, assignments, grades/rubrics, announcements, and schedules where simultaneous edits matter.
11. Treat deletion as an orchestrated state machine, not controller cascades.
12. Avoid polymorphic free-form JSON for security-critical scope/state. JSON payloads must have versioned schema, allowlisted keys, size/depth limits, and typed access.
13. Every audit/event schema has minimization, retention, actor/target pseudonymization behavior, and tamper/backup policy.
14. Any counter cached for performance must reconcile to source rows and define invalidation.
15. Migrations use expand/backfill/verify/switch/contract. Destructive contract runs only after rollback window and backup+restore proof.

## 17. API, service, policy, and UI technique guidance

### 17.1 Server/application

- Thin controllers: authorize, validate, invoke a use case, map result/redirect.
- FormRequests reject unknown nested keys and enforce bounded cardinality.
- Application services own transactions and idempotency; domain policies/state machines own legal transitions.
- Query objects/read models own policy-scoped reporting; never query in Blade or loop per row.
- Events distinguish facts from commands; use an outbox for external delivery.
- Files use private storage abstractions; never trust extension/client MIME/path.
- Generated HTML uses escaped data or an immutable SafeHtml produced only by the reviewed renderer.
- Keep framework defaults centralized and explicit for passwords, URLs, locales, time, pagination and upload bounds.
- Prefer maintained Laravel/native mechanisms; justify third-party package, version, license, maintenance/security posture, and lockfile change.

### 17.2 Front end

- Server-rendered native HTML remains the baseline; progressive enhancement must leave core work usable.
- Use buttons for actions and links for navigation.
- Use fieldset/legend for grouped choices, persistent labels, autocomplete/inputmode, error association and focus.
- Do not build custom ARIA widgets where a native element works.
- Ordering/drag always has named keyboard buttons.
- Client validation is convenience; server is authoritative.
- Client timer/score/progress/role/visibility cannot be trusted.
- Use content-visibility/virtualization only when focus, find-in-page, print and screen-reader behavior are proven.
- Never use color, icon, animation or sound as the only status channel.
- Respect prefers-reduced-motion and media/accessibility preferences.

### 17.3 Testing

- Unit/property tests: score/grade/time/rule state machines and normalization.
- Feature/integration tests: full role matrix, policies, direct-ID, validation, transactions, jobs, audits, migrations.
- Browser E2E: critical role journeys with disposable database and built assets, not stale Vite hot server.
- Accessibility: markup regression plus human keyboard/zoom/screen-reader/device matrix.
- Security: abuse cases, authorization regression, upload malicious structures, session/MFA/recovery, CSP, SSRF/XSS/CSRF/injection as relevant.
- Data migration: production-shaped sanitized fixture, pre/post counts, foreign keys, duplicate/orphan handling, rollback/forward fix.
- Target database: lock/concurrency and query plans; SQLite success cannot prove MySQL/PostgreSQL semantics.
- Performance: fixed query count, pagination bounds, background job throughput, page weight and web vitals under approved device/network.
- Recovery: process kill/timeouts/retries/idempotency, queue/scanner/mail outage, backup and coordinated storage restore.

## 18. Requirement-to-batch traceability

### 18.1 Owner-requested outcome coverage

| Requested outcome | Primary batch(es) |
|---|---|
| Thesis alignment | B08, B09, B14, B15, B17 |
| Mobile ready/responsive | B04; optional B16 |
| Clear value/CTA and easy navigation | B05 |
| Robust security | B01, B02, B03, B17 |
| Accessibility | B04 plus acceptance in every later UI batch |
| User friendly/engaging/not boring | B05, B08, B14, B15, B17 |
| Lecturer content/exercise management | B06, B08 |
| Lecturer scoring | B09 |
| Lecturer learning/task insight | B10 |
| Directed/required modules/tasks | B07 |
| Multimedia announcements | B11 |
| Timer/schedule/stopwatch | B12; graded timer B13 |
| Safe exam | B13 |
| User deletion and staff evidence | B02 |
| Institution integrity | B01, B06 |
| Role promotion/admin authority | Already implemented ADM-1; preserve and re-test B03/B06/B17 |
| Bad-code cleanup | B08 characterization; B17 focused refactor |
| AI/role-play/audio/peer thesis experiences | B15 |
| Offline/PWA/interoperability | Optional B16 |

### 18.2 Finding-level closure traceability

This table is the roadmap-level baseline. The current-evidence source for every row is its complete section 10 finding plus the section 15 code evidence index where applicable. B00 must transfer these rows to the machine-readable registry and add the exact decision, code, migration, automated test, browser/device check, human/legal review, production evidence, and checkpoint artifact as each batch executes. A finding is not closed merely because its batch changed code.

| Finding | Owning batch(es) | Owner requirement/question | Controlling decision record | Minimum closure evidence |
|---|---|---|---|---|
| NG-P0-001 | B01; recheck B03/B17 | Robust security; safe production operation | NG-B01-DECISIONS | Production seed fails before writes; no runtime literal credential; approved bootstrap/recovery runbook and tests |
| NG-P0-002 | B01; scoped migration B06 | Institution integrity; role/scope safety | NG-B01-DECISIONS; NG-B06-DECISIONS | Non-enumerating registration; approved invitation/enrollment proof; migration mapping; cross-institution tests |
| NG-P0-003 | B01; release gate B17 | Thesis/source fidelity and truthful release | NG-B01-DECISIONS; applicable CF approval records | Human approval set; immutable non-draft active release; activation/rollback evidence |
| NG-P0-004 | B02 | Question 18; public trust/privacy | NG-B02-DECISIONS plus legal approval | Field-retention matrix; rights request/export/delete/hold tests; pseudonymized retained audit; legal sign-off |
| NG-P0-005 | B00 | Bad-code/change safety; AI-executable history | NG-B00-DECISIONS | Usable HEAD/authoritative history; clean checkout; baseline hash; secret/private-artifact exclusion; reviewable checkpoint diff |
| NG-P1-006 | B09; reporting recheck B10 | Question 13 | NG-B09-DECISIONS; NG-B10-DECISIONS | Preview/started/submitted taxonomy; deterministic historical mapping; counts and role views tested |
| NG-P1-007 | B09; analytics B10 | Questions 1, 12, 13 | NG-B09-DECISIONS; NG-B10-DECISIONS | Participation/completion/pass/mastery definitions and versioned calculations; incorrect participation never shown as mastery |
| NG-P1-008 | B08/B09 | Questions 11–12; thesis/source fidelity | NG-B08-DECISIONS; NG-B09-DECISIONS plus content review | Activity/reveal classification; protected-answer tests; exact-source coverage and reviewer approval |
| NG-P1-009 | B06 | Questions 11, 13–16 | NG-B06-DECISIONS | Normalized scoped domains; enrollment/teaching policy matrix; cross-class/direct-ID/history tests |
| NG-P1-010 | B07 | Questions 14 and 16 | NG-B07-DECISIONS | Versioned assignment/rule states; directed-task journey; prerequisite/timezone/exception/concurrency tests |
| NG-P1-011 | B09 | Question 12 | NG-B09-DECISIONS | Approved versioned grading semantics; deterministic calculation; manual/open queue; override/release/audit tests |
| NG-P1-012 | B10 | Question 13 | NG-B10-DECISIONS | Metric dictionary; purpose/scoped policy; learner/lecturer parity; actionable status and truthful denominator tests |
| NG-P1-013 | B03 | Question 5 | NG-B03-DECISIONS | Approved password/throttle/MFA/recovery controls; abuse, session, step-up, accessibility, and audit evidence |
| NG-P1-014 | B03; production closure B17 | Question 5 | NG-B03-DECISIONS; NG-B17-DECISIONS | ASVS traceability; event/alert exercises; secrets/least privilege; restore and independent assessment evidence |
| NG-P1-015 | B03 | Questions 5, 11, 15 | NG-B03-DECISIONS | Approved scanner/CDR policy; quarantine/promotion; unavailable/positive/negative/re-scan and disposal tests |
| NG-P1-016 | B04 | Questions 2, 3, 6, 7, 10, 11 | NG-B04-DECISIONS | Automated regression plus human keyboard, zoom/reflow, focus, target, error, screen-reader, and device evidence |
| NG-P1-017 | B15 | Questions 1, 6, 8, 11 | NG-B15-DECISIONS plus content/accessibility review | Approved audio/accommodation contract; no answer leakage; consent/permission/storage tests; qualified reviews |
| NG-P2-018 | B05/B08 | Questions 4, 7, 10, 11 | NG-B05-DECISIONS; NG-B08-DECISIONS | Plain-language task-first UI; permission-aware evidence view; ordinary workflow usability evidence |
| NG-P2-019 | B05/B06 | Questions 7, 10, 11 | NG-B05-DECISIONS; NG-B06-DECISIONS | Accurate role/scope context on every admin surface; scope-switch and stale/unknown-context tests |
| NG-P2-020 | B04 | Questions 2, 3, 6, 7, 11 | NG-B04-DECISIONS | Approved responsive navigation/table alternative; narrow/physical-device, keyboard, focus, target, and no-overflow evidence |
| NG-P2-021 | B04/B05; data view B10 | Questions 2, 3, 7, 10, 13 | NG-B04-DECISIONS; NG-B05-DECISIONS; NG-B10-DECISIONS | Approved bounded summary/filter/navigation pattern; truthful totals; focus/history/query-count evidence |
| NG-P2-022 | B05; validation B17 | Questions 7 and 10 | NG-B05-DECISIONS; NG-B17-DECISIONS | Approved onboarding/help/search journey; accessible recovery; observed first-use evidence with limitations |
| NG-P2-023 | B02/B05 | Questions 4, 6, 7, 10, 18 | NG-B02-DECISIONS; NG-B05-DECISIONS plus legal approval | Versioned accessible trust/support destinations; registration linkage/version evidence; link/locale/keyboard tests |
| NG-P2-024 | B05 | Questions 4, 7, 10 | NG-B05-DECISIONS | Deterministic authorized next-action rules; empty/recovery states; no false/unavailable CTA |
| NG-P2-025 | B11 | Question 15 | NG-B11-DECISIONS | Approved post/media/audience/moderation/delivery states; authorization, leakage, upload, idempotency, retention tests |
| NG-P2-026 | B06 foundation; B07/B11 consumers; B12 capability; B13 graded timing | Question 16 | NG-B06-DECISIONS; NG-B07-DECISIONS; NG-B11-DECISIONS; NG-B12-DECISIONS; NG-B13-DECISIONS | One shared Clock/TimeContext; approved timezone precedence; DST/skew/retry/reschedule/accommodation and cross-scope tests |
| NG-P2-027 | B13 | Question 17 | NG-B13-DECISIONS plus legal/accessibility approval if required | Tier-accurate claims; timed/recovery/accommodation integrity tests; verified client/service evidence only if selected |
| NG-P2-028 | B14 | Questions 8–9 | NG-B14-DECISIONS plus learning/research review | Explainable review scheduler; opt-out/reset/accessibility tests; motivation distinct from grades; approved claims only |
| NG-P2-029 | B15 | Questions 1, 8–9, 11, 13 | NG-B15-DECISIONS plus privacy/legal/content/moderation approvals | Selected-tier contracts; attribution; scoped access; provider/outage/unsafe-output/retention/moderation tests |
| NG-P2-030 | Optional B16 | Questions 2–3 and mobile readiness | NG-B16-DECISIONS | Approved offline boundary; cache/update/conflict/logout/quota tests; idempotent sync; truthful availability CTA |
| NG-P2-031 | B04/B05/B08; human closure B17 | Questions 1, 6, 7, 10, 11 | NG-B04-DECISIONS; NG-B05-DECISIONS; NG-B08-DECISIONS | Approved localization/pedagogy boundary; no raw ordinary-UI enum; fallback, bilingual layout, screen-reader, fidelity review |
| NG-P2-032 | B08/B09 | Questions 1, 6, 11, 12 | NG-B08-DECISIONS; NG-B09-DECISIONS plus content review | Single interactive option presentation; accessible labels; deterministic compiler/source coverage; reviewer approval |
| NG-P2-033 | B04 | Questions 2, 3 and 6 | NG-B04-DECISIONS | Non-shrinking semantic controls; target-size, narrow width, zoom, keyboard, screen-reader, contrast, touch evidence |
| NG-P2-034 | B05 study design; B17 execution | Questions 7–11 and 13 | NG-B05-DECISIONS; NG-B17-DECISIONS plus supervisor/ethics approval | Approved non-assumed sample/design; raw permitted evidence; reproducible analysis; limitations/adverse findings retained |
| NG-P3-035 | B08/B17 | Bad-code audit; lecturer maintainability | NG-B08-DECISIONS; NG-B17-DECISIONS | No database query in affected views; equivalent role/render behavior; bounded query-count tests |
| NG-P3-036 | B08 characterization; B17 refactor | Bad-code audit and room for improvement | NG-B08-DECISIONS; NG-B17-DECISIONS | Before/after characterization; bounded responsibilities; stable contracts, hashes, policies, transactions, and failure behavior |
| NG-P3-037 | B08/B17 | Source fidelity and bad-code audit | NG-B08-DECISIONS; NG-B17-DECISIONS plus retention/provenance decision | Legacy read-only/isolation tests; accurate labeling; approved retirement/export criteria before deletion |
| NG-P3-038 | B08/B17 | Security, source fidelity, bad-code audit | NG-B08-DECISIONS; NG-B17-DECISIONS | Typed safe-render boundary; malicious-input suite; unchanged faithful formatting/determinism; unauthorized raw path check |
| NG-P3-039 | B08/B17 | Security and bad-code audit | NG-B08-DECISIONS; NG-B17-DECISIONS | Typed image-validation failures; malformed/polyglot/oversize tests; no warning/path leakage; fail-closed promotion |
| NG-P3-040 | B17 | Bad-code audit and robust security | NG-B17-DECISIONS | Pinned static-analysis gate and ratchet; architecture rules; risk-critical branch/mutation evidence |
| NG-P3-041 | B17 | Bad-code consistency audit | NG-B17-DECISIONS | Ownership/intent recorded for all unnamed routes; approved application names; stable middleware/authorization/vendor behavior |
| NG-P3-042 | B00/B17 | Source fidelity, robust security, production readiness | NG-B00-DECISIONS; NG-B17-DECISIONS | Exact-hash protected authority release lane; target-database migration/query/lock/concurrency lane; no critical skip |
| NG-P3-043 | B08/B17 | Bad-code audit; lecturer/admin maintainability and accessibility | NG-B08-DECISIONS; NG-B17-DECISIONS | Stable route snapshots; accessible component variants; unchanged server authorization; only proven-shared duplication removed |

## 19. Primary research registry

Accessed/rechecked 2026-07-19. Use the current official version at implementation time.

### Accessibility and web

- W3C WCAG 2.2 Recommendation: https://www.w3.org/TR/WCAG22/
- W3C target size minimum understanding: https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum
- W3C WAI forms tutorials: https://www.w3.org/WAI/tutorials/forms/
- HTML Living Standard forms: https://html.spec.whatwg.org/multipage/forms.html

Key use: WCAG 2.2 AA, 320 CSS px reflow, focus not obscured, 24×24 CSS px minimum target subject to normative exceptions, accessible authentication, media alternatives, native semantics.

### Security, privacy, identity, uploads

- OWASP ASVS 5.0.0: https://owasp.org/www-project-application-security-verification-standard/
- OWASP Authentication Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html
- OWASP Authorization Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html
- OWASP File Upload Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html
- OWASP Logging Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html
- NIST SP 800-63B-4: https://pages.nist.gov/800-63-4/sp800-63b.html
- NIST authenticator/password requirements: https://pages.nist.gov/800-63-4/sp800-63b/authenticators/
- Indonesian Law No. 27 of 2022 on Personal Data Protection, official JDIH entry: https://jdih.komdigi.go.id/produk_hukum/view/id/832/t/undangundang%2Bnomor%2B27%2Btahun%2B2022

Key use: ASVS is a verification baseline, not an automatic compliance badge. NIST recommends a 15-character minimum for a password used as a single factor, a common/expected/compromised blocklist, no arbitrary composition rule, throttling, and phishing-resistant cryptographic authentication where required. Indonesian data-rights/retention implementation requires qualified legal interpretation; the official law includes deletion/destruction and notification duties, but this roadmap does not provide legal advice.

### Exam integrity and interoperability

- Safe Exam Browser overview/architecture: https://safeexambrowser.org/about_overview_en.html
- Open edX timed exams: https://docs.openedx.org/en/latest/educators/concepts/advanced_features/about_timed_exams.html
- Open edX proctored exams: https://docs.openedx.org/en/latest/educators/concepts/proctored_exams/about_proctored_exams.html
- 1EdTech LTI 1.3 adoption/security/services: https://www.1edtech.org/standards/lti/why-adopt-lti-1p3

Key use: a lockdown exam requires local kiosk/client or proctor service beyond an ordinary page. Timed exams need extensions/accommodations and recovery. LTI 1.3 uses modern OIDC/OAuth2/JWT patterns and optional Deep Linking, Names and Roles, and Assignment and Grade services.

### Competitor official sources

- Udemy Business plans/features: https://business.udemy.com/plans/
- Udemy Business analytics: https://business.udemy.com/analytics/
- iSpring product training/features: https://www.ispringsolutions.com/support/product-training
- iSpring roles: https://www.ispringsolutions.com/articles/user-roles-in-ispring-lms
- LinkedIn Learning product overview: https://business.linkedin.com/learn/product-overview
- Coursera Business plan comparison: https://www.coursera.org/business/compare-plans
- Open edX educator documentation: https://docs.openedx.org/en/latest/educators/index.html
- Open edX course discussions: https://docs.openedx.org/en/latest/educators/concepts/communication/about_course_discussions.html
- Khan Academy teacher reporting: https://support.khanacademy.org/hc/en-us/articles/360031129891-What-reporting-options-are-available-on-Khan-Academy-for-teachers-to-track-student-performance
- Thinkific Course Builder: https://support.thinkific.com/hc/en-us/articles/360030371754-The-Thinkific-Course-Builder
- Canvas Instructor Guide index/current community: https://community.instructure.com/
- Canvas announcements example: https://community.instructure.com/en/kb/articles/660643-how-do-i-add-an-announcement-in-a-course
- Blackboard assessments: https://help.anthology.com/blackboard/instructor/en/assessments.html
- Blackboard grading/submission states: https://help.anthology.com/blackboard/instructor/en/grading/grading-assessments/access-submissions-to-grade.html
- Blackboard grade columns/calculations: https://help.anthology.com/blackboard/instructor/en/grading/grading-setup/grade-columns.html
- Duolingo classroom assignments: https://blog.duolingo.com/using-duolingo-in-the-classroom/
- Duolingo learning/personalization/gamification: https://blog.duolingo.com/duolingo-101-how-to-learn-a-language-on-duolingo/

Competitor marketing claims are vendor claims, not independent proof. Adopt interaction patterns only after Hospitrainity user research and owner questionnaire.

### Current framework references

- Laravel 12 authorization: https://laravel.com/docs/12.x/authorization
- Laravel 12 authentication/password confirmation: https://laravel.com/docs/12.x/authentication
- Laravel 12 validation/nested arrays/files: https://laravel.com/docs/12.x/validation
- Laravel 12 database transactions: https://laravel.com/docs/12.x/database#database-transactions
- Laravel 12 queues: https://laravel.com/docs/12.x/queues
- Laravel 12 deployment: https://laravel.com/docs/12.x/deployment
- Laravel 12 migrations and isolation: https://laravel.com/docs/12.x/migrations

Future AI must verify the installed Laravel minor version and current documentation before using an API.

## 20. Reproducible baseline commands

Run from the project root in PowerShell. Adjust executable paths only when proven different. Commands that write/build/test must use a disposable test environment and approved permissions.

Authority hashes:

    Get-FileHash -Algorithm SHA256 -LiteralPath 'C:\Users\dhion\Desktop\Documents\000 - Thesis Dhion Setio\Revisi 30 Juni 2026\Dhion Setio - Thesis Revise 7-18.docx'
    Get-FileHash -Algorithm SHA256 -LiteralPath 'C:\Users\dhion\Desktop\Documents\000 - Thesis Dhion Setio\Revisi 30 Juni 2026\Learning Materials - Fixed\Hospitrainity.docx'

Environment/routes:

    & 'C:\php\php.exe' artisan about
    & 'C:\php\php.exe' artisan route:list --json
    & 'C:\php\php.exe' artisan migrate:status

Source/canonical/brand:

    & 'C:\php\php.exe' scripts\curriculum\verify.php --source 'C:\Users\dhion\Desktop\Documents\000 - Thesis Dhion Setio\Revisi 30 Juni 2026\Learning Materials - Fixed\Hospitrainity.docx' --baseline curriculum\hospitrainity\0.3.0-draft
    & 'C:\php\php.exe' artisan hospitrainity:curriculum verify
    & 'C:\php\php.exe' artisan hospitrainity:brand-guard

Strict regression:

    & 'C:\php\php.exe' artisan test --display-all-issues --fail-on-all-issues --disallow-test-output
    & 'C:\Program Files\nodejs\npm.cmd' run lint:js
    & 'C:\Program Files\nodejs\npm.cmd' run test:js
    & 'C:\Program Files\nodejs\npm.cmd' run test:e2e
    & 'C:\Program Files\nodejs\npm.cmd' run build
    & 'C:\php\php.exe' vendor\bin\pint --test

Advisories and production readiness:

    & 'C:\Program Files\nodejs\npm.cmd' audit --json
    & 'C:\php\php.exe' 'C:\composer\composer.phar' audit --format=json
    & 'C:\php\php.exe' artisan hospitrainity:deployment-check --json

Targeted searches:

    rg -n --hidden -i 'stay[ _.-]*ready|stay\s*<[^>]+>\s*ready' app resources routes config database standalone public tests curriculum docs CHANGELOG.md .env.example --glob '!vendor/**' --glob '!node_modules/**' --glob '!.git/**' --glob '!storage/framework/**' --glob '!tmp/**'
    rg -n 'password|PasswordRule::defaults|RateLimiter|Permissions-Policy' app config routes database tests
    rg -n 'attempt_count|show_model|participation_rule_satisfied|completion_reason' app resources tests
    rg -n 'DB::|::query\(|::where\(|::all\(' resources\views --glob '*.blade.php'
    rg -n 'SoftDeletes|delete.*account|data.*delet|restrictOnDelete' app database routes resources tests
    rg -n 'manifest|serviceWorker|webmanifest|beforeinstallprompt|offline' app public resources routes config package.json vite.config.js --glob '!public/build/**'

Temporary local/browser checks:

    Get-NetTCPConnection -LocalAddress 127.0.0.1 -LocalPort 8000,8010,8011 -State Listen -ErrorAction SilentlyContinue

Never assume a port belongs to this task. Resolve PID/process/command line before stopping it. Use isolated E2E database and explicitly close server/browser state.

## 21. Definition of complete

The roadmap is not complete merely because every batch has code. Completion requires:

1. No P0 release blocker open.
2. Exact-hash authority and canonical determinism continue to pass.
3. A coherent non-draft canonical release has named human approvals.
4. Every included account/role/course capability is server-authorized and matrix-tested.
5. Lecturers can create/assemble approved content, exercises and answers, assign work, configure approved scoring, grade open work, and understand learner status through a usability-validated workflow.
6. Learners can understand what to do next, distinguish practice/completion/score/mastery, access feedback, manage notifications/privacy rights, and use the selected device/accessibility paths.
7. Announcements, schedule, exam, engagement, AI, PWA, and interoperability are either implemented under their decisions or explicitly deferred without false CTA/claim.
8. Data rights, retention, deletion and audit behavior have qualified legal/privacy approval.
9. WCAG 2.2 AA target has human evidence on the approved browser/AT/device matrix; open defects are release-blocked.
10. ASVS 5.0.0 target, production checker, dependency/static/dynamic tests, independent penetration test, logging/alerts, scanner, secrets, least privilege and incident procedures are evidenced.
11. Database and private storage backup/restore, migration, rollback and disaster paths are rehearsed.
12. Real intended learners and lecturers complete the approved pilot/usability tasks; results and limitations are recorded truthfully for thesis Chapter IV.
13. A clean checkout with tracked history can reproduce build/tests/release evidence.

## 22. Copyable handoff prompt for a new Codex task

Use this prompt only with both authority DOCX files attached/provided:

    Read AGENTS.md, this roadmap, docs/checkpoints/NG-B06-C-CLASS-LEARNING-CUTOVER-2026-07-22.md, docs/checkpoints/NG-AUTHORITY-CANDIDATE-2026-07-22.md, and docs/checkpoints/NG-CONSOLIDATED-TESTER-UPDATE-2026-07-22.md. Preserve the dirty working tree and do not restart B00 through B06 or send routine B07 through B17 questionnaires. Confirm the thesis hash BA64B2EB69673E84FC83E21D4617CD111691EE6340FC2F4331948FE6AF5EC132, active learning-authority hash 7F8A2C62DB6884498F7A1C2DE56E79E39609262944A685FE7EB97F702444CBB4, and replacement-candidate hash 5DE098DCDCC6405093004F6253A1DEF2F5DC4D85BC1CCACF1416376F3A77569B. The consolidated tester implementation is locally complete: 389 Laravel tests with 5,931 assertions passed; 60 Playwright journeys passed across desktop Chromium, desktop WebKit, mobile Chromium, and mobile WebKit; and 31 Python Chromium visual checks passed. Firefox failed during browser launch before application navigation, so 14 Firefox journeys remain unverified. Treat the v0.8 candidate as verified but not runtime-active. Do not create an adapter or promote content by inventing the missing activity hierarchy, answer or feedback models, response-group mapping, block provenance, outcome classifications, visibility behavior, lifecycle state, or release evidence. No external DOCX replacement, authority-config switch, active-package mutation, main-database migration, or production deployment has occurred. Begin read-only, inspect the requested scope, and avoid repeating completed work. If asked to activate v0.8, first record a reviewed canonical-contract decision for all eight incompatibilities. If asked to release, reproduce Firefox on a compatible host and treat migration, production configuration, deployment, specialist review, assistive-technology review, physical-device testing, and security testing as separate evidence gates. Root remains the only owner of shared routes, config, migrations, lockfiles, importer, governance hashes, external authority writes, active-package selection, main-database migration, and deployment. Do not claim independent academic, CEFR, accessibility, security, legal, production, physical-device, Firefox, or all-browser validation without evidence.

End of roadmap.

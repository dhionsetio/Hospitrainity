# NG-B05 checkpoint — guided first use, search, Help, and next actions

- Status: local technical implementation complete and installed on 2026-07-20; moderated intended-user validation remains B17.
- Branch: `codex/ng-b05-onboarding-search-help`
- Parent checkpoint: `d16b87c` (`feat: add accessible responsive interaction shell`)
- Authority sources: unchanged thesis and learning-material hashes recorded by B00.

## Owner decisions applied

The owner approved the B05 recommendations: learners, instructors/institution supervisors, and educational institutions are the primary audiences; unsupported employer and outcome claims remain absent; the public entry uses evidence-bounded actions; the first-use sequence is short, role-aware, skippable, resumable, and restartable; optional confidence is self-reflection rather than a diagnostic; ordinary pages lead with task/status/next action while authorized exact evidence remains discoverable; global search is limited to published learning, Help, and glossary content; Help has no unapproved public personal email; onboarding state is stored without behavioral analytics; and the future human study uses two formative moderated rounds with six intended learners and four instructor/supervisor/admin participants.

## Implemented

- Public navigation now leads to truthful learning, invitation, Help, privacy, accessibility, and sign-in destinations without promising a public sample, class, employer workflow, AI tutor, guaranteed outcome, or support channel that does not exist.
- Versioned bilingual-ready Help contains seven role/task topics, a ten-term glossary in each supported locale, contextual invitation/recovery guidance, current limitations, and an evidence-bounded About page. Help is public and consistently linked; authenticated search and onboarding remain subject to the current role/security controls.
- Every active work role has a three-step getting-started sequence. A read does not create state; only explicit advance, skip, or restart writes a state row. State is separate per user and work role, stale submissions fail safely, and no event/behavior stream is collected.
- The learner dashboard resolves the next action from current authorized facts: getting started, the most recently started incomplete activity, the first incomplete published module, optional confidence review after completion, or a truthful empty state. Institution Supervisor resolves to current-institution learner progress or invitation setup. Content/System Admin resolves to review work, editable work, or the canonical content workspace.
- Technical package codes, outcomes, hashes, lifecycle details, and legacy counts remain available to authorized users through named disclosure/evidence views instead of dominating the ordinary first view.
- The portable indexed search uses immutable generation records plus an atomic active-generation switch. It indexes only active published modules, sections, vocabulary, accessible activities, Help, and glossary entries; drafts, prompt items, answer models, user records, and private responses are excluded. Queries are token-bounded, role/audience filtered, paginated, and escaped by Blade output.
- Search rebuilding is wired into canonical import/no-change/rollback and active-release lifecycle transitions, seeding, deployment instructions, and the production-readiness gate. Old generations are retained; none was deleted.
- Authenticated navigation exposes Search, Getting started, and Help while preserving the approved rule that a learner-only account has no redundant role switcher.
- The approved B05 study design is recorded at `docs/research/NG-B05-FORMATIVE-FIRST-USE-PROTOCOL.md`. It defines consent, data minimization, role-specific tasks, assistance codes, severity, two-round gates, environment recording, adverse-finding retention, and claim limitations. No participant result is invented or claimed.

## Authoritative database migration

Preflight on `database/database.sqlite` reported SQLite integrity `ok`, zero foreign-key violations, 29 migrations, three users, all three configured demo identities present and enabled, 1,015 canonical entities, 1,216 canonical links, four canonical attempts, four canonical progress rows, one legacy completion, two institutions, and one institution membership.

Before migration, the database was copied to:

- `storage/app/backup-snapshots/b05-first-use-search-20260720-175223.sqlite`
- Size: 4,329,472 bytes
- SHA-256: `2285f58e28876bc4d6df5f26c88da0ea3074cee9d418249eac08a323e48b8895`

Migration `2026_07_20_000017_create_onboarding_and_search_foundation.php` ran as batch 16. The first authoritative rebuild activated generation `019f7f28-052a-7015-a0da-3982a9b86b53` with source fingerprint `79fb772098ffd176fe43ef27b1ef9a713f370816ec93c2d855baf7c57f33be91`.

Postflight reported:

- SQLite integrity `ok` and zero foreign-key violations;
- exactly one active search generation, with its declared count matching 223 stored documents and 10,511 weighted term rows;
- 25 activity, 20 glossary, 14 Help, 7 module, 85 section, and 72 vocabulary documents;
- 206 English and 17 Indonesian documents, all marked published;
- zero onboarding-state rows, so no user was silently started, skipped, or completed;
- every pre-existing business count unchanged, including users, institutions, memberships, curriculum, progress, attempts, responses, completions, sessions, and security/privacy records;
- all three configured demo accounts still present and enabled;
- zero authoritative business rows deleted.

The postflight database remained 4,329,472 bytes with SHA-256 `56e0cbf3875a699f41353bc414cdde4b09065769830e245df65454c10a26adf8` at the recorded postflight. A live database hash can later change through normal session/cache-related writes; the pre-migration backup hash is the immutable recovery reference.

## Verification

- Formatting/static analysis: Pint passed for the dirty set; PHPStan passed with no errors using an explicit 512 MB CLI ceiling; ESLint passed with zero warnings; EN/ID JSON decoding and key parity passed. One concurrent PHPStan attempt exhausted its configured 128 MB worker limit; the isolated final run completed normally and is the claimed result.
- Laravel: 335 tests / 5,307 assertions passed in 264.51 seconds, including onboarding lifecycle, Help/versioning, published-only and fail-closed audience search, role next actions, index plans, and production-readiness behavior.
- JavaScript: the complete sequential Node suite passed 50/50 tests. A prior concurrent multi-toolchain run produced one isolated timing failure; the affected file and the complete sequential suite both passed on rerun, so no application failure is claimed from that orchestration artifact.
- Build/supply chain: Vite 6.4.3 production build passed; npm audit reported zero vulnerabilities; Composer audit reported zero advisories and zero abandoned packages.
- Browser: the clean fresh supported-engine run at `storage/framework/testing/e2e-b05-supported-20260720-1745` passed 52/52 critical journeys across desktop Chromium, desktop WebKit, Pixel 7/mobile Chromium, and iPhone 15/mobile WebKit. The matrix includes public Help/glossary/About, onboarding skip/resume/restart, published-only search/no-result recovery, learner and staff journeys, accessibility interaction contracts, and legacy read-only access.
- Firefox: the retained run at `storage/framework/testing/e2e-b05-20260720-1735` failed before the first application navigation because the host Firefox process reported `RenderCompositorSWGL failed mapping default framebuffer` and the launch timed out after 180 seconds. Disabling the documented WebRender software/fallback preferences reproduced the same startup failure and was reverted. This is a host browser-startup gap, not Firefox application evidence.
- Governance: all 43 roadmap findings remain mapped; NG-P2-024 is technically complete. Human validation keeps NG-P2-018, 019, 021, 022, 023, 031, and 034 appropriately open/in progress rather than overstated.

## Evidence boundary and remaining gates

The green automated/browser matrix proves the tested software contracts, not unaided human comprehension, branded-browser compatibility, physical-device behavior, WCAG conformance, engagement, learning effectiveness, legal approval, or production readiness. B17 must execute the approved study under the required supervisor/ethics process and retain raw permitted observations, assistance, adverse findings, environment details, analysis, and limitations. Branded Chrome, Safari, Edge, Firefox, and Opera on the target Windows, macOS, Android, and iOS matrix remain external validation work.

B06 still owns institution/course/class/cohort/assignment structure. B08 owns deeper lecturer authoring usability and source/evidence boundaries. B10 owns broader bounded data views and truthful aggregate totals. No B05 CTA or Help copy represents those deferred capabilities as available.

## Rollback

Public copy and navigation can be forward-fixed without database rollback. The migration is additive, and its `down()` path targets only the four new B05 tables. After real onboarding state or additional search generations exist, prefer a forward fix or export those new records before any rollback. Do not restore the database merely to change copy or navigation. The recorded backup supports a controlled local rejection only after comparing all post-backup writes.

## Primary technical and research references

- [Laravel 12 migrations](https://laravel.com/docs/12.x/migrations): schema indexes and additive migration behavior.
- [Laravel 12 search](https://laravel.com/docs/12.x/search): database full-text limitations that informed the portable indexed implementation.
- [Laravel 12 pagination](https://laravel.com/docs/12.x/pagination): bounded result navigation.
- [W3C WCAG 2.2 — Consistent Help](https://www.w3.org/WAI/WCAG22/Understanding/consistent-help.html): consistent Help placement.
- [GOV.UK Service Manual — Planning user research](https://www.gov.uk/service-manual/user-research/plan-round-of-user-research): iterative formative research planning.
- [Mozilla Firefox source — SWGL framebuffer failure](https://searchfox.org/firefox-main/source/gfx/webrender_bindings/RenderCompositorSWGL.cpp): source location for the exact retained host-startup diagnostic.

## Exact next batch

B06 — institution, course, class/cohort, enrollment, and scoped lecturer roles. Begin with the B06 preference questionnaire; do not assume class, course, assignment, grading, or institution-management features beyond the installed membership foundation.

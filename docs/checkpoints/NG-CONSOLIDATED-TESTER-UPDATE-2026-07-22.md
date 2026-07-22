# Consolidated tester update checkpoint

- Saved: 2026-07-22 (Asia/Jakarta)
- Last fully closed domain checkpoint: B06-C
- Current state: consolidated tester implementation, local database integration, local tester access, and bilingual translation repair are complete; Firefox browser launch remains blocked by the local software-renderer runtime
- Authority activation: unchanged
- Main database mutation: additive migrations `000019` through `000022` applied after a verified backup
- Production deployment: none

## Owner direction used

The owner authorized one consolidated tester update, accepted the documented recommendations without more routine B07 through B17 questionnaires, and asked for the supplied v0.8 research and CEFR claims to be retained with their lack of independent review stated clearly. This does not authorize an external file replacement, production deployment, destructive data operation, external AI provider, or unsupported content mapping.

## Authority state

| Source | SHA-256 | Confirmed state |
|---|---|---|
| Thesis authority | `BA64B2EB69673E84FC83E21D4617CD111691EE6340FC2F4331948FE6AF5EC132` | Active and unchanged |
| Existing `Hospitrainity.docx` | `7F8A2C62DB6884498F7A1C2DE56E79E39609262944A685FE7EB97F702444CBB4` | Active learning authority for the v0.4 package |
| `Hospitrainity New.docx` v0.8.0-draft | `5DE098DCDCC6405093004F6253A1DEF2F5DC4D85BC1CCACF1416376F3A77569B` | Verified replacement candidate, not active |

No external DOCX replacement occurred. `config/authority-sources.json` was not switched to the candidate by this consolidated update, and the active v0.4 content package was not mutated.

## Confirmed consolidated implementation

The shared working tree contains the following implementation surfaces:

- a course-source assistant that searches content inside the learner's allowed curriculum scope and falls back to cited course excerpts when no external provider is available;
- encrypted learner text responses with immutable ownership and curriculum references, personal or exact-Class scope, draft and submitted states, bounded export, and server-side authorization;
- a browser lesson-listening control, a deterministic review policy, review scheduling fields, and learner-timezone streak calculation;
- learner-facing copy and interface cleanup work, including removal or role-bounding of technical evidence in the changed surfaces;
- the isolated v0.8 compiler and verifier, without runtime activation;
- an importer repair and rollback correction that preserves the exact activity and prompt entity IDs referenced by saved learner responses.

This inventory confirms code present in the working tree. Only the commands and totals in the verification section are claimed as executed evidence. External authority replacement, candidate activation, and production deployment did not occur.

## Local tester access and translation correction

The owner asked to unlock the administrative tools for the first local tester release and reported raw translation keys such as `admin.confirm_password_description.security` in the interface.

`AUTH_LOCAL_TESTER_MFA_BYPASS` is enabled only in the ignored local `.env`. The committed examples default it to `false`, PHPUnit and E2E force it to `false`, and the production-readiness checker rejects a production configuration that enables it. The bypass requires all of the following conditions at once:

1. the Laravel environment is exactly `local`;
2. the request host and untrusted-proxy-independent remote address are loopback;
3. the authenticated address is a default or explicitly configured demo account;
4. the configuration switch is the boolean `true`.

The bypass is checked at password login and on every privileged route. It does not alter `User::requiresMfa()`, does not create a real MFA timestamp, and records its distinct local tester method. Normal E2E sign-in still completes the recovery-code challenge. The privileged middleware now also enforces the configured 900-second MFA step-up lifetime instead of accepting the timestamp for the full session.

The Account security lock panel now has a direct setup action when the bypass is not available. In local tester mode, the lock panel is absent and the known local tester lands on the correct privileged dashboard.

The translation repair added the missing English and Indonesian literal inventory, local authentication, password-broker, and validation catalogs, and the dynamic administration/status families. Curriculum authoring now renders translated labels for block, import, asset, outcome, and entity types. Raw identifiers and diagnostic metadata on the shared Content Admin surface remain visible only to Superadmin.

Static inventory checked 1,501 literal translation uses in English and Indonesian with zero missing keys. The two JSON catalogs contain 936 matching keys each, all eight PHP translation groups have exact key parity, and placeholders match. Rendered Chromium checks confirmed the English and Indonesian security-confirmation page does not expose the raw key.

## Main local database integration

Before migration, `database/database.sqlite` was copied to:

`storage/framework/testing/local-database-before-000019-000022-20260722.sqlite`

The source and backup matched SHA-256 `03B4A98C67BA8451EEC14198BC2D87CDDE6291D82468221DD6F0BD76F4641952` before migration. Migrations `2026_07_21_000019` through `2026_07_22_000022` then completed successfully. `migrate:status` confirms all four are in batch 18. This installs the Class foundation, roster operations, Class learning scope, saved learner responses, review scheduling, and streak fields in the main local database.

No external authority, production database, or deployment target was changed. The main local database still has no `admin@example.com` account, and the existing Supervisor has no active institution membership; the bypass does not invent missing users or role assignments. Isolated E2E fixtures remain the verified all-role test environment.

### Saved-response retention correction

`app/Services/Curriculum/CanonicalCurriculumImporter.php` now repairs an existing package projection by matching entities on package and source path, retaining the existing numeric entity ID, and updating the canonical fields in place. Before repair, saved response references are locked and checked against the required activity or prompt type, source digest, and parent relationship. A mismatch stops the repair instead of inventing a remap.

Rollback snapshot format `1.1.0` includes the encrypted `learner_text_responses` rows. Rollback locks and compares the current rows with the recorded snapshot, refuses if they changed, deletes only the recorded response IDs, restores the referenced curriculum rows with their recorded IDs, and reinserts the exact encrypted response rows. This keeps database constraints enabled and fails closed rather than deleting newer learner work.

The focused regression creates a submitted encrypted role-play response, corrupts the referenced activity projection, repairs it in place, and then rolls back. The response body, activity ID, prompt ID, attempts, completions, release evidence, and progress remain available at the asserted checkpoints.

## v0.8 candidate verification and runtime boundary

The candidate verifier completed with exit code 0:

```powershell
& 'C:\php\php.exe' scripts\curriculum\verify_next.php --source 'C:\Users\dhion\Downloads\Hospitrainity New.docx'
```

- 103 generated files were byte-identical across verification runs.
- Deterministic tree SHA-256: `1239C67BB86CC4CE610988A143BE3D341B8299D70A477A2D69713F88A50B3401`.
- Changed-source-hash, missing-chapter, duplicate-code, and learner-facing em-dash probes passed.
- The bundle contains 7 chapters, 88 sections, 75 learner-visible sections, 28 outcomes, 151 activity or response records, and 13 content nodes.

The candidate is verified as a deterministic source bundle. It is not compatible with the current active `CanonicalPackageReader` and importer contract without unresolved decisions:

1. Its 151 atomic activity or response records do not define the current activity-container and prompt-child hierarchy.
2. Twenty-one unscored free-text journal prompts do not contain the answer and feedback models required by the current canonical reader.
3. One `response_group` block has no current canonical block type or renderer.
4. Candidate blocks omit the current per-block language and provenance fields.
5. All 28 outcomes omit the current projection's `module`, `type`, and `provisional_band` fields.
6. Thirteen build or provenance sections cannot be published safely because current delivery does not filter lesson sections by candidate visibility.
7. Candidate entities are explicitly draft and not active, while current delivery serves published canonical entities.
8. Candidate metadata does not contain the namespace, checksum declarations, projection metadata, workflow gate, and standalone evidence required by the current active package contract.

No adapter was created because filling these gaps would require invented hierarchy, editorial classification, lifecycle promotion, or data loss. Resolve the canonical contract explicitly before attempting activation.

## Executed verification evidence

| Gate | Confirmed result |
|---|---|
| v0.8 `verify_next.php` | Exit 0; 103 byte-identical files; deterministic tree hash above |
| Canonical importer focused file | 8 tests passed, 65 assertions |
| Strengthened saved-response repair and rollback case | 1 test passed, 25 assertions after the final test adjustment |
| Focused previous-failure PHP suite | 64 tests passed, 815 assertions |
| `DemoSeedGuardTest` plus `AdminRouteSmokeTest` | 14 tests passed, 121 assertions |
| `npm run test:js` | 57 tests passed |
| `npm run lint:js` | Exit 0 |
| Production Vite build | Exit 0 |
| Configured PHPStan application analysis | Exit 0, no errors |
| Blade `view:cache` | Passed |
| Focused Content Admin Chromium E2E | 1 test passed |
| Full Laravel regression | 389 tests passed, 5,931 assertions, no reported skips, failures, incomplete, or risky tests; 635.28 seconds |
| Full Playwright matrix | 60 journeys passed: 15 desktop Chromium, 15 desktop WebKit, 15 mobile Chromium, and 15 mobile WebKit |
| Firefox Playwright project | Browser launch timed out before application navigation with `RenderCompositorSWGL failed mapping default framebuffer`; 1 launch failure and 14 journeys not run |
| Python Chromium visual verifier | 31 learner checks passed across desktop and mobile, light and dark themes, focus, overflow, interaction, and reduced motion |
| Governance verification | 43 findings mapped; 1,680 tracked files scanned; 2 reviewed allowlisted findings and 0 unallowlisted findings |
| User-facing em-dash scan | No U+2014 or em-dash entity in views, scripts, translations, or standalone output; 23 U+2014 values remain only in non-rendered, hash-locked CEFR authority metadata |
| Authority, Pint, Blade, JSON, and `git diff --check` | Passed |

Post-checkpoint local correction evidence:

| Gate | Confirmed result |
|---|---|
| Focused security and production-readiness suite | 24 tests passed, 133 assertions |
| Translation parity and inventory suite | 5 tests passed, 35 assertions |
| Corrected ordering-copy regression | 1 test passed, 10 assertions |
| Final full Laravel regression | 397 tests passed, 5,987 assertions; 582.49 seconds |
| JavaScript lint | Exit 0, no warnings |
| Node regression | 57 tests passed |
| Production Vite build | Exit 0; 32 modules transformed |
| Local tester Chromium verification | Passed: direct Superadmin landing, no lock panel, English and Indonesian raw-key rejection, zero browser console errors |
| Focused normal-MFA Chromium E2E | 2 tests passed: Content Admin and Superadmin, with `AUTH_LOCAL_TESTER_MFA_BYPASS=false` forced by the E2E environment |
| Translation inventory | 1,501 literal uses checked in two locales, zero missing; 936 JSON keys per locale; eight PHP group files in parity |
| User-facing em-dash scan after correction | No U+2014 found in rendered UI sources |
| Main local database migration | Migrations `000019` through `000022` passed; backup hash and batch 18 state recorded above |

The four completed browser projects passed every configured journey. Firefox did not reach the application, so this checkpoint makes no Firefox behavior claim. Automated checks do not establish all-route exploratory coverage, physical-device behavior, screen-reader behavior, accessibility conformance, penetration-test results, production readiness, or independent academic review.

## Remaining gates

1. Reproduce the Firefox project on a host where headless Firefox can create its software-renderer framebuffer. Do not treat the current pre-navigation launch failure as application evidence.
2. The external authoritative DOCX replacement requires explicit approval, a recoverable copy, and an exact destination hash check before any authority-config switch.
3. Candidate activation requires a reviewed canonical-schema decision and a new immutable package. Do not edit v0.4 in place.
4. The supplied research and CEFR claims remain owner-authorized but independently unreviewed. The 18 external DOCX relationship targets remain unmapped because the source contains no hyperlink anchors.
5. Production configuration, deployment, specialist review, assistive-technology review, physical-device checks, and security testing remain separate release decisions.

## Exact next-agent boundaries

No implementation checkpoint is pending. A new agent must begin read-only: verify the three authority hashes, read this checkpoint, inspect the dirty tree, and confirm that the requested task does not repeat completed work. It must not reapply migrations `000019` through `000022`, recreate the local backup, disable the scoped local tester bypass, or replace the repaired catalogs without new evidence. If asked to activate v0.8, it must first resolve and record the eight canonical-contract gaps above. If asked to release, it must treat Firefox reproduction, production configuration, deployment, and external review as separate gates. It must not infer approval for an external DOCX write or active-package switch.

Root remains the sole integration owner for shared collision points:

- `config/authority-sources.json`
- `routes/web.php`, `routes/api.php`, and `routes/console.php`
- `bootstrap/app.php`
- `composer.json`, `composer.lock`, `package.json`, and lockfiles
- `database/migrations/2026_07_22_000022_create_learner_responses_and_engagement_fields.php`
- `app/Services/Curriculum/CanonicalCurriculumImporter.php`
- roadmap, decision-register, traceability, and final checkpoint hashes
- external authority replacement, active-package selection, final merge, main-database migration, and deployment decisions

If root assigns a defect after the full gates, use one non-overlapping owner from this table:

| Lane | Exclusive repair boundary |
|---|---|
| Course assistant | `app/Http/Controllers/CourseAssistantController.php`; `app/Http/Requests/AskCourseAssistantRequest.php`; `app/Services/Assistant/**`; `resources/views/assistant/**`; `config/course_assistant.php`; `lang/en/assistant.php`; `lang/id/assistant.php`; `tests/Feature/CourseAssistantTest.php` |
| Saved responses | `app/Http/Controllers/LearnerTextResponseController.php`; `app/Http/Requests/StoreLearnerTextResponseRequest.php`; `app/Models/LearnerTextResponse.php`; `app/Policies/LearnerTextResponsePolicy.php`; `app/Services/Responses/**`; `resources/views/responses/**`; `lang/en/responses.php`; `lang/id/responses.php`; `tests/Feature/LearnerTextResponseTest.php` |
| Engagement | `app/Services/Engagement/**`; `resources/js/lesson-listening.js`; `lang/en/engagement.php`; `lang/id/engagement.php`; `tests/Feature/LearningStreakServiceTest.php`; `tests/Unit/ReviewPolicyTest.php`; `tests/Node/lesson-listening.test.mjs` |
| Authority candidate | `scripts/curriculum/NextGenerationSourceCompiler.php`; `scripts/curriculum/compile_next.php`; `scripts/curriculum/verify_next.php`; candidate artifacts under `storage/framework/testing/hospitrainity-v0.8.0-candidate/**` |
| UI verification | Read-only application inspection plus `tests/E2E/critical-path.spec.mjs` only when root explicitly assigns an E2E correction; no application UI edits by default |

No lane may edit the active `curriculum/hospitrainity/0.4.0-draft/**` package, the external DOCX, shared integration files, or another lane's files without a new root assignment.

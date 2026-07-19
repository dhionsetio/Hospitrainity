# Content Fidelity CF-1 through CF-6 checkpoint

**Checkpoint date:** 17 July 2026  
**Implementation state:** implemented, locally deployed, and verified  
**Release state:** `0.4.0-draft`; not approved for publication (CF-7 remains open)  
**18 July follow-up:** CF-7 technical implementation, automated QA, and the source-DOCX visual comparison are complete, but its human/owner release gates remain open. See [`CONTENT_FIDELITY_CF7_CHECKPOINT.md`](CONTENT_FIDELITY_CF7_CHECKPOINT.md).

## Authority and reproducible artifacts

- Authoritative source: `C:\Users\dhion\Desktop\Documents\000 - Thesis Dhion Setio\Revisi 30 Juni 2026\Learning Materials - Fixed\Hospitrainity.docx`
- Authoritative SHA-256: `7f8a2c62db6884498f7a1c2de56e79e39609262944a685fe7eb97f702444cbb4`
- Immutable rollback package: `curriculum/hospitrainity/0.3.0-draft`
- Generated development package: `curriculum/hospitrainity/0.4.0-draft`
- Package tree SHA-256: `e864b4fcaa9d182b8379cf7d179279114baa7fef46624bcb261be41b8072dc9a`
- Package inventory: 469 files, 1,473,828 bytes, 463 JSON files, and 6 Markdown files
- Standalone SHA-256: `5aa0002c350d1f86094c9ea867ec27a2819252d07685e3ac24cd10822f79a407`
- Machine evidence: `curriculum/evidence/cf-1-to-cf-6.json`

The maintained build commands and fault contract are documented in `scripts/curriculum/README.md`. Generated package and standalone files must be rebuilt through those scripts; they must not be hand-edited.

## Implemented outcomes

### CF-1 — Compiler and coverage contract

- Added a deterministic DOCX compiler and evidence generator under `scripts/curriculum/`.
- Parses ordered WordprocessingML paragraphs, bold source markers, tables, hyperlinks, inline emphasis, and source positions directly from the authority file.
- A double build produced the same 469-file tree hash.
- Six injected faults fail closed: changed authority hash, missing section marker, duplicate entity ID, malformed table row, missing hyperlink relationship, and unsupported body block.
- Final source coverage is 7 chapters, 85 sections, 21 source tables, 21 warm-up questions, 96 exercise prompts, 28 rating statements, 18 external relationships, 90 accepted strings, and 138 feedback/guidance strings.

### CF-2 — Structured source content

- Every lesson section contains non-empty ordered typed blocks with stable UUIDs, language, provenance, source locator, and normalized source hash.
- Supported block types are paragraph, heading, callout, list item, dialogue turn, source table, external link, instruction, and activity embed.
- Package reader/importer/rollback/standalone projections validate and preserve 774 blocks, all 21 tables, and all 18 external relationships.
- Source-verbatim content remains distinguishable from derived navigation, outcomes, CEFR metadata, rubrics, scoring normalization, and workflow rules.
- Final evidence reconciliation removed inherited CP-14 wording that described a closed release gate and an obsolete SQL origin. Generated metadata now states that chapter visibility is not release approval, CF-7 remains open, and the package originates from the authoritative DOCX compiler.

### CF-3 — Faithful activity semantics

- Preserved 96 source exercise stems, 90 accepted strings, and 138 feedback/guidance strings.
- Reclassified six confidence instructions as guidance and generated 24 chapter ratings plus four Chapter 1 baseline ratings.
- Added 74 structured selection prompts, five ordering permutations, six reviewed closed short-text items, five open short-text items, four role-play tasks, and two service-artifact tasks.
- Correct choice IDs/orders exist only in server-side answer models. Closed-text normalized variants are explicitly marked `derived_scoring`.
- All activities declare a validated `derived_workflow` completion rule; open production is never assigned fabricated objective scoring.

### CF-4 — Complete Laravel reading experience

- Added routes and projections for all 85 section pages with previous/next sequence navigation.
- Restored source-order reading, warm-ups, dialogues, vocabulary/comparison/confidence/register tables, tips, common mistakes, Take it further, and Further reading.
- Source tables use captions, column-scoped headers, and a labeled horizontal scroll region without causing page-level overflow.
- Learner pages expose concise source content; lifecycle/provenance diagnostics remain secondary evidence.

### CF-5 — Accessible response forms

- Selection/rating use native `fieldset`, `legend`, and radio controls; closed/open text uses explicit labels; ordering uses labeled native selects plus move buttons, with no drag dependency.
- Ordering controls update position-specific accessible names, announce movement, and preserve focus on an enabled control.
- Objective models are hidden until a checked attempt or an explicit “show model without answering” action.
- Validation allowlists all top-level/nested keys and every choice/token value. Client correctness/score fields are rejected and server answer models remain authoritative.
- Validation failures preserve form input and focus the error summary. Successful results focus the announced result region.
- Open text uses participation plus explicit self-check; optional audio recording/storage is disabled.

### CF-6 — Versioned attempts and privacy

- Added version-bound activity progress, attempt, response, and event tables. Attempt submission and response/event/progress writes are one transaction.
- Distinguishes viewed, started, attempted, self-checked, completed, and explicitly skipped baseline states.
- Enforces completion per response contract: all required objective responses checked; open responses entered and required self-checks confirmed; all four confidence statements rated; baseline rated or explicitly skipped.
- Idempotency uses a database unique constraint plus Laravel's unique-conflict-safe `firstOrCreate`; reuse with different content fails closed. The comparison fingerprint is HMAC-SHA-256 keyed by `APP_KEY`, not a plain free-text hash.
- Raw open writing/role-play text is not written to attempt history. A validation failure may hold it temporarily in the current Laravel session to preserve the form. No audio is collected. Objective IDs/order, normalized closed text, and learner confidence ratings are stored.
- Confidence history is learner-only by default, paginated, source-labeled, versioned, and explicitly described as self-reflection—not proficiency, test, or CEFR evidence.
- The 24 pre-existing canonical completion records are mapped to their original package/version as `legacy_reveal_only`; they remain visible as history and do not complete `0.4.0-draft`.
- Dashboard and staff aggregate percentages now count only completed progress for the active package/version. The retired `/progress/store` contract rejects canonical activities.
- Import repair and rollback snapshots include progress, attempts, responses, and events and preserve canonical entity IDs where possible so legacy polymorphic references remain valid.

## Privacy and retention boundary

The implemented minimum-data default is:

- no raw open response or audio in the attempt database;
- no administrative access to raw confidence answers by default (ADR-002);
- learner response history is deleted by database cascade when its user account is deleted;
- no fixed time-based deletion job has been enabled because an institutional learner-response retention duration has not been approved.

Therefore CF-6's data-minimization and account-deletion behavior are implemented, but a production owner must still approve a time-based learner-response retention period (or explicitly approve account-lifetime retention) before CF-7 publication. The 365-day ADR-002 recommendation applies to administration audit lookup, not automatically to learner responses.

## Verification evidence

- Compiler verification: 469 byte-identical files and all six negative probes passed.
- Canonical reader/assessment/attempt/import focused run: 18 tests, 882 assertions passed before the final cross-project run.
- Full PHP suite: 172 tests, 2,682 assertions passed with `--fail-on-all-issues` and no test output.
- JavaScript: ESLint passed with zero warnings; 39 Node tests passed, including execution of the generated standalone's About and chapter routes.
- Production assets: Vite production build passed.
- Chromium E2E: 3/3 public, learner-attempt, and superadmin read-only critical paths passed.
- In-app browser: authenticated local delivery verified after migration/import; 320 px and 1280 px pages had no page-level horizontal overflow; the 320 px table scrolled within its labeled region; ordering controls were 50 px high, focus-preserving, and announced movement; browser error/warning log was empty.

## Local deployment record and rollback

- Applied migration: `2026_07_17_000007_create_versioned_curriculum_attempt_tables`.
- Dry-run correctly identified replacement of active `0.3.0-draft` by `0.4.0-draft`.
- Import run: `96cf17d4-1ce8-4b49-b1ea-912531eab731`.
- Pre-import database SHA-256: `7353ad34f3d5b60923f86f52f6963997619839085c5fe319d9e618960768f381`.
- Post-import database SHA-256: `1f4ee52202c4194318b676fee760aeed4b26da4dee1000e05d12b326527fa974`.
- Private rollback artifact: `storage/app/private/curriculum/rollbacks/96cf17d4-1ce8-4b49-b1ea-912531eab731-before.json`.
- Rollback artifact SHA-256: `4c5083c4820004acf2ba01bdbefe3e826bfb2a2331b71baaa5751f76409c6105`.
- Post-import `hospitrainity:curriculum verify` passed package, Laravel projection, counts, and standalone checksums.

A final evidence reconciliation changed projection metadata and the generated standalone without changing any of the 469 canonical source-package files or entity counts. Both corrections were applied through the importer's `repair_projection` path:

- Projection-metadata repair run: `01fd446a-709c-4660-b7e7-880f6cbefab7`; importer snapshot SHA-256 `bd1981f328a6af4bc9f5f8ac2798df2a71d65b89b1a32491c2248d98bd804cb9` → `a3ac6c8be931b4c086db1cdf141d1c2ae57af7837601f3820cef224f55fa733f`; rollback `storage/app/private/curriculum/rollbacks/01fd446a-709c-4660-b7e7-880f6cbefab7-before.json` (SHA-256 `36f0fc43584887f504bdb71d9ea0f3dff0ef6fcc6d3985175f67d313814833ec`).
- Standalone-template repair run: `6d2a40c2-c2f2-4d5b-a0ff-f832b172182f`; importer snapshot SHA-256 `a3ac6c8be931b4c086db1cdf141d1c2ae57af7837601f3820cef224f55fa733f` → `c6b661f0dfe071435b88d18340e46ed8c2b0ff827d75954832ed9fd74a300a0c`; rollback `storage/app/private/curriculum/rollbacks/6d2a40c2-c2f2-4d5b-a0ff-f832b172182f-before.json` (SHA-256 `215515da5a13395b32c4d7ed5d24cb03613f556a56fd00fdcdf6aea2780a1f54`).
- Final Laravel projection SHA-256: `9c74289e8e85308b52e9deaae572696227a6ef47a35186ec9caf95d8f4548b69`.

## Remaining work

CF-7 technical implementation and source-DOCX visual comparison are now recorded in `CONTENT_FIDELITY_CF7_CHECKPOINT.md`. Do not rename this package to `0.4.0` or mark it published until the remaining CF-7 human/owner gates—external-link decisions, broader qualified review/sign-off, content-owner approval, and the learner-response retention decision above—are closed with evidence.

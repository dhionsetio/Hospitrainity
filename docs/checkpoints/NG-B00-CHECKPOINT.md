# NG-B00 checkpoint — authority freeze and trustworthy baseline

Status: verified locally on 2026-07-19. B00 is complete; B01 has not started.

## Outcome

Hospitrainity now has an authoritative local Git history on `main`, evidence-backed change-control and traceability scaffolding, non-destructive CI definitions, protected authority-hash verification, and a reproducible clean-checkout proof. This batch did not change a feature, migration, canonical package, or the main database.

Accountable owner/coordinator: Dhion Setio (`dhionsetio`). Specialist academic/content, accessibility, privacy/legal, security, and production reviewers remain required at their roadmap gates and have not been invented or pre-approved.

## Authority and integrity evidence

- Thesis SHA-256: `c38ac625a2f7a34bb004e6b68183d748bc6c1d3c1c9896c3f791909072496d34`.
- Learning-materials SHA-256: `7f8a2c62db6884498f7a1c2de56e79e39609262944a685fe7eb97f702444cbb4`.
- Both protected inputs passed `scripts/release/verify-authorities.ps1`; neither DOCX was copied into or tracked by the repository.
- Baseline commit: `3f2a4964cd73ef77ca71b89deabb09c8c5ed4e68`.
- Baseline tree: `7e7c904f9bd4746b310d5db61fd7630fef048736`.
- Branch: `main`; commit author: `dhionsetio <dhionsetio@gmail.com>`.
- Bootstrap integrity exception: the local baseline commit/tag is intentionally unsigned. SSH signing is required before any remote publication.

## B00 changes

- Added repository boundaries for secrets, databases, authority DOCX files, uploads, backups, generated builds, test artifacts, caches, and private storage.
- Added a fail-closed tracked-content secret/artifact scanner with reviewed fingerprint-only exceptions and Node regression tests.
- Added exact-hash authority configuration and a dormant manual self-hosted Windows release workflow that does not upload authorities or artifacts.
- Added a non-destructive GitHub Actions quality workflow, workflow-policy verifier, read-only SQLite inventory tool, decision register, release-evidence template, and 43-finding machine-readable traceability registry.
- Corrected stale setup/capability statements in the README and added repeatable governance commands.
- Preserved historical changelog/checkpoint next-action text as provenance; it is not treated as a live to-do queue.

## Data and behavior impact

- New migrations: none.
- Main-database writes, cleanup, or reseeding: none.
- Main database SHA-256 before and after all B00 gates: `1500795734fce828592d3ca613c9c555d4b8d3879ebc8be00c1c8f84f970054a`.
- Canonical active package remains draft `0.4.0-draft`, schema `2.0.0`, tree SHA-256 `e864b4fcaa9d182b8379cf7d179279114baa7fef46624bcb261be41b8072dc9a`.
- Clean-checkout migration/import writes occurred only in ignored disposable databases under `tmp/ng-b00-clean-3f2a496` and the E2E test workspace.
- Feature, authorization, curriculum, scoring, visual, and user-visible behavior changes: none intended or observed by the regression gates.

## Verification

The original working folder and a fresh clone of the exact baseline commit were both checked. The fresh clone retained zero tracked changes after setup and gates.

- Lockfile setup: Composer installed 111 locked packages; npm installed 176 locked packages.
- Composer validation: passed. Composer audit: 0 advisories and 0 abandoned packages.
- npm audit: 0 vulnerabilities across 246 dependencies.
- Vite production build: passed; 11 modules transformed.
- Laravel: 215 tests passed, 3,783 assertions, 0 failures.
- Node: 50 tests passed, 0 failures; ESLint passed with 0 warnings.
- Blade cache and Pint: passed.
- Traceability: all 43 findings mapped; tracked scan: 1,478 files, 4 reviewed fingerprint-only allowlist entries, 0 unallowlisted findings.
- Workflow policy: 2 workflows verified; authority lane is manual-only and uploads nothing.
- Deterministic compiler: 469 byte-identical files; tree `9d1511a24c84059a7c561603bb94b6c2c52a8076edfc2ba71def63ba2f2259ac`; all 6 fail-closed probes passed.
- Canonical database/package verification: passed in the disposable clone with 7 chapters, 85 sections, 25 activities, 124 prompts, 96 answer models, 96 feedback models, and 6 rubrics; all three canonical hashes matched.
- Brand guard: 751 files scanned, 0 active violations.
- Playwright Chromium: 7/7 journeys passed in 30.5 seconds; test ports 8010/8011 were closed afterward.
- Production-readiness checker: expected local result, 7 checks passed and 7 failed. Local `.env`, debug/HTTP settings, cookie/HSTS state, and development dependencies are not production evidence.

Only valid completed runs are counted above. Sandbox-restricted install/build attempts and a PHP run made before the required Vite bundle existed were excluded; the exact gates passed after documented prerequisites were complete.

## Security, privacy, accessibility, and browser impact

- Security improved through tracked-content scanning, authority isolation, lockfile audits, immutable CI action references, and machine-checked workflow restrictions.
- No privacy policy, retention rule, consent, deletion, or production-security decision was made in B00.
- Automated accessibility markup contracts and Chromium keyboard journeys passed, but this is not independent accessibility conformance.
- No direct real-device or real-browser evidence was gathered for Safari, Firefox, Edge, Opera, Android, iOS, or macOS in B00.

## Known limitations and release gates

- No GitHub remote was created or pushed. The approved target is a private repository owned by `dhionsetio` on GitHub Free.
- GitHub Free does not provide protected branches/rulesets for private repositories; this limitation is recorded rather than represented as enforced protection.
- No SSH signing key is configured. Remote publication remains gated on signing setup and an owner-approved publication action.
- The protected authority workflow is dormant: its offline self-hosted Windows runner is neither installed nor activated.
- PostgreSQL 18.4, concurrency/locking/query-plan evidence, the broader browser/device matrix, and production configuration remain B17 work.
- NG-P3-042 remains open because its target-database and no-critical-skip release evidence is only partially satisfied.
- Existing P0 findings NG-P0-001 through NG-P0-004 remain open. B01 requires its questionnaire before implementation.

## Rollback

Do not delete history, authority files, databases, storage, or evidence. If the owner explicitly rejects B00 after this accepted baseline, revert its commits with new auditable Git commits and remove only the rejected scaffolding. Any deletion still requires the owner's exact permission; do not use `git reset --hard` or rewrite the baseline.

## Next checkpoint

Stop at B00. Before B01, ask and record the complete B01 mandatory questionnaire covering registration, institutional identity/enrollment, bootstrap credentials/recovery, and lifecycle/publication truth. Do not infer those product decisions.

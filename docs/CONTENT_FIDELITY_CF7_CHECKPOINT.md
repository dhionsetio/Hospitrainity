# Hospitrainity Content Fidelity Checkpoint — CF-7

**Date:** 18 July 2026  
**Implementation state:** technical implementation and automated verification complete  
**Release state:** `0.4.0-draft`; publication is not approved  
**Authoritative source:** `Hospitrainity.docx`, SHA-256 `7f8a2c62db6884498f7a1c2de56e79e39609262944a685fe7eb97f702444cbb4`

## Outcome

CF-7 now has one deterministic canonical package driving the Laravel projection and generated standalone experience. Every canonical response form has an executable standalone path, active-brand scanning is fail-closed, external links have a source-derived health report, representative web sections have reproducible desktop/mobile renders, and the automated PHP, JavaScript, Chromium, build, compiler, projection, style, and dependency gates pass.

CF-7 is not a release approval. The source-DOCX environment blocker is now closed, but the package stays `0.4.0-draft` because the required human decisions/reviews are not recorded. This distinction is enforced in `curriculum/evidence/cf-7.json`, not left as an informal note.

## Changes implemented

### 1. Generated standalone parity

- `StandaloneProjectionBuilder` now projects the normalized accepted-answer contract required by closed text checks.
- `resources/standalone/hospitrainity-template.html` implements all canonical response forms using in-memory state only:
  - selection;
  - ordering with named Move up/Move down buttons and no drag dependency;
  - closed and open short text;
  - role-play and service-artifact self-checks;
  - confidence ratings and history;
  - explicit Chapter 1 baseline skip;
  - model reveal that never marks an activity complete.
- The standalone does not use `localStorage` or `sessionStorage`, does not imply server synchronization, and labels its development/draft limitations truthfully.
- `standalone/Hospitrainity-Standalone.html` remains generated output and was not hand-edited.

### 2. Active-brand guard

- Added `ActiveBrandGuard` and the `hospitrainity:brand-guard` command.
- The guard detects literal, case-varied, separated, filename, split-markup, active database, and standalone occurrences.
- The sole allowance is the exact historical provenance phrase in `provenance/legacy-inventory.json` and its active database projection. Similar or additional occurrences fail the gate.
- Final result: 672 active files scanned, zero violations, one exact file allowance, and one corresponding database allowance.

### 3. Canonical form validation and accessibility regression coverage

- FormRequest attribute names now identify the affected prompt or ordered/self-check item in human-readable validation messages.
- The activity view now provides:
  - a prompt-aware error summary;
  - links to the affected prompt/control;
  - inline error messages;
  - `aria-invalid` and `aria-describedby` associations;
  - focus targets for prompts and the action region.
- The browser client moves focus to the actual invalid control before falling back to a generic prompt control. A real regression was fixed where a valid textarea preceding an invalid checkbox captured focus.
- Browser coverage now exercises all response forms, non-drag ordering, target sizing, self-checks, baseline skip, confidence history, standalone execution, and the superadmin read-only legacy boundary.
- The resulting report is regression evidence, not a claim of full WCAG conformance or independent assistive-technology certification.

### 4. Rich-text source fidelity defect found during visual review

The canonical source block for `HSP-C02-LS-04` correctly contains `a-MEN-i-tees`. Word split that phrase into three adjacent runs with identical formatting. The prior Blade partial emitted template indentation between runs, so the browser displayed `a-MEN- i -tees`; the same defect placed dialogue labels and their sentences on artificial lines.

The shared rich-text renderer now:

- escapes every source fragment before rendering;
- adds only the fixed allow-list of `strong`, `em`, and `u` formatting tags;
- concatenates adjacent fragments without template whitespace;
- falls back to escaped block text when no runs exist.

The canonical JSON/DOCX text was not altered. A feature regression asserts the exact contiguous pronunciation, and regenerated desktop/mobile evidence confirms both the pronunciation and dialogue layout.

### 5. Source-derived external-link health

- Added a bounded checker that derives its target inventory from the canonical package, follows redirects, limits concurrency/time, retries transient failures, and never mutates content.
- It verifies all 18 source relationships / 16 unique targets.
- Result:
  - 13 reachable responses (`2xx`);
  - 2 access-blocked/rate-limited responses (`403`): Goldstar Recruit and Hospitality Net;
  - 1 broken response (`410`): the Diageo Bar Academy complaint/service-recovery URL.
- A `403` is not automatically classified as a dead learner link. Replacements/removals require content-owner review; none was guessed or silently applied.

### 6. Visual evidence and source comparison

Fresh Playwright Chromium screenshots cover:

- `HSP-C02-LS-04` vocabulary/table at 1280 CSS pixels;
- `HSP-C05-LS-06` model email at 1280 CSS pixels;
- `HSP-C07-LS-06` model dialogue/letter at 1280 CSS pixels;
- `HSP-C02-LS-04` at 320 CSS pixels.

Every captured page has equal document client/scroll width and zero captured browser errors. At 320 pixels the wide source table is intentionally contained in its own keyboard-focusable horizontal scroll region (`246` CSS-pixel client width / `336` CSS-pixel scroll width), while the page itself does not overflow.

After LibreOffice 26.2.4.2 was installed, the exact-hash 117,314-byte DOCX was rendered successfully through a stabilized conversion path. The first bundled post-install attempt reached its 300-second bound without an artifact, and a direct long workspace-path/profile attempt crashed in `ucrtbase.dll` with Windows status `0xC0000409`. The successful retry copied the exact-hash source to a short no-space temporary path, used an isolated LibreOffice profile with `SAL_DISABLESKIA=1`, and completed `writer_pdf_Export` in 22.4 seconds. These failures and the workaround are retained in the evidence rather than omitted.

The resulting 58-page Letter PDF is 784,548 bytes with SHA-256 `28dead0e773cf44a7c5e9da203b00ecb5b7e2872eeeb32a9be9625e62b5680d5`. All pages were rasterized at 150 DPI and inspected at original detail. No unreadable clipping, overlap, missing content/glyphs, or broken table borders were found. Emoji and checkbox glyphs render. Two source-render presentation observations remain: page 14 has one atypically indented continued bullet line, and page 49 places one further-reading link on an otherwise mostly blank page; neither loses content.

Representative source-to-web comparison is now `verified`:

- Chapter 2 `HSP-C02-LS-04`, source pages 8–9: row order, meanings, examples, and contiguous `a-MEN-i-tees` are preserved; the 320-pixel view contains the wide table in its keyboard-focusable horizontal scroll region without page overflow.
- Chapter 5 `HSP-C05-LS-06`, source page 36: subject, greeting, paragraphs, details list, next step, sign-off, author, and explanatory note preserve wording/order.
- Chapter 7 `HSP-C07-LS-06`, source page 53: dialogue labels remain attached to their sentences, and the explanatory paragraph and apology letter preserve wording/order and document structure.

This verifies faithful semantic/structural projection with deliberate responsive adaptation; it does not claim pixel identity with Word or replace the independent human accessibility/content gates.

### 7. Consolidated evidence and projection repair

- `scripts/curriculum/evidence.php` now records CF-7 quality-artifact status, path, SHA-256, and size.
- Human gates are explicit: learner-response retention, qualified ESP/CEFR review, hospitality-practitioner review, accessibility review, external-link decisions, and content-owner release approval.
- The release decision explicitly retains the draft lifecycle.
- The active database projection was repaired through the normal transactional importer rather than direct SQL:
  - run ID: `a0f98562-bd2f-4368-a7ee-6dbd69c3b371`;
  - 1,599 writes planned to restore the canonical projection;
  - every entity-count delta was zero;
  - database SHA-256 changed from `c6b661f0dfe071435b88d18340e46ed8c2b0ff827d75954832ed9fd74a300a0c` to `a23bbb57b16763602e70074c31fe9b2057627342e52d86427924e574f11d4c2c`;
  - the standalone did not change during import;
  - a complete pre-import rollback artifact was recorded.

## Canonical integrity evidence

| Artifact | Verified value |
|---|---|
| Source DOCX SHA-256 | `7f8a2c62db6884498f7a1c2de56e79e39609262944a685fe7eb97f702444cbb4` |
| Source render PDF | 58 pages / 784,548 bytes / SHA-256 `28dead0e773cf44a7c5e9da203b00ecb5b7e2872eeeb32a9be9625e62b5680d5` |
| Canonical package | 469 files / 1,473,828 bytes |
| Canonical tree SHA-256 | `e864b4fcaa9d182b8379cf7d179279114baa7fef46624bcb261be41b8072dc9a` |
| Laravel projection SHA-256 | `0967725acf53b46349ad8000a6b8e652e8855f37978912e796c3391cd9587f7f` |
| Standalone SHA-256 | `55d1418fc75e359f85c9f7bb8ad1d93ea36943859b48f3c18a1db65039bf36c4` |
| Standalone size | 707,504 bytes |
| Consolidated CF-7 evidence SHA-256 | `9d864bf7b065bdd9593f3c5f06d94115d5ffccdc815d64126713838c2d7e4f04` |

Verified entity counts remain:

- 7 chapters;
- 85 sections;
- 25 activities;
- 124 prompts;
- 96 answer models;
- 96 feedback models;
- 6 rubrics;
- 21 outcomes;
- 7 competencies;
- 23 CEFR references;
- 7 source-provenance records;
- 8 migration edges.

The deterministic compiler built twice into independent temporary directories with 469 byte-identical files. Its six negative probes all failed closed for the intended reason: changed source hash, missing section marker, duplicate IDs, malformed table rows, missing hyperlink relationship, and unsupported WordprocessingML block.

## Quality artifacts

| Gate | Status | Artifact SHA-256 |
|---|---|---|
| Active brand | `verified` | `b71a87b8d8bcd6864c92a79a0e6b6cfb7acf936a5519afa00cb3d38b12b8cefe` |
| External links | `review_required` | `12f3774fb47608d202c15cbcc49410b7f40f5e024b555a6df43ffa197ea4fc45` |
| WCAG regression coverage | `automated_verified_manual_review_open` | `f53ca934b263867b05a881ef3f0b1f2be32422a751392c973214c2b2d0164e13` |
| Source visual comparison | `verified` | `eadebe9d3929c6849f7eb0669d8e9ed08d53f0c074ca9283f5ff652fd5fe368c` |
| Final live projection verify | `verified` | `14eb602b1c29c58ae6f19a3dc42f98575c6448ab646d9a93dfd902303bcb5227` |

## Verification results

- PHP: 175 tests passed, 2,729 assertions, no reported issues or disallowed output.
- Node: 45 tests passed.
- Playwright Chromium: 7 journeys passed.
- ESLint: zero warnings/errors.
- Laravel Pint: passed after applying deterministic formatting to five in-scope CF/ADM files.
- Production assets: Vite 6.4.3 built 10 modules successfully.
- Compiler: deterministic double build plus 6/6 fail-closed mutation probes passed.
- Live projection: source tree, Laravel projection, counts, and standalone SHA-256 verified.
- Composer audit: zero advisories and zero abandoned packages.
- npm audit: zero vulnerabilities at every severity.
- Active-brand scan: zero violations.
- Post-render targeted regression: 4 BrandIntegrity/CanonicalPackageReader tests passed with 58 assertions.

## Open release gates and limitations

The following are not implementation details an AI may infer or self-approve:

1. Approve a time-based learner-response retention period, or explicitly approve account-lifetime retention.
2. Obtain a qualified ESP/CEFR review; provisional CEFR bands remain hypotheses, not certification.
3. Obtain hospitality-practitioner review.
4. Obtain independent accessibility review, including keyboard, screen reader, focus visibility/not-obscured, zoom/reflow/text spacing, and the supported-browser decision.
5. Decide whether to replace/remove the `410` link and how to treat the two `403` targets after human/browser review.
6. Record content-owner release approval.

Until every gate passes, do not rename `0.4.0-draft`, change the release lifecycle to published, or describe CF-7 as independently certified.

## Rollback

The canonical source package was not mutated by CF-7. The recorded pre-repair database artifact is:

- path: `storage/app/private/curriculum/rollbacks/a0f98562-bd2f-4368-a7ee-6dbd69c3b371-before.json`;
- size: 5,073,508 bytes;
- SHA-256: `721f0c116505b88a2574ba7cb044b46f2f13c1c4100289596065131ed00ea999`.

Use only the guarded importer rollback action with that exact artifact, then rerun the final projection verifier. Do not restore database rows with ad hoc SQL. Code/generated-file rollback requires the prior reviewed workspace/archive because this workspace has no usable Git `HEAD`; do not treat the database rollback artifact as a source-code backup.

## Reproduction commands

Run from the project root with the documented PHP/Node runtimes:

```powershell
& 'C:\php\php.exe' scripts\curriculum\verify.php --source '<authoritative Hospitrainity.docx>' --baseline 'curriculum\hospitrainity\0.3.0-draft'
& 'C:\php\php.exe' artisan hospitrainity:curriculum verify --report=storage/app/private/curriculum/reports/cf-7-final-verify.json --no-interaction
& 'C:\php\php.exe' artisan hospitrainity:brand-guard --report=curriculum/evidence/cf-7-brand-guard.json
& 'C:\php\php.exe' artisan test --display-all-issues --fail-on-all-issues --disallow-test-output
& 'C:\php\php.exe' vendor\bin\pint --test
& 'C:\Program Files\nodejs\npm.cmd' run lint:js
& 'C:\Program Files\nodejs\npm.cmd' run test:js
& 'C:\Program Files\nodejs\npm.cmd' run test:e2e
& 'C:\Program Files\nodejs\npm.cmd' run build
& 'C:\php\php.exe' 'C:\composer\composer.phar' audit --format=json
& 'C:\Program Files\nodejs\npm.cmd' audit --json
```

The external-link check requires network access. The web capture requires a local Laravel server and Playwright Chromium. To reproduce the source render, first verify the authority hash, copy it without modification to a short no-space temporary path, then run LibreOffice 26.2.4.2 headlessly with an isolated short-path user profile, `SAL_DISABLESKIA=1`, and the `writer_pdf_Export` filter. Inspect all generated pages; do not treat a successful conversion alone as visual verification.

## Primary standards and implementation references

- [W3C Web Content Accessibility Guidelines 2.2](https://www.w3.org/TR/WCAG22/)
- [WHATWG HTML — Forms](https://html.spec.whatwg.org/multipage/forms.html)
- [Laravel 12 validation](https://laravel.com/docs/12.x/validation)
- [Playwright test assertions](https://playwright.dev/docs/test-assertions)

## Next action

The product/content owner should inspect the generated standalone, the four web screenshots, the verified source-visual report, and the link report, then provide the open decisions/review evidence above. After all human/owner gates are recorded, rerun the consolidated evidence generator and the full gate matrix. Only then may a separate release action promote `0.4.0-draft` to `0.4.0`.

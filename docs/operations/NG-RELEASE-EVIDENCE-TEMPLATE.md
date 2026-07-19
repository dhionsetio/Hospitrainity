# Hospitrainity Next-Generation Release Evidence Template

Use one copy per checkpoint or release candidate. Replace every placeholder with observed evidence or `NOT RUN / NOT AVAILABLE` plus its consequence. Never delete a failed result, convert a missing review into `PASS`, or treat automation as a human/legal/conformance claim.

## 1. Scope and identity

- Batch/release:
- Evidence date/time and timezone:
- Executor:
- Accountable owner:
- Git branch:
- Git commit SHA:
- Git tree SHA:
- Tag and tag-object SHA, if any:
- Parent/baseline SHA:
- Worktree clean before run:
- Worktree clean after run:
- Changed files reviewed:
- Decision record(s):
- Traceability finding IDs:

## 2. Authority evidence

| Authority | Expected SHA-256 | Observed SHA-256 | Result | Re-audit required? |
|---|---|---|---|---|
| Thesis/product intent |  |  |  |  |
| Hospitrainity learning materials |  |  |  |  |

- Source precedence applied:
- Authority artifacts copied, logged, committed, cached, or uploaded? State each explicitly:
- Exact protected-lane workflow/run, if applicable:

## 3. Change and data impact

- User-visible behavior changed:
- Visual/UI preference changed:
- Routes/policies/capabilities changed:
- Migrations added/changed:
- Main database access mode:
- Pre/post row counts and invariant checks:
- Private storage/upload impact:
- Seeders invoked:
- Destructive action performed:
- Backup taken and exact target:
- Isolated restore performed and result:

## 4. Security and privacy

- Tracked secret/private-artifact scan command and exact result:
- Reviewed allowlist entries and expiry/removal batch:
- Dependency audit commands/results:
- Authentication/authorization/CSRF/throttle/direct-ID/cross-scope evidence:
- Private fields/paths/logs/artifacts inspected for leakage:
- ASVS versioned requirements claimed and evidence:
- Privacy/legal decision and qualified approval:
- Known security/privacy limitations:

## 5. Accessibility and browser/device evidence

- Automated accessibility checks:
- Keyboard/focus/error checks:
- 320/390/768/1280 CSS-width evidence:
- 200%/400% zoom and reflow:
- Reduced motion/high contrast:
- Screen reader + version + OS/browser:
- Touch target/mobile behavior:
- Playwright Chromium:
- Playwright Firefox:
- Playwright WebKit:
- Branded Chrome:
- Microsoft Edge:
- Branded Firefox:
- Safari/macOS:
- Opera:
- Android Chrome/physical device:
- iOS Safari/physical device:
- Explicit coverage limitations:

## 6. Automated verification

| Gate | Exact command | Result | Tests/assertions/checks | Duration | Evidence location |
|---|---|---|---|---|---|
| Governance/traceability |  |  |  |  |  |
| PHP syntax/style |  |  |  |  |  |
| Strict Laravel suite |  |  |  |  |  |
| JavaScript lint |  |  |  |  |  |
| Node suite |  |  |  |  |  |
| Production build |  |  |  |  |  |
| Browser E2E |  |  |  |  |  |
| Canonical verifier |  |  |  |  |  |
| DOCX/compiler determinism |  |  |  |  |  |
| Negative compiler probes |  |  |  |  |  |
| Brand guard |  |  |  |  |  |
| Composer audit |  |  |  |  |  |
| npm audit |  |  |  |  |  |
| Production readiness |  |  |  |  |  |
| Target-database lane |  |  |  |  |  |
| Clean-checkout reproduction |  |  |  |  |  |

Passing tests prove only their inspected contracts. Explain why each gate covers the associated requirement and record material gaps.

## 7. Canonical package and runtime inventory

- PHP/Laravel/Composer versions:
- Node/npm and front-end package versions:
- Lockfile hashes:
- Route totals/named/unnamed and route-tree hash:
- Migration count/tree hash/database migration rows:
- Active/inactive package versions and lifecycle states:
- Canonical counts:
- Source tree / Laravel projection / standalone hashes:
- Main database read-only inventory:

## 8. Human, legal, and production gates

| Gate | Required reviewer/operator | Evidence | State | Release consequence |
|---|---|---|---|---|
| Product/feature/visual |  |  |  |  |
| Academic/content |  |  |  |  |
| Accessibility |  |  |  |  |
| Privacy/legal |  |  |  |  |
| Independent security |  |  |  |  |
| Production operations |  |  |  |  |

- Host/TLS/proxy:
- Production database/version:
- Private/object storage:
- Mail/queue/scheduler:
- Secrets manager and least privilege:
- Central logs/monitoring/alerts:
- Database + storage backup/restore drill:
- Deployment/rollback rehearsal:
- Incident and recovery owners:

## 9. Rollback and limitations

- Exact rollback or forward-fix procedure:
- Evidence/data that must be preserved:
- Known limitations/adverse findings:
- Open finding IDs:
- Production blockers:
- Truthful release statement:

## 10. Verdict and next boundary

- Technical verdict:
- Human/legal/production verdict:
- Release authorized by and date:
- Exact next batch:
- Complete next-batch questionnaire sent and answered? This checkpoint never authorizes it implicitly:

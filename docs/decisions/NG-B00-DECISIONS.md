# NG-B00 Authority Freeze and Change-Control Decisions

- Decision date: 2026-07-19 (Asia/Jakarta)
- Batch: B00
- Status: Accepted by the project owner
- Accountable owner/coordinator: Dhion
- Git identity: `dhionsetio <dhionsetio@gmail.com>`
- GitHub account: `dhionsetio`, Free plan
- Behavior/data effect: None authorized

## Owner answers used

| Question | Accepted answer and consequence |
|---|---|
| Authoritative working folder | This folder is authoritative. The existing empty `.git` directory is not evidence of prior history and does not need reconciliation. |
| Initialize version control | Yes. Initialize an explicit `main` branch and create the B00 baseline locally. |
| Repository hosting/access | Approved target: a private repository owned by GitHub user `dhionsetio`, initially accessible only to Dhion. B00 does not create or push a remote because no signed identity or authenticated GitHub client exists yet. Localhost website development is separate from source-repository hosting. |
| Confidential/generated content | Never track authority DOCXs or copies, `.env` secrets, databases, private storage/uploads, backups, rendered audit evidence, logs, caches, test artifacts, dependencies, or build output. Modify ignore rules and guards only; do not modify, move, or delete the protected files. |
| Branch/checkpoint convention | `main` is the baseline. Each later batch uses one `codex/ng-bxx-*` branch and one checkpoint. No later batch starts from the current checkpoint implicitly. |
| Integrity control | Adopt SSH-signed commits/tags before remote publication. No SSH key exists today. The local B00 baseline may use an unsigned annotated tag plus exact commit and tree hashes as a documented bootstrap exception. Never generate an unprotected signing key automatically. |
| CI/provider/matrix | GitHub Actions. Fast lane: Ubuntu, SQLite, Chromium. Extended release target: PostgreSQL 18.4, Chromium, patched Playwright Firefox/WebKit, branded Chrome/Edge, plus Windows/macOS smoke evidence. Real Safari, branded Firefox, Opera, Android Chrome, and physical iOS/Android remain manual release evidence. |
| Approval authority | Dhion is accountable owner/coordinator and sole product, feature, and visual decision-maker. Qualified academic/content, accessibility, privacy/legal, independent-security, and production reviewers/operators remain TBD and are mandatory before their applicable release gates. Codex may implement and test but cannot independently certify its own work or supply legal approval. |
| Batch boundary | One batch per Codex task/checkpoint. Stop after B00; B01 requires its own questionnaire. |
| Authority changes | Rehash both DOCXs before every batch. On a mismatch, stop and relearn the changed source before implementation. The learning DOCX controls authored learning content; the thesis controls product intent; published packages are not silently mutated. |
| Existing private/generated artifacts | Leave them in place and ignore them. Do not prune, move, or delete anything without Dhion's explicit permission and an exact target list. |
| Technical discretion | Codex may select current, evidence-based architecture, libraries, testing, and coding techniques. Dhion retains decisions that affect visuals, product features or role capabilities, pedagogy/grading, privacy/retention/legal policy, vendors/costs, and production topology. |

## Repository and GitHub Free constraint

GitHub Free permits private repositories and GitHub Actions, but it does not provide protected branches or rulesets for a private personal repository. Therefore:

1. no claim is made that GitHub will enforce signed commits, required checks, review, or force-push prevention on this private repository;
2. the B00 baseline relies on local exact hashes, an annotated tag, reviewable history, CI configuration, and owner-only access;
3. remote creation/push remains gated until a passphrase-protected SSH signing key is configured and its public key is registered;
4. GitHub Pro or a stronger applicable plan is recommended before branch protection becomes a production dependency or collaborators receive write access;
5. the authority workflow remains dormant and its runner remains offline. GitHub Free private environments cannot supply the independent deployment-review protection required for an unattended authority lane.

## Protected authority mechanism

`config/authority-sources.json` records only logical labels and expected SHA-256 values. The DOCX paths are supplied through environment variables configured locally on a dedicated Windows runner. `scripts/release/verify-authorities.ps1` reads and hashes the files without copying them, logging their paths, or uploading them. `.github/workflows/authority-release.yml` is manual-only, accepts an exact commit SHA, refuses a checkout mismatch, and contains no upload step.

This is a dormant B00 mechanism, not an active protected release service. Before activation, Dhion must explicitly authorize runner installation, verify the signed `main` commit locally, start the otherwise-offline runner, supervise the run, and stop it afterward. Target-database and complete source-compiler release lanes remain B17 work.

## Evidence boundaries and unresolved gates

- No remote repository was created, no collaborator was invited, and no code was pushed.
- No signing key was created or configured.
- No private authority artifact was copied into the repository.
- PostgreSQL release testing is approved as a target but remains unimplemented until a verified target-database lane is added.
- The real-browser/device matrix is approved, but no current B00 run can claim Safari, branded Firefox, Opera, Android, iOS, or physical-device coverage.
- Specialist reviewer identities, production host/topology, production operator, backup product, restore drill, mail, queue, storage, monitoring, and incident operations remain unknown and production-blocking.
- Historical checkpoint “Next action” text remains immutable provenance. It is not treated as a live to-do. Current live work is governed by the next-generation roadmap and this register.

## Primary-source basis

- Git initialization and explicit initial branch: https://git-scm.com/docs/git-init
- Git ignore precedence and tracked-file behavior: https://git-scm.com/docs/gitignore.html
- GitHub plans and private-repository limitations: https://docs.github.com/en/get-started/learning-about-github/githubs-plans
- Protected branch availability: https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/managing-protected-branches/about-protected-branches
- Commit signature verification: https://docs.github.com/en/authentication/managing-commit-signature-verification/about-commit-signature-verification
- GitHub self-hosted runner warning: https://docs.github.com/en/actions/how-tos/manage-runners/self-hosted-runners/add-runners
- Playwright browser scope: https://playwright.dev/docs/browsers
- Playwright device emulation boundary: https://playwright.dev/docs/emulation
- PostgreSQL supported-version policy: https://www.postgresql.org/support/versioning/
- Laravel 12 database support: https://laravel.com/docs/12.x/database
- OWASP secrets-management guidance: https://cheatsheetseries.owasp.org/cheatsheets/Secrets_Management_Cheat_Sheet.html
- OWASP CI/CD guidance: https://cheatsheetseries.owasp.org/cheatsheets/CI_CD_Security_Cheat_Sheet.html

## Supersession rule

Only a later owner-approved decision record may change these choices. A technical implementation may refine mechanics without another questionnaire only when it does not alter product behavior, visual preference, data/legal policy, external cost, access, or production topology.

# NG-B03 checkpoint — authentication, security assurance, and upload safeguards

- Status: local technical implementation complete and installed on 2026-07-20; production monitoring, recovery, scanner, restore, and independent-assessment gates remain blocked.
- Branch: `codex/ng-b03-security-assurance`
- Parent checkpoint: `dd4a320` (`feat: implement privacy and data lifecycle`)
- Authority sources: unchanged thesis and learning-material hashes recorded by B00.

## Owner decisions applied

The owner approved the recommended ASVS-informed architecture: passkeys as the primary strong authenticator, TOTP fallback, one-use recovery codes, required MFA for every enabled privileged account, optional MFA for learners, Argon2id with opportunistic bcrypt migration, a modern password policy without arbitrary composition rules, layered throttling without permanent attacker-triggered lockout, provider-neutral structured security evidence, a ClamAV-compatible upload scanner that fails closed when required, conservative CSP capabilities, and independent security testing before a real-user pilot.

No production host, TLS proxy, PostgreSQL/object-storage service, queue/scheduler, mail service, secrets manager, central log/SIEM destination, backup tool, second recovery controller, SSO provider, or alert endpoint was invented. Those remain explicit release gates.

## Implemented

- Password registration, invitation, reset, and account-change flows enforce 15–128 characters, a pinned 10,000-entry local common-password blocklist, application/user context screening, NFC validation, paste/autofill compatibility, and no arbitrary character-class rule. Existing bcrypt hashes remain valid and rehash to Argon2id only after successful authentication.
- Layered account, IP, global, MFA, passkey, reset, and sensitive-action rate limits use HMAC-pseudonymous account keys and generic authentication failures.
- Privileged password login requires a confirmed TOTP challenge or passkey; learners may opt in. TOTP secrets are encrypted, recovery codes are HMAC-digested and one-use, and the security center supports enrollment, regeneration, safe factor removal, password change, session inventory, and owner-scoped revocation.
- Laravel's first-party Fortify/passkeys packages provide WebAuthn ceremony handling. Registration and deletion require a recent password; deleting the last required factor fails closed. Relying-party and exact origin configuration are production readiness gates.
- Security events are append-only UUID records with encrypted bounded metadata, HMAC account/IP fingerprints, and a daily JSON channel. Login, MFA, passkey, session, CSP, and upload outcomes exclude passwords, tokens, TOTP secrets, request bodies, private URLs, and learner answers.
- CSP reporting accepts only a bounded minimized field set. Security/authenticator pages are `no-store` and `no-referrer`.
- DOCX and asset workspaces inspect content first, then call a provider-neutral ClamAV adapter before explicit promotion. Original filenames are encrypted; hashes, MIME, size, scanner outcome, and promotion time are retained. Missing scanner infrastructure is classified as unavailable rather than as a false malware finding; required production scanning fails closed.
- Production readiness now checks the B03 schema, Argon2id, privileged MFA enrollment, exact HTTPS passkey origin, required healthy scanner, structured JSON logging, and the ASVS evidence matrix.
- Larastan 3.10 / PHPStan level 5 rejects new findings. The 440 pre-B03 findings are an explicit baseline inventory, not a claim that legacy static findings are resolved.

Detailed control/gap evidence is in `docs/security/OWASP-ASVS-5.0.md`; operational ownership and response procedures are in `docs/operations/NG-B03-SECURITY-OPERATIONS.md`.

## Authoritative database migration

Before migration, `database/database.sqlite` was copied to:

- `storage/app/backup-snapshots/b03-presecurity-20260720-144046.sqlite`
- SHA-256: `1d4fbd470334199a24490d55e3e00f740566fc51bff18d861013934256f1111c`

Migration `2026_07_20_000015_create_security_assurance_tables.php` ran as batch 14. Postflight: SQLite integrity `ok`; zero foreign-key violations; all three demo identities remain present and enabled; the security-event and passkey schemas are installed. No account, institution, membership, learning record, session, curriculum record, upload, or private artifact was deleted.

## Verification

- Focused B03: 10 tests / 63 assertions passed, including password policy, bcrypt-to-Argon2id login, privileged MFA enforcement, TOTP/recovery replay rejection, security-route fail-closed behavior, recent-password passkey options, session ownership, clean/detected/unavailable scanner outcomes, minimized CSP evidence, and migration rollback/reapply.
- Cumulative Laravel: 319 tests / 4,826 assertions passed in 139.50 seconds.
- Static/JavaScript: PHPStan passed with zero new errors; ESLint passed with zero warnings; 50 Node tests passed.
- Supply chain/build: npm audit found 0 vulnerabilities across 248 dependencies; Composer found 0 advisories and 0 abandoned packages; Vite 6.4.3 production build passed.
- Governance: 43 findings mapped; 1,605 tracked files scanned; 2 reviewed allowlisted synthetic findings; 0 unallowlisted findings.
- Browser regression: 40/40 critical journeys passed across patched desktop Chromium, desktop WebKit, Pixel 7/mobile Chromium, and iPhone 15/mobile WebKit, including disposable MFA recovery for privileged test accounts. Patched Firefox `firefox-1532` twice failed before its first navigation because its headless SWGL renderer could not map a framebuffer; both traces were retained. This is not Firefox application evidence.
- Database performance: bcrypt cost 12 median 214.2 ms; Argon2id 64 MiB/time 4/parallelism 1 median 216.3 ms over five hashes on this machine.

## Security boundary and remaining gates

This checkpoint does not claim universal ASVS Level 2, production security, physical passkey/security-key coverage, assisted recovery readiness, branded Chrome/Safari/Edge/Firefox/Opera coverage, production ClamAV/CDR, tamper-aware central monitoring/alerts, backup restoration, SAST/DAST, penetration testing, or physical Windows/macOS/Android/iOS coverage.

The three demo accounts remain enabled by owner decision. The privileged demo accounts intentionally have no real enrolled authenticator and therefore fail the production readiness gate until the owner enrolls real factors. The local host also has no `clamscan` executable, so scanner health correctly fails production readiness.

Required external closure:

1. Enroll and physically verify at least two recovery-capable factors for the creator/System Admin and every enabled privileged pilot account.
2. Select a second recovery controller and exercise the dual-control lost-factor process.
3. Install and operate a production scanner/CDR pipeline; prove clean, positive, unavailable, timeout, retry, re-scan, quarantine, and disposal behavior.
4. Select production topology, central tamper-aware security logging/alerts, secrets management, backup/immutability, and accountable responders; run restore and incident exercises.
5. Run approved SAST/DAST and independent penetration testing without uploading private authority artifacts.
6. Reproduce Firefox and branded/physical browser-device coverage in a suitable host environment.

## 2026-07-20 navigation-boundary follow-up

The owner reported that an unenrolled privileged account could not leave `/security`. Reproduction confirmed that `EnsurePrivilegedMfa` had been appended to the global web middleware stack. The correction aliases that middleware and applies it only to the three privileged route groups. Public pages, Help, work-context selection, and the Learner dashboard are now usable without privileged assurance; privileged dashboards still redirect to `/security` unless strong MFA is enrolled and the current session has completed MFA verification.

Focused regression passed 26 tests / 359 assertions across the security assurance, role landing, route matrix, and work-context authorization suites. The cumulative Laravel suite passed 336 tests / 5,322 assertions. Pint, PHPStan, ESLint, 50 Node tests, the Vite production build, translation-key parity, JSON parsing, diff checks, and governance verification also passed; governance mapped 43 findings and scanned 1,671 tracked files with 2 reviewed allowlisted and 0 unallowlisted findings.

A browser journey against a disposable SQLite database and synthetic no-MFA System Admin verified all four transitions: sign-in lands on `/security`; the public home is reachable; `Continue as Learner` reaches `/dashboard`; switching back to System Admin returns to `/security`. The fixture did not modify the authoritative database, and the required production MFA/readiness gates remain unchanged.

## Rollback

The isolated migration rollback/reapply test preserves users. Before real passkeys, recovery codes, events, or scanner records exist, the verified backup can support a controlled rejection of B03. After real security evidence exists, do not silently remove enforcement or restore stale credential state: stop affected flows visibly, preserve evidence, retain password/hash compatibility, and forward-fix. Emergency privileged recovery must use the documented dual-control process; no bypass was added.

## Exact next batch

B04 — WCAG 2.2 AA implementation, responsive shells, authentication semantics, mobile lecturer workflow, and user accessibility preferences.

# OWASP ASVS 5.0 security evidence matrix

Date: 2026-07-20 (Asia/Jakarta)  
Target: ASVS 5.0.0 Level 2 overall, with selected higher-assurance controls for privileged work  
Status: technical baseline implemented; **no ASVS certification or production-security claim**

This is an evidence map, not a checklist marked complete by assertion. A control is `verified-local` only where repository code and repeatable automated tests exist. `external-gate` means the application has a fail-closed configuration or documented interface, but the production topology, independent assessor, or physical authenticator evidence does not exist yet.

| Area | Local status | Evidence | Explicit gap |
|---|---|---|---|
| Passwords (ASVS 6.2) | verified-local | `App\Rules\SecurePassword`; 15–128 characters; no composition/rotation rule; paste/autofill preserved; local 10,000-entry blocklist; contextual terms; `SecurityAssuranceTest` | No external breached-password service is called because k-anonymity/privacy approval is absent. The blocklist source must be reviewed when refreshed. |
| General authentication (ASVS 6.3) | verified-local with documented scope relaxation | Layered account/IP/global limits; generic login error; passkey/TOTP paths; security events; privileged enforcement | Learners may remain password-only by owner decision, so the application does not claim complete ASVS L2 authentication coverage for every account. Distributed/WAF evidence is external. |
| Factor lifecycle and recovery (ASVS 6.4) | verified-local / external-gate | First-party Laravel WebAuthn; encrypted TOTP secret; one-use HMAC-SHA-256 recovery-code digests; last-factor guard; recent-password management; recovery tests | Privileged assisted recovery is not enabled until a second authorized controller and dual-control process exist. Physical passkey/security-key ceremonies remain manual evidence. |
| Credential storage | verified-local migration mode | Argon2id is the default; successful legacy bcrypt login is rehashed; strict algorithm verification stays off only during the measured migration | Inventory and migrate every active legacy hash, then enable strict verification. Exact production Argon cost must be benchmarked on the selected host. |
| Session management | verified-local | Regeneration after login; database inventory; owner-scoped individual/all-other revocation; password-change and recovery revocation; disabled-account middleware | Production proxy/IP interpretation and multi-node cache/session behavior need target-environment testing. |
| Authorization and tenant isolation | verified-local, inherited | Fail-closed role/policy/tenant controls and cross-boundary tests from B01/B05; MFA middleware precedes privileged work | Independent adversarial review and production role inventory remain required. |
| Security logging and monitoring | verified-local / external-gate | Append-only bounded encrypted `security_events`; HMAC account/IP fingerprints; JSON-lines security channel; authentication/MFA/session/CSP/upload events | Tamper-aware centralized destination, alert transport, backup responder, retention approval, and incident exercise are not selected. No request/response bodies or session replay are enabled. |
| File upload security | verified-local / external-gate | MIME/signature/size/ZIP validation already present; new generated security record; no shell command construction; ClamAV clean/positive/unavailable tests; production fail-closed readiness gate | Real ClamAV installation, sandboxing, DOCX CDR, media re-encoding/metadata stripping, signature updates, and production throughput evidence remain external. |
| Browser security | verified-local / later human evidence | Strict CSP, CSP report minimization, HSTS readiness gate, secure session-cookie checks, deny-by-default capability policy | Branded-browser and physical-device evidence belongs to B04/B17; approved YouTube framing remains the only exact external frame source. |
| Secrets/dependencies/static analysis | verified-local / continuous | Tracked-secret scanner; Composer and npm audits; Laravel/Fortify/passkeys pinned in lockfiles; Larastan 3.10/PHPStan level 5 regression gate | The 440-item pre-B03 baseline is inventory, not remediation. SAST/DAST and dependency checks must run in CI once a CI platform is selected. |
| Backups, restore, alerting, penetration test | external-gate | `docs/operations/NG-B03-SECURITY-OPERATIONS.md`; production checker remains red | Host, database/object storage, secret manager, monitoring, immutable copy, restore rehearsal, backup responder, and independent penetration tester are unknown. |

## Reproduction

Run:

```powershell
& 'C:\php\php.exe' artisan test tests/Feature/SecurityAssuranceTest.php --display-all-issues --fail-on-all-issues --disallow-test-output
& 'C:\php\php.exe' vendor/bin/phpstan analyse --configuration=phpstan.neon.dist --memory-limit=1G
& 'C:\Program Files\nodejs\npm.cmd' run governance:verify
& 'C:\php\php.exe' artisan hospitrainity:deployment-check
```

The deployment check is expected to fail in the local development environment. A red local production check is accurate evidence, not a reason to weaken a control.

## Primary sources

- [OWASP ASVS 5.0 repository and requirements](https://github.com/OWASP/ASVS/tree/master/5.0/en)
- [OWASP ASVS 5.0 authentication requirements](https://github.com/OWASP/ASVS/blob/master/5.0/en/0x15-V6-Authentication.md)
- [OWASP File Upload Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html)
- [OWASP Logging Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html)
- [Laravel 12 authentication and session invalidation](https://laravel.com/docs/12.x/authentication)
- [Laravel first-party passkeys server package](https://github.com/laravel/passkeys-server)
- [W3C Web Authentication Level 3](https://www.w3.org/TR/webauthn-3/)
- [NIST SP 800-63B-4](https://pages.nist.gov/800-63-4/sp800-63b.html)
- [SecLists 2026.1 source repository](https://github.com/danielmiessler/SecLists) — tracked snapshot: `xato-net-10-million-passwords-10000.txt`, SHA-256 `c63d5e4ccc31344d662583cc39ca4bd5bd20517ff1d24501f0c4e0c22d9b722a`.

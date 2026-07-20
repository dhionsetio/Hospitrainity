# NG-B03 decisions — security assurance and operations

- Decision date: 2026-07-20 (Asia/Jakarta)
- Batch: B03
- Accountable owner: Dhion Setio
- Status: recommendations approved for staged implementation; production topology remains blocked

## Accepted direction

1. Target OWASP ASVS 5.0.0 Level 2 overall, with selected Level 3 controls for global administration, privacy, release, and production operations. Do not claim complete Level 3.
2. Use passkeys/WebAuthn as primary MFA, security keys through the same standard, TOTP fallback, and one-use hashed recovery codes. Do not classify email/SMS as strong MFA.
3. Require MFA for System Admin, Content Admin, Institution Supervisor/Instructor, and any future sensitive privacy capability; make it optional for learners. Require recent step-up for roles, recovery, account restriction/deletion, privacy exports, invitations, release/rollback, credentials, and sensitive audits.
4. Recovery prefers another passkey or recovery code. Privileged assisted recovery requires dual control and immutable audit; no security questions. Institutional SSO stays behind an adapter until real approved OIDC/SAML details exist.
5. Use the approved NIST-aligned password rules: 15 characters for password-only accounts; 8 only when genuinely MFA protected; maximum at least 128; Unicode normalization; no arbitrary composition or periodic reset; support paste/autofill/password managers. Use a local blocklist and add external k-anonymity only after privacy approval.
6. Benchmark and migrate opportunistically to Argon2id while retaining bcrypt verification/rehash compatibility.
7. Use account, IP, global, and event/risk throttles with progressive delay and no attacker-triggerable permanent lockout. Revoke other sessions after normal password change and all sessions after recovery; provide session inventory/revocation.
8. Record bounded authentication, recovery, MFA, session, invitation, role, privacy, release, upload, and privileged-audit events without secrets, tokens, response bodies, learner answers, or private paths.
9. Use structured local JSON logs and a provider-neutral adapter during development. Third-party monitoring requires B02 approval and must exclude request bodies, raw learner content, and session replay.
10. Add a provider-neutral upload scanner. ClamAV is acceptable locally; production upload promotion fails closed if scanning is unhealthy. Preserve original DOCX hash/provenance; keep sanitized derivatives separate; re-encode images/strip metadata and validate/transcode supported media.
11. Keep unnecessary CSP capabilities denied and allow exact external sources only after feature/privacy approval.
12. Small-pilot backup objective: 24-hour RPO, 8-hour RTO, encrypted daily backup, 30-day retention, immutable/off-account copy, and monthly restore rehearsal.
13. Require targeted independent penetration testing before a real-user pilot and full agreed scope before production. Add Larastan/PHPStan, SAST, and isolated ZAP baseline without uploading authority/private artifacts.
14. Roll out synthetic tests, then creator/System Admin, staff pilot, and learners only after recovery, privacy, scanning, monitoring, backup, and rollback gates pass.

## Assigned and unresolved operations

- Dhion Setio is the initial development alert/incident owner. A backup responder and escalation contact remain required for production.
- Development logging is local/provider-neutral. A tamper-aware production destination and retention policy are not selected.
- The exact host, TLS proxy, PostgreSQL service, private object storage, workers, scheduler, mail, secrets manager, monitoring, and backup service are not selected. No vendor or endpoint is fabricated.
- These missing operational facts block production evidence, not local implementation of provider-neutral controls.

## Evidence basis

- OWASP ASVS 5.0.0 and OWASP authorization, password-storage, logging, and file-upload guidance.
- NIST SP 800-63B-4 authentication/password guidance.
- W3C WebAuthn public-key credential specification.

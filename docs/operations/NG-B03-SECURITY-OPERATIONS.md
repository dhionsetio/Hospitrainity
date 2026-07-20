# NG-B03 security operations and incident runbook

Status: local provider-neutral procedure; production vendors/endpoints are intentionally unset.  
Initial development incident owner: Dhion Setio.  
Production backup responder/escalation contact: **required, not selected**.

## Alert classes

- Critical: suspected privileged-account takeover, malware promoted despite policy, key disclosure, widespread authorization failure, or loss of audit integrity.
- High: repeated privileged authentication/recovery failures, scanner unavailable in a production promotion path, abnormal role or export activity, or restore failure.
- Routine security evidence: isolated login/MFA failure, user session revocation, CSP violation aggregate, authenticator lifecycle, and clean scan.

No third-party destination is configured. Local development writes bounded JSON events to `storage/logs/security-*.jsonl` and encrypted append-only rows to `security_events`. Never forward request/response bodies, passwords, tokens, TOTP secrets, recovery codes, learner answers, raw uploads, private paths, or session replay.

## First response

1. Record the time, reporter, affected account/resource identifiers, and current release/version without copying secrets or private content.
2. Preserve relevant append-only audit/security records and configuration fingerprints. Do not edit the original events.
3. Contain narrowly: revoke the affected sessions, disable a compromised account through the reversible lifecycle, stop upload promotion, or place the application in a visible incident state. Do not silently bypass MFA/scanning.
4. Rotate an exposed credential through its owning service. Do not paste a replacement into a ticket, chat, log, or repository.
5. Determine scope using bounded event identifiers and server/provider evidence. Treat local IP fingerprints as correlation values, not proof of identity.
6. Recover through a reviewed release/restore path, verify database and private-storage invariants, then document follow-up actions.

## Privileged recovery

Normal recovery order is another registered passkey, one unused recovery code, then the verified-mail password reset path without bypassing MFA. Assisted privileged MFA reset is deliberately unavailable: it requires two authorized controllers, identity-proofing rules equal to enrollment, immutable approval evidence, and a tested break-glass procedure. Until those facts exist, no administrator can choose a user password or silently clear required MFA.

## Upload incident

`clean` may be promoted; `malicious` is rejected; `unavailable` is rejected whenever `UPLOAD_SCANNER_REQUIRED=true`. Keep the original SHA-256 and scan record, not the rejected file contents, unless a separately approved forensic hold exists. After a scanner/signature update, re-scan from private quarantine before promotion. Never make quarantine web-readable.

## Production gates

Before a real-user pilot, identify and test: TLS proxy, PostgreSQL/database service, private object storage, queue/scheduler, mail, secrets manager, tamper-aware log destination, alert routing, encrypted immutable/off-account backup, 24-hour RPO, 8-hour RTO, 30-day retention, monthly restore rehearsal, and independent penetration-test scope. These are unknown facts, not implementation defaults.

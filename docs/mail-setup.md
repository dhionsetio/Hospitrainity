# Hospitrainity Email Setup and Deliverability Guide

This document outlines the email configuration for local development testing and production deployment.

---

## 1. Local Testing Setup (Mailpit)

In local development, outgoing emails are captured locally using **Mailpit** (a lightweight SMTP testing server and web UI) to avoid sending emails to real user inboxes.

### Prerequisites & Installation
Download or run Mailpit:
- **Binary/CLI**: Download from [Mailpit Releases](https://github.com/axllent/mailpit) or install via package manager.
- Default Mailpit SMTP listening port: `1025`
- Default Web UI inbox URL: `http://127.0.0.1:8025`

### Local Environment Configuration (`.env`)
```ini
MAIL_MAILER=smtp
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="no-reply@hospitrainity.local"
MAIL_FROM_NAME="Hospitrainity"
```

> **Note**: `.env.example` specifies `MAIL_PORT=2525`. In local development, `MAIL_PORT=1025` supersedes port `2525` to match Mailpit's default configuration.

### Testing Email Verification Flow Locally
1. Start Mailpit (`mailpit` in terminal).
2. Register a new user on Hospitrainity (`/register`).
3. Open `http://127.0.0.1:8025` in your browser to view the generated email verification message and click the verification link.

### Artisan Direct Verification Command
For quick developer/tester verification without needing Mailpit:
```bash
php artisan app:verify-user-email dhionsetio@gmail.com
```
This command marks the specified account as verified directly in the database and dispatches the `Verified` event.

---

## 2. Production Environment Configuration (Resend)

For live production deployments, Hospitrainity uses **Resend** (supported natively via Laravel 12 Mail drivers) for transactional deliverability.

### Production Environment Variables (`.env.production`)
```ini
MAIL_MAILER=resend
RESEND_KEY="re_1234567890abcdef..."
MAIL_FROM_ADDRESS="no-reply@yourdomain.com"
MAIL_FROM_NAME="Hospitrainity"
```

### Production Fallback: Standard SMTP
If using a custom SMTP provider (Postmark, SendGrid, Amazon SES SMTP, Mailgun):
```ini
MAIL_MAILER=smtp
MAIL_HOST=smtp.yourprovider.com
MAIL_PORT=587
MAIL_SCHEME=tls
MAIL_USERNAME="your-smtp-username"
MAIL_PASSWORD="your-smtp-password"
MAIL_FROM_ADDRESS="no-reply@yourdomain.com"
MAIL_FROM_NAME="Hospitrainity"
```

### Domain Authentication & DNS Requirements
To ensure high deliverability in production:
1. **SPF Record**: Add `v=spf1 include:amazonses.com include:resend.com ~all` (customized for your provider) to your domain's TXT records.
2. **DKIM Record**: Configure the CNAME / TXT selector records provided by Resend or your SMTP host.
3. **DMARC Record**: Add a TXT record for `_dmarc.yourdomain.com` with `v=DMARC1; p=quarantine; rua=mailto:dmarc-reports@yourdomain.com`.

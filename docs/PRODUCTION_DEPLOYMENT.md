# Production deployment

This runbook is intentionally provider-neutral. Replace the placeholders in `.env.production.example` through the deployment platform's secret store; do not commit a populated `.env`.

## Release build

Run these commands from a clean release directory with the production PHP and Node versions:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan storage:link
php artisan migrate --force
php artisan optimize
php artisan hospitrainity:deployment-check
```

The web-server document root must be the application's `public` directory. The release process must delete any copied `public/hot` file before the readiness check; that file is only a local Vite development marker. Keep `storage` and `bootstrap/cache` writable by the application account, and run Laravel's scheduler once per minute (or use a dedicated scheduler worker).

## HTTPS and proxy validation

Terminate TLS only at a trusted proxy or the web server. If a trusted proxy terminates TLS, configure Laravel's trusted-proxy settings narrowly so secure requests are recognized correctly. Then verify:

- redirects and generated URLs use `https://`;
- the session cookie has `Secure`, `HttpOnly`, and `SameSite=Lax` (or `Strict` where compatible);
- `Content-Security-Policy` is enforced, not report-only;
- `Strict-Transport-Security` appears only on HTTPS responses;
- `/up`, login, password reset, and one authenticated learner workflow return no debug output or development-asset URLs.

HSTS `includeSubDomains` and `preload` default to off because enabling either without auditing every subdomain can make other services unreachable. Enable them only after that separate review.

## Deployment gate

`php artisan hospitrainity:deployment-check` exits non-zero if production mode, debug, HTTPS, cookie settings, CSP/HSTS, the Vite manifest, storage link, `public/hot`, or Composer development packages are unsafe. Run it after environment configuration and before shifting traffic.

After deployment, restart long-lived queue workers with `php artisan queue:restart`. Test password-reset delivery using the configured production mail transport without logging reset URLs or credentials.

## Primary references

- [Laravel deployment](https://laravel.com/docs/12.x/deployment)
- [Laravel Vite CSP nonces](https://laravel.com/docs/12.x/vite#content-security-policy-csp-nonce)
- [Laravel authentication](https://laravel.com/docs/12.x/authentication)
- [Laravel rate limiting](https://laravel.com/docs/12.x/rate-limiting)
- [OWASP HTTP security response headers](https://cheatsheetseries.owasp.org/cheatsheets/HTTP_Headers_Cheat_Sheet.html)
- [OWASP Content Security Policy](https://cheatsheetseries.owasp.org/cheatsheets/Content_Security_Policy_Cheat_Sheet.html)
- [OWASP HTTP Strict Transport Security](https://cheatsheetseries.owasp.org/cheatsheets/HTTP_Strict_Transport_Security_Cheat_Sheet.html)

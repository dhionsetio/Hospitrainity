<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::useHotFile((string) config('vite.hot_file'));
        Model::preventLazyLoading(! $this->app->isProduction());
        $this->configureRateLimiters();
    }

    /**
     * Named rate limiters for the application.
     *
     * The `login` limiter throttles POST /login to blunt credential-stuffing and
     * brute-force attacks (the login route previously had no throttling, unlike
     * the email-verification routes which already use throttle:6,1). It is keyed
     * on the submitted email + client IP so a single address cannot lock every
     * account, and a single account cannot be hammered from one address.
     * Exceeding the limit yields HTTP 429 via the `throttle:login` middleware.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $identity = $this->rateLimitIdentity($request->input('email'));

            return Limit::perMinute(5)->by('login-account:'.$identity.'|'.$request->ip());
        });

        RateLimiter::for('invitation-view', fn (Request $request): array => [
            Limit::perMinute(30)->by('invitation-view-ip:'.$request->ip()),
            Limit::perMinute(10)->by('invitation-view-token:'.hash('sha256', (string) $request->route('token'))),
            Limit::perMinute(300)->by('invitation-view-global'),
        ]);

        RateLimiter::for('invitation-redeem', fn (Request $request): array => [
            Limit::perMinute(10)->by('invitation-redeem-ip:'.$request->ip()),
            Limit::perMinute(5)->by('invitation-redeem-token:'.hash('sha256', (string) $request->route('token'))),
            Limit::perMinute(100)->by('invitation-redeem-global'),
        ]);

        RateLimiter::for('invitation-issue', fn (Request $request): array => [
            Limit::perMinute(5)->by('invitation-issue-minute:'.$request->user()->getAuthIdentifier()),
            Limit::perHour(30)->by('invitation-issue-hour:'.$request->user()->getAuthIdentifier()),
            Limit::perHour(20)->by('invitation-issue-ip:'.$request->ip()),
            Limit::perHour(5)->by('invitation-issue-target:'.$this->rateLimitIdentity($request->input('email'))),
            Limit::perHour(500)->by('invitation-issue-global'),
        ]);

        RateLimiter::for('invitation-revoke', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('invitation-revoke:'.$request->user()->getAuthIdentifier()));

        RateLimiter::for('institution-switch', fn (Request $request): Limit => Limit::perMinute(20)
            ->by('institution-switch:'.$request->user()->getAuthIdentifier()));

        RateLimiter::for('registration', function (Request $request): array {
            $identity = $this->rateLimitIdentity($request->input('email'));

            return [
                Limit::perMinute(5)->by('registration-ip:'.$request->ip()),
                Limit::perHour(3)->by('registration-account:'.$identity),
                Limit::perHour(200)->by('registration-global'),
            ];
        });

        RateLimiter::for('join-code-redeem', function (Request $request): array {
            $actor = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return [
                Limit::perMinute(5)->by('join-code-user:'.$actor),
                Limit::perHour(20)->by('join-code-ip:'.$request->ip()),
                Limit::perHour(10)->by('join-code-value:'.$this->rateLimitIdentity($request->input('code'))),
                Limit::perHour(500)->by('join-code-global'),
            ];
        });

        RateLimiter::for('join-code-issue', fn (Request $request): array => [
            Limit::perMinute(3)->by('join-code-issue-minute:'.$request->user()->getAuthIdentifier()),
            Limit::perHour(20)->by('join-code-issue-hour:'.$request->user()->getAuthIdentifier()),
        ]);

        RateLimiter::for('join-code-revoke', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('join-code-revoke:'.$request->user()->getAuthIdentifier()));

        RateLimiter::for('membership-decision', fn (Request $request): Limit => Limit::perMinute(20)
            ->by('membership-decision:'.$request->user()->getAuthIdentifier()));

        RateLimiter::for('institution-role-change', function (Request $request): array {
            $actor = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return [
                Limit::perMinute(5)->by('institution-role-change-minute:'.$actor),
                Limit::perHour(20)->by('institution-role-change-hour:'.$actor),
            ];
        });

        RateLimiter::for('password-email', function (Request $request): array {
            $identity = $this->rateLimitIdentity($request->input('email'));

            return [
                Limit::perMinute(20)->by('password-email-ip:'.$request->ip()),
                Limit::perMinute(5)->by('password-email-account:'.$identity.'|'.$request->ip()),
            ];
        });

        RateLimiter::for('password-reset', function (Request $request): array {
            $identity = $this->rateLimitIdentity($request->input('email'));

            return [
                Limit::perMinute(20)->by('password-reset-ip:'.$request->ip()),
                Limit::perMinute(5)->by('password-reset-account:'.$identity.'|'.$request->ip()),
            ];
        });

        RateLimiter::for('password-confirm', fn (Request $request): Limit => Limit::perMinute(5)
            ->by('password-confirm:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('role-change', function (Request $request): array {
            $actor = $request->user()?->getAuthIdentifier() ?? $request->ip();

            return [
                Limit::perMinute(5)->by('role-change-minute:'.$actor),
                Limit::perHour(20)->by('role-change-hour:'.$actor),
            ];
        });

        RateLimiter::for('superadmin-promotion', fn (Request $request): Limit => Limit::perHour(3)
            ->by('superadmin-promotion:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('curriculum-attempt', fn (Request $request): Limit => Limit::perMinute(30)
            ->by('curriculum-attempt:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('curriculum-publication', fn (Request $request): Limit => Limit::perHour(5)
            ->by('curriculum-publication:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));

        RateLimiter::for('privacy-request', fn (Request $request): array => [
            Limit::perMinute(3)->by('privacy-request-user:'.($request->user()?->getAuthIdentifier() ?? $request->ip())),
            Limit::perHour(20)->by('privacy-request-ip:'.$request->ip()),
            Limit::perHour(500)->by('privacy-request-global'),
        ]);
        RateLimiter::for('privacy-export-download', fn (Request $request): Limit => Limit::perMinute(5)
            ->by('privacy-export-user:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('privacy-admin', fn (Request $request): Limit => Limit::perMinute(60)
            ->by('privacy-admin:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('privacy-admin-action', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('privacy-admin-action:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('push-subscription', fn (Request $request): Limit => Limit::perMinute(10)
            ->by('push-subscription:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
        RateLimiter::for('push-test', fn (Request $request): Limit => Limit::perMinute(3)
            ->by('push-test:'.($request->user()?->getAuthIdentifier() ?? $request->ip())));
    }

    private function rateLimitIdentity(mixed $value): string
    {
        // Cache-backed limiter keys must not persist a plaintext account email.
        // APP_KEY is already a production readiness requirement; malformed
        // non-scalar input is intentionally grouped into the empty identity.
        return hash_hmac(
            'sha256',
            User::canonicalEmail($value),
            (string) config('app.key'),
        );
    }
}

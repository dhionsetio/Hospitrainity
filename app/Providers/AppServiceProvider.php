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
            $email = User::canonicalEmail($request->input('email'));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        RateLimiter::for('registration', fn (Request $request): Limit => Limit::perMinute(5)
            ->by('registration-ip:'.$request->ip()));

        RateLimiter::for('password-email', function (Request $request): array {
            $email = User::canonicalEmail($request->input('email'));

            return [
                Limit::perMinute(20)->by('password-email-ip:'.$request->ip()),
                Limit::perMinute(5)->by('password-email-account:'.$email.'|'.$request->ip()),
            ];
        });

        RateLimiter::for('password-reset', function (Request $request): array {
            $email = User::canonicalEmail($request->input('email'));

            return [
                Limit::perMinute(20)->by('password-reset-ip:'.$request->ip()),
                Limit::perMinute(5)->by('password-reset-account:'.$email.'|'.$request->ip()),
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
    }
}

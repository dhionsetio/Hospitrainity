<?php

use App\Exceptions\CurriculumDraftConflictException;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureLegacyCurriculumWritable;
use App\Http\Middleware\EnsurePrivilegedMfa;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Services\RoleLandingResolver;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(SecurityHeaders::class);

        // Browsers submit CSP violation reports without access to the user's
        // session token. The endpoint is write-bounded, rate-limited, and only
        // records a sanitized security event, so it must not require CSRF.
        $middleware->validateCsrfTokens(except: [
            'security/csp-reports',
        ]);

        $middleware->redirectUsersTo(
            fn (Request $request): string => app(RoleLandingResolver::class)->url($request->user()),
        );

        $middleware->alias([
            'legacy.curriculum.writable' => EnsureLegacyCurriculumWritable::class,
            'privileged.mfa' => EnsurePrivilegedMfa::class,
            'role' => CheckRole::class,
        ]);

        // Apply the visitor's preferred UI locale (session-driven) on every web request.
        $middleware->web(append: [
            SetLocale::class,
            EnsureAccountIsActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (CurriculumDraftConflictException $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 409);
            }

            return back()->withErrors(['revision' => $exception->getMessage()])->setStatusCode(409);
        });
    })->create();

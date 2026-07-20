<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);

        if (! config('security.headers_enabled', true)) {
            return $response;
        }

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $sensitiveTokenRoute = $request->routeIs(
            'invitations.accept',
            'invitations.redeem',
            'privacy-requests.*',
            'privacy-exports.*',
            'superadmin.privacy-requests.*',
        );
        $response->headers->set('Referrer-Policy', $sensitiveTokenRoute ? 'no-referrer' : 'strict-origin-when-cross-origin');
        if ($sensitiveTokenRoute) {
            $response->headers->set('Cache-Control', 'no-store, private');
            $response->headers->set('Pragma', 'no-cache');
        }
        $response->headers->set('Permissions-Policy', 'camera=(), geolocation=(), microphone=(), payment=(), usb=()');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-XSS-Protection', '0');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-site');
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        if (config('security.csp.enabled', true)) {
            $header = config('security.csp.report_only', true)
                ? 'Content-Security-Policy-Report-Only'
                : 'Content-Security-Policy';

            $response->headers->set($header, $this->contentSecurityPolicy($nonce));
        }

        if (config('security.hsts.enabled', false) && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', $this->strictTransportSecurity());
        }

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        return implode('; ', [
            "default-src 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "script-src 'self' 'nonce-{$nonce}'",
            "script-src-attr 'none'",
            "style-src 'self' 'nonce-{$nonce}'",
            "style-src-attr 'none'",
            "font-src 'self' data:",
            "img-src 'self' data: blob:",
            "media-src 'self' blob:",
            "connect-src 'self'",
            'frame-src https://www.youtube.com',
            "manifest-src 'self'",
            "worker-src 'self' blob:",
        ]).';';
    }

    private function strictTransportSecurity(): string
    {
        $value = 'max-age='.max(0, (int) config('security.hsts.max_age', 31_536_000));

        if (config('security.hsts.include_subdomains', false)) {
            $value .= '; includeSubDomains';
        }

        if (config('security.hsts.preload', false) && config('security.hsts.include_subdomains', false)) {
            $value .= '; preload';
        }

        return $value;
    }
}

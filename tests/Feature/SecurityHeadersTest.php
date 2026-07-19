<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_production_requests_use_report_only_csp_with_a_vite_nonce(): void
    {
        config()->set('security.csp.report_only', true);

        $response = $this->get('/login');
        $policy = (string) $response->headers->get('Content-Security-Policy-Report-Only');

        $response->assertOk()
            ->assertHeaderMissing('Content-Security-Policy');
        $this->assertMatchesRegularExpression("/script-src 'self' 'nonce-[A-Za-z0-9+\/_=-]+'/", $policy);
        $this->assertStringNotContainsString("'unsafe-inline'", $policy);
        $this->assertStringNotContainsString("'unsafe-eval'", $policy);
        $this->assertStringContainsString("script-src-attr 'none'", $policy);
        $this->assertStringContainsString("style-src-attr 'none'", $policy);

        preg_match("/'nonce-([^']+)'/", $policy, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $this->assertStringContainsString('nonce="'.$matches[1].'"', $response->getContent());
    }

    public function test_enforced_security_headers_are_strict_and_nonce_rotates_per_response(): void
    {
        config()->set('security.csp.report_only', false);

        $first = $this->get('/login');
        $second = $this->get('/login');
        $firstPolicy = (string) $first->headers->get('Content-Security-Policy');
        $secondPolicy = (string) $second->headers->get('Content-Security-Policy');

        $first->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-XSS-Protection', '0')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cross-Origin-Resource-Policy', 'same-site')
            ->assertHeader('X-Permitted-Cross-Domain-Policies', 'none')
            ->assertHeaderMissing('Content-Security-Policy-Report-Only');
        $this->assertStringContainsString("default-src 'self'", $firstPolicy);
        $this->assertStringContainsString("frame-ancestors 'none'", $firstPolicy);
        $this->assertStringContainsString("object-src 'none'", $firstPolicy);

        preg_match("/'nonce-([^']+)'/", $firstPolicy, $firstNonce);
        preg_match("/'nonce-([^']+)'/", $secondPolicy, $secondNonce);
        $this->assertNotSame($firstNonce[1] ?? null, $secondNonce[1] ?? null);
    }

    public function test_hsts_is_only_sent_for_secure_requests_and_session_cookie_flags_are_secure(): void
    {
        config()->set('security.hsts.enabled', true);

        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/login')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');

        config()->set('session.secure', true);
        config()->set('session.http_only', true);
        config()->set('session.same_site', 'lax');
        $user = User::factory()->create(['password' => 'password']);

        $response = $this->post('https://localhost/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));

        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_blade_templates_do_not_reintroduce_inline_event_or_style_attributes(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            $contents = $file->getContents();
            $path = $file->getRelativePathname();

            $this->assertDoesNotMatchRegularExpression('/\s(?:style|on[a-z]+)\s*=/i', $contents, $path);
            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\bnonce\s*=)[^>]*>/i', $contents, $path);
            $this->assertDoesNotMatchRegularExpression('/<style\b/i', $contents, $path);
            $this->assertStringNotContainsString('javascript:', strtolower($contents), $path);
        }
    }
}

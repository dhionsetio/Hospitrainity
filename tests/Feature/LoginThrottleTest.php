<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5.1: POST /login is rate-limited per email+IP to blunt brute-force /
 * credential-stuffing. Once the per-minute limit is exceeded the endpoint
 * responds with HTTP 429 (Too Many Requests) via the `throttle:login`
 * middleware backed by the named limiter in AppServiceProvider.
 */
class LoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_throttled_after_too_many_attempts(): void
    {
        $payload = ['email' => 'victim@example.com', 'password' => 'wrong-password'];

        // The `login` limiter allows 5 attempts per minute per email+IP.
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->from('/login')->post('/login', $payload);
            $this->assertNotSame(
                429,
                $response->getStatusCode(),
                "Attempt #{$i} was throttled too early."
            );
        }

        // The 6th attempt within the same window is rejected with 429.
        $this->from('/login')->post('/login', $payload)->assertStatus(429);
    }

    public function test_throttle_bucket_is_scoped_per_email(): void
    {
        // Exhaust the limit for one email from this client.
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', ['email' => 'a@example.com', 'password' => 'x']);
        }

        // A different email from the same client uses a separate bucket, so it is
        // not immediately throttled.
        $response = $this->post('/login', ['email' => 'b@example.com', 'password' => 'x']);
        $this->assertNotSame(429, $response->getStatusCode());
    }
}

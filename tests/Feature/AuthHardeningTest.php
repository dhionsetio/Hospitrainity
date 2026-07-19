<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_session_only_when_remember_is_not_checked(): void
    {
        $user = User::factory()->create([
            'email' => 'session-only@example.com',
            'password' => 'correct-password',
            'remember_token' => null,
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-password',
        ]);

        $response->assertRedirect(route('dashboard'))
            ->assertCookieMissing(Auth::guard('web')->getRecallerName());
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->remember_token);
    }

    public function test_login_queues_a_recaller_cookie_only_when_remember_is_checked(): void
    {
        $user = User::factory()->create([
            'email' => 'remembered@example.com',
            'password' => 'correct-password',
            'remember_token' => null,
        ]);

        $response = $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'correct-password',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('dashboard'))
            ->assertCookie(Auth::guard('web')->getRecallerName());
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->remember_token);
    }

    public function test_password_reset_link_response_does_not_disclose_account_existence(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'known@example.com']);
        $message = 'If an account exists for that email address, a password reset link will be sent.';

        $known = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => $user->email,
        ]);
        $unknown = $this->from(route('password.request'))->post(route('password.email'), [
            'email' => 'unknown@example.com',
        ]);

        $known->assertRedirect(route('password.request'))
            ->assertSessionHas('status', $message)
            ->assertSessionHasNoErrors();
        $unknown->assertRedirect(route('password.request'))
            ->assertSessionHas('status', $message)
            ->assertSessionHasNoErrors();
    }

    public function test_registration_is_throttled_after_five_attempts_per_ip(): void
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('register'), [])->assertRedirect();
        }

        $this->post(route('register'), [])->assertTooManyRequests();
    }

    public function test_password_reset_link_requests_use_one_canonical_email_bucket(): void
    {
        $variants = [
            'TARGET@example.com',
            'target@EXAMPLE.com',
            ' target@example.com ',
            'TARGET@EXAMPLE.COM',
            'target@example.com',
        ];

        foreach ($variants as $email) {
            $this->post(route('password.email'), ['email' => $email])->assertRedirect();
        }

        $this->post(route('password.email'), ['email' => 'target@example.com'])
            ->assertTooManyRequests();
    }

    public function test_password_reset_submissions_are_throttled_after_five_attempts(): void
    {
        $payload = [
            'token' => 'invalid-token',
            'email' => 'target@example.com',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ];

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('password.update'), $payload)->assertRedirect();
        }

        $this->post(route('password.update'), $payload)->assertTooManyRequests();
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the Phase 2 registration hardening:
 *   - `instansi` must match an institution that already exists (Rule::exists).
 *   - `role` is never mass-assigned from the request (privilege-escalation guard);
 *     new accounts always land as the default 'user'.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Learner',
            'instansi' => 'Hospitrainity HQ',
            'email' => 'new.learner@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => 'on',
        ], $overrides);
    }

    public function test_new_user_can_register_with_an_existing_institution(): void
    {
        // Seed an existing institution so Rule::exists passes.
        User::factory()->create(['instansi' => 'Hospitrainity HQ']);

        $response = $this->post('/register', $this->validPayload());

        $response->assertRedirect(route('verification.notice'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'new.learner@example.com',
            'role' => 'user',
        ]);
    }

    public function test_registration_requires_an_existing_institution(): void
    {
        $response = $this->from('/register')->post('/register', $this->validPayload([
            'instansi' => 'Totally Fake Org',
        ]));

        $response->assertSessionHasErrors('instansi');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'new.learner@example.com']);
    }

    public function test_registration_cannot_escalate_role(): void
    {
        User::factory()->create(['instansi' => 'Hospitrainity HQ']);

        $this->post('/register', $this->validPayload([
            'role' => 'superadmin',
        ]));

        $this->assertDatabaseHas('users', [
            'email' => 'new.learner@example.com',
            'role' => 'user',
        ]);
        $this->assertDatabaseMissing('users', [
            'email' => 'new.learner@example.com',
            'role' => 'superadmin',
        ]);
    }
}

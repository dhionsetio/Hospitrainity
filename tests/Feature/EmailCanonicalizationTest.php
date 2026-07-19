<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class EmailCanonicalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_model_storage_and_login_use_trimmed_lowercase_email(): void
    {
        $user = User::factory()->create(['email' => '  Mixed.Case@Example.COM  ']);

        $this->assertSame('mixed.case@example.com', $user->fresh()->email);

        $this->post('/login', [
            'email' => '  MIXED.CASE@EXAMPLE.COM  ',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_rejects_a_case_variant_of_an_existing_email(): void
    {
        $existing = User::factory()->create([
            'email' => 'learner@example.com',
            'instansi' => 'Existing Hotel',
        ]);

        $this->post('/register', [
            'name' => 'Duplicate Learner',
            'instansi' => $existing->instansi,
            'email' => 'LEARNER@EXAMPLE.COM',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
            'terms' => '1',
        ])->assertSessionHasErrors('email');

        $this->assertSame(1, User::where('email', 'learner@example.com')->count());
    }

    public function test_password_reset_accepts_a_case_variant_of_the_account_email(): void
    {
        $user = User::factory()->create(['email' => 'reset@example.com']);
        $token = Password::createToken($user);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => 'RESET@EXAMPLE.COM',
            'password' => 'Replacement123!',
            'password_confirmation' => 'Replacement123!',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('Replacement123!', $user->fresh()->password));
    }

    public function test_migration_normalizes_users_and_reset_tokens_idempotently(): void
    {
        $user = User::factory()->create(['email' => 'source@example.com']);
        DB::table('users')->where('id', $user->id)->update(['email' => ' Source@Example.COM ']);
        DB::table('password_reset_tokens')->insert([
            'email' => ' Source@Example.COM ',
            'token' => 'test-token',
            'created_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_07_16_000004_normalize_email_addresses.php');
        $migration->up();
        $migration->up();

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'source@example.com']);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'source@example.com']);
    }

    public function test_migration_reports_canonical_collisions_before_mutating_any_row(): void
    {
        $first = User::factory()->create(['email' => 'first@example.com']);
        $second = User::factory()->create(['email' => 'second@example.com']);
        DB::table('users')->where('id', $first->id)->update(['email' => 'Case@Example.com']);
        DB::table('users')->where('id', $second->id)->update(['email' => 'case@example.com']);

        $migration = require database_path('migrations/2026_07_16_000004_normalize_email_addresses.php');

        try {
            $migration->up();
            $this->fail('The migration did not report the canonical collision.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString("[{$first->id},{$second->id}]", $exception->getMessage());
            $this->assertStringContainsString('aborted without changes', $exception->getMessage());
        }

        $this->assertDatabaseHas('users', ['id' => $first->id, 'email' => 'Case@Example.com']);
        $this->assertDatabaseHas('users', ['id' => $second->id, 'email' => 'case@example.com']);
    }
}

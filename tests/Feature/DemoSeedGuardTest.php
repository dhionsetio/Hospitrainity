<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DemoSeedGuard;
use Database\Seeders\UserSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class DemoSeedGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_database_seeder_fails_before_any_write_even_when_flag_is_enabled(): void
    {
        $this->app['env'] = 'production';
        $this->configureDemoAccounts();

        try {
            Artisan::call('db:seed', [
                '--force' => true,
                '--no-interaction' => true,
            ]);
            $this->fail('Production seeding unexpectedly succeeded.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('production is never allowed', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('curriculum_packages', 0);
        $this->assertStringNotContainsString(
            config('identity.demo_seed.accounts.0.password'),
            Artisan::output(),
        );
    }

    public function test_guard_rejects_incomplete_secrets_in_testing(): void
    {
        $this->configureDemoAccounts();
        config()->set('identity.demo_seed.accounts.1.password', null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('requires a per-run secret');

        app(DemoSeedGuard::class)->authorizedAccounts();
    }

    public function test_guard_rejects_reused_demo_secrets_before_any_write(): void
    {
        $this->configureDemoAccounts();
        config()->set(
            'identity.demo_seed.accounts.1.password',
            config('identity.demo_seed.accounts.0.password'),
        );

        try {
            $this->seed(UserSeeder::class);
            $this->fail('Demo seeding unexpectedly accepted a reused secret.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('separate per-run secret', $exception->getMessage());
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('institution_memberships', 0);
    }

    public function test_authorized_user_seeder_hashes_process_supplied_per_run_secrets(): void
    {
        $secrets = $this->configureDemoAccounts();

        $this->seed(UserSeeder::class);

        $this->assertDatabaseCount('users', 3);
        $this->assertDatabaseCount('institution_memberships', 3);
        $this->assertDatabaseHas('institutions', ['key' => 'demo-hotel-a', 'verification_method' => 'disposable_demo_fixture']);
        $this->assertDatabaseHas('institutions', ['key' => 'demo-hotel-b', 'verification_method' => 'disposable_demo_fixture']);
        foreach (config('identity.demo_seed.accounts') as $index => $account) {
            $user = User::query()->where('email', $account['email'])->firstOrFail();
            $this->assertTrue(Hash::check($secrets[$index], $user->password));
            $this->assertNotSame($secrets[$index], $user->password);
        }
    }

    public function test_authorized_user_seeder_rolls_back_every_demo_identity_on_a_mid_seed_failure(): void
    {
        $this->configureDemoAccounts();

        // Deterministic SQLite test double: allow one membership write, then
        // simulate a storage failure while the second demo account is seeded.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER fail_second_demo_membership
            BEFORE INSERT ON institution_memberships
            WHEN (SELECT COUNT(*) FROM institution_memberships) = 1
            BEGIN
                SELECT RAISE(ABORT, 'test double: membership storage unavailable');
            END;
            SQL);

        try {
            $this->seed(UserSeeder::class);
            $this->fail('The deliberate mid-seed storage failure did not abort seeding.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('institution_memberships', 0);
        $this->assertDatabaseMissing('institutions', ['key' => 'demo-hotel-a']);
        $this->assertDatabaseMissing('institutions', ['key' => 'demo-hotel-b']);
    }

    public function test_demo_seeder_refuses_to_overwrite_an_existing_non_demo_account(): void
    {
        $this->configureDemoAccounts();
        $email = config('identity.demo_seed.accounts.0.email');
        $existing = User::factory()->create([
            'name' => 'Existing non-demo owner',
            'email' => $email,
            'password' => 'existing-account-password',
        ]);

        try {
            $this->seed(UserSeeder::class);
            $this->fail('Demo seeding overwrote an existing non-demo identity.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('existing non-demo account', $exception->getMessage());
        }

        $existing->refresh();
        $this->assertSame('Existing non-demo owner', $existing->name);
        $this->assertTrue(Hash::check('existing-account-password', $existing->password));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('institution_memberships', 0);
        $this->assertDatabaseMissing('institutions', ['key' => 'demo-hotel-a']);
        $this->assertDatabaseMissing('institutions', ['key' => 'demo-hotel-b']);
    }

    /** @return list<string> */
    private function configureDemoAccounts(): array
    {
        $secrets = [Str::password(40), Str::password(40), Str::password(40)];
        config()->set([
            'identity.demo_seed.enabled' => true,
            'identity.demo_seed.accounts.0.password' => $secrets[0],
            'identity.demo_seed.accounts.1.password' => $secrets[1],
            'identity.demo_seed.accounts.2.password' => $secrets[2],
        ]);

        return $secrets;
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RoleLandingRedirectTest extends TestCase
{
    use RefreshDatabase;

    private const LANDINGS = [
        'user' => 'dashboard',
        'supervisor' => 'supervisor.dashboard',
        'admin' => 'admin.dashboard',
        'superadmin' => 'superadmin.dashboard',
    ];

    public function test_authenticated_guest_pages_redirect_each_known_role_to_its_landing_page(): void
    {
        $guestPages = [
            '/login',
            '/register',
            '/forgot-password',
            '/reset-password/example-token?email=user%40example.test',
        ];

        foreach (self::LANDINGS as $role => $routeName) {
            $user = User::factory()->create(['role' => $role]);

            foreach ($guestPages as $page) {
                $this->actingAs($user)->get($page)->assertRedirect(route($routeName));
            }
        }
    }

    public function test_successful_login_ignores_stale_cross_role_and_external_intended_destinations(): void
    {
        $intendedDestinations = [
            '/dashboard',
            '/supervisor/dashboard',
            '/superadmin/dashboard',
            'https://example.invalid/outside',
        ];

        foreach (self::LANDINGS as $role => $routeName) {
            foreach ($intendedDestinations as $intended) {
                $user = User::factory()->create([
                    'email' => "{$role}-".md5($intended).'@example.test',
                    'password' => 'password',
                    'role' => $role,
                ]);

                $this->withSession(['url.intended' => $intended])
                    ->post('/login', [
                        'email' => $user->email,
                        'password' => 'password',
                    ])
                    ->assertRedirect(route($routeName));

                $this->post(route('logout'));
            }
        }
    }

    public function test_verified_accounts_leave_verification_notice_and_resend_via_role_landing(): void
    {
        foreach (self::LANDINGS as $role => $routeName) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->withSession(['url.intended' => '/dashboard'])
                ->get(route('verification.notice'))
                ->assertRedirect(route($routeName));

            $this->actingAs($user)
                ->withSession(['url.intended' => '/dashboard'])
                ->post(route('verification.send'))
                ->assertRedirect(route($routeName));
        }
    }

    public function test_verification_completion_redirects_each_known_role_to_its_landing_page(): void
    {
        foreach (self::LANDINGS as $role => $routeName) {
            $user = User::factory()->unverified()->create(['role' => $role]);
            $verificationUrl = URL::temporarySignedRoute(
                'verification.verify',
                now()->addMinutes(30),
                ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())],
            );

            $this->actingAs($user)
                ->withSession(['url.intended' => '/dashboard'])
                ->get($verificationUrl)
                ->assertRedirect(route($routeName));

            $this->assertTrue($user->fresh()->hasVerifiedEmail());
        }
    }

    public function test_database_constraint_rejects_unknown_roles(): void
    {
        $this->expectException(QueryException::class);

        DB::table('users')->insert([
            'name' => 'Unsupported role',
            'instansi' => 'Example',
            'email' => 'unknown-role@example.test',
            'email_verified_at' => now(),
            'role' => 'unknown',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

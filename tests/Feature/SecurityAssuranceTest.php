<?php

namespace Tests\Feature;

use App\Enums\InstitutionRole;
use App\Enums\UserRole;
use App\Models\MfaRecoveryCode;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Rules\SecurePassword;
use App\Services\MfaService;
use App\Services\UploadSecurityService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SecurityAssuranceTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_policy_is_long_blocklisted_context_aware_and_composition_neutral(): void
    {
        $blocked = collect(file(resource_path('security/common-passwords.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            ->first(fn (string $password): bool => mb_strlen($password) >= 15);
        $this->assertIsString($blocked);

        $tooShort = Validator::make(['password' => 'short'], ['password' => [new SecurePassword]]);
        $this->assertTrue($tooShort->fails());
        $minimum = (int) config('authentication.password.minimum', 8);
        $this->assertStringContainsString("at least {$minimum}", $tooShort->errors()->first('password'));

        $common = Validator::make(['password' => $blocked], ['password' => [new SecurePassword]]);
        $this->assertTrue($common->fails());
        $this->assertStringContainsString('blocklist', $common->errors()->first('password'));

        $contextual = Validator::make(
            ['password' => 'MyDisplayName-safe-phrase'],
            ['password' => [new SecurePassword(['My Display Name'])]],
        );
        $this->assertTrue($contextual->fails());

        $accepted = Validator::make(
            ['password' => 'correct horse battery staple ! 2026'],
            ['password' => [new SecurePassword(['Unrelated Person'])]],
        );
        $this->assertFalse($accepted->fails());
    }

    public function test_password_login_rehashes_bcrypt_to_argon2id_and_privileged_account_requires_enrollment(): void
    {
        $password = Str::password(40);
        $user = User::factory()->create(['role' => UserRole::Superadmin]);
        DB::table('users')->where('id', $user->getKey())->update(['password' => bcrypt($password)]);
        $user->refresh();

        $this->post(route('login'), ['email' => $user->email, 'password' => $password])
            ->assertRedirect(route('security.index'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('argon2id', password_get_info($user->fresh()->password)['algoName']);
        $this->get(route('superadmin.dashboard'))->assertRedirect(route('security.index'));
        $this->assertDatabaseHas('security_events', ['event' => 'authentication.password_succeeded', 'outcome' => 'allowed']);
    }

    public function test_local_tester_bypass_unlocks_only_a_known_demo_account_on_loopback(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->app['env'] = 'local';
        config()->set('authentication.mfa.local_tester_bypass', true);
        config()->set('identity.demo_seed.accounts.0.email', 'configured-superadmin@example.test');
        $password = Str::password(40);
        $user = User::factory()->create([
            'email' => 'configured-superadmin@example.test',
            'role' => UserRole::Superadmin,
            'password' => Hash::make($password),
        ]);

        $this->withSession(['auth.mfa_verified_at' => time()])
            ->post(route('login'), ['email' => $user->email, 'password' => $password])
            ->assertRedirect(route('superadmin.dashboard'))
            ->assertSessionHas('auth.mfa_method', 'local_tester_bypass')
            ->assertSessionMissing('auth.mfa_verified_at');

        $this->assertAuthenticatedAs($user);
        $this->get(route('superadmin.dashboard'))->assertOk();
        $this->assertDatabaseHas('security_events', [
            'event' => 'authentication.password_succeeded',
            'outcome' => 'allowed',
        ]);
        $event = SecurityEvent::query()
            ->where('event', 'authentication.password_succeeded')
            ->latest('id')
            ->firstOrFail();
        $this->assertTrue($event->metadata['local_tester_mfa_bypass'] ?? false);
    }

    public function test_local_tester_bypass_skips_totp_challenge_without_recording_real_mfa(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->app['env'] = 'local';
        config()->set('authentication.mfa.local_tester_bypass', true);
        $password = Str::password(40);
        $user = User::factory()->create([
            'email' => 'superadmin@example.com',
            'role' => UserRole::Superadmin,
            'password' => Hash::make($password),
        ]);
        $secret = app(MfaService::class)->beginTotp($user);
        app(MfaService::class)->confirmTotp($user->fresh(), (new Google2FA)->getCurrentOtp($secret));

        $this->post(route('login'), ['email' => $user->email, 'password' => $password])
            ->assertRedirect(route('superadmin.dashboard'))
            ->assertSessionHas('auth.mfa_method', 'local_tester_bypass')
            ->assertSessionMissing('auth.mfa_verified_at');

        $this->assertAuthenticatedAs($user);
    }

    public function test_local_tester_bypass_rejects_unknown_non_loopback_and_non_local_accounts(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->app['env'] = 'local';
        config()->set('authentication.mfa.local_tester_bypass', true);
        $password = Str::password(40);

        $unknown = User::factory()->create([
            'email' => 'unknown@example.test',
            'role' => UserRole::Superadmin,
            'password' => Hash::make($password),
        ]);
        $this->post(route('login'), ['email' => $unknown->email, 'password' => $password])
            ->assertRedirect(route('security.index'));
        $this->post(route('logout'));

        $known = User::factory()->create([
            'email' => 'superadmin@example.com',
            'role' => UserRole::Superadmin,
            'password' => Hash::make($password),
        ]);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
            ->post(route('login'), ['email' => $known->email, 'password' => $password])
            ->assertRedirect(route('security.index'));
        $this->post(route('logout'));

        $this->app['env'] = 'production';
        $this->post(route('login'), ['email' => $known->email, 'password' => $password])
            ->assertRedirect(route('security.index'));
    }

    public function test_confirmed_totp_challenges_before_login_and_recovery_code_is_hashed_and_one_use(): void
    {
        $password = Str::password(40);
        $user = User::factory()->create(['role' => UserRole::Superadmin, 'password' => Hash::make($password)]);
        $mfa = app(MfaService::class);
        $secret = $mfa->beginTotp($user);
        $codes = $mfa->confirmTotp($user->fresh(), (new Google2FA)->getCurrentOtp($secret));
        $this->assertCount(10, $codes);
        $raw = DB::table('mfa_recovery_codes')->where('user_id', $user->getKey())->pluck('code_digest')->implode('|');
        $this->assertStringNotContainsString($codes[0], $raw);

        $this->post(route('login'), ['email' => $user->email, 'password' => $password])
            ->assertRedirect(route('mfa.challenge'));
        $this->assertGuest();
        $this->post(route('mfa.challenge.store'), ['recovery_code' => $codes[0]])
            ->assertRedirect(route('superadmin.dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(MfaRecoveryCode::query()->where('user_id', $user->getKey())->whereNotNull('used_at')->first());

        $this->post(route('logout'));
        $this->post(route('login'), ['email' => $user->email, 'password' => $password]);
        $this->post(route('mfa.challenge.store'), ['recovery_code' => $codes[0]])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_privileged_middleware_fails_closed_but_security_setup_remains_reachable(): void
    {
        foreach ([
            [UserRole::Supervisor, 'supervisor.dashboard'],
            [UserRole::Admin, 'admin.dashboard'],
            [UserRole::Superadmin, 'superadmin.dashboard'],
        ] as [$role, $route]) {
            $user = User::factory()->create(['role' => $role]);
            if ($role === UserRole::Supervisor) {
                $this->grantInstitutionRole($user, InstitutionRole::Instructor);
            }
            $this->actingAsWithoutMfa($user);
            $this->get(route($route))->assertRedirect(route('security.index'));
        }

        $this->get(route('security.index'))
            ->assertOk()
            ->assertSee('Account security')
            ->assertSee('href="'.route('superadmin.dashboard').'"', false)
            ->assertSee('Return to current dashboard')
            ->assertSee('Administrative tools are locked')
            ->assertSee('Start MFA setup')
            ->assertSee('Continue as Learner');
    }

    public function test_privileged_mfa_step_up_expires_after_the_configured_window(): void
    {
        config()->set('authentication.mfa.step_up_seconds', 900);
        $user = User::factory()->create(['role' => UserRole::Superadmin]);
        $secret = app(MfaService::class)->beginTotp($user);
        app(MfaService::class)->confirmTotp($user->fresh(), (new Google2FA)->getCurrentOtp($secret));

        $this->actingAsWithoutMfa($user->fresh())
            ->withSession(['auth.mfa_verified_at' => time() - 901, 'auth.mfa_method' => 'totp'])
            ->get(route('superadmin.dashboard'))
            ->assertRedirect(route('security.index'))
            ->assertSessionHas('warning', __('Your MFA confirmation expired. Sign in again or confirm with a passkey before continuing.'));

        $this->withSession(['auth.mfa_verified_at' => time(), 'auth.mfa_method' => 'totp'])
            ->get(route('superadmin.dashboard'))
            ->assertOk();
    }

    public function test_security_password_confirmation_description_is_translated_in_both_locales(): void
    {
        $user = User::factory()->create();

        foreach (['en', 'id'] as $locale) {
            $this->actingAs($user)->withSession(['locale' => $locale]);
            $this->get(route('security.confirm'))->assertRedirect(route('password.confirm'));
            $this->get(route('password.confirm'))
                ->assertOk()
                ->assertSee(__('admin.confirm_password_description.security', locale: $locale))
                ->assertDontSee('admin.confirm_password_description.security');
        }
    }

    public function test_unenrolled_privileged_account_can_reach_safe_surfaces_and_continue_as_learner(): void
    {
        $user = User::factory()->create(['role' => UserRole::Superadmin]);
        $this->actingAsWithoutMfa($user);

        $this->get('/')->assertOk()->assertSee('Hospitrainity');
        $this->get(route('help.index'))->assertOk();
        $this->get(route('work-context.index'))->assertOk()->assertSee('Learner');

        $this->post(route('work-context.store'), ['role' => 'learner'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))->assertOk();
        $this->get(route('superadmin.dashboard'))->assertForbidden();
    }

    public function test_passkey_registration_options_require_recent_password_and_are_user_bound(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson(route('passkey.registration-options'))->assertStatus(423);
        $response = $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->getJson(route('passkey.registration-options'));
        $response->assertOk()->assertJsonStructure(['options' => ['challenge', 'rp', 'user']]);
        $this->assertNotSame($user->email, data_get($response->json(), 'options.user.id'));
    }

    public function test_session_revocation_is_owner_scoped_and_cannot_remove_current_session(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user);
        foreach ([['owned-session', $user->id], ['foreign-session', $other->id]] as [$id, $userId]) {
            DB::table('sessions')->insert([
                'id' => $id, 'user_id' => $userId, 'ip_address' => '127.0.0.1', 'user_agent' => 'Test browser',
                'payload' => '', 'last_activity' => time(),
            ]);
        }

        $this->delete(route('security.sessions.destroy', 'foreign-session'))->assertNotFound();
        $this->assertDatabaseHas('sessions', ['id' => 'foreign-session']);
        $this->delete(route('security.sessions.destroy', 'owned-session'))->assertRedirect(route('security.index'));
        $this->assertDatabaseMissing('sessions', ['id' => 'owned-session']);
    }

    public function test_password_change_validates_the_old_password_then_revokes_other_sessions(): void
    {
        $currentPassword = Str::password(40);
        $newPassword = 'a deliberately long replacement passphrase 2026';
        $user = User::factory()->create(['password' => Hash::make($currentPassword)]);
        DB::table('sessions')->insert([
            'id' => 'other-owned-session', 'user_id' => $user->getKey(), 'ip_address' => '127.0.0.1',
            'user_agent' => 'Test browser', 'payload' => '', 'last_activity' => time(),
        ]);

        $this->actingAs($user)->patch(route('security.password.update'), [
            'current_password' => $currentPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ])->assertRedirect(route('security.index'));

        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-owned-session']);
    }

    public function test_upload_scanner_clean_positive_and_unavailable_outcomes_are_recorded_without_plain_filename(): void
    {
        config()->set(['upload_security.required' => true, 'upload_security.driver' => 'clamav', 'upload_security.clamav.binary' => PHP_BINARY]);
        $actor = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent('private learner source.docx', 'benign fixture');
        $metadata = [
            'original_name' => $file->getClientOriginalName(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'bytes' => $file->getSize(),
            'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        Process::fake(['*' => Process::result(exitCode: 0)]);
        $clean = app(UploadSecurityService::class)->inspect($file, $actor, 'docx_import', $metadata);
        $this->assertSame('clean', $clean->status);
        $rawName = DB::table('upload_security_records')->where('id', $clean->getKey())->value('original_name');
        $this->assertStringNotContainsString('private learner source', $rawName);

        Process::fake(['*' => Process::result(exitCode: 1)]);
        try {
            app(UploadSecurityService::class)->inspect($file, $actor, 'docx_import', $metadata);
            $this->fail('A malicious scanner result must reject promotion.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('source', $exception->errors());
        }
        $this->assertDatabaseHas('upload_security_records', ['status' => 'malicious', 'result_code' => 'clamav_detected']);

        Process::fake(['*' => Process::result(exitCode: 2)]);
        $this->expectException(ValidationException::class);
        app(UploadSecurityService::class)->inspect($file, $actor, 'docx_import', $metadata);
    }

    public function test_csp_report_is_bounded_and_does_not_store_report_urls(): void
    {
        $this->assertContains(
            'security/csp-reports',
            app(ValidateCsrfToken::class)->getExcludedPaths(),
        );

        $this->postJson(route('security.csp-report'), ['csp-report' => [
            'effective-directive' => 'script-src-elem',
            'disposition' => 'enforce',
            'status-code' => 200,
            'document-uri' => 'https://example.test/private?secret=do-not-store',
            'blocked-uri' => 'https://evil.example/tracker.js',
        ]])->assertAccepted();

        $event = SecurityEvent::query()->where('event', 'browser.csp_violation')->firstOrFail();
        $this->assertSame('script-src-elem', $event->metadata['effective_directive']);
        $raw = DB::table('security_events')->where('id', $event->getKey())->value('metadata');
        $this->assertStringNotContainsString('secret=do-not-store', $raw);
        $this->assertStringNotContainsString('tracker.js', $raw);
    }

    public function test_security_migration_rolls_back_without_changing_users_and_reapplies(): void
    {
        $user = User::factory()->create();
        $migration = require database_path('migrations/2026_07_20_000015_create_security_assurance_tables.php');
        $migration->down();
        $this->assertFalse(Schema::hasTable('security_events'));
        $this->assertDatabaseHas('users', ['id' => $user->getKey(), 'email' => $user->email]);
        $migration->up();
        $this->assertTrue(Schema::hasTable('passkeys'));
        $this->assertTrue(Schema::hasTable('upload_security_records'));
    }
}

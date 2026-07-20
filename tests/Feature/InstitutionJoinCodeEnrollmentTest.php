<?php

namespace Tests\Feature;

use App\Enums\InstitutionJoinRequestStatus;
use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\UserRole;
use App\Models\Exercise;
use App\Models\IdentityAudit;
use App\Models\Institution;
use App\Models\InstitutionJoinCode;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use App\Services\InstitutionJoinCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class InstitutionJoinCodeEnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_hour_code_is_hashed_multi_use_and_creates_only_a_pending_request(): void
    {
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $instructor = $this->institutionUser($institution, UserRole::Supervisor, InstitutionRole::Instructor);
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        $issued = app(InstitutionJoinCodeService::class)->issue($instructor, $institution, 30);
        $plain = str_replace('-', '', $issued['code']);
        $record = $issued['record']->fresh();

        $this->assertMatchesRegularExpression('/^[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{16}$/', $plain);
        $this->assertSame(hash_hmac('sha256', $plain, (string) config('app.key')), $record->token_hash);
        $this->assertStringNotContainsString($plain, json_encode($record->getAttributes(), JSON_THROW_ON_ERROR));
        $this->assertTrue($record->expires_at->between(now()->addMinutes(59), now()->addMinutes(61)));
        $this->assertSame(30, $record->use_limit);

        $this->actingAs($learner)
            ->post(route('institution-enrollment.store'), [
                'code' => strtolower($issued['code']),
                'progress_boundary_acknowledgement' => '1',
            ])->assertRedirect()
            ->assertSessionHas('status');
        $request = $learner->joinRequests()->sole();
        $this->assertSame(InstitutionJoinRequestStatus::Pending, $request->status);
        $this->assertSame(1, $record->fresh()->use_count);
        $this->assertDatabaseMissing('institution_memberships', [
            'institution_id' => $institution->id,
            'user_id' => $learner->id,
        ]);

        $sameRequest = app(InstitutionJoinCodeService::class)->requestMembership($learner, $issued['code']);
        $this->assertTrue($request->is($sameRequest));
        $this->assertSame(1, $record->fresh()->use_count);
    }

    public function test_approval_creates_only_learner_scope_and_personal_progress_stays_separate(): void
    {
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $instructor = $this->institutionUser($institution, UserRole::Supervisor, InstitutionRole::Instructor);
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $issued = app(InstitutionJoinCodeService::class)->issue($instructor, $institution, 10);

        [$module, $lesson, $exercise] = $this->publishedExercise();
        $payload = ['type' => 'Exercise', 'items' => [$exercise->id]];
        $this->actingAs($learner)->postJson(route('progress.store'), $payload)->assertOk();
        $this->assertDatabaseHas('completions', [
            'user_id' => $learner->id,
            'learning_scope_key' => 'personal',
            'institution_membership_id' => null,
        ]);

        $joinRequest = app(InstitutionJoinCodeService::class)->requestMembership($learner, $issued['code']);
        app(InstitutionJoinCodeService::class)->decide($instructor, $joinRequest, true);
        $membership = InstitutionMembership::query()
            ->where('institution_id', $institution->id)
            ->where('user_id', $learner->id)
            ->firstOrFail();

        $this->assertSame(InstitutionMembershipStatus::Active, $membership->status);
        $this->assertDatabaseHas('institution_role_assignments', [
            'institution_membership_id' => $membership->id,
            'role' => InstitutionRole::Learner->value,
            'revoked_at' => null,
        ]);
        $this->assertDatabaseMissing('institution_role_assignments', [
            'institution_membership_id' => $membership->id,
            'role' => InstitutionRole::Instructor->value,
        ]);

        $this->actingAs($learner)
            ->post(route('learning-context.select'), [
                'scope' => 'institution',
                'membership_id' => $membership->id,
            ])->assertRedirect();
        $this->postJson(route('progress.store'), $payload)->assertOk();

        $this->assertSame(2, DB::table('completions')
            ->where('user_id', $learner->id)
            ->where('completable_type', Exercise::class)
            ->where('completable_id', $exercise->id)
            ->count());
        $this->assertDatabaseHas('completions', [
            'user_id' => $learner->id,
            'learning_scope_key' => 'membership:'.$membership->id,
            'institution_membership_id' => $membership->id,
        ]);
    }

    public function test_rejection_never_creates_a_membership_or_role(): void
    {
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $instructor = $this->institutionUser($institution, UserRole::Supervisor, InstitutionRole::Instructor);
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $issued = app(InstitutionJoinCodeService::class)->issue($instructor, $institution, 10);
        $request = app(InstitutionJoinCodeService::class)->requestMembership($learner, $issued['code']);

        $decided = app(InstitutionJoinCodeService::class)->decide($instructor, $request, false);

        $this->assertSame(InstitutionJoinRequestStatus::Rejected, $decided->status);
        $this->assertDatabaseMissing('institution_memberships', [
            'institution_id' => $institution->id,
            'user_id' => $learner->id,
        ]);
        $this->assertDatabaseCount('institution_role_assignments', 1);
    }

    public function test_staff_can_choose_a_duration_with_second_precision(): void
    {
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $instructor = $this->institutionUser($institution, UserRole::Supervisor, InstitutionRole::Instructor);
        $startedAt = now();
        $ttlSeconds = (2 * 86_400) + (3 * 3_600) + (4 * 60) + 5;

        $this->actingAs($instructor)->get(route('supervisor.join-codes.index'))
            ->assertOk()
            ->assertSee('Code duration')
            ->assertSee('name="duration_days"', false)
            ->assertSee('name="duration_hours"', false)
            ->assertSee('name="duration_minutes"', false)
            ->assertSee('name="duration_seconds"', false)
            ->assertSee('id="duration_hours" name="duration_hours" type="number" inputmode="numeric" min="0" max="23" required', false)
            ->assertSee('value="1" aria-describedby="duration-help"', false);

        $this->actingAs($instructor)
            ->post(route('supervisor.join-codes.store'), [
                'use_limit' => 25,
                'duration_days' => 2,
                'duration_hours' => 3,
                'duration_minutes' => 4,
                'duration_seconds' => 5,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('issued_join_code');

        $record = InstitutionJoinCode::query()->latest('id')->firstOrFail();
        $this->assertLessThanOrEqual(
            1,
            abs($record->expires_at->diffInSeconds($startedAt->copy()->addSeconds($ttlSeconds), false)),
        );
        $this->assertSame(25, $record->use_limit);
        $audit = IdentityAudit::query()->where('event', 'join_code.issued')->latest('id')->firstOrFail();
        $this->assertSame($ttlSeconds, $audit->metadata['ttl_seconds']);
    }

    public function test_duration_boundaries_allow_one_second_and_30_days_but_reject_outside_totals(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $instructor = $this->institutionUser($institution, UserRole::Supervisor, InstitutionRole::Instructor);
        $route = route('supervisor.join-codes.store');

        $this->actingAs($instructor)->post($route, [
            'use_limit' => 10,
            'duration_days' => 0,
            'duration_hours' => 0,
            'duration_minutes' => 0,
            'duration_seconds' => 1,
        ])->assertSessionHasNoErrors();

        $this->post($route, [
            'use_limit' => 10,
            'duration_days' => 30,
            'duration_hours' => 0,
            'duration_minutes' => 0,
            'duration_seconds' => 0,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('institution_join_codes', 2);

        $this->post($route, [
            'use_limit' => 10,
            'duration_days' => 0,
            'duration_hours' => 0,
            'duration_minutes' => 0,
            'duration_seconds' => 0,
        ])->assertSessionHasErrors('duration');

        $this->post($route, [
            'use_limit' => 10,
            'duration_days' => 30,
            'duration_hours' => 0,
            'duration_minutes' => 0,
            'duration_seconds' => 1,
        ])->assertSessionHasErrors('duration');
        $this->assertDatabaseCount('institution_join_codes', 2);
    }

    public function test_join_code_service_rejects_an_out_of_range_duration(): void
    {
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $instructor = $this->institutionUser($institution, UserRole::Supervisor, InstitutionRole::Instructor);

        $this->expectException(InvalidArgumentException::class);
        app(InstitutionJoinCodeService::class)->issue($instructor, $institution, 10, 0);
    }

    public function test_expand_first_migration_rolls_back_and_reapplies_without_foreign_key_damage(): void
    {
        $migration = require database_path('migrations/2026_07_20_000013_establish_tenant_roles_and_join_codes.php');

        $migration->down();
        $this->assertFalse(Schema::hasTable('institution_join_codes'));
        $this->assertFalse(Schema::hasColumn('curriculum_activity_progress', 'learning_scope_key'));
        $this->assertFalse(Schema::hasColumn('curriculum_attempts', 'institution_membership_id'));
        $this->assertFalse(Schema::hasColumn('completions', 'learning_scope_key'));

        $migration->up();
        $this->assertTrue(Schema::hasTable('institution_join_codes'));
        $this->assertTrue(Schema::hasColumn('curriculum_activity_progress', 'learning_scope_key'));
        $this->assertTrue(Schema::hasColumn('curriculum_attempts', 'institution_membership_id'));
        $this->assertTrue(Schema::hasColumn('completions', 'learning_scope_key'));
        if (DB::getDriverName() === 'sqlite') {
            $this->assertSame([], DB::select('PRAGMA foreign_key_check'));
        }
    }

    private function institutionUser(Institution $institution, UserRole $legacyRole, InstitutionRole $role): User
    {
        $user = User::factory()->create(['role' => $legacyRole]);
        $membership = InstitutionMembership::query()->create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'status' => InstitutionMembershipStatus::Active,
            'is_default' => true,
            'provenance' => 'test_fixture',
            'joined_at' => now(),
        ]);
        InstitutionRoleAssignment::query()->create([
            'institution_membership_id' => $membership->id,
            'role' => $role,
            'assigned_by_user_id' => null,
            'assigned_at' => now(),
        ]);

        return $user;
    }

    /** @return array{Module, Lesson, Exercise} */
    private function publishedExercise(): array
    {
        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->for($module)->create();
        $exercise = Exercise::factory()->for($lesson)->create();

        return [$module, $lesson, $exercise];
    }
}

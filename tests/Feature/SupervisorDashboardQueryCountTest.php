<?php

namespace Tests\Feature;

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\InstitutionStatus;
use App\Models\Exercise;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupervisorDashboardQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_dashboard_queries_are_bounded_and_do_not_expose_institution_wide_learner_progress(): void
    {
        $institution = Institution::query()->create([
            'key' => 'measured-hotel-test-fixture',
            'name_id' => 'Measured Hotel',
            'name_en' => 'Measured Hotel',
            'status' => InstitutionStatus::Active,
            'verified_at' => now(),
            'verification_method' => 'test_fixture',
        ]);
        $supervisor = User::factory()->create([
            'role' => 'supervisor',
            'instansi' => $institution->name_id,
        ]);
        $this->addMembership($supervisor, $institution);

        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);
        $exercise = Exercise::factory()->create(['lesson_id' => $lesson->id]);

        $firstLearner = User::factory()->create(['instansi' => $institution->name_id]);
        $firstMembership = $this->addMembership($firstLearner, $institution);
        $firstLearner->completions()->create([
            'completable_type' => Exercise::class,
            'completable_id' => $exercise->id,
            'learning_scope_key' => 'membership:'.$firstMembership->id,
            'institution_membership_id' => $firstMembership->id,
        ]);

        $small = $this->countQueries($supervisor);

        $learners = User::factory()->count(99)->create(['instansi' => $institution->name_id]);
        foreach ($learners as $learner) {
            $this->addMembership($learner, $institution);
        }
        [$large, $response] = $this->countQueries($supervisor, returnResponse: true);

        $this->assertLessThanOrEqual($small, $large, "Supervisor query count grew from {$small} to {$large}.");
        $this->assertLessThanOrEqual(25, $large);

        $classes = $response->viewData('classes');
        $this->assertInstanceOf(LengthAwarePaginator::class, $classes);
        $this->assertSame(0, $classes->total());
        $response->assertDontSee($firstLearner->email);
        $response->assertSeeText('Classes');
    }

    private function countQueries(User $supervisor, bool $returnResponse = false): int|array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($supervisor)->get(route('supervisor.dashboard'));
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        $response->assertOk();

        return $returnResponse ? [$count, $response] : $count;
    }

    private function addMembership(User $user, Institution $institution): InstitutionMembership
    {
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
            'role' => $user->isSupervisor() ? InstitutionRole::Instructor : InstitutionRole::Learner,
            'assigned_by_user_id' => null,
            'assigned_at' => now(),
        ]);

        return $membership;
    }
}

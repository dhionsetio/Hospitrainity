<?php

namespace Tests\Feature;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseOfferingStatus;
use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\CurriculumEntity;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\User;
use App\Services\CourseRevisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\InstallsCanonicalCurriculumFixture;
use Tests\TestCase;

class CourseAssistantTest extends TestCase
{
    use InstallsCanonicalCurriculumFixture;
    use RefreshDatabase;

    public function test_learner_receives_only_escaped_literal_course_sources(): void
    {
        $this->installCanonicalCurriculumFixture();
        $learner = User::factory()->create([
            'role' => UserRole::Learner,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($learner)
            ->get(route('assistant.index'))
            ->assertOk()
            ->assertSeeText('Course assistant')
            ->assertDontSeeText('provider')
            ->assertDontSeeText('model');

        $this->post(route('assistant.ask'), [
            'question' => '<script>alert(1)</script> reservation booking',
        ])
            ->assertOk()
            ->assertSeeText('Closest answer in your course')
            ->assertSeeText('a room arranged in advance')
            ->assertDontSee('<script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_assistant_returns_a_clear_no_answer_state_and_rejects_staff_context(): void
    {
        $this->installCanonicalCurriculumFixture();
        $learner = User::factory()->create([
            'role' => UserRole::Learner,
            'email_verified_at' => now(),
        ]);
        $admin = User::factory()->create([
            'role' => UserRole::Admin,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($learner)
            ->post(route('assistant.ask'), ['question' => 'quasar zeppelin xylophone'])
            ->assertOk()
            ->assertSeeText('I could not find that in your course');

        $this->actingAs($admin)->get(route('assistant.index'))->assertForbidden();
        $this->post(route('assistant.ask'), ['question' => 'reservation'])->assertForbidden();
    }

    public function test_class_assistant_cannot_search_outside_the_pinned_course_revision(): void
    {
        $package = $this->installCanonicalCurriculumFixture();
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $admin = User::factory()->create(['role' => UserRole::Learner]);
        $learner = User::factory()->create([
            'role' => UserRole::Learner,
            'email_verified_at' => now(),
        ]);
        $this->membership($admin, $institution, InstitutionRole::InstitutionAdmin);
        $learnerMembership = $this->membership($learner, $institution, InstitutionRole::Learner);
        $course = Course::query()->create([
            'institution_id' => $institution->getKey(),
            'key' => 'front-desk-assistant',
            'title' => 'Front Desk Assistant',
            'created_by_user_id' => $admin->getKey(),
        ]);
        $chapter = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->getKey())
            ->where('code', 'HSP-C02')
            ->sole();
        $revision = app(CourseRevisionService::class)->create(
            $admin,
            $course,
            $package,
            [$chapter->getKey()],
            'Front Desk only',
        );
        $offering = CourseOffering::query()->create([
            'institution_id' => $institution->getKey(),
            'course_id' => $course->getKey(),
            'course_revision_id' => $revision->getKey(),
            'key' => 'front-desk-assistant-a',
            'title' => 'Front Desk Assistant A',
            'status' => CourseOfferingStatus::Active,
            'created_by_user_id' => $admin->getKey(),
        ]);
        $enrollment = CourseEnrollment::query()->create([
            'course_offering_id' => $offering->getKey(),
            'institution_membership_id' => $learnerMembership->getKey(),
            'status' => CourseEnrollmentStatus::Active,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($learner)->post(route('learning-context.select'), [
            'scope' => 'class',
            'course_enrollment_id' => $enrollment->getKey(),
        ])->assertRedirect();

        $this->post(route('assistant.ask'), ['question' => 'reservation booking'])
            ->assertOk()
            ->assertSeeText('a room arranged in advance');
        $this->post(route('assistant.ask'), ['question' => 'self introduction name hometown'])
            ->assertOk()
            ->assertSeeText('Closest answer in your course')
            ->assertDontSeeText('Welcome and Introduction to Customer Care');
    }

    private function membership(User $user, Institution $institution, InstitutionRole $role): InstitutionMembership
    {
        $membership = InstitutionMembership::query()->create([
            'institution_id' => $institution->getKey(),
            'user_id' => $user->getKey(),
            'status' => InstitutionMembershipStatus::Active,
            'is_default' => true,
            'provenance' => 'test_fixture',
            'joined_at' => now(),
        ]);
        InstitutionRoleAssignment::query()->create([
            'institution_membership_id' => $membership->getKey(),
            'role' => $role,
            'assigned_at' => now(),
        ]);

        return $membership;
    }
}

<?php

namespace Tests\Feature;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseOfferingStatus;
use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\TeachingAssignmentRole;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\CurriculumActivityProgress;
use App\Models\CurriculumAttempt;
use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\CourseRevisionService;
use App\Services\Curriculum\CanonicalCurriculumImporter;
use App\Services\Curriculum\CanonicalPackageReader;
use App\Services\InstitutionInvitationService;
use App\Services\LearningContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClassLearningContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_class_context_delivers_only_its_pinned_revision_and_attributes_new_progress(): void
    {
        [$learner, $instructor, $offering, $enrollment, $pinnedPackage] = $this->classFixture();

        $this->actingAs($learner)
            ->post(route('learning-context.select'), [
                'scope' => 'class',
                'course_enrollment_id' => $enrollment->getKey(),
            ])
            ->assertRedirect();

        $this->assertSame($enrollment->getKey(), session(LearningContext::SESSION_ENROLLMENT_KEY));
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Front Desk and Check-In')
            ->assertDontSeeText('Welcome and Introduction to Customer Care');
        $this->get(route('curriculum.chapters.show', 'HSP-C01'))->assertNotFound();
        $this->get(route('curriculum.chapters.show', 'HSP-C02'))->assertOk();
        $this->get(route('search.index', ['q' => 'Front Desk', 'type' => 'module']))
            ->assertOk()
            ->assertSeeText('Course search is unavailable while you are working inside a Class')
            ->assertSeeText('0 results found');

        $this->post(route('curriculum.activities.attempts.store', 'HSP-C02-ACT-QUIZ'), [
            'attempt_key' => (string) Str::uuid(),
            'intent' => 'show_model',
        ])->assertRedirect();

        $this->post(route('responses.store', ['HSP-C02-ACT-ROLEPLAY', 'HSP-C02-RP-FREE']), [
            'response_key' => (string) Str::uuid(),
            'kind' => 'assessment',
            'intent' => 'submit',
            'body' => 'Welcome. May I see your identification, please?',
        ])->assertRedirect(route('responses.index'));
        $this->post(route('responses.store', ['HSP-C02-ACT-ROLEPLAY', 'HSP-C02-RP-FREE']), [
            'response_key' => (string) Str::uuid(),
            'kind' => 'journal',
            'intent' => 'save',
            'body' => 'PRIVATE CLASS JOURNAL DRAFT',
        ])->assertRedirect();

        $progress = CurriculumActivityProgress::query()->sole();
        $attempt = CurriculumAttempt::query()->sole();
        foreach ([$progress, $attempt] as $record) {
            $this->assertSame('class:'.$offering->getKey(), $record->learning_scope_key);
            $this->assertSame($enrollment->institution_membership_id, $record->institution_membership_id);
            $this->assertSame($offering->getKey(), $record->course_offering_id);
            $this->assertSame($enrollment->getKey(), $record->course_enrollment_id);
            $this->assertSame($pinnedPackage->package_name, $record->package_name);
        }

        $this->actingAs($instructor)
            ->get(route('supervisor.progress.learners.show', $learner))
            ->assertNotFound();
        $this->get(route('supervisor.classes.enrollments.progress', [$offering, $enrollment]))
            ->assertOk()
            ->assertSeeText($offering->title)
            ->assertDontSeeText((string) $learner->email)
            ->assertDontSeeText((string) $learner->instansi)
            ->assertSeeText('Front Desk and Check-In')
            ->assertSeeText('Submitted assessments')
            ->assertSeeText('Welcome. May I see your identification, please?')
            ->assertDontSeeText('PRIVATE CLASS JOURNAL DRAFT')
            ->assertDontSeeText('Welcome and Introduction to Customer Care');

        $otherOffering = CourseOffering::query()->create([
            'institution_id' => $offering->institution_id,
            'course_id' => $offering->course_id,
            'course_revision_id' => $offering->course_revision_id,
            'key' => 'front-desk-b',
            'title' => 'Front Desk Class B',
            'status' => CourseOfferingStatus::Active,
            'created_by_user_id' => $offering->created_by_user_id,
        ]);
        $otherEnrollment = CourseEnrollment::query()->create([
            'course_offering_id' => $otherOffering->getKey(),
            'institution_membership_id' => $enrollment->institution_membership_id,
            'status' => CourseEnrollmentStatus::Active,
            'enrolled_at' => now(),
        ]);
        $this->get(route('supervisor.classes.enrollments.progress', [$otherOffering, $otherEnrollment]))
            ->assertNotFound();
    }

    public function test_suspended_enrollment_invalidates_the_selected_class_without_erasing_history(): void
    {
        [$learner, , $offering, $enrollment] = $this->classFixture();
        $this->actingAs($learner)->post(route('learning-context.select'), [
            'scope' => 'class',
            'course_enrollment_id' => $enrollment->getKey(),
        ]);
        $this->get(route('curriculum.activities.show', 'HSP-C02-ACT-QUIZ'))->assertOk();
        $progressId = CurriculumActivityProgress::query()->sole()->getKey();

        $enrollment->forceFill([
            'status' => CourseEnrollmentStatus::Suspended,
            'suspended_at' => now(),
        ])->save();

        $this->get(route('curriculum.activities.show', 'HSP-C02-ACT-QUIZ'))->assertForbidden();
        $this->assertNull(session(LearningContext::SESSION_ENROLLMENT_KEY));
        $this->assertDatabaseHas('curriculum_activity_progress', [
            'id' => $progressId,
            'course_offering_id' => $offering->getKey(),
            'course_enrollment_id' => $enrollment->getKey(),
        ]);
    }

    public function test_accepting_a_class_invitation_selects_the_exact_new_enrollment(): void
    {
        [, $instructor, $offering] = $this->classFixture();
        $target = User::factory()->create([
            'role' => UserRole::Learner,
            'email' => 'invited-class-learner@example.test',
            'email_verified_at' => now(),
        ]);
        $issued = app(InstitutionInvitationService::class)->issue(
            $instructor,
            $offering->institution,
            $target->email,
            $offering,
        );

        $this->actingAs($target)
            ->post(route('invitations.redeem', ['token' => $issued['token']]))
            ->assertRedirect(route('dashboard'));

        $enrollment = CourseEnrollment::query()
            ->where('course_offering_id', $offering->getKey())
            ->whereHas('membership', fn ($query) => $query->where('user_id', $target->getKey()))
            ->sole();
        $this->assertSame($enrollment->getKey(), session(LearningContext::SESSION_ENROLLMENT_KEY));
        $this->assertSame($enrollment->institution_membership_id, session(LearningContext::SESSION_MEMBERSHIP_KEY));
    }

    /** @return array{User, User, CourseOffering, CourseEnrollment, CurriculumPackage} */
    private function classFixture(): array
    {
        app(CanonicalCurriculumImporter::class)->import(app(CanonicalPackageReader::class)->read());
        $package = CurriculumPackage::query()->where('is_active', true)->sole();
        $package->forceFill(['lifecycle_status' => 'published'])->save();
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $admin = User::factory()->create(['role' => UserRole::Learner]);
        $instructor = User::factory()->create(['role' => UserRole::Supervisor]);
        $learner = User::factory()->create([
            'role' => UserRole::Learner,
            'email' => 'private-class-progress@example.test',
            'instansi' => 'Private legacy profile value',
        ]);
        $this->membership($admin, $institution, InstitutionRole::InstitutionAdmin);
        $instructorMembership = $this->membership($instructor, $institution, InstitutionRole::Instructor);
        $learnerMembership = $this->membership($learner, $institution, InstitutionRole::Learner);
        $course = Course::query()->create([
            'institution_id' => $institution->getKey(),
            'key' => 'Class scoped English',
            'title' => 'Class scoped English',
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
            'key' => 'front-desk-a',
            'title' => 'Front Desk Class A',
            'status' => CourseOfferingStatus::Active,
            'created_by_user_id' => $admin->getKey(),
        ]);
        TeachingAssignment::query()->create([
            'course_offering_id' => $offering->getKey(),
            'institution_membership_id' => $instructorMembership->getKey(),
            'role' => TeachingAssignmentRole::Primary,
            'assigned_by_user_id' => $admin->getKey(),
            'assigned_at' => now(),
        ]);
        $enrollment = CourseEnrollment::query()->create([
            'course_offering_id' => $offering->getKey(),
            'institution_membership_id' => $learnerMembership->getKey(),
            'status' => CourseEnrollmentStatus::Active,
            'enrolled_by_user_id' => $admin->getKey(),
            'enrolled_at' => now(),
        ]);

        CurriculumPackage::query()->whereKey($package->getKey())->update(['is_active' => false]);
        CurriculumPackage::query()->create([
            'package_name' => 'replacement-package',
            'content_version' => '2.0.0',
            'schema_version' => '2.1.0',
            'namespace_uuid' => (string) Str::uuid(),
            'lifecycle_status' => 'published',
            'source_path' => 'tests/replacement',
            'source_tree_sha256' => hash('sha256', 'replacement-package'),
            'source_file_count' => 1,
            'source_byte_count' => 1,
            'counts' => [],
            'projection_meta' => [],
            'laravel_projection_sha256' => hash('sha256', 'replacement-laravel'),
            'standalone_sha256' => hash('sha256', 'replacement-standalone'),
            'is_active' => true,
            'imported_at' => now(),
        ]);

        return [$learner, $instructor, $offering, $enrollment, $package];
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

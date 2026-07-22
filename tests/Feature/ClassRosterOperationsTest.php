<?php

namespace Tests\Feature;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseOfferingStatus;
use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\TeachingAssignmentRole;
use App\Enums\UserRole;
use App\Enums\WorkContextRole;
use App\Models\Course;
use App\Models\CourseEnrollmentEvent;
use App\Models\CourseOffering;
use App\Models\CourseRevision;
use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\ClassWorkspaceService;
use App\Services\CourseEnrollmentService;
use App\Services\CourseRevisionService;
use App\Services\InstitutionContext;
use App\Services\InstitutionInvitationService;
use App\Services\InstitutionJoinCodeService;
use App\Services\TeachingAssignmentService;
use App\Services\WorkContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\TestCase;

class ClassRosterOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_institution_admin_can_create_a_course_and_draft_class_through_the_controller(): void
    {
        $institution = $this->polinema();
        $admin = User::factory()->create(['role' => UserRole::Learner]);
        $instructor = User::factory()->create(['role' => UserRole::Learner]);
        $this->membership($admin, $institution, InstitutionRole::InstitutionAdmin);
        $instructorMembership = $this->membership($instructor, $institution, InstitutionRole::Instructor);
        [, $sourceRevision] = $this->courseFixture($institution, $admin, 'controller-create');
        $package = $sourceRevision->curriculumPackage()->firstOrFail();
        $module = $sourceRevision->modules()->firstOrFail()->curriculumEntity()->firstOrFail();
        $session = [
            WorkContext::SESSION_ROLE_KEY => WorkContextRole::InstitutionAdmin->value,
            InstitutionContext::SESSION_KEY => $institution->getKey(),
        ];

        $response = $this->actingAs($admin)
            ->withSession($session)
            ->post(route('supervisor.classes.store-with-course'), [
                'course_key' => 'front-office-english',
                'course_title' => 'Front Office English',
                'course_description' => 'Approved learning for the first local Class.',
                'curriculum_package_id' => $package->getKey(),
                'revision_title' => 'Initial approved sequence',
                'module_ids' => [$module->getKey()],
                'class_key' => 'front-office-a',
                'class_title' => 'Front Office Class A',
                'term_label' => 'Tester release',
                'timezone' => 'Asia/Jakarta',
                'primary_instructor_membership_id' => $instructorMembership->getKey(),
            ]);

        $offering = CourseOffering::query()->where('key', 'front-office-a')->firstOrFail();
        $response->assertRedirect(route('supervisor.classes.show', $offering));
        $this->assertSame(CourseOfferingStatus::Draft, $offering->status);
        $this->assertSame('Front Office English', $offering->course->title);
        $this->assertSame([$module->getKey()], $offering->revision->modules()->pluck('curriculum_entity_id')->all());
        $this->assertDatabaseHas('teaching_assignments', [
            'course_offering_id' => $offering->getKey(),
            'institution_membership_id' => $instructorMembership->getKey(),
            'role' => TeachingAssignmentRole::Primary->value,
            'revoked_at' => null,
        ]);
    }

    public function test_safe_copy_keeps_only_the_pinned_revision_timezone_and_a_new_primary_instructor(): void
    {
        $institution = $this->polinema();
        $admin = User::factory()->create(['role' => UserRole::Learner]);
        $instructor = User::factory()->create(['role' => UserRole::Learner]);
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->membership($admin, $institution, InstitutionRole::InstitutionAdmin);
        $instructorMembership = $this->membership($instructor, $institution, InstitutionRole::Instructor);
        $learnerMembership = $this->membership($learner, $institution, InstitutionRole::Learner);
        [$course, $revision] = $this->courseFixture($institution, $admin, 'copy');
        $source = $this->offering($institution, $course, $revision, $admin, 'source', 'Source Class');

        app(TeachingAssignmentService::class)->assign(
            $admin,
            $source,
            $instructorMembership,
            TeachingAssignmentRole::Primary,
        );
        app(CourseEnrollmentService::class)->enroll(
            $admin,
            $source,
            $learnerMembership,
            'Approved source enrollment.',
        );
        app(InstitutionJoinCodeService::class)->issue($admin, $institution, 10, 3_600, $source);
        app(InstitutionInvitationService::class)->issue(
            $admin,
            $institution,
            'copy-target@example.test',
            $source,
        );

        $copy = app(ClassWorkspaceService::class)->copy(
            $admin,
            $source,
            $instructorMembership,
            'next-cohort',
            'Next Cohort',
        );

        $this->assertSame(CourseOfferingStatus::Draft, $copy->status);
        $this->assertSame($source->course_revision_id, $copy->course_revision_id);
        $this->assertSame($source->timezone, $copy->timezone);
        $this->assertNull($copy->term_label);
        $this->assertSame('next-cohort', $copy->key);
        $this->assertSame(0, $copy->enrollments()->count());
        $this->assertSame(0, $copy->joinCodes()->count());
        $this->assertSame(0, $copy->invitations()->count());
        $this->assertSame(0, $copy->joinRequests()->count());
        $this->assertSame(0, $copy->events()->count());
        $this->assertSame(1, $copy->teachingAssignments()->whereNull('revoked_at')->count());
        $this->assertSame(
            TeachingAssignmentRole::Primary,
            $copy->teachingAssignments()->whereNull('revoked_at')->firstOrFail()->role,
        );
        $this->assertSame(1, $source->enrollments()->count());
        $this->assertSame(1, $source->joinCodes()->count());
        $this->assertSame(1, $source->invitations()->count());
    }

    public function test_roster_status_and_transfer_operations_retain_append_only_history(): void
    {
        $institution = $this->polinema();
        $admin = User::factory()->create(['role' => UserRole::Learner]);
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->membership($admin, $institution, InstitutionRole::InstitutionAdmin);
        $learnerMembership = $this->membership($learner, $institution, InstitutionRole::Learner);
        [$course, $revision] = $this->courseFixture($institution, $admin, 'transfer');
        $source = $this->offering($institution, $course, $revision, $admin, 'source', 'Source Class');
        $target = $this->offering($institution, $course, $revision, $admin, 'target', 'Target Class');
        $service = app(CourseEnrollmentService::class);

        $sourceEnrollment = $service->enroll($admin, $source, $learnerMembership, 'Manual enrollment approved.');
        $service->changeStatus($admin, $sourceEnrollment, CourseEnrollmentStatus::Suspended, 'Temporary suspension.');
        $service->changeStatus($admin, $sourceEnrollment, CourseEnrollmentStatus::Active, 'Suspension reviewed.');
        $targetEnrollment = $service->transfer($admin, $sourceEnrollment, $target, 'Move to the next available Class.');

        $this->assertSame(CourseEnrollmentStatus::Withdrawn, $sourceEnrollment->fresh()->status);
        $this->assertSame(CourseEnrollmentStatus::Active, $targetEnrollment->fresh()->status);
        $this->assertSame(4, $sourceEnrollment->events()->count());
        $this->assertSame(1, $targetEnrollment->events()->count());
        $this->assertDatabaseHas('course_enrollment_events', [
            'course_enrollment_id' => $sourceEnrollment->getKey(),
            'to_status' => CourseEnrollmentStatus::Withdrawn->value,
            'transfer_to_course_offering_id' => $target->getKey(),
        ]);

        $event = $sourceEnrollment->events()->firstOrFail();
        try {
            $event->delete();
            $this->fail('Append-only enrollment evidence unexpectedly allowed deletion.');
        } catch (LogicException) {
            $this->assertSame(5, CourseEnrollmentEvent::query()->count());
        }
    }

    public function test_primary_replacement_retains_assignment_history_and_cannot_leave_a_class_unowned(): void
    {
        $institution = $this->polinema();
        $admin = User::factory()->create(['role' => UserRole::Learner]);
        $first = User::factory()->create(['role' => UserRole::Learner]);
        $second = User::factory()->create(['role' => UserRole::Learner]);
        $this->membership($admin, $institution, InstitutionRole::InstitutionAdmin);
        $firstMembership = $this->membership($first, $institution, InstitutionRole::Instructor);
        $secondMembership = $this->membership($second, $institution, InstitutionRole::Instructor);
        [$course, $revision] = $this->courseFixture($institution, $admin, 'teaching');
        $offering = $this->offering($institution, $course, $revision, $admin, 'team', 'Teaching Team');
        $service = app(TeachingAssignmentService::class);

        $firstPrimary = $service->assign($admin, $offering, $firstMembership, TeachingAssignmentRole::Primary);
        $service->assign($admin, $offering, $secondMembership, TeachingAssignmentRole::CoInstructor);
        $replacement = $service->assign($admin, $offering, $secondMembership, TeachingAssignmentRole::Primary);
        $sameReplacement = $service->assign($admin, $offering, $secondMembership, TeachingAssignmentRole::Primary);

        $this->assertTrue($replacement->is($sameReplacement));
        $this->assertSame(3, TeachingAssignment::query()->where('course_offering_id', $offering->id)->count());
        $this->assertSame(1, TeachingAssignment::query()
            ->where('course_offering_id', $offering->id)
            ->whereNull('revoked_at')
            ->count());
        $this->assertNotNull($firstPrimary->fresh()->revoked_at);
        $this->assertSame(TeachingAssignmentRole::Primary, $replacement->fresh()->role);

        $this->expectException(ValidationException::class);
        $service->revoke($admin, $replacement);
    }

    public function test_classroom_code_approval_enrolls_an_existing_staff_member_as_a_learner(): void
    {
        $institution = $this->polinema();
        $admin = User::factory()->create(['role' => UserRole::Learner]);
        $staffLearner = User::factory()->create(['role' => UserRole::Learner]);
        $this->membership($admin, $institution, InstitutionRole::InstitutionAdmin);
        $membership = $this->membership($staffLearner, $institution, InstitutionRole::Instructor);
        [$course, $revision] = $this->courseFixture($institution, $admin, 'code');
        $offering = $this->offering($institution, $course, $revision, $admin, 'code-class', 'Code Class');
        $codes = app(InstitutionJoinCodeService::class);

        $issued = $codes->issue($admin, $institution, 5, 3_600, $offering);
        $request = $codes->requestMembership($staffLearner, $issued['code']);
        $approved = $codes->decide($admin, $request, true);

        $this->assertSame($offering->getKey(), $request->course_offering_id);
        $this->assertSame('approved', $approved->status->value);
        $this->assertDatabaseHas('institution_role_assignments', [
            'institution_membership_id' => $membership->getKey(),
            'role' => InstitutionRole::Learner->value,
            'revoked_at' => null,
        ]);
        $this->assertDatabaseHas('course_enrollments', [
            'course_offering_id' => $offering->getKey(),
            'institution_membership_id' => $membership->getKey(),
            'status' => CourseEnrollmentStatus::Active->value,
        ]);
        $this->assertSame(1, $issued['record']->fresh()->use_count);
    }

    public function test_class_invitation_enrolls_an_existing_staff_member_without_duplicating_membership(): void
    {
        $institution = $this->polinema();
        $admin = User::factory()->create(['role' => UserRole::Learner]);
        $staffLearner = User::factory()->create(['role' => UserRole::Learner]);
        $this->membership($admin, $institution, InstitutionRole::InstitutionAdmin);
        $membership = $this->membership($staffLearner, $institution, InstitutionRole::Instructor);
        [$course, $revision] = $this->courseFixture($institution, $admin, 'invite');
        $offering = $this->offering($institution, $course, $revision, $admin, 'invite-class', 'Invitation Class');
        $invitations = app(InstitutionInvitationService::class);

        $issued = $invitations->issue($admin, $institution, $staffLearner->email, $offering);
        $result = $invitations->redeem($issued['token'], $staffLearner, null);

        $this->assertFalse($result['created']);
        $this->assertSame($membership->getKey(), $result['membership']->getKey());
        $this->assertSame($offering->getKey(), $result['offering']?->getKey());
        $this->assertSame(1, InstitutionMembership::query()
            ->where('institution_id', $institution->getKey())
            ->where('user_id', $staffLearner->getKey())
            ->count());
        $this->assertTrue($staffLearner->hasInstitutionRole($institution, InstitutionRole::Learner));
        $this->assertDatabaseHas('course_enrollments', [
            'course_offering_id' => $offering->getKey(),
            'institution_membership_id' => $membership->getKey(),
            'status' => CourseEnrollmentStatus::Active->value,
        ]);
    }

    public function test_class_pages_are_relationship_scoped_and_do_not_render_learner_email_or_private_progress(): void
    {
        $institution = $this->polinema();
        $admin = User::factory()->create(['role' => UserRole::Learner]);
        $unassignedInstructor = User::factory()->create(['role' => UserRole::Learner]);
        $learner = User::factory()->create([
            'name' => 'Roster Privacy Learner',
            'email' => 'private-roster-address@example.test',
            'role' => UserRole::Learner,
        ]);
        $this->membership($admin, $institution, InstitutionRole::InstitutionAdmin);
        $this->membership($unassignedInstructor, $institution, InstitutionRole::Instructor);
        $learnerMembership = $this->membership($learner, $institution, InstitutionRole::Learner);
        [$course, $revision] = $this->courseFixture($institution, $admin, 'ui');
        $offering = $this->offering($institution, $course, $revision, $admin, 'ui-class', 'UI Verification Class');
        app(CourseEnrollmentService::class)->enroll($admin, $offering, $learnerMembership, 'Approved for UI verification.');

        $session = [
            WorkContext::SESSION_ROLE_KEY => WorkContextRole::InstitutionAdmin->value,
            InstitutionContext::SESSION_KEY => $institution->getKey(),
        ];
        $this->actingAs($admin)
            ->withSession($session)
            ->get(route('supervisor.classes.show', $offering))
            ->assertOk()
            ->assertSee('UI Verification Class')
            ->assertSee('Roster Privacy Learner')
            ->assertDontSee('private-roster-address@example.test')
            ->assertSee('Personal self-study activity and private responses are not included.');
        $this->actingAs($admin)
            ->withSession($session)
            ->get(route('supervisor.classes.preview', $offering))
            ->assertOk()
            ->assertSee('Learner preview')
            ->assertSee('no learner progress or answers are saved');

        $this->actingAs($unassignedInstructor)
            ->withSession([
                WorkContext::SESSION_ROLE_KEY => WorkContextRole::Instructor->value,
                InstitutionContext::SESSION_KEY => $institution->getKey(),
            ])
            ->get(route('supervisor.classes.show', $offering))
            ->assertNotFound();
    }

    private function polinema(): Institution
    {
        return Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
    }

    private function membership(User $user, Institution $institution, InstitutionRole $role): InstitutionMembership
    {
        $membership = InstitutionMembership::query()->firstOrCreate([
            'institution_id' => $institution->getKey(),
            'user_id' => $user->getKey(),
        ], [
            'status' => InstitutionMembershipStatus::Active,
            'is_default' => true,
            'provenance' => 'test_fixture',
            'joined_at' => now(),
        ]);
        InstitutionRoleAssignment::query()->firstOrCreate([
            'institution_membership_id' => $membership->getKey(),
            'role' => $role->value,
        ], [
            'assigned_by_user_id' => null,
            'assigned_at' => now(),
        ]);

        return $membership;
    }

    /** @return array{Course, CourseRevision} */
    private function courseFixture(Institution $institution, User $creator, string $suffix): array
    {
        CurriculumPackage::query()->where('is_active', true)->update(['is_active' => false]);
        $package = CurriculumPackage::query()->create([
            'package_name' => 'class-roster-'.$suffix,
            'content_version' => '1.0.0-'.$suffix,
            'schema_version' => '2.1.0',
            'namespace_uuid' => '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
            'lifecycle_status' => 'published',
            'source_path' => 'tests/class-roster/'.$suffix,
            'source_tree_sha256' => hash('sha256', 'package-'.$suffix),
            'source_file_count' => 1,
            'source_byte_count' => 1,
            'counts' => [],
            'projection_meta' => [],
            'laravel_projection_sha256' => hash('sha256', 'laravel-'.$suffix),
            'standalone_sha256' => hash('sha256', 'standalone-'.$suffix),
            'is_active' => true,
            'imported_at' => now(),
        ]);
        $module = CurriculumEntity::query()->create([
            'curriculum_package_id' => $package->getKey(),
            'entity_uuid' => '00000000-0000-4000-8000-000000000001',
            'code' => 'module-'.$suffix,
            'entity_type' => 'chapter',
            'position' => 1,
            'lifecycle_status' => 'published',
            'content_version' => '1.0.0',
            'source_path' => 'chapters/'.$suffix.'.json',
            'source_sha256' => hash('sha256', 'module-'.$suffix),
            'payload' => ['title' => 'Module '.$suffix],
        ]);
        $course = Course::query()->create([
            'institution_id' => $institution->getKey(),
            'key' => 'course-'.$suffix,
            'title' => 'Course '.$suffix,
            'created_by_user_id' => $creator->getKey(),
        ]);
        $revision = app(CourseRevisionService::class)->create(
            $creator,
            $course,
            $package,
            [$module->getKey()],
            'Approved Revision',
        );

        return [$course, $revision];
    }

    private function offering(
        Institution $institution,
        Course $course,
        CourseRevision $revision,
        User $creator,
        string $key,
        string $title,
    ): CourseOffering {
        return CourseOffering::query()->create([
            'institution_id' => $institution->getKey(),
            'course_id' => $course->getKey(),
            'course_revision_id' => $revision->getKey(),
            'key' => $key,
            'title' => $title,
            'term_label' => 'Term retained only on source',
            'status' => CourseOfferingStatus::Active,
            'timezone' => 'Asia/Jakarta',
            'created_by_user_id' => $creator->getKey(),
        ]);
    }
}

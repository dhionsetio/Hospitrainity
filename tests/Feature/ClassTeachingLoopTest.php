<?php

namespace Tests\Feature;

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseOfferingStatus;
use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\TeachingAssignmentRole;
use App\Enums\UserRole;
use App\Enums\WorkContextRole;
use App\Models\ClassAnnouncement;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseOffering;
use App\Models\CourseRevision;
use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\ClassTeachingService;
use App\Services\CourseRevisionService;
use App\Services\InstitutionContext;
use App\Services\LearningContext;
use App\Services\WorkContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClassTeachingLoopTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_instructor_can_replace_a_prelaunch_class_module_sequence_with_retained_history(): void
    {
        $fixture = $this->fixture(CourseOfferingStatus::EnrollmentOpen, 'content');
        $oldRevisionId = $fixture['revision']->getKey();

        $this->actingAs($fixture['instructor'])
            ->withSession($this->staffSession($fixture['institution']))
            ->post(route('supervisor.classes.content-revisions.store', $fixture['offering']), [
                'curriculum_package_id' => $fixture['package']->getKey(),
                'revision_title' => 'Updated arrival sequence',
                'module_ids' => [
                    $fixture['modules'][1]->getKey(),
                    $fixture['modules'][2]->getKey(),
                ],
                'reason' => 'Use the two modules needed by this tester Class.',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', __('classes.messages.content_updated'));

        $offering = $fixture['offering']->fresh();
        $this->assertNotSame($oldRevisionId, $offering->course_revision_id);
        $this->assertSame(
            [$fixture['modules'][1]->getKey(), $fixture['modules'][2]->getKey()],
            $offering->revision->modules()->pluck('curriculum_entity_id')->all(),
        );
        $this->assertDatabaseHas('course_revisions', ['id' => $oldRevisionId]);
        $this->assertDatabaseHas('course_offering_revision_events', [
            'course_offering_id' => $offering->getKey(),
            'from_course_revision_id' => $oldRevisionId,
            'to_course_revision_id' => $offering->course_revision_id,
            'actor_user_id' => $fixture['instructor']->getKey(),
        ]);

        $outsider = User::factory()->create(['role' => UserRole::Learner]);
        $this->membership($outsider, $fixture['institution'], InstitutionRole::Instructor);
        $this->expectException(AuthorizationException::class);
        app(ClassTeachingService::class)->reviseContent(
            $outsider,
            $offering,
            $fixture['package'],
            [$fixture['modules'][0]->getKey()],
            'Unauthorized replacement',
            'This actor is not assigned to the Class.',
        );
    }

    public function test_class_content_is_locked_after_activation_or_recorded_learner_activity(): void
    {
        $active = $this->fixture(CourseOfferingStatus::Active, 'active-lock');
        $activeRevisionId = $active['offering']->course_revision_id;

        $this->actingAs($active['instructor'])
            ->withSession($this->staffSession($active['institution']))
            ->post(route('supervisor.classes.content-revisions.store', $active['offering']), [
                'curriculum_package_id' => $active['package']->getKey(),
                'revision_title' => 'Must remain rejected',
                'module_ids' => [$active['modules'][1]->getKey()],
                'reason' => 'Attempted after activation.',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('content');
        $this->assertSame($activeRevisionId, $active['offering']->fresh()->course_revision_id);

        $prelaunch = $this->fixture(CourseOfferingStatus::EnrollmentOpen, 'activity-lock');
        $enrollment = $this->enroll($prelaunch);
        DB::table('completions')->insert([
            'user_id' => $prelaunch['learner']->getKey(),
            'learning_scope_key' => 'class:'.$prelaunch['offering']->getKey(),
            'institution_membership_id' => $prelaunch['learner_membership']->getKey(),
            'course_offering_id' => $prelaunch['offering']->getKey(),
            'course_enrollment_id' => $enrollment->getKey(),
            'completable_type' => 'App\\Models\\Module',
            'completable_id' => 999999,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertFalse(app(ClassTeachingService::class)->canReviseContent(
            $prelaunch['instructor'],
            $prelaunch['offering'],
        ));
        $this->assertSame($prelaunch['revision']->getKey(), $prelaunch['offering']->fresh()->course_revision_id);
    }

    public function test_instructor_instructions_reach_only_the_selected_class_and_can_be_archived(): void
    {
        $fixture = $this->fixture(CourseOfferingStatus::Active, 'instructions');
        $enrollment = $this->enroll($fixture);
        $otherOffering = CourseOffering::query()->create([
            'institution_id' => $fixture['institution']->getKey(),
            'course_id' => $fixture['course']->getKey(),
            'course_revision_id' => $fixture['revision']->getKey(),
            'key' => 'other-instructions-class',
            'title' => 'Other Instructions Class',
            'status' => CourseOfferingStatus::Active,
            'created_by_user_id' => $fixture['admin']->getKey(),
        ]);
        $other = ClassAnnouncement::query()->create([
            'course_offering_id' => $otherOffering->getKey(),
            'institution_id' => $fixture['institution']->getKey(),
            'title' => 'Other Class only',
            'body' => 'This must never appear in the selected Class.',
            'created_by_user_id' => $fixture['admin']->getKey(),
            'updated_by_user_id' => $fixture['admin']->getKey(),
            'published_at' => now(),
        ]);

        $staffSession = $this->staffSession($fixture['institution']);
        $this->actingAs($fixture['instructor'])
            ->withSession($staffSession)
            ->post(route('supervisor.classes.instructions.store', $fixture['offering']), [
                'instruction_title' => 'Prepare for the role-play',
                'instruction_body' => 'Complete the arrival dialogue before the next session.',
                'instruction_module_id' => $fixture['modules'][0]->getKey(),
            ])
            ->assertRedirect()
            ->assertSessionHas('status', __('classes.messages.instruction_posted'));
        $announcement = ClassAnnouncement::query()
            ->where('course_offering_id', $fixture['offering']->getKey())
            ->sole();

        $this->actingAs($fixture['learner'])
            ->post(route('learning-context.select'), [
                'scope' => 'class',
                'course_enrollment_id' => $enrollment->getKey(),
            ])
            ->assertRedirect();
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Prepare for the role-play')
            ->assertSeeText('Complete the arrival dialogue before the next session.')
            ->assertDontSeeText('Other Class only');

        $this->actingAs($fixture['instructor'])
            ->withSession($staffSession)
            ->patch(route('supervisor.classes.instructions.archive', [$fixture['offering'], $announcement]), [
                'expected_revision' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('status', __('classes.messages.instruction_archived'));

        $this->actingAs($fixture['learner'])
            ->withSession([
                LearningContext::SESSION_MEMBERSHIP_KEY => $fixture['learner_membership']->getKey(),
                LearningContext::SESSION_ENROLLMENT_KEY => $enrollment->getKey(),
            ])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('Prepare for the role-play');
        $this->assertNotNull($announcement->fresh()->archived_at);

        $this->actingAs($fixture['instructor'])
            ->withSession($staffSession)
            ->patch(route('supervisor.classes.instructions.archive', [$fixture['offering'], $other]), [
                'expected_revision' => 1,
            ])
            ->assertNotFound();
    }

    public function test_class_workspace_shows_human_facing_content_and_instruction_controls(): void
    {
        $fixture = $this->fixture(CourseOfferingStatus::EnrollmentOpen, 'workspace');

        $this->actingAs($fixture['instructor'])
            ->withSession($this->staffSession($fixture['institution']))
            ->get(route('supervisor.classes.show', $fixture['offering']))
            ->assertOk()
            ->assertSeeText(__('classes.class_content'))
            ->assertSeeText(__('classes.change_modules'))
            ->assertSeeText(__('classes.instructions'))
            ->assertSeeText(__('classes.post_instruction'))
            ->assertSeeText('Module workspace 1')
            ->assertDontSeeText('curriculum_package_id')
            ->assertDontSeeText('course_revision_id');
    }

    public function test_minimum_teaching_loop_migration_rolls_back_and_reapplies_in_the_isolated_database(): void
    {
        $migration = require database_path('migrations/2026_07_22_000023_complete_minimum_class_teaching_loop.php');

        $migration->down();
        $this->assertFalse(Schema::hasTable('class_announcements'));
        $this->assertFalse(Schema::hasTable('course_offering_revision_events'));

        $migration->up();
        $this->assertTrue(Schema::hasTable('class_announcements'));
        $this->assertTrue(Schema::hasTable('course_offering_revision_events'));
    }

    /**
     * @return array{
     *   institution: Institution,
     *   admin: User,
     *   instructor: User,
     *   learner: User,
     *   instructor_membership: InstitutionMembership,
     *   learner_membership: InstitutionMembership,
     *   package: CurriculumPackage,
     *   modules: list<CurriculumEntity>,
     *   course: Course,
     *   revision: CourseRevision,
     *   offering: CourseOffering
     * }
     */
    private function fixture(CourseOfferingStatus $status, string $suffix): array
    {
        $institution = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $admin = User::factory()->create(['role' => UserRole::Learner]);
        $instructor = User::factory()->create(['role' => UserRole::Supervisor]);
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->membership($admin, $institution, InstitutionRole::InstitutionAdmin);
        $instructorMembership = $this->membership($instructor, $institution, InstitutionRole::Instructor);
        $learnerMembership = $this->membership($learner, $institution, InstitutionRole::Learner);
        [$package, $modules] = $this->packageWithModules($suffix, 3);
        $course = Course::query()->create([
            'institution_id' => $institution->getKey(),
            'key' => 'course-'.$suffix,
            'title' => 'Course '.$suffix,
            'created_by_user_id' => $admin->getKey(),
        ]);
        $revision = app(CourseRevisionService::class)->create(
            $admin,
            $course,
            $package,
            [$modules[0]->getKey()],
            'Initial Class sequence',
        );
        $offering = CourseOffering::query()->create([
            'institution_id' => $institution->getKey(),
            'course_id' => $course->getKey(),
            'course_revision_id' => $revision->getKey(),
            'key' => 'class-'.$suffix,
            'title' => 'Class '.$suffix,
            'status' => $status,
            'created_by_user_id' => $admin->getKey(),
        ]);
        TeachingAssignment::query()->create([
            'course_offering_id' => $offering->getKey(),
            'institution_membership_id' => $instructorMembership->getKey(),
            'role' => TeachingAssignmentRole::Primary,
            'assigned_by_user_id' => $admin->getKey(),
            'assigned_at' => now(),
        ]);

        return compact(
            'institution',
            'admin',
            'instructor',
            'learner',
            'instructorMembership',
            'learnerMembership',
            'package',
            'modules',
            'course',
            'revision',
            'offering',
        ) + [
            'instructor_membership' => $instructorMembership,
            'learner_membership' => $learnerMembership,
        ];
    }

    private function enroll(array $fixture): CourseEnrollment
    {
        return CourseEnrollment::query()->create([
            'course_offering_id' => $fixture['offering']->getKey(),
            'institution_membership_id' => $fixture['learner_membership']->getKey(),
            'status' => CourseEnrollmentStatus::Active,
            'enrolled_by_user_id' => $fixture['admin']->getKey(),
            'enrolled_at' => now(),
        ]);
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

    /** @return array{CurriculumPackage, list<CurriculumEntity>} */
    private function packageWithModules(string $suffix, int $count): array
    {
        CurriculumPackage::query()->where('is_active', true)->update(['is_active' => false]);
        $package = CurriculumPackage::query()->create([
            'package_name' => 'teaching-loop-'.$suffix,
            'content_version' => '1.0.0-'.$suffix,
            'schema_version' => '2.1.0',
            'namespace_uuid' => (string) Str::uuid(),
            'lifecycle_status' => 'published',
            'source_path' => 'tests/'.$suffix,
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
        $modules = [];
        for ($position = 1; $position <= $count; $position++) {
            $modules[] = CurriculumEntity::query()->create([
                'curriculum_package_id' => $package->getKey(),
                'entity_uuid' => (string) Str::uuid(),
                'code' => 'module-'.$suffix.'-'.$position,
                'entity_type' => 'chapter',
                'position' => $position,
                'lifecycle_status' => 'published',
                'content_version' => '1.0.0',
                'source_path' => 'chapters/'.$position.'.json',
                'source_sha256' => hash('sha256', $suffix.'-'.$position),
                'payload' => [
                    'module' => $position,
                    'title' => 'Module '.$suffix.' '.$position,
                ],
            ]);
        }

        return [$package, $modules];
    }

    /** @return array<string, string> */
    private function staffSession(Institution $institution): array
    {
        return [
            WorkContext::SESSION_ROLE_KEY => WorkContextRole::Instructor->value,
            InstitutionContext::SESSION_KEY => $institution->getKey(),
        ];
    }
}

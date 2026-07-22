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
use App\Models\CourseRevision;
use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\Institution;
use App\Models\InstitutionMembership;
use App\Models\InstitutionRoleAssignment;
use App\Models\TeachingAssignment;
use App\Models\User;
use App\Services\CourseAccessService;
use App\Services\CourseOfferingLifecycle;
use App\Services\CourseRevisionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Tests\TestCase;

class CourseClassFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_backfills_only_the_approved_institution_timezone_and_validates_iana_identifiers(): void
    {
        $polinema = Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
        $hq = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();

        $this->assertSame('Asia/Jakarta', $polinema->timezone);
        $this->assertNull($hq->timezone);

        $user = User::factory()->create();
        $user->update(['timezone' => 'Europe/Amsterdam']);
        $this->assertSame('Europe/Amsterdam', $user->fresh()->timezone);

        $this->expectException(InvalidArgumentException::class);
        $user->update(['timezone' => 'Not/A_Timezone']);
    }

    public function test_course_revision_is_an_ordered_immutable_selection_from_one_active_published_package(): void
    {
        $institution = $this->polinema();
        $admin = User::factory()->create(['role' => UserRole::Learner]);
        $this->membership($admin, $institution, InstitutionRole::InstitutionAdmin);
        $course = $this->course($institution, $admin);
        [$package, $modules] = $this->packageWithModules('approved', 3);

        $revision = app(CourseRevisionService::class)->create(
            $admin,
            $course,
            $package,
            [$modules[2]->id, $modules[0]->id],
            'Front Office sequence',
        );

        $this->assertSame(1, $revision->revision_number);
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $revision->content_sha256);
        $this->assertSame(
            [$modules[2]->id, $modules[0]->id],
            $revision->modules->pluck('curriculum_entity_id')->all(),
        );

        try {
            $revision->update(['title' => 'Mutated title']);
            $this->fail('An immutable course revision unexpectedly allowed an update.');
        } catch (LogicException) {
            $this->assertSame('Front Office sequence', $revision->fresh()->title);
        }

        $otherPackage = $this->packageWithModules('other', 1)[0];
        $otherPackage->update(['is_active' => false]);
        CurriculumPackage::query()->whereKey($package->getKey())->update(['is_active' => true]);
        $this->expectException(InvalidArgumentException::class);
        app(CourseRevisionService::class)->create(
            $admin,
            $course,
            $package,
            [$modules[0]->id, $otherPackage->entities()->firstOrFail()->id],
            'Invalid mixed selection',
        );
    }

    public function test_only_relationship_scoped_staff_and_enrolled_learners_receive_class_access(): void
    {
        $institution = $this->polinema();
        $otherInstitution = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $admin = User::factory()->create(['role' => UserRole::Learner]);
        $instructor = User::factory()->create(['role' => UserRole::Learner]);
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $outsider = User::factory()->create(['role' => UserRole::Learner]);
        $adminMembership = $this->membership($admin, $institution, InstitutionRole::InstitutionAdmin);
        $instructorMembership = $this->membership($instructor, $institution, InstitutionRole::Instructor);
        $learnerMembership = $this->membership($learner, $institution, InstitutionRole::Learner);
        $this->membership($outsider, $otherInstitution, InstitutionRole::InstitutionAdmin);

        $course = $this->course($institution, $admin);
        [$package, $modules] = $this->packageWithModules('access', 1);
        $revision = app(CourseRevisionService::class)->create($admin, $course, $package, [$modules[0]->id], 'Revision 1');
        $offering = $this->offering($institution, $course, $revision, $admin);

        TeachingAssignment::query()->create([
            'course_offering_id' => $offering->id,
            'institution_membership_id' => $instructorMembership->id,
            'role' => TeachingAssignmentRole::Primary,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        CourseEnrollment::query()->create([
            'course_offering_id' => $offering->id,
            'institution_membership_id' => $learnerMembership->id,
            'status' => CourseEnrollmentStatus::Active,
            'enrolled_by_user_id' => $admin->id,
            'enrolled_at' => now(),
        ]);

        $access = app(CourseAccessService::class);
        $this->assertTrue($access->canManageOffering($admin, $offering));
        $this->assertTrue($access->canManageOffering($instructor, $offering));
        $this->assertTrue($access->canViewOffering($learner, $offering));
        $this->assertFalse($access->canManageOffering($learner, $offering));
        $this->assertFalse($access->canViewOffering($outsider, $offering));
        $this->assertTrue(Gate::forUser($instructor)->allows('update', $offering));
        $this->assertTrue(Gate::forUser($learner)->allows('view', $offering));
        $this->assertFalse(Gate::forUser($outsider)->allows('view', $offering));
        $this->assertTrue(Gate::forUser($admin)->allows('create', [Course::class, $institution]));

        $adminMembership->update(['status' => InstitutionMembershipStatus::Suspended]);
        $this->assertFalse($access->canManageOffering($admin, $offering));

        $lifecycle = app(CourseOfferingLifecycle::class);
        try {
            $lifecycle->transition(
                $outsider,
                $offering,
                CourseOfferingStatus::Active,
                CourseOfferingStatus::Closed,
                'Cross-tenant transition must fail.',
            );
            $this->fail('A cross-tenant actor unexpectedly changed the Class lifecycle.');
        } catch (AuthorizationException) {
            $this->assertSame(CourseOfferingStatus::Active, $offering->fresh()->status);
        }

        try {
            $offering->update(['status' => CourseOfferingStatus::Closed]);
            $this->fail('A direct Class lifecycle update unexpectedly bypassed the transition service.');
        } catch (LogicException) {
            $this->assertSame(CourseOfferingStatus::Active, $offering->fresh()->status);
        }

        try {
            $lifecycle->transition(
                $instructor,
                $offering,
                CourseOfferingStatus::Active,
                CourseOfferingStatus::Archived,
                'Skipping Closed must fail.',
            );
            $this->fail('An invalid Class lifecycle transition unexpectedly succeeded.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('course_offering_events', 0);
        }

        $offering = $lifecycle->transition(
            $instructor,
            $offering,
            CourseOfferingStatus::Active,
            CourseOfferingStatus::Closed,
            'Teaching period has ended.',
        );
        $offering = $lifecycle->transition(
            $instructor,
            $offering,
            CourseOfferingStatus::Closed,
            CourseOfferingStatus::Archived,
            'Retain the completed Class as historical evidence.',
        );
        $this->assertFalse($access->canManageOffering($instructor, $offering));
        $this->assertTrue($access->canViewOffering($instructor, $offering));
        $this->assertSame(2, $offering->events()->count());
        $this->assertSame(CourseOfferingStatus::Active, $offering->events()->firstOrFail()->from_status);

        $event = $offering->events()->firstOrFail();
        try {
            $event->delete();
            $this->fail('Append-only Class lifecycle evidence unexpectedly allowed deletion.');
        } catch (LogicException) {
            $this->assertDatabaseCount('course_offering_events', 2);
        }
    }

    public function test_class_relationships_reject_cross_institution_rows_and_allow_one_active_primary(): void
    {
        $institution = $this->polinema();
        $otherInstitution = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $admin = User::factory()->create(['role' => UserRole::Superadmin]);
        $course = $this->course($institution, $admin);
        [$package, $modules] = $this->packageWithModules('integrity', 1);
        $revision = app(CourseRevisionService::class)->create($admin, $course, $package, [$modules[0]->id], 'Revision 1');
        $offering = $this->offering($institution, $course, $revision, $admin);
        $first = User::factory()->create();
        $second = User::factory()->create();
        $firstMembership = $this->membership($first, $institution, InstitutionRole::Instructor);
        $secondMembership = $this->membership($second, $institution, InstitutionRole::Instructor);

        $primary = TeachingAssignment::query()->create([
            'course_offering_id' => $offering->id,
            'institution_membership_id' => $firstMembership->id,
            'role' => TeachingAssignmentRole::Primary,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $this->assertSame(1, $primary->primary_slot);

        try {
            TeachingAssignment::query()->create([
                'course_offering_id' => $offering->id,
                'institution_membership_id' => $secondMembership->id,
                'role' => TeachingAssignmentRole::Primary,
                'assigned_by_user_id' => $admin->id,
                'assigned_at' => now(),
            ]);
            $this->fail('A second active primary instructor unexpectedly passed the database constraint.');
        } catch (QueryException) {
            $this->assertDatabaseCount('teaching_assignments', 1);
        }

        $primary->update(['revoked_at' => now()]);
        $this->assertNull($primary->fresh()->primary_slot);
        $replacement = TeachingAssignment::query()->create([
            'course_offering_id' => $offering->id,
            'institution_membership_id' => $secondMembership->id,
            'role' => TeachingAssignmentRole::Primary,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $this->assertSame(1, $replacement->primary_slot);

        $crossTenantMembership = $this->membership(
            User::factory()->create(),
            $otherInstitution,
            InstitutionRole::Learner,
        );
        $this->expectException(LogicException::class);
        CourseEnrollment::query()->create([
            'course_offering_id' => $offering->id,
            'institution_membership_id' => $crossTenantMembership->id,
            'status' => CourseEnrollmentStatus::Active,
            'enrolled_at' => now(),
        ]);
    }

    public function test_non_admin_cannot_create_course_revision_and_history_cannot_be_deleted(): void
    {
        $institution = $this->polinema();
        $admin = User::factory()->create(['role' => UserRole::Learner]);
        $instructor = User::factory()->create(['role' => UserRole::Learner]);
        $this->membership($admin, $institution, InstitutionRole::InstitutionAdmin);
        $this->membership($instructor, $institution, InstitutionRole::Instructor);
        $course = $this->course($institution, $admin);
        [$package, $modules] = $this->packageWithModules('deny', 1);

        try {
            app(CourseRevisionService::class)->create($instructor, $course, $package, [$modules[0]->id], 'Denied');
            $this->fail('An Institution Instructor unexpectedly created a Course Revision in B06-A.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('course_revisions', 0);
        }

        $revision = app(CourseRevisionService::class)->create($admin, $course, $package, [$modules[0]->id], 'Allowed');
        $this->expectException(LogicException::class);
        $revision->delete();
    }

    public function test_composite_foreign_keys_reject_raw_cross_tenant_enrollment_bypasses(): void
    {
        $institution = $this->polinema();
        $otherInstitution = Institution::query()->where('key', 'hospitrainity-hq')->firstOrFail();
        $admin = User::factory()->create(['role' => UserRole::Superadmin]);
        $course = $this->course($institution, $admin);
        [$package, $modules] = $this->packageWithModules('database-boundary', 1);
        $revision = app(CourseRevisionService::class)->create($admin, $course, $package, [$modules[0]->id], 'Revision 1');
        $offering = $this->offering($institution, $course, $revision, $admin);
        $otherMembership = $this->membership(
            User::factory()->create(),
            $otherInstitution,
            InstitutionRole::Learner,
        );

        $this->expectException(QueryException::class);
        DB::table('course_enrollments')->insert([
            'course_offering_id' => $offering->id,
            'institution_id' => $institution->id,
            'institution_membership_id' => $otherMembership->id,
            'status' => CourseEnrollmentStatus::Active->value,
            'enrolled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_b06_migration_rolls_back_and_reapplies_in_the_isolated_test_database(): void
    {
        $migration = require database_path('migrations/2026_07_21_000019_establish_course_class_and_time_foundation.php');

        $migration->down();
        $this->assertFalse(Schema::hasTable('courses'));
        $this->assertFalse(Schema::hasColumn('users', 'timezone'));

        $migration->up();
        $this->assertTrue(Schema::hasTable('courses'));
        $this->assertTrue(Schema::hasTable('course_offerings'));
        $this->assertTrue(Schema::hasTable('course_offering_events'));
        $this->assertTrue(Schema::hasColumn('users', 'timezone'));
        $this->assertSame(
            'Asia/Jakarta',
            Institution::query()->where('key', 'politeknik-negeri-malang')->value('timezone'),
        );
    }

    private function polinema(): Institution
    {
        return Institution::query()->where('key', 'politeknik-negeri-malang')->firstOrFail();
    }

    private function membership(User $user, Institution $institution, InstitutionRole $role): InstitutionMembership
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
            'role' => $role,
            'assigned_by_user_id' => null,
            'assigned_at' => now(),
        ]);

        return $membership;
    }

    private function course(Institution $institution, User $creator): Course
    {
        return Course::query()->create([
            'institution_id' => $institution->id,
            'key' => 'Front Office English',
            'title' => 'Front Office English',
            'created_by_user_id' => $creator->id,
        ]);
    }

    /** @return array{CurriculumPackage, list<CurriculumEntity>} */
    private function packageWithModules(string $suffix, int $count): array
    {
        CurriculumPackage::query()->where('is_active', true)->update(['is_active' => false]);
        $sourceHash = hash('sha256', 'package-'.$suffix);
        $package = CurriculumPackage::query()->create([
            'package_name' => 'course-fixture-'.$suffix,
            'content_version' => '1.0.0-'.$suffix,
            'schema_version' => '2.1.0',
            'namespace_uuid' => '6ba7b810-9dad-11d1-80b4-00c04fd430c8',
            'lifecycle_status' => 'published',
            'source_path' => 'tests/'.$suffix,
            'source_tree_sha256' => $sourceHash,
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
                'curriculum_package_id' => $package->id,
                'entity_uuid' => sprintf('00000000-0000-4000-8000-%012d', $position),
                'code' => 'module-'.$suffix.'-'.$position,
                'entity_type' => 'chapter',
                'position' => $position,
                'lifecycle_status' => 'published',
                'content_version' => '1.0.0',
                'source_path' => 'chapters/'.$position.'.json',
                'source_sha256' => hash('sha256', $suffix.'-'.$position),
                'payload' => ['title' => 'Module '.$position],
            ]);
        }

        return [$package, $modules];
    }

    private function offering(
        Institution $institution,
        Course $course,
        CourseRevision $revision,
        User $creator,
    ): CourseOffering {
        return CourseOffering::query()->create([
            'institution_id' => $institution->id,
            'course_id' => $course->id,
            'course_revision_id' => $revision->id,
            'key' => 'Class A',
            'title' => 'Class A',
            'status' => CourseOfferingStatus::Active,
            'created_by_user_id' => $creator->id,
        ]);
    }
}

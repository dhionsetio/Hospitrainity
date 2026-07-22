<?php

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertNormalizedRoleMappingsAreComplete();

        Schema::table('course_enrollments', function (Blueprint $table): void {
            $table->unique(
                ['id', 'institution_membership_id', 'course_offering_id'],
                'course_enrollment_learning_scope_unique',
            );
        });

        foreach (['curriculum_activity_progress', 'curriculum_attempts', 'completions'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->uuid('course_offering_id')->nullable()->after('institution_membership_id');
                $table->foreignId('course_enrollment_id')->nullable()->after('course_offering_id');
                $table->foreign(
                    ['course_enrollment_id', 'institution_membership_id', 'course_offering_id'],
                    $tableName.'_class_learning_scope_foreign',
                )->references(['id', 'institution_membership_id', 'course_offering_id'])
                    ->on('course_enrollments')
                    ->restrictOnDelete();
                $table->index(
                    ['course_offering_id', 'user_id', 'updated_at'],
                    $tableName.'_class_user_activity',
                );
            });
        }
    }

    public function down(): void
    {
        foreach (['curriculum_activity_progress', 'curriculum_attempts', 'completions'] as $tableName) {
            if (DB::table($tableName)->whereNotNull('course_enrollment_id')->exists()) {
                throw new RuntimeException(
                    "Rollback refused: {$tableName} contains retained Class learning history.",
                );
            }
        }

        foreach (['completions', 'curriculum_attempts', 'curriculum_activity_progress'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (DB::getDriverName() === 'sqlite') {
                    $table->dropForeign(['course_enrollment_id', 'institution_membership_id', 'course_offering_id']);
                } else {
                    $table->dropForeign($tableName.'_class_learning_scope_foreign');
                }
                $table->dropIndex($tableName.'_class_user_activity');
                $table->dropColumn(['course_enrollment_id', 'course_offering_id']);
            });
        }

        Schema::table('course_enrollments', function (Blueprint $table): void {
            $table->dropUnique('course_enrollment_learning_scope_unique');
        });
    }

    private function assertNormalizedRoleMappingsAreComplete(): void
    {
        $unmappedStaff = DB::table('institution_memberships as memberships')
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->where('memberships.status', InstitutionMembershipStatus::Active->value)
            ->whereIn('users.role', [UserRole::Supervisor->value, UserRole::Admin->value])
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('institution_role_assignments as roles')
                    ->whereColumn('roles.institution_membership_id', 'memberships.id')
                    ->where('roles.role', InstitutionRole::Instructor->value)
                    ->whereNull('roles.revoked_at');
            })
            ->exists();

        $unmappedLearners = DB::table('institution_memberships as memberships')
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->where('memberships.status', InstitutionMembershipStatus::Active->value)
            ->where('users.role', UserRole::Learner->value)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('institution_role_assignments as roles')
                    ->whereColumn('roles.institution_membership_id', 'memberships.id')
                    ->where('roles.role', InstitutionRole::Learner->value)
                    ->whereNull('roles.revoked_at');
            })
            ->exists();

        if ($unmappedStaff || $unmappedLearners) {
            throw new RuntimeException(
                'Class-context cutover refused: one or more active legacy memberships lack their normalized role mapping.',
            );
        }
    }
};

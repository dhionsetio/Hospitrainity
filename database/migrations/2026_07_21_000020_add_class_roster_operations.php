<?php

use App\Enums\CourseEnrollmentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institution_invitations', function (Blueprint $table): void {
            $table->uuid('course_offering_id')->nullable()->after('institution_id');
            $table->foreign(
                ['course_offering_id', 'institution_id'],
                'institution_invitation_offering_institution_foreign',
            )->references(['id', 'institution_id'])->on('course_offerings')->restrictOnDelete();
            $table->index(['course_offering_id', 'created_at'], 'institution_invitation_offering_index');
        });

        Schema::table('institution_join_codes', function (Blueprint $table): void {
            $table->uuid('course_offering_id')->nullable()->after('institution_id');
            $table->foreign(
                ['course_offering_id', 'institution_id'],
                'institution_join_code_offering_institution_foreign',
            )->references(['id', 'institution_id'])->on('course_offerings')->restrictOnDelete();
            $table->index(['course_offering_id', 'created_at'], 'institution_join_code_offering_index');
        });

        Schema::table('institution_join_requests', function (Blueprint $table): void {
            $table->uuid('course_offering_id')->nullable()->after('institution_id');
            $table->foreign(
                ['course_offering_id', 'institution_id'],
                'institution_join_request_offering_institution_foreign',
            )->references(['id', 'institution_id'])->on('course_offerings')->restrictOnDelete();
            $table->index(
                ['course_offering_id', 'status', 'requested_at'],
                'institution_join_request_offering_status',
            );
        });

        Schema::table('teaching_assignments', function (Blueprint $table): void {
            $table->unsignedBigInteger('active_membership_slot')->nullable()->after('institution_membership_id');
        });
        DB::table('teaching_assignments')
            ->whereNull('revoked_at')
            ->update(['active_membership_slot' => DB::raw('institution_membership_id')]);
        Schema::table('teaching_assignments', function (Blueprint $table): void {
            $table->dropUnique('teaching_assignment_membership_unique');
            $table->unique(
                ['course_offering_id', 'active_membership_slot'],
                'teaching_assignment_active_membership_unique',
            );
        });

        Schema::table('course_enrollments', function (Blueprint $table): void {
            $table->unique(
                ['id', 'course_offering_id', 'institution_id'],
                'course_enrollment_scope_unique',
            );
        });

        Schema::create('course_enrollment_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('course_enrollment_id');
            $table->uuid('course_offering_id');
            $table->uuid('institution_id');
            $table->enum('from_status', CourseEnrollmentStatus::values())->nullable();
            $table->enum('to_status', CourseEnrollmentStatus::values());
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('transfer_to_course_offering_id')->nullable();
            $table->string('reason', 500);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(
                ['course_enrollment_id', 'course_offering_id', 'institution_id'],
                'course_enrollment_event_scope_foreign',
            )->references(['id', 'course_offering_id', 'institution_id'])->on('course_enrollments')->restrictOnDelete();
            $table->foreign(
                ['transfer_to_course_offering_id', 'institution_id'],
                'course_enrollment_event_transfer_offering_foreign',
            )->references(['id', 'institution_id'])->on('course_offerings')->restrictOnDelete();
            $table->index(['course_offering_id', 'created_at'], 'course_enrollment_event_offering');
            $table->index(['institution_id', 'created_at'], 'course_enrollment_event_institution');
        });
    }

    public function down(): void
    {
        $hasClassConnections = DB::table('institution_invitations')->whereNotNull('course_offering_id')->exists()
            || DB::table('institution_join_codes')->whereNotNull('course_offering_id')->exists()
            || DB::table('institution_join_requests')->whereNotNull('course_offering_id')->exists();
        $hasRosterEvents = DB::table('course_enrollment_events')->exists();
        $hasRepeatedTeachingHistory = DB::table('teaching_assignments')
            ->select(['course_offering_id', 'institution_membership_id'])
            ->groupBy(['course_offering_id', 'institution_membership_id'])
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasClassConnections || $hasRosterEvents || $hasRepeatedTeachingHistory) {
            throw new RuntimeException('B06-B rollback refused because retained Class connection or roster history exists. Disable the feature without dropping evidence.');
        }

        Schema::dropIfExists('course_enrollment_events');
        Schema::table('course_enrollments', function (Blueprint $table): void {
            $table->dropUnique('course_enrollment_scope_unique');
        });

        Schema::table('teaching_assignments', function (Blueprint $table): void {
            $table->dropUnique('teaching_assignment_active_membership_unique');
            $table->dropColumn('active_membership_slot');
            $table->unique(
                ['course_offering_id', 'institution_membership_id'],
                'teaching_assignment_membership_unique',
            );
        });

        Schema::table('institution_join_requests', function (Blueprint $table): void {
            $table->dropForeign('institution_join_request_offering_institution_foreign');
            $table->dropIndex('institution_join_request_offering_status');
            $table->dropColumn('course_offering_id');
        });
        Schema::table('institution_join_codes', function (Blueprint $table): void {
            $table->dropForeign('institution_join_code_offering_institution_foreign');
            $table->dropIndex('institution_join_code_offering_index');
            $table->dropColumn('course_offering_id');
        });
        Schema::table('institution_invitations', function (Blueprint $table): void {
            $table->dropForeign('institution_invitation_offering_institution_foreign');
            $table->dropIndex('institution_invitation_offering_index');
            $table->dropColumn('course_offering_id');
        });
    }
};

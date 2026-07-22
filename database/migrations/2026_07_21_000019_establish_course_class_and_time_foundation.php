<?php

use App\Enums\CourseEnrollmentStatus;
use App\Enums\CourseOfferingStatus;
use App\Enums\TeachingAssignmentRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('institutions', function (Blueprint $table): void {
            $table->string('timezone', 64)->nullable()->after('name_en');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->string('timezone', 64)->nullable()->after('ui_no_audio');
        });

        DB::table('institutions')
            ->where('key', 'politeknik-negeri-malang')
            ->update(['timezone' => 'Asia/Jakarta']);

        Schema::table('institution_memberships', function (Blueprint $table): void {
            $table->unique(['id', 'institution_id'], 'institution_membership_id_institution_unique');
        });
        Schema::table('curriculum_entities', function (Blueprint $table): void {
            $table->unique(['id', 'curriculum_package_id'], 'curriculum_entity_id_package_unique');
        });

        Schema::create('courses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('institution_id');
            $table->string('key', 100);
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
            $table->unique(['institution_id', 'key']);
            $table->unique(['id', 'institution_id'], 'course_id_institution_unique');
            $table->index(['institution_id', 'archived_at']);
        });

        Schema::create('course_revisions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('course_id');
            $table->foreignId('curriculum_package_id')->constrained('curriculum_packages')->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('title', 180);
            $table->char('content_sha256', 64);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('course_id')->references('id')->on('courses')->restrictOnDelete();
            $table->unique(['course_id', 'revision_number']);
            $table->unique(['id', 'course_id'], 'course_revision_id_course_unique');
            $table->unique(['id', 'curriculum_package_id'], 'course_revision_id_package_unique');
            $table->index(['curriculum_package_id', 'created_at']);
        });

        Schema::create('course_revision_modules', function (Blueprint $table): void {
            $table->id();
            $table->uuid('course_revision_id');
            $table->foreignId('curriculum_package_id');
            $table->foreignId('curriculum_entity_id');
            $table->unsignedInteger('position');

            $table->foreign(
                ['course_revision_id', 'curriculum_package_id'],
                'course_revision_module_revision_package_foreign',
            )->references(['id', 'curriculum_package_id'])->on('course_revisions')->restrictOnDelete();
            $table->foreign(
                ['curriculum_entity_id', 'curriculum_package_id'],
                'course_revision_module_entity_package_foreign',
            )->references(['id', 'curriculum_package_id'])->on('curriculum_entities')->restrictOnDelete();
            $table->unique(['course_revision_id', 'position'], 'course_revision_position_unique');
            $table->unique(['course_revision_id', 'curriculum_entity_id'], 'course_revision_entity_unique');
        });

        Schema::create('course_offerings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('institution_id');
            $table->uuid('course_id');
            $table->uuid('course_revision_id');
            $table->string('key', 100);
            $table->string('title', 180);
            $table->string('term_label', 120)->nullable();
            $table->enum('status', CourseOfferingStatus::values())->default(CourseOfferingStatus::Draft->value);
            $table->string('timezone', 64)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
            $table->foreign(
                ['course_id', 'institution_id'],
                'course_offering_course_institution_foreign',
            )->references(['id', 'institution_id'])->on('courses')->restrictOnDelete();
            $table->foreign(
                ['course_revision_id', 'course_id'],
                'course_offering_revision_course_foreign',
            )->references(['id', 'course_id'])->on('course_revisions')->restrictOnDelete();
            $table->unique(['institution_id', 'key']);
            $table->unique(['id', 'institution_id'], 'course_offering_id_institution_unique');
            $table->index(['institution_id', 'status']);
            $table->index(['course_revision_id', 'status']);
        });

        Schema::create('course_enrollments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('course_offering_id');
            $table->uuid('institution_id');
            $table->foreignId('institution_membership_id');
            $table->enum('status', CourseEnrollmentStatus::values())->default(CourseEnrollmentStatus::Active->value);
            $table->foreignId('enrolled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('enrolled_at');
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->foreign(
                ['course_offering_id', 'institution_id'],
                'course_enrollment_offering_institution_foreign',
            )->references(['id', 'institution_id'])->on('course_offerings')->restrictOnDelete();
            $table->foreign(
                ['institution_membership_id', 'institution_id'],
                'course_enrollment_membership_institution_foreign',
            )->references(['id', 'institution_id'])->on('institution_memberships')->restrictOnDelete();
            $table->unique(['course_offering_id', 'institution_membership_id'], 'course_enrollment_membership_unique');
            $table->index(['institution_membership_id', 'status'], 'course_enrollment_member_status');
            $table->index(['course_offering_id', 'status'], 'course_enrollment_offering_status');
        });

        Schema::create('teaching_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('course_offering_id');
            $table->uuid('institution_id');
            $table->foreignId('institution_membership_id');
            $table->enum('role', TeachingAssignmentRole::values());
            $table->unsignedTinyInteger('primary_slot')->nullable();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign(
                ['course_offering_id', 'institution_id'],
                'teaching_assignment_offering_institution_foreign',
            )->references(['id', 'institution_id'])->on('course_offerings')->restrictOnDelete();
            $table->foreign(
                ['institution_membership_id', 'institution_id'],
                'teaching_assignment_membership_institution_foreign',
            )->references(['id', 'institution_id'])->on('institution_memberships')->restrictOnDelete();
            $table->unique(['course_offering_id', 'institution_membership_id'], 'teaching_assignment_membership_unique');
            $table->unique(['course_offering_id', 'primary_slot'], 'teaching_primary_slot_unique');
            $table->index(['institution_membership_id', 'revoked_at'], 'teaching_assignment_member_active');
            $table->index(['course_offering_id', 'revoked_at'], 'teaching_assignment_offering_active');
        });

        Schema::create('course_offering_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('course_offering_id');
            $table->uuid('institution_id');
            $table->enum('from_status', CourseOfferingStatus::values());
            $table->enum('to_status', CourseOfferingStatus::values());
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(
                ['course_offering_id', 'institution_id'],
                'course_offering_event_offering_institution_foreign',
            )->references(['id', 'institution_id'])->on('course_offerings')->restrictOnDelete();
            $table->index(['course_offering_id', 'created_at'], 'course_offering_event_history');
            $table->index(['institution_id', 'created_at'], 'course_offering_event_institution');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_offering_events');
        Schema::dropIfExists('teaching_assignments');
        Schema::dropIfExists('course_enrollments');
        Schema::dropIfExists('course_offerings');
        Schema::dropIfExists('course_revision_modules');
        Schema::dropIfExists('course_revisions');
        Schema::dropIfExists('courses');

        Schema::table('curriculum_entities', function (Blueprint $table): void {
            $table->dropUnique('curriculum_entity_id_package_unique');
        });
        Schema::table('institution_memberships', function (Blueprint $table): void {
            $table->dropUnique('institution_membership_id_institution_unique');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
        Schema::table('institutions', function (Blueprint $table): void {
            $table->dropColumn('timezone');
        });
    }
};

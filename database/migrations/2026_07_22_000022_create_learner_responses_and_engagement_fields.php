<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('learning_streak_enabled')->default(true)->after('ui_no_audio');
        });

        Schema::table('curriculum_activity_progress', function (Blueprint $table): void {
            $table->string('review_policy_version', 40)->nullable()->after('baseline_skipped_at');
            $table->unsignedSmallInteger('review_step')->default(0)->after('review_policy_version');
            $table->timestamp('review_due_at')->nullable()->after('review_step')->index();
            $table->timestamp('last_reviewed_at')->nullable()->after('review_due_at');
        });

        Schema::table('institution_memberships', function (Blueprint $table): void {
            $table->unique(['id', 'user_id'], 'institution_membership_id_user_unique');
        });
        Schema::table('course_offerings', function (Blueprint $table): void {
            $table->unique(['id', 'course_revision_id'], 'course_offering_id_revision_unique');
        });

        Schema::create('learner_text_responses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('response_key');
            $table->string('learning_scope_key', 180);
            $table->foreignId('institution_membership_id')->nullable();
            $table->uuid('course_offering_id')->nullable();
            $table->foreignId('course_enrollment_id')->nullable();
            $table->foreignUuid('course_revision_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('curriculum_package_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('activity_entity_id');
            $table->unsignedBigInteger('prompt_entity_id');
            $table->char('activity_source_sha256', 64);
            $table->char('prompt_source_sha256', 64);
            $table->enum('kind', ['assessment', 'journal']);
            $table->enum('state', ['draft', 'submitted'])->default('draft');
            $table->text('body');
            $table->char('body_hmac_sha256', 64);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign(
                ['course_enrollment_id', 'institution_membership_id', 'course_offering_id'],
                'learner_response_class_scope_foreign',
            )->references(['id', 'institution_membership_id', 'course_offering_id'])
                ->on('course_enrollments')
                ->restrictOnDelete();
            $table->foreign(
                ['institution_membership_id', 'user_id'],
                'learner_response_membership_owner_foreign',
            )->references(['id', 'user_id'])
                ->on('institution_memberships')
                ->restrictOnDelete();
            $table->foreign(
                ['course_offering_id', 'course_revision_id'],
                'learner_response_offering_revision_foreign',
            )->references(['id', 'course_revision_id'])
                ->on('course_offerings')
                ->restrictOnDelete();
            $table->foreign(
                ['activity_entity_id', 'curriculum_package_id'],
                'learner_response_activity_package_foreign',
            )->references(['id', 'curriculum_package_id'])
                ->on('curriculum_entities')
                ->restrictOnDelete();
            $table->foreign(
                ['prompt_entity_id', 'curriculum_package_id'],
                'learner_response_prompt_package_foreign',
            )->references(['id', 'curriculum_package_id'])
                ->on('curriculum_entities')
                ->restrictOnDelete();

            $table->unique(['user_id', 'response_key'], 'learner_response_owner_key_unique');
            $table->index(['user_id', 'learning_scope_key', 'updated_at'], 'learner_response_owner_scope');
            $table->index(['course_offering_id', 'state', 'submitted_at'], 'learner_response_class_state');
            $table->index(['activity_entity_id', 'prompt_entity_id'], 'learner_response_activity_prompt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_text_responses');

        Schema::table('course_offerings', function (Blueprint $table): void {
            $table->dropUnique('course_offering_id_revision_unique');
        });
        Schema::table('institution_memberships', function (Blueprint $table): void {
            $table->dropUnique('institution_membership_id_user_unique');
        });

        Schema::table('curriculum_activity_progress', function (Blueprint $table): void {
            $table->dropIndex(['review_due_at']);
            $table->dropColumn([
                'review_policy_version',
                'review_step',
                'review_due_at',
                'last_reviewed_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('learning_streak_enabled');
        });
    }
};

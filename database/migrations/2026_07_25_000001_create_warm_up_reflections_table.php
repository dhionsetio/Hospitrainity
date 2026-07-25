<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warm_up_reflections', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('response_key');
            $table->string('learning_scope_key', 180);
            $table->foreignId('institution_membership_id')->nullable();
            $table->uuid('course_offering_id')->nullable();
            $table->foreignId('course_enrollment_id')->nullable();
            $table->foreignUuid('course_revision_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('curriculum_package_id')->constrained()->restrictOnDelete();
            $table->string('chapter_code', 64);
            $table->string('section_code', 64);
            $table->char('section_source_sha256', 64);
            $table->unsignedSmallInteger('prompt_index');
            $table->char('prompt_fingerprint', 64);
            $table->enum('state', ['draft', 'submitted'])->default('draft');
            $table->text('body')->nullable();
            $table->char('body_hmac_sha256', 64)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->foreign(
                ['course_enrollment_id', 'institution_membership_id', 'course_offering_id'],
                'warm_up_reflection_class_scope_foreign',
            )->references(['id', 'institution_membership_id', 'course_offering_id'])
                ->on('course_enrollments')
                ->restrictOnDelete();
            $table->foreign(
                ['institution_membership_id', 'user_id'],
                'warm_up_reflection_membership_owner_foreign',
            )->references(['id', 'user_id'])
                ->on('institution_memberships')
                ->restrictOnDelete();
            $table->foreign(
                ['course_offering_id', 'course_revision_id'],
                'warm_up_reflection_offering_revision_foreign',
            )->references(['id', 'course_revision_id'])
                ->on('course_offerings')
                ->restrictOnDelete();

            $table->unique(['user_id', 'response_key'], 'warm_up_reflection_owner_key_unique');
            $table->unique(
                ['user_id', 'learning_scope_key', 'chapter_code', 'section_code', 'prompt_index'],
                'warm_up_reflection_owner_prompt_unique',
            );
            $table->index(['user_id', 'learning_scope_key', 'updated_at'], 'warm_up_reflection_owner_scope');
            $table->index(['course_offering_id', 'state', 'submitted_at'], 'warm_up_reflection_class_state');
            $table->index(['section_code', 'prompt_index'], 'warm_up_reflection_section_prompt');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('warm_up_reflections');
    }
};

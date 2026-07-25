<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_offering_revision_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('course_offering_id');
            $table->uuid('institution_id');
            $table->uuid('from_course_revision_id');
            $table->uuid('to_course_revision_id');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 500);
            $table->timestamp('created_at')->useCurrent();

            $table->foreign(
                ['course_offering_id', 'institution_id'],
                'class_revision_event_offering_institution_foreign',
            )->references(['id', 'institution_id'])->on('course_offerings')->restrictOnDelete();
            $table->foreign('from_course_revision_id', 'class_revision_event_from_foreign')
                ->references('id')->on('course_revisions')->restrictOnDelete();
            $table->foreign('to_course_revision_id', 'class_revision_event_to_foreign')
                ->references('id')->on('course_revisions')->restrictOnDelete();
            $table->index(['course_offering_id', 'created_at'], 'class_revision_event_history');
        });

        Schema::create('class_announcements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('course_offering_id');
            $table->uuid('institution_id');
            $table->foreignId('curriculum_package_id')->nullable();
            $table->foreignId('curriculum_entity_id')->nullable();
            $table->string('title', 180);
            $table->text('body');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at');
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->foreign(
                ['course_offering_id', 'institution_id'],
                'class_announcement_offering_institution_foreign',
            )->references(['id', 'institution_id'])->on('course_offerings')->restrictOnDelete();
            $table->foreign(
                ['curriculum_entity_id', 'curriculum_package_id'],
                'class_announcement_module_package_foreign',
            )->references(['id', 'curriculum_package_id'])->on('curriculum_entities')->restrictOnDelete();
            $table->index(
                ['course_offering_id', 'archived_at', 'published_at'],
                'class_announcement_delivery',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_announcements');
        Schema::dropIfExists('course_offering_revision_events');
    }
};

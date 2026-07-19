<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_activity_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('package_name', 100);
            $table->string('content_version', 50);
            $table->string('activity_code', 120);
            $table->string('section_code', 120);
            $table->string('legacy_status', 50)->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('self_checked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('baseline_skipped_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'package_name', 'content_version', 'activity_code'], 'curriculum_progress_version_unique');
            $table->index(['user_id', 'content_version', 'completed_at'], 'curriculum_progress_user_version');
        });

        Schema::create('curriculum_attempts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('package_name', 100);
            $table->string('content_version', 50);
            $table->string('activity_code', 120);
            $table->char('activity_source_sha256', 64);
            $table->uuid('idempotency_key');
            $table->char('submission_hmac_sha256', 64);
            $table->string('intent', 30);
            $table->string('state', 30);
            $table->string('completion_reason', 60)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('attempted_at')->nullable();
            $table->timestamp('self_checked_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'package_name', 'content_version', 'activity_code', 'idempotency_key'], 'curriculum_attempt_idempotency_unique');
            $table->index(['user_id', 'content_version', 'activity_code', 'created_at'], 'curriculum_attempt_history');
        });

        Schema::create('curriculum_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('curriculum_attempt_id')->constrained('curriculum_attempts')->cascadeOnDelete();
            $table->string('prompt_code', 120);
            $table->string('response_form', 40);
            $table->string('scoring_mode', 50);
            $table->json('response')->nullable();
            $table->boolean('response_present');
            $table->boolean('is_correct')->nullable();
            $table->boolean('self_checked')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['curriculum_attempt_id', 'prompt_code']);
            $table->index(['prompt_code', 'created_at']);
        });

        Schema::create('curriculum_attempt_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('curriculum_attempt_id')->constrained('curriculum_attempts')->cascadeOnDelete();
            $table->string('event_type', 30);
            $table->timestamp('occurred_at');

            $table->unique(['curriculum_attempt_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_attempt_events');
        Schema::dropIfExists('curriculum_responses');
        Schema::dropIfExists('curriculum_attempts');
        Schema::dropIfExists('curriculum_activity_progress');
    }
};

<?php

use App\Enums\DataSubjectRequestStatus;
use App\Enums\DataSubjectRequestType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('policy_acknowledgements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('policy_type', 40);
            $table->string('locale', 10);
            $table->string('version', 40);
            $table->char('content_sha256', 64);
            $table->timestamp('acknowledged_at');
            $table->string('source', 40);

            $table->unique(['user_id', 'policy_type', 'version']);
            $table->index(['policy_type', 'version', 'acknowledged_at']);
        });

        Schema::create('data_subject_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->char('subject_reference_hash', 64)->index();
            $table->enum('type', DataSubjectRequestType::values());
            $table->enum('status', DataSubjectRequestStatus::values())
                ->default(DataSubjectRequestStatus::Submitted->value);
            $table->text('request_note')->nullable();
            $table->text('decision_note')->nullable();
            $table->string('reason_code', 80)->nullable();
            $table->timestamp('identity_verified_at')->nullable();
            $table->foreignId('assigned_to_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('due_at')->index();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('executing_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'created_at']);
            $table->index(['status', 'due_at']);
        });

        Schema::create('data_subject_request_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('data_subject_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 80);
            $table->text('metadata')->nullable();
            $table->timestamp('created_at');

            $table->index(['data_subject_request_id', 'created_at']);
        });

        Schema::create('data_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('data_subject_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->text('encrypted_path')->nullable();
            $table->char('payload_sha256', 64)->nullable();
            $table->unsignedBigInteger('payload_bytes')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestamp('available_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('downloaded_at')->nullable();
            $table->timestamps();

            $table->unique('data_subject_request_id');
            $table->index(['user_id', 'status']);
        });

        Schema::create('account_erasure_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('data_subject_request_id')->constrained()->cascadeOnDelete();
            $table->string('step', 80);
            $table->string('status', 30)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error_code', 80)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['data_subject_request_id', 'step'], 'erasure_request_step_unique');
        });

        Schema::create('push_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->text('public_key');
            $table->text('auth_token');
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('account_erasure_steps');
        Schema::dropIfExists('data_exports');
        Schema::dropIfExists('data_subject_request_events');
        Schema::dropIfExists('data_subject_requests');
        Schema::dropIfExists('policy_acknowledgements');
    }
};

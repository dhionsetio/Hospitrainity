<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_drafts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('base_package_id')->nullable()->constrained('curriculum_packages')->restrictOnDelete();
            $table->foreignId('published_package_id')->nullable()->constrained('curriculum_packages')->nullOnDelete();
            $table->string('package_name', 100);
            $table->string('content_version', 50);
            $table->string('schema_version', 50);
            $table->uuid('namespace_uuid');
            $table->string('title', 160);
            $table->string('status', 30)->default('draft')->index();
            $table->unsignedBigInteger('revision')->default(1);
            $table->json('validation_report')->nullable();
            $table->json('diff_report')->nullable();
            $table->uuid('publication_run_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['package_name', 'content_version']);
        });

        Schema::create('curriculum_draft_entities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curriculum_draft_id')->constrained()->cascadeOnDelete();
            $table->foreignId('source_entity_id')->nullable()->constrained('curriculum_entities')->nullOnDelete();
            $table->uuid('entity_uuid')->nullable();
            $table->string('code', 120)->nullable();
            $table->string('entity_type', 60);
            $table->string('parent_code', 120)->nullable();
            $table->integer('position')->nullable();
            $table->string('source_path');
            $table->json('payload');
            $table->unsignedBigInteger('revision')->default(1);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['curriculum_draft_id', 'source_path'], 'curriculum_draft_entity_source_unique');
            $table->unique(['curriculum_draft_id', 'entity_uuid'], 'curriculum_draft_entity_uuid_unique');
            $table->unique(['curriculum_draft_id', 'code'], 'curriculum_draft_entity_code_unique');
            $table->index(['curriculum_draft_id', 'entity_type', 'parent_code'], 'curriculum_draft_entity_lookup');
        });

        Schema::create('curriculum_draft_blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curriculum_draft_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_draft_entity_id')->constrained()->cascadeOnDelete();
            $table->uuid('block_uuid');
            $table->string('block_type', 40);
            $table->integer('position');
            $table->json('payload');
            $table->unsignedBigInteger('revision')->default(1);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['curriculum_draft_id', 'block_uuid'], 'curriculum_draft_block_uuid_unique');
            $table->index(['curriculum_draft_entity_id', 'archived_at', 'position'], 'curriculum_draft_block_order');
        });

        Schema::create('curriculum_draft_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curriculum_draft_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 50);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->unsignedBigInteger('revision');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['curriculum_draft_id', 'created_at'], 'curriculum_draft_event_timeline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_draft_events');
        Schema::dropIfExists('curriculum_draft_blocks');
        Schema::dropIfExists('curriculum_draft_entities');
        Schema::dropIfExists('curriculum_drafts');
    }
};

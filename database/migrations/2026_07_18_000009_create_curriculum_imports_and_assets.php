<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_imports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('curriculum_draft_id')->constrained()->cascadeOnDelete();
            $table->foreignId('base_package_id')->constrained('curriculum_packages')->restrictOnDelete();
            $table->string('status', 24)->index();
            $table->unsignedBigInteger('revision')->default(1);
            $table->string('source_original_name');
            $table->string('source_storage_path')->nullable()->unique();
            $table->char('source_sha256', 64)->index();
            $table->unsignedBigInteger('source_bytes');
            $table->string('detected_mime', 120);
            $table->string('declared_purpose', 500);
            $table->string('compiled_package_path')->nullable()->unique();
            $table->string('evidence_path')->nullable()->unique();
            $table->json('compiler_report')->nullable();
            $table->json('inventory_report')->nullable();
            $table->json('diff_report')->nullable();
            $table->json('error_report')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index(['curriculum_draft_id', 'created_at'], 'curriculum_import_draft_timeline');
        });

        Schema::create('curriculum_asset_blobs', function (Blueprint $table): void {
            $table->id();
            $table->char('sha256', 64)->unique();
            $table->string('storage_path')->unique();
            $table->string('detected_mime', 120);
            $table->string('extension', 10);
            $table->unsignedBigInteger('bytes');
            $table->timestamps();
        });

        Schema::create('curriculum_assets', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('curriculum_draft_id')->constrained()->cascadeOnDelete();
            $table->foreignId('curriculum_asset_blob_id')->constrained()->restrictOnDelete();
            $table->string('display_name', 160);
            $table->string('kind', 16);
            $table->text('accessibility_text');
            $table->text('rights_basis');
            $table->string('source_url', 2048)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['curriculum_draft_id', 'archived_at', 'kind'], 'curriculum_asset_draft_lookup');
        });

        Schema::table('curriculum_draft_blocks', function (Blueprint $table): void {
            $table->foreignId('curriculum_asset_id')->nullable()->after('curriculum_draft_entity_id')
                ->constrained('curriculum_assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('curriculum_draft_blocks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('curriculum_asset_id');
        });
        Schema::dropIfExists('curriculum_assets');
        Schema::dropIfExists('curriculum_asset_blobs');
        Schema::dropIfExists('curriculum_imports');
    }
};

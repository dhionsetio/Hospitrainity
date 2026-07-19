<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_packages', function (Blueprint $table): void {
            $table->id();
            $table->string('package_name', 100);
            $table->string('content_version', 50);
            $table->string('schema_version', 50);
            $table->string('namespace_uuid', 36);
            $table->string('lifecycle_status', 50);
            $table->string('source_path');
            $table->char('source_tree_sha256', 64)->unique();
            $table->unsignedInteger('source_file_count');
            $table->unsignedBigInteger('source_byte_count');
            $table->json('counts');
            $table->json('projection_meta');
            $table->char('laravel_projection_sha256', 64);
            $table->char('standalone_sha256', 64);
            $table->boolean('is_active')->default(false)->index();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['package_name', 'content_version']);
        });

        Schema::create('curriculum_source_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curriculum_package_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->char('sha256', 64);
            $table->unsignedBigInteger('bytes');

            $table->unique(['curriculum_package_id', 'path']);
        });

        Schema::create('curriculum_entities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curriculum_package_id')->constrained()->cascadeOnDelete();
            $table->string('entity_uuid', 36)->nullable();
            $table->string('code', 120)->nullable();
            $table->string('entity_type', 60);
            $table->string('parent_code', 120)->nullable();
            $table->integer('position')->nullable();
            $table->string('lifecycle_status', 50)->nullable();
            $table->string('content_version', 50)->nullable();
            $table->string('source_path');
            $table->char('source_sha256', 64);
            $table->json('payload');

            $table->unique(['curriculum_package_id', 'source_path']);
            $table->unique(['curriculum_package_id', 'entity_uuid']);
            $table->unique(['curriculum_package_id', 'code']);
            $table->index(['curriculum_package_id', 'entity_type', 'parent_code'], 'curriculum_entities_lookup');
        });

        Schema::create('curriculum_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('curriculum_package_id')->constrained()->cascadeOnDelete();
            $table->string('source_code', 120);
            $table->string('relationship', 60);
            $table->string('target_code', 120);
            $table->unsignedInteger('position')->default(0);
            $table->json('metadata')->nullable();

            $table->unique(
                ['curriculum_package_id', 'source_code', 'relationship', 'target_code'],
                'curriculum_links_unique'
            );
            $table->index(['curriculum_package_id', 'target_code'], 'curriculum_links_target');
        });

        Schema::create('curriculum_import_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('curriculum_package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30);
            $table->string('status', 30);
            $table->char('source_tree_sha256', 64)->nullable();
            $table->char('before_database_sha256', 64)->nullable();
            $table->char('after_database_sha256', 64)->nullable();
            $table->char('standalone_sha256', 64)->nullable();
            $table->string('rollback_path')->nullable();
            $table->char('rollback_sha256', 64)->nullable();
            $table->string('report_path')->nullable();
            $table->json('report');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_import_runs');
        Schema::dropIfExists('curriculum_links');
        Schema::dropIfExists('curriculum_entities');
        Schema::dropIfExists('curriculum_source_files');
        Schema::dropIfExists('curriculum_packages');
    }
};

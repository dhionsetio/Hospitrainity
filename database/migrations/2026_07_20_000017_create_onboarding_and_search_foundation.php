<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_onboarding_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('context_role', 32);
            $table->string('status', 20)->default('pending');
            $table->unsignedSmallInteger('current_step')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('restarted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'context_role']);
            $table->index(['status', 'updated_at']);
        });

        Schema::create('search_index_generations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('source_fingerprint', 64);
            $table->unsignedInteger('document_count')->default(0);
            $table->boolean('is_active')->default(false)->index();
            $table->timestamp('built_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['source_fingerprint', 'is_active']);
        });

        Schema::create('search_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('search_index_generation_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 32);
            $table->string('source_key', 191);
            $table->string('route_reference', 191);
            $table->string('locale', 5);
            $table->string('audience', 32)->default('authenticated');
            $table->string('title');
            $table->text('summary');
            $table->boolean('published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->char('content_sha256', 64);

            $table->unique(
                ['search_index_generation_id', 'source_type', 'source_key', 'locale'],
                'search_documents_generation_source_unique',
            );
            $table->index(
                ['search_index_generation_id', 'published', 'locale', 'source_type'],
                'search_documents_scope_index',
            );
        });

        Schema::create('search_document_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('search_document_id')->constrained()->cascadeOnDelete();
            $table->string('term', 64);
            $table->unsignedSmallInteger('weight');

            $table->unique(['search_document_id', 'term']);
            $table->index(['term', 'search_document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('search_document_terms');
        Schema::dropIfExists('search_documents');
        Schema::dropIfExists('search_index_generations');
        Schema::dropIfExists('user_onboarding_states');
    }
};

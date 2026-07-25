<?php

use App\Enums\CurriculumApprovalGate;
use App\Enums\CurriculumReleaseState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('curriculum_release_locks', function (Blueprint $table): void {
            $table->string('name', 80)->primary();
            $table->timestamps();
        });
        DB::table('curriculum_release_locks')->insert([
            'name' => 'lifecycle-transition',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('curriculum_releases', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('curriculum_package_id')->unique()->constrained()->restrictOnDelete();
            $table->enum('state', CurriculumReleaseState::values())->default(CurriculumReleaseState::Draft->value)->index();
            $table->boolean('preview_only')->default(true);
            $table->char('source_tree_sha256', 64);
            $table->foreignId('activated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('retired_at')->nullable();
            $table->timestamps();
        });

        Schema::create('curriculum_release_approvals', function (Blueprint $table): void {
            $table->id();
            $table->uuid('curriculum_release_id');
            $table->enum('gate', CurriculumApprovalGate::values());
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewer_name');
            $table->string('reviewer_qualification');
            $table->char('evidence_sha256', 64);
            $table->timestamp('approved_at');
            $table->timestamps();

            $table->foreign('curriculum_release_id')->references('id')->on('curriculum_releases')->restrictOnDelete();
            $table->unique(['curriculum_release_id', 'gate']);
        });

        Schema::create('curriculum_release_events', function (Blueprint $table): void {
            $table->id();
            $table->uuid('curriculum_release_id');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 80);
            $table->enum('from_state', CurriculumReleaseState::values())->nullable();
            $table->enum('to_state', CurriculumReleaseState::values());
            $table->text('reason');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('curriculum_release_id')->references('id')->on('curriculum_releases')->restrictOnDelete();
            $table->index(['curriculum_release_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });

        $now = now();
        DB::table('curriculum_packages')->orderBy('id')->each(function (object $package) use ($now): void {
            DB::table('curriculum_releases')->insert([
                'id' => (string) Str::uuid(),
                'curriculum_package_id' => $package->id,
                'state' => CurriculumReleaseState::Draft->value,
                'preview_only' => true,
                'source_tree_sha256' => $package->source_tree_sha256,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('curriculum_release_events');
        Schema::dropIfExists('curriculum_release_approvals');
        Schema::dropIfExists('curriculum_releases');
        Schema::dropIfExists('curriculum_release_locks');
    }
};

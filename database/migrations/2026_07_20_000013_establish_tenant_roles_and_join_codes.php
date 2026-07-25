<?php

use App\Enums\InstitutionJoinRequestStatus;
use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionRole;
use App\Enums\PlatformRole;
use App\Enums\UserCapability;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', PlatformRole::values());
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'role']);
            $table->index(['role', 'revoked_at']);
        });

        Schema::create('institution_role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('institution_membership_id')->constrained('institution_memberships')->cascadeOnDelete();
            $table->enum('role', InstitutionRole::values());
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['institution_membership_id', 'role'], 'institution_membership_role_unique');
            $table->index(['role', 'revoked_at']);
        });

        Schema::create('user_capability_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('capability', UserCapability::values());
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'capability']);
            $table->index(['capability', 'revoked_at']);
        });

        Schema::create('institution_join_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('institution_id');
            $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->char('display_suffix', 4);
            $table->timestamp('expires_at')->index();
            $table->unsignedSmallInteger('use_limit');
            $table->unsignedSmallInteger('use_count')->default(0);
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
            $table->index(['institution_id', 'created_at']);
            $table->index(['institution_id', 'revoked_at', 'expires_at'], 'join_codes_available_index');
        });

        Schema::create('institution_join_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('institution_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('join_code_id')->constrained('institution_join_codes')->restrictOnDelete();
            $table->enum('status', InstitutionJoinRequestStatus::values())->default(InstitutionJoinRequestStatus::Pending->value);
            $table->timestamp('requested_at');
            $table->foreignId('decided_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
            $table->index(['institution_id', 'status', 'requested_at'], 'join_requests_institution_status');
            $table->index(['user_id', 'status', 'requested_at'], 'join_requests_user_status');
        });

        Schema::table('curriculum_activity_progress', function (Blueprint $table): void {
            $table->string('learning_scope_key', 80)->default('personal')->after('user_id');
            $table->foreignId('institution_membership_id')->nullable()->after('learning_scope_key')
                ->constrained('institution_memberships')->restrictOnDelete();
            $table->dropUnique('curriculum_progress_version_unique');
            $table->unique(
                ['user_id', 'learning_scope_key', 'package_name', 'content_version', 'activity_code'],
                'curriculum_progress_scope_unique',
            );
            $table->index(['institution_membership_id', 'content_version', 'completed_at'], 'curriculum_progress_membership');
        });

        Schema::table('curriculum_attempts', function (Blueprint $table): void {
            $table->string('learning_scope_key', 80)->default('personal')->after('user_id');
            $table->foreignId('institution_membership_id')->nullable()->after('learning_scope_key')
                ->constrained('institution_memberships')->restrictOnDelete();
            $table->dropUnique('curriculum_attempt_idempotency_unique');
            $table->unique(
                ['user_id', 'learning_scope_key', 'package_name', 'content_version', 'activity_code', 'idempotency_key'],
                'curriculum_attempt_scope_idempotency',
            );
            $table->index(['institution_membership_id', 'content_version', 'activity_code', 'created_at'], 'curriculum_attempt_membership_history');
        });

        Schema::table('completions', function (Blueprint $table): void {
            $table->string('learning_scope_key', 80)->default('personal')->after('user_id');
            $table->foreignId('institution_membership_id')->nullable()->after('learning_scope_key')
                ->constrained('institution_memberships')->restrictOnDelete();
            $table->dropUnique(['user_id', 'completable_id', 'completable_type']);
            $table->unique(
                ['user_id', 'learning_scope_key', 'completable_id', 'completable_type'],
                'completions_user_scope_completable_unique',
            );
            $table->index(['institution_membership_id', 'created_at'], 'completions_membership_history');
        });

        $this->backfillAssignments();
    }

    public function down(): void
    {
        $this->assertLegacyUniqueKeysCanBeRestored();

        Schema::table('completions', function (Blueprint $table): void {
            $table->dropForeign(['institution_membership_id']);
            $table->dropIndex('completions_membership_history');
            $table->dropUnique('completions_user_scope_completable_unique');
            $table->dropColumn(['learning_scope_key', 'institution_membership_id']);
            $table->unique(['user_id', 'completable_id', 'completable_type']);
        });

        Schema::table('curriculum_attempts', function (Blueprint $table): void {
            $table->dropForeign(['institution_membership_id']);
            $table->dropIndex('curriculum_attempt_membership_history');
            $table->dropUnique('curriculum_attempt_scope_idempotency');
            $table->dropColumn(['learning_scope_key', 'institution_membership_id']);
            $table->unique(
                ['user_id', 'package_name', 'content_version', 'activity_code', 'idempotency_key'],
                'curriculum_attempt_idempotency_unique',
            );
        });

        Schema::table('curriculum_activity_progress', function (Blueprint $table): void {
            $table->dropForeign(['institution_membership_id']);
            $table->dropIndex('curriculum_progress_membership');
            $table->dropUnique('curriculum_progress_scope_unique');
            $table->dropColumn(['learning_scope_key', 'institution_membership_id']);
            $table->unique(
                ['user_id', 'package_name', 'content_version', 'activity_code'],
                'curriculum_progress_version_unique',
            );
        });

        Schema::dropIfExists('institution_join_requests');
        Schema::dropIfExists('institution_join_codes');
        Schema::dropIfExists('user_capability_assignments');
        Schema::dropIfExists('institution_role_assignments');
        Schema::dropIfExists('platform_role_assignments');
    }

    private function backfillAssignments(): void
    {
        $now = now();

        DB::table('users')->where('role', UserRole::Superadmin->value)->orderBy('id')->each(
            static function (object $user) use ($now): void {
                DB::table('platform_role_assignments')->insertOrIgnore([
                    'user_id' => $user->id,
                    'role' => PlatformRole::SystemAdmin->value,
                    'assigned_by_user_id' => null,
                    'assigned_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            },
        );

        DB::table('users')->where('role', UserRole::Admin->value)->orderBy('id')->each(
            static function (object $user) use ($now): void {
                DB::table('user_capability_assignments')->insertOrIgnore([
                    'user_id' => $user->id,
                    'capability' => UserCapability::ContentAuthor->value,
                    'assigned_by_user_id' => null,
                    'assigned_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            },
        );

        DB::table('institution_memberships as memberships')
            ->join('users', 'users.id', '=', 'memberships.user_id')
            ->where('memberships.status', InstitutionMembershipStatus::Active->value)
            ->select(['memberships.id', 'users.role'])
            ->orderBy('memberships.id')
            ->each(static function (object $membership) use ($now): void {
                $role = match ($membership->role) {
                    UserRole::Superadmin->value => InstitutionRole::InstitutionAdmin,
                    UserRole::Supervisor->value, UserRole::Admin->value => InstitutionRole::Instructor,
                    default => InstitutionRole::Learner,
                };
                DB::table('institution_role_assignments')->insertOrIgnore([
                    'institution_membership_id' => $membership->id,
                    'role' => $role->value,
                    'assigned_by_user_id' => null,
                    'assigned_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    private function assertLegacyUniqueKeysCanBeRestored(): void
    {
        $duplicates = [
            'curriculum_activity_progress' => DB::table('curriculum_activity_progress')
                ->select(['user_id', 'package_name', 'content_version', 'activity_code'])
                ->groupBy(['user_id', 'package_name', 'content_version', 'activity_code'])
                ->havingRaw('COUNT(*) > 1')->exists(),
            'curriculum_attempts' => DB::table('curriculum_attempts')
                ->select(['user_id', 'package_name', 'content_version', 'activity_code', 'idempotency_key'])
                ->groupBy(['user_id', 'package_name', 'content_version', 'activity_code', 'idempotency_key'])
                ->havingRaw('COUNT(*) > 1')->exists(),
            'completions' => DB::table('completions')
                ->select(['user_id', 'completable_id', 'completable_type'])
                ->groupBy(['user_id', 'completable_id', 'completable_type'])
                ->havingRaw('COUNT(*) > 1')->exists(),
        ];

        if (in_array(true, $duplicates, true)) {
            throw new RuntimeException('Rollback refused: multiple learning scopes now use the same legacy progress identity. Archive or migrate those rows explicitly; no row was deleted.');
        }
    }
};

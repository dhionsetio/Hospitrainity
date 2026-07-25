<?php

use App\Enums\InstitutionMembershipStatus;
use App\Enums\InstitutionStatus;
use App\Enums\LegacyInstitutionState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_bootstrap_locks', function (Blueprint $table): void {
            $table->string('name', 80)->primary();
            $table->foreignId('completed_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
        DB::table('identity_bootstrap_locks')->insert([
            'name' => 'initial-superadmin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('identity_migration_states', function (Blueprint $table): void {
            $table->string('name', 80)->primary();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedBigInteger('revoked_session_count')->default(0);
            $table->string('completion_method', 80)->nullable();
            $table->timestamps();
        });
        DB::table('identity_migration_states')->insert([
            'name' => 'normalized-institutions-session-revocation-v1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('institutions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 100)->unique();
            $table->string('name_id');
            $table->string('name_en');
            $table->enum('status', InstitutionStatus::values())->default(InstitutionStatus::Pending->value)->index();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_method', 80)->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('legacy_institution_state', LegacyInstitutionState::values())
                ->default(LegacyInstitutionState::Unresolved->value)
                ->index();
        });

        Schema::create('institution_memberships', function (Blueprint $table): void {
            $table->id();
            $table->uuid('institution_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('status', InstitutionMembershipStatus::values())->default(InstitutionMembershipStatus::Active->value)->index();
            $table->boolean('is_default')->default(false);
            $table->string('provenance', 80);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
            $table->unique(['institution_id', 'user_id']);
            $table->index(['user_id', 'status']);
        });

        Schema::create('institution_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('institution_id');
            $table->foreignId('issued_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('target_email_ciphertext');
            $table->char('target_email_hash', 64)->index();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->unsignedSmallInteger('use_limit')->default(1);
            $table->unsignedSmallInteger('use_count')->default(0);
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('institution_id')->references('id')->on('institutions')->restrictOnDelete();
            $table->index(['institution_id', 'created_at']);
            $table->index(['issued_by_user_id', 'created_at']);
        });

        Schema::create('identity_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('institution_id')->nullable();
            $table->uuid('invitation_id')->nullable();
            $table->string('event', 80);
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('institution_id')->references('id')->on('institutions')->nullOnDelete();
            $table->foreign('invitation_id')->references('id')->on('institution_invitations')->nullOnDelete();
            $table->index(['actor_user_id', 'created_at']);
            $table->index(['institution_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });

        $now = now();
        $institutions = [
            'hospitrainity-hq' => [
                'id' => (string) Str::uuid(),
                'name_id' => 'Hospitrainity HQ',
                'name_en' => 'Hospitrainity HQ',
            ],
            'politeknik-negeri-malang' => [
                'id' => (string) Str::uuid(),
                'name_id' => 'Politeknik Negeri Malang',
                'name_en' => 'State Polytechnic of Malang',
            ],
        ];

        foreach ($institutions as $key => $institution) {
            DB::table('institutions')->insert([
                'id' => $institution['id'],
                'key' => $key,
                'name_id' => $institution['name_id'],
                'name_en' => $institution['name_en'],
                'status' => InstitutionStatus::Active->value,
                // Naming an institution is not proof of its owner or domain.
                // Verification is recorded only by a later explicit workflow.
                'verified_at' => null,
                'verification_method' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Reviewed exact-string aliases only. Hotel A/B and every other legacy
        // value intentionally remain unresolved; there is no fuzzy matching.
        $exactMappings = [
            'Hospitrainity HQ' => $institutions['hospitrainity-hq']['id'],
            'Politeknik Negeri Malang' => $institutions['politeknik-negeri-malang']['id'],
            'State Polytechnic of Malang' => $institutions['politeknik-negeri-malang']['id'],
        ];

        DB::table('users')->orderBy('id')->each(function (object $user) use ($exactMappings, $now): void {
            $legacy = (string) $user->instansi;
            $institutionId = $exactMappings[$legacy] ?? null;
            if ($institutionId === null) {
                return;
            }

            DB::table('institution_memberships')->insert([
                'institution_id' => $institutionId,
                'user_id' => $user->id,
                'status' => InstitutionMembershipStatus::Active->value,
                'is_default' => true,
                'provenance' => 'reviewed_exact_legacy_mapping',
                'joined_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('users')->where('id', $user->id)->update([
                'legacy_institution_state' => LegacyInstitutionState::Mapped->value,
            ]);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('identity_audits');
        Schema::dropIfExists('institution_invitations');
        Schema::dropIfExists('institution_memberships');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['legacy_institution_state']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('legacy_institution_state');
        });

        Schema::dropIfExists('institutions');
        Schema::dropIfExists('identity_migration_states');
        Schema::dropIfExists('identity_bootstrap_locks');
    }
};

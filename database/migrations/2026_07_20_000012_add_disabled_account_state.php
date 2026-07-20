<?php

use App\Enums\LegacyInstitutionState;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('disabled_at')->nullable()->index();
            $table->foreignId('disabled_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('disabled_reason_code', 80)->nullable();
        });

        // SQLite rebuilds the table while adding the self-referencing foreign
        // key. Reassert both enum columns so their CHECK constraints survive
        // that rebuild instead of degrading silently to unconstrained VARCHAR.
        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', UserRole::values())
                ->default(UserRole::Learner->value)
                ->change();
            $table->enum('legacy_institution_state', LegacyInstitutionState::values())
                ->default(LegacyInstitutionState::Unresolved->value)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['disabled_by_user_id']);
            $table->dropIndex(['disabled_at']);
            $table->dropColumn(['disabled_at', 'disabled_by_user_id', 'disabled_reason_code']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', UserRole::values())
                ->default(UserRole::Learner->value)
                ->change();
            $table->enum('legacy_institution_state', LegacyInstitutionState::values())
                ->default(LegacyInstitutionState::Unresolved->value)
                ->change();
        });
    }
};

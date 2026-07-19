<?php

use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $unknownRoles = DB::table('users')
            ->select('role', DB::raw('COUNT(*) as aggregate'))
            ->whereNotIn('role', UserRole::values())
            ->groupBy('role')
            ->orderBy('role')
            ->get();

        if ($unknownRoles->isNotEmpty()) {
            $inventory = $unknownRoles
                ->map(static fn (object $row): string => sprintf('%s (%d)', (string) $row->role, (int) $row->aggregate))
                ->implode(', ');

            throw new RuntimeException(
                'User-role constraint migration aborted before schema changes. Resolve unsupported users.role values: '.$inventory.'.',
            );
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->enum('role', UserRole::values())
                ->default(UserRole::Learner->value)
                ->change();
        });

        if (! Schema::hasTable('administration_audits')) {
            Schema::create('administration_audits', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
                $table->foreignId('target_user_id')->constrained('users')->restrictOnDelete();
                $table->string('event', 80);
                $table->enum('old_role', UserRole::values());
                $table->enum('new_role', UserRole::values());
                $table->text('reason');
                $table->string('ip_address', 45)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['actor_user_id', 'created_at']);
                $table->index(['target_user_id', 'created_at']);
                $table->index(['event', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('administration_audits');

        Schema::table('users', function (Blueprint $table): void {
            $table->string('role')->default(UserRole::Learner->value)->change();
        });
    }
};

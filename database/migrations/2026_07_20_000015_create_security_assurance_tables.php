<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('two_factor_secret')->nullable()->after('password');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_secret');
        });

        Schema::create('passkeys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('credential_id')->unique();
            $table->json('credential');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->index('user_id');
        });

        Schema::create('mfa_recovery_codes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('code_digest', 64)->unique();
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'used_at']);
        });

        Schema::create('security_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('account_fingerprint', 64)->nullable();
            $table->char('ip_fingerprint', 64)->nullable();
            $table->string('event', 100);
            $table->string('outcome', 32);
            $table->string('severity', 16);
            $table->text('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->index(['event', 'occurred_at']);
            $table->index(['actor_user_id', 'occurred_at']);
            $table->index(['account_fingerprint', 'occurred_at']);
        });

        Schema::create('upload_security_records', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 32);
            $table->text('original_name');
            $table->char('sha256', 64);
            $table->unsignedBigInteger('bytes');
            $table->string('detected_mime', 160);
            $table->string('scanner_driver', 32);
            $table->string('status', 32);
            $table->string('result_code', 80)->nullable();
            $table->timestamp('promoted_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['sha256', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_security_records');
        Schema::dropIfExists('security_events');
        Schema::dropIfExists('mfa_recovery_codes');
        Schema::dropIfExists('passkeys');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['two_factor_secret', 'two_factor_confirmed_at']);
        });
    }
};

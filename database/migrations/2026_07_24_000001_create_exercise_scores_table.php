<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exercise_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('learning_scope_key', 80)->default('personal');
            $table->foreignId('institution_membership_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('exercise_id')->constrained()->cascadeOnDelete();
            $table->integer('score')->default(0);
            $table->integer('max_score')->default(0);
            $table->json('response_data')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'learning_scope_key', 'exercise_id'], 'user_scope_exercise_score_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exercise_scores');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('exercises', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->onDelete('cascade');

            // Exercise title or general instruction.
            // Examples: "Matching Game", "Pronunciation Drill"
            $table->string('title');

            // Exercise type used to select the appropriate frontend behavior.
            // Examples: 'matching', 'spelling', 'pronunciation'
            $table->string('type');

            // JSON payload containing exercise-type-specific data.
            $table->json('content');

            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exercises');
    }
};

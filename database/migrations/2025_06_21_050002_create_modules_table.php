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
        Schema::create('modules', function (Blueprint $table) {
            $table->id(); // Primary key
            $table->string('title'); // Module title, for example, "Basic Conversation"
            $table->string('slug')->unique(); // URL-friendly title form
            $table->text('description')->nullable(); // Short module description
            $table->enum('level', ['beginner', 'intermediate', 'advanced'])->default('beginner'); // Difficulty level
            $table->integer('order')->default(0); // Module sequence
            $table->boolean('is_published')->default(false); // Publication status
            $table->timestamps(); // created_at and updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};

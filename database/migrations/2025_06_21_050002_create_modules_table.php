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
            $table->id(); // Kunci utama
            $table->string('title'); // Judul modul, cth: "Percakapan Dasar"
            $table->string('slug')->unique(); // Versi judul yang ramah-URL
            $table->text('description')->nullable(); // Deskripsi singkat modul
            $table->enum('level', ['beginner', 'intermediate', 'advanced'])->default('beginner'); // Tingkat kesulitan
            $table->integer('order')->default(0); // Urutan modul
            $table->boolean('is_published')->default(false); // Status publikasi
            $table->timestamps(); // created_at dan updated_at
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

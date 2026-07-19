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

            // Judul atau instruksi umum untuk latihan.
            // Cth: "Matching Game", "Pronunciation Drill"
            $table->string('title');

            // Tipe latihan untuk membedakan logika di frontend.
            // Cth: 'matching', 'spelling', 'pronunciation'
            $table->string('type');

            // Kolom JSON untuk menyimpan data spesifik latihan.
            // Jika versi MySQL Anda lama, ganti json() dengan text().
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

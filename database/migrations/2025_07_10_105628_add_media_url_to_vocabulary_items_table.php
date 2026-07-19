<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vocabulary_items', function (Blueprint $table) {
            // Kolom baru untuk menyimpan path ke file audio/video
            $table->string('media_url')->nullable()->after('details');
        });
    }

    public function down(): void
    {
        Schema::table('vocabulary_items', function (Blueprint $table) {
            $table->dropColumn('media_url');
        });
    }
};

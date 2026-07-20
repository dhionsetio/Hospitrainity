<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('ui_theme', 16)->default('system')->after('two_factor_confirmed_at');
            $table->string('ui_motion', 16)->default('system')->after('ui_theme');
            $table->string('ui_text_scale', 16)->default('default')->after('ui_motion');
            $table->boolean('ui_high_contrast')->default(false)->after('ui_text_scale');
            $table->boolean('ui_no_audio')->default(false)->after('ui_high_contrast');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'ui_theme',
                'ui_motion',
                'ui_text_scale',
                'ui_high_contrast',
                'ui_no_audio',
            ]);
        });
    }
};

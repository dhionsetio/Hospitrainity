<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DEMO_EMAIL = 'user@example.com';

    private const ENGLISH_NAME = 'Hospitrainity Test Learner';

    public function up(): void
    {
        DB::table('users')
            ->where('email', self::DEMO_EMAIL)
            ->update([
                'name' => self::ENGLISH_NAME,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // This is a forward-only demo-data correction. Rolling back schema
        // must not reintroduce a retired non-English canonical display name.
    }
};

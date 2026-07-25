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
        Schema::table('users', function (Blueprint $table) {
            $table->string('first_name')->nullable()->after('name');
            $table->string('middle_name')->nullable()->after('first_name');
            $table->string('last_name')->nullable()->after('middle_name');
            $table->string('gender')->nullable()->after('last_name');
            $table->string('occupation')->nullable()->after('gender');
            $table->string('occupation_other')->nullable()->after('occupation');
            $table->string('prefix')->nullable()->after('occupation_other');
        });

        Schema::table('institution_memberships', function (Blueprint $table) {
            $table->string('student_number')->nullable()->after('provenance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name',
                'middle_name',
                'last_name',
                'gender',
                'occupation',
                'occupation_other',
                'prefix',
            ]);
        });

        Schema::table('institution_memberships', function (Blueprint $table) {
            $table->dropColumn('student_number');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modules', function (Blueprint $table): void {
            $table->index(['is_published', 'order', 'id'], 'modules_published_order_idx');
        });
        Schema::table('lessons', function (Blueprint $table): void {
            $table->index(['module_id', 'order', 'id'], 'lessons_module_order_idx');
        });
        Schema::table('vocabularies', function (Blueprint $table): void {
            $table->index(['lesson_id', 'order', 'id'], 'vocabularies_lesson_order_idx');
        });
        Schema::table('materials', function (Blueprint $table): void {
            $table->index(['lesson_id', 'order', 'id'], 'materials_lesson_order_idx');
        });
        Schema::table('exercises', function (Blueprint $table): void {
            $table->index(['lesson_id', 'order', 'id'], 'exercises_lesson_order_idx');
        });
        Schema::table('vocabulary_items', function (Blueprint $table): void {
            $table->index(['vocabulary_id', 'order', 'id'], 'vocabulary_items_parent_order_idx');
        });
        Schema::table('material_items', function (Blueprint $table): void {
            $table->index(['material_id', 'order', 'id'], 'material_items_parent_order_idx');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->index(['role', 'instansi', 'id'], 'users_role_instansi_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table): void {
            $table->dropIndex('modules_published_order_idx');
        });
        Schema::table('lessons', function (Blueprint $table): void {
            $table->dropIndex('lessons_module_order_idx');
        });
        Schema::table('vocabularies', function (Blueprint $table): void {
            $table->dropIndex('vocabularies_lesson_order_idx');
        });
        Schema::table('materials', function (Blueprint $table): void {
            $table->dropIndex('materials_lesson_order_idx');
        });
        Schema::table('exercises', function (Blueprint $table): void {
            $table->dropIndex('exercises_lesson_order_idx');
        });
        Schema::table('vocabulary_items', function (Blueprint $table): void {
            $table->dropIndex('vocabulary_items_parent_order_idx');
        });
        Schema::table('material_items', function (Blueprint $table): void {
            $table->dropIndex('material_items_parent_order_idx');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_role_instansi_id_idx');
        });
    }
};

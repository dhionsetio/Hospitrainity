<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Module;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * FIP Phase 8 (tests & CI hardening): a smoke test that locks in the routing
 * surface established across FIP Phases 1-7 so future edits cannot silently
 * reintroduce a 500 or resurrect a removed/broken endpoint.
 *
 * It asserts three things:
 *  1. Every admin screen that IS wired renders without a 500 on an empty
 *     database, for an authenticated + verified superadmin.
 *  2. Those same screens are gated: guests are redirected to /login and
 *     non-admin (verified) learners receive HTTP 403 from role:superadmin.
 *  3. The resource verbs deliberately dropped in FIP Phase 1 (create/edit/show
 *     via ->only([...])) and the dead legacy routes (lessons.complete and the
 *     shadowed top-level materials.show) are NOT registered.
 */
class AdminRouteSmokeTest extends TestCase
{
    use RefreshDatabase;

    /** Admin screens that must render (HTTP 200) for a superadmin. */
    private const ADMIN_INDEX_ROUTES = [
        'superadmin.dashboard',
        'superadmin.modules.index',
        'superadmin.lessons.index',
        'superadmin.vocabularies.index',
        'superadmin.materials.index',
        'superadmin.exercises.index',
        'superadmin.curriculum-drafts.index',
        'superadmin.curriculum-exercises.index',
        'superadmin.legacy-evidence.index',
        'superadmin.progress.index',
    ];

    /** Resource verbs intentionally removed in FIP Phase 1 (->only([...])). */
    private const REMOVED_RESOURCE_VERBS = [
        'superadmin.modules.create',       'superadmin.modules.edit',       'superadmin.modules.show',
        'superadmin.lessons.create',       'superadmin.lessons.edit',       'superadmin.lessons.show',
        'superadmin.vocabularies.create',  'superadmin.vocabularies.edit',  'superadmin.vocabularies.show',
        'superadmin.materials.create',     'superadmin.materials.edit',     'superadmin.materials.show',
        'superadmin.exercises.create',     'superadmin.exercises.edit',     'superadmin.exercises.show',
    ];

    /** Dead/broken legacy routes removed earlier in the fix plan. */
    private const DEAD_ROUTES = [
        'lessons.complete', // legacy Progress model + LessonController@markAsComplete (removed)
        'materials.show',   // top-level GET /lessons/{material} shadowed by lessons.show (removed)
    ];

    private function superadmin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    public function test_admin_index_screens_render_for_superadmin(): void
    {
        $admin = $this->superadmin();

        foreach (self::ADMIN_INDEX_ROUTES as $name) {
            $response = $this->actingAs($admin)->get(route($name));

            $response->assertOk(); // no 500 and no redirect away from the screen
        }
    }

    public function test_paginated_admin_indexes_render_with_populated_second_pages(): void
    {
        $admin = $this->superadmin();
        $module = Module::factory()->create();
        $lesson = Lesson::factory()->for($module)->create();

        Module::factory()->count(10)->create();
        Lesson::factory()->count(10)->for($module)->create();
        Vocabulary::factory()->count(11)->for($lesson)->create();
        Material::factory()->count(11)->for($lesson)->create();
        Exercise::factory()->count(11)->for($lesson)->create();

        $screens = [
            'superadmin.modules.index' => __('admin.module_management'),
            'superadmin.lessons.index' => __('admin.lesson_management'),
            'superadmin.vocabularies.index' => __('admin.vocabulary_management'),
            'superadmin.materials.index' => __('admin.material_management'),
            'superadmin.exercises.index' => __('admin.exercise_management'),
        ];

        foreach ($screens as $routeName => $heading) {
            $response = $this->actingAs($admin)->get(route($routeName));

            $response
                ->assertOk()
                ->assertSee($heading)
                ->assertSee('page=2', false);
        }
    }

    public function test_admin_screens_reject_a_verified_learner_with_403(): void
    {
        $learner = User::factory()->create(['role' => 'user']); // verified by default

        foreach (self::ADMIN_INDEX_ROUTES as $name) {
            $this->actingAs($learner)
                ->get(route($name))
                ->assertForbidden(); // abort(403) from App\Http\Middleware\CheckRole
        }
    }

    public function test_admin_screens_require_authentication(): void
    {
        foreach (self::ADMIN_INDEX_ROUTES as $name) {
            $this->get(route($name))->assertRedirect('/login');
        }
    }

    public function test_removed_resource_verbs_are_not_registered(): void
    {
        foreach (self::REMOVED_RESOURCE_VERBS as $name) {
            $this->assertFalse(
                Route::has($name),
                "Route [{$name}] should stay removed by the resource ->only([...]) whitelist; "
                .'its controller method/view was never implemented and would 500.'
            );
        }
    }

    public function test_dead_legacy_routes_are_not_registered(): void
    {
        foreach (self::DEAD_ROUTES as $name) {
            $this->assertFalse(
                Route::has($name),
                "Dead legacy route [{$name}] must not be reintroduced."
            );
        }
    }
}

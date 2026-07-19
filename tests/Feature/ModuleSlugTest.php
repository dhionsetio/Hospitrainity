<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Module slugs must stay unique even when two distinct (title-unique)
 * titles collapse to the same Str::slug() value.
 */
class ModuleSlugTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'superadmin']);
    }

    public function test_colliding_titles_get_distinct_slugs(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('superadmin.modules.store'), [
            'title' => 'Hello World',
            'description' => 'First',
            'level' => 'beginner',
        ])->assertSessionHasNoErrors();

        // Different title (passes unique:modules,title) but same slug base.
        $this->actingAs($admin)->post(route('superadmin.modules.store'), [
            'title' => 'Hello World!',
            'description' => 'Second',
            'level' => 'beginner',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('modules', ['title' => 'Hello World', 'slug' => 'hello-world']);
        $this->assertDatabaseHas('modules', ['title' => 'Hello World!', 'slug' => 'hello-world-2']);
    }

    public function test_updating_a_module_keeps_its_own_slug_stable(): void
    {
        $admin = $this->admin();
        $module = Module::factory()->create(['title' => 'Alpha', 'slug' => 'alpha']);

        $this->actingAs($admin)->put(route('superadmin.modules.update', $module), [
            'title' => 'Alpha',
            'description' => 'Updated description',
            'level' => 'intermediate',
        ])->assertSessionHasNoErrors();

        // The module's own row is ignored, so no -2 suffix is appended.
        $this->assertDatabaseHas('modules', ['id' => $module->id, 'slug' => 'alpha']);
    }
}

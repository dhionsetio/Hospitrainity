<?php

namespace Tests\Feature;

use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalized_slug_collisions_receive_stable_suffixes(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $module = Module::factory()->create();

        foreach ([['Cafe', 'Cafe'], ['Cafe punctuation', 'Cafe!']] as [$title, $slug]) {
            $this->actingAs($admin)->post(route('superadmin.lessons.store'), [
                'title' => $title,
                'slug' => $slug,
                'module_id' => $module->id,
            ])->assertRedirect(route('superadmin.lessons.index'));
        }

        $this->assertSame(['cafe', 'cafe-2'], Lesson::orderBy('id')->pluck('slug')->all());
    }

    public function test_updating_a_lesson_keeps_its_own_normalized_slug_stable(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        $lesson = Lesson::factory()->create(['slug' => 'front-office']);

        $this->actingAs($admin)->put(route('superadmin.lessons.update', $lesson), [
            'title' => 'Front Office Updated',
            'slug' => ' Front Office ',
            'module_id' => $lesson->module_id,
        ])->assertRedirect(route('superadmin.lessons.index'));

        $this->assertSame('front-office', $lesson->fresh()->slug);
    }
}

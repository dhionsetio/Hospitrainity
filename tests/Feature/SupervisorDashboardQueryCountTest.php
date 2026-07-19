<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SupervisorDashboardQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_queries_are_bounded_and_learners_are_paginated(): void
    {
        $institution = 'Measured Hotel';
        $supervisor = User::factory()->create([
            'role' => 'supervisor',
            'instansi' => $institution,
        ]);

        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->create(['module_id' => $module->id]);
        $exercise = Exercise::factory()->create(['lesson_id' => $lesson->id]);

        $firstLearner = User::factory()->create(['instansi' => $institution]);
        $firstLearner->completions()->create([
            'completable_type' => Exercise::class,
            'completable_id' => $exercise->id,
        ]);

        $small = $this->countQueries($supervisor);

        User::factory()->count(99)->create(['instansi' => $institution]);
        [$large, $response] = $this->countQueries($supervisor, returnResponse: true);

        $this->assertSame($small, $large, "Supervisor query count grew from {$small} to {$large}.");
        $this->assertLessThanOrEqual(15, $large);

        $users = $response->viewData('users');
        $this->assertInstanceOf(LengthAwarePaginator::class, $users);
        $this->assertSame(100, $users->total());
        $this->assertCount(20, $users->items());
        $this->assertSame(100, $users->getCollection()->firstWhere('id', $firstLearner->id)->overall_progress);
    }

    private function countQueries(User $supervisor, bool $returnResponse = false): int|array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($supervisor)->get(route('supervisor.dashboard'));
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();
        $response->assertOk();

        return $returnResponse ? [$count, $response] : $count;
    }
}

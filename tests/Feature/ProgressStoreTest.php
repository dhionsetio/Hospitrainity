<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the Phase 1 progress-store hardening. The controller only accepts a
 * completable type from a fixed allowlist (VocabularyItem, MaterialItem,
 * Exercise) instead of interpolating "App\\Models\\{type}" from raw input.
 */
class ProgressStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_store_progress(): void
    {
        $response = $this->post(route('progress.store'), [
            'type' => 'Exercise',
            'items' => [1],
        ]);

        $response->assertRedirect('/login');
    }

    public function test_invalid_completable_type_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('progress.store'), [
            'type' => 'User', // not in the allowlist
            'items' => [1],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('type');
        $this->assertDatabaseCount('completions', 0);
    }

    public function test_valid_progress_is_recorded(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->postJson(route('progress.store'), [
            'type' => 'Exercise',
            'items' => [$exercise->id],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('completions', [
            'user_id' => $user->id,
            'completable_id' => $exercise->id,
            'completable_type' => Exercise::class,
        ]);
    }

    public function test_storing_the_same_item_twice_is_idempotent(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();

        $payload = ['type' => 'Exercise', 'items' => [$exercise->id]];

        $this->actingAs($user)->postJson(route('progress.store'), $payload)->assertStatus(200);
        $this->actingAs($user)->postJson(route('progress.store'), $payload)->assertStatus(200);

        $this->assertDatabaseCount('completions', 1);
    }
}

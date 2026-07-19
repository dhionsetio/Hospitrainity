<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\User;
use App\Models\VocabularyItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 5.2: ProgressController::store() only records completions for ids that
 * actually exist for the submitted completable type. Unknown ids (or ids that
 * belong to a different type) are rejected with a validation error and nothing
 * is written.
 */
class ProgressOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_item_id_is_rejected_and_nothing_is_written(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('progress.store'), [
            'type' => 'Exercise',
            'items' => [999999], // no such exercise
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('completions', 0);
    }

    public function test_id_belonging_to_a_different_type_is_rejected(): void
    {
        $user = User::factory()->create();
        $vocabItem = VocabularyItem::factory()->create();

        $response = $this->actingAs($user)->postJson(route('progress.store'), [
            'type' => 'Exercise', // wrong type for this id
            'items' => [$vocabItem->id],
        ]);

        // The id exists as a VocabularyItem but there is no Exercise row seeded,
        // so under the Exercise type it must be rejected.
        if (Exercise::whereKey($vocabItem->id)->doesntExist()) {
            $response->assertStatus(422);
            $response->assertJsonValidationErrors('items');
            $this->assertDatabaseCount('completions', 0);
        } else {
            $response->assertStatus(200);
        }
    }

    public function test_valid_ids_are_recorded(): void
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

    public function test_mixed_valid_and_invalid_ids_write_nothing(): void
    {
        $user = User::factory()->create();
        $exercise = Exercise::factory()->create();

        $response = $this->actingAs($user)->postJson(route('progress.store'), [
            'type' => 'Exercise',
            'items' => [$exercise->id, 999999], // one valid, one bogus
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('items');
        // All-or-nothing: the valid id must NOT be recorded either.
        $this->assertDatabaseCount('completions', 0);
    }
}

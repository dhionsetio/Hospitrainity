<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DisplayPreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_follow_the_operating_system_and_render_all_preference_contracts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('preferences.edit'))
            ->assertOk()
            ->assertSee('data-theme="system"', escape: false)
            ->assertSee('data-motion="system"', escape: false)
            ->assertSee('data-text-scale="default"', escape: false)
            ->assertSee('data-contrast="default"', escape: false)
            ->assertSee('data-audio="on"', escape: false)
            ->assertSeeText('Show my learning streak')
            ->assertSee('Browser zoom');
    }

    public function test_authenticated_user_can_persist_every_approved_display_preference(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('preferences.update'), [
            'ui_theme' => 'dark',
            'ui_motion' => 'reduce',
            'ui_text_scale' => 'larger',
            'ui_high_contrast' => '1',
            'ui_no_audio' => '1',
            'learning_streak_enabled' => '0',
        ])->assertRedirect(route('preferences.edit'));

        $user->refresh();
        $this->assertSame('dark', $user->ui_theme);
        $this->assertSame('reduce', $user->ui_motion);
        $this->assertSame('larger', $user->ui_text_scale);
        $this->assertTrue($user->ui_high_contrast);
        $this->assertTrue($user->ui_no_audio);
        $this->assertFalse($user->learning_streak_enabled);

        $this->get(route('preferences.edit'))
            ->assertSee('data-theme="dark"', escape: false)
            ->assertSee('data-motion="reduce"', escape: false)
            ->assertSee('data-text-scale="larger"', escape: false)
            ->assertSee('data-contrast="stronger"', escape: false)
            ->assertSee('data-audio="off"', escape: false);
    }

    public function test_invalid_values_fail_closed_and_guest_cannot_change_preferences(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->from(route('preferences.edit'))->patch(route('preferences.update'), [
            'ui_theme' => 'unverified-theme',
            'ui_motion' => 'unverified-motion',
            'ui_text_scale' => 'unbounded',
        ])->assertRedirect(route('preferences.edit'))
            ->assertSessionHasErrors(['ui_theme', 'ui_motion', 'ui_text_scale']);

        $this->post(route('logout'));
        $this->patch(route('preferences.update'), [
            'ui_theme' => 'dark',
            'ui_motion' => 'reduce',
            'ui_text_scale' => 'larger',
        ])->assertRedirect(route('login'));
    }

    public function test_display_preference_migration_rolls_back_without_removing_the_user_and_reapplies(): void
    {
        $user = User::factory()->create();
        $migration = require database_path('migrations/2026_07_20_000016_add_display_preferences_to_users.php');

        $migration->down();
        $this->assertFalse(Schema::hasColumn('users', 'ui_theme'));
        $this->assertDatabaseHas('users', ['id' => $user->getKey(), 'email' => $user->email]);

        $migration->up();
        $this->assertTrue(Schema::hasColumn('users', 'ui_theme'));
        $this->assertTrue(Schema::hasColumn('users', 'ui_no_audio'));
    }
}

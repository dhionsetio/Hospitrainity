<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the Phase 5 session-driven locale switch (`locale.switch` route +
 * SetLocale middleware). Only the shipped locales (en, id) may be stored.
 */
class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_supported_locale_is_stored_in_session(): void
    {
        $response = $this->from('/')->get('/locale/id');

        $response->assertRedirect('/');
        $response->assertSessionHas('locale', 'id');
    }

    public function test_unsupported_locale_is_ignored(): void
    {
        $response = $this->from('/')->get('/locale/fr');

        $response->assertRedirect('/');
        $response->assertSessionMissing('locale');
    }

    public function test_language_switchers_use_stable_vector_flags_and_expose_the_current_locale(): void
    {
        $learner = User::factory()->create(['role' => 'user']);

        $this->actingAs($learner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-language-switcher', escape: false)
            ->assertSee('data-current-locale="en"', escape: false)
            ->assertSee('data-flag="id"', escape: false)
            ->assertSee('data-flag="gb"', escape: false)
            ->assertSeeText('Bahasa Indonesia')
            ->assertSeeText('English')
            ->assertDontSee('🇮🇩', escape: false)
            ->assertDontSee('🇬🇧', escape: false);

        $this->withSession(['locale' => 'id'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-current-locale="id"', escape: false)
            ->assertSeeText('Bahasa Inggris');
    }
}

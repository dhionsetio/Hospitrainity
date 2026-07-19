<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Covers the Phase 5 session-driven locale switch (`locale.switch` route +
 * SetLocale middleware). Only the shipped locales (en, id) may be stored.
 */
class LocaleSwitchTest extends TestCase
{
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
}

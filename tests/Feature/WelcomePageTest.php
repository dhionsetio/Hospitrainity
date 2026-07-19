<?php

namespace Tests\Feature;

use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    public function test_welcome_page_renders_successfully(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    public function test_welcome_page_shows_the_hospitrainity_brand(): void
    {
        $response = $this->get('/');

        $response->assertSee('Hospitrainity', false);
    }
}

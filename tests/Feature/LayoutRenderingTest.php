<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LayoutRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_layout_renders_locale_csrf_token_and_valid_comments(): void
    {
        $bufferLevel = ob_get_level();
        app()->setLocale('id');
        $response = $this
            ->withSession(['_token' => 'guest-layout-token'])
            ->get(route('login'));

        $this->assertSame($bufferLevel, ob_get_level(), 'The guest request leaked an output buffer.');
        $this->assertValidLayout($response, 'id', 'guest-layout-token');
    }

    public function test_app_layout_renders_locale_csrf_token_and_valid_comments(): void
    {
        $bufferLevel = ob_get_level();
        app()->setLocale('en');
        $user = User::factory()->create();
        $response = $this
            ->withSession(['_token' => 'app-layout-token'])
            ->actingAs($user)
            ->get(route('dashboard'));

        $this->assertSame($bufferLevel, ob_get_level(), 'The app request leaked an output buffer.');
        $this->assertValidLayout($response, 'en', 'app-layout-token');
    }

    private function assertValidLayout(TestResponse $response, string $locale, string $token): void
    {
        $response
            ->assertOk()
            ->assertSee('<html lang="'.$locale.'"', false)
            ->assertSee('<meta name="csrf-token" content="'.$token.'">', false)
            ->assertSee('<!-- Compiled, self-hosted assets: Tailwind, Font Awesome, Alpine CSP, and app JS. -->', false)
            ->assertDontSee('cdn.jsdelivr.net', false)
            ->assertDontSee('cdnjs.cloudflare.com', false)
            ->assertDontSee('fonts.googleapis.com', false)
            ->assertDontSee('<html lang=" str_replace', false)
            ->assertDontSee('<meta name="csrf-token" content=" csrf_token()', false);
    }
}

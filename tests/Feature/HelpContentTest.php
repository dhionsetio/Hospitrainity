<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\HelpContentRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HelpContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_help_about_and_glossary_use_plain_task_focused_copy(): void
    {
        $this->get(route('help.index'))
            ->assertOk()
            ->assertSee('Getting started')
            ->assertSee('hsp-card-link', escape: false)
            ->assertSee('aria-label="Return to Hospitrainity"', escape: false)
            ->assertDontSee('Help version')
            ->assertDontSee('thesis prototype')
            ->assertDontSee('dhionsetio@gmail.com');

        $this->get(route('help.show', 'invitations-and-codes'))
            ->assertOk()
            ->assertSee('up to 30 days')
            ->assertSee('separate progress record')
            ->assertDontSee('dhionsetio@gmail.com');

        $this->get(route('glossary.index'))
            ->assertOk()
            ->assertSee('Confidence check')
            ->assertSee('not a proficiency diagnostic');

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('practise English used in hospitality situations')
            ->assertSee('not a grade or proof of mastery')
            ->assertDontSee('independent WCAG conformance');

        $learner = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
        $this->actingAs($learner)
            ->get(route('help.index'))
            ->assertOk()
            ->assertSee('aria-label="Return to dashboard"', escape: false)
            ->assertSee('href="'.route('dashboard').'"', escape: false);

        $this->get(route('help.show', 'getting-started'))
            ->assertOk()
            ->assertSee('aria-label="Return to Help"', escape: false);
    }

    public function test_help_is_bilingual_searchable_and_has_reproducible_content_evidence(): void
    {
        $registry = app(HelpContentRegistry::class);
        $english = $registry->topic('account-and-recovery', 'en');
        $indonesian = $registry->topic('account-and-recovery', 'id');

        $this->assertNotNull($english);
        $this->assertNotNull($indonesian);
        $this->assertSame(config('help.version'), $english['version']);
        $this->assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $english['content_sha256']);
        $this->assertNotSame($english['title'], $indonesian['title']);
        $this->assertNotSame($english['content_sha256'], $indonesian['content_sha256']);

        $this->withSession(['locale' => 'id'])
            ->get(route('help.index', ['q' => 'pemulihan']))
            ->assertOk()
            ->assertSee('Akses dan pemulihan akun')
            ->assertDontSee('dhionsetio@gmail.com');

        $this->get(route('help.show', 'not-a-real-topic'))->assertNotFound();
        $this->get(route('help.index', ['q' => str_repeat('a', 81)]))->assertSessionHasErrors('q');
    }
}

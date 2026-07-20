<?php

namespace Tests\Feature;

use App\Services\HelpContentRegistry;
use Tests\TestCase;

class HelpContentTest extends TestCase
{
    public function test_versioned_public_help_about_and_glossary_are_available_without_a_support_claim(): void
    {
        $this->get(route('help.index'))
            ->assertOk()
            ->assertSee('Getting started')
            ->assertSee('Help version '.config('help.version'))
            ->assertDontSee('dhionsetio@gmail.com');

        $this->get(route('help.show', 'invitations-and-codes'))
            ->assertOk()
            ->assertSee('up to 30 days')
            ->assertSee('separate no-progress view')
            ->assertDontSee('dhionsetio@gmail.com');

        $this->get(route('glossary.index'))
            ->assertOk()
            ->assertSee('Confidence check')
            ->assertSee('not a proficiency diagnostic');

        $this->get(route('about'))
            ->assertOk()
            ->assertSee('thesis prototype')
            ->assertSee('not proof of proficiency or mastery')
            ->assertSee('does not claim guaranteed fluency');
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

<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearnerShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_learner_gets_the_shared_shell_on_application_and_help_pages(): void
    {
        $learner = User::factory()->create([
            'role' => UserRole::Learner,
            'email_verified_at' => now(),
        ]);

        foreach ([route('dashboard'), route('help.index'), route('curriculum.confidence-history')] as $url) {
            $response = $this->actingAs($learner)->get($url)->assertOk();
            $response
                ->assertSee('data-shell="learner"', false)
                ->assertSee('aria-label="Learner navigation"', false)
                ->assertSee('aria-label="Primary learner navigation"', false)
                ->assertSee('aria-label="Mobile learner navigation"', false)
                ->assertSee('aria-label="Open learning context"', false)
                ->assertSee('aria-label="Open account"', false)
                ->assertSee('href="'.route('dashboard').'"', false)
                ->assertSee('href="'.route('curriculum.confidence-history').'"', false)
                ->assertSee('href="'.route('help.index').'"', false)
                ->assertDontSee('href="'.route('work-context.index').'"', false)
                ->assertDontSee('role="menu"', false)
                ->assertDontSee('role="menuitem"', false);

            $this->assertSame(2, substr_count($response->getContent(), 'aria-current="page"'));
        }
    }

    public function test_secondary_learner_page_does_not_falsely_mark_a_primary_destination_current(): void
    {
        $learner = User::factory()->create([
            'role' => UserRole::Learner,
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($learner)
            ->get(route('institution-enrollment.index'))
            ->assertOk()
            ->assertSee('data-shell="learner"', false);

        $this->assertSame(0, substr_count($response->getContent(), 'aria-current="page"'));
    }

    public function test_public_and_unverified_help_do_not_expose_authenticated_learner_navigation(): void
    {
        $this->get(route('help.index'))
            ->assertOk()
            ->assertSee('data-shell="default"', false)
            ->assertDontSee('aria-label="Learner navigation"', false);

        $unverified = User::factory()->unverified()->create(['role' => UserRole::Learner]);
        $this->actingAs($unverified)
            ->get(route('help.index'))
            ->assertOk()
            ->assertSee('data-shell="default"', false)
            ->assertDontSee('aria-label="Learner navigation"', false);
    }
}

<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\ShellNavigation;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

use Tests\TestCase;

class ShellNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_learner_shell_navigation(): void
    {
        $user = User::factory()->create(['role' => UserRole::Learner]);
        $navigation = app(ShellNavigation::class)->forUser(Request::create('/dashboard'), $user);

        $this->assertSame('Learner', $navigation['roleLabel']);
        $this->assertCount(4, $navigation['railDestinations']);

        $labels = array_column($navigation['railDestinations'], 'label');
        $this->assertSame(['Home', 'Learn', 'Progress', 'Help'], $labels);

        $urls = array_column($navigation['railDestinations'], 'url');
        $this->assertStringContainsString('/dashboard', $urls[0]);
        $this->assertStringContainsString('/dashboard#learning-modules', $urls[1]);
        $this->assertStringContainsString('/curriculum/confidence-history', $urls[2]);
        $this->assertStringContainsString('/help', $urls[3]);
    }

    public function test_supervisor_shell_navigation(): void
    {
        $user = User::factory()->create(['role' => UserRole::Supervisor]);
        $navigation = app(ShellNavigation::class)->forUser(Request::create('/supervisor/dashboard'), $user);

        $this->assertSame('Supervisor', $navigation['roleLabel']);
        $this->assertCount(5, $navigation['railDestinations']);

        $labels = array_column($navigation['railDestinations'], 'label');
        $this->assertSame(['Dashboard', 'Classes', 'Invitations', 'Classroom codes', 'Help'], $labels);

        $urls = array_column($navigation['railDestinations'], 'url');
        $this->assertStringContainsString('/supervisor/dashboard', $urls[0]);
        $this->assertStringContainsString('/supervisor/classes', $urls[1]);
        $this->assertStringContainsString('/supervisor/invitations', $urls[2]);
        $this->assertStringContainsString('/supervisor/classroom-codes', $urls[3]);
        $this->assertStringContainsString('/help', $urls[4]);
    }

    public function test_admin_shell_navigation(): void
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);
        $navigation = app(ShellNavigation::class)->forUser(Request::create('/admin/dashboard'), $user);

        $this->assertSame('Admin', $navigation['roleLabel']);
        $this->assertCount(5, $navigation['railDestinations']);

        $labels = array_column($navigation['railDestinations'], 'label');
        $this->assertSame(['Dashboard', 'Progress', 'Content', 'Exercises', 'Help'], $labels);

        $urls = array_column($navigation['railDestinations'], 'url');
        $this->assertStringContainsString('/admin/dashboard', $urls[0]);
        $this->assertStringContainsString('/admin/progress', $urls[1]);
        $this->assertStringContainsString('/admin/curriculum-drafts', $urls[2]);
        $this->assertStringContainsString('/admin/curriculum-exercises', $urls[3]);
        $this->assertStringContainsString('/help', $urls[4]);
    }

    public function test_superadmin_shell_navigation(): void
    {
        $user = User::factory()->create(['role' => UserRole::Superadmin]);
        $navigation = app(ShellNavigation::class)->forUser(Request::create('/superadmin/dashboard'), $user);

        $this->assertSame('Superadmin', $navigation['roleLabel']);
        $this->assertCount(11, $navigation['railDestinations']);

        $labels = array_column($navigation['railDestinations'], 'label');
        $this->assertContains('Institutions', $labels);
        $this->assertContains('Audit logs', $labels);
        $this->assertContains('Users', $labels);

        $urls = array_column($navigation['railDestinations'], 'url');
        foreach ($urls as $url) {
            $this->assertNotEmpty($url);
            $this->assertStringStartsWith('http', $url);
        }
    }
}

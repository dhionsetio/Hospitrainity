<?php

namespace Tests\Feature;

use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Module;
use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleRouteMatrixTest extends TestCase
{
    use RefreshDatabase;

    private const SUPERADMIN_ROUTES = [
        'superadmin.dashboard',
        'superadmin.modules.index',
        'superadmin.lessons.index',
        'superadmin.vocabularies.index',
        'superadmin.materials.index',
        'superadmin.exercises.index',
        'superadmin.curriculum-drafts.index',
        'superadmin.curriculum-exercises.index',
        'superadmin.legacy-evidence.index',
        'superadmin.progress.index',
    ];

    private const CONTENT_ADMIN_ROUTES = [
        'admin.dashboard',
        'admin.modules.index',
        'admin.lessons.index',
        'admin.vocabularies.index',
        'admin.materials.index',
        'admin.exercises.index',
        'admin.curriculum-drafts.index',
        'admin.curriculum-exercises.index',
        'admin.legacy-evidence.index',
        'admin.progress.index',
    ];

    public function test_learner_can_only_use_learner_routes(): void
    {
        $user = User::factory()->create(['role' => 'user']);
        $fixture = $this->learnerFixture();

        $this->assertLearnerRouteStatus($user, $fixture, 200);
        $this->actingAs($user)->get(route('supervisor.dashboard'))->assertForbidden();
        $this->assertAdministrationRouteStatus($user, self::CONTENT_ADMIN_ROUTES, 403);
        $this->assertAdministrationRouteStatus($user, self::SUPERADMIN_ROUTES, 403);
    }

    public function test_supervisor_can_only_use_supervisor_routes(): void
    {
        $supervisor = User::factory()->create(['role' => 'supervisor']);
        $fixture = $this->learnerFixture();

        $this->actingAs($supervisor)->get(route('supervisor.dashboard'))->assertOk();
        $this->assertLearnerRouteStatus($supervisor, $fixture, 403);
        $this->assertAdministrationRouteStatus($supervisor, self::CONTENT_ADMIN_ROUTES, 403);
        $this->assertAdministrationRouteStatus($supervisor, self::SUPERADMIN_ROUTES, 403);
    }

    public function test_content_admin_can_only_use_approved_admin_routes(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $fixture = $this->learnerFixture();

        $this->assertAdministrationRouteStatus($admin, self::CONTENT_ADMIN_ROUTES, 200);
        $this->assertAdministrationRouteStatus($admin, self::SUPERADMIN_ROUTES, 403);
        $this->actingAs($admin)->get(route('superadmin.users.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('supervisor.dashboard'))->assertForbidden();
        $this->assertLearnerRouteStatus($admin, $fixture, 403);

        $this->actingAs($admin)
            ->post(route('superadmin.modules.store'), ['title' => 'Denied'])
            ->assertForbidden();
    }

    public function test_superadmin_can_only_use_superadmin_routes(): void
    {
        $superadmin = User::factory()->create(['role' => 'superadmin']);
        $fixture = $this->learnerFixture();

        $this->assertAdministrationRouteStatus($superadmin, self::SUPERADMIN_ROUTES, 200);
        $this->assertAdministrationRouteStatus($superadmin, self::CONTENT_ADMIN_ROUTES, 403);
        $this->actingAs($superadmin)->get(route('supervisor.dashboard'))->assertForbidden();
        $this->assertLearnerRouteStatus($superadmin, $fixture, 403);
    }

    public function test_unverified_accounts_are_stopped_before_role_routes(): void
    {
        $user = User::factory()->unverified()->create(['role' => 'user']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    /** @return array{Module, Lesson, Vocabulary, Material, Exercise} */
    private function learnerFixture(): array
    {
        $module = Module::factory()->create(['is_published' => true]);
        $lesson = Lesson::factory()->for($module)->create();

        return [
            $module,
            $lesson,
            Vocabulary::factory()->for($lesson)->create(),
            Material::factory()->for($lesson)->create(),
            Exercise::factory()->for($lesson)->create(),
        ];
    }

    /** @param array{Module, Lesson, Vocabulary, Material, Exercise} $fixture */
    private function assertLearnerRouteStatus(User $user, array $fixture, int $status): void
    {
        [$module, $lesson, $vocabulary, $material, $exercise] = $fixture;
        $urls = [
            route('dashboard'),
            route('modules.show', $module),
            route('lessons.show', $lesson),
            route('lessons.practice', [$lesson, $vocabulary]),
            route('lessons.material.show', [$lesson, $material]),
            route('lessons.exercise.practice', $lesson),
        ];

        foreach ($urls as $url) {
            $this->actingAs($user)->get($url)->assertStatus($status);
        }

        $this->actingAs($user)->postJson(route('progress.store'), [
            'type' => 'Exercise',
            'items' => [$exercise->id],
        ])->assertStatus($status);
    }

    /** @param list<string> $routeNames */
    private function assertAdministrationRouteStatus(User $user, array $routeNames, int $status): void
    {
        foreach ($routeNames as $routeName) {
            $this->actingAs($user)->get(route($routeName))->assertStatus($status);
        }
    }
}

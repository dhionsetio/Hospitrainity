<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CurriculumActivityProgress;
use App\Models\CurriculumAttempt;
use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\CurriculumResponse;
use App\Models\User;
use App\Services\CurriculumProgressService;
use App\Services\ProgressAdministrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProgressAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_progress_routes_enforce_identity_aggregate_and_institution_boundaries(): void
    {
        $this->canonicalPackage();
        $learner = $this->learner('Scoped Learner', 'Hotel A');
        $otherLearner = $this->learner('Other Learner', 'Hotel B');
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor, 'instansi' => 'Hotel A']);
        $blankSupervisor = User::factory()->create(['role' => UserRole::Supervisor, 'instansi' => '']);
        $unverified = User::factory()->unverified()->create(['role' => UserRole::Superadmin]);

        $this->get(route('superadmin.progress.index'))->assertRedirect(route('login'));
        $this->actingAs($unverified)->get(route('superadmin.progress.index'))->assertRedirect(route('verification.notice'));
        $this->actingAs($admin)->get(route('superadmin.progress.index'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('superadmin.progress.index'))->assertForbidden();
        $this->actingAs($superadmin)->get(route('superadmin.progress.index'))->assertOk();

        $this->actingAs($admin)->get(route('admin.progress.index'))
            ->assertOk()
            ->assertSee('Aggregate progress')
            ->assertDontSee($learner->email);
        $this->actingAs($superadmin)->get(route('admin.progress.index'))->assertForbidden();

        $this->actingAs($supervisor)
            ->get(route('supervisor.progress.learners.show', $learner))
            ->assertOk();
        $this->actingAs($supervisor)
            ->get(route('supervisor.progress.learners.show', $otherLearner))
            ->assertNotFound();
        $this->actingAs($blankSupervisor)
            ->get(route('supervisor.progress.learners.show', $learner))
            ->assertNotFound();
        $this->actingAs($superadmin)
            ->get(route('superadmin.progress.learners.show', $otherLearner))
            ->assertOk();
        $this->actingAs($superadmin)
            ->get(route('superadmin.progress.learners.show', $admin))
            ->assertNotFound();
    }

    public function test_identity_filters_use_exact_highest_state_and_metadata_only_queries(): void
    {
        [$package, $firstActivity, $secondActivity] = $this->canonicalPackage();
        $viewed = $this->learner('Viewed Learner', 'Hotel A');
        $completed = $this->learner('Completed Learner', 'Hotel B');
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);

        $this->progress($viewed, $package, $firstActivity, ['viewed_at' => now()]);
        $completedProgress = $this->progress($completed, $package, $firstActivity, [
            'viewed_at' => now()->subHour(),
            'started_at' => now()->subMinutes(50),
            'attempted_at' => now()->subMinutes(40),
            'completed_at' => now()->subMinutes(30),
            'legacy_status' => 'legacy_reveal_only',
        ]);
        $attempt = $this->attempt($completed, $package, $firstActivity);
        CurriculumResponse::query()->create([
            'curriculum_attempt_id' => $attempt->id,
            'prompt_code' => 'PRIVATE-PROMPT',
            'response_form' => 'open_text',
            'scoring_mode' => 'self_check',
            'response' => ['text' => 'PRIVATE-OPEN-RESPONSE-DO-NOT-RENDER'],
            'response_present' => true,
            'is_correct' => null,
            'self_checked' => true,
            'checked_at' => now(),
        ]);

        $this->actingAs($superadmin)
            ->get(route('superadmin.progress.index', [
                'version' => $package->content_version,
                'module' => 'CH-01',
                'status' => 'viewed',
                'recent' => '7',
            ]))
            ->assertOk()
            ->assertSee($viewed->email)
            ->assertDontSee($completed->email)
            ->assertDontSee('PRIVATE-OPEN-RESPONSE-DO-NOT-RENDER');

        $this->actingAs($superadmin)
            ->from(route('superadmin.progress.index'))
            ->get(route('superadmin.progress.index', ['status' => 'private-response']))
            ->assertRedirect(route('superadmin.progress.index'))
            ->assertSessionHasErrors('status');
        $this->get(route('superadmin.progress.index'))
            ->assertOk()
            ->assertSee('Review the filter values below.');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($superadmin)
            ->get(route('superadmin.progress.learners.show', $completed))
            ->assertOk()
            ->assertSee('Front Desk Basics')
            ->assertSee('Migrated legacy completion')
            ->assertSee($completedProgress->completed_at->format('Y-m-d H:i'))
            ->assertSee($secondActivity->payload['title'])
            ->assertDontSee('PRIVATE-OPEN-RESPONSE-DO-NOT-RENDER')
            ->assertDontSee('PRIVATE-PROMPT');
        $detailSql = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();
        $this->assertStringNotContainsString('curriculum_responses', $detailSql);
    }

    public function test_admin_aggregate_never_exposes_identity_and_export_remains_unavailable(): void
    {
        [$package, $activity] = $this->canonicalPackage();
        $learner = $this->learner('Private Learner Name', 'One-person Institution');
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $this->progress($learner, $package, $activity, ['viewed_at' => now(), 'completed_at' => now()]);

        $this->actingAs($admin)
            ->get(route('admin.progress.index'))
            ->assertOk()
            ->assertSee('CSV export unavailable')
            ->assertDontSee($learner->name)
            ->assertDontSee($learner->email)
            ->assertDontSee($learner->instansi);

        $routeNames = collect(app('router')->getRoutes()->getRoutes())->map->getName()->filter();
        $this->assertFalse($routeNames->contains(fn (string $name): bool => str_contains($name, 'progress.export')));
    }

    public function test_stale_and_unknown_versions_are_labeled_without_invented_content(): void
    {
        $learner = $this->learner('Historical Learner', 'Hotel A');
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
        CurriculumActivityProgress::query()->create([
            'user_id' => $learner->id,
            'package_name' => 'retired-package',
            'content_version' => '0.0.1-retired',
            'activity_code' => 'RETIRED-ACTIVITY-CODE',
            'section_code' => 'RETIRED-SECTION-CODE',
            'viewed_at' => now(),
        ]);

        $this->actingAs($superadmin)
            ->get(route('superadmin.progress.learners.show', [
                'learner' => $learner,
                'package' => 'retired-package',
                'version' => '0.0.1-retired',
            ]))
            ->assertOk()
            ->assertSee('Canonical definition unavailable')
            ->assertSee('RETIRED-ACTIVITY-CODE')
            ->assertSee('RETIRED-SECTION-CODE');

        $this->actingAs($superadmin)
            ->get(route('superadmin.progress.learners.show', [
                'learner' => $learner,
                'package' => 'unknown-package',
                'version' => '9.9.9-unknown',
            ]))
            ->assertOk()
            ->assertSee('Canonical definition unavailable')
            ->assertDontSee('RETIRED-ACTIVITY-CODE');
    }

    public function test_progress_list_query_count_is_bounded_and_pagination_preserves_filters(): void
    {
        [$package, $activity] = $this->canonicalPackage();
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $first = $this->learner('Learner 00', 'Hotel A');
        $this->progress($first, $package, $activity, ['viewed_at' => now()]);

        $oneLearnerQueries = $this->progressPageQueryCount($superadmin);

        User::factory()->count(24)->create([
            'role' => UserRole::Learner,
            'instansi' => 'Hotel A',
        ])->each(fn (User $learner) => $this->progress($learner, $package, $activity, ['viewed_at' => now()]));

        DB::flushQueryLog();
        DB::enableQueryLog();
        $response = $this->actingAs($superadmin)->get(route('superadmin.progress.index', [
            'institution' => 'Hotel A',
            'status' => 'viewed',
        ]));
        $manyLearnerQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $response->assertOk()
            ->assertSee('page=2', escape: false)
            ->assertSee('institution=Hotel%20A', escape: false)
            ->assertSee('status=viewed', escape: false);
        $this->assertSame($oneLearnerQueries, $manyLearnerQueries);
        $this->assertLessThanOrEqual(24, $manyLearnerQueries);
    }

    public function test_detail_reflects_new_progress_without_a_stale_administration_cache(): void
    {
        [$package, $activity] = $this->canonicalPackage();
        $learner = $this->learner('Changing Learner', 'Hotel A');
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $progress = $this->progress($learner, $package, $activity, ['viewed_at' => now()]);

        $this->actingAs($superadmin)
            ->get(route('superadmin.progress.learners.show', $learner))
            ->assertOk()
            ->assertSee('Viewed');

        $progress->forceFill(['completed_at' => now()])->save();

        $this->actingAs($superadmin)
            ->get(route('superadmin.progress.learners.show', $learner))
            ->assertOk()
            ->assertSee('Completed');
    }

    public function test_unknown_activity_codes_cannot_inflate_active_completion(): void
    {
        [$package, $activity] = $this->canonicalPackage();
        $learner = $this->learner('Bounded Completion Learner', 'Hotel A');
        $this->progress($learner, $package, $activity, ['viewed_at' => now(), 'completed_at' => now()]);
        CurriculumActivityProgress::query()->create([
            'user_id' => $learner->id,
            'package_name' => $package->package_name,
            'content_version' => $package->content_version,
            'activity_code' => 'UNKNOWN-ACTIVITY',
            'section_code' => 'UNKNOWN-SECTION',
            'viewed_at' => now(),
            'completed_at' => now(),
        ]);

        $this->assertSame(50, app(CurriculumProgressService::class)->overallForUser($learner));
        $this->assertSame(50, app(ProgressAdministrationService::class)->aggregateOverview()['active_completion_percent']);
    }

    public function test_deleting_a_learner_removes_metadata_and_direct_detail_returns_not_found(): void
    {
        [$package, $activity] = $this->canonicalPackage();
        $learner = $this->learner('Deleted Learner', 'Hotel A');
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $this->progress($learner, $package, $activity, ['viewed_at' => now()]);
        $learnerId = $learner->id;
        $learner->delete();

        $this->assertDatabaseMissing('curriculum_activity_progress', ['user_id' => $learnerId]);
        $this->actingAs($superadmin)
            ->get(route('superadmin.progress.learners.show', $learnerId))
            ->assertNotFound();
    }

    /** @return array{CurriculumPackage, CurriculumEntity, CurriculumEntity} */
    private function canonicalPackage(): array
    {
        $package = CurriculumPackage::query()->create([
            'package_name' => 'hospitrainity',
            'content_version' => '1.0.0-test',
            'schema_version' => '1.0.0',
            'namespace_uuid' => (string) Str::uuid(),
            'lifecycle_status' => 'published',
            'source_path' => 'tests/package.json',
            'source_tree_sha256' => hash('sha256', 'test-package'),
            'source_file_count' => 1,
            'source_byte_count' => 1,
            'counts' => [],
            'projection_meta' => [],
            'laravel_projection_sha256' => hash('sha256', 'laravel'),
            'standalone_sha256' => hash('sha256', 'standalone'),
            'is_active' => true,
            'imported_at' => now(),
        ]);
        $chapter = $this->entity($package, 'CH-01', 'chapter', null, 1, [
            'module' => 1,
            'title' => 'Front Desk Basics',
        ]);
        $section = $this->entity($package, 'SEC-01', 'lesson-section', $chapter->code, 1, [
            'title' => 'Welcoming a Guest',
        ]);
        $first = $this->entity($package, 'ACT-01', 'activity', $section->code, 1, [
            'title' => 'Greeting Practice',
        ]);
        $second = $this->entity($package, 'ACT-02', 'activity', $section->code, 2, [
            'title' => 'Reservation Check',
        ]);

        return [$package, $first, $second];
    }

    /** @param array<string, mixed> $payload */
    private function entity(
        CurriculumPackage $package,
        string $code,
        string $type,
        ?string $parent,
        int $position,
        array $payload,
    ): CurriculumEntity {
        return CurriculumEntity::query()->create([
            'curriculum_package_id' => $package->id,
            'entity_uuid' => (string) Str::uuid(),
            'code' => $code,
            'entity_type' => $type,
            'parent_code' => $parent,
            'position' => $position,
            'lifecycle_status' => 'published',
            'content_version' => $package->content_version,
            'source_path' => 'tests/'.$code.'.json',
            'source_sha256' => hash('sha256', $code),
            'payload' => $payload,
        ]);
    }

    private function learner(string $name, string $institution): User
    {
        return User::factory()->create([
            'name' => $name,
            'instansi' => $institution,
            'role' => UserRole::Learner,
        ]);
    }

    /** @param array<string, mixed> $state */
    private function progress(
        User $learner,
        CurriculumPackage $package,
        CurriculumEntity $activity,
        array $state,
    ): CurriculumActivityProgress {
        return CurriculumActivityProgress::query()->create(array_merge([
            'user_id' => $learner->id,
            'package_name' => $package->package_name,
            'content_version' => $package->content_version,
            'activity_code' => $activity->code,
            'section_code' => $activity->parent_code,
        ], $state));
    }

    private function attempt(User $learner, CurriculumPackage $package, CurriculumEntity $activity): CurriculumAttempt
    {
        return CurriculumAttempt::query()->create([
            'id' => (string) Str::uuid(),
            'user_id' => $learner->id,
            'package_name' => $package->package_name,
            'content_version' => $package->content_version,
            'activity_code' => $activity->code,
            'activity_source_sha256' => $activity->source_sha256,
            'idempotency_key' => (string) Str::uuid(),
            'submission_hmac_sha256' => hash('sha256', 'submission'),
            'intent' => 'check',
            'state' => 'completed',
            'completion_reason' => 'test_only',
            'started_at' => now()->subMinute(),
            'attempted_at' => now(),
            'completed_at' => now(),
        ]);
    }

    private function progressPageQueryCount(User $superadmin): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($superadmin)
            ->get(route('superadmin.progress.index', [
                'institution' => 'Hotel A',
                'status' => 'viewed',
            ]))
            ->assertOk();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }
}

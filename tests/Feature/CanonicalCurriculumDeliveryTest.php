<?php

namespace Tests\Feature;

use App\Models\CurriculumActivityProgress;
use App\Models\CurriculumAttempt;
use App\Models\CurriculumDraftEntity;
use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\Exercise;
use App\Models\Lesson;
use App\Models\Material;
use App\Models\Module;
use App\Models\User;
use App\Models\Vocabulary;
use App\Services\CanonicalCurriculumRepository;
use App\Services\Curriculum\CanonicalCurriculumImporter;
use App\Services\Curriculum\CanonicalPackageReader;
use App\Services\Curriculum\CurriculumDraftWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class CanonicalCurriculumDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private string $artifactRoot;

    private User $learner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artifactRoot = storage_path('framework/testing/curriculum-delivery-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->artifactRoot);
        File::copy(config('curriculum.standalone_output'), $this->artifactRoot.'/standalone.html');
        config([
            'curriculum.standalone_output' => $this->artifactRoot.'/standalone.html',
            'curriculum.report_directory' => $this->artifactRoot.'/reports',
            'curriculum.rollback_directory' => $this->artifactRoot.'/rollbacks',
        ]);

        app(CanonicalCurriculumImporter::class)->import(app(CanonicalPackageReader::class)->read());
        $this->learner = User::factory()->create(['role' => 'user', 'email_verified_at' => now()]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->artifactRoot);

        parent::tearDown();
    }

    public function test_active_package_drives_dashboard_chapter_and_activity_views(): void
    {
        $this->actingAs($this->learner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('0.4.0-draft')
            ->assertDontSeeText('Source and lifecycle evidence')
            ->assertDontSeeText('Technical evidence')
            ->assertSee('Welcome and Introduction to Customer Care')
            ->assertSee('Dealing with Problems and Complaints');

        $this->get(route('curriculum.chapters.show', 'HSP-C02'))
            ->assertOk()
            ->assertSee('Front Desk and Check-In')
            ->assertDontSeeText('HSP-C02-LS-13')
            ->assertDontSeeText('Outcome evidence')
            ->assertDontSeeText('Section evidence')
            ->assertSee('Open section');

        $this->get(route('curriculum.sections.show', 'HSP-C02-LS-01'))
            ->assertOk()
            ->assertDontSeeText('Source and lifecycle evidence');

        $this->get(route('curriculum.activities.show', 'HSP-C02-ACT-QUIZ'))
            ->assertOk()
            ->assertSee('Which is the most polite way to ask for a document?')
            ->assertSee('Could I see your ID, please?')
            ->assertDontSee('Could I ... please?')
            ->assertSee('<fieldset', escape: false)
            ->assertDontSeeText('objective choice');
    }

    public function test_curriculum_evidence_is_reserved_for_system_admin_learner_context(): void
    {
        $systemAdmin = User::factory()->create(['role' => 'superadmin', 'email_verified_at' => now()]);
        $this->actingAs($systemAdmin)
            ->post(route('work-context.store'), ['role' => 'learner'])
            ->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSeeText('Non-production draft preview')
            ->assertSeeText('0.4.0-draft')
            ->assertSeeText('Source and lifecycle evidence')
            ->assertSeeText('Technical evidence');

        $this->get(route('curriculum.chapters.show', 'HSP-C02'))
            ->assertOk()
            ->assertSeeText('Outcome evidence')
            ->assertSeeText('Section evidence')
            ->assertSeeText('Source and lifecycle evidence');

        $this->get(route('curriculum.sections.show', 'HSP-C02-LS-01'))
            ->assertOk()
            ->assertSeeText('Source and lifecycle evidence');

        $this->get(route('curriculum.activities.show', 'HSP-C02-ACT-QUIZ'))
            ->assertOk()
            ->assertSeeText('objective choice');
    }

    public function test_section_view_model_preserves_ordered_blocks_tables_links_and_sequence_navigation(): void
    {
        $repository = app(CanonicalCurriculumRepository::class);
        $sections = CurriculumEntity::query()
            ->where('entity_type', 'lesson-section')
            ->orderBy('id')
            ->pluck('code');

        $models = $sections->map(static fn (string $code): array => $repository->section($code));

        $this->assertCount(85, $models);
        $this->assertTrue($models->every(static fn (array $section): bool => $section['blocks'] !== []));
        $this->assertSame(21, $models->sum(static fn (array $section): int => collect($section['blocks'])->where('type', 'source_table')->count()));
        $this->assertSame(18, $models->sum(static fn (array $section): int => collect($section['blocks'])->where('type', 'external_link')->sum(
            static fn (array $block): int => count($block['links']),
        )));

        $first = $repository->section('HSP-C01-LS-01');
        $last = $repository->section('HSP-C07-LS-13');
        $this->assertNull($first['navigation']['previous']);
        $this->assertSame('HSP-C01-LS-02', $first['navigation']['next']['code']);
        $this->assertSame('HSP-C07-LS-12', $last['navigation']['previous']['code']);
        $this->assertNull($last['navigation']['next']);
        $this->assertSame(85, $last['navigation']['total']);
    }

    public function test_all_sections_render_source_content_semantics_and_navigation(): void
    {
        $codes = CurriculumEntity::query()
            ->where('entity_type', 'lesson-section')
            ->orderBy('id')
            ->pluck('code');

        foreach ($codes as $code) {
            $this->actingAs($this->learner)
                ->get(route('curriculum.sections.show', $code))
                ->assertOk()
                ->assertDontSee('no standalone prompt body');
        }

        $this->get(route('curriculum.sections.show', 'HSP-C02-LS-01'))
            ->assertOk()
            ->assertSee('In one word, how are you feeling today?')
            ->assertSee('Next');
        $this->get(route('curriculum.sections.show', 'HSP-C02-LS-04'))
            ->assertOk()
            ->assertSee('<table', escape: false)
            ->assertSee('scope="col"', escape: false)
            ->assertSee('Word or phrase')
            ->assertSee('a-MEN-i-tees')
            ->assertDontSee('a-MEN- i -tees');
        $this->get(route('curriculum.sections.show', 'HSP-C02-LS-13'))
            ->assertOk()
            ->assertSee('(external site, opens in a new tab)')
            ->assertSee('rel="noopener noreferrer"', escape: false);
        $this->get(route('curriculum.sections.show', 'HSP-C07-LS-13'))
            ->assertOk()
            ->assertSee('Previous')
            ->assertDontSee('rel="next"', escape: false);
    }

    public function test_canonical_activity_completion_is_authorized_persisted_and_reflected_in_progress(): void
    {
        $activityCode = 'HSP-C02-ACT-QUIZ';
        $prompts = CurriculumEntity::query()
            ->where('entity_type', 'prompt-item')
            ->where('parent_code', $activityCode)
            ->get();
        $answers = CurriculumEntity::query()
            ->where('entity_type', 'answer-model')
            ->whereIn('parent_code', $prompts->pluck('code'))
            ->get()
            ->keyBy('parent_code');
        $responses = $prompts->mapWithKeys(static fn (CurriculumEntity $prompt): array => [
            $prompt->code => $answers[$prompt->code]->payload['correct_choice_ids'][0],
        ])->all();

        $this->actingAs($this->learner)
            ->post(route('curriculum.activities.attempts.store', $activityCode), [
                'attempt_key' => (string) Str::uuid(),
                'intent' => 'check',
                'responses' => $responses,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('curriculum_activity_progress', [
            'user_id' => $this->learner->id,
            'activity_code' => $activityCode,
        ]);
        $this->assertNotNull(CurriculumActivityProgress::query()->sole()->completed_at);
        $this->assertSame('completed', CurriculumAttempt::query()->sole()->state);
        $this->assertDatabaseCount('completions', 0);
        $this->get(route('dashboard'))->assertOk()->assertSee('25%');
        $this->get(route('curriculum.activities.show', $activityCode))
            ->assertOk()
            ->assertSee('Progress state: completed')
            ->assertSee('Attempts: 1');
    }

    public function test_retired_completion_endpoint_rejects_canonical_entities(): void
    {
        $chapter = CurriculumEntity::query()->where('code', 'HSP-C02')->sole();

        $this->actingAs($this->learner)
            ->postJson(route('progress.store'), [
                'type' => 'CurriculumActivity',
                'items' => [$chapter->id],
            ])
            ->assertUnprocessable();

        $this->assertDatabaseCount('completions', 0);
        $this->assertDatabaseCount('curriculum_activity_progress', 0);
    }

    public function test_legacy_delivery_is_retired_but_admin_evidence_remains_visible(): void
    {
        $legacy = Module::create([
            'title' => 'Legacy module',
            'slug' => 'legacy-module',
            'level' => 'beginner',
            'order' => 1,
            'is_published' => true,
        ]);

        $this->actingAs($this->learner)
            ->get(route('modules.show', $legacy))
            ->assertGone();

        $admin = User::factory()->create(['role' => 'superadmin', 'email_verified_at' => now()]);
        $this->actingAs($admin)
            ->get(route('superadmin.modules.index'))
            ->assertOk()
            ->assertSee('The canonical curriculum is active.')
            ->assertSee('Legacy evidence')
            ->assertSee('Legacy Module Evidence')
            ->assertSee('Read-only evidence')
            ->assertDontSee('Content management')
            ->assertDontSee('Manage modules')
            ->assertDontSee('Create module')
            ->assertDontSee('Edit Legacy module')
            ->assertDontSee('Delete Legacy module')
            ->assertSee('Legacy module');
    }

    public function test_superadmin_dashboard_uses_canonical_counts_and_labels_legacy_counts_as_evidence(): void
    {
        Module::factory()->count(8)->create();
        $admin = User::factory()->create(['role' => 'superadmin', 'email_verified_at' => now()]);
        $active = CurriculumPackage::active();
        CurriculumEntity::query()->create([
            'curriculum_package_id' => $active->id,
            'entity_uuid' => (string) Str::uuid(),
            'code' => 'ARCHIVED-CHAPTER-REGRESSION',
            'entity_type' => 'chapter',
            'position' => 999,
            'lifecycle_status' => 'archived',
            'content_version' => $active->content_version,
            'source_path' => 'archive/dashboard-regression.json',
            'source_sha256' => hash('sha256', 'archive/dashboard-regression.json'),
            'payload' => ['title' => 'Archived dashboard regression row'],
        ]);
        $draft = app(CurriculumDraftWorkspace::class)->create($admin, [
            'source' => 'empty',
            'title' => 'Dashboard isolation draft',
            'content_version' => '9.2.0',
        ]);
        CurriculumDraftEntity::query()->create([
            'curriculum_draft_id' => $draft->id,
            'entity_uuid' => (string) Str::uuid(),
            'code' => 'DRAFT-AVAILABLE',
            'entity_type' => 'chapter',
            'position' => 1,
            'source_path' => 'draft/dashboard-available.json',
            'payload' => ['title' => 'Available draft entity'],
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);
        CurriculumDraftEntity::query()->create([
            'curriculum_draft_id' => $draft->id,
            'entity_uuid' => (string) Str::uuid(),
            'code' => 'DRAFT-ARCHIVED',
            'entity_type' => 'chapter',
            'position' => 2,
            'source_path' => 'draft/dashboard-archived.json',
            'payload' => ['title' => 'Archived draft entity'],
            'archived_at' => now(),
            'created_by' => $admin->id,
            'updated_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)->get(route('superadmin.dashboard'));

        $response
            ->assertOk()
            ->assertViewHas('stats', static fn (array $stats): bool => $stats['active_canonical_chapters'] === 7)
            ->assertViewHas('canonicalStatus', static fn (array $status): bool => $status['content_version'] === '0.4.0-draft'
                && $status['chapters'] === 7
                && $status['sections'] === 85
                && $status['activities'] === 25
                && $status['total_versions'] === 1
                && $status['draft_lifecycle_versions'] === 1
                && $status['inactive_versions'] === 0)
            ->assertViewHas('legacyEvidenceCounts', static fn (array $counts): bool => $counts['modules'] === 8)
            ->assertViewHas('draftWorkspaceCounts', static fn (array $counts): bool => $counts['total'] === 1
                && $counts['editable'] === 1
                && $counts['available_entities'] === 1
                && $counts['archived_entities'] === 1)
            ->assertSee('Active canonical chapters')
            ->assertSee('Active delivery version')
            ->assertSee('0.4.0-draft')
            ->assertSee('Legacy evidence')
            ->assertSee('Legacy modules')
            ->assertDontSee('Total modules')
            ->assertDontSee('Content management')
            ->assertDontSee('Manage modules');
    }

    public function test_all_retained_curriculum_indexes_use_truthful_legacy_evidence_terminology(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'email_verified_at' => now()]);
        $screens = [
            'superadmin.modules.index' => ['Legacy Module Evidence', 'Manage modules'],
            'superadmin.lessons.index' => ['Legacy Lesson Evidence', 'Manage lessons'],
            'superadmin.vocabularies.index' => ['Legacy Vocabulary Evidence', 'Manage vocabulary'],
            'superadmin.materials.index' => ['Legacy Material Evidence', 'Manage materials'],
            'superadmin.exercises.index' => ['Legacy Exercise Evidence', 'Manage exercises'],
        ];

        foreach ($screens as $routeName => [$heading, $retiredLabel]) {
            $this->actingAs($admin)
                ->get(route($routeName))
                ->assertOk()
                ->assertSee('Legacy evidence')
                ->assertSee($heading)
                ->assertSee('Read-only')
                ->assertDontSee('Content management')
                ->assertDontSee($retiredLabel);
        }

        $this->withSession(['locale' => 'id'])
            ->actingAs($admin)
            ->get(route('superadmin.exercises.index'))
            ->assertOk()
            ->assertSee('Bukti sistem lama')
            ->assertSee('Bukti Latihan Sistem Lama')
            ->assertSee('Hanya baca');
    }

    public function test_all_legacy_admin_writes_fail_closed_while_canonical_package_is_active(): void
    {
        $admin = User::factory()->create(['role' => 'superadmin', 'email_verified_at' => now()]);
        $targets = [
            'modules' => Module::factory()->create(),
            'lessons' => Lesson::factory()->create(),
            'vocabularies' => Vocabulary::factory()->create(),
            'materials' => Material::factory()->create(),
            'exercises' => Exercise::factory()->create(),
        ];

        $this->actingAs($admin);

        foreach ($targets as $resource => $model) {
            $this->post(route("superadmin.{$resource}.store"))->assertGone();
            $this->put(route("superadmin.{$resource}.update", $model))->assertGone();
            $this->delete(route("superadmin.{$resource}.destroy", $model))->assertGone();

            $this->assertNotNull($model->fresh(), "Blocked {$resource} writes must preserve retained evidence.");
        }
    }

    public function test_canonical_interface_controls_keep_indonesian_locale_without_translating_source_content(): void
    {
        $this->withSession(['locale' => 'id'])
            ->actingAs($this->learner)
            ->get(route('curriculum.chapters.show', 'HSP-C02'))
            ->assertOk()
            ->assertSee('Capaian pembelajaran')
            ->assertSee('Front Desk and Check-In');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertDontSeeText('tanpa terjemahan buatan');

        $systemAdmin = User::factory()->create(['role' => 'superadmin', 'email_verified_at' => now()]);
        $this->actingAs($systemAdmin)
            ->post(route('work-context.store'), ['role' => 'learner'])
            ->assertRedirect(route('dashboard'));
        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('tanpa terjemahan buatan', escape: false);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\CurriculumEntity;
use App\Models\CurriculumPackage;
use App\Models\SearchDocument;
use App\Models\SearchDocumentTerm;
use App\Models\SearchIndexGeneration;
use App\Models\User;
use App\Services\Curriculum\CanonicalCurriculumImporter;
use App\Services\Curriculum\CanonicalPackageReader;
use App\Services\SearchIndexBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublishedContentSearchTest extends TestCase
{
    use RefreshDatabase;

    private string $artifactRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artifactRoot = storage_path('framework/testing/b05-search-'.bin2hex(random_bytes(5)));
        File::ensureDirectoryExists($this->artifactRoot);
        File::copy(config('curriculum.standalone_output'), $this->artifactRoot.'/standalone.html');
        config([
            'curriculum.standalone_output' => $this->artifactRoot.'/standalone.html',
            'curriculum.report_directory' => $this->artifactRoot.'/reports',
            'curriculum.rollback_directory' => $this->artifactRoot.'/rollbacks',
        ]);

        app(CanonicalCurriculumImporter::class)->import(app(CanonicalPackageReader::class)->read());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->artifactRoot);
        parent::tearDown();
    }

    public function test_index_contains_only_approved_published_result_types_and_search_is_authenticated(): void
    {
        $this->get(route('search.index'))->assertRedirect(route('login'));

        $generation = SearchIndexGeneration::query()->where('is_active', true)->sole();
        $this->assertGreaterThan(0, $generation->document_count);
        $this->assertSame($generation->document_count, $generation->documents()->count());
        $this->assertSame(
            [],
            SearchDocument::query()->distinct()->pluck('source_type')->diff(['module', 'section', 'vocabulary', 'activity', 'help', 'glossary'])->values()->all(),
        );
        $this->assertFalse(SearchDocument::query()->where('published', false)->exists());
        $this->assertFalse(SearchDocument::query()->whereIn('source_type', ['draft', 'prompt-item', 'answer-model', 'user', 'response'])->exists());

        $section = SearchDocument::query()->where('source_type', 'section')->orderBy('id')->firstOrFail();
        $query = implode(' ', array_slice(app(SearchIndexBuilder::class)->tokens($section->title), 0, 6));
        $learner = User::factory()->create(['role' => UserRole::Learner]);

        $this->actingAs($learner)->get(route('search.index', ['q' => $query, 'type' => 'section']))
            ->assertOk()
            ->assertSee($section->title)
            ->assertDontSee('<mark>', false);
        $this->get(route('search.index', ['q' => 'test', 'type' => 'user']))
            ->assertSessionHasErrors('type');
    }

    public function test_search_generation_is_reproducible_retained_and_rebuilt_when_versioned_help_changes(): void
    {
        $builder = app(SearchIndexBuilder::class);
        $first = $builder->rebuild();
        $this->assertFalse($first['changed']);
        $this->assertDatabaseCount('search_index_generations', 1);

        config()->set('help.version', '2026-07-20-test-revision');
        $second = $builder->rebuild();

        $this->assertTrue($second['changed']);
        $this->assertNotSame($first['id'], $second['id']);
        $this->assertDatabaseCount('search_index_generations', 2);
        $this->assertSame(1, SearchIndexGeneration::query()->where('is_active', true)->count());
        $this->assertTrue(SearchIndexGeneration::query()->whereKey($first['id'])->where('is_active', false)->exists());
    }

    public function test_draft_and_private_entity_kinds_cannot_enter_the_index(): void
    {
        $package = CurriculumPackage::active();
        $this->assertNotNull($package);
        $needle = 'PRIVATE-DRAFT-'.Str::upper(Str::random(10));

        CurriculumEntity::query()->create([
            'curriculum_package_id' => $package->id,
            'entity_uuid' => (string) Str::uuid(),
            'code' => $needle,
            'entity_type' => 'prompt-item',
            'position' => 999,
            'lifecycle_status' => 'draft',
            'content_version' => $package->content_version,
            'source_path' => 'test/private-draft.json',
            'source_sha256' => hash('sha256', $needle),
            'payload' => ['title' => $needle, 'answer' => 'must-not-be-indexed'],
        ]);

        config()->set('help.version', 'force-safe-rebuild');
        app(SearchIndexBuilder::class)->rebuild();

        $this->assertFalse(SearchDocument::query()->where('title', $needle)->exists());
        $this->assertFalse(SearchDocument::query()->where('summary', 'like', '%must-not-be-indexed%')->exists());
    }

    public function test_search_fails_closed_for_a_future_restricted_audience_document(): void
    {
        $generation = SearchIndexGeneration::query()->where('is_active', true)->sole();
        $document = SearchDocument::query()->create([
            'search_index_generation_id' => $generation->id,
            'source_type' => 'help',
            'source_key' => 'restricted-test-record',
            'route_reference' => 'getting-started',
            'locale' => 'en',
            'audience' => 'system-admin',
            'title' => 'Restricted audience sentinel',
            'summary' => 'sentinelrestricted must not be visible to an ordinary authenticated search.',
            'published' => true,
            'sort_order' => 999999,
            'content_sha256' => hash('sha256', 'restricted audience sentinel'),
        ]);
        SearchDocumentTerm::query()->create([
            'search_document_id' => $document->id,
            'term' => 'sentinelrestricted',
            'weight' => 30,
        ]);

        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $this->actingAs($learner)
            ->get(route('search.index', ['q' => 'sentinelrestricted']))
            ->assertOk()
            ->assertDontSee('Restricted audience sentinel');
    }
}

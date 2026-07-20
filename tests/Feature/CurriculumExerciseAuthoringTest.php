<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Http\Requests\ExerciseRequest;
use App\Models\CurriculumActivityProgress;
use App\Models\CurriculumAttempt;
use App\Models\CurriculumDraft;
use App\Models\CurriculumDraftEntity;
use App\Models\CurriculumPackage;
use App\Models\User;
use App\Services\Curriculum\CanonicalCurriculumImporter;
use App\Services\Curriculum\CanonicalExerciseTemplateRegistry;
use App\Services\Curriculum\CanonicalPackageReader;
use App\Services\Curriculum\CurriculumDraftExerciseWorkspace;
use App\Services\Curriculum\CurriculumDraftReview;
use App\Services\Curriculum\CurriculumDraftWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class CurriculumExerciseAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private string $artifactRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artifactRoot = storage_path('framework/testing/adm4-'.bin2hex(random_bytes(4)));
        File::ensureDirectoryExists($this->artifactRoot);
        File::copy(config('curriculum.standalone_output'), $this->artifactRoot.'/standalone.html');
        config([
            'filesystems.disks.curriculum_private.root' => $this->artifactRoot.'/private',
            'curriculum.standalone_output' => $this->artifactRoot.'/standalone.html',
            'curriculum.report_directory' => $this->artifactRoot.'/reports',
            'curriculum.rollback_directory' => $this->artifactRoot.'/rollbacks',
            'curriculum.draft_validation_directory' => $this->artifactRoot.'/validation',
            'curriculum.published_package_directory' => $this->artifactRoot.'/published',
        ]);
        app('filesystem')->forgetDisk('curriculum_private');
    }

    protected function tearDown(): void
    {
        app('filesystem')->forgetDisk('curriculum_private');
        File::deleteDirectory($this->artifactRoot);
        parent::tearDown();
    }

    public function test_registry_inventories_all_legacy_names_with_explicit_enabled_contracts(): void
    {
        $registry = app(CanonicalExerciseTemplateRegistry::class);
        $this->assertSame(ExerciseRequest::TYPES, array_keys($registry->all()));
        $this->assertCount(12, $registry->enabledTypes());
        $this->assertSame(
            ['spelling_quiz', 'listening_task'],
            array_keys(array_filter($registry->all(), static fn (array $definition): bool => $definition['enabled'] === false)),
        );
        foreach ($registry->all() as $type => $definition) {
            $this->assertSame('canonical_activity', $definition['renderer'], $type);
            $this->assertContains($definition['scoring_mode'], array_keys($registry->scoringPolicies()), $type);
            $this->assertNotEmpty($definition['stable_identifiers'], $type);
            $this->assertGreaterThanOrEqual($definition['cardinality']['minimum'], $definition['cardinality']['maximum'], $type);
        }
        $this->assertSame('exact', $registry->scoringPolicies()['objective_choice']['policy']);
        $this->assertFalse($registry->scoringPolicies()['rubric_self_assessment']['server_scored']);
        $this->assertFalse($registry->scoringPolicies()['unscored_self_report']['server_scored']);
    }

    public function test_exercise_routes_are_policy_scoped_and_render_all_enabled_templates(): void
    {
        $this->importActive();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $unverified = User::factory()->unverified()->create(['role' => UserRole::Admin]);
        $draft = $this->cloneDraft($admin);

        $this->get(route('admin.curriculum-drafts.exercises.index', $draft))->assertRedirect(route('login'));
        $this->actingAs($learner)->get(route('admin.curriculum-drafts.exercises.index', $draft))->assertForbidden();
        $this->actingAs($unverified)->get(route('admin.curriculum-drafts.exercises.index', $draft))->assertRedirect(route('verification.notice'));
        $response = $this->actingAs($admin)->get(route('admin.curriculum-drafts.exercises.index', $draft))->assertOk();
        foreach (app(CanonicalExerciseTemplateRegistry::class)->enabledTypes() as $type) {
            $response->assertSee(__('admin.exercise_templates.'.$type));
        }
        $this->actingAs($superadmin)->get(route('superadmin.curriculum-drafts.exercises.index', $draft))->assertOk();
    }

    public function test_all_enabled_templates_compile_validate_and_preview_through_the_canonical_renderer(): void
    {
        $this->importActive();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $draft = $this->cloneDraft($admin);
        $active = CurriculumPackage::active();
        $activeHash = $active->source_tree_sha256;
        $enabledTypes = app(CanonicalExerciseTemplateRegistry::class)->enabledTypes();
        $sections = $this->emptySections($draft, count($enabledTypes));

        foreach ($enabledTypes as $index => $type) {
            $payload = $this->payload($draft, $type, $sections[$index]->code, '', $index + 1);
            $this->actingAs($admin)->post(route('admin.curriculum-drafts.exercises.store', $draft), $payload)
                ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
            $draft->refresh();
            $activity = $draft->entities()->where('entity_type', 'activity')->where('code', $payload['code'])->sole();
            $this->assertSame($type, $activity->payload['template_type']);
            $this->assertSame(CanonicalExerciseTemplateRegistry::VERSION, $activity->payload['template_registry_version']);
            $this->actingAs($admin)->get(route('admin.curriculum-drafts.preview.activities.show', [$draft, $activity->code]))
                ->assertOk()->assertSee($payload['title'])->assertSee($payload['items'][0]['stem']);
        }

        $this->assertSame(12, $draft->entities()->where('entity_type', 'activity')->whereNotNull('payload->template_type')->count());
        $selectionPrompts = $draft->entities()->where('entity_type', 'prompt-item')->where('payload->response_form', 'selection')->get();
        foreach ($selectionPrompts as $prompt) {
            $answer = $draft->entities()->where('entity_type', 'answer-model')->where('parent_code', $prompt->code)->sole();
            $ids = array_column($prompt->payload['choices'], 'id');
            $this->assertCount(count(array_unique($ids)), $ids);
            $this->assertContains($answer->payload['correct_choice_ids'][0], $ids);
            $this->assertArrayNotHasKey('correct', $prompt->payload['choices'][0]);
        }
        $orderingPrompts = $draft->entities()->where('entity_type', 'prompt-item')->where('payload->response_form', 'ordering')->get();
        foreach ($orderingPrompts as $prompt) {
            $answer = $draft->entities()->where('entity_type', 'answer-model')->where('parent_code', $prompt->code)->sole();
            $this->assertEqualsCanonicalizing(array_column($prompt->payload['tokens'], 'id'), $answer->payload['correct_order']);
            $this->assertNotSame(array_column($prompt->payload['tokens'], 'id'), $answer->payload['correct_order']);
        }

        $validated = app(CurriculumDraftReview::class)->validate($draft->fresh(), $admin, $draft->revision);
        $this->assertSame('valid', $validated->validation_report['status'], json_encode($validated->validation_report));
        $this->assertSame($active->id, CurriculumPackage::active()?->id);
        $this->assertSame($activeHash, CurriculumPackage::active()?->source_tree_sha256);
        $this->assertDatabaseCount((new CurriculumAttempt)->getTable(), 0);
        $this->assertDatabaseCount((new CurriculumActivityProgress)->getTable(), 0);
    }

    public function test_invalid_choice_is_rejected_and_edit_preserves_ids_while_reordering_items(): void
    {
        $this->importActive();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $draft = $this->cloneDraft($admin);
        $section = $this->emptySections($draft, 1)->first();
        $payload = $this->payload($draft, 'multiple_choice_quiz', $section->code, '', 1);
        $payload['items'][] = ['code' => '', 'stem' => 'Second question', 'answer' => 'B', 'options' => "A\nB", 'tokens' => '', 'model_answer' => '', 'feedback' => 'Review the model.'];
        $this->actingAs($admin)->post(route('admin.curriculum-drafts.exercises.store', $draft), $payload)->assertSessionHasNoErrors();
        $draft->refresh();
        $activity = $draft->entities()->where('code', $payload['code'])->sole();
        $prompts = $draft->entities()->where('entity_type', 'prompt-item')->where('parent_code', $activity->code)->orderBy('position')->get();
        $stablePromptCodes = $prompts->pluck('code')->all();
        $choiceIds = array_column($prompts[0]->payload['choices'], 'id');

        $bad = $payload;
        $bad['draft_revision'] = $draft->revision;
        $bad['activity_revision'] = $activity->revision;
        $bad['items'][0]['code'] = $prompts[0]->code;
        $bad['items'][0]['answer'] = 'Not an option';
        $bad['items'][1]['code'] = $prompts[1]->code;
        $this->actingAs($admin)->patch(route('admin.curriculum-drafts.exercises.update', [$draft, $activity]), $bad)
            ->assertSessionHasErrors('items.0.answer');
        $this->assertSame($draft->revision, $draft->fresh()->revision);

        $good = $payload;
        $good['draft_revision'] = $draft->revision;
        $good['activity_revision'] = $activity->revision;
        $good['items'] = [
            array_replace($payload['items'][1], ['code' => $prompts[1]->code]),
            array_replace($payload['items'][0], ['code' => $prompts[0]->code, 'options' => "Yes, please\nNo", 'answer' => 'Yes, please']),
        ];
        $this->actingAs($admin)->patch(route('admin.curriculum-drafts.exercises.update', [$draft, $activity]), $good)
            ->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');
        $reordered = $draft->entities()->where('entity_type', 'prompt-item')->where('parent_code', $activity->code)->orderBy('position')->get();
        $this->assertSame(array_reverse($stablePromptCodes), $reordered->pluck('code')->all());
        $edited = $reordered->firstWhere('code', $stablePromptCodes[0]);
        $this->assertSame($choiceIds, array_column($edited->payload['choices'], 'id'));
    }

    public function test_draft_preview_uses_server_validation_and_scoring_without_recording_attempt_or_progress(): void
    {
        $this->importActive();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $draft = $this->cloneDraft($admin);
        $section = $this->emptySections($draft, 1)->first();
        $payload = $this->payload($draft, 'multiple_choice_quiz', $section->code, '', 1);
        $payload['items'][] = [
            'code' => '', 'stem' => 'Second question', 'answer' => 'B', 'options' => "A\nB",
            'tokens' => '', 'model_answer' => '', 'feedback' => 'Review the second choice.',
        ];
        $this->actingAs($admin)->post(route('admin.curriculum-drafts.exercises.store', $draft), $payload)
            ->assertSessionHasNoErrors();
        $draft->refresh();
        $activity = $draft->entities()->where('code', $payload['code'])->sole();
        $prompts = $draft->entities()->where('entity_type', 'prompt-item')->where('parent_code', $activity->code)
            ->orderBy('position')->get();
        $firstAnswer = $draft->entities()->where('entity_type', 'answer-model')->where('parent_code', $prompts[0]->code)->sole();
        $secondAnswer = $draft->entities()->where('entity_type', 'answer-model')->where('parent_code', $prompts[1]->code)->sole();
        $wrongSecondChoice = collect($prompts[1]->payload['choices'])
            ->first(fn (array $choice): bool => $choice['id'] !== $secondAnswer->payload['correct_choice_ids'][0]);

        $this->actingAs($admin)->get(route('admin.curriculum-drafts.preview.activities.show', [$draft, $activity->code]))
            ->assertOk()
            ->assertSee('method="POST"', false)
            ->assertSee('action="'.route('admin.curriculum-drafts.preview.activities.attempt', [$draft, $activity->code]).'"', false)
            ->assertSee('type="submit" name="intent" value="check"', false);

        $invalid = $this->actingAs($admin)->from(route('admin.curriculum-drafts.preview.activities.show', [$draft, $activity->code]))
            ->post(route('admin.curriculum-drafts.preview.activities.attempt', [$draft, $activity->code]), [
                'attempt_key' => (string) Str::uuid(), 'intent' => 'check',
                'responses' => [$prompts[0]->code => 'client-invented-choice'],
            ]);
        $invalid->assertSessionHasErrors('responses.'.$prompts[0]->code);

        $response = $this->actingAs($admin)->post(
            route('admin.curriculum-drafts.preview.activities.attempt', [$draft, $activity->code]),
            [
                'attempt_key' => (string) Str::uuid(), 'intent' => 'check',
                'responses' => [
                    $prompts[0]->code => $firstAnswer->payload['correct_choice_ids'][0],
                    $prompts[1]->code => $wrongSecondChoice['id'],
                ],
            ],
        );
        $response->assertRedirect(route('admin.curriculum-drafts.preview.activities.show', [$draft, $activity->code]).'#attempt-result')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('preview_attempt_result', function (array $result) use ($prompts): bool {
                return $result['attempt_id'] === null
                    && $result['prompt_results'][$prompts[0]->code]['is_correct'] === true
                    && $result['prompt_results'][$prompts[1]->code]['is_correct'] === false;
            });
        $this->get(route('admin.curriculum-drafts.preview.activities.show', [$draft, $activity->code]))
            ->assertOk()->assertSee('Correct')->assertSee('Not yet correct')->assertSee('Review the second choice.');

        $this->assertDatabaseCount((new CurriculumAttempt)->getTable(), 0);
        $this->assertDatabaseCount((new CurriculumActivityProgress)->getTable(), 0);
        $this->assertSame(2, $draft->fresh()->revision);
    }

    public function test_audio_only_templates_remain_mapped_but_fail_closed_until_an_equivalent_alternative_is_approved(): void
    {
        $this->importActive();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $draft = $this->cloneDraft($admin);
        $section = $this->emptySections($draft, 1)->first();
        foreach (['spelling_quiz', 'listening_task'] as $type) {
            $this->actingAs($admin)->get(route('admin.curriculum-drafts.exercises.create', [$draft, 'template' => $type]))->assertNotFound();
            $payload = $this->payload($draft, $type, $section->code, (string) Str::uuid(), 1);
            $this->actingAs($admin)->post(route('admin.curriculum-drafts.exercises.store', $draft), $payload)
                ->assertSessionHasErrors('template_type');
            $this->assertFalse($draft->entities()->where('code', $payload['code'])->exists());
        }
        $this->assertSame(1, $draft->fresh()->revision);
    }

    public function test_duplicate_gets_new_entity_choice_and_token_ids_and_requires_an_empty_section(): void
    {
        $this->importActive();
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $draft = $this->cloneDraft($admin);
        $sections = $this->emptySections($draft, 2);
        $payload = $this->payload($draft, 'sequencing', $sections[0]->code, '', 1);
        $this->actingAs($admin)->post(route('admin.curriculum-drafts.exercises.store', $draft), $payload)->assertSessionHasNoErrors();
        $draft->refresh();
        $source = $draft->entities()->where('code', $payload['code'])->sole();
        $sourcePrompt = $draft->entities()->where('entity_type', 'prompt-item')->where('parent_code', $source->code)->sole();

        $this->actingAs($admin)->post(route('admin.curriculum-drafts.exercises.duplicate', [$draft, $source]), [
            'draft_revision' => $draft->revision, 'section_code' => $sections[0]->code,
        ])->assertSessionHasErrors('section_code');
        $this->actingAs($admin)->post(route('admin.curriculum-drafts.exercises.duplicate', [$draft, $source]), [
            'draft_revision' => $draft->revision, 'section_code' => $sections[1]->code,
        ])->assertRedirect()->assertSessionHasNoErrors()->assertSessionHas('success');

        $copy = $draft->entities()->where('entity_type', 'activity')->where('parent_code', $sections[1]->code)->sole();
        $copyPrompt = $draft->entities()->where('entity_type', 'prompt-item')->where('parent_code', $copy->code)->sole();
        $this->assertNotSame($source->entity_uuid, $copy->entity_uuid);
        $this->assertNotSame($sourcePrompt->entity_uuid, $copyPrompt->entity_uuid);
        $this->assertNotSame(array_column($sourcePrompt->payload['tokens'], 'id'), array_column($copyPrompt->payload['tokens'], 'id'));
        $this->assertDatabaseHas('curriculum_draft_events', ['curriculum_draft_id' => $draft->id, 'event_type' => 'exercise_created']);
    }

    public function test_published_template_uses_the_same_view_and_later_edits_preserve_versioned_attempt_history(): void
    {
        $this->importActive();
        $superadmin = User::factory()->create(['role' => UserRole::Superadmin]);
        $learner = User::factory()->create(['role' => UserRole::Learner]);
        $draft = $this->cloneDraft($superadmin);
        $section = $this->emptySections($draft, 1)->first();
        $payload = $this->payload($draft, 'multiple_choice_quiz', $section->code, '', 1);
        $this->actingAs($superadmin)->post(route('superadmin.curriculum-drafts.exercises.store', $draft), $payload)->assertSessionHasNoErrors();
        $draft->refresh();
        $activity = $draft->entities()->where('code', $payload['code'])->sole();

        $this->actingAs($superadmin)->get(route('superadmin.curriculum-drafts.preview.activities.show', [$draft, $activity->code]))
            ->assertOk()->assertViewIs('curriculum.activity')->assertSee('name="responses['.$activity->code.'-I01]"', false);
        $this->assertDatabaseCount((new CurriculumActivityProgress)->getTable(), 0);

        $review = app(CurriculumDraftReview::class);
        $draft = $review->validate($draft, $superadmin, $draft->revision);
        $this->assertSame('valid', $draft->validation_report['status'], json_encode($draft->validation_report));
        $draft = $review->approve($draft, $superadmin, $draft->revision, 'ADM-4 canonical validation and preview were reviewed.');
        // This characterization test needs two synthetic delivered versions to
        // prove historical attempts remain version-bound. The release guard
        // also requires APP_ENV=testing, so this cannot widen runtime behavior.
        config()->set('curriculum.release.allow_unapproved_replacement_for_tests', true);
        $review->publish($draft, $superadmin, $draft->revision);

        $live = $this->actingAs($learner)->get(route('curriculum.activities.show', $activity->code))
            ->assertOk()->assertViewIs('curriculum.activity')->assertSee('name="responses['.$activity->code.'-I01]"', false);
        $live->assertSee($payload['items'][0]['stem']);
        $prompt = CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)
            ->where('entity_type', 'prompt-item')->where('parent_code', $activity->code)->sole();
        $answer = CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)
            ->where('entity_type', 'answer-model')->where('parent_code', $prompt->code)->sole();
        $this->actingAs($learner)->post(route('curriculum.activities.attempts.store', $activity->code), [
            'attempt_key' => (string) Str::uuid(), 'intent' => 'check',
            'responses' => [$prompt->code => $answer->payload['correct_choice_ids'][0]],
        ])->assertRedirect()->assertSessionHasNoErrors();
        $attempt = CurriculumAttempt::query()->where('user_id', $learner->id)->where('activity_code', $activity->code)->sole();
        $this->assertSame('0.4.1', $attempt->content_version);

        $revisionDraft = app(CurriculumDraftWorkspace::class)->create($superadmin, [
            'source' => 'clone', 'content_version' => '0.4.2', 'title' => 'ADM-4 later exercise revision',
        ]);
        $revisionActivity = $revisionDraft->entities()->where('entity_type', 'activity')->where('code', $activity->code)->sole();
        $edit = app(CurriculumDraftExerciseWorkspace::class)->editorData($revisionDraft, $revisionActivity);
        $edit['title'] = 'A later draft title';
        $this->actingAs($superadmin)->patch(route('superadmin.curriculum-drafts.exercises.update', [$revisionDraft, $revisionActivity]), $edit)
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($payload['title'], CurriculumPackage::active()->entities()->where('code', $activity->code)->sole()->payload['title']);
        $this->assertSame('0.4.1', $attempt->fresh()->content_version);
        $this->assertSame('A later draft title', $revisionActivity->fresh()->payload['title']);
    }

    private function importActive(): void
    {
        app(CanonicalCurriculumImporter::class)->import(app(CanonicalPackageReader::class)->read());
    }

    private function cloneDraft(User $actor): CurriculumDraft
    {
        return app(CurriculumDraftWorkspace::class)->create($actor, [
            'source' => 'clone', 'content_version' => '0.4.1', 'title' => 'ADM-4 exercise authoring candidate',
        ]);
    }

    /** @return Collection<int, CurriculumDraftEntity> */
    private function emptySections(CurriculumDraft $draft, int $count)
    {
        $occupied = $draft->entities()->where('entity_type', 'activity')->whereNull('archived_at')->pluck('parent_code');

        return $draft->entities()->where('entity_type', 'lesson-section')->whereNull('archived_at')
            ->whereNotIn('code', $occupied)->orderBy('parent_code')->orderBy('position')->limit($count)->get();
    }

    /** @return array<string, mixed> */
    private function payload(CurriculumDraft $draft, string $type, string $section, string $audio, int $number): array
    {
        $definition = app(CanonicalExerciseTemplateRegistry::class)->get($type);
        $items = [];
        for ($index = 0; $index < $definition['cardinality']['minimum']; $index++) {
            $items[] = match ($definition['editor']) {
                'closed' => ['code' => '', 'stem' => "Closed prompt {$index}", 'answer' => "Answer {$index}", 'options' => '', 'tokens' => '', 'model_answer' => '', 'feedback' => 'Review spelling and context.'],
                'explicit_selection' => ['code' => '', 'stem' => "Selection prompt {$index}", 'answer' => 'Yes', 'options' => "Yes\nNo", 'tokens' => '', 'model_answer' => '', 'feedback' => 'Review the selected option.'],
                'derived_selection' => ['code' => '', 'stem' => "Match prompt {$index}", 'answer' => 'Category '.($index + 1), 'options' => '', 'tokens' => '', 'model_answer' => '', 'feedback' => 'Review the match or category.'],
                'silent_letter' => ['code' => '', 'stem' => 'knock', 'answer' => 'k', 'options' => '', 'tokens' => '', 'model_answer' => '', 'feedback' => 'Notice the initial silent letter.'],
                'ordering' => ['code' => '', 'stem' => "Order prompt {$index}", 'answer' => '', 'options' => '', 'tokens' => "First step\nSecond step", 'model_answer' => '', 'feedback' => 'Review the service sequence.'],
                'open' => ['code' => '', 'stem' => "Open practice {$index}", 'answer' => '', 'options' => '', 'tokens' => '', 'model_answer' => 'A polite reviewed model response.', 'feedback' => 'Compare tone, clarity, and task completion.'],
            };
        }

        return [
            'draft_revision' => $draft->revision, 'template_type' => $type,
            'code' => 'HSP-ADM4-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'section_code' => $section, 'title' => 'ADM-4 '.$type,
            'guidance' => 'Complete each item, then check the server result or self-assessment model.',
            'provenance_note' => 'Administrative test exercise; source and teaching review are required before release.',
            'audio_asset_public_id' => $definition['audio_required'] ? $audio : '',
            'rubric' => $definition['rubric_required'] ? 'Polite task completion | Needs revision | Ready for service' : '',
            'items' => $items,
        ];
    }
}

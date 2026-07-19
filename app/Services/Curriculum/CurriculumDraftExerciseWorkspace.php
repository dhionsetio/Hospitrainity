<?php

namespace App\Services\Curriculum;

use App\Enums\CurriculumDraftStatus;
use App\Exceptions\CurriculumDraftConflictException;
use App\Models\CurriculumAsset;
use App\Models\CurriculumDraft;
use App\Models\CurriculumDraftEntity;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class CurriculumDraftExerciseWorkspace
{
    public function __construct(
        private readonly CanonicalExerciseTemplateRegistry $registry,
        private readonly CurriculumDraftLifecycle $lifecycle,
    ) {}

    /** @param array<string, mixed> $data */
    public function create(CurriculumDraft $draft, User $actor, array $data): CurriculumDraftEntity
    {
        if (! in_array((string) ($data['template_type'] ?? ''), $this->registry->enabledTypes(), true)) {
            throw ValidationException::withMessages(['template_type' => 'The selected exercise template is mapped but unavailable.']);
        }

        return DB::transaction(function () use ($draft, $actor, $data): CurriculumDraftEntity {
            $lockedDraft = $this->editableDraft($draft, (int) $data['draft_revision']);
            $section = $this->availableSection($lockedDraft, (string) $data['section_code']);
            $this->assertSectionAvailable($lockedDraft, $section);
            $this->assertCodeAvailable($lockedDraft, (string) $data['code']);
            $activity = $this->createActivity($lockedDraft, $section, $actor, $data);
            $this->syncPrompts($lockedDraft, $activity, $actor, $data, collect());
            $this->syncRubric($lockedDraft, $activity, $actor, $data, null);
            $this->touchDraft($lockedDraft, $actor, 'exercise_created', [
                'activity_code' => $activity->code,
                'template_type' => $data['template_type'],
                'registry_version' => CanonicalExerciseTemplateRegistry::VERSION,
            ]);

            return $activity->fresh();
        }, attempts: 3);
    }

    /** @param array<string, mixed> $data */
    public function update(CurriculumDraft $draft, CurriculumDraftEntity $activity, User $actor, array $data): CurriculumDraftEntity
    {
        return DB::transaction(function () use ($draft, $activity, $actor, $data): CurriculumDraftEntity {
            $lockedDraft = $this->editableDraft($draft, (int) $data['draft_revision']);
            $lockedActivity = $this->lockedActivity($lockedDraft, $activity, (int) $data['activity_revision']);
            $type = (string) ($lockedActivity->payload['template_type'] ?? '');
            if (! hash_equals($type, (string) $data['template_type'])) {
                throw ValidationException::withMessages(['template_type' => 'An exercise template cannot be changed after creation. Duplicate it into another template instead.']);
            }
            if (! hash_equals((string) $lockedActivity->code, (string) $data['code'])) {
                throw ValidationException::withMessages(['code' => 'The stable activity code cannot be changed after creation.']);
            }
            $section = $this->availableSection($lockedDraft, (string) $data['section_code']);
            $this->assertSectionAvailable($lockedDraft, $section, $lockedActivity->id);

            $payload = $this->activityPayload($lockedDraft, $section, $data, $lockedActivity->payload);
            $lockedActivity->forceFill([
                'parent_code' => $section->code,
                'payload' => $payload,
                'revision' => $lockedActivity->revision + 1,
                'updated_by' => $actor->id,
            ])->save();
            $existingPrompts = CurriculumDraftEntity::query()->lockForUpdate()
                ->where('curriculum_draft_id', $lockedDraft->id)->where('entity_type', 'prompt-item')
                ->where('parent_code', $lockedActivity->code)->get();
            $this->syncPrompts($lockedDraft, $lockedActivity, $actor, $data, $existingPrompts);
            $existingRubric = CurriculumDraftEntity::query()->lockForUpdate()
                ->where('curriculum_draft_id', $lockedDraft->id)->where('entity_type', 'rubric')
                ->where('parent_code', $lockedActivity->code)->first();
            $this->syncRubric($lockedDraft, $lockedActivity, $actor, $data, $existingRubric);
            $this->touchDraft($lockedDraft, $actor, 'exercise_updated', [
                'activity_code' => $lockedActivity->code,
                'activity_revision' => $lockedActivity->revision,
                'template_type' => $type,
            ]);

            return $lockedActivity->fresh();
        }, attempts: 3);
    }

    public function duplicate(CurriculumDraft $draft, CurriculumDraftEntity $activity, User $actor, int $expectedDraftRevision, string $sectionCode): CurriculumDraftEntity
    {
        $input = $this->editorData($draft, $activity);
        $input['draft_revision'] = $expectedDraftRevision;
        $input['section_code'] = $sectionCode;
        $input['code'] = $this->copyCode($draft, (string) $activity->code);
        $input['title'] = 'Copy of '.(string) $input['title'];
        $input['provenance_note'] = 'Duplicated from draft exercise '.$activity->code.'; review source alignment before publication.';
        unset($input['activity_revision']);
        foreach ($input['items'] as &$item) {
            unset($item['code']);
        }
        unset($item);

        return $this->create($draft, $actor, $input);
    }

    /** @return array<string, mixed> */
    public function editorData(CurriculumDraft $draft, CurriculumDraftEntity $activity): array
    {
        $this->assertActivityBelongsTo($draft, $activity);
        $activity->refresh();
        $prompts = CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)
            ->where('entity_type', 'prompt-item')->where('parent_code', $activity->code)
            ->whereNull('archived_at')->orderBy('position')->orderBy('id')->get();
        $models = CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)
            ->whereIn('entity_type', ['answer-model', 'feedback-model'])
            ->whereIn('parent_code', $prompts->pluck('code'))->whereNull('archived_at')->get()->groupBy('parent_code');
        $items = $prompts->map(function (CurriculumDraftEntity $prompt) use ($models): array {
            $answer = ($models[$prompt->code] ?? collect())->firstWhere('entity_type', 'answer-model');
            $feedback = ($models[$prompt->code] ?? collect())->firstWhere('entity_type', 'feedback-model');
            $payload = $prompt->payload;
            $answerPayload = $answer?->payload ?? [];
            $choiceById = collect($payload['choices'] ?? [])->keyBy('id');
            $tokenById = collect($payload['tokens'] ?? [])->keyBy('id');
            $correctChoice = $answerPayload['correct_choice_ids'][0] ?? null;

            return [
                'code' => $prompt->code,
                'stem' => $payload['stem'] ?? '',
                'answer' => match ($payload['response_form'] ?? null) {
                    'selection' => $choiceById[$correctChoice]['text'] ?? '',
                    'short_text' => implode("\n", $answerPayload['accepted'] ?? []),
                    default => '',
                },
                'options' => implode("\n", array_column($payload['choices'] ?? [], 'text')),
                'tokens' => implode("\n", array_map(
                    static fn (string $id): string => (string) ($tokenById[$id]['text'] ?? ''),
                    $answerPayload['correct_order'] ?? [],
                )),
                'model_answer' => in_array($payload['response_form'] ?? null, ['role_play', 'service_artifact'], true)
                    ? implode("\n", $answerPayload['accepted'] ?? []) : '',
                'feedback' => implode("\n", array_column($feedback?->payload['messages'] ?? [], 'text')),
            ];
        })->values()->all();
        $rubric = CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)
            ->where('entity_type', 'rubric')->where('parent_code', $activity->code)->whereNull('archived_at')->first();
        $rubricText = implode("\n", array_map(
            static fn (array $criterion): string => implode(' | ', [$criterion['descriptor'], ...$criterion['levels']]),
            $rubric?->payload['criteria'] ?? [],
        ));

        return [
            'draft_revision' => $draft->revision,
            'activity_revision' => $activity->revision,
            'template_type' => $activity->payload['template_type'] ?? '',
            'code' => $activity->code,
            'section_code' => $activity->parent_code,
            'title' => $activity->payload['title'] ?? '',
            'guidance' => $activity->payload['guidance'] ?? '',
            'provenance_note' => $activity->payload['provenance_note'] ?? '',
            'audio_asset_public_id' => $activity->payload['audio_asset']['id'] ?? '',
            'rubric' => $rubricText,
            'items' => $items,
        ];
    }

    /** @param array<string, mixed> $data */
    private function createActivity(CurriculumDraft $draft, CurriculumDraftEntity $section, User $actor, array $data): CurriculumDraftEntity
    {
        $uuid = (string) Str::uuid();

        return CurriculumDraftEntity::create([
            'curriculum_draft_id' => $draft->id,
            'entity_uuid' => $uuid,
            'code' => $data['code'],
            'entity_type' => 'activity',
            'parent_code' => $section->code,
            'position' => 1,
            'source_path' => 'activities/'.$data['code'].'.json',
            'payload' => $this->activityPayload($draft, $section, $data, ['id' => $uuid]),
            'revision' => 1,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
    }

    /** @param array<string, mixed> $data @param array<string, mixed> $existing @return array<string, mixed> */
    private function activityPayload(CurriculumDraft $draft, CurriculumDraftEntity $section, array $data, array $existing): array
    {
        $definition = $this->registry->get((string) $data['template_type']);
        $chapter = CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)
            ->where('entity_type', 'chapter')->where('code', $section->parent_code)->firstOrFail();
        $outcomeCodes = array_values($chapter->payload['outcome_codes'] ?? []);
        if ($outcomeCodes === []) {
            $outcomeCodes = CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)
                ->where('entity_type', 'outcome')->where('payload->module', (int) ($chapter->payload['module'] ?? $chapter->position))
                ->whereNull('archived_at')->orderBy('code')->pluck('code')->all();
        }
        if ($outcomeCodes === []) {
            throw ValidationException::withMessages(['section_code' => 'The selected section has no canonical learning outcome to align with this exercise.']);
        }
        $audio = $this->audioPayload($draft, $data['audio_asset_public_id'] ?? null);
        $text = trim((string) $data['title'].' '.(string) ($data['guidance'] ?? '').' '.(string) $data['provenance_note']);

        return array_replace($existing, [
            'id' => $existing['id'] ?? (string) Str::uuid(),
            'code' => $data['code'],
            'content_version' => $draft->content_version,
            'entity_type' => 'activity',
            'status' => 'draft',
            'lesson_code' => $section->code,
            'title' => trim((string) $data['title']),
            'guidance' => trim((string) ($data['guidance'] ?? '')),
            'cefr_activity' => in_array($definition['response_form'], ['role_play', 'service_artifact'], true) ? 'interaction' : 'reception',
            'channel' => in_array($data['template_type'], ['speaking_practice', 'pronunciation_drill'], true) ? 'spoken_ftf' : 'multimodal_online',
            'timing' => 'asynchronous',
            'participation' => 'individual',
            'pedagogical_function' => 'formative_check',
            'response_form' => $definition['response_form'],
            'response_forms' => [$definition['response_form']],
            'scoring_mode' => $definition['scoring_mode'],
            'accessibility' => $definition['accessibility'],
            'outcome_codes' => $outcomeCodes,
            'completion_rule' => in_array($definition['scoring_mode'], ['model_self_check', 'rubric_self_assessment'], true)
                ? 'all_required_responses_and_self_checks_completed'
                : 'all_required_items_attempted_and_checked',
            'completion_rule_provenance_kind' => 'derived_workflow',
            'template_type' => $data['template_type'],
            'template_registry_version' => CanonicalExerciseTemplateRegistry::VERSION,
            'provenance_kind' => 'admin_authored',
            'provenance_note' => trim((string) $data['provenance_note']),
            'source_locator' => $this->manualLocator($draft, $chapter, $section, $text),
            'audio_asset' => $audio,
            'replaces' => $existing['replaces'] ?? null,
            'replaced_by' => $existing['replaced_by'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $data @param Collection<int, CurriculumDraftEntity> $existingPrompts */
    private function syncPrompts(CurriculumDraft $draft, CurriculumDraftEntity $activity, User $actor, array $data, Collection $existingPrompts): void
    {
        $definition = $this->registry->get((string) $data['template_type']);
        $existingByCode = $existingPrompts->keyBy('code');
        $used = [];
        $derivedChoices = $definition['editor'] === 'derived_selection'
            ? array_values(array_unique(array_map(static fn (array $item): string => trim((string) $item['answer']), $data['items'])))
            : [];
        foreach (array_values($data['items']) as $index => $item) {
            $postedCode = trim((string) ($item['code'] ?? ''));
            $prompt = $postedCode !== '' ? $existingByCode->get($postedCode) : null;
            if ($postedCode !== '' && (! $prompt instanceof CurriculumDraftEntity || $prompt->archived_at !== null)) {
                throw ValidationException::withMessages(["items.{$index}.code" => 'The stable item code is not available in this exercise.']);
            }
            if (! $prompt instanceof CurriculumDraftEntity) {
                $prompt = $this->newPromptEntity($draft, $activity, $actor, $index + 1);
            }
            $used[] = $prompt->id;
            $oldPayload = $prompt->payload;
            [$promptPayload, $answerPayload] = $this->compileItem(
                $draft, $activity, $prompt, $item, $definition, $derivedChoices, $index + 1,
            );
            $prompt->forceFill([
                'position' => $index + 1,
                'payload' => $promptPayload,
                'revision' => $prompt->revision + ($oldPayload === $promptPayload ? 0 : 1),
                'archived_at' => null,
                'updated_by' => $actor->id,
            ])->save();
            $this->upsertModel($draft, $prompt, $actor, 'answer-model', '-AM', $answerPayload);
            $this->upsertModel($draft, $prompt, $actor, 'feedback-model', '-FB', $this->feedbackPayload($draft, $activity, $prompt, (string) $item['feedback']));
        }

        foreach ($existingPrompts->whereNotIn('id', $used) as $removed) {
            $removed->forceFill(['archived_at' => now(), 'revision' => $removed->revision + 1, 'updated_by' => $actor->id])->save();
            CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)
                ->whereIn('entity_type', ['answer-model', 'feedback-model'])->where('parent_code', $removed->code)
                ->whereNull('archived_at')->update(['archived_at' => now(), 'updated_by' => $actor->id]);
        }
    }

    /** @return array{0: array<string, mixed>, 1: array<string, mixed>} */
    private function compileItem(CurriculumDraft $draft, CurriculumDraftEntity $activity, CurriculumDraftEntity $prompt, array $item, array $definition, array $derivedChoices, int $position): array
    {
        $oldPrompt = $prompt->payload;
        $oldAnswer = CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)
            ->where('entity_type', 'answer-model')->where('parent_code', $prompt->code)->first()?->payload ?? [];
        $base = array_replace($oldPrompt, [
            'id' => $oldPrompt['id'] ?? $prompt->entity_uuid,
            'code' => $prompt->code,
            'content_version' => $draft->content_version,
            'entity_type' => 'prompt-item',
            'status' => 'draft',
            'activity_code' => $activity->code,
            'order' => $position,
            'language' => 'en',
            'stem' => trim((string) $item['stem']),
            'response_form' => $definition['response_form'],
            'scoring_mode' => $definition['scoring_mode'],
            'provenance_kind' => 'admin_authored',
            'source_locator' => $activity->payload['source_locator'],
            'replaces' => $oldPrompt['replaces'] ?? null,
            'replaced_by' => $oldPrompt['replaced_by'] ?? null,
        ]);
        $answer = [
            'scoring_mode' => $definition['scoring_mode'],
            'accepted' => [],
        ];
        unset($base['choices'], $base['tokens'], $base['response_constraints'], $base['self_check_required'], $base['audio_asset']);

        if (in_array($definition['editor'], ['explicit_selection', 'derived_selection', 'silent_letter'], true)) {
            $texts = match ($definition['editor']) {
                'explicit_selection' => $this->registry->lines((string) ($item['options'] ?? ''), 10),
                'derived_selection' => $derivedChoices,
                default => $this->registry->graphemes((string) $item['stem']),
            };
            $choices = $this->stableRows($oldPrompt['choices'] ?? [], $texts);
            foreach ($choices as $choiceIndex => &$choice) {
                $choice['label'] = $this->choiceLabel($choiceIndex);
            }
            unset($choice);
            $needle = $this->identity((string) $item['answer']);
            $correct = collect($choices)->first(fn (array $choice): bool => $this->identity($choice['text']) === $needle);
            if (! is_array($correct)) {
                throw ValidationException::withMessages(['items' => 'A correct answer does not reference an allowed option.']);
            }
            $base['choices'] = $choices;
            $answer['accepted'] = [(string) $item['answer']];
            $answer['correct_choice_ids'] = [$correct['id']];
        } elseif ($definition['editor'] === 'ordering') {
            $correctTexts = $this->registry->lines((string) ($item['tokens'] ?? ''), 12);
            $correctRows = $this->stableRows($oldPrompt['tokens'] ?? [], $correctTexts);
            $base['tokens'] = count($correctRows) > 1 ? [...array_slice($correctRows, 1), $correctRows[0]] : $correctRows;
            $answer['accepted'] = [implode(' → ', $correctTexts)];
            $answer['correct_order'] = array_column($correctRows, 'id');
        } elseif ($definition['editor'] === 'closed') {
            $accepted = $this->registry->lines((string) $item['answer'], 8);
            $answer['accepted'] = $accepted;
            $answer['accepted_normalized'] = array_values(array_unique(array_map($this->identity(...), $accepted)));
            $answer['accepted_normalized_provenance_kind'] = 'derived_scoring';
            $base['response_constraints'] = ['minimum_non_whitespace_characters' => 1, 'maximum_characters' => 1000];
        } else {
            $answer['accepted'] = $this->registry->lines((string) $item['model_answer'], 8);
            $base['response_constraints'] = [
                'minimum_non_whitespace_characters' => 1,
                'maximum_characters' => 6000,
                'raw_text_storage' => 'disabled',
                'audio_storage' => 'disabled',
            ];
            $base['self_check_required'] = true;
        }
        if (is_array($activity->payload['audio_asset'] ?? null)) {
            $base['audio_asset'] = $activity->payload['audio_asset'];
        }

        return [$base, array_replace($oldAnswer, $answer)];
    }

    /** @param array<string, mixed> $answerSpecific @return array<string, mixed> */
    private function answerPayload(CurriculumDraft $draft, CurriculumDraftEntity $prompt, array $answerSpecific, array $existing): array
    {
        return array_replace($existing, $answerSpecific, [
            'id' => $existing['id'] ?? (string) Str::uuid(),
            'code' => $prompt->code.'-AM',
            'content_version' => $draft->content_version,
            'entity_type' => 'answer-model',
            'status' => 'draft',
            'prompt_code' => $prompt->code,
            'provenance_kind' => 'admin_authored',
            'source_locator' => $prompt->payload['source_locator'],
            'replaces' => $existing['replaces'] ?? null,
            'replaced_by' => $existing['replaced_by'] ?? null,
        ]);
    }

    /** @return array<string, mixed> */
    private function feedbackPayload(CurriculumDraft $draft, CurriculumDraftEntity $activity, CurriculumDraftEntity $prompt, string $feedback): array
    {
        return [
            'messages' => array_map(static fn (string $line): array => ['feedback_type' => 'next_step', 'text' => $line], $this->registry->lines($feedback, 8)),
            'provenance_kind' => 'admin_authored',
            'source_locator' => $activity->payload['source_locator'],
        ];
    }

    /** @param array<string, mixed> $specific */
    private function upsertModel(CurriculumDraft $draft, CurriculumDraftEntity $prompt, User $actor, string $type, string $suffix, array $specific): void
    {
        $entity = CurriculumDraftEntity::query()->lockForUpdate()->where('curriculum_draft_id', $draft->id)
            ->where('entity_type', $type)->where('parent_code', $prompt->code)->first();
        $code = $prompt->code.$suffix;
        $existing = $entity?->payload ?? [];
        $payload = $type === 'answer-model'
            ? $this->answerPayload($draft, $prompt, $specific, $existing)
            : array_replace($existing, $specific, [
                'id' => $existing['id'] ?? (string) Str::uuid(), 'code' => $code,
                'content_version' => $draft->content_version, 'entity_type' => $type, 'status' => 'draft',
                'prompt_code' => $prompt->code, 'replaces' => $existing['replaces'] ?? null,
                'replaced_by' => $existing['replaced_by'] ?? null,
            ]);
        if ($entity === null) {
            CurriculumDraftEntity::create([
                'curriculum_draft_id' => $draft->id, 'entity_uuid' => $payload['id'], 'code' => $code,
                'entity_type' => $type, 'parent_code' => $prompt->code, 'position' => 1,
                'source_path' => $this->assessmentPath($draft, $prompt, $type, $code),
                'payload' => $payload, 'revision' => 1, 'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
        } else {
            $entity->forceFill([
                'payload' => $payload, 'archived_at' => null,
                'revision' => $entity->revision + ($entity->payload === $payload ? 0 : 1), 'updated_by' => $actor->id,
            ])->save();
        }
    }

    /** @param array<string, mixed> $data */
    private function syncRubric(CurriculumDraft $draft, CurriculumDraftEntity $activity, User $actor, array $data, ?CurriculumDraftEntity $rubric): void
    {
        $rows = $this->registry->rubricRows($data['rubric'] ?? null);
        if ($rows === []) {
            if ($rubric !== null && $rubric->archived_at === null) {
                $rubric->forceFill(['archived_at' => now(), 'revision' => $rubric->revision + 1, 'updated_by' => $actor->id])->save();
            }

            return;
        }
        $code = $activity->code.'-RUBRIC';
        $existing = $rubric?->payload ?? [];
        $criteria = array_map(function (array $row, int $index) use ($activity): array {
            return ['code' => $activity->code.'-CRIT-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT), ...$row];
        }, $rows, array_keys($rows));
        $payload = array_replace($existing, [
            'id' => $existing['id'] ?? (string) Str::uuid(), 'code' => $code,
            'content_version' => $draft->content_version, 'entity_type' => 'rubric', 'status' => 'draft',
            'activity_code' => $activity->code, 'criteria' => $criteria,
            'provenance_kind' => 'admin_authored', 'source_locator' => $activity->payload['source_locator'],
            'replaces' => $existing['replaces'] ?? null, 'replaced_by' => $existing['replaced_by'] ?? null,
        ]);
        if ($rubric === null) {
            CurriculumDraftEntity::create([
                'curriculum_draft_id' => $draft->id, 'entity_uuid' => $payload['id'], 'code' => $code,
                'entity_type' => 'rubric', 'parent_code' => $activity->code, 'position' => 1,
                'source_path' => $this->assessmentPath($draft, $activity, 'rubric', $code),
                'payload' => $payload, 'revision' => 1, 'created_by' => $actor->id, 'updated_by' => $actor->id,
            ]);
        } else {
            $rubric->forceFill([
                'payload' => $payload, 'archived_at' => null,
                'revision' => $rubric->revision + ($rubric->payload === $payload ? 0 : 1), 'updated_by' => $actor->id,
            ])->save();
        }
    }

    private function newPromptEntity(CurriculumDraft $draft, CurriculumDraftEntity $activity, User $actor, int $position): CurriculumDraftEntity
    {
        $code = $this->nextPromptCode($draft, (string) $activity->code);
        $uuid = (string) Str::uuid();

        return CurriculumDraftEntity::create([
            'curriculum_draft_id' => $draft->id, 'entity_uuid' => $uuid, 'code' => $code,
            'entity_type' => 'prompt-item', 'parent_code' => $activity->code, 'position' => $position,
            'source_path' => $this->assessmentPath($draft, $activity, 'prompt-item', $code),
            'payload' => ['id' => $uuid], 'revision' => 1, 'created_by' => $actor->id, 'updated_by' => $actor->id,
        ]);
    }

    /** @param list<array<string, mixed>> $oldRows @param list<string> $texts @return list<array{id: string, text: string}> */
    private function stableRows(array $oldRows, array $texts): array
    {
        $unused = array_values($oldRows);
        $rows = [];
        foreach ($texts as $position => $text) {
            $match = null;
            foreach ($unused as $index => $row) {
                if ($this->identity((string) ($row['text'] ?? '')) === $this->identity($text)) {
                    $match = $row;
                    unset($unused[$index]);
                    break;
                }
            }
            if ($match === null && isset($unused[$position])) {
                $match = $unused[$position];
                unset($unused[$position]);
            }
            $rows[] = ['id' => (string) ($match['id'] ?? Str::uuid()), 'text' => $text];
        }

        return $rows;
    }

    /** @return array<string, mixed>|null */
    private function audioPayload(CurriculumDraft $draft, mixed $publicId): ?array
    {
        if (! is_string($publicId) || $publicId === '') {
            return null;
        }
        $asset = CurriculumAsset::query()->where('curriculum_draft_id', $draft->id)->where('public_id', $publicId)
            ->where('kind', 'audio')->whereNull('archived_at')->with('blob')->firstOrFail();

        return [
            'id' => $asset->public_id, 'kind' => $asset->kind->value, 'display_name' => $asset->display_name,
            'accessibility_text' => $asset->accessibility_text, 'rights_basis' => $asset->rights_basis,
            'source_url' => $asset->source_url, 'sha256' => $asset->blob->sha256,
            'mime_type' => $asset->blob->detected_mime,
            'path' => 'assets/'.$asset->blob->sha256.'.'.$asset->blob->extension,
        ];
    }

    private function editableDraft(CurriculumDraft $draft, int $expectedRevision): CurriculumDraft
    {
        $locked = CurriculumDraft::query()->lockForUpdate()->findOrFail($draft->id);
        $this->lifecycle->assertRevision($locked, $expectedRevision);
        if ($locked->status !== CurriculumDraftStatus::Draft) {
            throw new RuntimeException('Only a draft-status workspace can be edited.');
        }

        return $locked;
    }

    private function lockedActivity(CurriculumDraft $draft, CurriculumDraftEntity $activity, int $expectedRevision): CurriculumDraftEntity
    {
        $locked = CurriculumDraftEntity::query()->lockForUpdate()->where('curriculum_draft_id', $draft->id)
            ->where('entity_type', 'activity')->findOrFail($activity->id);
        if ($locked->revision !== $expectedRevision) {
            throw new CurriculumDraftConflictException($expectedRevision, $locked->revision);
        }
        if (! $this->registry->has((string) ($locked->payload['template_type'] ?? ''))) {
            throw ValidationException::withMessages(['exercise' => 'This imported activity has no editable canonical exercise-template contract.']);
        }

        return $locked;
    }

    private function availableSection(CurriculumDraft $draft, string $code): CurriculumDraftEntity
    {
        return CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)->where('entity_type', 'lesson-section')
            ->where('code', $code)->whereNull('archived_at')->firstOrFail();
    }

    private function assertSectionAvailable(CurriculumDraft $draft, CurriculumDraftEntity $section, ?int $exceptActivityId = null): void
    {
        $query = CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)->where('entity_type', 'activity')
            ->where('parent_code', $section->code)->whereNull('archived_at');
        if ($exceptActivityId !== null) {
            $query->whereKeyNot($exceptActivityId);
        }
        if ($query->exists()) {
            throw ValidationException::withMessages(['section_code' => 'The canonical learner section already has an activity. Choose a section without an activity.']);
        }
    }

    private function assertCodeAvailable(CurriculumDraft $draft, string $code): void
    {
        if (CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'This canonical code is already used in the draft.']);
        }
    }

    private function assertActivityBelongsTo(CurriculumDraft $draft, CurriculumDraftEntity $activity): void
    {
        if ($activity->curriculum_draft_id !== $draft->id || $activity->entity_type !== 'activity') {
            abort(404);
        }
    }

    private function touchDraft(CurriculumDraft $draft, User $actor, string $event, array $metadata): void
    {
        $draft->forceFill(['revision' => $draft->revision + 1, 'updated_by' => $actor->id])->save();
        $this->lifecycle->record($draft, $actor, $event, metadata: $metadata);
    }

    private function nextPromptCode(CurriculumDraft $draft, string $activityCode): string
    {
        for ($index = 1; $index <= 999; $index++) {
            $code = $activityCode.'-I'.str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            if (! CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('No stable prompt code remains available for this exercise.');
    }

    private function copyCode(CurriculumDraft $draft, string $activityCode): string
    {
        for ($index = 1; $index <= 99; $index++) {
            $suffix = $index === 1 ? '-COPY' : '-COPY-'.$index;
            $code = mb_substr($activityCode, 0, 120 - strlen($suffix)).$suffix;
            if (! CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)->where('code', $code)->exists()) {
                return $code;
            }
        }

        throw new RuntimeException('No duplicate activity code remains available.');
    }

    private function assessmentPath(CurriculumDraft $draft, CurriculumDraftEntity $parent, string $type, string $code): string
    {
        $activityCode = $parent->entity_type === 'activity' ? $parent->code : $parent->parent_code;
        $activity = $parent->entity_type === 'activity' ? $parent : CurriculumDraftEntity::query()
            ->where('curriculum_draft_id', $draft->id)->where('entity_type', 'activity')->where('code', $activityCode)->firstOrFail();
        $section = CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)
            ->where('entity_type', 'lesson-section')->where('code', $activity->parent_code)->firstOrFail();
        $directory = match ($type) {
            'prompt-item' => 'prompts', 'answer-model' => 'answers', 'feedback-model' => 'feedback', 'rubric' => 'rubrics',
            default => throw new RuntimeException("Unsupported assessment entity type: {$type}"),
        };

        return 'assessment/'.$section->parent_code.'/'.$directory.'/'.$code.'.json';
    }

    /** @return array<string, mixed> */
    private function manualLocator(CurriculumDraft $draft, CurriculumDraftEntity $chapter, CurriculumDraftEntity $section, string $text): array
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);

        return [
            'artifact' => 'Hospitrainity canonical authoring workspace', 'body_index' => 0,
            'chapter' => (int) ($chapter->payload['module'] ?? $chapter->position ?? 0),
            'heading_path' => [$chapter->payload['title'] ?? $chapter->code, $section->payload['title'] ?? $section->code],
            'normalized_text_sha256' => hash('sha256', $normalized),
        ];
    }

    private function identity(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }

    private function choiceLabel(int $index): string
    {
        return $index < 26 ? chr(65 + $index) : (string) ($index + 1);
    }
}

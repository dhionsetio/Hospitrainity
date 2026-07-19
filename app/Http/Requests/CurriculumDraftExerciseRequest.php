<?php

namespace App\Http\Requests;

use App\Enums\CurriculumAssetKind;
use App\Models\CurriculumAsset;
use App\Models\CurriculumDraft;
use App\Models\CurriculumDraftEntity;
use App\Services\Curriculum\CanonicalExerciseTemplateRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class CurriculumDraftExerciseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $draft = $this->route('curriculumDraft');

        return $draft instanceof CurriculumDraft && $this->user()?->can('update', $draft) === true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        $registry = app(CanonicalExerciseTemplateRegistry::class);

        return [
            'draft_revision' => ['required', 'integer', 'min:1'],
            'activity_revision' => [$this->isMethod('PATCH') ? 'required' : 'nullable', 'integer', 'min:1'],
            'template_type' => ['required', 'string', Rule::in($registry->enabledTypes())],
            'code' => ['required', 'string', 'max:120', 'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/'],
            'section_code' => ['required', 'string', 'max:120', 'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/'],
            'title' => ['required', 'string', 'min:2', 'max:240'],
            'guidance' => ['nullable', 'string', 'max:3000'],
            'provenance_note' => ['required', 'string', 'min:10', 'max:2000'],
            'audio_asset_public_id' => ['nullable', 'uuid'],
            'rubric' => ['nullable', 'string', 'max:6000'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*' => ['required', 'array:code,stem,answer,options,tokens,model_answer,feedback'],
            'items.*.code' => ['nullable', 'string', 'max:120', 'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/'],
            'items.*.stem' => ['required', 'string', 'max:2000'],
            'items.*.answer' => ['nullable', 'string', 'max:4000'],
            'items.*.options' => ['nullable', 'string', 'max:6000'],
            'items.*.tokens' => ['nullable', 'string', 'max:6000'],
            'items.*.model_answer' => ['nullable', 'string', 'max:6000'],
            'items.*.feedback' => ['required', 'string', 'max:3000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $registry = app(CanonicalExerciseTemplateRegistry::class);
            $type = (string) $this->input('template_type');
            $items = array_values($this->input('items', []));
            foreach ($registry->semanticErrors($type, $items, $this->input('rubric')) as $field => $messages) {
                foreach ($messages as $message) {
                    $validator->errors()->add($field, $message);
                }
            }

            $draft = $this->route('curriculumDraft');
            if (! $draft instanceof CurriculumDraft) {
                return;
            }
            $sectionExists = CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)
                ->where('entity_type', 'lesson-section')->where('code', $this->input('section_code'))
                ->whereNull('archived_at')->exists();
            if (! $sectionExists) {
                $validator->errors()->add('section_code', 'The selected lesson section is unavailable in this draft.');
            }

            $definition = $registry->get($type);
            $assetId = $this->input('audio_asset_public_id');
            if ($definition['audio_required'] && (! is_string($assetId) || $assetId === '')) {
                $validator->errors()->add('audio_asset_public_id', 'This template requires a reviewed audio asset and transcript/description.');
            }
            if (is_string($assetId) && $assetId !== '') {
                $audio = CurriculumAsset::query()->where('curriculum_draft_id', $draft->id)
                    ->where('public_id', $assetId)->where('kind', CurriculumAssetKind::Audio->value)
                    ->whereNull('archived_at')->first();
                if ($audio === null) {
                    $validator->errors()->add('audio_asset_public_id', 'The selected audio asset is unavailable in this draft.');
                } elseif ($definition['audio_required']) {
                    $description = ' '.$this->normalizeForLeakCheck($audio->accessibility_text).' ';
                    foreach ($items as $item) {
                        foreach ($registry->lines((string) ($item['answer'] ?? ''), 8) as $answer) {
                            $normalized = $this->normalizeForLeakCheck($answer);
                            if (mb_strlen($normalized) >= 3 && str_contains($description, ' '.$normalized.' ')) {
                                $validator->errors()->add('audio_asset_public_id', 'The audio accessibility description must not reveal an accepted/correct answer before checking.');
                                break 2;
                            }
                        }
                    }
                }
            }

            $rubricLines = $registry->lines($this->input('rubric'), 8);
            if (count($rubricLines) !== count($registry->rubricRows($this->input('rubric')))) {
                $validator->errors()->add('rubric', 'Each rubric row must use: criterion | level 1 | level 2 (up to five levels).');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $exercise = $this->route('draftExercise');
        if ($exercise instanceof CurriculumDraftEntity && ! $this->filled('template_type')) {
            $this->merge(['template_type' => $exercise->payload['template_type'] ?? null]);
        }
        $items = array_values(array_filter(
            is_array($this->input('items')) ? $this->input('items') : [],
            static function (mixed $item): bool {
                if (! is_array($item)) {
                    return false;
                }
                foreach (['stem', 'answer', 'options', 'tokens', 'model_answer', 'feedback'] as $field) {
                    if (trim((string) ($item[$field] ?? '')) !== '') {
                        return true;
                    }
                }

                return false;
            },
        ));
        $this->merge(['items' => $items]);
    }

    private function normalizeForLeakCheck(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}

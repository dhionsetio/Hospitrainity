<?php

namespace App\Http\Requests;

use App\Enums\CurriculumDraftEntityType;
use App\Models\CurriculumDraft;
use App\Models\CurriculumDraftEntity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCurriculumDraftEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('curriculumDraft')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'draft_revision' => ['required', 'integer', 'min:1'],
            'entity_type' => ['required', Rule::enum(CurriculumDraftEntityType::class)],
            'code' => ['required', 'string', 'regex:/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/', 'max:120'],
            'title' => ['required_unless:entity_type,outcome', 'nullable', 'string', 'max:240'],
            'parent_code' => ['required_if:entity_type,lesson-section', 'nullable', 'string', 'max:120'],
            'position' => ['required', 'integer', 'min:1', 'max:999'],
            'module' => ['required_if:entity_type,outcome', 'nullable', 'integer', 'min:1', 'max:7'],
            'statement' => ['required_if:entity_type,outcome', 'nullable', 'string', 'max:2000'],
            'outcome_type' => ['required_if:entity_type,outcome', 'nullable', Rule::in(['knowledge', 'performance', 'reflection'])],
            'provisional_band' => ['required_if:entity_type,outcome', 'nullable', 'string', 'max:30'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var CurriculumDraft|null $draft */
            $draft = $this->route('curriculumDraft');
            if ($draft === null) {
                return;
            }
            if (CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)->where('code', strtoupper((string) $this->input('code')))->exists()) {
                $validator->errors()->add('code', __('admin.draft_code_must_be_unique'));
            }
            if ($this->input('entity_type') === CurriculumDraftEntityType::Section->value
                && ! CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)->where('entity_type', 'chapter')->where('code', $this->input('parent_code'))->whereNull('archived_at')->exists()) {
                $validator->errors()->add('parent_code', __('admin.available_parent_chapter_required'));
            }
        }];
    }
}

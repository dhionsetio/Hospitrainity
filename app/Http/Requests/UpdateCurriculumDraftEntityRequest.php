<?php

namespace App\Http\Requests;

use App\Models\CurriculumDraftEntity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCurriculumDraftEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('curriculumDraft')) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $entity = $this->route('draftEntity');
        $type = $entity?->entity_type;

        return [
            'draft_revision' => ['required', 'integer', 'min:1'],
            'entity_revision' => ['required', 'integer', 'min:1'],
            'title' => [in_array($type, ['chapter', 'lesson-section'], true) ? 'required' : 'nullable', 'string', 'max:240'],
            'parent_code' => [$type === 'lesson-section' ? 'required' : 'nullable', 'string', 'max:120'],
            'position' => ['required', 'integer', 'min:1', 'max:999'],
            'module' => [$type === 'outcome' ? 'required' : 'nullable', 'integer', 'min:1', 'max:7'],
            'statement' => [$type === 'outcome' ? 'required' : 'nullable', 'string', 'max:2000'],
            'outcome_type' => [$type === 'outcome' ? 'required' : 'nullable', Rule::in(['knowledge', 'performance', 'reflection'])],
            'provisional_band' => [$type === 'outcome' ? 'required' : 'nullable', 'string', 'max:30'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $draft = $this->route('curriculumDraft');
            $entity = $this->route('draftEntity');
            if ($draft === null || $entity?->entity_type !== 'lesson-section') {
                return;
            }
            if (! CurriculumDraftEntity::query()->where('curriculum_draft_id', $draft->id)
                ->where('entity_type', 'chapter')->where('code', $this->input('parent_code'))->whereNull('archived_at')->exists()) {
                $validator->errors()->add('parent_code', __('admin.available_parent_chapter_required'));
            }
        }];
    }
}

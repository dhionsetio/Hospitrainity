<?php

namespace App\Http\Requests;

use App\Models\CurriculumDraft;
use App\Models\CurriculumPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCurriculumDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CurriculumDraft::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:160'],
            'content_version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+$/', 'max:50', Rule::unique('curriculum_drafts', 'content_version')->where('package_name', 'hospitrainity')],
            'source' => ['required', Rule::in(['clone', 'empty'])],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (CurriculumPackage::query()->where('package_name', 'hospitrainity')->where('content_version', $this->input('content_version'))->exists()) {
                $validator->errors()->add('content_version', __('admin.content_version_must_be_new'));
            }
        }];
    }
}

<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesClassSettings;
use App\Services\InstitutionContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreClassWithCourseRequest extends FormRequest
{
    use ValidatesClassSettings;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'course_key' => Str::slug((string) $this->input('course_key')),
            'class_key' => Str::slug((string) $this->input('class_key')),
            'timezone' => $this->input('timezone') ?: null,
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $institution = app(InstitutionContext::class)->current($this, $this->user());

        return [
            'course_key' => [
                'required', 'string', 'max:100', 'alpha_dash:ascii',
                Rule::unique('courses', 'key')->where('institution_id', $institution->getKey()),
            ],
            'course_title' => ['required', 'string', 'max:180'],
            'course_description' => ['nullable', 'string', 'max:2000'],
            'curriculum_package_id' => ['required', 'integer', 'exists:curriculum_packages,id'],
            'revision_title' => ['required', 'string', 'max:180'],
            'module_ids' => ['required', 'array', 'min:1', 'max:100'],
            'module_ids.*' => ['required', 'integer', 'distinct', 'exists:curriculum_entities,id'],
            'class_key' => $this->classKeyRules(),
            'class_title' => ['required', 'string', 'max:180'],
            'term_label' => ['nullable', 'string', 'max:120'],
            'timezone' => $this->timezoneRules(),
            'primary_instructor_membership_id' => ['required', 'integer', 'exists:institution_memberships,id'],
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Enums\WorkContextRole;
use App\Services\WorkContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLearnerTextResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && app(WorkContext::class)->current($this, $this->user()) === WorkContextRole::Learner;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'response_key' => ['required', 'uuid'],
            'kind' => ['required', Rule::in(['assessment', 'journal'])],
            'intent' => ['required', Rule::in(['save', 'submit'])],
            'body' => [
                'required',
                'string',
                'max:'.(int) config('course_assistant.maximum_saved_response_characters', 6000),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && trim($value) === '') {
                        $fail(__('responses.validation.body_required'));
                    }
                },
            ],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        $this->forgetBodyInput();

        parent::failedValidation($validator);
    }

    protected function passedValidation(): void
    {
        $this->forgetBodyInput();
    }

    private function forgetBodyInput(): void
    {
        $this->offsetUnset('body');
        $this->container->make('request')->offsetUnset('body');
    }
}

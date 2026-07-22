<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

abstract class CanonicalAttemptRequest extends FormRequest
{
    /**
     * Resolve the authoritative activity/prompt contract for this request.
     * Active delivery and draft preview provide different storage lookups but
     * intentionally share every validation rule below.
     *
     * @return array<string, mixed>
     */
    abstract public function definition(): array;

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $definition = $this->definition();
        $promptCodes = array_keys($definition['prompts']);
        $openCodes = [];
        $checking = fn (): bool => $this->input('intent') === 'check';
        $rules = [
            'attempt_key' => ['required', 'uuid'],
            'intent' => ['required', Rule::in(['check', 'show_model', 'skip_baseline'])],
            'responses' => ['nullable', 'array:'.implode(',', $promptCodes)],
            'self_checks' => ['nullable', 'array'],
        ];

        foreach ($definition['prompts'] as $code => $prompt) {
            $required = Rule::requiredIf($checking);
            $form = $prompt['payload']['response_form'];
            if ($form === 'selection') {
                $rules["responses.{$code}"] = [$required, 'string', Rule::in(array_column($prompt['payload']['choices'], 'id'))];
            } elseif ($form === 'ordering') {
                $tokens = array_column($prompt['payload']['tokens'], 'id');
                $rules["responses.{$code}"] = [$required, 'array', 'size:'.count($tokens)];
                $rules["responses.{$code}.*"] = ['string', 'distinct:strict', Rule::in($tokens)];
            } elseif ($form === 'rating') {
                $rules["responses.{$code}"] = [$required, 'integer', 'between:1,5'];
            } else {
                $maximum = (int) ($prompt['payload']['response_constraints']['maximum_characters'] ?? 1000);
                $rules["responses.{$code}"] = [$required, 'string', 'max:'.$maximum, function (string $attribute, mixed $value, \Closure $fail): void {
                    if (is_string($value) && trim($value) === '') {
                        $fail('Please enter a response before checking this activity.');
                    }
                }];
                if (($prompt['payload']['self_check_required'] ?? false) === true) {
                    $openCodes[] = $code;
                    $rules["self_checks.{$code}"] = [Rule::requiredIf($checking), 'accepted'];
                }
            }
        }
        $rules['self_checks'] = ['nullable', 'array:'.implode(',', $openCodes)];

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $allowed = ['_token', 'attempt_key', 'intent', 'responses', 'self_checks'];
            $unexpected = array_values(array_diff(array_keys($this->all()), $allowed));
            if ($unexpected !== []) {
                $validator->errors()->add('request', 'Unexpected request fields: '.implode(', ', $unexpected).'.');
            }
            $intent = $this->input('intent');
            $responses = $this->input('responses', []);
            if ($intent === 'show_model' && is_array($responses) && $responses !== []) {
                $validator->errors()->add('responses', 'Choose either to check responses or to show the model without answering.');
            }
            $selfChecks = $this->input('self_checks', []);
            if ($intent === 'show_model' && is_array($selfChecks) && $selfChecks !== []) {
                $validator->errors()->add('self_checks', 'Self-check confirmations only apply when checking submitted responses.');
            }
            $activity = $this->definition()['activity'];
            if ($intent === 'skip_baseline' && $activity['code'] !== 'HSP-C01-ACT-BASELINE') {
                $validator->errors()->add('intent', 'Only the Chapter 1 baseline may be explicitly skipped.');
            }
        }];
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        $this->forgetOpenResponseInput();

        parent::failedValidation($validator);
    }

    private function forgetOpenResponseInput(): void
    {
        $openResponseCodes = collect($this->definition()['prompts'])
            ->reject(static fn (array $prompt): bool => in_array(
                $prompt['payload']['response_form'] ?? null,
                ['selection', 'ordering', 'rating'],
                true,
            ))
            ->keys()
            ->all();

        foreach ([$this, $this->container->make('request')] as $request) {
            $responses = $request->input('responses');

            if (! is_array($responses)) {
                $request->offsetUnset('responses');

                continue;
            }

            foreach ($openResponseCodes as $code) {
                unset($responses[$code]);
            }

            if ($responses === []) {
                $request->offsetUnset('responses');
            } else {
                $request->merge(['responses' => $responses]);
            }
        }
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        $attributes = [];

        foreach ($this->definition()['prompts'] as $code => $prompt) {
            $attributes["responses.{$code}"] = "response to prompt {$code}";
            $attributes["responses.{$code}.*"] = "ordered item for prompt {$code}";
            $attributes["self_checks.{$code}"] = "self-check for prompt {$code}";
        }

        return $attributes;
    }
}

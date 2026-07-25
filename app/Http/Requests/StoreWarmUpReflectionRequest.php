<?php

namespace App\Http\Requests;

use App\Enums\WorkContextRole;
use App\Services\WorkContext;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class StoreWarmUpReflectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null
            && app(WorkContext::class)->current($this, $this->user()) === WorkContextRole::Learner;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $maxBodyChars = (int) config('learning_reflection.max_body_characters', 6000);
        $maxAttachmentKib = (int) config('learning_reflection.max_attachment_kib', 51200);
        $maxAttachments = (int) config('learning_reflection.max_attachments_per_reflection', 3);

        return [
            'response_key' => ['required', 'uuid'],
            'intent' => ['required', Rule::in(['save', 'submit'])],
            'section_code' => ['required', 'string', 'max:64', 'regex:/\A[A-Za-z0-9._-]+\z/'],
            'prompt_index' => ['required', 'integer', 'min:0', 'max:50'],
            'body' => ['nullable', 'string', "max:{$maxBodyChars}"],
            'attachments' => ['nullable', 'array', "max:{$maxAttachments}"],
            'attachments.*' => [
                'file',
                "max:{$maxAttachmentKib}",
                File::types([
                    'webm', 'ogg', 'oga', 'mp3', 'm4a', 'mp4a', 'wav', 'mp4',
                ]),
            ],
            'recording_kind' => ['nullable', 'string', Rule::in(['voice_recording', 'audio_file', 'video_file'])],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $body = $this->input('body');
            $hasBody = is_string($body) && trim($body) !== '';

            $files = $this->file('attachments');
            $hasAttachments = is_array($files) && count(array_filter($files)) > 0;

            if (! $hasBody && ! $hasAttachments) {
                $validator->errors()->add(
                    'body',
                    __('reflections.validation.reflection_empty')
                );
            }

            if ($this->input('intent') === 'submit' && ! $hasBody && ! $hasAttachments) {
                $validator->errors()->add(
                    'intent',
                    __('reflections.validation.cannot_submit_empty')
                );
            }
        });
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

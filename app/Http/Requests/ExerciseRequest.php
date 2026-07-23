<?php

namespace App\Http\Requests;

use App\Models\Exercise;
use App\Rules\PublicAudioUrl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Shared validation for creating and updating exercises.
 *
 * These bounded rules mirror the exact shapes consumed by the learner engine
 * and prevent malformed or unexpectedly large JSON payloads from reaching it.
 */
abstract class ExerciseRequest extends FormRequest
{
    /**
     * The 14 exercise types the engine knows how to render. Keep in sync with the
     * RENDERERS registry in resources/js/exercises/index.js.
     */
    public const TYPES = [
        'spelling_quiz',
        'matching_game',
        'fill_in_the_blank',
        'listening_task',
        'speaking_practice',
        'sentence_scramble',
        'translation_match',
        'fill_with_options',
        'multiple_choice_quiz',
        'fill_multiple_blanks',
        'silent_letter_hunt',
        'pronunciation_drill',
        'sound_sorting',
        'sequencing',
        'information',
        'writing',
        'drag_the_words',
        'drag_and_drop',
    ];

    public function authorize(): bool
    {
        $exercise = $this->route('exercise');

        return $exercise
            ? $this->user()?->can('update', $exercise) === true
            : $this->user()?->can('create', Exercise::class) === true;
    }

    /**
     * The effective exercise type for this request. On update the type <select>
     * is disabled in the admin UI (a disabled control is not submitted), so fall
     * back to the existing model's type to keep per-type content rules applying.
     */
    protected function effectiveType(): ?string
    {
        $type = $this->input('type');
        if (is_string($type) && $type !== '') {
            return $type;
        }

        $exercise = $this->route('exercise');

        return $exercise?->type;
    }

    /** Rules shared by store + update (the `type` rule differs per subclass). */
    protected function baseRules(): array
    {
        return [
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
            'title' => ['required', 'string', 'max:255'],
            'order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
        ];
    }

    /**
     * Per-type structural rules for the JSON `content` payload.
     */
    protected function contentRules(?string $type): array
    {
        return match ($type) {
            'spelling_quiz' => [
                'content' => ['required', 'array:correct_answer,prompt_text,audio_url'],
                'content.correct_answer' => ['required', 'string', 'max:1000'],
                'content.prompt_text' => ['required', 'string', 'max:1000'],
                'content.audio_url' => ['sometimes', 'nullable', 'string', 'max:2048', new PublicAudioUrl],
            ],
            'matching_game' => [
                'content' => ['required', 'array:pairs'],
                'content.pairs' => ['required', 'array', 'list', 'min:1', 'max:100'],
                'content.pairs.*' => ['array:question,answer'],
                'content.pairs.*.question' => ['required', 'string', 'max:1000'],
                'content.pairs.*.answer' => ['required', 'string', 'max:1000'],
            ],
            'fill_in_the_blank' => [
                'content' => ['required', 'array:correct_answer,sentence_parts,sentence_template'],
                'content.correct_answer' => ['required', 'string', 'max:1000'],
                // Renderer + seeders use sentence_parts; the admin form still posts
                // sentence_template. Accept either (the engine normalizes it).
                'content.sentence_parts' => ['required_without:content.sentence_template', 'array', 'list', 'size:2'],
                'content.sentence_parts.*' => ['string', 'max:5000'],
                'content.sentence_template' => ['required_without:content.sentence_parts', 'string', 'max:10000'],
            ],
            'listening_task' => [
                'content' => ['required', 'array:correct_answer,options,instruction'],
                'content.correct_answer' => ['required', 'string', 'max:1000'],
                'content.options' => ['required', 'array', 'list', 'min:2', 'max:100'],
                'content.options.*' => ['required', 'string', 'max:1000'],
                'content.instruction' => ['sometimes', 'nullable', 'string', 'max:5000'],
            ],
            'speaking_practice', 'pronunciation_drill' => [
                'content' => ['required', 'array:prompt_text'],
                'content.prompt_text' => ['required', 'string', 'max:5000'],
            ],
            'sentence_scramble' => [
                'content' => ['required', 'array:sentence'],
                'content.sentence' => ['required', 'string', 'max:5000'],
            ],
            'translation_match' => [
                'content' => ['required', 'array:question_word,correct_answer,options'],
                'content.question_word' => ['required', 'string', 'max:1000'],
                'content.correct_answer' => ['required', 'string', 'max:1000'],
                'content.options' => ['required', 'array', 'list', 'min:2', 'max:100'],
                'content.options.*' => ['required', 'string', 'max:1000'],
            ],
            'fill_with_options' => [
                'content' => ['required', 'array:correct_answer,sentence_parts,options'],
                'content.correct_answer' => ['required', 'string', 'max:1000'],
                'content.sentence_parts' => ['required', 'array', 'list', 'size:2'],
                'content.sentence_parts.*' => ['string', 'max:5000'],
                'content.options' => ['required', 'array', 'list', 'min:2', 'max:100'],
                'content.options.*' => ['required', 'string', 'max:1000'],
            ],
            'multiple_choice_quiz' => [
                'content' => ['required', 'array:question_text,correct_answer,options'],
                'content.question_text' => ['required', 'string', 'max:5000'],
                'content.correct_answer' => ['required', 'string', 'max:1000'],
                'content.options' => ['required', 'array', 'list', 'min:2', 'max:100'],
                'content.options.*' => ['required', 'string', 'max:1000'],
            ],
            'fill_multiple_blanks' => [
                'content' => ['required', 'array:sentence_parts,correct_answers'],
                'content.sentence_parts' => ['required', 'array', 'list', 'min:2', 'max:101'],
                'content.sentence_parts.*' => ['string', 'max:5000'],
                'content.correct_answers' => ['required', 'array', 'list', 'min:1', 'max:100'],
                'content.correct_answers.*' => ['required', 'string', 'max:1000'],
            ],
            'silent_letter_hunt' => [
                'content' => ['required', 'array:sentence,words'],
                'content.sentence' => ['required', 'string', 'max:5000'],
                'content.words' => ['required', 'array', 'list', 'min:1', 'max:100'],
                'content.words.*' => ['array:word,silent_letter_index'],
                'content.words.*.word' => ['required', 'string', 'max:1000'],
                'content.words.*.silent_letter_index' => ['required', 'integer', 'min:0'],
            ],
            'sound_sorting' => [
                'content' => ['required', 'array:categories,words'],
                'content.categories' => ['required', 'array', 'list', 'min:1', 'max:100'],
                'content.categories.*' => ['array:id,name'],
                'content.categories.*.id' => ['required', 'string', 'max:255'],
                'content.categories.*.name' => ['required', 'string', 'max:1000'],
                'content.words' => ['required', 'array', 'list', 'min:1', 'max:100'],
                'content.words.*' => ['array:word,category_id'],
                'content.words.*.word' => ['required', 'string', 'max:1000'],
                'content.words.*.category_id' => ['required', 'string', 'max:255'],
            ],
            'sequencing' => [
                'content' => ['required', 'array:steps'],
                'content.steps' => ['required', 'array', 'list', 'min:2', 'max:100'],
                'content.steps.*' => ['required', 'string', 'max:5000'],
            ],
            'information' => [
                'content' => ['required', 'array:body,media_url,media_type'],
                'content.body' => ['required', 'string', 'max:10000'],
                'content.media_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
                'content.media_type' => ['sometimes', 'nullable', 'string', Rule::in(['image', 'video'])],
            ],
            'writing' => [
                'content' => ['required', 'array:prompt,min_words,max_words,keywords,model_answer,accept_spelling_errors'],
                'content.prompt' => ['required', 'string', 'max:5000'],
                'content.min_words' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
                'content.max_words' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10000'],
                'content.keywords' => ['required', 'array', 'list', 'min:1', 'max:50'],
                'content.keywords.*' => ['array:text,weight,required,case_sensitive'],
                'content.keywords.*.text' => ['required', 'string', 'max:255'],
                'content.keywords.*.weight' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10'],
                'content.keywords.*.required' => ['sometimes', 'nullable', 'boolean'],
                'content.keywords.*.case_sensitive' => ['sometimes', 'nullable', 'boolean'],
                'content.model_answer' => ['sometimes', 'nullable', 'string', 'max:10000'],
                'content.accept_spelling_errors' => ['sometimes', 'nullable', 'boolean'],
            ],
            'drag_the_words' => [
                'content' => ['required', 'array:text,distractors,show_solution,instant_feedback'],
                'content.text' => ['required', 'string', 'max:10000'],
                'content.distractors' => ['sometimes', 'nullable', 'array', 'list', 'max:20'],
                'content.distractors.*' => ['required', 'string', 'max:1000'],
                'content.show_solution' => ['sometimes', 'nullable', 'boolean'],
                'content.instant_feedback' => ['sometimes', 'nullable', 'boolean'],
            ],
            'drag_and_drop' => [
                'content' => ['required', 'array:background_image,draggables,drop_zones,single_point,show_solution'],
                'content.background_image' => ['sometimes', 'nullable', 'string', 'max:2048'],
                'content.draggables' => ['required', 'array', 'list', 'min:1', 'max:50'],
                'content.draggables.*' => ['array:id,label,image,multiple'],
                'content.draggables.*.id' => ['required', 'string', 'max:255'],
                'content.draggables.*.label' => ['required', 'string', 'max:1000'],
                'content.draggables.*.image' => ['sometimes', 'nullable', 'string', 'max:2048'],
                'content.draggables.*.multiple' => ['sometimes', 'nullable', 'boolean'],
                'content.drop_zones' => ['required', 'array', 'list', 'min:1', 'max:50'],
                'content.drop_zones.*' => ['array:id,label,x,y,width,height,single,correct_draggable_ids'],
                'content.drop_zones.*.id' => ['required', 'string', 'max:255'],
                'content.drop_zones.*.label' => ['required', 'string', 'max:1000'],
                'content.drop_zones.*.x' => ['required', 'integer', 'min:0'],
                'content.drop_zones.*.y' => ['required', 'integer', 'min:0'],
                'content.drop_zones.*.width' => ['required', 'integer', 'min:1'],
                'content.drop_zones.*.height' => ['required', 'integer', 'min:1'],
                'content.drop_zones.*.single' => ['sometimes', 'nullable', 'boolean'],
                'content.drop_zones.*.correct_draggable_ids' => ['required', 'array', 'list', 'min:1'],
                'content.drop_zones.*.correct_draggable_ids.*' => ['required', 'string', 'max:255'],
                'content.single_point' => ['sometimes', 'nullable', 'boolean'],
                'content.show_solution' => ['sometimes', 'nullable', 'boolean'],
            ],
            default => [],
        };
    }

    public function messages(): array
    {
        return [
            'type.in' => 'The selected exercise type is not supported.',
            'content.required' => 'The exercise content is required.',
            'content.array' => 'The exercise content is malformed.',
        ];
    }

    /** @return array<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $content = $this->input('content', []);
            if (! is_array($content)) {
                return;
            }

            $type = $this->effectiveType();
            if (in_array($type, ['listening_task', 'translation_match', 'fill_with_options', 'multiple_choice_quiz'], true)) {
                $answer = $content['correct_answer'] ?? null;
                $options = $content['options'] ?? [];
                if (is_string($answer) && is_array($options) && ! in_array($answer, $options, true)) {
                    $validator->errors()->add('content.correct_answer', 'The correct answer must be one of the available options.');
                }
            }

            if ($type === 'sound_sorting') {
                $categoryIds = collect($content['categories'] ?? [])->pluck('id')->filter()->all();
                if (count($categoryIds) !== count(array_unique($categoryIds))) {
                    $validator->errors()->add('content.categories', 'Category IDs must be unique.');
                }

                foreach ($content['words'] ?? [] as $index => $word) {
                    if (is_array($word) && ! in_array($word['category_id'] ?? null, $categoryIds, true)) {
                        $validator->errors()->add("content.words.{$index}.category_id", 'Each word must reference an available category.');
                    }
                }
            }

            if ($type === 'fill_multiple_blanks') {
                $partCount = count((array) ($content['sentence_parts'] ?? []));
                $answerCount = count((array) ($content['correct_answers'] ?? []));
                if ($partCount !== $answerCount + 1) {
                    $validator->errors()->add(
                        'content.sentence_parts',
                        'Sentence parts must contain exactly one more entry than the correct answers.',
                    );
                }
            }

            if ($type === 'silent_letter_hunt') {
                foreach ($content['words'] ?? [] as $index => $word) {
                    if (! is_array($word) || ! is_string($word['word'] ?? null) || ! is_numeric($word['silent_letter_index'] ?? null)) {
                        continue;
                    }

                    if ((int) $word['silent_letter_index'] >= mb_strlen($word['word'])) {
                        $validator->errors()->add(
                            "content.words.{$index}.silent_letter_index",
                            'The silent-letter index must point to a character in the word.',
                        );
                    }
                }
            }

            if ($type === 'drag_the_words') {
                $text = $content['text'] ?? '';
                if (! is_string($text) || ! preg_match('/\*[^*]+\*/', $text)) {
                    $validator->errors()->add('content.text', 'The text must contain at least one *word* marker for a draggable position.');
                }
            }

            if ($type === 'drag_and_drop') {
                $draggableIds = collect($content['draggables'] ?? [])->pluck('id')->filter()->all();
                if (count($draggableIds) !== count(array_unique($draggableIds))) {
                    $validator->errors()->add('content.draggables', 'Draggable IDs must be unique.');
                }

                $dropZoneIds = collect($content['drop_zones'] ?? [])->pluck('id')->filter()->all();
                if (count($dropZoneIds) !== count(array_unique($dropZoneIds))) {
                    $validator->errors()->add('content.drop_zones', 'Drop zone IDs must be unique.');
                }

                foreach ($content['drop_zones'] ?? [] as $zIdx => $zone) {
                    if (! is_array($zone) || ! is_array($zone['correct_draggable_ids'] ?? null)) {
                        continue;
                    }
                    foreach ($zone['correct_draggable_ids'] as $cId) {
                        if (! in_array($cId, $draggableIds, true)) {
                            $validator->errors()->add(
                                "content.drop_zones.{$zIdx}.correct_draggable_ids",
                                "Target draggable '{$cId}' does not exist.",
                            );
                        }
                    }
                }
            }
        }];
    }
}

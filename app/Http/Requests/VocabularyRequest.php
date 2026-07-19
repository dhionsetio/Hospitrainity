<?php

namespace App\Http\Requests;

use App\Models\Vocabulary;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

abstract class VocabularyRequest extends AdminFormRequest
{
    abstract protected function isUpdate(): bool;

    public function rules(): array
    {
        $itemKeys = $this->isUpdate() ? 'array:id,term,details,media,order' : 'array:term,details,media,order';
        $idRules = $this->isUpdate()
            ? $this->updateIdRules()
            : ['prohibited'];

        return [
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
            'category' => ['required', 'string', 'max:255'],
            'order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'items.*' => [$itemKeys],
            'items.*.id' => $idRules,
            'items.*.term' => ['required', 'string', 'max:255'],
            'items.*.details' => ['nullable', 'string', 'max:5000'],
            'items.*.order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'items.*.media' => ['nullable', File::types(['mp3', 'wav', 'mp4'])->max('5mb')],
        ];
    }

    private function updateIdRules(): array
    {
        /** @var Vocabulary $vocabulary */
        $vocabulary = $this->route('vocabulary');

        return [
            'nullable',
            'integer',
            'distinct',
            Rule::exists('vocabulary_items', 'id')
                ->where('vocabulary_id', $vocabulary->getKey()),
        ];
    }
}

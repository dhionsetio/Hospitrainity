<?php

namespace App\Http\Requests;

use App\Models\Material;
use App\Rules\YouTubeUrl;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

abstract class MaterialRequest extends AdminFormRequest
{
    abstract protected function isUpdate(): bool;

    protected function prepareForValidation(): void
    {
        if ($this->effectiveType() !== 'Video') {
            return;
        }

        $items = $this->input('items', []);
        if (! is_array($items)) {
            return;
        }

        foreach ($items as &$item) {
            if (is_array($item) && is_string($item['url'] ?? null)) {
                $item['url'] = YouTubeUrl::canonicalize($item['url']) ?? $item['url'];
            }
        }
        unset($item);

        $this->merge(['items' => $items]);
    }

    public function rules(): array
    {
        $material = $this->route('material');
        $type = $this->effectiveType();
        $itemKeys = $this->isUpdate()
            ? 'array:id,title,description,file,audio_file,url,order'
            : 'array:title,description,file,audio_file,url,order';

        $rules = [
            'lesson_id' => ['required', 'integer', 'exists:lessons,id'],
            'type' => $this->isUpdate()
                ? ['sometimes', Rule::in([$material->type])]
                : ['required', Rule::in(Material::TYPES)],
            'order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
            'items' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'items.*' => [$itemKeys],
            'items.*.id' => $this->isUpdate() ? $this->updateIdRules($material) : ['prohibited'],
            'items.*.title' => ['nullable', 'string', 'max:255'],
            'items.*.description' => ['required', 'string', 'max:10000'],
            'items.*.order' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:1000000'],
        ];

        $existingUrls = $this->existingItemUrls($material);
        foreach (array_keys((array) $this->input('items', [])) as $index) {
            $itemId = $this->input("items.{$index}.id");
            $hasExistingPrimaryFile = $itemId !== null && isset($existingUrls[(int) $itemId]);

            $rules["items.{$index}.file"] = $this->primaryFileRules($type, $hasExistingPrimaryFile);
            $rules["items.{$index}.audio_file"] = $this->audioFileRules($type);
            $rules["items.{$index}.url"] = $type === 'Video'
                ? ['required', 'string', new YouTubeUrl]
                : ['prohibited'];
        }

        return $rules;
    }

    protected function effectiveType(): ?string
    {
        if ($this->isUpdate()) {
            return $this->route('material')?->type;
        }

        return is_string($this->input('type')) ? $this->input('type') : null;
    }

    private function updateIdRules(Material $material): array
    {
        return [
            'nullable',
            'integer',
            'distinct',
            Rule::exists('material_items', 'id')->where('material_id', $material->getKey()),
        ];
    }

    /** @return array<int, string> */
    private function existingItemUrls(?Material $material): array
    {
        if (! $this->isUpdate() || $material === null) {
            return [];
        }

        return $material->items()
            ->whereNotNull('url')
            ->pluck('url', 'id')
            ->all();
    }

    private function primaryFileRules(?string $type, bool $hasExistingFile): array
    {
        return match ($type) {
            'Gambar' => [
                $hasExistingFile ? 'nullable' : 'required',
                File::image()->max('5mb'),
            ],
            'Audio' => [
                $hasExistingFile ? 'nullable' : 'required',
                File::types(['mp3', 'wav'])->max('5mb'),
            ],
            default => ['prohibited'],
        };
    }

    private function audioFileRules(?string $type): array
    {
        return in_array($type, ['Teks', 'Gambar'], true)
            ? ['nullable', File::types(['mp3', 'wav'])->max('5mb')]
            : ['prohibited'];
    }
}

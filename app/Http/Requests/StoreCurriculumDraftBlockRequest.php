<?php

namespace App\Http\Requests;

use App\Enums\CurriculumBlockType;
use App\Models\CurriculumDraftEntity;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCurriculumDraftBlockRequest extends FormRequest
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
            'entity_revision' => ['required', 'integer', 'min:1'],
            'block_type' => ['required', Rule::enum(CurriculumBlockType::class)],
            'text' => ['nullable', 'string', 'max:10000'],
            'speaker' => ['nullable', 'string', 'max:160'],
            'caption' => ['nullable', 'string', 'max:500'],
            'table_header' => ['nullable', 'string', 'max:5000'],
            'table_rows' => ['nullable', 'string', 'max:30000'],
            'link_text' => ['nullable', 'array', 'max:10'],
            'link_text.*' => ['nullable', 'string', 'max:500'],
            'link_target' => ['nullable', 'array', 'max:10'],
            'link_target.*' => ['nullable', 'url:http,https', 'max:2048'],
            'activity_code' => ['nullable', 'string', 'max:120'],
            'asset_public_id' => ['nullable', 'uuid'],
            'provenance_note' => ['nullable', 'string', 'min:3', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $payload = $this->richPayload();
            $typeValue = $this->input('block_type') ?? $this->route('draftBlock')?->block_type;
            $type = CurriculumBlockType::tryFrom((string) $typeValue);
            if ($type === null) {
                return;
            }
            if (trim((string) $this->input('provenance_note')) === '') {
                $validator->errors()->add('provenance_note', __('admin.provenance_note_required'));
            }
            $nonEmptyString = static fn (mixed $value): bool => is_string($value) && trim($value) !== '';
            if (in_array($type, [CurriculumBlockType::Paragraph, CurriculumBlockType::Heading, CurriculumBlockType::Callout, CurriculumBlockType::ListItem, CurriculumBlockType::Instruction], true)
                && ! $nonEmptyString($payload['text'] ?? null)) {
                $validator->errors()->add('text', __('admin.block_text_required'));
            }
            if ($type === CurriculumBlockType::DialogueTurn
                && (! $nonEmptyString($payload['speaker'] ?? null) || ! $nonEmptyString($payload['text'] ?? null))) {
                $validator->errors()->add('speaker', __('admin.dialogue_block_fields_required'));
            }
            if ($type === CurriculumBlockType::SourceTable) {
                $header = $payload['header'] ?? null;
                $rows = $payload['rows'] ?? null;
                $valid = $nonEmptyString($payload['caption'] ?? null) && is_array($header) && $header !== []
                    && ! array_filter($header, fn ($cell): bool => ! $nonEmptyString($cell))
                    && is_array($rows) && ! array_filter($rows, fn ($row): bool => ! is_array($row) || count($row) !== count($header));
                if (! $valid) {
                    $validator->errors()->add('table_rows', __('admin.source_table_block_invalid'));
                }
            }
            if ($type === CurriculumBlockType::ExternalLink) {
                $links = $payload['links'] ?? null;
                $valid = is_array($links) && $links !== [] && ! array_filter($links, function ($link) use ($nonEmptyString): bool {
                    $url = is_array($link) ? filter_var($link['target'] ?? null, FILTER_VALIDATE_URL) : false;
                    $scheme = is_string($url) ? strtolower((string) parse_url($url, PHP_URL_SCHEME)) : '';

                    return ! is_array($link) || ! $nonEmptyString($link['text'] ?? null) || ! in_array($scheme, ['http', 'https'], true);
                });
                if (! $valid) {
                    $validator->errors()->add('link_target', __('admin.external_link_block_invalid'));
                }
            }
            if ($type === CurriculumBlockType::ActivityEmbed) {
                $draft = $this->route('curriculumDraft');
                $code = $payload['activity_code'] ?? null;
                if (! $nonEmptyString($code) || $draft === null || ! CurriculumDraftEntity::query()
                    ->where('curriculum_draft_id', $draft->id)->where('entity_type', 'activity')
                    ->where('code', $code)->whereNull('archived_at')->exists()) {
                    $validator->errors()->add('activity_code', __('admin.activity_embed_block_invalid'));
                }
            }
            $assetId = $this->input('asset_public_id');
            $draft = $this->route('curriculumDraft');
            if (is_string($assetId) && $assetId !== '' && ($draft === null || ! $draft->assets()
                ->where('public_id', $assetId)->whereNull('archived_at')->exists())) {
                $validator->errors()->add('asset_public_id', __('admin.asset_reference_invalid'));
            }
        }];
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        return $this->richPayload();
    }

    /** @return array<string, mixed> */
    private function richPayload(): array
    {
        $type = CurriculumBlockType::tryFrom((string) ($this->input('block_type') ?? $this->route('draftBlock')?->block_type));
        $payload = match ($type) {
            CurriculumBlockType::Paragraph,
            CurriculumBlockType::Heading,
            CurriculumBlockType::Callout,
            CurriculumBlockType::ListItem,
            CurriculumBlockType::Instruction => ['text' => trim((string) $this->input('text'))],
            CurriculumBlockType::DialogueTurn => [
                'speaker' => trim((string) $this->input('speaker')),
                'text' => trim((string) $this->input('text')),
            ],
            CurriculumBlockType::SourceTable => [
                'caption' => trim((string) $this->input('caption')),
                'header' => $this->tabbedRow((string) $this->input('table_header')),
                'rows' => $this->tabbedRows((string) $this->input('table_rows')),
            ],
            CurriculumBlockType::ExternalLink => ['links' => $this->links()],
            CurriculumBlockType::ActivityEmbed => ['activity_code' => trim((string) $this->input('activity_code'))],
            default => [],
        };
        $note = trim((string) $this->input('provenance_note'));
        if ($note !== '') {
            $payload['provenance_note'] = $note;
        }

        return $payload;
    }

    /** @return list<string> */
    private function tabbedRow(string $value): array
    {
        return array_values(array_map('trim', explode("\t", trim($value))));
    }

    /** @return list<list<string>> */
    private function tabbedRows(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        return array_values(array_map(fn (string $row): array => $this->tabbedRow($row), preg_split('/\R/u', trim($value)) ?: []));
    }

    /** @return list<array{text:string,target:string}> */
    private function links(): array
    {
        $labels = $this->input('link_text', []);
        $targets = $this->input('link_target', []);
        $links = [];
        $count = max(count($labels), count($targets));
        if ($count === 0) {
            return [];
        }
        foreach (range(0, $count - 1) as $index) {
            $text = trim((string) ($labels[$index] ?? ''));
            $target = trim((string) ($targets[$index] ?? ''));
            if ($text !== '' || $target !== '') {
                $links[] = ['text' => $text, 'target' => $target];
            }
        }

        return $links;
    }
}

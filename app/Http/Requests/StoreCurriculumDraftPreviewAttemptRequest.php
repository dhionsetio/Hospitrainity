<?php

namespace App\Http\Requests;

use App\Models\CurriculumDraft;
use App\Models\CurriculumDraftEntity;

class StoreCurriculumDraftPreviewAttemptRequest extends CanonicalAttemptRequest
{
    /** @var array<string, mixed>|null */
    private ?array $attemptDefinition = null;

    public function authorize(): bool
    {
        $draft = $this->route('curriculumDraft');

        return $draft instanceof CurriculumDraft && $this->user()?->can('preview', $draft) === true;
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        if ($this->attemptDefinition !== null) {
            return $this->attemptDefinition;
        }

        $draft = $this->route('curriculumDraft');
        abort_unless($draft instanceof CurriculumDraft, 404);
        $activity = $draft->entities()->available()
            ->where('entity_type', 'activity')
            ->where('code', (string) $this->route('activity'))
            ->first();
        abort_if($activity === null, 404);
        $prompts = $draft->entities()->available()
            ->where('entity_type', 'prompt-item')
            ->where('parent_code', $activity->code)
            ->orderByRaw('position is null')->orderBy('position')->orderBy('code')
            ->get()
            ->keyBy('code')
            ->map(static fn (CurriculumDraftEntity $prompt): array => [
                'code' => $prompt->code,
                'payload' => $prompt->payload,
            ])
            ->all();

        return $this->attemptDefinition = [
            'activity' => [
                'id' => $activity->id,
                'code' => $activity->code,
                'parent_code' => $activity->parent_code,
                'payload' => $activity->payload,
            ],
            'prompts' => $prompts,
        ];
    }
}

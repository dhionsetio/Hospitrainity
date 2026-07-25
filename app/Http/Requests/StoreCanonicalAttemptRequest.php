<?php

namespace App\Http\Requests;

use App\Enums\WorkContextRole;
use App\Models\CurriculumEntity;
use App\Services\LearningContentScope;
use App\Services\WorkContext;

class StoreCanonicalAttemptRequest extends CanonicalAttemptRequest
{
    /** @var array<string, mixed>|null */
    private ?array $definition = null;

    public function authorize(): bool
    {
        return $this->user() !== null
            && app(WorkContext::class)->current($this, $this->user()) === WorkContextRole::Learner;
    }

    /** @return array<string, mixed> */
    public function definition(): array
    {
        if ($this->definition !== null) {
            return $this->definition;
        }
        $scope = app(LearningContentScope::class)->current($this, $this->user());
        $package = $scope['package'];
        abort_if($package === null, 404);
        $activity = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->where('entity_type', 'activity')
            ->published()
            ->where('code', (string) $this->route('activity'))
            ->first();
        abort_if($activity === null || ! app(LearningContentScope::class)->allowsEntity($scope, $activity), 404);
        $prompts = CurriculumEntity::query()
            ->where('curriculum_package_id', $package->id)
            ->where('entity_type', 'prompt-item')
            ->published()
            ->where('parent_code', $activity->code)
            ->orderByRaw('position is null')->orderBy('position')->orderBy('code')
            ->get()
            ->keyBy('code')
            ->map(static fn (CurriculumEntity $prompt): array => [
                'code' => $prompt->code,
                'payload' => $prompt->payload,
            ])
            ->all();

        return $this->definition = ['package' => $package, 'activity' => ['id' => $activity->id, 'code' => $activity->code, 'parent_code' => $activity->parent_code, 'source_sha256' => $activity->source_sha256, 'payload' => $activity->payload], 'prompts' => $prompts];
    }
}

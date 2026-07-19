<?php

namespace App\Services\Curriculum;

use App\Enums\CurriculumDraftStatus;
use App\Exceptions\CurriculumDraftConflictException;
use App\Models\CurriculumDraft;
use App\Models\CurriculumDraftEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CurriculumDraftLifecycle
{
    /** @param array<string, mixed> $attributes @param array<string, mixed>|null $metadata */
    public function transition(
        CurriculumDraft $draft,
        CurriculumDraftStatus $to,
        User $actor,
        int $expectedRevision,
        string $eventType,
        ?string $reason = null,
        array $attributes = [],
        ?array $metadata = null,
    ): CurriculumDraft {
        return DB::transaction(function () use ($draft, $to, $actor, $expectedRevision, $eventType, $reason, $attributes, $metadata): CurriculumDraft {
            $locked = CurriculumDraft::query()->lockForUpdate()->findOrFail($draft->id);
            $this->assertRevision($locked, $expectedRevision);
            $from = $locked->status;
            if (! $from->canTransitionTo($to)) {
                throw new RuntimeException("Invalid curriculum draft transition: {$from->value} to {$to->value}.");
            }

            $locked->forceFill(array_merge($attributes, [
                'status' => $to,
                'revision' => $locked->revision + 1,
                'updated_by' => $actor->id,
            ]))->save();

            $this->record($locked, $actor, $eventType, $from, $to, $reason, $metadata);

            return $locked->fresh();
        }, attempts: 3);
    }

    /** @param array<string, mixed>|null $metadata */
    public function record(
        CurriculumDraft $draft,
        ?User $actor,
        string $eventType,
        ?CurriculumDraftStatus $from = null,
        ?CurriculumDraftStatus $to = null,
        ?string $reason = null,
        ?array $metadata = null,
    ): CurriculumDraftEvent {
        return CurriculumDraftEvent::create([
            'curriculum_draft_id' => $draft->id,
            'actor_id' => $actor?->id,
            'event_type' => $eventType,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'revision' => $draft->revision,
            'reason' => $reason,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }

    public function assertRevision(CurriculumDraft $draft, int $expectedRevision): void
    {
        if ($draft->revision !== $expectedRevision) {
            throw new CurriculumDraftConflictException($expectedRevision, $draft->revision);
        }
    }
}

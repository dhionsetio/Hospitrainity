<?php

// FILE: app/Http/Controllers/ProgressController.php

namespace App\Http\Controllers;

use App\Models\Completion;
use App\Models\Exercise;
use App\Models\MaterialItem;
use App\Models\VocabularyItem;
use App\Services\LearningContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProgressController extends Controller
{
    /**
     * Allowlist of completable types the client may report progress for.
     * Maps the short type sent by the front-end to a fully-qualified model class.
     * This replaces the previous unsafe "App\\Models\\{$type}" interpolation so
     * arbitrary class names can never be constructed from user input.
     */
    private const COMPLETABLE_TYPES = [
        'VocabularyItem' => VocabularyItem::class,
        'MaterialItem' => MaterialItem::class,
        'Exercise' => Exercise::class,
    ];

    /** Keep progress payloads bounded even when a client is modified or broken. */
    private const MAX_ITEMS_PER_REQUEST = 100;

    public function store(Request $request, LearningContext $learning): JsonResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array', 'list', 'min:1', 'max:'.self::MAX_ITEMS_PER_REQUEST],
            'items.*' => ['required', 'integer', 'min:1'],
            'type' => ['required', 'string', Rule::in(array_keys(self::COMPLETABLE_TYPES))],
        ]);

        $user = $request->user();
        $context = $learning->current($request, $user);
        abort_if($context['class_invalidated'], 403);
        abort_if($context['course_enrollment_id'] !== null, 410, __('Legacy progress tracking is unavailable in a Class learning context.'));

        // Resolve to a known model class via the allowlist (never from raw input).
        $modelClass = self::COMPLETABLE_TYPES[$validated['type']];

        // Ownership / integrity guard (Phase 5.2): only accept ids that actually
        // exist for the resolved completable type. Without this a client could
        // POST arbitrary ids — or ids belonging to a different type — and inflate
        // its own progress with completion rows that point at nothing. Unknown
        // ids are rejected as a validation error and NOTHING is written
        // (all-or-nothing), so a single bad id cannot partially record progress.
        $itemIds = array_values(array_unique(array_map('intval', $validated['items'])));

        $existingIds = $this->publishedItemsQuery($validated['type'])
            ->whereKey($itemIds)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $missingIds = array_values(array_diff($itemIds, $existingIds));

        if ($missingIds !== []) {
            throw ValidationException::withMessages([
                'items' => 'One or more items are unavailable for progress tracking.',
            ]);
        }

        $timestamp = now();
        $rows = array_map(
            static fn (int $itemId): array => [
                'user_id' => $user->getKey(),
                'learning_scope_key' => $context['scope_key'],
                'institution_membership_id' => $context['membership_id'],
                'course_offering_id' => null,
                'course_enrollment_id' => null,
                'completable_id' => $itemId,
                'completable_type' => $modelClass,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            $itemIds,
        );

        DB::transaction(function () use ($rows): void {
            Completion::upsert(
                $rows,
                uniqueBy: ['user_id', 'learning_scope_key', 'completable_id', 'completable_type'],
                update: ['updated_at'],
            );
        }, attempts: 3);

        // Progress just changed: drop this user's cached overall-progress value so
        // the supervisor dashboard reflects the update on its next load.
        $user->forgetProgressCache();

        return response()->json(['message' => __('Progress saved successfully.')]);
    }

    /**
     * Restrict progress writes to items whose ancestor module is published.
     */
    private function publishedItemsQuery(string $type): Builder
    {
        return match ($type) {
            'VocabularyItem' => VocabularyItem::query()
                ->whereHas('vocabulary.lesson.module', fn (Builder $query) => $query->where('is_published', true)),
            'MaterialItem' => MaterialItem::query()
                ->whereHas('material.lesson.module', fn (Builder $query) => $query->where('is_published', true)),
            'Exercise' => Exercise::query()
                ->whereHas('lesson.module', fn (Builder $query) => $query->where('is_published', true)),
        };
    }
}

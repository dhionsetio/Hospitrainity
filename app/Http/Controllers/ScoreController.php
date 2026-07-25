<?php

namespace App\Http\Controllers;

use App\Models\Exercise;
use App\Models\ExerciseScore;
use App\Services\LearningContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ScoreController extends Controller
{
    public function store(Request $request, LearningContext $learning): JsonResponse
    {
        $validated = $request->validate([
            'exercise_id' => ['required', 'integer', 'min:1'],
            'score' => ['required', 'integer', 'min:0', 'max:10000'],
            'max_score' => ['required', 'integer', 'min:0', 'max:10000'],
            'response_data' => ['sometimes', 'nullable', 'array'],
        ]);

        if ($validated['score'] > $validated['max_score']) {
            throw ValidationException::withMessages([
                'score' => __('The score cannot exceed the maximum score.'),
            ]);
        }

        $user = $request->user();
        $context = $learning->current($request, $user);
        abort_if($context['class_invalidated'], 403);
        abort_if($context['course_enrollment_id'] !== null, 410, __('Legacy score tracking is unavailable in a Class learning context.'));

        $exerciseExists = Exercise::query()
            ->whereHas('lesson.module', fn (Builder $query) => $query->where('is_published', true))
            ->whereKey((int) $validated['exercise_id'])
            ->exists();

        if (! $exerciseExists) {
            throw ValidationException::withMessages([
                'exercise_id' => __('The requested exercise is unavailable for score tracking.'),
            ]);
        }

        $timestamp = now();

        DB::transaction(function () use ($user, $context, $validated, $timestamp): void {
            ExerciseScore::upsert(
                [
                    [
                        'user_id' => $user->getKey(),
                        'learning_scope_key' => $context['scope_key'],
                        'institution_membership_id' => $context['membership_id'],
                        'exercise_id' => (int) $validated['exercise_id'],
                        'score' => (int) $validated['score'],
                        'max_score' => (int) $validated['max_score'],
                        'response_data' => isset($validated['response_data']) ? json_encode($validated['response_data']) : null,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ],
                ],
                uniqueBy: ['user_id', 'learning_scope_key', 'exercise_id'],
                update: ['score', 'max_score', 'response_data', 'updated_at'],
            );
        }, attempts: 3);

        return response()->json(['message' => __('Score saved successfully.')]);
    }
}

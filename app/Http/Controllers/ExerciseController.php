<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExerciseRequest;
use App\Http\Requests\UpdateExerciseRequest;
use App\Models\Exercise;
use App\Models\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ExerciseController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Exercise::class);
        $exercises = Exercise::with('lesson')
            ->orderBy('lesson_id')
            ->curriculumOrder()
            ->paginate(10);
        $lessons = Lesson::orderBy('title')->get();

        return view('superadmin.exercises.index', compact('exercises', 'lessons'));
    }

    /** Store a new exercise submitted through the modal form. */
    public function store(StoreExerciseRequest $request): RedirectResponse
    {
        $this->authorize('create', Exercise::class);
        $data = $request->validated();
        DB::transaction(function () use ($data): void {
            Exercise::create([
                'lesson_id' => $data['lesson_id'],
                'title' => $data['title'],
                'type' => $data['type'],
                'content' => $data['content'],
                'order' => (int) ($data['order'] ?? 0),
            ]);
        }, attempts: 3);

        return redirect()->route('superadmin.exercises.index')->with('success', __('Exercise created successfully.'));
    }

    /** Update an exercise submitted through the modal form. */
    public function update(UpdateExerciseRequest $request, Exercise $exercise): RedirectResponse
    {
        $this->authorize('update', $exercise);
        $validated = $request->validated();
        $data = [
            'lesson_id' => $validated['lesson_id'],
            'title' => $validated['title'],
            'content' => $validated['content'],
        ];
        if (array_key_exists('order', $validated)) {
            $data['order'] = (int) ($validated['order'] ?? 0);
        }

        DB::transaction(fn () => $exercise->update($data), attempts: 3);

        return redirect()->route('superadmin.exercises.index')->with('success', __('Exercise updated successfully.'));
    }

    /** Delete an exercise. */
    public function destroy(Exercise $exercise): RedirectResponse
    {
        $this->authorize('delete', $exercise);
        DB::transaction(fn () => $exercise->delete(), attempts: 3);

        return redirect()->route('superadmin.exercises.index')->with('success', __('Exercise deleted successfully.'));
    }
}

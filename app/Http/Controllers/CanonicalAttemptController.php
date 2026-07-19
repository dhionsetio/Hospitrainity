<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCanonicalAttemptRequest;
use App\Services\Curriculum\CurriculumAttemptService;
use Illuminate\Http\RedirectResponse;

class CanonicalAttemptController extends Controller
{
    public function __construct(private readonly CurriculumAttemptService $attempts) {}

    public function store(StoreCanonicalAttemptRequest $request, string $activity): RedirectResponse
    {
        $result = $this->attempts->submit($request->user(), $activity, $request->validated());

        return redirect()
            ->to(route('curriculum.activities.show', $activity).'#attempt-result')
            ->with('attempt_result', $result);
    }
}

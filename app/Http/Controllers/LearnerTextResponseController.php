<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLearnerTextResponseRequest;
use App\Models\LearnerTextResponse;
use App\Services\LearningContext;
use App\Services\Responses\LearnerTextResponseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LearnerTextResponseController extends Controller
{
    public function index(Request $request, LearningContext $learningContext): View
    {
        $context = $learningContext->current($request, $request->user());
        $responses = LearnerTextResponse::query()
            ->where('user_id', $request->user()->getKey())
            ->where('learning_scope_key', $context['scope_key'])
            ->with(['activity', 'prompt'])
            ->latest('updated_at')
            ->paginate(20);

        return view('responses.index', compact('responses'));
    }

    public function edit(
        Request $request,
        string $activity,
        string $prompt,
        LearnerTextResponseService $responses,
    ): View {
        $definition = $responses->definition($request->user(), $activity, $prompt);
        $draft = $responses->draft($request->user(), $activity, $prompt);

        return view('responses.edit', [
            'activity' => $definition['activity'],
            'prompt' => $definition['prompt'],
            'maximumCharacters' => $definition['maximum_characters'],
            'draft' => $draft,
            'responseKey' => $draft === null ? (string) Str::uuid() : (string) $draft->response_key,
        ]);
    }

    public function store(
        StoreLearnerTextResponseRequest $request,
        string $activity,
        string $prompt,
        LearnerTextResponseService $responses,
    ): RedirectResponse {
        $response = $responses->save($request->user(), $activity, $prompt, $request->validated());

        if ($response->state === 'submitted') {
            return redirect()
                ->route('responses.index')
                ->with('status', __('responses.submitted'));
        }

        return redirect()
            ->route('responses.edit', [$activity, $prompt])
            ->with('status', __('responses.saved'));
    }
}

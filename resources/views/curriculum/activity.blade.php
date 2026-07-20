@extends('layouts.app')

@section('title'){{ $curriculumActivity['title'] }} - {{ __('Hospitrainity activity') }}@endsection
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @include('partials.learner-nav')
    @isset($curriculumPreview)
        @include('curriculum.partials.preview-banner')
    @else
        @include('curriculum.partials.active-draft-banner', ['activePackage' => $curriculumActivity['package'], 'showCurriculumEvidence' => $showCurriculumEvidence])
    @endisset

    @php
        $attemptResult = isset($curriculumPreview) ? session('preview_attempt_result') : session('attempt_result');
        $errorKeys = $errors->keys();
        $promptCodeFromError = static function (string $key): ?string {
            if (preg_match('/^(?:responses|self_checks)\.([A-Z0-9-]+)/', $key, $matches) !== 1) {
                return null;
            }

            return $matches[1];
        };
        $promptErrorMessages = static function (string $code) use ($errors, $errorKeys): array {
            $messages = [];
            foreach ($errorKeys as $key) {
                if ($key === "responses.{$code}" || str_starts_with($key, "responses.{$code}.") || $key === "self_checks.{$code}") {
                    array_push($messages, ...$errors->get($key));
                }
            }

            return array_values(array_unique($messages));
        };
        $hasResponseError = static fn (string $code): bool => collect($errorKeys)->contains(
            static fn (string $key): bool => $key === "responses.{$code}" || str_starts_with($key, "responses.{$code}."),
        );
        $hasSelfCheckError = static fn (string $code): bool => in_array("self_checks.{$code}", $errorKeys, true);
        $progressLabel = match ($curriculumActivity['progress']['state']) {
            'completed' => __('Completed'),
            'self_checked' => __('Self-checked'),
            'attempted' => __('Attempted'),
            'started' => __('In progress'),
            'viewed' => __('Ready to practise'),
            'preview_not_recorded' => __('Preview only'),
            default => __('Not started'),
        };
        $promptIcon = static fn (string $responseForm): string => match ($responseForm) {
            'selection' => 'fa-circle-check',
            'rating' => 'fa-chart-simple',
            'ordering' => 'fa-list-ol',
            'short_text' => 'fa-keyboard',
            default => 'fa-pen',
        };
    @endphp
    <main class="container mx-auto max-w-5xl px-4 py-6 sm:px-6 sm:py-8">
        <x-back-control
            :href="isset($curriculumPreview) ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.preview.sections.show', [$curriculumPreview, $curriculumActivity['section']['code']]) : route('curriculum.sections.show', $curriculumActivity['section']['code'])"
            :label="__('Return to section: :section', ['section' => $curriculumActivity['section']['title']])"
        />

        <header class="hsp-practice-hero mt-4">
            <p class="text-sm font-semibold text-indigo-700">{{ $curriculumActivity['section']['title'] }}@if($showCurriculumEvidence) · {{ $curriculumActivity['section']['code'] }}@endif</p>
            <h1 class="mt-2 text-3xl font-bold text-neutral-950">{{ $curriculumActivity['title'] }}</h1>
            <div class="hsp-practice-status mt-4">
                <span><i class="fa-solid {{ $curriculumActivity['progress']['completed'] ? 'fa-circle-check' : 'fa-bolt' }}" aria-hidden="true"></i> {{ $progressLabel }}</span>
                <small>{{ trans_choice(':count attempt|:count attempts', $curriculumActivity['progress']['attempt_count'], ['count' => $curriculumActivity['progress']['attempt_count']]) }}</small>
            </div>
            @if($showCurriculumEvidence)
                <div class="mt-4 flex flex-wrap gap-2">
                    <span class="rounded-full border border-neutral-300 bg-neutral-50 px-3 py-1 text-xs font-semibold text-neutral-700">{{ __('Progress state: :state', ['state' => str_replace('_', ' ', $curriculumActivity['progress']['state'])]) }}</span>
                    @foreach (['cefr_activity', 'pedagogical_function', 'response_form', 'channel', 'participation', 'scoring_mode', 'timing'] as $field)
                        <span class="rounded-full border border-neutral-300 bg-neutral-50 px-3 py-1 text-xs font-semibold text-neutral-700">{{ str_replace('_', ' ', $curriculumActivity['metadata'][$field]) }}</span>
                    @endforeach
                </div>
            @endif
        </header>

        @if ($curriculumActivity['guidance'])
            <aside class="mt-5 rounded-lg border border-sky-200 bg-sky-50 p-4 leading-7 text-sky-950" aria-label="{{ __('Activity guidance') }}">{{ $curriculumActivity['guidance'] }}</aside>
        @endif

        @if($showCurriculumEvidence)
            <aside class="mt-5 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950" aria-label="{{ __('Response privacy') }}">
                {{ __('Raw open role-play and writing text is not saved in your attempt history. A validation error may keep it temporarily in your current session so this form can preserve your response. Audio recording is disabled. Confidence ratings are saved for your own history and are not proficiency or CEFR evidence.') }}
            </aside>
        @else
            <aside class="mt-5 flex gap-3 rounded-lg border border-sky-200 bg-sky-50 p-4 text-sm leading-6 text-sky-950" aria-label="{{ __('Response privacy') }}">
                <i class="fa-solid fa-shield-halved mt-1" aria-hidden="true"></i>
                <p>{{ __('Your written practice stays private and is not added to your attempt history. Confidence ratings, when shown, are saved only for your own reflection.') }}</p>
            </aside>
        @endif

        @if ($curriculumActivity['progress']['legacy_reveal_only'])
            <aside class="mt-5 rounded-lg border border-neutral-300 bg-white p-4 text-sm text-neutral-800" role="note">
                {{ __('A legacy reveal-only completion exists. It is preserved as history but does not count as a completed response attempt for this version.') }}
            </aside>
        @endif

        @if ($errors->any())
            <div id="activity-errors" data-error-summary tabindex="-1" class="mt-6 rounded-lg border-2 border-red-500 bg-red-50 p-4 text-red-950" role="alert" aria-labelledby="activity-errors-heading">
                <h2 id="activity-errors-heading" class="font-bold">{{ __('Please correct the following problems') }}</h2>
                <ul class="mt-2 list-disc space-y-1 pl-6">
                    @foreach ($errors->getMessages() as $errorKey => $messages)
                        @php($errorPromptCode = $promptCodeFromError($errorKey))
                        @foreach ($messages as $error)
                            <li>
                                <a href="#{{ $errorPromptCode ? 'prompt-'.$errorPromptCode : 'attempt-actions' }}" data-error-link class="font-semibold underline underline-offset-2">{{ $error }}</a>
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($attemptResult)
            <section id="attempt-result" tabindex="-1" class="mt-6 rounded-xl border-2 {{ $attemptResult['state'] === 'completed' ? 'border-emerald-500 bg-emerald-50' : 'border-indigo-400 bg-indigo-50' }} p-5" role="status" aria-live="polite">
                <h2 class="text-xl font-bold text-neutral-950">
                    @if ($attemptResult['model_without_attempt'])
                        {{ __('Model shown without recording an attempted response') }}
                    @elseif ($attemptResult['completion_reason'] === 'baseline_explicitly_skipped')
                        {{ __('Baseline explicitly skipped') }}
                    @else
                        {{ __('Responses checked and activity completed') }}
                    @endif
                </h2>
                @if ($attemptResult['reused'])
                    <p class="mt-2 text-sm">{{ __('This was the same idempotent submission; no duplicate attempt was created.') }}</p>
                @endif
            </section>
        @endif

        <form method="POST" action="{{ isset($curriculumPreview) ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.preview.activities.attempt', [$curriculumPreview, $curriculumActivity['code']]) : route('curriculum.activities.attempts.store', $curriculumActivity['code']) }}" @if(isset($curriculumPreview)) data-preview-form @endif class="mt-8 space-y-6" novalidate>
            @csrf
            <input type="hidden" name="attempt_key" value="{{ old('attempt_key', (string) \Illuminate\Support\Str::uuid()) }}">

            <section class="space-y-5" aria-labelledby="prompts-heading">
                <h2 id="prompts-heading" class="text-2xl font-bold text-neutral-950">{{ __('Your responses') }}</h2>
                @foreach ($curriculumActivity['prompts'] as $prompt)
                    @php($result = $attemptResult['prompt_results'][$prompt['code']] ?? null)
                    @php($promptErrors = $promptErrorMessages($prompt['code']))
                    @php($responseInvalid = $hasResponseError($prompt['code']))
                    @php($selfCheckInvalid = $hasSelfCheckError($prompt['code']))
                    <article class="hsp-activity-prompt hsp-activity-prompt--{{ $prompt['response_form'] }}" id="prompt-{{ $prompt['code'] }}" tabindex="-1">
                        <div class="hsp-activity-prompt__heading">
                            <span><i class="fa-solid {{ $promptIcon($prompt['response_form']) }}" aria-hidden="true"></i></span>
                            <p>{{ __('Prompt :number of :total', ['number' => $loop->iteration, 'total' => count($curriculumActivity['prompts'])]) }}</p>
                        </div>
                        @if($showCurriculumEvidence)
                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">{{ $prompt['code'] }} · {{ str_replace('_', ' ', $prompt['scoring_mode']) }}</p>
                        @endif

                        @if ($promptErrors !== [])
                            <div id="prompt-error-{{ $prompt['code'] }}" class="mt-3 rounded-lg border border-red-400 bg-red-50 p-3 font-semibold text-red-950" role="alert">
                                <span class="sr-only">{{ __('Error:') }}</span>
                                <ul class="list-disc space-y-1 pl-5">
                                    @foreach ($promptErrors as $promptError)
                                        <li>{{ $promptError }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @if(is_array($prompt['audio'] ?? null) && is_string($prompt['audio']['url'] ?? null))
                            <div class="mt-4 rounded-lg border border-indigo-200 bg-indigo-50 p-4">
                                <p id="audio-description-{{ $prompt['code'] }}" class="font-semibold text-indigo-950">{{ $prompt['audio']['accessibility_text'] }}</p>
                                <audio controls preload="none" class="mt-3 w-full" aria-describedby="audio-description-{{ $prompt['code'] }}">
                                    <source src="{{ $prompt['audio']['url'] }}" type="{{ $prompt['audio']['mime_type'] }}">
                                    {{ __('Your browser does not support audio playback.') }}
                                </audio>
                            </div>
                        @endif

                        @if ($prompt['response_form'] === 'selection')
                            <fieldset class="mt-3">
                                <legend class="text-base font-semibold leading-7 text-neutral-950">{{ $prompt['stem'] }}</legend>
                                <div class="mt-4 space-y-3">
                                    @foreach ($prompt['choices'] as $choice)
                                        <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border border-neutral-300 p-3 hover:border-indigo-500 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50">
                                            <input type="radio" name="responses[{{ $prompt['code'] }}]" value="{{ $choice['id'] }}" class="mt-1 h-5 w-5 shrink-0" @checked(old('responses.'.$prompt['code']) === $choice['id']) @if($responseInvalid) aria-invalid="true" aria-describedby="prompt-error-{{ $prompt['code'] }}" @endif>
                                            <span><span class="font-bold uppercase">{{ $choice['label'] }}.</span> {{ $choice['text'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @elseif ($prompt['response_form'] === 'rating')
                            <fieldset class="mt-3">
                                <legend class="text-base font-semibold leading-7 text-neutral-950">{{ $prompt['stem'] }}</legend>
                                <p class="mt-2 text-sm text-neutral-600">{{ __('1 = not confident yet; 5 = very confident') }}</p>
                                <div class="mt-3 flex flex-wrap gap-3">
                                    @foreach ($prompt['rating_scale']['values'] as $value)
                                        <label class="flex min-h-11 min-w-11 cursor-pointer items-center justify-center rounded-lg border border-neutral-300 px-3 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50">
                                            <input type="radio" name="responses[{{ $prompt['code'] }}]" value="{{ $value }}" class="mr-2 h-5 w-5 shrink-0" @checked((string) old('responses.'.$prompt['code']) === (string) $value) @if($responseInvalid) aria-invalid="true" aria-describedby="prompt-error-{{ $prompt['code'] }}" @endif>
                                            <span>{{ $value }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>
                        @elseif ($prompt['response_form'] === 'ordering')
                            <fieldset class="mt-3">
                                <legend class="text-base font-semibold leading-7 text-neutral-950">{{ $prompt['stem'] }}</legend>
                                <p class="mt-2 text-sm text-neutral-600">{{ __('Choose an item for each position. You may also use Move up and Move down; no dragging is required.') }}</p>
                                <p class="sr-only" aria-live="polite" data-ordering-status></p>
                                <ol class="mt-4 space-y-3" data-ordering-list data-ordering-label="{{ __('Position') }}" data-ordering-up-label="{{ __('Move item at position') }}" data-ordering-up-suffix="{{ __('up') }}" data-ordering-down-label="{{ __('Move item at position') }}" data-ordering-down-suffix="{{ __('down') }}" data-ordering-moved-label="{{ __('Item moved to position') }}">
                                    @foreach ($prompt['tokens'] as $position => $token)
                                        <li class="grid gap-2 rounded-lg border border-neutral-300 p-3 sm:grid-cols-[1fr_auto]" data-ordering-item>
                                            <label class="font-semibold">
                                                <span data-ordering-position>{{ __('Position :number', ['number' => $position + 1]) }}</span>
                                                <select name="responses[{{ $prompt['code'] }}][]" class="mt-1 block min-h-11 w-full rounded-lg border-neutral-400" @if($responseInvalid) aria-invalid="true" aria-describedby="prompt-error-{{ $prompt['code'] }}" @endif>
                                                    @foreach ($prompt['tokens'] as $option)
                                                        <option value="{{ $option['id'] }}" @selected(old('responses.'.$prompt['code'].'.'.$position, $token['id']) === $option['id'])>{{ $option['text'] }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <div class="flex items-end gap-2">
                                                <button type="button" data-order-up class="min-h-11 rounded-lg border border-neutral-400 px-3 font-semibold hover:bg-neutral-100">{{ __('Move up') }}</button>
                                                <button type="button" data-order-down class="min-h-11 rounded-lg border border-neutral-400 px-3 font-semibold hover:bg-neutral-100">{{ __('Move down') }}</button>
                                            </div>
                                        </li>
                                    @endforeach
                                </ol>
                            </fieldset>
                        @else
                            <label for="response-{{ $prompt['code'] }}" class="mt-3 block text-base font-semibold leading-7 text-neutral-950">{{ $prompt['stem'] }}</label>
                            @if ($prompt['response_form'] === 'short_text' && $prompt['scoring_mode'] === 'objective_normalized_closed')
                                <input type="text" id="response-{{ $prompt['code'] }}" name="responses[{{ $prompt['code'] }}]" value="{{ old('responses.'.$prompt['code']) }}" maxlength="{{ $prompt['response_constraints']['maximum_characters'] ?? 1000 }}" class="mt-3 block min-h-11 w-full rounded-lg border-neutral-400" autocomplete="off" @if($responseInvalid) aria-invalid="true" aria-describedby="prompt-error-{{ $prompt['code'] }}" @endif>
                            @else
                                <textarea id="response-{{ $prompt['code'] }}" name="responses[{{ $prompt['code'] }}]" rows="6" maxlength="{{ $prompt['response_constraints']['maximum_characters'] ?? 6000 }}" class="mt-3 block w-full rounded-lg border-neutral-400" aria-describedby="privacy-{{ $prompt['code'] }}{{ $responseInvalid ? ' prompt-error-'.$prompt['code'] : '' }}" @if($responseInvalid) aria-invalid="true" @endif>{{ old('responses.'.$prompt['code']) }}</textarea>
                                <p id="privacy-{{ $prompt['code'] }}" class="mt-2 text-sm text-neutral-600">
                                    {{ $showCurriculumEvidence
                                        ? __('This text stays in the current form/session and is not persisted as a raw server response.')
                                        : __('Your response is used only for this practice check and is not added to your attempt history.') }}
                                </p>
                                @if ($prompt['self_check_required'])
                                    <label class="mt-3 flex min-h-11 items-center gap-3 rounded-lg border border-neutral-300 p-3">
                                        <input type="checkbox" name="self_checks[{{ $prompt['code'] }}]" value="1" class="h-5 w-5 shrink-0" @checked(old('self_checks.'.$prompt['code'])) @if($selfCheckInvalid) aria-invalid="true" aria-describedby="prompt-error-{{ $prompt['code'] }}" @endif>
                                        <span>{{ __('I completed or rehearsed this response and will compare it with the model/rubric.') }}</span>
                                    </label>
                                @endif
                            @endif
                        @endif

                        @if ($result)
                            <div class="mt-5 space-y-3 rounded-lg border border-indigo-200 bg-indigo-50 p-4" role="status" aria-live="polite">
                                @if ($result['is_correct'] === true)
                                    <p class="font-bold text-emerald-800">{{ __('Correct') }}</p>
                                @elseif ($result['is_correct'] === false)
                                    <p class="font-bold text-red-800">{{ __('Not yet correct; review the source feedback below.') }}</p>
                                @else
                                    <p class="font-bold text-indigo-900">{{ __('Use the model and rubric for self-checking; this open response is not automatically graded.') }}</p>
                                @endif
                                @foreach ($result['model_answers'] as $answer)
                                    <div class="rounded-md border border-emerald-200 bg-white p-3 text-emerald-950"><span class="font-semibold">{{ __('Model:') }}</span> {{ $answer }}</div>
                                @endforeach
                                @foreach ($result['feedback'] as $feedback)
                                    <div class="rounded-md border border-indigo-200 bg-white p-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-indigo-700">{{ str_replace('_', ' ', $feedback['feedback_type']) }}</p>
                                        <p class="mt-1 text-neutral-900">{{ $feedback['text'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </article>
                @endforeach
            </section>

            @if ($curriculumActivity['rubric'])
                <section class="rounded-xl bg-white p-5 shadow sm:p-6" aria-labelledby="rubric-heading">
                    <h2 id="rubric-heading" class="text-2xl font-bold text-neutral-950">{{ __('Self-assessment rubric') }}</h2>
                    <div class="mt-4 max-w-full overflow-x-auto rounded-lg border border-neutral-300" role="region" aria-label="{{ __('Self-assessment rubric') }}" tabindex="0">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-neutral-100"><tr><th scope="col" class="p-3">{{ __('Criterion') }}</th><th scope="col" class="p-3">{{ __('Levels') }}</th></tr></thead>
                            <tbody class="divide-y">
                                @foreach ($curriculumActivity['rubric']['criteria'] as $criterion)
                                    <tr><th scope="row" class="p-3 font-semibold">{{ $criterion['descriptor'] }}</th><td class="p-3">{{ implode(' · ', $criterion['levels']) }}</td></tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            <div id="attempt-actions" class="rounded-xl border border-indigo-300 bg-indigo-50 p-5" tabindex="-1">
                <div class="flex flex-wrap gap-3">
                    <button type="submit" name="intent" value="check" class="min-h-11 rounded-lg bg-indigo-700 px-5 py-2 font-semibold text-white hover:bg-indigo-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-800">{{ __('Check responses') }}</button>
                    <button type="submit" name="intent" value="show_model" class="min-h-11 rounded-lg border border-indigo-700 bg-white px-5 py-2 font-semibold text-indigo-800 hover:bg-indigo-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-800">{{ __('Show model without answering') }}</button>
                    @if ($curriculumActivity['code'] === 'HSP-C01-ACT-BASELINE')
                        <button type="submit" name="intent" value="skip_baseline" class="min-h-11 rounded-lg border border-neutral-500 bg-white px-5 py-2 font-semibold text-neutral-800 hover:bg-neutral-100">{{ __('Explicitly skip this baseline') }}</button>
                    @endif
                </div>
                @if ($curriculumActivity['metadata']['response_form'] === 'rating')
                    <a href="{{ isset($curriculumPreview) ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.show', $curriculumPreview) : route('curriculum.confidence-history') }}" class="mt-4 inline-block font-semibold text-indigo-800 underline underline-offset-4">{{ __('View my confidence history') }}</a>
                @endif
            </div>
        </form>
    </main>
@endsection

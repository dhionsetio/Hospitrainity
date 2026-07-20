@extends('layouts.app')

@section('title'){{ $curriculumChapter['title'] }} - {{ __('Canonical Module - Hospitrainity') }}@endsection
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @include('partials.learner-nav')
    @isset($curriculumPreview)
        @include('curriculum.partials.preview-banner')
    @else
        @include('curriculum.partials.active-draft-banner', ['activePackage' => $curriculumChapter['package'], 'showCurriculumEvidence' => $showCurriculumEvidence])
    @endisset

    <main class="container mx-auto max-w-6xl px-4 py-6 sm:px-6 sm:py-8">
        <x-back-control
            :href="isset($curriculumPreview) ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.preview.index', $curriculumPreview) : route('dashboard')"
            :label="isset($curriculumPreview) ? __('Return to curriculum preview') : __('Return to dashboard')"
        />

        <header class="hsp-module-hero mt-5">
            <div>
                <span class="hsp-module-badge"><i class="fa-solid fa-bell-concierge" aria-hidden="true"></i> {{ __('Module :number', ['number' => $curriculumChapter['module']]) }}</span>
                <h1 class="mt-4 text-4xl font-bold text-neutral-900">{{ $curriculumChapter['title'] }}</h1>
                <p class="mt-3 max-w-2xl text-lg text-neutral-700">{{ __('Work through one learning step at a time. Each step contains up to five short sections.') }}</p>
            </div>
            <div class="hsp-module-journey" aria-hidden="true">
                @foreach($curriculumChapter['steps'] as $step)
                    <span>{{ $step['number'] }}</span>
                @endforeach
            </div>
        </header>

        <section class="mt-9" aria-labelledby="outcomes-heading">
            <h2 id="outcomes-heading" class="text-2xl font-bold text-neutral-900">{{ __('What you will practise') }}</h2>
            <ul class="hsp-outcome-list mt-4">
                @foreach ($curriculumChapter['outcomes'] as $outcome)
                    <li>
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        <div>
                            <p class="text-neutral-800">{{ $outcome['statement'] }}</p>
                        @if($showCurriculumEvidence)
                            <details class="mt-3 text-sm text-neutral-700">
                                <summary class="cursor-pointer font-semibold text-indigo-800">{{ __('Outcome evidence') }}</summary>
                                <p class="mt-2 font-mono text-xs">{{ $outcome['code'] }} · {{ $outcome['type'] }} · {{ __('Provisional band: :band', ['band' => $outcome['provisional_band']]) }}</p>
                            </details>
                        @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="mt-10" aria-labelledby="sections-heading">
            <div class="max-w-3xl">
                <h2 id="sections-heading" class="text-2xl font-bold text-neutral-900">{{ __('Your learning journey') }}</h2>
                <p class="mt-2 text-neutral-700">{{ __('Open any section. At the end of each step, you will see a short wrap-up before continuing.') }}</p>
            </div>
            <div class="hsp-learning-journey mt-6">
                @foreach ($curriculumChapter['steps'] as $step)
                    <section class="hsp-learning-step" aria-labelledby="learning-step-{{ $step['number'] }}">
                        <div class="hsp-learning-step__marker" aria-hidden="true">{{ $step['number'] }}</div>
                        <div class="hsp-learning-step__body">
                            <header class="flex flex-wrap items-baseline justify-between gap-2">
                                <h3 id="learning-step-{{ $step['number'] }}" class="text-xl font-bold text-neutral-900">{{ __('Learning step :number', ['number' => $step['number']]) }}</h3>
                                <p class="text-sm font-semibold text-neutral-600">{{ trans_choice(':count section|:count sections', $step['count'], ['count' => $step['count']]) }}</p>
                            </header>
                            <ol class="mt-4 grid gap-3 md:grid-cols-2">
                                @foreach($step['sections'] as $section)
                                    @php
                                        $sectionUrl = isset($curriculumPreview)
                                            ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.preview.sections.show', [$curriculumPreview, $section['code']])
                                            : route('curriculum.sections.show', $section['code']);
                                    @endphp
                                    <li class="hsp-lesson-item">
                                        <a href="{{ $sectionUrl }}" class="hsp-lesson-link group">
                                            <span class="hsp-lesson-link__number">{{ $loop->iteration }}</span>
                                            <span class="min-w-0 flex-1">
                                                <span class="block font-bold text-neutral-900 group-hover:text-indigo-800">{{ $section['title'] }}</span>
                                                <span class="mt-1 flex items-center gap-2 text-sm text-neutral-600">
                                                    <i class="fa-solid {{ $section['activity'] ? 'fa-bolt' : 'fa-book-open' }}" aria-hidden="true"></i>
                                                    {{ $section['activity'] ? __('Practice included') : __('Short lesson') }}
                                                </span>
                                            </span>
                                            <i class="fa-solid fa-arrow-right hsp-card-link__arrow" aria-hidden="true"></i>
                                            <span class="sr-only">{{ __('Open section') }}</span>
                                        </a>
                                        @if($showCurriculumEvidence)
                                            <details class="border-t border-neutral-200 px-4 py-3 text-sm text-neutral-700">
                                        <summary class="cursor-pointer font-semibold text-indigo-800">{{ __('Section evidence') }}</summary>
                                        <p class="mt-2 font-mono text-xs">{{ $section['code'] }}@if($section['activity']) · {{ $section['activity']['response_form'] }} · {{ $section['activity']['scoring_mode'] }}@endif</p>
                                            </details>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    </section>
                @endforeach
            </div>
        </section>

        @if($showCurriculumEvidence)
            <details class="mt-8 rounded-lg border border-neutral-300 bg-white p-4 text-sm text-neutral-700">
                <summary class="cursor-pointer font-semibold">{{ __('Source and lifecycle evidence') }}</summary>
                <dl class="mt-3 grid gap-2 md:grid-cols-2">
                    <div><dt class="font-semibold">{{ __('Package version') }}</dt><dd>{{ $curriculumChapter['package']->content_version }}</dd></div>
                    <div><dt class="font-semibold">{{ __('Content code') }}</dt><dd class="font-mono">{{ $curriculumChapter['code'] }}</dd></div>
                    <div><dt class="font-semibold">{{ __('Lifecycle') }}</dt><dd>{{ $curriculumChapter['status'] }}</dd></div>
                    <div><dt class="font-semibold">{{ __('Source artifact') }}</dt><dd>{{ $curriculumChapter['source_locator']['artifact'] ?? __('Not declared') }}</dd></div>
                    <div><dt class="font-semibold">{{ __('Source block') }}</dt><dd>{{ $curriculumChapter['source_locator']['body_index'] ?? __('Not declared') }}</dd></div>
                    <div><dt class="font-semibold">SHA-256</dt><dd class="break-all font-mono text-xs">{{ $curriculumChapter['source_locator']['normalized_text_sha256'] ?? __('Not declared') }}</dd></div>
                </dl>
            </details>
        @endif
    </main>
@endsection

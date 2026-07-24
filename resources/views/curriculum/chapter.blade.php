@extends('layouts.app')

@section('title'){{ $curriculumChapter['title'] }} - {{ __('Module - Hospitrainity') }}@endsection
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

        @php
            $displayTitle = ($curriculumChapter['module'] === 1) ? __('Introduction to Customer Care') : $curriculumChapter['title'];
            $totalStepsCount = count($curriculumChapter['steps']);
            $firstSectionCode = $curriculumChapter['steps'][0]['sections'][0]['code'] ?? null;
            $firstSectionUrl = $firstSectionCode
                ? (isset($curriculumPreview)
                    ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.preview.sections.show', [$curriculumPreview, $firstSectionCode])
                    : route('curriculum.sections.show', $firstSectionCode))
                : null;
        @endphp

        <header class="hsp-module-hero mt-5 flex flex-wrap items-center justify-between gap-6">
            <div class="flex-1 min-w-[280px]">
                <span class="hsp-module-badge"><i class="fa-solid fa-bell-concierge" aria-hidden="true"></i> {{ __('Module :number', ['number' => $curriculumChapter['module']]) }}</span>
                <h1 class="mt-4 text-4xl font-bold text-neutral-900">{{ $displayTitle }}</h1>
                <p class="mt-3 max-w-2xl text-lg text-neutral-700">{{ __('Work through one learning step at a time. Each step contains up to five short sections.') }}</p>
            </div>
            <div class="flex flex-col items-center justify-center rounded-2xl border border-indigo-100 bg-indigo-50/80 px-8 py-5 text-center shadow-sm">
                <span class="text-4xl font-black text-indigo-700">{{ $totalStepsCount }}</span>
                <span class="mt-1 text-sm font-bold uppercase tracking-wider text-indigo-900">{{ trans_choice('Step|Steps', $totalStepsCount) }}</span>
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
                        @if($showCurriculumEvidence && Auth::user()?->isSuperAdmin())
                            <details class="mt-3 text-sm text-neutral-700">
                                <summary class="cursor-pointer font-semibold text-indigo-800">{{ __('Outcome evidence') }}</summary>
                                <p class="mt-2 font-mono text-xs">{{ $outcome['code'] }} · {{ $outcome['type'] }} · {{ __('Provisional band: :band', ['band' => $outcome['provisional_band']]) }}</p>
                            </details>
                        @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            @if($firstSectionUrl)
                <div class="mt-8 flex justify-center">
                    <a href="{{ $firstSectionUrl }}" class="inline-flex min-h-12 items-center gap-3 rounded-xl bg-indigo-600 px-8 py-3.5 text-lg font-bold text-white shadow-md hover:bg-indigo-700 transition-all">
                        <span>{{ __('Start') }}</span>
                        <i class="fa-solid fa-arrow-right text-base" aria-hidden="true"></i>
                    </a>
                </div>
            @endif
        </section>

        <section class="mt-10" aria-labelledby="sections-heading" data-curriculum-accordion>
            <div class="max-w-3xl">
                <h2 id="sections-heading" class="text-2xl font-bold text-neutral-900">{{ __('Lesson Sections') }}</h2>
                <p class="mt-2 text-neutral-700">{{ __('Open any section.') }}</p>
            </div>
            <div class="hsp-learning-journey mt-6">
                @foreach ($curriculumChapter['steps'] as $step)
                    <section class="hsp-learning-step relative pb-6" aria-labelledby="learning-step-{{ $step['number'] }}">
                        <div class="flex items-start gap-4">
                            <button type="button"
                                    data-step-trigger="step-{{ $step['number'] }}"
                                    aria-expanded="false"
                                    aria-controls="step-content-{{ $step['number'] }}"
                                    aria-label="{{ __('Toggle Learning step :number sections', ['number' => $step['number']]) }}"
                                    class="hsp-learning-step__marker cursor-pointer transition-transform hover:scale-105 focus:ring-4 focus:ring-indigo-300">
                                {{ $step['number'] }}
                            </button>
                            <div class="flex-1">
                                <header class="flex flex-wrap items-baseline justify-between gap-2 pt-1">
                                    <h3 id="learning-step-{{ $step['number'] }}" class="text-xl font-bold text-neutral-900">{{ __('Learning step :number', ['number' => $step['number']]) }}</h3>
                                    <p class="text-sm font-semibold text-neutral-600">{{ trans_choice(':count section|:count sections', $step['count'], ['count' => $step['count']]) }}</p>
                                </header>
                                <div data-step-content="step-{{ $step['number'] }}" id="step-content-{{ $step['number'] }}" class="mt-4">
                                    <ol class="grid gap-3 md:grid-cols-2">
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
                                                @if($showCurriculumEvidence && Auth::user()?->isSuperAdmin())
                                                    <details class="border-t border-neutral-200 px-4 py-3 text-sm text-neutral-700">
                                                        <summary class="cursor-pointer font-semibold text-indigo-800">{{ __('Section evidence') }}</summary>
                                                        <p class="mt-2 font-mono text-xs">{{ $section['code'] }}@if($section['activity']) · {{ $section['activity']['response_form'] }} · {{ $section['activity']['scoring_mode'] }}@endif</p>
                                                    </details>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </section>
                @endforeach
            </div>
        </section>

        @if($showCurriculumEvidence && Auth::user()?->isSuperAdmin())
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

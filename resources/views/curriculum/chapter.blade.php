@extends('layouts.app')

@section('title'){{ $curriculumChapter['title'] }} - {{ __('Canonical Module - Hospitrainity') }}@endsection
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @include('partials.learner-nav')
    @isset($curriculumPreview)
        @include('curriculum.partials.preview-banner')
    @else
        @include('curriculum.partials.active-draft-banner', ['activePackage' => $curriculumChapter['package']])
    @endisset

    <main class="container mx-auto px-6 py-8">
        <a href="{{ isset($curriculumPreview) ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.preview.index', $curriculumPreview) : route('dashboard') }}" class="text-sm font-semibold text-indigo-700 hover:underline">&larr; {{ isset($curriculumPreview) ? __('admin.back_to_preview') : __('Back to Dashboard') }}</a>

        <header class="mt-4 rounded-xl bg-white p-6 shadow">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-indigo-100 px-3 py-1 text-sm font-semibold text-indigo-700">{{ __('Module :number', ['number' => $curriculumChapter['module']]) }}</span>
            </div>
            <h1 class="mt-3 text-4xl font-bold text-neutral-900">{{ $curriculumChapter['title'] }}</h1>
        </header>

        <section class="mt-8 rounded-xl bg-white p-6 shadow" aria-labelledby="outcomes-heading">
            <h2 id="outcomes-heading" class="text-2xl font-bold text-neutral-900">{{ __('Learning outcomes') }}</h2>
            <ul class="mt-4 space-y-3">
                @foreach ($curriculumChapter['outcomes'] as $outcome)
                    <li class="rounded-lg border border-neutral-200 p-4">
                        <p class="text-neutral-800">{{ $outcome['statement'] }}</p>
                        <details class="mt-3 text-sm text-neutral-700">
                            <summary class="cursor-pointer font-semibold text-indigo-800">{{ __('Outcome evidence') }}</summary>
                            <p class="mt-2 font-mono text-xs">{{ $outcome['code'] }} · {{ $outcome['type'] }} · {{ __('Provisional band: :band', ['band' => $outcome['provisional_band']]) }}</p>
                        </details>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="mt-8" aria-labelledby="sections-heading">
            <h2 id="sections-heading" class="text-2xl font-bold text-neutral-900">{{ __('Lesson sections') }}</h2>
            <div class="mt-4 space-y-3">
                @foreach ($curriculumChapter['sections'] as $section)
                    <article class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-col justify-between gap-4 md:flex-row md:items-center">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wide text-neutral-500">{{ __('Section :number', ['number' => $section['order']]) }}</p>
                                <h3 class="mt-1 text-lg font-bold text-neutral-900">{{ $section['title'] }}</h3>
                                @if ($section['activity'])
                                    <p class="mt-2 text-sm text-neutral-600">{{ __('Includes an interactive activity') }}</p>
                                @else
                                    <p class="mt-2 text-sm text-neutral-500">{{ __('Reading section') }}</p>
                                @endif
                                <details class="mt-2 text-sm text-neutral-700">
                                    <summary class="cursor-pointer font-semibold text-indigo-800">{{ __('Section evidence') }}</summary>
                                    <p class="mt-2 font-mono text-xs">{{ $section['code'] }}@if($section['activity']) · {{ $section['activity']['response_form'] }} · {{ $section['activity']['scoring_mode'] }}@endif</p>
                                </details>
                            </div>
                            <a href="{{ isset($curriculumPreview) ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.preview.sections.show', [$curriculumPreview, $section['code']]) : route('curriculum.sections.show', $section['code']) }}" class="self-start rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700">{{ __('Open section') }}</a>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

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
    </main>
@endsection

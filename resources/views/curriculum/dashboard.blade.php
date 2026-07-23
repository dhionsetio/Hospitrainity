@extends('layouts.app')

@section('title', __('Learning Dashboard - Hospitrainity'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @include('partials.learner-nav')
    @isset($curriculumPreview)
        @include('curriculum.partials.preview-banner')
    @else
        @include('curriculum.partials.active-draft-banner', ['activePackage' => $package, 'showCurriculumEvidence' => $showCurriculumEvidence])
    @endisset

    <main class="container mx-auto px-6 py-8">
        <header class="mb-8">
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-700">{{ __('Hospitality English learning') }}</p>
            <h1 class="mt-2 text-3xl font-bold text-neutral-900">{{ $greeting ?? __('Welcome Back!') }}</h1>
            <p class="mt-2 text-neutral-600">{{ __('Continue through the available Hospitrainity modules and activities.') }}</p>
            @isset($nextAction)
                @include('partials.next-action', ['nextAction' => $nextAction])
            @endisset
        </header>

        @isset($classAnnouncements)
            @if($classAnnouncements->isNotEmpty())
                <section class="mb-8 rounded-xl border border-indigo-200 bg-white p-5 sm:p-6" aria-labelledby="class-instructions-heading">
                    <h2 id="class-instructions-heading" class="text-2xl font-bold text-neutral-950">{{ __('classes.instructions') }}</h2>
                    <p class="mt-2 max-w-3xl text-neutral-600">{{ __('classes.instructions_learner_intro') }}</p>
                    <div class="mt-5 divide-y divide-neutral-200">
                        @foreach($classAnnouncements as $announcement)
                            <article class="py-5 first:pt-0 last:pb-0">
                                <p class="text-sm font-semibold text-indigo-700">
                                    {{ $announcement->module?->payloadData()['title'] ?? __('classes.class_wide') }}
                                </p>
                                <h3 class="mt-1 text-lg font-bold text-neutral-950">{{ $announcement->title }}</h3>
                                <p class="mt-2 max-w-3xl whitespace-pre-wrap text-neutral-700">{{ $announcement->body }}</p>
                                <p class="mt-3 text-sm text-neutral-600">{{ __('classes.posted_by', ['name' => $announcement->createdBy?->name ?? __('Instructor')]) }}</p>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif
        @endisset

        <section class="mb-8 grid gap-5 lg:grid-cols-[minmax(0,1.35fr)_minmax(18rem,0.65fr)]" aria-labelledby="practice-tools-heading">
            <div class="rounded-xl border border-neutral-200 bg-white p-5 sm:p-6">
                <h2 id="practice-tools-heading" class="text-2xl font-bold text-neutral-950">{{ __('engagement.practice_tools') }}</h2>
                <p class="mt-2 text-neutral-600">{{ __('engagement.practice_tools_intro') }}</p>
                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <a href="{{ route('assistant.index') }}" class="hsp-action flex min-h-11 items-start gap-3 rounded-lg bg-indigo-700 p-4 font-semibold text-white hover:bg-indigo-800">
                        <i class="fa-solid fa-comments mt-1" aria-hidden="true"></i>
                        <span><span class="block">{{ __('engagement.assistant') }}</span><span class="mt-1 block text-sm font-normal text-indigo-100">{{ __('engagement.assistant_description') }}</span></span>
                    </a>
                    <a href="{{ route('responses.index') }}" class="hsp-action flex min-h-11 items-start gap-3 rounded-lg border border-indigo-700 bg-white p-4 font-semibold text-indigo-800 hover:bg-indigo-50">
                        <i class="fa-solid fa-file-pen mt-1" aria-hidden="true"></i>
                        <span><span class="block">{{ __('engagement.saved_writing') }}</span><span class="mt-1 block text-sm font-normal text-neutral-600">{{ __('engagement.saved_writing_description') }}</span></span>
                    </a>
                </div>
            </div>

            <div class="rounded-xl border border-neutral-200 bg-white p-5 sm:p-6">
                <h2 class="text-xl font-bold text-neutral-950">{{ __('engagement.review_title') }}</h2>
                @if(Auth::user()->learning_streak_enabled)
                    <p class="mt-3 font-semibold text-indigo-800">
                        {{ $learningStreak > 0 ? __('engagement.streak', ['count' => $learningStreak]) : __('engagement.streak_zero') }}
                    </p>
                    <p class="mt-1 text-sm text-neutral-600">{{ __('engagement.streak_note') }}</p>
                @endif
                @if($reviewQueue === [])
                    <p class="mt-4 text-neutral-700">{{ __('engagement.review_empty') }}</p>
                @else
                    <ul class="mt-4 space-y-3">
                        @foreach($reviewQueue as $review)
                            <li class="flex flex-wrap items-center justify-between gap-3 border-t border-neutral-200 pt-3 first:border-t-0 first:pt-0">
                                <span><span class="block font-semibold text-neutral-900">{{ $review['title'] }}</span><span class="text-sm text-neutral-600">{{ __('engagement.review_due') }}</span></span>
                                <a href="{{ $review['url'] }}" class="inline-flex min-h-11 items-center rounded-lg border border-indigo-700 px-4 py-2 font-semibold text-indigo-800 hover:bg-indigo-50">{{ __('engagement.open_review') }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </section>

        @if($showCurriculumEvidence && Auth::user()?->isSuperAdmin())
            <div class="mb-8 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="note">
                <p class="font-medium">{{ __('The canonical manuscript is in English. Interface controls follow your selected language, while source content is shown without invented translation.') }}</p>
            </div>

            <details class="mb-8 rounded-lg border border-neutral-300 bg-white p-4 text-sm text-neutral-700">
                <summary class="cursor-pointer font-semibold">{{ __('Source and lifecycle evidence') }}</summary>
                <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                    <div><dt class="font-semibold">{{ __('Package version') }}</dt><dd>{{ $package->content_version }}</dd></div>
                    <div><dt class="font-semibold">{{ __('Lifecycle') }}</dt><dd>{{ $package->lifecycle_status }}</dd></div>
                </dl>
                <p class="mt-3">{{ $package->projection_meta['notice'] }}</p>
            </details>
        @endif

        <div id="learning-modules" tabindex="-1" class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($chapters as $chapter)
                <article class="flex flex-col overflow-hidden rounded-lg bg-white shadow-lg">
                    <div class="flex-grow p-6">
                        <span class="rounded-full bg-indigo-100 px-3 py-1 text-sm font-semibold text-indigo-700">{{ __('Module :number', ['number' => $chapter['module']]) }}</span>
                        <h2 class="mt-4 text-xl font-bold text-neutral-900">{{ $chapter['title'] }}</h2>
                        @if($showCurriculumEvidence && Auth::user()?->isSuperAdmin())
                            <details class="mt-3 text-xs text-neutral-600">
                                <summary class="cursor-pointer font-semibold">{{ __('Technical evidence') }}</summary>
                                <dl class="mt-2 space-y-1">
                                    <div><dt class="inline font-semibold">{{ __('Content code') }}:</dt> <dd class="inline font-mono">{{ $chapter['code'] }}</dd></div>
                                    <div><dt class="inline font-semibold">{{ __('Lifecycle') }}:</dt> <dd class="inline">{{ $chapter['status'] }}</dd></div>
                                </dl>
                            </details>
                        @endif
                    </div>
                    <div class="px-6 pb-4">
                        @if ($chapter['activities_count'] > 0)
                            <div class="mb-1 flex justify-between">
                                <span class="font-medium text-neutral-700">{{ __('Activity progress') }}</span>
                                <span class="text-sm font-medium text-neutral-700">{{ $chapter['progress'] }}%</span>
                            </div>
                            <progress class="hsp-progress" value="{{ $chapter['progress'] }}" max="100" aria-label="{{ __('Progress for :module', ['module' => $chapter['title']]) }}">{{ $chapter['progress'] }}%</progress>
                        @else
                            <p class="text-sm text-neutral-500">{{ __('This introductory chapter has no tracked assessment activity.') }}</p>
                        @endif
                    </div>
                    <footer class="flex items-center justify-between border-t border-neutral-200 bg-neutral-50 p-4">
                        <span class="text-sm text-neutral-600">{{ $chapter['sections_count'] }} {{ __('sections') }} · {{ $chapter['activities_count'] }} {{ __('activities') }}</span>
                        <a href="{{ isset($curriculumPreview) ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.preview.chapters.show', [$curriculumPreview, $chapter['code']]) : route('curriculum.chapters.show', $chapter['code']) }}" class="inline-flex min-h-11 items-center rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('Open module') }}</a>
                    </footer>
                </article>
            @endforeach
        </div>
    </main>
@endsection

@extends('layouts.app')

@section('title', __('Learning Dashboard - Hospitrainity'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @include('partials.learner-nav')
    @isset($curriculumPreview)
        @include('curriculum.partials.preview-banner')
    @else
        @include('curriculum.partials.active-draft-banner', ['activePackage' => $package])
    @endisset

    <main class="container mx-auto px-6 py-8">
        <header class="mb-8">
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-700">{{ __('Hospitality English learning') }}</p>
            <h1 class="mt-2 text-3xl font-bold text-neutral-900">{{ __('Welcome Back!') }}</h1>
            <p class="mt-2 text-neutral-600">{{ __('Continue through the available Hospitrainity modules and activities.') }}</p>
        </header>

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

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($chapters as $chapter)
                <article class="flex flex-col overflow-hidden rounded-lg bg-white shadow-lg">
                    <div class="flex-grow p-6">
                        <span class="rounded-full bg-indigo-100 px-3 py-1 text-sm font-semibold text-indigo-700">{{ __('Module :number', ['number' => $chapter['module']]) }}</span>
                        <h2 class="mt-4 text-xl font-bold text-neutral-900">{{ $chapter['title'] }}</h2>
                        <details class="mt-3 text-xs text-neutral-600">
                            <summary class="cursor-pointer font-semibold">{{ __('Technical evidence') }}</summary>
                            <dl class="mt-2 space-y-1">
                                <div><dt class="inline font-semibold">{{ __('Content code') }}:</dt> <dd class="inline font-mono">{{ $chapter['code'] }}</dd></div>
                                <div><dt class="inline font-semibold">{{ __('Lifecycle') }}:</dt> <dd class="inline">{{ $chapter['status'] }}</dd></div>
                            </dl>
                        </details>
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
                        <a href="{{ isset($curriculumPreview) ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.preview.chapters.show', [$curriculumPreview, $chapter['code']]) : route('curriculum.chapters.show', $chapter['code']) }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('Open module') }}</a>
                    </footer>
                </article>
            @endforeach
        </div>
    </main>
@endsection

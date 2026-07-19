@extends('layouts.app')

@section('title', __('Canonical Curriculum - Hospitrainity'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @include('partials.learner-nav')
    @isset($curriculumPreview)
        @include('curriculum.partials.preview-banner')
    @endisset

    <main class="container mx-auto px-6 py-8">
        <header class="mb-8">
            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-700">{{ __('Canonical curriculum') }} · {{ $package->content_version }}</p>
            <h1 class="mt-2 text-3xl font-bold text-neutral-900">{{ __('Welcome Back!') }}</h1>
            <p class="mt-2 text-neutral-600">{{ __('Continue through the published Hospitrainity chapters and their source-traceable activities.') }}</p>
        </header>

        <div class="mb-8 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="note">
            <p>{{ $package->projection_meta['notice'] }}</p>
            <p class="mt-2 font-medium">{{ __('The canonical manuscript is in English. Interface controls follow your selected language, while source content is shown without invented translation.') }}</p>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($chapters as $chapter)
                <article class="flex flex-col overflow-hidden rounded-lg bg-white shadow-lg">
                    <div class="flex-grow p-6">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-indigo-100 px-3 py-1 text-sm font-semibold text-indigo-700">{{ __('Module :number', ['number' => $chapter['module']]) }}</span>
                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold uppercase text-emerald-800">{{ $chapter['status'] }}</span>
                        </div>
                        <h2 class="mt-4 text-xl font-bold text-neutral-900">{{ $chapter['title'] }}</h2>
                        <p class="mt-2 font-mono text-xs text-neutral-500">{{ $chapter['code'] }}</p>
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

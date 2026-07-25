@extends('layouts.app')

@section('title', __('admin.legacy_evidence_title'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    <main class="container mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
        <header class="rounded-xl border border-amber-300 bg-amber-50 p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 class="text-3xl font-bold text-amber-950">{{ __('admin.legacy_evidence') }}</h1>
                        <p class="mt-2 max-w-4xl text-amber-900">{{ __('admin.legacy_evidence_summary') }}</p>
                    </div>
                    <span class="rounded-full border border-amber-300 bg-white px-3 py-1 text-xs font-semibold uppercase text-amber-950">{{ __('admin.read_only') }}</span>
                </div>
            </header>

            <section class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3" aria-labelledby="legacy-evidence-destinations">
                <h2 id="legacy-evidence-destinations" class="sr-only">{{ __('admin.legacy_evidence_destinations') }}</h2>
                @foreach([
                    'modules' => ['label' => 'legacy_modules', 'icon' => 'fa-layer-group'],
                    'lessons' => ['label' => 'legacy_lessons', 'icon' => 'fa-book-open'],
                    'vocabularies' => ['label' => 'legacy_vocabulary', 'icon' => 'fa-book'],
                    'materials' => ['label' => 'legacy_materials', 'icon' => 'fa-file-lines'],
                    'exercises' => ['label' => 'legacy_exercises', 'icon' => 'fa-puzzle-piece'],
                ] as $route => $definition)
                    <article class="rounded-xl bg-white p-5 shadow">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3">
                                <i class="fas {{ $definition['icon'] }} fa-fw text-amber-700" aria-hidden="true"></i>
                                <h3 class="font-bold text-neutral-950">{{ __('admin.'.$definition['label']) }}</h3>
                            </div>
                            <span class="text-2xl font-bold tabular-nums text-neutral-950">{{ $counts[$route] }}</span>
                        </div>
                        <a href="{{ route($routePrefix.'.'.$route.'.index') }}" class="mt-5 inline-block font-semibold text-indigo-700 underline">{{ __('admin.review_evidence') }}</a>
                    </article>
                @endforeach
            </section>
        </main>
@endsection

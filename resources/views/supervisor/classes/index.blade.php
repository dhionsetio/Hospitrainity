@extends('layouts.app')

@section('title', __('classes.index_title').' - Hospitrainity')
@section('bodyClass', 'bg-neutral-100')

@section('content')
    <div class="flex min-h-screen flex-col bg-neutral-100 md:flex-row">
        @include('supervisor.sidebar')

        <main class="min-w-0 flex-1 p-4 pb-24 sm:p-6 md:p-10">
            <header class="mx-auto flex max-w-6xl flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-semibold text-indigo-700">{{ $institution->displayName(app()->getLocale()) }}</p>
                    <h1 class="mt-1 text-3xl font-bold text-neutral-950">{{ __('classes.index_title') }}</h1>
                    <p class="mt-2 max-w-2xl text-neutral-600">{{ __('classes.index_intro') }}</p>
                </div>
                @if($canCreate)
                    <a href="{{ route('supervisor.classes.create') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-indigo-700 px-5 py-2.5 font-semibold text-white hover:bg-indigo-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        {{ __('classes.create') }}
                    </a>
                @endif
            </header>

            <section class="mx-auto mt-8 max-w-6xl" aria-label="{{ __('classes.index_title') }}">
                <div class="grid gap-4 lg:grid-cols-2">
                    @forelse($classes as $class)
                        @php($primary = $class->teachingAssignments->first()?->membership?->user)
                        <article class="rounded-xl border border-neutral-200 bg-white p-5 shadow-sm">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-indigo-700">{{ $class->course->title }}</p>
                                    <h2 class="mt-1 text-xl font-bold text-neutral-950">{{ $class->title }}</h2>
                                </div>
                                <span class="rounded-full bg-neutral-100 px-3 py-1 text-xs font-bold uppercase tracking-wide text-neutral-700">
                                    {{ __('classes.status.'.$class->status->value) }}
                                </span>
                            </div>
                            <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-2">
                                <div>
                                    <dt class="text-neutral-500">{{ __('classes.primary_instructor') }}</dt>
                                    <dd class="mt-1 font-semibold text-neutral-900">{{ $primary?->name ?? __('classes.unassigned') }}</dd>
                                </div>
                                <div>
                                    <dt class="text-neutral-500">{{ __('classes.roster') }}</dt>
                                    <dd class="mt-1 font-semibold text-neutral-900">{{ trans_choice('classes.active_learners', $class->active_learners_count, ['count' => $class->active_learners_count]) }}</dd>
                                </div>
                                @if($class->term_label)
                                    <div class="sm:col-span-2">
                                        <dt class="text-neutral-500">{{ __('classes.term_label') }}</dt>
                                        <dd class="mt-1 font-semibold text-neutral-900">{{ $class->term_label }}</dd>
                                    </div>
                                @endif
                            </dl>
                            <a href="{{ route('supervisor.classes.show', $class) }}" class="mt-5 inline-flex min-h-11 items-center gap-2 rounded-lg border border-indigo-700 px-4 py-2 font-semibold text-indigo-800 hover:bg-indigo-50">
                                {{ __('classes.open_class') }}
                                <i class="fas fa-arrow-right" aria-hidden="true"></i>
                            </a>
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-neutral-400 bg-white p-8 text-center lg:col-span-2">
                            <h2 class="text-lg font-bold text-neutral-950">{{ __('classes.no_classes') }}</h2>
                            <p class="mx-auto mt-2 max-w-xl text-neutral-600">{{ __('classes.no_classes_hint') }}</p>
                        </div>
                    @endforelse
                </div>
                <div class="mt-6">{{ $classes->links() }}</div>
            </section>
        </main>
    </div>
@endsection

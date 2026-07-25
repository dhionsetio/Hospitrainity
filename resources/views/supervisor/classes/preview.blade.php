@extends('layouts.app')

@section('title', __('classes.preview_title').' - '.$offering->title)
@section('bodyClass', 'bg-neutral-100')

@section('content')
    <main class="container mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
        <div class="mx-auto max-w-4xl">
                <a href="{{ route('supervisor.classes.show', $offering) }}" class="inline-flex min-h-11 items-center gap-2 font-semibold text-indigo-800"><i class="fas fa-arrow-left" aria-hidden="true"></i>{{ $offering->title }}</a>
                <div class="mt-4 rounded-xl border-2 border-amber-400 bg-amber-50 p-5 text-amber-950" role="status">
                    <p class="text-sm font-bold uppercase tracking-wide">{{ __('classes.preview_title') }}</p>
                    <p class="mt-2">{{ __('classes.preview_notice') }}</p>
                </div>
                <header class="mt-8">
                    <p class="font-semibold text-indigo-700">{{ $offering->course->title }}</p>
                    <h1 class="mt-1 text-3xl font-bold text-neutral-950">{{ $offering->title }}</h1>
                </header>
                <section class="mt-8 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm" aria-labelledby="preview-modules-heading">
                    <h2 id="preview-modules-heading" class="text-xl font-bold text-neutral-950">{{ __('classes.preview_modules') }}</h2>
                    <ol class="mt-5 space-y-3">
                        @forelse($offering->revision->modules as $module)
                            <li class="flex gap-4 rounded-lg border border-neutral-200 p-4">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-indigo-100 font-bold text-indigo-900">{{ $module->position }}</span>
                                <h3 class="font-bold text-neutral-950">{{ $module->curriculumEntity->payloadData()['title'] ?? __('Untitled module') }}</h3>
                            </li>
                        @empty
                            <li class="rounded-lg border border-dashed border-neutral-300 p-6 text-neutral-600">{{ __('classes.no_modules') }}</li>
                        @endforelse
                    </ol>
                </section>
            </div>
        </main>
@endsection

@extends('layouts.app')

@section('title'){{ __('Learning step :number wrap-up', ['number' => $curriculumStep['number']]) }} - {{ $curriculumChapter['title'] }}@endsection
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @include('partials.learner-nav')
    @include('curriculum.partials.active-draft-banner', ['activePackage' => $curriculumChapter['package'], 'showCurriculumEvidence' => $showCurriculumEvidence])

    <main class="container mx-auto max-w-4xl px-4 py-6 sm:px-6 sm:py-8">
        <x-back-control
            :href="route('curriculum.sections.show', $curriculumStep['last']['code'])"
            :label="__('Return to the last section')"
        />

        <section class="hsp-checkpoint mt-5" aria-labelledby="checkpoint-title">
            <div class="hsp-checkpoint__mark" aria-hidden="true">
                <i class="fa-solid fa-check"></i>
            </div>
            <p class="font-semibold text-indigo-800">{{ __('Learning step :number of :total', ['number' => $curriculumStep['number'], 'total' => $curriculumStep['total']]) }}</p>
            <h1 id="checkpoint-title" class="mt-2 text-4xl font-bold text-neutral-950">{{ __('You reached the step wrap-up') }}</h1>
            <p class="mt-3 max-w-2xl text-lg text-neutral-700">{{ __('Review the parts you covered, then continue when you are ready.') }}</p>

            <div class="hsp-checkpoint__recap mt-7">
                <h2 class="text-xl font-bold text-neutral-900">{{ __('This step covered') }}</h2>
                <ol class="mt-4 space-y-3">
                    @foreach($curriculumStep['sections'] as $section)
                        <li>
                            <span>{{ $loop->iteration }}</span>
                            <a href="{{ route('curriculum.sections.show', $section['code']) }}">{{ $section['title'] }}</a>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="mt-7 flex flex-wrap gap-3">
                @if($nextStep)
                    <a href="{{ route('curriculum.sections.show', $nextStep['first']['code']) }}" class="hsp-action inline-flex min-h-11 items-center gap-3 rounded-lg bg-indigo-700 px-5 py-3 font-semibold text-white">
                        {{ __('Continue to learning step :number', ['number' => $nextStep['number']]) }}
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('curriculum.chapters.show', $curriculumChapter['code']) }}" class="hsp-action inline-flex min-h-11 items-center rounded-lg border border-indigo-700 px-5 py-3 font-semibold text-indigo-800">{{ __('Review module journey') }}</a>
                @else
                    <a href="{{ route('dashboard') }}" class="hsp-action inline-flex min-h-11 items-center gap-3 rounded-lg bg-indigo-700 px-5 py-3 font-semibold text-white">
                        {{ __('Return to learning dashboard') }}
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                    <a href="{{ route('curriculum.chapters.show', $curriculumChapter['code']) }}" class="hsp-action inline-flex min-h-11 items-center rounded-lg border border-indigo-700 px-5 py-3 font-semibold text-indigo-800">{{ __('Review this module') }}</a>
                @endif
            </div>
        </section>
    </main>
@endsection

@extends('layouts.app')

@section('title'){{ $lesson->title }} - {{ __('Lesson - Hospitrainity') }}@endsection
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @include('partials.learner-nav')

    <main class="container mx-auto px-6 py-8">
        <div class="mb-8">
            <x-back-control :href="route('modules.show', $lesson->module)" :label="__('Back to Module:').' '.$lesson->module->title" />
            <h1 class="text-4xl font-bold text-neutral-800 mt-2">{{ $lesson->title }}</h1>
        </div>

        <!-- Bagian Kosakata -->
        <div>
            <h2 class="text-2xl font-bold text-neutral-800 mb-4">{{ __('Vocabulary and Phrases') }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse ($lesson->vocabularies as $vocabulary_category)
                <a href="{{ route('lessons.practice', ['lesson' => $lesson, 'vocabulary' => $vocabulary_category]) }}" class="block p-6 bg-white rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-transform duration-300">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-neutral-900">{{ $vocabulary_category->category }}</h3>
                        <span class="text-sm font-medium text-indigo-600 bg-indigo-100 px-3 py-1 rounded-full">{{ count($vocabulary_category->items) }} {{ __('words') }}</span>
                    </div>
                    <p class="mt-2 text-sm text-neutral-600">{{ __('Start learning the vocabulary in this category.') }}</p>
                    <div class="text-right mt-4 text-indigo-600 font-semibold">
                        {{ __('Start Practice') }} &rarr;
                    </div>
                </a>
                @empty
                <p class="text-neutral-500 md:col-span-2 lg:col-span-3">{{ __('No vocabulary for this lesson yet.') }}</p>
                @endforelse
            </div>
        </div>

        <div>
            <h2 class="text-2xl font-bold text-neutral-800 mb-4 mt-8">{{ __('Materials') }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @forelse ($lesson->materials as $material_category)
                <a href="{{ route('lessons.material.show', ['lesson' => $lesson, 'material' => $material_category]) }}" class="block p-6 bg-white rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-transform duration-300">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-neutral-900">{{ $material_category->type }}</h3>
                        <span class="text-sm font-medium text-indigo-600 bg-indigo-100 px-3 py-1 rounded-full">{{ count($material_category->items) }} {{ __('Items') }}</span>
                    </div>
                    <p class="mt-2 text-sm text-neutral-600">{{ __('View the materials in this category.') }}</p>
                    <div class="text-right mt-4 text-indigo-600 font-semibold">
                        {{ __('View Materials') }} &rarr;
                    </div>
                </a>
                @empty
                <p class="text-neutral-500">{{ __('No materials for this lesson yet.') }}</p>
                @endforelse
            </div>
        </div>

        <div>
            <h2 class="text-2xl font-bold text-neutral-800 mb-4 mt-8">{{ __('Exercises') }}</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @if($lesson->exercises->isNotEmpty())
                <a href="{{ route('lessons.exercise.practice', $lesson) }}" class="block p-6 bg-white rounded-lg shadow-md hover:shadow-xl hover:-translate-y-1 transition-transform duration-300">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-neutral-900">{{ __('Integrated Practice') }}</h3>
                        <span class="text-sm font-medium text-indigo-600 bg-indigo-100 px-3 py-1 rounded-full">{{ count($lesson->exercises) }} {{ __('Exercises') }}</span>
                    </div>
                    <p class="mt-2 text-sm text-neutral-600">{{ __('Complete all exercises for this lesson in one session.') }}</p>
                    <div class="text-right mt-4 text-indigo-600 font-semibold">
                        {{ __('Start Practice') }} &rarr;
                    </div>
                </a>
                @else
                <p class="text-neutral-500">{{ __('No exercises for this lesson yet.') }}</p>
                @endif
            </div>

        </div>

    </main>

@endsection

@extends('layouts.app')

@section('title'){{ $module->title }} - {{ __('Module - Hospitrainity') }}@endsection
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @include('partials.learner-nav')

    <main class="container mx-auto px-6 py-8">
        <!-- Header Modul -->
        <div class="mb-8">
            <a href="{{ route('dashboard') }}" class="text-sm text-indigo-600 hover:underline">&larr; {{ __('Back to Dashboard') }}</a>
            <h1 class="text-4xl font-bold text-neutral-800 mt-2">{{ $module->title }}</h1>
            <p class="text-neutral-600 mt-2">{{ $module->description }}</p>
        </div>

        <!-- Daftar Pelajaran (Lessons) -->
        <div class="bg-white rounded-lg shadow-lg">
            <ul class="divide-y divide-neutral-200">
                @forelse ($module->lessons as $lesson)
                <li>
                    <a href="{{ route('lessons.show', $lesson) }}" class="block hover:bg-neutral-50 p-6">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <span class="flex items-center justify-center w-10 h-10 bg-indigo-100 rounded-full text-indigo-600 font-bold">
                                    {{ $lesson->order }}
                                </span>
                                <div>
                                    <p class="text-lg font-semibold text-neutral-900">{{ $lesson->title }}</p>
                                    <p class="text-sm text-neutral-500">{{ __('Start this lesson to continue.') }}</p>
                                </div>
                            </div>
                            <i class="fas fa-chevron-right text-neutral-400"></i>
                        </div>
                    </a>
                </li>
                @empty
                <li class="p-6 text-center text-neutral-500">
                    {{ __('No lessons are available for this module yet.') }}
                </li>
                @endforelse
            </ul>
        </div>
    </main>


@endsection

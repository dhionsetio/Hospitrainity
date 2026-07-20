@extends('layouts.app')

@section('title', __('Learner Dashboard - Hospitrainity'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @include('partials.learner-nav')

    <!-- Main Content -->
    <main class="container mx-auto px-6 py-8">
        <h1 class="text-3xl font-bold text-neutral-800">{{ __('Welcome Back!') }}</h1>
        <p class="text-neutral-600 mt-2">{{ __('Continue your learning and reach your goals.') }}</p>

        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            @forelse ($modules as $module)
            <div class="bg-white rounded-lg shadow-lg overflow-hidden flex flex-col transform hover:-translate-y-1 transition-transform duration-300">
                <div class="p-6 flex-grow">
                    <span class="text-sm font-semibold text-indigo-600 bg-indigo-100 px-3 py-1 rounded-full">{{ ucfirst($module->level) }}</span>
                    <h2 class="text-xl font-bold text-neutral-800 mt-4 mb-2">{{ $module->title }}</h2>
                    <p class="text-neutral-600 text-sm flex-grow">{{ $module->description }}</p>
                </div>
                <div class="px-6 pb-4">
                    <div class="flex justify-between mb-1">
                        <span class="text-base font-medium text-neutral-700">{{ __('Progress') }}</span>
                        <span class="text-sm font-medium text-neutral-700">{{ round($module->progress) }}%</span>
                    </div>
                    <progress class="hsp-progress" value="{{ $module->progress }}" max="100" aria-label="{{ __('Progress for :module', ['module' => $module->title]) }}">{{ $module->progress }}%</progress>
                </div>
                <div class="bg-neutral-50 p-4 border-t border-neutral-200 flex justify-between items-center">
                    <span class="text-sm text-neutral-500">
                        <i class="fas fa-book-open mr-2"></i>{{ $module->lessons_count }} {{ __('Lessons') }}
                    </span>
                    <a href="{{ route('modules.show', $module) }}" class="bg-indigo-600 text-white py-2 px-4 rounded-lg text-sm font-semibold hover:bg-indigo-700 transition duration-300">{{ __('Start Learning') }}</a>
                </div>
            </div>
            @empty
            <!-- Tampilkan pesan ini jika tidak ada modul yang ditemukan -->
            <div class="col-span-1 md:col-span-2 lg:col-span-3 text-center py-12">
                <p class="text-neutral-500 text-lg">{{ __('Oops! It looks like there are no modules available right now.') }}</p>
            </div>
            @endforelse
        </div>
    </main>


@endsection

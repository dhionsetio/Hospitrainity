@extends('layouts.guest')

@section('title', __('Hospitrainity - Hospitality English Training'))
@section('bodyClass', 'bg-neutral-50')

@section('content')
    <header class="sticky top-0 z-50 bg-white shadow-sm">
        <nav class="container mx-auto flex items-center justify-between px-6 py-4" aria-label="{{ __('Primary navigation') }}">
            <a href="/" class="text-2xl font-bold text-indigo-600">Hospitrainity</a>
            <div class="hidden items-center space-x-6 md:flex">
                <a href="#features" class="text-neutral-600 transition duration-300 hover:text-indigo-600">{{ __('Features') }}</a>
                <a href="#get-started" class="text-neutral-600 transition duration-300 hover:text-indigo-600">{{ __('Get Started') }}</a>
            </div>
            <div class="hidden items-center space-x-4 md:flex">
                <div class="flex items-center gap-1 text-sm text-neutral-500" aria-label="{{ __('Language') }}">
                    <a href="{{ route('locale.switch', 'en') }}" class="font-medium hover:text-indigo-600" lang="en" hreflang="en">EN</a>
                    <span aria-hidden="true">|</span>
                    <a href="{{ route('locale.switch', 'id') }}" class="font-medium hover:text-indigo-600" lang="id" hreflang="id">ID</a>
                </div>
                <a href="{{ route('login') }}" class="font-medium text-indigo-700 hover:text-indigo-900">{{ __('Login') }}</a>
                <a href="{{ route('register') }}" class="rounded-lg bg-indigo-600 px-4 py-2 text-white transition duration-300 hover:bg-indigo-700">{{ __('Create an Account') }}</a>
            </div>
            <button class="rounded md:hidden" id="mobile-menu-button" type="button" aria-controls="mobile-menu" aria-expanded="false" aria-label="{{ __('Open navigation') }}" data-open-label="{{ __('Open navigation') }}" data-close-label="{{ __('Close navigation') }}">
                <i class="fas fa-bars text-2xl text-neutral-700" aria-hidden="true"></i>
            </button>
        </nav>
        <div class="hidden border-t border-neutral-100 md:hidden" id="mobile-menu">
            <a href="#features" class="block px-4 py-2 text-sm hover:bg-neutral-100">{{ __('Features') }}</a>
            <a href="#get-started" class="block px-4 py-2 text-sm hover:bg-neutral-100">{{ __('Get Started') }}</a>
            <div class="flex items-center gap-2 px-4 py-2 text-sm text-neutral-500" aria-label="{{ __('Language') }}">
                <a href="{{ route('locale.switch', 'en') }}" class="font-medium hover:text-indigo-600" lang="en" hreflang="en">EN</a>
                <span aria-hidden="true">|</span>
                <a href="{{ route('locale.switch', 'id') }}" class="font-medium hover:text-indigo-600" lang="id" hreflang="id">ID</a>
            </div>
            <a href="{{ route('login') }}" class="block px-4 py-2 text-center text-sm font-medium text-indigo-700 hover:bg-neutral-100">{{ __('Login') }}</a>
            <a href="{{ route('register') }}" class="block rounded-b-lg bg-indigo-600 px-4 py-2 text-center text-sm text-white hover:bg-indigo-700">{{ __('Create an Account') }}</a>
        </div>
    </header>

    <main>
        <section class="py-20 md:py-28">
            <div class="container mx-auto grid items-center gap-12 px-6 lg:grid-cols-2">
                <div class="text-center lg:text-left">
                    <h1 class="text-4xl font-bold leading-tight text-neutral-800 md:text-6xl">
                        {{ __('Practice Hospitality English at Your Own Pace') }}
                    </h1>
                    <p class="mx-auto mt-4 max-w-2xl text-lg text-neutral-600 md:text-xl lg:mx-0">
                        {{ __('Work through structured modules, vocabulary, materials, and interactive exercises while tracking completed activities.') }}
                    </p>
                    <div class="mt-8 flex flex-col justify-center gap-4 sm:flex-row lg:justify-start">
                        <a href="{{ route('register') }}" class="rounded-lg bg-indigo-600 px-8 py-3 text-lg font-medium text-white transition duration-300 hover:bg-indigo-700">{{ __('Create an Account') }}</a>
                        <a href="#features" class="rounded-lg bg-neutral-200 px-8 py-3 text-lg font-medium text-neutral-800 transition duration-300 hover:bg-neutral-300">{{ __('Explore Features') }}</a>
                    </div>
                </div>

                <div class="rounded-2xl border border-indigo-100 bg-white p-6 shadow-xl" aria-label="{{ __('Hospitrainity learning overview') }}">
                    <div class="mb-6 flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-indigo-600">{{ __('Learning dashboard') }}</p>
                            <h2 class="text-2xl font-bold text-neutral-800">{{ __('Hospitality English') }}</h2>
                        </div>
                        <i class="fas fa-graduation-cap text-3xl text-indigo-600" aria-hidden="true"></i>
                    </div>
                    <div class="space-y-4">
                        @foreach ([
                            ['icon' => 'fa-book-open', 'title' => __('Structured modules'), 'text' => __('Published lessons in a clear learning order')],
                            ['icon' => 'fa-headphones', 'title' => __('Media and vocabulary'), 'text' => __('Text, image, audio, video, and pronunciation practice')],
                            ['icon' => 'fa-list-check', 'title' => __('Interactive exercises'), 'text' => __('Multiple activity formats with feedback and saved completion')],
                        ] as $item)
                            <div class="flex items-start gap-4 rounded-xl bg-neutral-50 p-4">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-indigo-100 text-indigo-700">
                                    <i class="fas {{ $item['icon'] }}" aria-hidden="true"></i>
                                </span>
                                <div>
                                    <h3 class="font-semibold text-neutral-800">{{ $item['title'] }}</h3>
                                    <p class="mt-1 text-sm text-neutral-600">{{ $item['text'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </section>

        <section id="features" class="bg-white py-20">
            <div class="container mx-auto px-6">
                <h2 class="mb-12 text-center text-3xl font-bold text-neutral-800">{{ __('What You Can Do in Hospitrainity') }}</h2>
                <div class="grid grid-cols-1 gap-8 md:grid-cols-3">
                    <article class="rounded-lg bg-neutral-50 p-8 text-center shadow-md">
                        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100">
                            <i class="fas fa-book-open text-3xl text-indigo-600" aria-hidden="true"></i>
                        </div>
                        <h3 class="mb-2 text-xl font-bold text-neutral-800">{{ __('Structured Lessons') }}</h3>
                        <p class="text-neutral-600">{{ __('Study hospitality-focused vocabulary, learning materials, and exercises organized by module and lesson.') }}</p>
                    </article>
                    <article class="rounded-lg bg-neutral-50 p-8 text-center shadow-md">
                        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100">
                            <i class="fas fa-keyboard text-3xl text-indigo-600" aria-hidden="true"></i>
                        </div>
                        <h3 class="mb-2 text-xl font-bold text-neutral-800">{{ __('Interactive Practice') }}</h3>
                        <p class="text-neutral-600">{{ __('Practice with listening, matching, sequencing, pronunciation prompts, quizzes, and other supported exercise formats.') }}</p>
                    </article>
                    <article class="rounded-lg bg-neutral-50 p-8 text-center shadow-md">
                        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-indigo-100">
                            <i class="fas fa-chart-line text-3xl text-indigo-600" aria-hidden="true"></i>
                        </div>
                        <h3 class="mb-2 text-xl font-bold text-neutral-800">{{ __('Progress Tracking') }}</h3>
                        <p class="text-neutral-600">{{ __('Save completed vocabulary, material, and exercise items and review progress across published modules.') }}</p>
                    </article>
                </div>
            </div>
        </section>

        <section id="get-started" class="bg-indigo-700 text-white">
            <div class="container mx-auto px-6 py-20 text-center">
                <h2 class="mb-4 text-3xl font-bold">{{ __('Ready to Start Practicing?') }}</h2>
                <p class="mx-auto mb-8 max-w-2xl text-lg text-indigo-100">{{ __('Create an account to access the hospitality English modules available to your institution.') }}</p>
                <a href="{{ route('register') }}" class="rounded-lg bg-white px-8 py-3 text-lg font-medium text-indigo-700 transition duration-300 hover:bg-neutral-100">{{ __('Create an Account') }}</a>
            </div>
        </section>
    </main>

    <footer class="bg-neutral-800 text-white">
        <div class="container mx-auto px-6 py-8 text-center md:text-left">
            <a href="/" class="text-2xl font-bold">Hospitrainity</a>
            <p class="mt-2 text-neutral-400">{{ __('© :year Hospitrainity. All rights reserved.', ['year' => now()->year]) }}</p>
        </div>
    </footer>
@endsection

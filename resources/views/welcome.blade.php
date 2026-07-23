@extends('layouts.guest')

@section('title', __('Hospitrainity - Hospitality English Training'))
@section('bodyClass', 'bg-neutral-50')

@section('content')
    <header class="sticky top-0 z-50 bg-white shadow-sm">
        <nav class="container mx-auto flex items-center justify-between px-6 py-4" aria-label="{{ __('Primary navigation') }}">
            <a href="/" aria-label="{{ __('Hospitrainity home') }}">
                <x-brand-logo class="h-8 w-auto" />
            </a>
            <div class="hidden items-center space-x-6 md:flex">
                <a href="#how-learning-works" class="text-neutral-600 transition duration-300 hover:text-indigo-600">{{ __('How learning works') }}</a>
                <a href="{{ route('about') }}" class="text-neutral-600 transition duration-300 hover:text-indigo-600">{{ __('About') }}</a>
                <a href="{{ route('help.index') }}" class="text-neutral-600 transition duration-300 hover:text-indigo-600">{{ __('Help') }}</a>
            </div>
            <div class="hidden items-center space-x-4 md:flex">
                <x-language-switcher compact />
                <x-button :href="route('login')" variant="primary" data-login-modal-open>{{ __('Sign in') }}</x-button>
            </div>
            <button class="rounded md:hidden" id="mobile-menu-button" type="button" aria-controls="mobile-menu" aria-expanded="false" aria-label="{{ __('Open navigation') }}" data-open-label="{{ __('Open navigation') }}" data-close-label="{{ __('Close navigation') }}">
                <i class="fas fa-bars text-2xl text-neutral-700" aria-hidden="true"></i>
            </button>
        </nav>
        <div class="hidden border-t border-neutral-100 md:hidden" id="mobile-menu">
            <a href="#how-learning-works" class="block px-4 py-2 text-sm hover:bg-neutral-100">{{ __('How learning works') }}</a>
            <a href="{{ route('about') }}" class="block px-4 py-2 text-sm hover:bg-neutral-100">{{ __('About') }}</a>
            <a href="{{ route('help.index') }}" class="block px-4 py-2 text-sm hover:bg-neutral-100">{{ __('Help') }}</a>
            <x-language-switcher class="px-2 py-2" />
            <a href="{{ route('login') }}" data-login-modal-open class="block rounded-b-lg bg-indigo-600 px-4 py-3 text-center text-sm font-medium text-white hover:bg-indigo-700">{{ __('Sign in') }}</a>
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
                        <a href="{{ route('help.show', 'invitations-and-codes') }}" class="rounded-lg bg-indigo-600 px-8 py-3 text-lg font-medium text-white transition duration-300 hover:bg-indigo-700">{{ __('How Invitations Work') }}</a>
                        <a href="#how-learning-works" class="rounded-lg bg-neutral-200 px-8 py-3 text-lg font-medium text-neutral-800 transition duration-300 hover:bg-neutral-300">{{ __('Explore how learning works') }}</a>
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

        <section id="how-learning-works" class="scroll-mt-24 bg-white py-20">
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
                <p class="mx-auto mb-8 max-w-2xl text-lg text-indigo-100">{{ __('Learn independently with a verified account, or ask authorized institution staff for an invitation or classroom code.') }}</p>
                <div class="flex flex-wrap justify-center gap-3">
                    <a href="{{ route('help.show', 'invitations-and-codes') }}" class="rounded-lg bg-white px-8 py-3 text-lg font-medium text-indigo-700 transition duration-300 hover:bg-neutral-100">{{ __('How Invitations Work') }}</a>
                    <a href="{{ route('help.index') }}" class="rounded-lg border border-white px-8 py-3 text-lg font-medium text-white transition duration-300 hover:bg-indigo-800">{{ __('Help') }}</a>
                </div>
            </div>
        </section>
    </main>

    <!-- Login Modal -->
    <div id="login-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4 hidden" role="dialog" aria-modal="true" aria-labelledby="login-modal-title">
        <!-- Darkened Backdrop -->
        <div class="fixed inset-0 bg-neutral-900/60 backdrop-blur-sm transition-opacity" data-login-modal-close></div>

        <!-- Dialog Container -->
        <div class="relative w-full max-w-md rounded-2xl bg-white dark:bg-neutral-900 p-8 shadow-2xl transition-all border border-neutral-200 dark:border-neutral-800 z-10">
            <button type="button" class="absolute top-4 right-4 text-neutral-400 hover:text-neutral-600 dark:hover:text-neutral-200 p-2 rounded-lg" data-login-modal-close aria-label="{{ __('Close dialog') }}">
                <i class="fas fa-times text-xl" aria-hidden="true"></i>
            </button>

            <div class="text-center">
                <a href="/" aria-label="{{ __('Hospitrainity home') }}" class="inline-block">
                    <x-brand-logo class="h-10 w-auto mx-auto" />
                </a>
                <h2 id="login-modal-title" class="mt-4 text-2xl font-bold text-neutral-900 dark:text-white">
                    {{ __('Sign in to your account') }}
                </h2>
            </div>

            @include('auth.partials.login-form')
        </div>
    </div>
@endsection

@extends('layouts.app')

@section('title')
    {{ __('assistant.title') }} - Hospitrainity
@endsection
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @include('partials.learner-nav')

    <main class="container mx-auto max-w-4xl px-4 py-6 sm:px-6 sm:py-10">
        <a href="{{ route('dashboard') }}" class="inline-flex min-h-11 items-center gap-2 font-semibold text-indigo-700 underline-offset-4 hover:underline">
            <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            <span>{{ __('assistant.back') }}</span>
        </a>

        <header class="mt-5 rounded-2xl bg-indigo-950 px-5 py-7 text-white shadow-sm sm:px-8">
            <p class="text-sm font-semibold text-indigo-200">Hospitrainity</p>
            <h1 class="mt-2 text-3xl font-bold">{{ __('assistant.title') }}</h1>
            <p class="mt-3 max-w-2xl leading-7 text-indigo-100">{{ __('assistant.intro') }}</p>
        </header>

        @if ($errors->any())
            <div class="mt-6 rounded-xl border-2 border-red-500 bg-red-50 p-4 text-red-950" role="alert">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('assistant.ask') }}" class="mt-6 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf
            <label for="assistant-question" class="block text-lg font-bold text-neutral-950">{{ __('assistant.question_label') }}</label>
            <textarea
                id="assistant-question"
                name="question"
                rows="3"
                maxlength="{{ (int) config('course_assistant.maximum_question_characters', 300) }}"
                placeholder="{{ __('assistant.question_placeholder') }}"
                class="mt-3 block w-full rounded-xl border-neutral-400 text-base leading-7 focus:border-indigo-600 focus:ring-indigo-600"
                required
            >{{ old('question', $question) }}</textarea>
            <button type="submit" class="mt-4 inline-flex min-h-11 items-center gap-2 rounded-xl bg-indigo-700 px-5 py-3 font-bold text-white hover:bg-indigo-800 focus:outline-none focus:ring-4 focus:ring-indigo-300">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <span>{{ __('assistant.ask') }}</span>
            </button>
        </form>

        @if (is_array($result))
            <section class="mt-7" aria-live="polite" aria-labelledby="assistant-result-heading">
                @if ($result['status'] === 'answered')
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 sm:p-7">
                        <h2 id="assistant-result-heading" class="text-2xl font-bold text-neutral-950">{{ __('assistant.answer_heading') }}</h2>
                        <p class="mt-3 whitespace-pre-wrap leading-7 text-neutral-900">{{ $result['answer'] }}</p>
                    </div>
                @elseif ($result['status'] === 'sources_found')
                    <div class="rounded-2xl border border-indigo-200 bg-indigo-50 p-5 sm:p-7">
                        <h2 id="assistant-result-heading" class="text-2xl font-bold text-neutral-950">{{ __('assistant.matches_heading') }}</h2>
                        <p class="mt-2 leading-7 text-neutral-700">{{ __('assistant.matches_intro') }}</p>
                    </div>
                @else
                    <div class="rounded-2xl border border-amber-300 bg-amber-50 p-5 sm:p-7">
                        <h2 id="assistant-result-heading" class="text-2xl font-bold text-neutral-950">{{ __('assistant.no_answer_heading') }}</h2>
                        <p class="mt-2 leading-7 text-neutral-700">{{ __('assistant.no_answer_body') }}</p>
                    </div>
                @endif

                @if ($result['sources'] !== [])
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        @foreach ($result['sources'] as $source)
                            <article class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm">
                                <h3 class="text-lg font-bold text-neutral-950">{{ $source['title'] }}</h3>
                                @if ($result['status'] !== 'answered')
                                    <p class="mt-3 leading-7 text-neutral-700">{{ $source['excerpt'] }}</p>
                                @endif
                                <a href="{{ $source['url'] }}" class="mt-4 inline-flex min-h-11 items-center gap-2 font-bold text-indigo-700 underline-offset-4 hover:underline">
                                    <span>{{ __('assistant.open_source') }}</span>
                                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                </a>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
    </main>
@endsection

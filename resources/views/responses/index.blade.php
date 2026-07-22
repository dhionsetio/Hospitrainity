@extends('layouts.app')

@section('title')
    {{ __('responses.title') }} - Hospitrainity
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
            <h1 class="text-3xl font-bold">{{ __('responses.title') }}</h1>
            <p class="mt-3 max-w-2xl leading-7 text-indigo-100">{{ __('responses.intro') }}</p>
        </header>

        @if (session('status'))
            <div class="mt-5 rounded-xl border border-emerald-300 bg-emerald-50 p-4 font-semibold text-emerald-950" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($responses->isEmpty())
            <p class="mt-6 rounded-2xl border border-neutral-200 bg-white p-6 text-neutral-700 shadow-sm">{{ __('responses.empty') }}</p>
        @else
            <div class="mt-6 space-y-4">
                @foreach ($responses as $response)
                    <article class="rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold text-indigo-700">{{ $response->activity?->payloadData()['title'] ?? '' }}</p>
                                <h2 class="mt-1 text-lg font-bold leading-7 text-neutral-950">{{ $response->prompt?->payloadData()['stem'] ?? '' }}</h2>
                            </div>
                            <span class="rounded-full px-3 py-1 text-sm font-bold {{ $response->state === 'submitted' ? 'bg-emerald-100 text-emerald-900' : 'bg-amber-100 text-amber-950' }}">
                                {{ $response->state === 'submitted' ? __('responses.submitted_state') : __('responses.draft') }}
                            </span>
                        </div>
                        <p class="mt-4 whitespace-pre-wrap rounded-xl bg-neutral-50 p-4 leading-7 text-neutral-800">{{ $response->body }}</p>
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 text-sm text-neutral-600">
                            <span>{{ $response->kind === 'journal' ? __('responses.journal') : __('responses.assessment') }}</span>
                            <span>{{ __('responses.updated', ['time' => $response->updated_at->diffForHumans()]) }}</span>
                        </div>
                        @if ($response->state === 'draft')
                            <a href="{{ route('responses.edit', [$response->activity->code, $response->prompt->code]) }}" class="mt-4 inline-flex min-h-11 items-center gap-2 font-bold text-indigo-700 underline-offset-4 hover:underline">
                                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                <span>{{ __('responses.edit_title') }}</span>
                            </a>
                        @endif
                    </article>
                @endforeach
            </div>

            <div class="mt-6">{{ $responses->links() }}</div>
        @endif
    </main>
@endsection

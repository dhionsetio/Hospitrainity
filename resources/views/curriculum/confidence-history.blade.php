@extends('layouts.app')

@section('title'){{ __('My confidence history - Hospitrainity') }}@endsection
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @include('partials.learner-nav')

    <main class="container mx-auto max-w-5xl px-4 py-6 sm:px-6 sm:py-8">
        <x-back-control :href="route('dashboard')" :label="__('Back to dashboard')" />
        <header class="mt-4 rounded-xl bg-white p-6 shadow">
            <h1 class="text-3xl font-bold text-neutral-950">{{ __('My confidence history') }}</h1>
            <p class="mt-3 leading-7 text-neutral-700">{{ __('These ratings help you reflect on how confident you feel over time.') }}</p>
        </header>

        @if ($confidenceHistory->isEmpty())
            <p class="mt-6 rounded-xl bg-white p-6 text-neutral-700 shadow">{{ __('No completed confidence ratings yet.') }}</p>
        @else
            <div class="mt-6 space-y-5">
                @foreach ($confidenceHistory as $entry)
                    @php($moduleNumber = preg_match('/HSP-C(\d{2})-/', $entry['activity_code'], $moduleMatch) === 1 ? (int) $moduleMatch[1] : null)
                    <section class="rounded-xl bg-white p-6 shadow" aria-labelledby="history-{{ $entry['attempt_id'] }}">
                        <h2 id="history-{{ $entry['attempt_id'] }}" class="text-xl font-bold text-neutral-950">{{ $entry['baseline'] ? __('Chapter 1 baseline') : ($moduleNumber === null ? __('Confidence check') : __('Module :number confidence check', ['number' => $moduleNumber])) }}</h2>
                        <p class="mt-1 text-sm text-neutral-600">@if($showCurriculumEvidence && Auth::user()?->isSuperAdmin()){{ $entry['content_version'] }} · @endif{{ $entry['completed_at']?->format('Y-m-d H:i') }}</p>
                        <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach ($entry['ratings'] as $rating)
                                <div class="rounded-lg border border-neutral-300 p-3"><dt class="text-sm font-semibold leading-5 text-neutral-700">{{ $rating['statement'] }}</dt><dd class="mt-2 text-2xl font-bold text-indigo-800">{{ $rating['rating'] }}<span class="text-sm font-normal text-neutral-600">/5</span></dd>@if($showCurriculumEvidence && Auth::user()?->isSuperAdmin())<dd class="mt-1 text-xs text-neutral-500">{{ $rating['prompt_code'] }}</dd>@endif</div>
                            @endforeach
                        </dl>
                    </section>
                @endforeach
            </div>
            <div class="mt-6">{{ $confidenceHistory->links() }}</div>
        @endif
    </main>
@endsection

@extends('layouts.app')

@section('title', __('Getting started - Hospitrainity'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
<main class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6">
    <a href="{{ $returnUrl }}" class="inline-flex min-h-11 items-center font-semibold text-indigo-800 underline">&larr; {{ __('Return to dashboard') }}</a>
    <header class="mt-5">
        <p class="text-sm font-semibold uppercase tracking-wide text-indigo-700">{{ $onboarding['label'] }}</p>
        <h1 class="mt-2 text-4xl font-bold text-neutral-950">{{ __('Getting started') }}</h1>
        <p class="mt-3 text-lg text-neutral-700">{{ __('A short first-task guide. Your place is saved, and you can skip or restart without losing account or learning data.') }}</p>
    </header>

    @if(session('status'))<div class="mt-6 rounded-lg border border-green-400 bg-green-50 p-4 text-green-950" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="mt-6 rounded-lg border border-red-400 bg-red-50 p-4 text-red-950" role="alert"><p class="font-bold">{{ __('Review the current onboarding step.') }}</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if(in_array($onboarding['status'], ['completed', 'skipped'], true))
        <section class="mt-8 rounded-xl border border-neutral-300 bg-white p-6" aria-labelledby="onboarding-status-title">
            <h2 id="onboarding-status-title" class="text-2xl font-bold text-neutral-950">{{ $onboarding['status'] === 'completed' ? __('Guide completed') : __('Guide skipped') }}</h2>
            <p class="mt-2 text-neutral-700">{{ __('Restarting only resets this guide. It never resets learning progress, memberships, or account settings.') }}</p>
            <form method="POST" action="{{ route('onboarding.update') }}" class="mt-5">@csrf @method('PATCH')<input type="hidden" name="action" value="restart"><button class="rounded-lg bg-indigo-700 px-5 py-3 font-semibold text-white" type="submit">{{ __('Restart guide') }}</button></form>
        </section>
    @else
        @php($step = $onboarding['steps'][$onboarding['current_step']])
        <ol class="mt-8 grid gap-3 sm:grid-cols-3" aria-label="{{ __('Onboarding progress') }}">
            @foreach($onboarding['steps'] as $index => $item)
                <li class="rounded-lg border p-3 text-sm {{ $index === $onboarding['current_step'] ? 'border-indigo-700 bg-indigo-50 font-semibold' : ($index < $onboarding['current_step'] ? 'border-green-500 bg-green-50' : 'border-neutral-300 bg-white') }}" @if($index === $onboarding['current_step']) aria-current="step" @endif>
                    {{ __('Step :current of :total', ['current' => $index + 1, 'total' => count($onboarding['steps'])]) }}<span class="mt-1 block">{{ $item['title'] }}</span>
                </li>
            @endforeach
        </ol>

        <section class="mt-6 rounded-xl border border-neutral-300 bg-white p-6 shadow-sm" aria-labelledby="current-onboarding-step">
            <p class="text-sm font-semibold text-indigo-700">{{ __('Step :current of :total', ['current' => $onboarding['current_step'] + 1, 'total' => count($onboarding['steps'])]) }}</p>
            <h2 id="current-onboarding-step" class="mt-2 text-2xl font-bold text-neutral-950">{{ $step['title'] }}</h2>
            <p class="mt-3 text-neutral-700">{{ $step['description'] }}</p>
            <a href="{{ $step['url'] }}" class="mt-5 inline-flex min-h-11 items-center rounded-lg border border-indigo-700 px-5 py-3 font-semibold text-indigo-800">{{ $step['action'] }}</a>
            <form method="POST" action="{{ route('onboarding.update') }}" class="mt-6 flex flex-wrap gap-3">
                @csrf @method('PATCH')
                <input type="hidden" name="action" value="advance">
                <input type="hidden" name="step" value="{{ $onboarding['current_step'] }}">
                <button type="submit" class="rounded-lg bg-indigo-700 px-5 py-3 font-semibold text-white">{{ $onboarding['current_step'] + 1 === count($onboarding['steps']) ? __('Finish guide') : __('Done, next step') }}</button>
            </form>
        </section>

        <form method="POST" action="{{ route('onboarding.update') }}" class="mt-5">@csrf @method('PATCH')<input type="hidden" name="action" value="skip"><button type="submit" class="min-h-11 font-semibold text-neutral-700 underline">{{ __('Skip for now') }}</button></form>
    @endif
</main>
@endsection

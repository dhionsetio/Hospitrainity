@extends('layouts.guest')

@section('title', __('About Hospitrainity'))

@section('content')
<main class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6">
    <x-back-control :href="route('help.index')" :label="__('Return to Help')" />

    <header class="mt-5">
        <h1 class="text-4xl font-bold text-neutral-950">{{ $document['title'] }}</h1>
        <p class="mt-3 text-lg text-neutral-700">{{ $document['summary'] }}</p>
    </header>
    <div class="mt-8 space-y-6">
        @foreach($document['sections'] as $section)
            <section class="rounded-xl border border-neutral-300 bg-white p-6" aria-labelledby="about-section-{{ $loop->iteration }}">
                <h2 id="about-section-{{ $loop->iteration }}" class="text-2xl font-bold text-neutral-950">{{ $section['title'] }}</h2>
                @foreach($section['body'] as $paragraph)<p class="mt-3 text-neutral-800">{{ $paragraph }}</p>@endforeach
            </section>
        @endforeach
    </div>
    <div class="mt-8 flex flex-wrap gap-3">
        <a href="{{ route('help.index') }}" class="hsp-action inline-flex min-h-11 items-center rounded-lg bg-indigo-700 px-5 py-3 font-semibold text-white">{{ __('Help') }}</a>
        <a href="{{ route('policies.show', ['type' => 'accessibility']) }}" class="hsp-action inline-flex min-h-11 items-center rounded-lg border border-indigo-700 px-5 py-3 font-semibold text-indigo-800">{{ __('Accessibility') }}</a>
    </div>
</main>
@endsection

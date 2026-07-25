@extends('layouts.guest')

@section('title'){{ $topic['title'] }} - Hospitrainity @endsection

@section('content')
<main class="mx-auto w-full max-w-4xl px-4 py-10 sm:px-6">
    <x-back-control :href="route('help.index')" :label="__('Return to Help')" />
    <header class="mt-5">
        <h1 class="text-4xl font-bold text-neutral-950">{{ $topic['title'] }}</h1>
        <p class="mt-3 text-lg text-neutral-700">{{ $topic['summary'] }}</p>
    </header>
    <div class="mt-8 space-y-6">
        @foreach($topic['sections'] as $section)
            <section class="rounded-xl border border-neutral-300 bg-white p-6" aria-labelledby="help-section-{{ $loop->iteration }}">
                <h2 id="help-section-{{ $loop->iteration }}" class="text-2xl font-bold text-neutral-950">{{ $section['title'] }}</h2>
                <ul class="mt-3 list-disc space-y-2 pl-6 text-neutral-800">
                    @foreach($section['body'] as $paragraph)<li>{{ $paragraph }}</li>@endforeach
                </ul>
            </section>
        @endforeach
    </div>
</main>
@endsection

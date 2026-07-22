@extends('layouts.guest')

@section('title', __('Glossary - Hospitrainity'))

@section('content')
<main class="mx-auto w-full max-w-5xl px-4 py-10 sm:px-6">
    <x-back-control :href="route('help.index')" :label="__('Return to Help')" />
    <header class="mt-5">
        <h1 class="text-4xl font-bold text-neutral-950">{{ __('Glossary') }}</h1>
        <p class="mt-3 text-lg text-neutral-700">{{ __('Plain-language meanings for Hospitrainity roles, progress, content, and institution terms.') }}</p>
    </header>
    <dl class="mt-8 space-y-4">
        @foreach($entries as $entry)
            <div id="term-{{ $entry['slug'] }}" class="scroll-mt-24 rounded-xl border border-neutral-300 bg-white p-5">
                <dt class="text-xl font-bold text-neutral-950">{{ $entry['term'] }}</dt>
                <dd class="mt-2 text-neutral-700">{{ $entry['definition'] }}</dd>
            </div>
        @endforeach
    </dl>
</main>
@endsection

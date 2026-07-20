@extends('layouts.guest')

@section('title', __('Help - Hospitrainity'))

@section('content')
<main class="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6">
    <header class="max-w-3xl">
        <p class="text-sm font-semibold uppercase tracking-wide text-indigo-700">Hospitrainity</p>
        <h1 class="mt-2 text-4xl font-bold text-neutral-950">{{ __('Help') }}</h1>
        <p class="mt-3 text-lg text-neutral-700">{{ __('Search current instructions, recovery guidance, institution workflows, and terminology.') }}</p>
    </header>

    <form method="GET" action="{{ route('help.index') }}" role="search" class="mt-8 flex max-w-3xl flex-col gap-3 sm:flex-row">
        <div class="flex-1">
            <label for="help-query" class="block font-semibold text-neutral-800">{{ __('Search Help and glossary') }}</label>
            <input id="help-query" name="q" type="search" maxlength="80" value="{{ $query }}" class="mt-2 w-full rounded-lg border border-neutral-400 px-4 py-3" autocomplete="off">
        </div>
        <button type="submit" class="self-end rounded-lg bg-indigo-700 px-6 py-3 font-semibold text-white">{{ __('Search') }}</button>
    </form>

    @if($query !== '')
        <p class="mt-4" role="status">{{ __('Results for “:query”', ['query' => $query]) }}</p>
    @endif

    <section class="mt-10" aria-labelledby="help-topics-title">
        <h2 id="help-topics-title" class="text-2xl font-bold text-neutral-950">{{ __('Help topics') }}</h2>
        <div class="mt-5 grid gap-5 md:grid-cols-2">
            @forelse($topics as $topic)
                <article class="rounded-xl border border-neutral-300 bg-white p-5 shadow-sm">
                    <h3 class="text-xl font-bold text-neutral-950"><a href="{{ route('help.show', $topic['slug']) }}" class="text-indigo-800 underline underline-offset-2">{{ $topic['title'] }}</a></h3>
                    <p class="mt-2 text-neutral-700">{{ $topic['summary'] }}</p>
                </article>
            @empty
                <p class="rounded-lg border border-dashed border-neutral-400 p-5 text-neutral-700">{{ __('No Help topic matches this search. Try a shorter term or browse the full glossary.') }}</p>
            @endforelse
        </div>
    </section>

    @if($glossary->isNotEmpty())
        <section class="mt-10" aria-labelledby="help-glossary-title">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="help-glossary-title" class="text-2xl font-bold text-neutral-950">{{ __('Glossary matches') }}</h2>
                <a href="{{ route('glossary.index') }}" class="font-semibold text-indigo-800 underline">{{ __('Open full glossary') }}</a>
            </div>
            <dl class="mt-5 grid gap-4 md:grid-cols-2">
                @foreach($glossary as $entry)
                    <div class="rounded-lg border border-neutral-300 bg-white p-4"><dt class="font-bold text-neutral-950">{{ $entry['term'] }}</dt><dd class="mt-1 text-neutral-700">{{ $entry['definition'] }}</dd></div>
                @endforeach
            </dl>
        </section>
    @endif

    <p class="mt-10 text-sm text-neutral-600">{{ __('Help version :version · updated :date', ['version' => config('help.version'), 'date' => config('help.updated_at')]) }}</p>
</main>
@endsection

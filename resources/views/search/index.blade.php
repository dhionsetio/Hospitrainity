@extends('layouts.app')

@section('title', __('Search - Hospitrainity'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
<main class="mx-auto w-full max-w-5xl px-4 py-10 sm:px-6">
    <header>
        <p class="text-sm font-semibold uppercase tracking-wide text-indigo-700">Hospitrainity</p>
        <h1 class="mt-2 text-4xl font-bold text-neutral-950">{{ __('Search') }}</h1>
        <p class="mt-3 text-lg text-neutral-700">{{ __('Search published modules, lesson sections, vocabulary, accessible activities, Help, and the glossary.') }}</p>
    </header>

    @if($errors->any())<div class="mt-6 rounded-lg border border-red-400 bg-red-50 p-4 text-red-950" role="alert"><p class="font-bold">{{ __('Review your search.') }}</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <form method="GET" action="{{ route('search.index') }}" role="search" class="mt-8 grid gap-4 rounded-xl border border-neutral-300 bg-white p-5 sm:grid-cols-[1fr_13rem_auto] sm:items-end">
        <div>
            <label for="global-search-query" class="block font-semibold text-neutral-800">{{ __('Search terms') }}</label>
            <input id="global-search-query" name="q" type="search" required maxlength="80" value="{{ $query }}" class="mt-2 w-full rounded-lg border border-neutral-400 px-4 py-3" autocomplete="off">
        </div>
        <div>
            <label for="global-search-type" class="block font-semibold text-neutral-800">{{ __('Result type') }}</label>
            <select id="global-search-type" name="type" class="mt-2 w-full rounded-lg border border-neutral-400 px-3 py-3">
                <option value="">{{ __('All supported content') }}</option>
                @foreach(\App\Services\SearchService::TYPES as $resultType)<option value="{{ $resultType }}" @selected($type === $resultType)>{{ __('search_type.'.$resultType) }}</option>@endforeach
            </select>
        </div>
        <button type="submit" class="rounded-lg bg-indigo-700 px-6 py-3 font-semibold text-white">{{ __('Search') }}</button>
    </form>

    @if($results !== null)
        <section class="mt-8" aria-labelledby="search-results-title">
            <h2 id="search-results-title" class="text-2xl font-bold text-neutral-950">{{ __('Search results') }}</h2>
            <p class="mt-2 text-neutral-700" role="status">{{ trans_choice(':count result found|:count results found', $results->total(), ['count' => $results->total()]) }}</p>
            <div class="mt-5 space-y-4">
                @forelse($results as $result)
                    <article class="rounded-xl border border-neutral-300 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-indigo-100 px-3 py-1 text-sm font-semibold text-indigo-800">{{ __('search_type.'.$result['type']) }}</span>
                            @if($result['locale'] === 'en' && app()->getLocale() !== 'en')<span class="text-sm text-neutral-600" lang="en">EN</span>@endif
                        </div>
                        <h3 class="mt-3 text-xl font-bold text-neutral-950"><a href="{{ $result['url'] }}" class="text-indigo-800 underline underline-offset-2">{{ $result['title'] }}</a></h3>
                        <p class="mt-2 text-neutral-700">{{ $result['summary'] }}</p>
                    </article>
                @empty
                    <div class="rounded-xl border border-dashed border-neutral-400 bg-white p-6">
                        <h3 class="font-bold text-neutral-950">{{ __('No matching published content') }}</h3>
                        <p class="mt-2 text-neutral-700">{{ __('Try fewer words, remove the type filter, or search the Help glossary. Drafts, private responses, and cross-institution user records are never included here.') }}</p>
                        <a href="{{ route('help.index', ['q' => $query]) }}" class="mt-4 inline-flex min-h-11 items-center font-semibold text-indigo-800 underline">{{ __('Search Help and glossary') }}</a>
                    </div>
                @endforelse
            </div>
            <div class="mt-6">{{ $results->links() }}</div>
        </section>
    @else
        <section class="mt-8 rounded-xl border border-neutral-300 bg-white p-6" aria-labelledby="search-scope-title">
            <h2 id="search-scope-title" class="text-xl font-bold text-neutral-950">{{ __('What search includes') }}</h2>
            <p class="mt-2 text-neutral-700">{{ __('Only the active published curriculum and versioned self-help content are indexed. User lookup remains inside authorized administration pages.') }}</p>
        </section>
    @endif
</main>
@endsection

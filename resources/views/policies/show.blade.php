@extends('layouts.guest')

@section('title', $document['title'].' - Hospitrainity')
@section('bodyClass', 'bg-neutral-50 text-neutral-900')

@section('content')
    <main id="main-content" class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:py-12">
        <a href="{{ url('/') }}" class="font-semibold text-indigo-700 underline">&larr; {{ __('Back to Hospitrainity') }}</a>
        <article class="mt-6 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-10">
            <header class="border-b border-neutral-200 pb-6">
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-700">{{ __('Public trust document') }}</p>
                <h1 class="mt-2 text-3xl font-bold">{{ $document['title'] }}</h1>
                <p class="mt-3 text-lg text-neutral-700">{{ $document['summary'] }}</p>
                <dl class="mt-4 grid gap-2 text-sm text-neutral-700 sm:grid-cols-2">
                    <div><dt class="font-semibold">{{ __('Version') }}</dt><dd>{{ $document['version'] }}</dd></div>
                    <div><dt class="font-semibold">{{ __('Effective date') }}</dt><dd>{{ $document['effective_date'] }}</dd></div>
                    <div><dt class="font-semibold">{{ __('Authoritative language') }}</dt><dd>{{ strtoupper($document['authoritative_locale']) }}</dd></div>
                    <div><dt class="font-semibold">{{ __('Review status') }}</dt><dd>{{ $document['review_status'] }}</dd></div>
                </dl>
            </header>

            <div class="mt-8 space-y-8">
                @foreach($document['sections'] as $section)
                    <section>
                        <h2 class="text-xl font-bold">{{ $section['title'] }}</h2>
                        @if(is_array($section['body']))
                            <ul class="mt-3 list-disc space-y-2 pl-6 text-neutral-700">
                                @foreach($section['body'] as $item)<li>{{ $item }}</li>@endforeach
                            </ul>
                        @else
                            <p class="mt-3 leading-7 text-neutral-700">{{ $section['body'] }}</p>
                        @endif
                    </section>
                @endforeach
            </div>
        </article>
    </main>
@endsection

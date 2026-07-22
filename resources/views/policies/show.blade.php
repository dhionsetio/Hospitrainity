@extends('layouts.guest')

@section('title', $document['title'].' - Hospitrainity')
@section('bodyClass', 'bg-neutral-50 text-neutral-900')

@section('content')
    <main id="main-content" class="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:py-12">
        <x-back-control :href="$returnUrl" :label="$returnLabel" />
        <article class="mt-6 rounded-xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-10">
            <header class="border-b border-neutral-200 pb-6">
                <h1 class="text-3xl font-bold">{{ $document['title'] }}</h1>
                <p class="mt-3 text-lg text-neutral-700">{{ $document['summary'] }}</p>
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

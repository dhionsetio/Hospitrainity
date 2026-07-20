@extends('layouts.app')

@section('title'){{ $curriculumSection['title'] }} - {{ __('Hospitrainity lesson') }}@endsection
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @include('partials.learner-nav')
    @isset($curriculumPreview)
        @include('curriculum.partials.preview-banner')
    @else
        @include('curriculum.partials.active-draft-banner', ['activePackage' => $curriculumSection['package'], 'showCurriculumEvidence' => $showCurriculumEvidence])
    @endisset

    <main class="container mx-auto max-w-4xl px-4 py-6 sm:px-6 sm:py-8">
        @php
            $lessonOutline = collect($curriculumSection['blocks'])
                ->map(function (array $block, int $index): ?array {
                    if (($block['type'] ?? null) !== 'heading') {
                        return null;
                    }

                    $label = trim((string) ($block['text'] ?? collect($block['runs'] ?? [])->pluck('text')->implode('')));

                    return $label === '' ? null : ['index' => $index, 'label' => $label];
                })
                ->filter();
            $primaryActivity = collect($curriculumSection['blocks'])->firstWhere('type', 'activity_embed');
        @endphp
        <nav aria-label="{{ __('Breadcrumb') }}">
            <a href="{{ isset($curriculumPreview) ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.preview.chapters.show', [$curriculumPreview, $curriculumSection['chapter']['code']]) : route('curriculum.chapters.show', $curriculumSection['chapter']['code']) }}" class="text-sm font-semibold text-indigo-700 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700">&larr; {{ __('Module :number', ['number' => $curriculumSection['chapter']['module']]) }}: {{ $curriculumSection['chapter']['title'] }}</a>
        </nav>

        <header class="mt-4 rounded-xl bg-white p-5 shadow sm:p-7">
            <p class="text-sm font-semibold text-indigo-700">{{ __('Section :number of :total', ['number' => $curriculumSection['navigation']['position'], 'total' => $curriculumSection['navigation']['total']]) }}</p>
            <h1 class="mt-2 break-words text-3xl font-bold text-neutral-950 sm:text-4xl">{{ $curriculumSection['title'] }}</h1>
            <div class="mt-5 flex flex-wrap gap-3">
                @if ($primaryActivity)
                    <a href="#activity-{{ $primaryActivity['id'] }}" class="inline-flex min-h-11 items-center rounded-lg bg-indigo-700 px-5 py-2 font-semibold text-white hover:bg-indigo-800">{{ __('Continue to activity') }}</a>
                @elseif ($curriculumSection['navigation']['next'])
                    <a rel="next" href="{{ isset($curriculumPreview) ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.preview.sections.show', [$curriculumPreview, $curriculumSection['navigation']['next']['code']]) : route('curriculum.sections.show', $curriculumSection['navigation']['next']['code']) }}" class="inline-flex min-h-11 items-center rounded-lg bg-indigo-700 px-5 py-2 font-semibold text-white hover:bg-indigo-800">{{ __('Continue to next section') }}</a>
                @endif
            </div>
        </header>

        @if ($lessonOutline->isNotEmpty())
            <details class="mt-6 rounded-xl border border-neutral-300 bg-white p-4 shadow-sm">
                <summary class="cursor-pointer font-semibold text-neutral-900">{{ __('Lesson outline') }}</summary>
                <nav class="mt-3" aria-label="{{ __('Lesson outline') }}">
                    <ol class="space-y-2 pl-5">
                        @foreach ($lessonOutline as $item)
                            <li><a href="#lesson-part-{{ $item['index'] + 1 }}" class="font-medium text-indigo-700 underline underline-offset-2">{{ $item['label'] }}</a></li>
                        @endforeach
                    </ol>
                </nav>
            </details>
        @endif

        <article class="mt-6 space-y-5 rounded-xl bg-white p-5 shadow sm:p-8" aria-label="{{ $curriculumSection['title'] }}">
            @foreach ($curriculumSection['blocks'] as $block)
                @switch($block['type'])
                    @case('heading')
                        <h2 id="lesson-part-{{ $loop->index + 1 }}" class="scroll-mt-24 pt-2 text-2xl font-bold text-neutral-950">
                            @include('curriculum.partials.rich-text', ['runs' => $block['runs'] ?? [], 'text' => $block['text'] ?? ''])
                        </h2>
                        @break

                    @case('callout')
                        <aside class="rounded-lg border-l-4 border-indigo-500 bg-indigo-50 p-4 leading-7 text-indigo-950">
                            @include('curriculum.partials.rich-text', ['runs' => $block['runs'] ?? [], 'text' => $block['text'] ?? ''])
                        </aside>
                        @break

                    @case('instruction')
                        <aside class="rounded-lg border border-amber-300 bg-amber-50 p-4 leading-7 text-amber-950" aria-label="{{ __('Learning instruction') }}">
                            @include('curriculum.partials.rich-text', ['runs' => $block['runs'] ?? [], 'text' => $block['text'] ?? ''])
                        </aside>
                        @break

                    @case('list_item')
                        <ul class="list-disc space-y-2 pl-6 text-neutral-800">
                            <li class="pl-1 leading-7">
                                @include('curriculum.partials.rich-text', ['runs' => $block['runs'] ?? [], 'text' => $block['text'] ?? ''])
                            </li>
                        </ul>
                        @break

                    @case('dialogue_turn')
                        <dl class="grid gap-1 rounded-lg border border-neutral-200 bg-neutral-50 p-4 sm:grid-cols-[9rem_1fr] sm:gap-4">
                            <dt class="font-bold text-indigo-800">{{ $block['speaker'] }}</dt>
                            <dd class="leading-7 text-neutral-900">{{ $block['text'] }}</dd>
                        </dl>
                        @break

                    @case('source_table')
                        <div class="max-w-full overflow-x-auto rounded-lg border border-neutral-300 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700" role="region" aria-label="{{ $block['caption'] }}" tabindex="0">
                            <table class="min-w-full border-collapse text-left text-sm text-neutral-900">
                                <caption class="sr-only">{{ $block['caption'] }}</caption>
                                <thead class="bg-indigo-50">
                                    <tr>
                                        @foreach ($block['header'] as $cell)
                                            <th scope="col" class="whitespace-normal border-b border-neutral-300 px-4 py-3 font-bold">{{ $cell }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-neutral-200">
                                    @foreach ($block['rows'] as $row)
                                        <tr class="align-top odd:bg-white even:bg-neutral-50">
                                            @foreach ($row as $cell)
                                                <td class="min-w-28 whitespace-pre-line px-4 py-3 leading-6">{{ $cell }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @break

                    @case('external_link')
                        <ul class="space-y-2 rounded-lg border border-sky-200 bg-sky-50 p-4" aria-label="{{ __('Further reading') }}">
                            @foreach ($block['links'] as $link)
                                <li>
                                    <a href="{{ $link['target'] }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center gap-2 font-semibold text-sky-800 underline decoration-2 underline-offset-4 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-sky-800">
                                        <span>{{ $link['text'] }}</span>
                                        <span class="text-xs font-normal">{{ __('(external site, opens in a new tab)') }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        @break

                    @case('activity_embed')
                        <section class="rounded-xl border-2 border-indigo-200 bg-indigo-50 p-5" aria-labelledby="activity-{{ $block['id'] }}">
                            <h2 id="activity-{{ $block['id'] }}" class="text-xl font-bold text-indigo-950">{{ $curriculumSection['activity']['title'] ?? __('Practice activity') }}</h2>
                            <p class="mt-2 text-sm text-indigo-900">{{ __('Your responses are saved only when you submit the activity form.') }}</p>
                            <a href="{{ isset($curriculumPreview) ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.preview.activities.show', [$curriculumPreview, $block['activity_code']]) : route('curriculum.activities.show', $block['activity_code']) }}" class="mt-4 inline-flex min-h-11 items-center rounded-lg bg-indigo-700 px-5 py-2 font-semibold text-white hover:bg-indigo-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-800">{{ __('Open activity') }}</a>
                        </section>
                        @break

                    @default
                        <p class="whitespace-pre-line break-words leading-7 text-neutral-800">
                            @include('curriculum.partials.rich-text', ['runs' => $block['runs'] ?? [], 'text' => $block['text'] ?? ''])
                        </p>
                @endswitch
                @if(isset($block['asset']['url']))
                    <figure class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                        @if(($block['asset']['kind'] ?? null) === 'image')
                            <img src="{{ $block['asset']['url'] }}" alt="{{ $block['asset']['accessibility_text'] }}" class="mx-auto max-h-[32rem] max-w-full rounded-md" loading="lazy">
                        @elseif(($block['asset']['kind'] ?? null) === 'audio')
                            <audio controls preload="metadata" class="w-full"><source src="{{ $block['asset']['url'] }}" type="{{ $block['asset']['mime_type'] ?? '' }}">{{ __('Your browser does not support embedded audio.') }}</audio>
                        @endif
                        <figcaption class="mt-2 text-sm text-neutral-700"><span class="font-semibold">{{ $block['asset']['display_name'] }}</span> &mdash; {{ $block['asset']['accessibility_text'] }}</figcaption>
                    </figure>
                @endif
            @endforeach
        </article>

        <nav class="mt-6 grid gap-3 sm:grid-cols-2" aria-label="{{ __('Lesson section navigation') }}">
            @if ($curriculumSection['navigation']['previous'])
                <a rel="prev" href="{{ isset($curriculumPreview) ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.preview.sections.show', [$curriculumPreview, $curriculumSection['navigation']['previous']['code']]) : route('curriculum.sections.show', $curriculumSection['navigation']['previous']['code']) }}" class="min-h-14 rounded-lg border border-neutral-300 bg-white p-4 font-semibold text-indigo-800 shadow-sm hover:border-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700">&larr; <span class="block text-xs font-normal text-neutral-600">{{ __('Previous') }}</span>{{ $curriculumSection['navigation']['previous']['title'] }}</a>
            @else
                <span></span>
            @endif
            @if ($curriculumSection['navigation']['next'])
                <a rel="next" href="{{ isset($curriculumPreview) ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.preview.sections.show', [$curriculumPreview, $curriculumSection['navigation']['next']['code']]) : route('curriculum.sections.show', $curriculumSection['navigation']['next']['code']) }}" class="min-h-14 rounded-lg border border-neutral-300 bg-white p-4 text-right font-semibold text-indigo-800 shadow-sm hover:border-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-700"><span class="block text-xs font-normal text-neutral-600">{{ __('Next') }}</span>{{ $curriculumSection['navigation']['next']['title'] }} &rarr;</a>
            @endif
        </nav>

        @if($showCurriculumEvidence)
            <details class="mt-6 rounded-lg border border-neutral-300 bg-white p-4 text-sm text-neutral-700">
                <summary class="cursor-pointer font-semibold">{{ __('Source and lifecycle evidence') }}</summary>
                <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                    <div><dt class="font-semibold">{{ __('Package version') }}</dt><dd>{{ $curriculumSection['package']->content_version }}</dd></div>
                    <div><dt class="font-semibold">{{ __('Content code') }}</dt><dd class="font-mono">{{ $curriculumSection['code'] }}</dd></div>
                    <div><dt class="font-semibold">{{ __('Source artifact') }}</dt><dd>Hospitrainity.docx</dd></div>
                    <div><dt class="font-semibold">{{ __('Ordered blocks') }}</dt><dd>{{ count($curriculumSection['blocks']) }}</dd></div>
                    <div><dt class="font-semibold">{{ __('Lifecycle') }}</dt><dd>{{ $curriculumSection['status'] }}</dd></div>
                </dl>
            </details>
        @endif
    </main>
@endsection

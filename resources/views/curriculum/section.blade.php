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
            $stepCheckpointUrl = isset($curriculumPreview)
                ? null
                : route('curriculum.steps.show', [$curriculumSection['chapter']['code'], $curriculumSection['step']['number']]);
            $nextSectionUrl = $curriculumSection['navigation']['next']
                ? (isset($curriculumPreview)
                    ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.preview.sections.show', [$curriculumPreview, $curriculumSection['navigation']['next']['code']])
                    : route('curriculum.sections.show', $curriculumSection['navigation']['next']['code']))
                : null;
        @endphp
        <x-back-control
            :href="isset($curriculumPreview) ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.preview.chapters.show', [$curriculumPreview, $curriculumSection['chapter']['code']]) : route('curriculum.chapters.show', $curriculumSection['chapter']['code'])"
            :label="__('Return to Module :number', ['number' => $curriculumSection['chapter']['module']])"
        />

        <nav aria-label="{{ __('Breadcrumb') }}" class="mt-2 flex items-center gap-2 text-xs text-neutral-500">
            <a href="{{ route('dashboard') }}" class="text-neutral-600 hover:underline">{{ __('Dashboard') }}</a>
            <i class="fa-solid fa-chevron-right text-[10px] text-neutral-400" aria-hidden="true"></i>
            <a href="{{ route('curriculum.chapters.show', $curriculumSection['chapter']['code']) }}" class="text-neutral-600 hover:underline">{{ __('Module :number', ['number' => $curriculumSection['chapter']['module']]) }}</a>
            <i class="fa-solid fa-chevron-right text-[10px] text-neutral-400" aria-hidden="true"></i>
            <span class="font-medium text-neutral-900 truncate" aria-current="page">{{ $curriculumSection['title'] }}</span>
        </nav>

        <header class="mt-4 rounded-xl bg-white p-5 shadow sm:p-7">
            <p class="text-sm font-semibold text-indigo-700">{{ __('Learning step :number of :total', ['number' => $curriculumSection['step']['number'], 'total' => $curriculumSection['step']['total']]) }}</p>
            <p class="mt-1 text-sm text-neutral-600">{{ __('Part :number of :total in this step', ['number' => $curriculumSection['step']['position'], 'total' => $curriculumSection['step']['count']]) }}</p>
            <ol class="hsp-step-position mt-4" aria-label="{{ __('Current position in learning step') }}">
                @for($part = 1; $part <= $curriculumSection['step']['count']; $part++)
                    <li @class(['is-current' => $part === $curriculumSection['step']['position']])>
                        <span aria-hidden="true">{{ $part }}</span>
                        <span class="sr-only">{{ $part === $curriculumSection['step']['position'] ? __('Current part :number', ['number' => $part]) : __('Part :number', ['number' => $part]) }}</span>
                    </li>
                @endfor
            </ol>
            @php($displayTitle = preg_replace('/^\s*Step\s+\d+[\.\:\-\s]*/i', '', $curriculumSection['title']))
            <h1 class="mt-2 break-words text-3xl font-bold text-neutral-950 sm:text-4xl">{{ $displayTitle }}</h1>
            <div class="mt-5 flex flex-wrap gap-3">
                @unless(Auth::user()?->ui_no_audio)
                    <button
                        type="button"
                        class="hsp-action inline-flex min-h-11 items-center gap-2 rounded-lg border border-indigo-700 bg-white px-5 py-2 font-semibold text-indigo-800 hover:bg-indigo-50"
                        data-speak-target="lesson-content"
                        data-speak-language="en-US"
                        data-playing-message="{{ __('engagement.listening_started') }}"
                        data-finished-message="{{ __('engagement.listening_finished') }}"
                        data-stopped-message="{{ __('engagement.listening_stopped') }}"
                        data-failed-message="{{ __('engagement.listening_failed') }}"
                        aria-describedby="lesson-listening-status"
                        aria-pressed="false"
                    >
                        <i class="fa-solid fa-volume-high" aria-hidden="true"></i>
                        <span data-listen-label>{{ __('engagement.listen_lesson') }}</span>
                        <span data-stop-label hidden>{{ __('engagement.stop_listening') }}</span>
                    </button>
                    <span id="lesson-listening-status" class="sr-only" aria-live="polite"></span>
                @endunless
                @if ($primaryActivity)
                    <a href="#activity-{{ $primaryActivity['id'] }}" class="hsp-action inline-flex min-h-11 items-center rounded-lg bg-indigo-700 px-5 py-2 font-semibold text-white hover:bg-indigo-800">{{ __('Continue to activity') }}</a>
                @elseif (!isset($curriculumPreview) && $curriculumSection['step']['is_last_section'])
                    <a rel="next" href="{{ $stepCheckpointUrl }}" class="hsp-action inline-flex min-h-11 items-center gap-2 rounded-lg bg-indigo-700 px-5 py-2 font-semibold text-white hover:bg-indigo-800">{{ __('Finish learning step :number', ['number' => $curriculumSection['step']['number']]) }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                @elseif ($curriculumSection['navigation']['next'])
                    <a rel="next" href="{{ $nextSectionUrl }}" class="hsp-action inline-flex min-h-11 items-center rounded-lg bg-indigo-700 px-5 py-2 font-semibold text-white hover:bg-indigo-800">{{ __('Continue to next section') }}</a>
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

        @if($curriculumSection['is_warm_up'] ?? false)
            @include('curriculum.partials.warm-up', [
                'sectionCode' => $curriculumSection['code'],
                'step' => $curriculumSection['step'],
                'title' => $displayTitle,
                'blocks' => $curriculumSection['blocks'],
                'audioUrl' => collect($curriculumSection['blocks'])->firstWhere('asset.kind', 'audio')['asset']['url'] ?? null,
                'activity' => $curriculumSection['activity'],
                'navigation' => $curriculumSection['navigation'],
                'existingReflections' => $existingReflections ?? collect(),
            ])
        @else
            <article id="lesson-content" class="hsp-lesson-content mt-6 space-y-5" aria-label="{{ $curriculumSection['title'] }}">
                @foreach ($curriculumSection['blocks'] as $block)
                    @switch($block['type'])
                        @case('heading')
                            <h2 id="lesson-part-{{ $loop->index + 1 }}" class="scroll-mt-24 pt-2 text-2xl font-bold text-neutral-950">
                                @include('curriculum.partials.rich-text', ['runs' => $block['runs'] ?? [], 'text' => $block['text'] ?? ''])
                            </h2>
                            @break

                        @case('callout')
                            <aside class="rounded-lg border border-indigo-300 bg-indigo-50 p-4 leading-7 text-indigo-950">
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
                            <dl class="hsp-dialogue-turn">
                                <dt><i class="fa-solid fa-comment" aria-hidden="true"></i> {{ $block['speaker'] }}</dt>
                                <dd class="leading-7 text-neutral-900">{{ $block['text'] }}</dd>
                            </dl>
                            @break

                        @case('source_table')
                            @if($showCurriculumEvidence && Auth::user()?->isSuperAdmin())
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
                            @else
                                @include('curriculum.partials.learning-cards', ['tableBlock' => $block])
                            @endif
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
                            <section class="rounded-xl border border-indigo-200 bg-indigo-50/60 p-6 sm:p-8 space-y-5" aria-labelledby="activity-{{ $block['id'] }}">
                                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-indigo-200/80 pb-4">
                                    <div>
                                        <span class="inline-block rounded-md bg-indigo-100 px-3 py-1 text-xs font-bold uppercase tracking-wider text-indigo-900">
                                            {{ __('Activity Type') }}: {{ $curriculumSection['activity']['type_label'] ?? __('Practice Activity') }}
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-4 text-xs font-semibold text-neutral-600">
                                        <span><i class="fa-solid fa-layer-group text-indigo-600" aria-hidden="true"></i> {{ $curriculumSection['activity']['unit_count'] ?? 1 }} {{ __('Units') }}</span>
                                        <span><i class="fa-solid fa-clock text-indigo-600" aria-hidden="true"></i> ~{{ $curriculumSection['activity']['estimated_minutes'] ?? 3 }} {{ __('min') }}</span>
                                    </div>
                                </div>
                                <p class="text-base font-medium text-neutral-800 leading-relaxed">
                                    {{ $curriculumSection['activity']['summary'] ?? __('Practice key concepts covered in this lesson.') }}
                                </p>
                                <div class="pt-2 flex justify-center">
                                    <a href="{{ isset($curriculumPreview) ? route((Auth::user()?->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.preview.activities.show', [$curriculumPreview, $block['activity_code'] ?? '']) : route('curriculum.activities.show', $block['activity_code'] ?? '') }}" class="inline-flex min-h-12 items-center gap-3 rounded-xl bg-indigo-700 px-8 py-3 text-base font-bold text-white shadow-md hover:bg-indigo-800 transition-all">
                                        <span>{{ __('Start Activity') }}</span>
                                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                                    </a>
                                </div>
                            </section>
                            @break

                        @default
                            <p class="whitespace-pre-line break-words leading-7 text-neutral-800">
                                @include('curriculum.partials.rich-text', ['runs' => $block['runs'] ?? [], 'text' => $block['text'] ?? ''])
                            </p>
                            @break
                    @endswitch
                    @if(isset($block['asset']['url']))
                        <figure class="rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                            @if(($block['asset']['kind'] ?? null) === 'image')
                                <img src="{{ $block['asset']['url'] }}" alt="{{ $block['asset']['accessibility_text'] }}" class="mx-auto max-h-[32rem] max-w-full rounded-md" loading="lazy">
                            @elseif(($block['asset']['kind'] ?? null) === 'audio')
                                <audio controls preload="metadata" class="w-full"><source src="{{ $block['asset']['url'] }}" type="{{ $block['asset']['mime_type'] ?? '' }}">{{ __('Your browser does not support embedded audio.') }}</audio>
                            @endif
                            <figcaption class="mt-2 text-sm text-neutral-700"><span class="font-semibold">{{ $block['asset']['display_name'] }}</span>: {{ $block['asset']['accessibility_text'] }}</figcaption>
                        </figure>
                    @endif
                @endforeach
            </article>

            <nav class="mt-6 grid gap-3 sm:grid-cols-2" aria-label="{{ __('Lesson section navigation') }}">
                @if ($curriculumSection['navigation']['previous'])
                    <a rel="prev" href="{{ isset($curriculumPreview) ? route((Auth::user()->isSuperAdmin() ? 'superadmin' : 'admin').'.curriculum-drafts.preview.sections.show', [$curriculumPreview, $curriculumSection['navigation']['previous']['code']]) : route('curriculum.sections.show', $curriculumSection['navigation']['previous']['code']) }}" class="hsp-section-nav hsp-section-nav--previous">
                        <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                        <span><span>{{ __('Previous part') }}</span>{{ $curriculumSection['navigation']['previous']['title'] }}</span>
                    </a>
                @else
                    <span></span>
                @endif
                @if (!isset($curriculumPreview) && $curriculumSection['step']['is_last_section'])
                    <a rel="next" href="{{ $stepCheckpointUrl }}" class="hsp-section-nav hsp-section-nav--next">
                        <span><span>{{ __('Step wrap-up') }}</span>{{ __('Finish learning step :number', ['number' => $curriculumSection['step']['number']]) }}</span>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                @elseif ($curriculumSection['navigation']['next'])
                    <a rel="next" href="{{ $nextSectionUrl }}" class="hsp-section-nav hsp-section-nav--next">
                        <span><span>{{ $primaryActivity ? __('Skip Activity') : __('Next part') }}</span>{{ $curriculumSection['navigation']['next']['title'] }}</span>
                        <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                    </a>
                @endif
            </nav>
        @endif

        @if($showCurriculumEvidence && Auth::user()?->isSuperAdmin())
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

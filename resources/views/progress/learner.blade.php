@extends('layouts.app')

@section('title', __('admin.learner_progress_detail_title', ['name' => $detail['learner']->name]))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    <div class="flex min-h-screen flex-col bg-neutral-100 md:flex-row">
        @if($administrationRoutePrefix === 'supervisor')
            @include('supervisor.sidebar')
        @else
            @include('superadmin.sidebar')
        @endif

        <main class="min-w-0 flex-1 p-6 md:p-10">
            <x-back-control :href="$backUrl ?? route($backRouteName)" :label="__('admin.back_to_progress')" />
            <header class="mt-5">
                <h1 class="text-3xl font-bold text-neutral-900">{{ $detail['learner']->name }}</h1>
                <p class="mt-2 text-neutral-600">
                    @if(auth()->user()?->isSuperAdmin())
                        {{ $detail['learner']->email }} <span aria-hidden="true">·</span>
                    @endif
                    {{ isset($scopeInstitution) ? $scopeInstitution->displayName(app()->getLocale()) : $detail['learner']->instansi }}
                </p>
                @isset($scopeClass)<p class="mt-1 font-semibold text-neutral-700">{{ $scopeClass->title }}</p>@endisset
                @if(auth()->user()?->isSuperAdmin())<p class="mt-1 text-sm text-neutral-500">{{ __('admin.metadata_only_detail') }}</p>@endif
            </header>

            @if(auth()->user()?->isSuperAdmin())
            <nav class="mt-6 rounded-lg bg-white p-5 shadow-sm" aria-label="{{ __('admin.package_version_navigation') }}">
                <h2 class="font-semibold text-neutral-900">{{ __('admin.package_versions') }}</h2>
                @if($detail['inventory']->isEmpty())
                    <p class="mt-2 text-sm text-neutral-600">{{ __('admin.no_package_versions') }}</p>
                @else
                    <ul class="mt-3 flex flex-wrap gap-2">
                        @foreach($detail['inventory'] as $version)
                            @php($selected = $detail['selection']['package_name'] === $version['package_name'] && $detail['selection']['content_version'] === $version['content_version'])
                            <li>
                                <a href="{{ route($detailRouteName, array_merge($detailRouteParameters ?? ['learner' => $detail['learner']], ['package' => $version['package_name'], 'version' => $version['content_version']])) }}"
                                   @if($selected) aria-current="page" @endif
                                   class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-sm font-semibold {{ $selected ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-neutral-300 bg-white text-neutral-700 hover:bg-neutral-50' }}">
                                    {{ $version['package_name'] }} {{ $version['content_version'] }}
                                    @if($version['is_active'])<span>({{ __('admin.active') }})</span>@endif
                                    @if(! $version['is_available'])<span>({{ __('admin.archived_definition_unavailable') }})</span>@endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </nav>

            @if($detail['selection']['content_version'] !== null && ! $detail['selection']['is_available'])
                <div class="mt-6 rounded-lg border border-amber-300 bg-amber-50 p-5 text-amber-950" role="status">
                    <h2 class="font-semibold">{{ __('admin.stale_package_heading') }}</h2>
                    <p class="mt-1 text-sm">{{ __('admin.stale_package_description') }}</p>
                </div>
            @endif
            @endif

            <section class="mt-6" aria-labelledby="learner-summary-title">
                <h2 id="learner-summary-title" class="sr-only">{{ __('admin.progress_overview') }}</h2>
                <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    @foreach([
                        'completion_percent' => 'current_completion',
                        'known_activities' => 'known_activities',
                        'tracked_activities' => 'tracked_activities',
                        'completed_activities' => 'completed_activities',
                        'attempt_count' => 'attempts',
                    ] as $key => $label)
                        <div class="rounded-lg bg-white p-5 shadow-sm">
                            <dt class="text-sm text-neutral-500">{{ __('admin.'.$label) }}</dt>
                            <dd class="mt-1 text-2xl font-semibold tabular-nums text-neutral-900">{{ $detail['summary'][$key] }}{{ $key === 'completion_percent' ? '%' : '' }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            @isset($submittedResponses)
                @if($submittedResponses->isNotEmpty())
                    <section class="mt-6 rounded-xl bg-white p-6 shadow-sm" aria-labelledby="submitted-assessments-heading">
                        <h2 id="submitted-assessments-heading" class="text-2xl font-bold text-neutral-950">{{ __('responses.submitted_assessments') }}</h2>
                        <p class="mt-2 text-neutral-600">{{ __('responses.submitted_assessments_intro') }}</p>
                        <div class="mt-5 space-y-4">
                            @foreach($submittedResponses as $submittedResponse)
                                <article class="rounded-lg border border-neutral-200 p-4">
                                    <p class="text-sm font-semibold text-indigo-700">{{ $submittedResponse->activity?->payloadData()['title'] ?? __('responses.assessment') }}</p>
                                    <h3 class="mt-1 font-bold leading-7 text-neutral-950">{{ $submittedResponse->prompt?->payloadData()['stem'] ?? __('responses.assessment') }}</h3>
                                    <p class="mt-3 whitespace-pre-wrap rounded-lg bg-neutral-50 p-4 leading-7 text-neutral-800">{{ $submittedResponse->body }}</p>
                                    <p class="mt-3 text-sm text-neutral-600">{{ __('responses.submitted_at', ['time' => $submittedResponse->submitted_at?->diffForHumans()]) }}</p>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endisset

            <div class="mt-6 space-y-6">
                @forelse($detail['hierarchy'] as $chapter)
                    <section class="rounded-lg bg-white p-6 shadow-sm" aria-labelledby="chapter-{{ $chapter['code'] }}">
                        <div class="border-b border-neutral-200 pb-4">
                            <p class="text-sm font-semibold uppercase tracking-wide text-indigo-700">{{ __('admin.module_number', ['number' => $chapter['module']]) }}</p>
                            <h2 id="chapter-{{ $chapter['code'] }}" class="mt-1 text-2xl font-bold text-neutral-900">{{ $chapter['title'] }}</h2>
                            @if(auth()->user()?->isSuperAdmin())<p class="mt-1 text-xs text-neutral-500">{{ $chapter['code'] }} <span aria-hidden="true">·</span> {{ $chapter['lifecycle_status'] }}</p>@endif
                        </div>
                        <div class="mt-5 space-y-6">
                            @forelse($chapter['sections'] as $section)
                                <section aria-labelledby="section-{{ $section['code'] }}">
                                    <h3 id="section-{{ $section['code'] }}" class="text-lg font-semibold text-neutral-900">{{ $section['title'] }}</h3>
                                    @if(auth()->user()?->isSuperAdmin())<p class="mt-1 text-xs text-neutral-500">{{ $section['code'] }}</p>@endif
                                    @if($section['activities']->isEmpty())
                                        <p class="mt-2 text-sm text-neutral-500">{{ __('admin.no_activities_in_section') }}</p>
                                    @else
                                        <div class="mt-3 overflow-x-auto rounded-md border border-neutral-200">
                                            <table class="w-full text-left text-sm text-neutral-700">
                                                <caption class="sr-only">{{ __('admin.section_activity_progress', ['section' => $section['title']]) }}</caption>
                                                <thead class="bg-neutral-50 text-xs uppercase text-neutral-600">
                                                    <tr>
                                                        <th scope="col" class="px-4 py-3">{{ __('admin.activity') }}</th>
                                                        <th scope="col" class="px-4 py-3">{{ __('admin.progress_status') }}</th>
                                                        <th scope="col" class="px-4 py-3">{{ __('admin.attempts') }}</th>
                                                        <th scope="col" class="px-4 py-3">{{ __('admin.last_activity') }}</th>
                                                        <th scope="col" class="px-4 py-3">{{ __('admin.completion_time') }}</th>
                                                        @if(auth()->user()?->isSuperAdmin())<th scope="col" class="px-4 py-3">{{ __('admin.notes') }}</th>@endif
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-neutral-200">
                                                    @foreach($section['activities'] as $activity)
                                                    <tr>
                                                        <th scope="row" class="px-4 py-3 font-semibold text-neutral-900">
                                                            {{ $activity['title'] }}
                                                            @if(auth()->user()?->isSuperAdmin())<span class="mt-1 block font-mono text-xs font-normal text-neutral-500">{{ $activity['code'] }}</span>@endif
                                                        </th>
                                                        <td class="px-4 py-3">{{ __('admin.progress_states.'.$activity['state']) }}</td>
                                                        <td class="px-4 py-3 tabular-nums">{{ $activity['attempt_count'] }}</td>
                                                        <td class="px-4 py-3 whitespace-nowrap">{{ $activity['last_activity_at']?->format('Y-m-d H:i') ?? __('admin.no_activity_recorded') }}</td>
                                                        <td class="px-4 py-3 whitespace-nowrap">{{ $activity['completed_at']?->format('Y-m-d H:i') ?? __('admin.not_completed') }}</td>
                                                        @if(auth()->user()?->isSuperAdmin())<td class="px-4 py-3">
                                                            @if($activity['legacy_status'])
                                                                <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-900">{{ __('admin.migrated_legacy_completion') }}</span>
                                                            @elseif($activity['baseline_skipped_at'])
                                                                <span class="rounded-full bg-neutral-100 px-2 py-1 text-xs font-semibold text-neutral-700">{{ __('admin.baseline_skipped') }}</span>
                                                            @else
                                                                <span class="text-neutral-500">{{ __('None') }}</span>
                                                            @endif
                                                        </td>@endif
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @endif
                                </section>
                            @empty
                                <p class="text-sm text-neutral-500">{{ __('admin.no_sections_in_module') }}</p>
                            @endforelse
                        </div>
                    </section>
                @empty
                    @if($detail['selection']['content_version'] === null)
                        <div class="rounded-lg bg-white p-8 text-center text-neutral-600 shadow-sm">{{ auth()->user()?->isSuperAdmin() ? __('admin.no_progress_or_packages') : __('No learning progress is available.') }}</div>
                    @elseif($detail['unresolved']->isEmpty())
                        <div class="rounded-lg bg-white p-8 text-center text-neutral-600 shadow-sm">{{ auth()->user()?->isSuperAdmin() ? __('admin.no_canonical_hierarchy_for_version') : __('No learning progress is available.') }}</div>
                    @endif
                @endforelse

                @if(auth()->user()?->isSuperAdmin() && $detail['unresolved']->isNotEmpty())
                    <section class="rounded-lg border border-amber-300 bg-amber-50 p-6" aria-labelledby="unresolved-title">
                        <h2 id="unresolved-title" class="text-xl font-semibold text-amber-950">{{ __('admin.unresolved_historical_progress') }}</h2>
                        <p class="mt-2 text-sm text-amber-900">{{ __('admin.unresolved_historical_progress_description') }}</p>
                        <div class="mt-4 overflow-x-auto rounded-md border border-amber-200 bg-white">
                            <table class="w-full text-left text-sm text-neutral-700">
                                <caption class="sr-only">{{ __('admin.unresolved_progress_table') }}</caption>
                                <thead class="bg-amber-100 text-xs uppercase text-amber-950"><tr>
                                    <th scope="col" class="px-4 py-3">{{ __('admin.activity_code') }}</th>
                                    <th scope="col" class="px-4 py-3">{{ __('admin.section_code') }}</th>
                                    <th scope="col" class="px-4 py-3">{{ __('admin.progress_status') }}</th>
                                    <th scope="col" class="px-4 py-3">{{ __('admin.attempts') }}</th>
                                    <th scope="col" class="px-4 py-3">{{ __('admin.last_activity') }}</th>
                                    <th scope="col" class="px-4 py-3">{{ __('admin.completion_time') }}</th>
                                </tr></thead>
                                <tbody class="divide-y divide-neutral-200">
                                    @foreach($detail['unresolved'] as $activity)
                                        <tr>
                                            <th scope="row" class="px-4 py-3 font-mono text-xs">{{ $activity['code'] }}</th>
                                            <td class="px-4 py-3 font-mono text-xs">{{ $activity['section_code'] }}</td>
                                            <td class="px-4 py-3">{{ __('admin.progress_states.'.$activity['state']) }}</td>
                                            <td class="px-4 py-3">{{ $activity['attempt_count'] }}</td>
                                            <td class="px-4 py-3">{{ $activity['last_activity_at']?->format('Y-m-d H:i') ?? __('admin.no_activity_recorded') }}</td>
                                            <td class="px-4 py-3">{{ $activity['completed_at']?->format('Y-m-d H:i') ?? __('admin.not_completed') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </section>
                @endif
            </div>
        </main>
    </div>
@endsection

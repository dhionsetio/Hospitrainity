@extends('layouts.app')

@section('title', __('admin.learner_progress_title'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    <div class="flex min-h-screen flex-col bg-neutral-100 md:flex-row">
        @include('superadmin.sidebar')

        <main class="min-w-0 flex-1 p-6 md:p-10">
            <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-neutral-900">{{ __('admin.learner_progress') }}</h1>
                    <p class="mt-2 max-w-3xl text-neutral-600">{{ __('admin.learner_progress_description') }}</p>
                </div>
                <div>
                    <x-button :href="route('superadmin.progress.export', request()->query())" variant="primary" class="inline-flex items-center gap-2">
                        <i class="fas fa-file-csv" aria-hidden="true"></i> {{ __('Export CSV') }}
                    </x-button>
                </div>
            </header>

            <section class="my-6 rounded-lg border border-blue-200 bg-blue-50 p-5" aria-labelledby="progress-boundary-title">
                <h2 id="progress-boundary-title" class="font-semibold text-blue-950">{{ __('admin.progress_privacy_boundary') }}</h2>
                <p class="mt-1 text-sm text-blue-900">{{ __('admin.progress_metadata_only_notice') }}</p>
            </section>

            @include('progress.overview', ['overview' => $overview])

            <section class="mt-6 rounded-lg bg-white p-6 shadow-sm" aria-labelledby="progress-filters-title">
                <h2 id="progress-filters-title" class="text-lg font-semibold text-neutral-900">{{ __('admin.filter_progress') }}</h2>
                @if($errors->any())
                    <div class="mt-4 rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-900" role="alert">
                        <p class="font-semibold">{{ __('admin.filter_error') }}</p>
                        <ul class="mt-2 list-disc pl-5">
                            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif
                <form method="GET" action="{{ route('superadmin.progress.index') }}" class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" data-progress-filter-form>
                    <div>
                        <label for="progress-q" class="block text-sm font-medium text-neutral-700">{{ __('admin.learner_search') }}</label>
                        <input id="progress-q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" maxlength="100" class="mt-1 w-full rounded-md border-neutral-300" placeholder="{{ __('admin.search_users_placeholder') }}">
                    </div>
                    <div>
                        <label for="progress-institution" class="block text-sm font-medium text-neutral-700">{{ __('admin.institution') }}</label>
                        <select id="progress-institution" name="institution" class="mt-1 w-full rounded-md border-neutral-300">
                            <option value="">{{ __('admin.all_institutions') }}</option>
                            @foreach($options['institutions'] as $institution)
                                <option value="{{ $institution }}" @selected(($filters['institution'] ?? '') === $institution)>{{ $institution }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="progress-version" class="block text-sm font-medium text-neutral-700">{{ __('admin.package_version') }}</label>
                        <select id="progress-version" name="version" class="mt-1 w-full rounded-md border-neutral-300">
                            <option value="">{{ __('admin.all_package_versions') }}</option>
                            @foreach($options['versions']->unique('content_version') as $version)
                                <option value="{{ $version['content_version'] }}" @selected(($filters['version'] ?? '') === $version['content_version'])>
                                    {{ $version['content_version'] }}{{ $version['is_active'] ? ', '.__('admin.active') : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="progress-module" class="block text-sm font-medium text-neutral-700">{{ __('admin.module') }}</label>
                        <select id="progress-module" name="module" class="mt-1 w-full rounded-md border-neutral-300">
                            <option value="">{{ __('admin.all_modules') }}</option>
                            @foreach($options['modules'] as $module)
                                <option value="{{ $module['code'] }}" @selected(($filters['module'] ?? '') === $module['code'])>
                                    {{ $module['module'] ? __('admin.module_number', ['number' => $module['module']]).': ' : '' }}{{ $module['title'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="progress-status" class="block text-sm font-medium text-neutral-700">{{ __('admin.progress_status') }}</label>
                        <select id="progress-status" name="status" class="mt-1 w-full rounded-md border-neutral-300">
                            <option value="">{{ __('admin.all_progress_states') }}</option>
                            @foreach(['viewed', 'started', 'attempted', 'self_checked', 'completed'] as $state)
                                <option value="{{ $state }}" @selected(($filters['status'] ?? '') === $state)>{{ __('admin.progress_states.'.$state) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="progress-recent" class="block text-sm font-medium text-neutral-700">{{ __('admin.recent_activity') }}</label>
                        <select id="progress-recent" name="recent" class="mt-1 w-full rounded-md border-neutral-300">
                            <option value="">{{ __('admin.any_activity_date') }}</option>
                            @foreach(['7', '30', '90'] as $days)
                                <option value="{{ $days }}" @selected(($filters['recent'] ?? '') === $days)>{{ __('admin.within_days', ['days' => $days]) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-3 md:col-span-2 xl:col-span-3">
                        <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 font-semibold text-white hover:bg-indigo-700" data-progress-submit>
                            <span data-progress-ready>{{ __('admin.apply_filters') }}</span>
                            <span data-progress-loading hidden>{{ __('admin.loading_progress') }}</span>
                        </button>
                        <a href="{{ route('superadmin.progress.index') }}" class="rounded-md border border-neutral-300 px-4 py-2 font-semibold text-neutral-700 hover:bg-neutral-50">{{ __('admin.clear_filters') }}</a>
                        <span class="sr-only" aria-live="polite" data-progress-status></span>
                    </div>
                </form>
            </section>

            <section class="mt-6 overflow-hidden rounded-lg bg-white shadow-sm" aria-labelledby="learner-results-title">
                <div class="border-b border-neutral-200 px-6 py-4">
                    <h2 id="learner-results-title" class="text-lg font-semibold text-neutral-900">{{ __('admin.learner_results') }}</h2>
                    <p class="mt-1 text-sm text-neutral-500">{{ trans_choice('admin.learners_found', $learners->total(), ['count' => $learners->total()]) }}</p>
                </div>
                <div class="divide-y divide-neutral-200 md:hidden">
                    @forelse($learners as $row)
                        @php($learner = $row['learner'])
                        @php($summary = $row['summary'])
                        <article class="space-y-4 p-5">
                            <div>
                                <h3 class="font-semibold text-neutral-900">{{ $learner->name }}</h3>
                                <p class="break-all text-sm text-neutral-600">{{ $learner->email }}</p>
                            </div>
                            <dl class="grid grid-cols-2 gap-3 text-sm">
                                <div class="col-span-2"><dt class="font-semibold text-neutral-700">{{ __('admin.institution') }}</dt><dd>{{ $learner->instansi }}</dd></div>
                                <div><dt class="font-semibold text-neutral-700">{{ __('admin.current_completion') }}</dt><dd class="tabular-nums">{{ $summary['overall_percent'] }}%</dd></div>
                                <div><dt class="font-semibold text-neutral-700">{{ __('admin.progress_status') }}</dt><dd>{{ __('admin.progress_states.'.$summary['latest_state']) }}</dd></div>
                                <div><dt class="font-semibold text-neutral-700">{{ __('admin.attempts') }}</dt><dd class="tabular-nums">{{ $summary['attempt_count'] }}</dd></div>
                                <div><dt class="font-semibold text-neutral-700">{{ __('admin.last_activity') }}</dt><dd>{{ $summary['last_activity_at']?->format('Y-m-d H:i') ?? __('admin.no_activity_recorded') }}</dd></div>
                            </dl>
                            <a href="{{ route('superadmin.progress.learners.show', $learner) }}" class="inline-flex min-h-11 items-center font-semibold text-indigo-700 underline underline-offset-2">{{ __('admin.view_progress_detail') }}</a>
                        </article>
                    @empty
                        <p class="p-6 text-center text-neutral-500">{{ __('admin.no_progress_results') }}</p>
                    @endforelse
                </div>
                <div class="hidden overflow-x-auto md:block">
                    <table class="w-full text-left text-sm text-neutral-700">
                        <caption class="sr-only">{{ __('admin.global_learner_progress_table') }}</caption>
                        <thead class="bg-neutral-50 text-xs uppercase text-neutral-600">
                            <tr>
                                <th scope="col" class="px-6 py-3">{{ __('admin.account') }}</th>
                                <th scope="col" class="px-6 py-3">{{ __('admin.institution') }}</th>
                                <th scope="col" class="px-6 py-3">{{ __('admin.current_completion') }}</th>
                                <th scope="col" class="px-6 py-3">{{ __('admin.progress_status') }}</th>
                                <th scope="col" class="px-6 py-3">{{ __('admin.attempts') }}</th>
                                <th scope="col" class="px-6 py-3">{{ __('admin.last_activity') }}</th>
                                <th scope="col" class="px-6 py-3">{{ __('admin.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            @forelse($learners as $row)
                                @php($learner = $row['learner'])
                                @php($summary = $row['summary'])
                                <tr>
                                    <th scope="row" class="px-6 py-4 font-semibold text-neutral-900">
                                        {{ $learner->name }}
                                        <span class="mt-1 block font-normal text-neutral-500">{{ $learner->email }}</span>
                                    </th>
                                    <td class="px-6 py-4">{{ $learner->instansi }}</td>
                                    <td class="px-6 py-4">
                                        <span class="font-semibold tabular-nums">{{ $summary['overall_percent'] }}%</span>
                                        <span class="block text-xs text-neutral-500">{{ __('admin.completed_of_tracked', ['completed' => $summary['completed_activities'], 'tracked' => $summary['tracked_activities']]) }}</span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex rounded-full bg-neutral-100 px-2 py-1 text-xs font-semibold text-neutral-800">{{ __('admin.progress_states.'.$summary['latest_state']) }}</span>
                                    </td>
                                    <td class="px-6 py-4 tabular-nums">{{ $summary['attempt_count'] }}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">{{ $summary['last_activity_at']?->format('Y-m-d H:i') ?? __('admin.no_activity_recorded') }}</td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="{{ route('superadmin.progress.learners.show', $learner) }}" class="font-semibold text-indigo-700 hover:text-indigo-900">{{ __('admin.view_progress_detail') }}</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-6 py-10 text-center text-neutral-500">{{ __('admin.no_progress_results') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-neutral-200 p-4">{{ $learners->links() }}</div>
            </section>
        </main>
    </div>
@endsection

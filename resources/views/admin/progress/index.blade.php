@extends('layouts.app')

@section('title', __('admin.aggregate_progress_title'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    <div class="flex min-h-screen flex-col bg-neutral-100 md:flex-row">
        @include('superadmin.sidebar')

        <main class="min-w-0 flex-1 p-6 md:p-10">
            <header class="mb-8">
                <h1 class="text-3xl font-bold text-neutral-900">{{ __('admin.aggregate_progress') }}</h1>
                <p class="mt-2 max-w-3xl text-neutral-600">{{ __('admin.aggregate_progress_description') }}</p>
            </header>

            <section class="mb-6 rounded-lg border border-blue-200 bg-blue-50 p-5" aria-labelledby="aggregate-boundary-title">
                <h2 id="aggregate-boundary-title" class="font-semibold text-blue-950">{{ __('admin.progress_privacy_boundary') }}</h2>
                <p class="mt-1 text-sm text-blue-900">{{ __('admin.admin_aggregate_only_notice') }}</p>
            </section>

            @include('progress.overview', ['overview' => $overview])

            <section class="mt-6 rounded-lg border border-neutral-200 bg-white p-6 shadow-sm" aria-labelledby="export-title">
                <h2 id="export-title" class="text-lg font-semibold text-neutral-900">{{ __('admin.progress_export') }}</h2>
                <p class="mt-2 text-sm text-neutral-600">{{ __('admin.progress_export_disabled_reason') }}</p>
                <button type="button" disabled aria-disabled="true" class="mt-4 cursor-not-allowed rounded-md bg-neutral-200 px-4 py-2 font-semibold text-neutral-500">
                    {{ __('admin.csv_export_unavailable') }}
                </button>
            </section>
        </main>
    </div>
@endsection

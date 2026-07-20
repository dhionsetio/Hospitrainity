@extends('layouts.app')

@section('title', __('Privacy request queue').' - Hospitrainity')

@section('content')
    <main id="main-content" class="mx-auto max-w-6xl space-y-6 px-4 py-8 sm:px-6">
        <header>
            <a href="{{ route('superadmin.dashboard') }}" class="font-semibold text-indigo-700 underline">&larr; {{ __('System Admin dashboard') }}</a>
            <h1 class="mt-4 text-3xl font-bold">{{ __('Privacy request queue') }}</h1>
            <p class="mt-2 text-neutral-700">{{ __('Review identity, scope, retention, and retained evidence before making a decision. Opening this queue and every status change are privileged actions.') }}</p>
        </header>
        <form method="GET" class="flex flex-wrap items-end gap-3 rounded-lg border bg-white p-4">
            <div>
                <label for="request-status-filter" class="block text-sm font-semibold">{{ __('Status') }}</label>
                <select id="request-status-filter" name="status" class="mt-1 rounded-md border-neutral-300 py-2">
                    <option value="">{{ __('All statuses') }}</option>
                    @foreach($statuses as $status)<option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ __(str_replace('_', ' ', ucfirst($status->value))) }}</option>@endforeach
                </select>
            </div>
            <button class="rounded-md bg-indigo-700 px-4 py-2 font-semibold text-white">{{ __('Filter') }}</button>
        </form>
        <div class="space-y-3">
            @forelse($requests as $privacyRequest)
                <a href="{{ route('superadmin.privacy-requests.show', $privacyRequest) }}" class="block rounded-lg border border-neutral-200 bg-white p-5 shadow-sm hover:border-indigo-500">
                    <div class="flex flex-wrap justify-between gap-3">
                        <div><h2 class="font-bold">{{ __(str_replace('_', ' ', ucfirst($privacyRequest->type->value))) }}</h2><p class="font-mono text-xs text-neutral-600">{{ $privacyRequest->getKey() }}</p></div>
                        <span class="font-semibold">{{ __(str_replace('_', ' ', ucfirst($privacyRequest->status->value))) }}</span>
                    </div>
                    <p class="mt-3 text-sm text-neutral-700">{{ __('Subject') }}: {{ $privacyRequest->user?->name ?? __('Pseudonymized/deleted user') }} &middot; {{ __('Due') }}: {{ $privacyRequest->due_at->toDayDateTimeString() }}</p>
                </a>
            @empty
                <p class="rounded-lg border border-dashed bg-white p-6">{{ __('No requests match this filter.') }}</p>
            @endforelse
        </div>
        {{ $requests->links() }}
    </main>
@endsection

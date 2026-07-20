@extends('layouts.app')

@section('title', __('Review privacy request').' - Hospitrainity')

@section('content')
    <main id="main-content" class="mx-auto max-w-5xl space-y-6 px-4 py-8 sm:px-6">
        <x-back-control :href="route('superadmin.privacy-requests.index')" :label="__('Privacy request queue')" />
        <header>
            <h1 class="text-3xl font-bold">{{ __('Review privacy request') }}</h1>
            <p class="mt-2 font-mono text-sm">{{ $privacyRequest->getKey() }}</p>
        </header>
        @if(session('status'))<p class="rounded-md border border-green-300 bg-green-50 p-4 text-green-900" role="status">{{ session('status') }}</p>@endif
        @if($errors->any())<div class="rounded-md border border-red-300 bg-red-50 p-4 text-red-900" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

        <section class="rounded-lg border bg-white p-6" aria-labelledby="request-summary">
            <h2 id="request-summary" class="text-xl font-bold">{{ __('Request summary') }}</h2>
            <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                <div><dt class="font-semibold">{{ __('Type') }}</dt><dd>{{ __(str_replace('_', ' ', ucfirst($privacyRequest->type->value))) }}</dd></div>
                <div><dt class="font-semibold">{{ __('Status') }}</dt><dd>{{ __(str_replace('_', ' ', ucfirst($privacyRequest->status->value))) }}</dd></div>
                <div><dt class="font-semibold">{{ __('Subject') }}</dt><dd>{{ $privacyRequest->user?->name ?? __('Pseudonymized/deleted user') }}</dd></div>
                <div><dt class="font-semibold">{{ __('Identity verified') }}</dt><dd>{{ $privacyRequest->identity_verified_at?->toDayDateTimeString() ?? __('Pending') }}</dd></div>
                <div><dt class="font-semibold">{{ __('Submitted') }}</dt><dd>{{ $privacyRequest->created_at->toDayDateTimeString() }}</dd></div>
                <div><dt class="font-semibold">{{ __('Due') }}</dt><dd>{{ $privacyRequest->due_at->toDayDateTimeString() }}</dd></div>
                <div class="sm:col-span-2"><dt class="font-semibold">{{ __('User note') }}</dt><dd class="mt-1 whitespace-pre-wrap">{{ $privacyRequest->request_note ?: __('No note supplied.') }}</dd></div>
                <div class="sm:col-span-2"><dt class="font-semibold">{{ __('Decision note') }}</dt><dd class="mt-1 whitespace-pre-wrap">{{ $privacyRequest->decision_note ?: __('Not decided.') }}</dd></div>
            </dl>
        </section>

        @if($actions !== [])
            <section class="rounded-lg border bg-white p-6" aria-labelledby="request-action">
                <h2 id="request-action" class="text-xl font-bold">{{ __('Record reviewed action') }}</h2>
                <form method="POST" action="{{ route('superadmin.privacy-requests.update', $privacyRequest) }}" class="mt-4 space-y-4">
                    @csrf @method('PATCH')
                    <div><label for="new-request-status" class="block font-semibold">{{ __('New status') }}</label><select id="new-request-status" name="status" required class="mt-1 w-full rounded-md border-neutral-300 py-3"><option value="">{{ __('Choose an action') }}</option>@foreach($actions as $action)<option value="{{ $action->value }}">{{ __(str_replace('_', ' ', ucfirst($action->value))) }}</option>@endforeach</select></div>
                    <div><label for="request-reason" class="block font-semibold">{{ __('Bounded reason code') }}</label><input id="request-reason" name="reason_code" maxlength="80" pattern="[a-z0-9_-]+" class="mt-1 w-full rounded-md border-neutral-300" placeholder="review_complete"></div>
                    <div><label for="request-decision-note" class="block font-semibold">{{ __('Decision note visible to the requester') }}</label><textarea id="request-decision-note" name="decision_note" maxlength="2000" rows="4" class="mt-1 w-full rounded-md border-neutral-300"></textarea></div>
                    <button class="rounded-md bg-indigo-700 px-5 py-3 font-semibold text-white">{{ __('Save reviewed action') }}</button>
                </form>
            </section>
        @endif

        <section class="rounded-lg border bg-white p-6" aria-labelledby="request-events">
            <h2 id="request-events" class="text-xl font-bold">{{ __('Immutable event history') }}</h2>
            <ol class="mt-4 space-y-3">
                @foreach($privacyRequest->events as $event)
                    <li class="border-l-2 border-indigo-300 pl-4"><p class="font-semibold">{{ $event->event }}</p><p class="text-sm text-neutral-600">{{ $event->created_at->toDayDateTimeString() }} &middot; {{ $event->actor?->name ?? __('System') }}</p></li>
                @endforeach
            </ol>
        </section>
    </main>
@endsection

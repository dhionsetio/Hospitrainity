@extends('layouts.app')

@section('title', __('Privacy and account requests').' - Hospitrainity')

@section('content')
    <main id="main-content" class="mx-auto max-w-5xl space-y-8 px-4 py-8 sm:px-6">
        <header>
            <x-back-control :href="$returnUrl" :label="__('Return to current dashboard')" />
            <h1 class="mt-4 text-3xl font-bold text-neutral-900">{{ __('Privacy and account requests') }}</h1>
            <p class="mt-2 max-w-3xl text-neutral-700">{{ __('Submit one tracked request at a time for each request type. Status and reasons remain visible here; sensitive exports and deletion require recent password confirmation.') }}</p>
        </header>

        @if(session('status'))<p class="rounded-md border border-green-300 bg-green-50 p-4 text-green-900" role="status">{{ session('status') }}</p>@endif
        @if($errors->any())
            <div class="rounded-md border border-red-300 bg-red-50 p-4 text-red-900" role="alert" tabindex="-1">
                <p class="font-bold">{{ __('Review the highlighted request fields.') }}</p>
                <ul class="mt-2 list-disc pl-6">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <section aria-labelledby="sensitive-request-heading">
            <h2 id="sensitive-request-heading" class="text-xl font-bold">{{ __('Sensitive requests') }}</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <a href="{{ route('privacy-requests.sensitive', ['type' => 'access-export']) }}" class="rounded-lg border border-indigo-300 bg-white p-5 shadow-sm hover:border-indigo-600">
                    <span class="text-lg font-bold text-indigo-800">{{ __('Download my data') }}</span>
                    <span class="mt-2 block text-sm text-neutral-700">{{ __('Creates an encrypted JSON and CSV archive with a short-lived signed download.') }}</span>
                </a>
                <a href="{{ route('privacy-requests.sensitive', ['type' => 'deletion']) }}" class="rounded-lg border border-red-300 bg-white p-5 shadow-sm hover:border-red-600">
                    <span class="text-lg font-bold text-red-800">{{ __('Request account deletion') }}</span>
                    <span class="mt-2 block text-sm text-neutral-700">{{ __('Starts a reviewed pseudonymization and deletion workflow; it is not immediate.') }}</span>
                </a>
            </div>
        </section>

        <section class="rounded-lg border border-neutral-200 bg-white p-6 shadow-sm" aria-labelledby="general-request-heading">
            <h2 id="general-request-heading" class="text-xl font-bold">{{ __('Other requests') }}</h2>
            <form method="POST" action="{{ route('privacy-requests.store') }}" class="mt-5 space-y-5">
                @csrf
                <div>
                    <label for="privacy-request-type" class="block font-semibold">{{ __('Request type') }}</label>
                    <select id="privacy-request-type" name="type" required class="mt-1 block w-full rounded-md border-neutral-300 py-3">
                        <option value="">{{ __('Choose a request') }}</option>
                        @foreach($generalTypes as $type)<option value="{{ $type->value }}" @selected(old('type') === $type->value)>{{ __(str_replace('_', ' ', ucfirst($type->value))) }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="privacy-request-note" class="block font-semibold">{{ __('What should be reviewed?') }}</label>
                    <textarea id="privacy-request-note" name="note" rows="4" maxlength="2000" class="mt-1 block w-full rounded-md border-neutral-300">{{ old('note') }}</textarea>
                    <p class="mt-1 text-sm text-neutral-600">{{ __('Do not include passwords, reset links, classroom codes, or unnecessary information about another person.') }}</p>
                </div>
                <button class="rounded-md bg-indigo-700 px-5 py-3 font-semibold text-white hover:bg-indigo-800">{{ __('Submit request') }}</button>
            </form>
        </section>

        <section class="rounded-lg border border-neutral-200 bg-white p-6 shadow-sm" aria-labelledby="push-settings-heading"
            @if($pushConfigured)
                data-push-settings
                data-public-key="{{ $pushPublicKey }}"
                data-store-url="{{ route('push-subscriptions.store') }}"
                data-destroy-base-url="{{ url('/push-subscriptions') }}"
                data-test-url="{{ route('push-subscriptions.test') }}"
                data-subscription-id="{{ $pushSubscription?->getKey() }}"
                data-unsupported-message="{{ __('This browser or connection does not support secure Web Push.') }}"
                data-subscribed-message="{{ __('Push notifications are enabled for this browser.') }}"
                data-unsubscribed-message="{{ __('Push notifications are disabled for this browser.') }}"
                data-test-message="{{ __('A test notification was queued.') }}"
                data-failed-message="{{ __('Push notification settings could not be changed.') }}"
            @endif>
            <h2 id="push-settings-heading" class="text-xl font-bold">{{ __('Push notifications') }}</h2>
            @if($pushConfigured)
                <p class="mt-2 text-neutral-700">{{ __('Push is optional and browser-specific. Hospitrainity stores the encrypted subscription until you disable it or close the account; notification content is intentionally brief.') }}</p>
                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="button" data-push-subscribe class="rounded-md bg-indigo-700 px-4 py-2 font-semibold text-white">{{ __('Enable on this browser') }}</button>
                    <button type="button" data-push-unsubscribe class="rounded-md border border-neutral-400 px-4 py-2 font-semibold">{{ __('Disable on this browser') }}</button>
                    <button type="button" data-push-test class="rounded-md border border-indigo-500 px-4 py-2 font-semibold text-indigo-800">{{ __('Send test') }}</button>
                </div>
                <p class="mt-3 text-sm" data-push-status aria-live="polite">{{ $pushSubscription ? __('This account has an active browser subscription.') : __('Push is not yet enabled on this browser.') }}</p>
            @else
                <p class="mt-2 text-neutral-700">{{ __('Push delivery is safely unavailable until this deployment receives a private VAPID key pair. No subscription prompt is shown and email remains the current recovery channel.') }}</p>
            @endif
        </section>

        <section aria-labelledby="request-history-heading">
            <h2 id="request-history-heading" class="text-xl font-bold">{{ __('Request history') }}</h2>
            <div class="mt-4 space-y-4">
                @forelse($requests as $privacyRequest)
                    <article class="rounded-lg border border-neutral-200 bg-white p-5 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-bold">{{ __(str_replace('_', ' ', ucfirst($privacyRequest->type->value))) }}</h3>
                                <p class="mt-1 font-mono text-xs text-neutral-600">{{ $privacyRequest->getKey() }}</p>
                            </div>
                            <span class="rounded-full bg-neutral-100 px-3 py-1 text-sm font-semibold">{{ __(str_replace('_', ' ', ucfirst($privacyRequest->status->value))) }}</span>
                        </div>
                        <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-3">
                            <div><dt class="font-semibold">{{ __('Submitted') }}</dt><dd>{{ $privacyRequest->created_at->toDayDateTimeString() }}</dd></div>
                            <div><dt class="font-semibold">{{ __('Due') }}</dt><dd>{{ $privacyRequest->due_at->toDayDateTimeString() }}</dd></div>
                            <div><dt class="font-semibold">{{ __('Reason') }}</dt><dd>{{ $privacyRequest->reason_code ?? __('Not decided') }}</dd></div>
                        </dl>
                        @if($privacyRequest->export?->status === 'available' && $privacyRequest->export->expires_at?->isFuture())
                            <a href="{{ URL::temporarySignedRoute('privacy-exports.download', now()->addMinutes(10), ['export' => $privacyRequest->export]) }}" class="mt-4 inline-block rounded-md bg-indigo-700 px-4 py-2 font-semibold text-white">{{ __('Download export') }}</a>
                        @endif
                        @if(in_array($privacyRequest->status, [\App\Enums\DataSubjectRequestStatus::Submitted, \App\Enums\DataSubjectRequestStatus::IdentityPending], true))
                            <form method="POST" action="{{ route('privacy-requests.cancel', $privacyRequest) }}" class="mt-4">@csrf @method('PATCH')<button class="font-semibold text-red-700 underline">{{ __('Cancel request') }}</button></form>
                        @endif
                    </article>
                @empty
                    <p class="rounded-lg border border-dashed border-neutral-300 bg-white p-6 text-neutral-700">{{ __('No privacy or account requests have been submitted.') }}</p>
                @endforelse
            </div>
            <div class="mt-6">{{ $requests->links() }}</div>
        </section>
    </main>
@endsection

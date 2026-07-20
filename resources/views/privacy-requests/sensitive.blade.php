@extends('layouts.app')

@section('title', __('Confirm sensitive request').' - Hospitrainity')

@section('content')
    <main id="main-content" class="mx-auto max-w-2xl px-4 py-8 sm:px-6">
        <a href="{{ route('privacy-requests.index') }}" class="font-semibold text-indigo-700 underline">&larr; {{ __('Privacy requests') }}</a>
        <h1 class="mt-4 text-3xl font-bold">{{ $requestType === \App\Enums\DataSubjectRequestType::Deletion ? __('Request account deletion') : __('Download my data') }}</h1>
        <div class="mt-6 rounded-lg border {{ $requestType === \App\Enums\DataSubjectRequestType::Deletion ? 'border-red-300 bg-red-50' : 'border-indigo-300 bg-indigo-50' }} p-5">
            @if($requestType === \App\Enums\DataSubjectRequestType::Deletion)
                <p>{{ __('Approval disables sign-in, revokes sessions and notifications, removes personal-scope learning data, closes active memberships, and replaces identifying account fields with a pseudonymous tombstone. Institution-attributed evidence and bounded audit/request evidence may remain under the documented retention policy.') }}</p>
            @else
                <p>{{ __('After staff approval, a background job creates an encrypted archive containing allowlisted account, membership, progress, attempt, and policy-acknowledgment fields. The signed download expires after 24 hours.') }}</p>
            @endif
        </div>
        <form method="POST" action="{{ route('privacy-requests.store') }}" class="mt-6 space-y-5">
            @csrf
            <input type="hidden" name="type" value="{{ $requestType->value }}">
            <div>
                <label for="sensitive-note" class="block font-semibold">{{ __('Optional context') }}</label>
                <textarea id="sensitive-note" name="note" rows="4" maxlength="2000" class="mt-1 block w-full rounded-md border-neutral-300">{{ old('note') }}</textarea>
            </div>
            <label class="flex items-start gap-3">
                <input type="checkbox" name="confirm_effects" value="1" required class="mt-1 shrink-0 rounded border-neutral-300">
                <span>{{ __('I understand the described scope, review process, retention limits, and that this request is not immediate.') }}</span>
            </label>
            <button class="rounded-md px-5 py-3 font-semibold text-white {{ $requestType === \App\Enums\DataSubjectRequestType::Deletion ? 'bg-red-700 hover:bg-red-800' : 'bg-indigo-700 hover:bg-indigo-800' }}">{{ __('Submit sensitive request') }}</button>
        </form>
    </main>
@endsection

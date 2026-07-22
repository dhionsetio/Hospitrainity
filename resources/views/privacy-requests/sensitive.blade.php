@extends('layouts.app')

@section('title', __('Confirm sensitive request').' - Hospitrainity')

@section('content')
    <main id="main-content" class="mx-auto max-w-2xl px-4 py-8 sm:px-6">
        <x-back-control :href="route('privacy-requests.index')" :label="__('Privacy requests')" />
        <h1 class="mt-4 text-3xl font-bold">{{ $requestType === \App\Enums\DataSubjectRequestType::Deletion ? __('Request account deletion') : __('Download my data') }}</h1>
        <div class="mt-6 rounded-lg border {{ $requestType === \App\Enums\DataSubjectRequestType::Deletion ? 'border-red-300 bg-red-50' : 'border-indigo-300 bg-indigo-50' }} p-5">
            @if($requestType === \App\Enums\DataSubjectRequestType::Deletion)
                <p>{{ __('If approved, you will be signed out, your personal learning data will be removed, and active memberships will close. Some institution learning records and request history may be kept for the periods explained in the privacy notice.') }}</p>
            @else
                <p>{{ __('If approved, Hospitrainity will prepare a protected download of your account, membership, and learning data. The download link expires after 24 hours.') }}</p>
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
                <span>{{ __('I understand what this request changes, what may be kept, and that staff must review it first.') }}</span>
            </label>
            <button class="rounded-md px-5 py-3 font-semibold text-white {{ $requestType === \App\Enums\DataSubjectRequestType::Deletion ? 'bg-red-700 hover:bg-red-800' : 'bg-indigo-700 hover:bg-indigo-800' }}">{{ __('Submit sensitive request') }}</button>
        </form>
    </main>
@endsection

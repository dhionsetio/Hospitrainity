@extends('layouts.app')

@section('title', __('Institution connections'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @include('partials.learner-nav')

    <main class="container mx-auto max-w-5xl space-y-8 px-6 py-8">
        <header>
            <h1 class="text-3xl font-bold text-neutral-900">{{ __('Institution connections') }}</h1>
            <p class="mt-2 max-w-3xl text-neutral-600">{{ __('Your personal learning remains available without an institution. A classroom code creates a request that the institution must approve.') }}</p>
        </header>

        @if(session('status'))
            <div class="rounded-md border border-green-300 bg-green-50 px-4 py-3 text-green-950" role="status">{{ session('status') }}</div>
        @endif

        <section class="max-w-2xl rounded-lg bg-white p-6 shadow" aria-labelledby="join-institution-heading">
            <h2 id="join-institution-heading" class="text-xl font-bold text-neutral-900">{{ __('Request to join an institution') }}</h2>
            <p class="mt-2 text-sm text-neutral-600">{{ __('Enter a current classroom code supplied directly by your lecturer, instructor, or institution administrator. Hospitrainity does not publish an institution directory.') }}</p>
            <form method="POST" action="{{ route('institution-enrollment.store') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <label for="code" class="block text-sm font-medium text-neutral-700">{{ __('Classroom code') }}</label>
                    <input id="code" name="code" type="text" inputmode="text" autocomplete="one-time-code" required maxlength="32" value="{{ old('code') }}"
                        @error('code') aria-invalid="true" aria-describedby="join-code-error" @enderror
                        class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-3 font-mono uppercase tracking-wider focus:border-indigo-500 focus:ring-indigo-500">
                    @error('code')<p id="join-code-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-start gap-3 text-sm text-neutral-700">
                    <input type="checkbox" name="progress_boundary_acknowledgement" value="1" required class="mt-1 shrink-0 rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500">
                    <span>{{ __('I understand that institution staff will see only progress recorded in the institution learning context after approval. My earlier personal progress will not be copied.') }}</span>
                </label>
                <button type="submit" class="rounded-md bg-indigo-600 px-5 py-3 font-semibold text-white hover:bg-indigo-700">{{ __('Send join request') }}</button>
            </form>
        </section>

        <section class="rounded-lg bg-white p-6 shadow" aria-labelledby="learning-context-heading">
            <h2 id="learning-context-heading" class="text-xl font-bold text-neutral-900">{{ __('Learning context') }}</h2>
            <p class="mt-1 text-sm text-neutral-600">{{ __('Choose where new progress is recorded. Contexts never combine institution permissions invisibly.') }}</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @if($currentContext['membership_id'] === null && !$currentContext['class_invalidated'])
                    <div class="hsp-context-current" aria-current="true">
                        <div>
                            <h3 class="font-bold text-neutral-900">{{ __('Personal self-study') }}</h3>
                            <p class="mt-1 text-sm text-neutral-600">{{ __('Visible to you, not institution staff.') }}</p>
                        </div>
                        <span class="hsp-status-pill"><i class="fa-solid fa-check" aria-hidden="true"></i> {{ __('Current context') }}</span>
                    </div>
                @else
                    <form method="POST" action="{{ route('learning-context.select') }}">
                        @csrf
                        <input type="hidden" name="scope" value="personal">
                        <button type="submit" class="hsp-context-choice">
                            <span>
                                <span class="block font-bold text-neutral-900">{{ __('Personal self-study') }}</span>
                                <span class="mt-1 block text-sm text-neutral-600">{{ __('Visible to you, not institution staff.') }}</span>
                            </span>
                            <span class="hsp-context-choice__action">{{ __('Use this context') }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                        </button>
                    </form>
                @endif
                @foreach($memberships as $membership)
                    @if($currentContext['course_enrollment_id'] === null && $currentContext['membership_id'] === $membership->id)
                        <div class="hsp-context-current flex flex-col justify-between rounded-lg border border-neutral-200 bg-white p-4 shadow-sm" aria-current="true">
                            <div>
                                <h3 class="font-bold text-neutral-900">{{ $membership->institution->displayName(app()->getLocale()) }}</h3>
                                <p class="mt-1 text-sm text-neutral-600">{{ __('Active institution membership.') }}</p>
                            </div>
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-neutral-100 pt-3">
                                <span class="hsp-status-pill inline-flex items-center gap-1 rounded bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800">
                                    <i class="fa-solid fa-check" aria-hidden="true"></i> {{ __('Current context') }}
                                </span>
                                <form method="POST" action="{{ route('institution-enrollment.destroy', $membership) }}" data-confirm-submit="{{ __('Are you sure you want to leave :institution? Your progress will be preserved.', ['institution' => $membership->institution->displayName(app()->getLocale())]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="submit" variant="danger" size="sm">
                                        {{ __('Leave') }}
                                    </x-button>
                                </form>
                            </div>
                        </div>
                    @else
                        <div class="hsp-context-choice flex flex-col justify-between rounded-lg border border-neutral-200 bg-white p-4 shadow-sm">
                            <div>
                                <h3 class="font-bold text-neutral-900">{{ $membership->institution->displayName(app()->getLocale()) }}</h3>
                                <p class="mt-1 text-sm text-neutral-600">{{ __('Active institution membership.') }}</p>
                            </div>
                            <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-neutral-100 pt-3">
                                <form method="POST" action="{{ route('learning-context.select') }}">
                                    @csrf
                                    <input type="hidden" name="scope" value="institution">
                                    <input type="hidden" name="membership_id" value="{{ $membership->id }}">
                                    <x-button type="submit" variant="secondary" size="sm">
                                        {{ __('Learn') }}
                                    </x-button>
                                </form>
                                <form method="POST" action="{{ route('institution-enrollment.destroy', $membership) }}" data-confirm-submit="{{ __('Are you sure you want to leave :institution? Your progress will be preserved.', ['institution' => $membership->institution->displayName(app()->getLocale())]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-button type="submit" variant="danger" size="sm">
                                        {{ __('Leave') }}
                                    </x-button>
                                </form>
                            </div>
                        </div>
                    @endif
                @endforeach
                @foreach($classEnrollments as $enrollment)
                    @if($currentContext['course_enrollment_id'] === $enrollment->id)
                        <div class="hsp-context-current" aria-current="true">
                            <div>
                                <h3 class="font-bold text-neutral-900">{{ $enrollment->offering->title }}</h3>
                                <p class="mt-1 text-sm text-neutral-600">{{ $enrollment->membership->institution->displayName(app()->getLocale()) }} · {{ __('New progress is saved to this Class.') }}</p>
                            </div>
                            <span class="hsp-status-pill"><i class="fa-solid fa-check" aria-hidden="true"></i> {{ __('Current context') }}</span>
                        </div>
                    @else
                        <form method="POST" action="{{ route('learning-context.select') }}">
                            @csrf
                            <input type="hidden" name="scope" value="class">
                            <input type="hidden" name="course_enrollment_id" value="{{ $enrollment->id }}">
                            <button type="submit" class="hsp-context-choice">
                                <span>
                                    <span class="block font-bold text-neutral-900">{{ $enrollment->offering->title }}</span>
                                    <span class="mt-1 block text-sm text-neutral-600">{{ $enrollment->membership->institution->displayName(app()->getLocale()) }} · {{ __('New progress is saved to this Class.') }}</span>
                                </span>
                                <span class="hsp-context-choice__action">{{ __('Use this context') }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                            </button>
                        </form>
                    @endif
                @endforeach
            </div>
        </section>

        <section class="overflow-x-auto rounded-lg bg-white shadow" aria-labelledby="request-history-heading">
            <h2 id="request-history-heading" class="p-6 text-xl font-bold text-neutral-900">{{ __('Join-request history') }}</h2>
            <table class="w-full text-left text-sm text-neutral-700">
                <thead class="border-y bg-neutral-50 text-xs uppercase text-neutral-600">
                    <tr><th class="px-6 py-3">{{ __('Institution') }}</th><th class="px-6 py-3">{{ __('classes.navigation') }}</th><th class="px-6 py-3">{{ __('Requested') }}</th><th class="px-6 py-3">{{ __('Status') }}</th></tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($requests as $joinRequest)
                        <tr>
                            <td class="px-6 py-4">{{ $joinRequest->institution->displayName(app()->getLocale()) }}</td>
                            <td class="px-6 py-4">{{ $joinRequest->offering?->title ?? __('None') }}</td>
                            <td class="px-6 py-4">{{ $joinRequest->requested_at->toDayDateTimeString() }}</td>
                            <td class="px-6 py-4">{{ __(ucfirst($joinRequest->status->value)) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-6 py-8 text-center text-neutral-500">{{ __('No join requests yet.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-4">{{ $requests->links() }}</div>
        </section>
    </main>
@endsection

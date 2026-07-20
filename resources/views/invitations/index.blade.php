@extends('layouts.app')

@section('title', __('Invitations - Hospitrainity'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    <div class="flex min-h-screen flex-col bg-neutral-100 md:flex-row">
        @include($routePrefix === 'supervisor' ? 'supervisor.sidebar' : 'superadmin.sidebar')

        <main class="min-w-0 flex-1 p-6 md:p-10">
            <header class="mb-8">
                <h1 class="text-3xl font-bold text-neutral-900">{{ __('Invitations') }}</h1>
                <p class="mt-2 max-w-3xl text-neutral-600">{{ __('Invite a learner without exposing the institution directory publicly.') }}</p>
            </header>

            @if(session('status'))
                <div class="mb-5 rounded-md border border-green-300 bg-green-50 px-4 py-3 text-green-900" role="status">{{ session('status') }}</div>
            @endif

            @if($institutions->count() > 1)
                <form method="POST" action="{{ route($routePrefix.'.invitations.institution') }}" class="mb-6 max-w-xl rounded-lg bg-white p-5 shadow">
                    @csrf
                    <label for="institution_id" class="block text-sm font-medium text-neutral-700">{{ __('Active institution') }}</label>
                    <div class="mt-2 flex gap-3">
                        <select id="institution_id" name="institution_id" class="min-w-0 flex-1 rounded-md border border-neutral-300 bg-white px-3 py-2">
                            @foreach($institutions as $option)
                                <option value="{{ $option->id }}" @selected($option->is($institution))>{{ $option->displayName(app()->getLocale()) }}</option>
                            @endforeach
                        </select>
                        <button class="rounded-md border border-indigo-600 px-4 py-2 font-semibold text-indigo-700 hover:bg-indigo-50" type="submit">{{ __('Switch') }}</button>
                    </div>
                </form>
            @endif

            <section class="mb-8 max-w-xl rounded-lg bg-white p-6 shadow" aria-labelledby="invite-heading">
                <h2 id="invite-heading" class="text-xl font-bold text-neutral-900">{{ __('Invite a learner') }}</h2>
                <p class="mt-1 text-sm text-neutral-600">{{ __('The invitation is single-use and expires automatically.') }}</p>
                <form method="POST" action="{{ route($routePrefix.'.invitations.store') }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label for="email" class="block text-sm font-medium text-neutral-700">{{ __('Email address') }}</label>
                        <input id="email" name="email" type="email" autocomplete="email" required maxlength="255" value="{{ old('email') }}"
                            @error('email') aria-invalid="true" aria-describedby="invitation-email-error" @enderror
                            class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                        @error('email')<p id="invitation-email-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="rounded-md bg-indigo-600 px-5 py-3 font-semibold text-white hover:bg-indigo-700">{{ __('Send invitation') }}</button>
                </form>
            </section>

            <section class="rounded-lg bg-white shadow" aria-labelledby="invitation-history-heading">
                <h2 id="invitation-history-heading" class="p-6 text-xl font-bold text-neutral-900">{{ __('Invitation history') }}</h2>
                <div class="space-y-3 px-4 pb-4 md:hidden">
                    @forelse($invitations as $invitation)
                        @php($mobileStatus = $invitation->accepted_at ? __('Accepted') : ($invitation->revoked_at ? __('Revoked') : ($invitation->expires_at->isPast() ? __('Expired') : __('Pending'))))
                        <article class="rounded-lg border border-neutral-300 p-4" aria-label="{{ __('Invitation for :email', ['email' => $invitation->masked_target]) }}">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <p class="font-semibold text-neutral-950">{{ $invitation->masked_target }}</p>
                                <span class="rounded-full border border-neutral-400 px-2 py-1 text-sm font-semibold">{{ $mobileStatus }}</span>
                            </div>
                            <dl class="mt-3"><dt class="text-sm font-medium text-neutral-600">{{ __('Expires') }}</dt><dd class="text-neutral-900">{{ $invitation->expires_at->toDayDateTimeString() }}</dd></dl>
                            @if(!$invitation->accepted_at && !$invitation->revoked_at && $invitation->expires_at->isFuture())
                                <form method="POST" action="{{ route($routePrefix.'.invitations.destroy', $invitation) }}" class="mt-4">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="rounded-lg border border-red-700 px-4 py-2 font-semibold text-red-800">{{ __('Revoke') }}</button>
                                </form>
                            @endif
                        </article>
                    @empty
                        <p class="rounded-lg border border-dashed border-neutral-400 p-6 text-center text-neutral-600">{{ __('No invitations yet.') }}</p>
                    @endforelse
                </div>
                <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-left text-sm text-neutral-700">
                    <thead class="border-y bg-neutral-50 text-xs uppercase text-neutral-500">
                        <tr><th class="px-6 py-3">{{ __('Email') }}</th><th class="px-6 py-3">{{ __('Status') }}</th><th class="px-6 py-3">{{ __('Expires') }}</th><th class="px-6 py-3">{{ __('Actions') }}</th></tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($invitations as $invitation)
                            @php($status = $invitation->accepted_at ? __('Accepted') : ($invitation->revoked_at ? __('Revoked') : ($invitation->expires_at->isPast() ? __('Expired') : __('Pending'))))
                            <tr>
                                <td class="px-6 py-4">{{ $invitation->masked_target }}</td>
                                <td class="px-6 py-4">{{ $status }}</td>
                                <td class="px-6 py-4">{{ $invitation->expires_at->toDayDateTimeString() }}</td>
                                <td class="px-6 py-4">
                                    @if(!$invitation->accepted_at && !$invitation->revoked_at && $invitation->expires_at->isFuture())
                                        <form method="POST" action="{{ route($routePrefix.'.invitations.destroy', $invitation) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="font-semibold text-red-700 hover:text-red-900">{{ __('Revoke') }}</button>
                                        </form>
                                    @else
                                        <span class="text-neutral-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-8 text-center text-neutral-500">{{ __('No invitations yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                <div class="p-4">{{ $invitations->links() }}</div>
            </section>
        </main>
    </div>
@endsection

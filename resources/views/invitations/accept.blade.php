@extends('layouts.guest')

@section('title', __('Accept invitation - Hospitrainity'))
@section('bodyClass', 'bg-neutral-50 flex items-center justify-center min-h-screen')

@section('content')
    <main class="w-full max-w-md space-y-6 rounded-lg bg-white p-8 shadow-md">
        <div class="text-center">
            <a href="/" class="text-3xl font-bold text-indigo-600">Hospitrainity</a>
            <h1 class="mt-4 text-2xl font-bold text-neutral-900">{{ __('Accept your invitation') }}</h1>
        </div>

        <div class="rounded-md border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-950">
            <p><strong>{{ __('Institution') }}:</strong> {{ $invitation->institution->displayName(app()->getLocale()) }}</p>
            <p class="mt-1"><strong>{{ __('Invited email') }}:</strong> {{ $maskedEmail }}</p>
            <p class="mt-1"><strong>{{ __('Expires') }}:</strong> {{ $invitation->expires_at->toDayDateTimeString() }}</p>
        </div>

        @if(auth()->check())
            @if($authenticatedAccountCanAccept)
                <form method="POST" action="{{ route('invitations.redeem', ['token' => $token]) }}">
                    @csrf
                    <button type="submit" class="flex w-full justify-center rounded-md bg-indigo-600 px-4 py-3 font-semibold text-white hover:bg-indigo-700">
                        {{ __('Accept invitation') }}
                    </button>
                </form>
            @else
                <div class="rounded-md border border-amber-300 bg-amber-50 p-4 text-amber-950" role="alert">
                    {{ __('This invitation can be accepted only while signed in to the invited learner account.') }}
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="font-semibold text-indigo-700 hover:text-indigo-900">{{ __('Sign out') }}</button>
                </form>
            @endif
        @else
            <form class="space-y-4" method="POST" action="{{ route('invitations.redeem', ['token' => $token]) }}">
                @csrf
                <div>
                    <label for="name" class="block text-sm font-medium text-neutral-700">{{ __('Name') }}</label>
                    <input id="name" name="name" type="text" autocomplete="name" required maxlength="255" value="{{ old('name') }}"
                        @error('name') aria-invalid="true" aria-describedby="invitation-name-error" @enderror
                        class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                    @error('name')<p id="invitation-name-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium text-neutral-700">{{ __('Password') }}</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" minlength="15" maxlength="128" required
                        @error('password') aria-invalid="true" aria-describedby="invitation-password-error" @enderror
                        class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                    @error('password')<p id="invitation-password-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-neutral-700">{{ __('Confirm Password') }}</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="15" maxlength="128" required
                        class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="flex items-start gap-2">
                    <input id="terms" name="terms" type="checkbox" value="1" required
                        @error('terms') aria-invalid="true" aria-describedby="invitation-terms-error" @enderror
                        class="mt-1 h-4 w-4 shrink-0 rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500">
                    <label for="terms" class="text-sm text-neutral-700">{{ __('I confirm that the information above is correct.') }}</label>
                </div>
                @error('terms')<p id="invitation-terms-error" class="text-sm text-red-600">{{ $message }}</p>@enderror
                <button type="submit" class="flex w-full justify-center rounded-md bg-indigo-600 px-4 py-3 font-semibold text-white hover:bg-indigo-700">
                    {{ __('Create account and accept') }}
                </button>
            </form>
            <p class="text-center text-sm text-neutral-600">
                {{ __('Already have an account?') }}
                <a href="{{ route('login') }}" class="font-semibold text-indigo-700 hover:text-indigo-900">{{ __('Sign in') }}</a>
            </p>
        @endif
    </main>
@endsection

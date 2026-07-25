@extends('layouts.guest')

@section('title', __('Two-factor authentication'))
@section('bodyClass', 'bg-neutral-50 flex items-center justify-center min-h-screen')

@section('content')
<main class="w-full max-w-md space-y-6 rounded-lg bg-white p-8 shadow-md" id="main-content">
    <div>
        <a href="{{ url('/') }}" class="text-2xl font-bold text-indigo-700">Hospitrainity</a>
        <h1 class="mt-4 text-2xl font-bold text-neutral-950">{{ __('Two-factor authentication') }}</h1>
        <p class="mt-2 text-neutral-700">{{ __('Enter the current six-digit code from your authenticator app, or one unused recovery code.') }}</p>
    </div>
    @if($errors->any())
        <div role="alert" class="rounded-md border border-red-300 bg-red-50 p-3 text-red-900">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('mfa.challenge.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="code" class="block font-medium text-neutral-900">{{ __('Authenticator code') }}</label>
            <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" class="mt-1 w-full rounded-md border border-neutral-400 px-3 py-3">
        </div>
        <p class="text-center text-sm text-neutral-600">{{ __('or') }}</p>
        <div>
            <label for="recovery_code" class="block font-medium text-neutral-900">{{ __('Recovery code') }}</label>
            <input id="recovery_code" name="recovery_code" autocomplete="one-time-code" maxlength="40" class="mt-1 w-full rounded-md border border-neutral-400 px-3 py-3">
        </div>
        <button class="w-full rounded-md bg-indigo-700 px-4 py-3 font-semibold text-white hover:bg-indigo-800">{{ __('Verify and continue') }}</button>
    </form>
    <a href="{{ route('login') }}" class="inline-block text-sm font-medium text-indigo-700 underline">{{ __('Cancel and return to sign in') }}</a>
</main>
@endsection

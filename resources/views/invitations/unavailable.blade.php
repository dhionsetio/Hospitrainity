@extends('layouts.guest')

@section('title', __('Invitation unavailable - Hospitrainity'))
@section('bodyClass', 'bg-neutral-50 flex items-center justify-center min-h-screen')

@section('content')
    <main class="w-full max-w-md space-y-6 rounded-lg bg-white p-8 text-center shadow-md">
        <a href="/" class="text-3xl font-bold text-indigo-600">Hospitrainity</a>
        <h1 class="text-2xl font-bold text-neutral-900">{{ __('Invitation unavailable') }}</h1>
        <p class="text-neutral-600">{{ __('This invitation cannot be used. It may have expired, been revoked, or already been accepted.') }}</p>
        <p class="text-sm text-neutral-600">{{ __('Ask an authorized staff member to send a new invitation.') }}</p>
        <a href="{{ route('login') }}" class="inline-flex rounded-md bg-indigo-600 px-5 py-3 font-semibold text-white hover:bg-indigo-700">{{ __('Sign in') }}</a>
    </main>
@endsection

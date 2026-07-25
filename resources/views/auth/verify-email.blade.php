@extends('layouts.guest')

@section('title', __('Verify Email - Hospitrainity'))
@section('bodyClass', 'bg-neutral-50 flex items-center justify-center min-h-screen')

@section('content')
    <main class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md">
        <div class="text-center">
            <a href="/" class="text-3xl font-bold text-indigo-600">Hospitrainity</a>
            <h1 class="mt-4 text-2xl font-bold text-neutral-900">{{ __('Verify your email address') }}</h1>
        </div>

        <p class="text-sm text-neutral-600">
            {{ __('Thanks for signing up! Before getting started, please verify your email address using the link we sent you. If you did not receive the email, you can request another.') }}
        </p>

        @if (session('status') == 'verification-link-sent')
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded" role="alert">
                {{ __('A new verification link has been sent to the email address you provided during registration.') }}
            </div>
        @endif

        <div class="flex items-center justify-between gap-4">
            <form action="{{ route('verification.send') }}" method="POST">
                @csrf
                <button type="submit"
                    class="py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    {{ __('Resend verification email') }}
                </button>
            </form>

            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="text-sm font-medium text-neutral-600 hover:text-neutral-900">
                    {{ __('Logout') }}
                </button>
            </form>
        </div>
    </main>
@endsection

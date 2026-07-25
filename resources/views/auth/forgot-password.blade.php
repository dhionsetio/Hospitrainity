@extends('layouts.guest')

@section('title', __('Forgot Password - Hospitrainity'))
@section('bodyClass', 'bg-neutral-50 flex items-center justify-center min-h-screen')

@section('content')
    <main class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md">
        <div class="text-center">
            <a href="/" class="text-3xl font-bold text-indigo-600">Hospitrainity</a>
            <h1 class="mt-4 text-2xl font-bold text-neutral-900">{{ __('Reset your password') }}</h1>
            <p class="mt-2 text-sm text-neutral-600">{{ __('Enter your email address and we will send you a link to reset your password.') }}</p>
        </div>

        @if (session('status'))
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded" role="alert">
                {{ session('status') }}
            </div>
        @endif

        @error('email')
            <div id="email-error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded" role="alert">
                {{ $message }}
            </div>
        @enderror

        <form class="space-y-6" action="{{ route('password.email') }}" method="POST">
            @csrf
            <div>
                <label for="email" class="sr-only">{{ __('Email address') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                    class="appearance-none rounded-md block w-full px-3 py-3 border border-neutral-300 placeholder-neutral-500 text-neutral-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    placeholder="{{ __('Email address') }}" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            </div>
            <button type="submit"
                class="relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('Email password reset link') }}
            </button>
            <p class="text-center text-sm text-neutral-600">
                <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:text-indigo-500">{{ __('Back to sign in') }}</a>
            </p>
        </form>
    </main>
@endsection

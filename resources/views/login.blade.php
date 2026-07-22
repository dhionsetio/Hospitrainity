@extends('layouts.guest')

@section('title', __('Login - Hospitrainity'))
@section('bodyClass', 'bg-neutral-50 flex items-center justify-center min-h-screen')

@section('content')
    <main class="w-full max-w-md p-8 space-y-8 bg-white rounded-lg shadow-md">
        <div class="text-center">
            <a href="/" class="text-3xl font-bold text-indigo-600">Hospitrainity</a>
            <h1 class="mt-4 text-2xl font-bold text-neutral-900">
                {{ __('Sign in to your account') }}
            </h1>
        </div>
        <form class="mt-8 space-y-6" action="{{ route('login') }}" method="POST">
            @csrf
            @error('email')
            <div id="email-error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline">{{ $message }}</span>
            </div>
            @enderror
            <div class="rounded-md shadow-sm -space-y-px flex flex-col gap-4">
                <div>
                    <label for="email-address" class="sr-only">{{ __('Email address') }}</label>
                    <input id="email-address" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required class="appearance-none rounded-md relative block w-full px-3 py-3 border border-neutral-300 placeholder-neutral-500 text-neutral-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="{{ __('Email address') }}" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                </div>
                <div>
                    <label for="password" class="sr-only">{{ __('Password') }}</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required class="appearance-none rounded-md relative block w-full px-3 py-3 border border-neutral-300 placeholder-neutral-500 text-neutral-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="{{ __('Password') }}">
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center">
                    <input id="remember-me" name="remember" type="checkbox" value="1" @checked(old('remember')) class="h-4 w-4 shrink-0 text-indigo-600 focus:ring-indigo-500 border-neutral-300 rounded">
                    <label for="remember-me" class="ml-2 block text-sm text-neutral-900">
                        {{ __('Remember me') }}
                    </label>
                </div>

                <div class="text-sm">
                    <a href="{{ route('password.request') }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                        {{ __('Forgot your password?') }}
                    </a>
                </div>
            </div>

            <div class="flex flex-col gap-4">
                <button type="submit" class="relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    {{ __('Sign in') }}
                </button>
                <button type="button" data-passkey-login hidden class="relative w-full justify-center rounded-md border border-indigo-700 px-4 py-3 text-sm font-semibold text-indigo-800 hover:bg-indigo-50">
                    {{ __('Sign in with a passkey') }}
                </button>
                <p data-passkey-status role="status" aria-live="polite" class="min-h-5 text-center text-sm text-neutral-700"></p>
                <p class="text-center text-sm text-neutral-600">
                    {{ __('Need an account?') }}
                    <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                        {{ __('Create account') }}
                    </a>
                </p>
            </div>
        </form>
    </main>
@endsection

@extends('layouts.guest')

@section('title', __('Reset Password - Hospitrainity'))
@section('bodyClass', 'bg-neutral-50 flex items-center justify-center min-h-screen')

@section('content')
    <main class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md">
        <div class="text-center">
            <a href="/" class="text-3xl font-bold text-indigo-600">Hospitrainity</a>
            <h1 class="mt-4 text-2xl font-bold text-neutral-900">{{ __('Choose a new password') }}</h1>
        </div>

        @if ($errors->any())
            <div id="password-reset-errors" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded" role="alert">
                <ul class="list-disc list-inside text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form class="space-y-6" action="{{ route('password.update') }}" method="POST">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div>
                <label for="email" class="block text-sm font-medium text-neutral-700 mb-1">{{ __('Email address') }}</label>
                <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email"
                    class="appearance-none rounded-md block w-full px-3 py-3 border border-neutral-300 text-neutral-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" @error('email') aria-invalid="true" aria-describedby="password-reset-errors" @enderror>
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-neutral-700 mb-1">{{ __('New password') }}</label>
                <input id="password" name="password" type="password" required autocomplete="new-password"
                    class="appearance-none rounded-md block w-full px-3 py-3 border border-neutral-300 text-neutral-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                    minlength="15" maxlength="128" placeholder="{{ __('15–128 characters') }}" @error('password') aria-invalid="true" aria-describedby="password-reset-errors" @enderror>
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-neutral-700 mb-1">{{ __('Confirm new password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                    class="appearance-none rounded-md block w-full px-3 py-3 border border-neutral-300 text-neutral-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" @error('password_confirmation') aria-invalid="true" aria-describedby="password-reset-errors" @enderror>
            </div>
            <button type="submit"
                class="relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                {{ __('Reset password') }}
            </button>
        </form>
    </main>
@endsection

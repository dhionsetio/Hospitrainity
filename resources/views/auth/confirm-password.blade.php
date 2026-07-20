@extends('layouts.guest')

@section('title', __('admin.confirm_password_title'))
@section('bodyClass', 'bg-neutral-50 flex items-center justify-center min-h-screen')

@section('content')
    <main class="w-full max-w-md p-8 space-y-6 bg-white rounded-lg shadow-md">
        <div class="text-center">
            <a href="{{ route('superadmin.dashboard') }}" class="text-3xl font-bold text-indigo-600">Hospitrainity</a>
            <h1 class="mt-4 text-2xl font-bold text-neutral-900">{{ __('admin.confirm_password_heading') }}</h1>
        </div>

        <p class="text-sm text-neutral-600">{{ __("admin.confirm_password_description.{$confirmationContext}") }}</p>

        <form action="{{ route('password.confirm.store') }}" method="POST" class="space-y-5">
            @csrf
            <div>
                <label for="password" class="block text-sm font-medium text-neutral-700">{{ __('Password') }}</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required autofocus
                    class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-3 text-neutral-900 focus:border-indigo-500 focus:outline-none focus:ring-indigo-500"
                    @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                @error('password')
                    <p id="password-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex items-center justify-between gap-4">
                <a href="{{ route('superadmin.dashboard') }}" class="text-sm font-medium text-neutral-600 hover:text-neutral-900">{{ __('admin.cancel') }}</a>
                <button type="submit" class="rounded-md bg-indigo-600 px-5 py-3 text-sm font-semibold text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    {{ __('admin.confirm_password_action') }}
                </button>
            </div>
        </form>
    </main>
@endsection

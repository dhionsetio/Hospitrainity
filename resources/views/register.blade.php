@extends('layouts.guest')

@section('title', __('Register - Hospitrainity'))
@section('bodyClass', 'bg-neutral-50 flex items-center justify-center min-h-screen')

@section('content')
    <div class="w-full max-w-md p-8 space-y-8 bg-white rounded-lg shadow-md">
        <div class="text-center">
            <a href="/" class="text-3xl font-bold text-indigo-600">Hospitrainity</a>
            <h2 class="mt-4 text-2xl font-bold text-neutral-900">
                {{ __('Create your account') }}
            </h2>
        </div>
        @if (session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
            <strong class="font-bold">{{ __('Success!') }}</strong>
            <span class="block sm:inline">{{ session('success') }}</span>
        </div>
        @endif
        <form class="mt-8 space-y-6" action="{{ route('register') }}" method="POST">
            @csrf
            <div class="rounded-md shadow-sm -space-y-px flex flex-col gap-4">
                <div>
                    <label for="name" class="sr-only">{{ __('Name') }}</label>
                    <input id="name" name="name" type="text" autocomplete="name" value="{{ old('name') }}" required class="appearance-none rounded-md relative block w-full px-3 py-3 border border-neutral-300 placeholder-neutral-500 text-neutral-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="{{ __('Name') }}">
                    @error('name')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
                <div class="relative">
                    <label for="instansi" class="sr-only">{{ __('Institution') }}</label>
                    <select id="instansi" name="instansi" required class="appearance-none rounded-md relative block w-full px-3 py-3 border border-neutral-300 bg-white placeholder-neutral-500 text-neutral-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm">
                        <option value="" disabled @selected(!old('instansi'))>{{ __('Select Institution') }}</option>
                        @forelse ($institutions as $institution)
                            <option value="{{ $institution }}" @selected(old('instansi') === $institution)>{{ $institution }}</option>
                        @empty
                            <option value="" disabled>{{ __('No institutions registered yet') }}</option>
                        @endforelse
                    </select>
                    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-2 text-neutral-700">
                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                            <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" />
                        </svg>
                    </div>
                    @error('instansi')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <label for="email-address" class="sr-only">{{ __('Email address') }}</label>
                    <input id="email-address" name="email" type="email" autocomplete="email" value="{{ old('email') }}" required class="appearance-none rounded-md relative block w-full px-3 py-3 border border-neutral-300 placeholder-neutral-500 text-neutral-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="{{ __('Email address') }}">
                    @error('email')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <label for="password" class="sr-only">{{ __('Password') }}</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required class="appearance-none rounded-md relative block w-full px-3 py-3 border border-neutral-300 placeholder-neutral-500 text-neutral-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="{{ __('Password') }}">
                    @error('password')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
                <div>
                    <label for="confirm-password" class="sr-only">{{ __('Confirm Password') }}</label>
                    <input id="confirm-password" name="password_confirmation" type="password" autocomplete="new-password" required class="appearance-none rounded-md relative block w-full px-3 py-3 border border-neutral-300 placeholder-neutral-500 text-neutral-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="{{ __('Confirm Password') }}">
                    @error('password')
                    <span class="text-red-500 text-sm">{{ $message }}</span>
                    @enderror
                </div>
            </div>

            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <input id="terms" name="terms" type="checkbox" value="1" required @checked(old('terms')) class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-neutral-300 rounded" aria-describedby="terms-error">
                    <label for="terms" class="ml-2 block text-sm text-neutral-900">
                        {{ __('I confirm that the information above is correct.') }}
                    </label>
                </div>
            </div>
            @error('terms')
                <p id="terms-error" class="text-sm text-red-600">{{ $message }}</p>
            @enderror

            <div class="flex flex-col gap-4">
                <button type="submit" class="relative w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    {{ __('Sign up') }}
                </button>
                <p class="text-center text-sm text-neutral-600">
                    {{ __('Already have an account?') }}
                    <a href="{{ route('login') }}" class="font-medium text-indigo-600 hover:text-indigo-500">
                        {{ __('Login') }}
                    </a>
                </p>
            </div>
        </form>
    </div>
@endsection

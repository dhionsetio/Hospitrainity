@extends('layouts.guest')

@section('title', __('Create a personal learning account'))
@section('bodyClass', 'bg-neutral-50 flex items-center justify-center min-h-screen')

@section('content')
    <main class="w-full max-w-lg space-y-6 rounded-lg bg-white p-6 shadow-md sm:p-8">
        <div class="text-center">
            <a href="/" class="text-3xl font-bold text-indigo-600">Hospitrainity</a>
            <h1 class="mt-4 text-2xl font-bold text-neutral-900">{{ __('Create a personal learning account') }}</h1>
            <p class="mt-2 text-neutral-600">{{ __('You can learn independently without joining an institution.') }}</p>
        </div>

        <section class="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950" aria-labelledby="institution-boundary-heading">
            <h2 id="institution-boundary-heading" class="font-bold">{{ __('Are you learning with an institution?') }}</h2>
            <p class="mt-1">{{ __('Create and verify this personal account first. Then enter a current classroom code supplied by your lecturer or institution.') }}</p>
            <p class="mt-2">{{ __('Your institution will receive a new, separate progress view only after it approves your request. Earlier personal progress is not copied or shown to institution staff.') }}</p>
        </section>

        <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
            @csrf
            <div>
                <label for="name" class="block text-sm font-medium text-neutral-700">{{ __('Name') }}</label>
                <input id="name" name="name" type="text" autocomplete="name" required maxlength="255" value="{{ old('name') }}"
                    @error('name') aria-invalid="true" aria-describedby="registration-name-error" @enderror
                    class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                @error('name')<p id="registration-name-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-neutral-700">{{ __('Email address') }}</label>
                <input id="email" name="email" type="email" autocomplete="email" required maxlength="255" value="{{ old('email') }}"
                    @error('email') aria-invalid="true" aria-describedby="registration-email-error" @enderror
                    class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                @error('email')<p id="registration-email-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-neutral-700">{{ __('Password') }}</label>
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="15" maxlength="128" required
                    @error('password') aria-invalid="true" aria-describedby="registration-password-error" @enderror
                    class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                @error('password')<p id="registration-password-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-neutral-700">{{ __('Confirm password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="15" maxlength="128" required
                    class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-3 focus:border-indigo-500 focus:ring-indigo-500">
            </div>
            <div>
                <label class="flex items-start gap-3 text-sm text-neutral-700">
                    <input type="checkbox" name="scope_acknowledgement" value="1" required @checked(old('scope_acknowledgement')) class="mt-1 shrink-0 rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500"
                        @error('scope_acknowledgement') aria-invalid="true" aria-describedby="registration-scope-error" @enderror>
                    <span>{{ __('I understand that personal and institution-tracked progress are separate, and joining an institution requires its approval.') }}</span>
                </label>
                @error('scope_acknowledgement')<p id="registration-scope-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="flex items-start gap-3 text-sm text-neutral-700">
                    <input type="checkbox" name="policy_acknowledgement" value="1" required @checked(old('policy_acknowledgement')) class="mt-1 shrink-0 rounded border-neutral-300 text-indigo-600 focus:ring-indigo-500"
                        @error('policy_acknowledgement') aria-invalid="true" aria-describedby="registration-policy-error" @enderror>
                    <span>
                        {{ __('I have read the prototype') }}
                        <a href="{{ route('policies.show', ['type' => 'privacy']) }}" class="font-semibold text-indigo-700 underline">{{ __('privacy notice') }}</a>
                        {{ __('and') }}
                        <a href="{{ route('policies.show', ['type' => 'terms']) }}" class="font-semibold text-indigo-700 underline">{{ __('terms') }}</a>
                        ({{ $privacyPolicy['version'] }}). {{ __('This acknowledgment is separate from optional consent.') }}
                    </span>
                </label>
                @error('policy_acknowledgement')<p id="registration-policy-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="w-full rounded-md bg-indigo-600 px-5 py-3 font-semibold text-white hover:bg-indigo-700">{{ __('Create account') }}</button>
        </form>

        <p class="text-center text-sm text-neutral-600">{{ __('Already have an account?') }} <a href="{{ route('login') }}" class="font-semibold text-indigo-700 hover:text-indigo-900">{{ __('Sign in') }}</a></p>
    </main>
@endsection

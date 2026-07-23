@extends('layouts.guest')

@section('title', __('Create a personal learning account'))
@section('bodyClass', 'bg-neutral-50 flex items-center justify-center min-h-screen')

@section('content')
    <main class="w-full max-w-lg space-y-6 rounded-lg bg-white p-6 shadow-md sm:p-8">
        <div class="text-center">
            <a href="/" aria-label="{{ __('Hospitrainity home') }}" class="inline-block">
                <x-brand-logo class="h-10 w-auto mx-auto" />
            </a>
            <h1 class="mt-4 text-2xl font-bold text-neutral-900">{{ __('Create a personal learning account') }}</h1>
            <p class="mt-2 text-neutral-600">{{ __('You can learn independently without joining an institution.') }}</p>
        </div>

        <section class="rounded-md border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-950" aria-labelledby="institution-boundary-heading">
            <h2 id="institution-boundary-heading" class="font-bold">{{ __('Joining an institution?') }}</h2>
            <p class="mt-1">{{ __('After creating your account, you can join your institution using a classroom code from your lecturer. Earlier personal progress is not copied or shown to institution staff.') }}</p>
        </section>

        <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
            @csrf
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-1">
                    <label for="first_name" class="block text-sm font-medium text-neutral-700">{{ __('First Name') }}</label>
                    <input id="first_name" name="first_name" type="text" autocomplete="given-name" required maxlength="100" value="{{ old('first_name') }}"
                        @error('first_name') aria-invalid="true" aria-describedby="registration-first-name-error" @enderror
                        class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                    @error('first_name')<p id="registration-first-name-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-1">
                    <label for="middle_name" class="block text-sm font-medium text-neutral-700">{{ __('Middle Name') }}</label>
                    <input id="middle_name" name="middle_name" type="text" autocomplete="additional-name" maxlength="100" value="{{ old('middle_name') }}"
                        class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="sm:col-span-1">
                    <label for="last_name" class="block text-sm font-medium text-neutral-700">{{ __('Last Name') }}</label>
                    <input id="last_name" name="last_name" type="text" autocomplete="family-name" maxlength="100" value="{{ old('last_name') }}"
                        class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="prefix" class="block text-sm font-medium text-neutral-700">{{ __('Prefix') }}</label>
                    <select id="prefix" name="prefix" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="None" @selected(old('prefix') === 'None')>{{ __('None') }}</option>
                        <option value="Mr." @selected(old('prefix') === 'Mr.')>Mr.</option>
                        <option value="Mrs." @selected(old('prefix') === 'Mrs.')>Mrs.</option>
                        <option value="Ms." @selected(old('prefix') === 'Ms.')>Ms.</option>
                    </select>
                </div>
                <div>
                    <label for="gender" class="block text-sm font-medium text-neutral-700">{{ __('Gender') }}</label>
                    <select id="gender" name="gender" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="rather_not_say" @selected(old('gender') === 'rather_not_say')>{{ __('Rather not say') }}</option>
                        <option value="male" @selected(old('gender') === 'male')>{{ __('Male') }}</option>
                        <option value="female" @selected(old('gender') === 'female')>{{ __('Female') }}</option>
                    </select>
                </div>
                <div>
                    <label for="occupation" class="block text-sm font-medium text-neutral-700">{{ __('Occupation') }}</label>
                    <select id="occupation" name="occupation" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="student" @selected(old('occupation', 'student') === 'student')>{{ __('Student') }}</option>
                        <option value="lecturer" @selected(old('occupation') === 'lecturer')>{{ __('Lecturer') }}</option>
                        <option value="teacher" @selected(old('occupation') === 'teacher')>{{ __('Teacher') }}</option>
                        <option value="staff" @selected(old('occupation') === 'staff')>{{ __('Staff') }}</option>
                        <option value="manager" @selected(old('occupation') === 'manager')>{{ __('Manager') }}</option>
                        <option value="other" @selected(old('occupation') === 'other')>{{ __('Other') }}</option>
                    </select>
                </div>
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
                <input id="password" name="password" type="password" autocomplete="new-password" minlength="8" maxlength="128" required
                    @error('password') aria-invalid="true" aria-describedby="registration-password-error" @enderror
                    class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-3 focus:border-indigo-500 focus:ring-indigo-500">
                @error('password')<p id="registration-password-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-neutral-700">{{ __('Confirm password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" minlength="8" maxlength="128" required
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
                        {{ __('I agree to the') }}
                        <a href="{{ route('policies.show', ['type' => 'privacy']) }}" class="font-semibold text-indigo-700 underline">{{ __('privacy notice') }}</a>
                        {{ __('and') }}
                        <a href="{{ route('policies.show', ['type' => 'terms']) }}" class="font-semibold text-indigo-700 underline">{{ __('terms') }}</a>.
                    </span>
                </label>
                @error('policy_acknowledgement')<p id="registration-policy-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="w-full rounded-md bg-indigo-600 px-5 py-3 font-semibold text-white hover:bg-indigo-700">{{ __('Create account') }}</button>
        </form>

        <p class="text-center text-sm text-neutral-600">{{ __('Already have an account?') }} <a href="{{ route('login') }}" class="font-semibold text-indigo-700 hover:text-indigo-900">{{ __('Sign in') }}</a></p>
    </main>
@endsection

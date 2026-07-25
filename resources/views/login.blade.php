@extends('layouts.guest')

@section('title', __('Login - Hospitrainity'))
@section('bodyClass', 'bg-neutral-50 flex items-center justify-center min-h-screen')

@section('content')
    <main class="w-full max-w-md p-8 space-y-8 bg-white rounded-lg shadow-md">
        <div class="text-center">
            <a href="/" aria-label="{{ __('Hospitrainity home') }}" class="inline-block">
                <x-brand-logo class="h-10 w-auto mx-auto" />
            </a>
            <h1 class="mt-4 text-2xl font-bold text-neutral-900">
                {{ __('Sign in to your account') }}
            </h1>
        </div>
        @include('auth.partials.login-form')
    </main>
@endsection

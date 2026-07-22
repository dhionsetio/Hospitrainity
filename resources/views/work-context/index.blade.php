@extends('layouts.app')

@section('title', __('Switch role or work context'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    <main class="container mx-auto max-w-4xl space-y-6 px-6 py-10">
        <header>
            <x-back-control :href="app(\App\Services\RoleLandingResolver::class)->url(auth()->user())" :label="__('Return to current dashboard')" />
            <h1 class="mt-4 text-3xl font-bold text-neutral-900">{{ __('Switch role or work context') }}</h1>
            <p class="mt-2 max-w-3xl text-neutral-600">{{ __('Choose the role and institution you want to use now. You can switch again later.') }}</p>
        </header>

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach($contexts as $context)
                @php($role = $context['role'])
                @php($institution = $context['institution'])
                @php($preview = $context['preview'])
                @php($isCurrent = $currentRole === $role
                    && $currentPreview === $preview
                    && ($institution?->getKey() === $currentInstitutionId || $institution === null))
                @php($label = match($role) {
                    \App\Enums\WorkContextRole::Learner => __('Learner'),
                    \App\Enums\WorkContextRole::Instructor => __('Instructor'),
                    \App\Enums\WorkContextRole::InstitutionAdmin => __('Institution Admin'),
                    \App\Enums\WorkContextRole::ContentAuthor => __('Content Author'),
                    \App\Enums\WorkContextRole::SystemAdmin => __('System Admin'),
                })
                @php($description = match($role) {
                    \App\Enums\WorkContextRole::Learner => __('Study independently or with one of your institutions.'),
                    \App\Enums\WorkContextRole::Instructor => __('Manage your Classes, learners, invitations, and progress.'),
                    \App\Enums\WorkContextRole::InstitutionAdmin => __('Manage people and Classes for this institution.'),
                    \App\Enums\WorkContextRole::ContentAuthor => __('Create and review shared learning content.'),
                    \App\Enums\WorkContextRole::SystemAdmin => __('Manage Hospitrainity settings and accounts.'),
                })
                @if($isCurrent)
                    <div class="hsp-context-current" aria-current="true">
                        <div>
                            <h2 class="text-xl font-bold text-neutral-900">{{ $label }}</h2>
                            @if($preview)<p class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-bold uppercase text-amber-950">{{ __('Preview as role') }}</p>@endif
                            @if($institution !== null)<p class="mt-1 text-neutral-600">{{ $institution->displayName(app()->getLocale()) }}</p>@endif
                            <p class="mt-2 text-sm text-neutral-600">{{ $description }}</p>
                        </div>
                        <span class="hsp-status-pill"><i class="fa-solid fa-check" aria-hidden="true"></i> {{ __('Current context') }}</span>
                    </div>
                @else
                    <form method="POST" action="{{ route('work-context.store') }}">
                        @csrf
                        <input type="hidden" name="role" value="{{ $role->value }}">
                        @if($institution !== null)<input type="hidden" name="institution_id" value="{{ $institution->id }}">@endif
                        @if($preview)<input type="hidden" name="preview" value="1">@endif
                        <button type="submit" class="hsp-context-choice">
                            <span>
                                <span class="block text-xl font-bold text-neutral-900">{{ $label }}</span>
                                @if($preview)<span class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-bold uppercase text-amber-950">{{ __('Preview as role') }}</span>@endif
                                @if($institution !== null)<span class="mt-1 block text-neutral-600">{{ $institution->displayName(app()->getLocale()) }}</span>@endif
                                <span class="mt-2 block text-sm text-neutral-600">{{ $description }}</span>
                            </span>
                            <span class="hsp-context-choice__action">{{ __('Switch to this context') }} <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span>
                        </button>
                    </form>
                @endif
            @endforeach
        </div>
    </main>
@endsection

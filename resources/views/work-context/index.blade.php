@extends('layouts.app')

@section('title', __('Switch role or work context'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    <main class="container mx-auto max-w-4xl space-y-6 px-6 py-10">
        <header>
            <a href="{{ app(\App\Services\RoleLandingResolver::class)->url(auth()->user()) }}" class="font-semibold text-indigo-700 hover:text-indigo-900">← {{ __('Return to current dashboard') }}</a>
            <h1 class="mt-4 text-3xl font-bold text-neutral-900">{{ __('Switch role or work context') }}</h1>
            <p class="mt-2 max-w-3xl text-neutral-600">{{ __('Only one role and institution context is active at a time. Hospitrainity validates it again on every request.') }}</p>
        </header>

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach($contexts as $context)
                @php($role = $context['role'])
                @php($institution = $context['institution'])
                @php($preview = $context['preview'])
                @php($label = match($role) {
                    \App\Enums\WorkContextRole::Learner => __('Learner'),
                    \App\Enums\WorkContextRole::Instructor => __('Instructor'),
                    \App\Enums\WorkContextRole::InstitutionAdmin => __('Institution Admin'),
                    \App\Enums\WorkContextRole::ContentAuthor => __('Content Author'),
                    \App\Enums\WorkContextRole::SystemAdmin => __('System Admin'),
                })
                <form method="POST" action="{{ route('work-context.store') }}" class="rounded-lg border bg-white p-5 shadow-sm">
                    @csrf
                    <input type="hidden" name="role" value="{{ $role->value }}">
                    @if($institution !== null)<input type="hidden" name="institution_id" value="{{ $institution->id }}">@endif
                    @if($preview)<input type="hidden" name="preview" value="1">@endif
                    <h2 class="text-xl font-bold text-neutral-900">{{ $label }}</h2>
                    @if($preview)<p class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-1 text-xs font-bold uppercase text-amber-950">{{ __('Preview as role') }}</p>@endif
                    @if($institution !== null)<p class="mt-1 text-neutral-600">{{ $institution->displayName(app()->getLocale()) }}</p>@endif
                    <p class="mt-2 text-sm text-neutral-600">{{ match($role) {
                        \App\Enums\WorkContextRole::Learner => __('Personal or institution-attributed learning, selected separately in Learning context.'),
                        \App\Enums\WorkContextRole::Instructor => __('Learner oversight, invitations, and classroom membership requests for this institution.'),
                        \App\Enums\WorkContextRole::InstitutionAdmin => __('Institution-scoped management without global platform authority.'),
                        \App\Enums\WorkContextRole::ContentAuthor => __('Shared curriculum authoring without institution or system administration.'),
                        \App\Enums\WorkContextRole::SystemAdmin => __('Global platform administration. Institutions cannot grant this role.'),
                    } }}</p>
                    <button type="submit" class="mt-4 rounded-md border border-indigo-600 px-4 py-2 font-semibold text-indigo-700 hover:bg-indigo-50">{{ $currentRole === $role && $currentPreview === $preview ? __('Continue in this context') : __('Switch to this context') }}</button>
                </form>
            @endforeach
        </div>
    </main>
@endsection

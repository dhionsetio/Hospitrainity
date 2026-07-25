@extends('layouts.app')

@section('title')
    {{ __('responses.edit_title') }} - Hospitrainity
@endsection
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @include('partials.learner-nav')

    <main class="container mx-auto max-w-3xl px-4 py-6 sm:px-6 sm:py-10">
        <div class="flex flex-wrap gap-x-6 gap-y-2">
            <a href="{{ route('curriculum.activities.show', $activity->code) }}" class="inline-flex min-h-11 items-center gap-2 font-semibold text-indigo-700 underline-offset-4 hover:underline">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                <span>{{ __('responses.back_to_activity') }}</span>
            </a>
            <a href="{{ route('responses.index') }}" class="inline-flex min-h-11 items-center gap-2 font-semibold text-indigo-700 underline-offset-4 hover:underline">
                <i class="fa-solid fa-file-pen" aria-hidden="true"></i>
                <span>{{ __('responses.back_to_saved') }}</span>
            </a>
        </div>

        <header class="mt-5 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-7">
            <p class="text-sm font-semibold text-indigo-700">{{ $activity->payloadData()['title'] ?? '' }}</p>
            <h1 class="mt-2 text-3xl font-bold text-neutral-950">{{ __('responses.edit_title') }}</h1>
            <p class="mt-4 text-lg leading-8 text-neutral-800">{{ $prompt->payloadData()['stem'] ?? '' }}</p>
        </header>

        @if (session('status'))
            <div class="mt-5 rounded-xl border border-emerald-300 bg-emerald-50 p-4 font-semibold text-emerald-950" role="status">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="mt-5 rounded-xl border-2 border-red-500 bg-red-50 p-4 text-red-950" role="alert">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('responses.store', [$activity->code, $prompt->code]) }}" class="mt-6 space-y-6 rounded-2xl border border-neutral-200 bg-white p-5 shadow-sm sm:p-7">
            @csrf
            <input type="hidden" name="response_key" value="{{ old('response_key', $responseKey) }}">

            <fieldset>
                <legend class="text-base font-bold text-neutral-950">{{ __('responses.kind_label') }}</legend>
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-neutral-300 p-4 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50">
                        <input type="radio" name="kind" value="assessment" class="h-5 w-5 shrink-0" @checked(old('kind', $draft?->kind ?? 'assessment') === 'assessment')>
                        <span class="font-semibold">{{ __('responses.assessment') }}</span>
                    </label>
                    <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-neutral-300 p-4 has-[:checked]:border-indigo-600 has-[:checked]:bg-indigo-50">
                        <input type="radio" name="kind" value="journal" class="h-5 w-5 shrink-0" @checked(old('kind', $draft?->kind) === 'journal')>
                        <span class="font-semibold">{{ __('responses.journal') }}</span>
                    </label>
                </div>
            </fieldset>

            <div>
                <label for="learner-response-body" class="block text-base font-bold text-neutral-950">{{ __('responses.response_label') }}</label>
                <textarea
                    id="learner-response-body"
                    name="body"
                    rows="10"
                    maxlength="{{ $maximumCharacters }}"
                    class="mt-3 block w-full rounded-xl border-neutral-400 text-base leading-7 focus:border-indigo-600 focus:ring-indigo-600"
                    required
                >{{ old('body', $draft?->body) }}</textarea>
                <p class="mt-2 text-sm leading-6 text-neutral-600">{{ __('responses.privacy_note') }}</p>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="submit" name="intent" value="save" class="inline-flex min-h-11 items-center gap-2 rounded-xl border border-indigo-700 bg-white px-5 py-3 font-bold text-indigo-700 hover:bg-indigo-50 focus:outline-none focus:ring-4 focus:ring-indigo-200">
                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i>
                    <span>{{ __('responses.save_draft') }}</span>
                </button>
                <button type="submit" name="intent" value="submit" class="inline-flex min-h-11 items-center gap-2 rounded-xl bg-indigo-700 px-5 py-3 font-bold text-white hover:bg-indigo-800 focus:outline-none focus:ring-4 focus:ring-indigo-300">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                    <span>{{ __('responses.submit') }}</span>
                </button>
            </div>
        </form>
    </main>
@endsection

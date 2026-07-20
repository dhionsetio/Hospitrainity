@extends('layouts.app')

@section('title', __('Display and accessibility preferences'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
<main id="main-content" class="mx-auto w-full max-w-4xl px-4 py-8 sm:px-6">
    <a href="{{ $returnUrl }}" class="inline-flex min-h-11 items-center font-semibold text-indigo-700 underline">&larr; {{ __('Return to dashboard') }}</a>
    <header class="mt-4">
        <p class="text-sm font-semibold uppercase tracking-wide text-indigo-700">{{ __('Your account') }}</p>
        <h1 class="mt-1 text-3xl font-bold text-neutral-950">{{ __('Display and accessibility preferences') }}</h1>
        <p class="mt-2 max-w-3xl text-neutral-700">{{ __('These choices follow you across signed-in devices. Browser zoom, operating-system accessibility settings, keyboard access, semantic reading order, and error recovery always remain available.') }}</p>
    </header>

    @if(session('status'))
        <div class="mt-6 rounded-md border border-green-400 bg-green-50 p-4 text-green-950" role="status" tabindex="-1" data-focus-status>{{ session('status') }}</div>
    @endif
    @if($errors->any())
        <div id="preference-errors" class="mt-6 rounded-md border border-red-400 bg-red-50 p-4 text-red-950" role="alert" tabindex="-1" data-focus-errors>
            <h2 class="font-bold">{{ __('Please correct the highlighted preferences.') }}</h2>
            <ul class="mt-2 list-disc pl-5">
                @foreach($errors->keys() as $field)
                    <li><a class="underline" href="#{{ $field }}">{{ $errors->first($field) }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('preferences.update') }}" class="mt-8 space-y-8">
        @csrf
        @method('PATCH')

        @foreach([
            'ui_theme' => [__('Theme'), __('Use the system appearance by default, or keep this account light or dark.'), ['system' => __('System'), 'light' => __('Light'), 'dark' => __('Dark')]],
            'ui_motion' => [__('Motion'), __('System follows your device. Reduce removes non-essential movement. Full keeps normal interface transitions.'), ['system' => __('System'), 'reduce' => __('Reduce'), 'full' => __('Full')]],
            'ui_text_scale' => [__('Text size'), __('This adds an in-site text adjustment without limiting browser zoom.'), ['default' => __('Default'), 'large' => __('Large'), 'larger' => __('Larger')]],
        ] as $field => [$legend, $description, $options])
            <fieldset class="rounded-xl border border-neutral-300 bg-white p-5" @if($errors->has($field)) aria-describedby="{{ $field }}-description {{ $field }}-error" @else aria-describedby="{{ $field }}-description" @endif>
                <legend class="px-1 text-lg font-bold text-neutral-950">{{ $legend }}</legend>
                <p id="{{ $field }}-description" class="mt-1 text-sm text-neutral-700">{{ $description }}</p>
                @error($field)<p id="{{ $field }}-error" class="mt-2 font-semibold text-red-800">{{ $message }}</p>@enderror
                <div class="mt-4 grid gap-3 sm:grid-cols-3">
                    @foreach($options as $value => $label)
                        <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-lg border border-neutral-300 px-4 py-3 font-medium text-neutral-900 transition-colors has-[:checked]:border-indigo-400 has-[:checked]:bg-indigo-50 has-[:checked]:text-indigo-950 has-[:checked]:shadow-sm">
                            <input id="{{ $field }}{{ $loop->first ? '' : '-'.$value }}" name="{{ $field }}" value="{{ $value }}" type="radio" class="h-5 w-5 shrink-0" @checked(old($field, $user->{$field}) === $value) @if($errors->has($field)) aria-invalid="true" @endif>
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
        @endforeach

        <fieldset class="rounded-xl border border-neutral-300 bg-white p-5">
            <legend class="px-1 text-lg font-bold text-neutral-950">{{ __('Additional preferences') }}</legend>
            <div class="mt-2 space-y-3">
                <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border border-neutral-300 p-4">
                    <input id="ui_high_contrast" name="ui_high_contrast" value="1" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0" @checked(old('ui_high_contrast', $user->ui_high_contrast))>
                    <span><span class="block font-semibold">{{ __('Stronger contrast') }}</span><span class="block text-sm text-neutral-700">{{ __('Strengthen borders and link identification while keeping content and meaning unchanged.') }}</span></span>
                </label>
                <label class="flex min-h-11 cursor-pointer items-start gap-3 rounded-lg border border-neutral-300 p-4">
                    <input id="ui_no_audio" name="ui_no_audio" value="1" type="checkbox" class="mt-0.5 h-5 w-5 shrink-0" @checked(old('ui_no_audio', $user->ui_no_audio))>
                    <span><span class="block font-semibold">{{ __('Prefer no audio') }}</span><span class="block text-sm text-neutral-700">{{ __('Records your preference for text or visual alternatives. It does not remove required learning information.') }}</span></span>
                </label>
            </div>
        </fieldset>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="min-h-11 rounded-lg bg-indigo-700 px-6 py-3 font-semibold text-white hover:bg-indigo-800">{{ __('Save preferences') }}</button>
            <a href="{{ route('policies.show', ['type' => 'accessibility']) }}" class="inline-flex min-h-11 items-center rounded-lg border border-indigo-700 px-5 py-3 font-semibold text-indigo-800">{{ __('Accessibility information') }}</a>
        </div>
    </form>
</main>
@endsection

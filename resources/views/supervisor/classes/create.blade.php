@extends('layouts.app')

@section('title', __('classes.create_title').' - Hospitrainity')
@section('bodyClass', 'bg-neutral-100')

@section('content')
    <main class="container mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
        <div class="mx-auto max-w-5xl">
                <a href="{{ route('supervisor.classes.index') }}" class="inline-flex min-h-11 items-center gap-2 font-semibold text-indigo-800 hover:text-indigo-950">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    {{ __('classes.back_to_classes') }}
                </a>
                <header class="mt-4">
                    <p class="text-sm font-semibold text-indigo-700">{{ $institution->displayName(app()->getLocale()) }}</p>
                    <h1 class="mt-1 text-3xl font-bold text-neutral-950">{{ __('classes.create_title') }}</h1>
                    <p class="mt-2 max-w-3xl text-neutral-600">{{ __('classes.create_intro') }}</p>
                </header>

                @if($errors->any())
                    <div class="mt-6 rounded-lg border border-red-300 bg-red-50 p-4 text-red-950" role="alert">
                        <p class="font-bold">{{ __('Please correct the highlighted fields.') }}</p>
                        <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                @if($instructors->isEmpty())
                    <div class="mt-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-950">
                        <p class="font-semibold">{{ __('classes.no_instructors') }}</p>
                        <a href="{{ route('supervisor.institution-roles.index') }}" class="mt-2 inline-flex min-h-11 items-center font-bold underline">{{ __('classes.manage_roles') }}</a>
                    </div>
                @endif

                <div class="mt-8 grid gap-6 xl:grid-cols-2">
                    <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm" aria-labelledby="reuse-revision-heading">
                        <h2 id="reuse-revision-heading" class="text-xl font-bold text-neutral-950">{{ __('classes.reuse_revision') }}</h2>
                        <p class="mt-2 text-sm text-neutral-600">{{ __('classes.reuse_revision_intro') }}</p>
                        @if($revisions->isEmpty())
                            <p class="mt-5 rounded-lg border border-dashed border-neutral-300 p-4 text-neutral-600">{{ __('classes.no_revisions') }}</p>
                        @else
                            <form method="POST" action="{{ route('supervisor.classes.store-from-revision') }}" class="mt-5 space-y-4">
                                @csrf
                                <label class="block font-semibold text-neutral-900">
                                    {{ __('classes.revision') }}
                                    <select name="course_revision_id" required class="mt-2 min-h-11 w-full rounded-lg border-neutral-300">
                                        @foreach($revisions as $revision)
                                            <option value="{{ $revision->id }}" @selected(old('course_revision_id') === $revision->id)>{{ $revision->course->title }}, {{ $revision->title }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                @include('supervisor.classes.partials.class-fields', ['prefix' => 'existing', 'instructors' => $instructors])
                                <button type="submit" @disabled($instructors->isEmpty()) class="min-h-11 w-full rounded-lg bg-indigo-700 px-5 py-2.5 font-semibold text-white hover:bg-indigo-800 disabled:cursor-not-allowed disabled:opacity-50">{{ __('classes.create_from_revision') }}</button>
                            </form>
                        @endif
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm" aria-labelledby="new-course-heading">
                        <h2 id="new-course-heading" class="text-xl font-bold text-neutral-950">{{ __('classes.new_course') }}</h2>
                        <p class="mt-2 text-sm text-neutral-600">{{ __('classes.new_course_intro') }}</p>
                        @if($activePackage === null)
                            <p class="mt-5 rounded-lg border border-amber-300 bg-amber-50 p-4 font-semibold text-amber-950">{{ __('classes.no_package') }}</p>
                        @else
                            <form method="POST" action="{{ route('supervisor.classes.store-with-course') }}" class="mt-5 space-y-4">
                                @csrf
                                <input type="hidden" name="curriculum_package_id" value="{{ $activePackage->id }}">
                                <label class="block font-semibold text-neutral-900">{{ __('classes.course_title') }}<input name="course_title" value="{{ old('course_title') }}" required maxlength="180" class="mt-2 min-h-11 w-full rounded-lg border-neutral-300"></label>
                                <label class="block font-semibold text-neutral-900">{{ __('classes.course_key') }}<input name="course_key" value="{{ old('course_key') }}" required maxlength="100" class="mt-2 min-h-11 w-full rounded-lg border-neutral-300" autocomplete="off"></label>
                                <label class="block font-semibold text-neutral-900">{{ __('classes.course_description') }}<textarea name="course_description" rows="3" maxlength="2000" class="mt-2 w-full rounded-lg border-neutral-300">{{ old('course_description') }}</textarea></label>
                                <label class="block font-semibold text-neutral-900">{{ __('classes.revision_title') }}<input name="revision_title" value="{{ old('revision_title') }}" required maxlength="180" class="mt-2 min-h-11 w-full rounded-lg border-neutral-300"></label>
                                <fieldset>
                                    <legend class="font-semibold text-neutral-900">{{ __('classes.approved_modules') }}</legend>
                                    <div class="mt-2 max-h-72 space-y-2 overflow-y-auto rounded-lg border border-neutral-300 p-3">
                                        @foreach($modules as $module)
                                            <label class="flex min-h-11 items-start gap-3 rounded-lg p-2 hover:bg-neutral-50">
                                                <input type="checkbox" name="module_ids[]" class="mt-1 shrink-0 rounded border-neutral-400 text-indigo-700" value="{{ $module->id }}" @checked(in_array($module->id, old('module_ids', [])))>
                                                <span class="font-semibold text-neutral-900">{{ $module->payloadData()['title'] ?? __('Untitled module') }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                                @include('supervisor.classes.partials.class-fields', ['prefix' => 'new', 'instructors' => $instructors])
                                <button type="submit" @disabled($instructors->isEmpty() || $modules->isEmpty()) class="min-h-11 w-full rounded-lg bg-indigo-700 px-5 py-2.5 font-semibold text-white hover:bg-indigo-800 disabled:cursor-not-allowed disabled:opacity-50">{{ __('classes.create_with_course') }}</button>
                            </form>
                        @endif
                    </section>
                </div>
            </div>
        </main>
@endsection

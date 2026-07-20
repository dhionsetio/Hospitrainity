@extends('layouts.app')

@section('title'){{ $exercise ? __('admin.edit_exercise') : __('admin.create_exercise') }} - {{ __('admin.exercise_templates.'.$templateType) }}@endsection
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @php($legacyCurriculumReadOnly = true)
    @php($editable = $draft->status === \App\Enums\CurriculumDraftStatus::Draft)
    @php($items = old('items', $formData['items']))
    <div class="flex min-h-screen flex-col bg-neutral-100 md:flex-row">
        @include('superadmin.sidebar')
        <main class="min-w-0 flex-1 p-6 md:p-10">
            <x-back-control :href="route($routePrefix.'.curriculum-drafts.exercises.index', $draft)" :label="__('admin.exercise_builder')" />
            <header class="mt-4 rounded-xl bg-white p-6 shadow">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div><p class="text-sm font-semibold uppercase tracking-wide text-indigo-700">{{ __('admin.exercise_templates.'.$templateType) }}</p><h1 class="mt-2 text-3xl font-bold text-neutral-950">{{ $exercise ? __('admin.edit_exercise') : __('admin.create_exercise') }}</h1><p class="mt-2 max-w-4xl text-neutral-700">{{ __('admin.exercise_template_help.'.$templateType) }}</p></div>
                    @if($exercise)<a href="{{ route($routePrefix.'.curriculum-drafts.preview.activities.show', [$draft, $exercise->code]) }}" class="rounded-md border border-indigo-700 px-4 py-2 font-semibold text-indigo-800">{{ __('admin.preview_as_learner') }}</a>@endif
                </div>
                <dl class="mt-5 grid gap-3 text-sm sm:grid-cols-3"><div><dt class="font-semibold">{{ __('admin.response') }}</dt><dd>{{ str_replace('_', ' ', $definition['response_form']) }}</dd></div><div><dt class="font-semibold">{{ __('admin.scoring') }}</dt><dd>{{ str_replace('_', ' ', $definition['scoring_mode']) }}</dd></div><div><dt class="font-semibold">{{ __('admin.item_limit') }}</dt><dd>{{ $definition['cardinality']['minimum'] }}–{{ $definition['cardinality']['maximum'] }}</dd></div></dl>
            </header>

            @if(session('success'))<div class="mt-5 rounded-lg border border-green-300 bg-green-50 p-4 text-green-950" role="status">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="mt-5 rounded-lg border border-red-300 bg-red-50 p-4 text-red-950" role="alert" tabindex="-1" data-error-summary><p class="font-bold">{{ __('admin.correct_form_errors') }}</p><ul class="mt-2 list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            <form method="POST" action="{{ $exercise ? route($routePrefix.'.curriculum-drafts.exercises.update', [$draft, $exercise]) : route($routePrefix.'.curriculum-drafts.exercises.store', $draft) }}" class="mt-6 space-y-6" data-exercise-authoring data-min-items="{{ $definition['cardinality']['minimum'] }}" data-max-items="{{ $definition['cardinality']['maximum'] }}">
                @csrf @if($exercise)@method('PATCH')@endif
                <input type="hidden" name="draft_revision" value="{{ $draft->revision }}"><input type="hidden" name="template_type" value="{{ $templateType }}">@if($exercise)<input type="hidden" name="activity_revision" value="{{ $exercise->revision }}">@endif

                <section class="rounded-xl bg-white p-6 shadow" aria-labelledby="exercise-details-heading">
                    <h2 id="exercise-details-heading" class="text-xl font-bold text-neutral-950">{{ __('admin.exercise_details') }}</h2>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <label class="text-sm font-semibold">{{ __('admin.code') }}<input name="code" required maxlength="120" pattern="[A-Z0-9]+(?:-[A-Z0-9]+)*" value="{{ old('code', $formData['code']) }}" @readonly($exercise) class="mt-1 block w-full rounded-md border-neutral-300 read-only:bg-neutral-100" aria-describedby="code-help"><span id="code-help" class="mt-1 block font-normal text-neutral-600">{{ __('admin.stable_code_help') }}</span></label>
                        <label class="text-sm font-semibold">{{ __('admin.lesson_section') }}<select name="section_code" required class="mt-1 block w-full rounded-md border-neutral-300"><option value="">{{ __('admin.choose_lesson_section') }}</option>@foreach($sections as $section)<option value="{{ $section->code }}" @selected(old('section_code', $formData['section_code']) === $section->code)>{{ $section->parent_code }} · {{ $section->payload['title'] }} ({{ $section->code }})</option>@endforeach</select></label>
                        <label class="text-sm font-semibold md:col-span-2">{{ __('admin.title') }}<input name="title" required minlength="2" maxlength="240" value="{{ old('title', $formData['title']) }}" class="mt-1 block w-full rounded-md border-neutral-300"></label>
                        <label class="text-sm font-semibold md:col-span-2">{{ __('admin.guidance') }}<textarea name="guidance" maxlength="3000" rows="3" class="mt-1 block w-full rounded-md border-neutral-300">{{ old('guidance', $formData['guidance']) }}</textarea></label>
                        <label class="text-sm font-semibold md:col-span-2">{{ __('admin.provenance_note') }}<textarea name="provenance_note" required minlength="10" maxlength="2000" rows="3" class="mt-1 block w-full rounded-md border-neutral-300">{{ old('provenance_note', $formData['provenance_note']) }}</textarea><span class="mt-1 block font-normal text-neutral-600">{{ __('admin.exercise_provenance_help') }}</span></label>
                        @if($definition['audio_required'])
                            <label class="text-sm font-semibold md:col-span-2">{{ __('admin.required_audio_asset') }}<select name="audio_asset_public_id" required class="mt-1 block w-full rounded-md border-neutral-300"><option value="">{{ __('admin.choose_audio_asset') }}</option>@foreach($audioAssets as $asset)<option value="{{ $asset->public_id }}" @selected(old('audio_asset_public_id', $formData['audio_asset_public_id']) === $asset->public_id)>{{ $asset->display_name }} · {{ $asset->accessibility_text }}</option>@endforeach</select><span class="mt-1 block font-normal text-neutral-600">{{ __('admin.audio_asset_exercise_help') }}</span></label>
                        @endif
                    </div>
                </section>

                <section class="rounded-xl bg-white p-6 shadow" aria-labelledby="exercise-items-heading">
                    <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 id="exercise-items-heading" class="text-xl font-bold text-neutral-950">{{ __('admin.exercise_items') }}</h2><p class="mt-1 text-sm text-neutral-600">{{ __('admin.reorder_items_help') }}</p></div>@if($editable)<button type="button" data-add-exercise-item class="rounded-md border border-indigo-700 px-4 py-2 font-semibold text-indigo-800">{{ __('admin.add_item') }}</button>@endif</div>
                    <p class="sr-only" aria-live="polite" data-exercise-authoring-status></p>
                    <ol class="mt-5 space-y-5" data-exercise-items>
                        @foreach($items as $index => $item)
                            <li class="rounded-lg border border-neutral-300 p-5" data-exercise-item>
                                <input type="hidden" name="items[{{ $index }}][code]" value="{{ $item['code'] ?? '' }}" data-stable-item-code>
                                <div class="flex flex-wrap items-center justify-between gap-3"><h3 class="font-bold text-neutral-950"><span data-exercise-item-number>{{ __('admin.item_number', ['number' => $index + 1]) }}</span> @if($item['code'] ?? null)<span class="ml-2 font-mono text-xs font-normal text-neutral-500">{{ $item['code'] }}</span>@endif</h3><div class="flex flex-wrap gap-2"><button type="button" data-item-up class="min-h-11 rounded-md border border-neutral-400 px-3 font-semibold">{{ __('admin.move_up') }}</button><button type="button" data-item-down class="min-h-11 rounded-md border border-neutral-400 px-3 font-semibold">{{ __('admin.move_down') }}</button><button type="button" data-remove-exercise-item class="min-h-11 rounded-md border border-red-500 px-3 font-semibold text-red-800">{{ __('admin.remove') }}</button></div></div>
                                <div class="mt-4 grid gap-4">
                                    <label class="text-sm font-semibold">{{ __('admin.prompt') }}<textarea name="items[{{ $index }}][stem]" required maxlength="2000" rows="3" class="mt-1 block w-full rounded-md border-neutral-300">{{ $item['stem'] ?? '' }}</textarea></label>
                                    @if($definition['editor'] === 'closed')
                                        <label class="text-sm font-semibold">{{ __('admin.accepted_answers') }}<textarea name="items[{{ $index }}][answer]" required maxlength="4000" rows="3" class="mt-1 block w-full rounded-md border-neutral-300">{{ $item['answer'] ?? '' }}</textarea><span class="mt-1 block font-normal text-neutral-600">{{ __('admin.one_answer_per_line') }}</span></label>
                                    @elseif($definition['editor'] === 'explicit_selection')
                                        <label class="text-sm font-semibold">{{ __('admin.options') }}<textarea name="items[{{ $index }}][options]" required maxlength="6000" rows="4" class="mt-1 block w-full rounded-md border-neutral-300">{{ $item['options'] ?? '' }}</textarea><span class="mt-1 block font-normal text-neutral-600">{{ __('admin.one_option_per_line') }}</span></label>
                                        <label class="text-sm font-semibold">{{ __('admin.correct_answer') }}<input name="items[{{ $index }}][answer]" required maxlength="1000" value="{{ $item['answer'] ?? '' }}" class="mt-1 block w-full rounded-md border-neutral-300"><span class="mt-1 block font-normal text-neutral-600">{{ __('admin.answer_must_match_option') }}</span></label>
                                    @elseif(in_array($definition['editor'], ['derived_selection', 'silent_letter'], true))
                                        <label class="text-sm font-semibold">{{ $definition['editor'] === 'silent_letter' ? __('admin.silent_letter') : __('admin.match_or_category_answer') }}<input name="items[{{ $index }}][answer]" required maxlength="1000" value="{{ $item['answer'] ?? '' }}" class="mt-1 block w-full rounded-md border-neutral-300"></label>
                                    @elseif($definition['editor'] === 'ordering')
                                        <label class="text-sm font-semibold">{{ __('admin.correct_order') }}<textarea name="items[{{ $index }}][tokens]" required maxlength="6000" rows="5" class="mt-1 block w-full rounded-md border-neutral-300">{{ $item['tokens'] ?? '' }}</textarea><span class="mt-1 block font-normal text-neutral-600">{{ __('admin.correct_order_help') }}</span></label>
                                    @elseif($definition['editor'] === 'open')
                                        <label class="text-sm font-semibold">{{ __('admin.model_answer') }}<textarea name="items[{{ $index }}][model_answer]" required maxlength="6000" rows="5" class="mt-1 block w-full rounded-md border-neutral-300">{{ $item['model_answer'] ?? '' }}</textarea><span class="mt-1 block font-normal text-neutral-600">{{ __('admin.open_answer_not_auto_graded') }}</span></label>
                                    @endif
                                    <label class="text-sm font-semibold">{{ __('admin.feedback_next_step') }}<textarea name="items[{{ $index }}][feedback]" required maxlength="3000" rows="3" class="mt-1 block w-full rounded-md border-neutral-300">{{ $item['feedback'] ?? '' }}</textarea></label>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>

                @if($definition['editor'] === 'open' || $definition['rubric_required'])
                    <section class="rounded-xl bg-white p-6 shadow" aria-labelledby="rubric-heading"><h2 id="rubric-heading" class="text-xl font-bold text-neutral-950">{{ __('admin.self_assessment_rubric') }}</h2><label class="mt-4 block text-sm font-semibold">{{ __('admin.rubric_rows') }}<textarea name="rubric" @required($definition['rubric_required']) maxlength="6000" rows="6" class="mt-1 block w-full rounded-md border-neutral-300">{{ old('rubric', $formData['rubric']) }}</textarea><span class="mt-1 block font-normal text-neutral-600">{{ __('admin.rubric_format_help') }}</span></label></section>
                @endif

                @if($editable)<button class="rounded-md bg-indigo-700 px-6 py-3 font-bold text-white hover:bg-indigo-800">{{ __('admin.save_exercise') }}</button>@endif
            </form>

            @if($exercise && $editable)
                <section class="mt-6 rounded-xl border border-neutral-300 bg-white p-6 shadow" aria-labelledby="duplicate-heading"><h2 id="duplicate-heading" class="text-xl font-bold text-neutral-950">{{ __('admin.duplicate_exercise') }}</h2><p class="mt-2 text-neutral-700">{{ __('admin.duplicate_exercise_help') }}</p><form method="POST" action="{{ route($routePrefix.'.curriculum-drafts.exercises.duplicate', [$draft, $exercise]) }}" class="mt-4 grid gap-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">@csrf<input type="hidden" name="draft_revision" value="{{ $draft->revision }}"><label class="min-w-0 text-sm font-semibold">{{ __('admin.target_lesson_section') }}<select name="section_code" required class="mt-1 block w-full max-w-full rounded-md border-neutral-300"><option value="">{{ __('admin.choose_lesson_section') }}</option>@foreach($sections->where('code', '!=', $exercise->parent_code) as $section)<option value="{{ $section->code }}">{{ $section->parent_code }} · {{ $section->payload['title'] }}</option>@endforeach</select></label><button class="rounded-md border border-indigo-700 px-4 py-2 font-semibold text-indigo-800">{{ __('admin.duplicate') }}</button></form></section>
            @endif
        </main>
    </div>
@endsection

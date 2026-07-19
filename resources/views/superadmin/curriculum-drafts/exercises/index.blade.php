@extends('layouts.app')

@section('title'){{ __('admin.exercise_builder') }} - {{ $draft->title }}@endsection
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @php($legacyCurriculumReadOnly = true)
    @php($editable = $draft->status === \App\Enums\CurriculumDraftStatus::Draft)
    <div class="flex min-h-screen flex-col bg-neutral-100 md:flex-row">
        @include('superadmin.sidebar')
        <main class="min-w-0 flex-1 p-6 md:p-10">
            <a href="{{ route($routePrefix.'.curriculum-drafts.show', $draft) }}" class="font-semibold text-indigo-700 underline">&larr; {{ $draft->title }}</a>
            <header class="mt-4 rounded-xl bg-white p-6 shadow">
                <p class="text-sm font-semibold uppercase tracking-wide text-indigo-700">{{ __('admin.registry_version') }} {{ \App\Services\Curriculum\CanonicalExerciseTemplateRegistry::VERSION }}</p>
                <h1 class="mt-2 text-3xl font-bold text-neutral-950">{{ __('admin.exercise_builder') }}</h1>
                <p class="mt-2 max-w-4xl text-neutral-700">{{ __('admin.exercise_builder_help') }}</p>
            </header>

            @if(session('success'))<div class="mt-5 rounded-lg border border-green-300 bg-green-50 p-4 text-green-950" role="status">{{ session('success') }}</div>@endif

            <section class="mt-6 rounded-xl bg-white p-6 shadow" aria-labelledby="draft-exercises-heading">
                <h2 id="draft-exercises-heading" class="text-xl font-bold text-neutral-950">{{ __('admin.draft_exercises') }}</h2>
                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full border-collapse text-left">
                        <thead><tr class="border-b border-neutral-300"><th class="p-3">{{ __('admin.exercise') }}</th><th class="p-3">{{ __('admin.template') }}</th><th class="p-3">{{ __('admin.lesson_section') }}</th><th class="p-3">{{ __('admin.items') }}</th><th class="p-3">{{ __('admin.status') }}</th><th class="p-3">{{ __('admin.actions') }}</th></tr></thead>
                        <tbody>
                            @forelse($exercises as $exercise)
                                <tr class="border-b border-neutral-200">
                                    <td class="p-3"><span class="block font-semibold text-neutral-950">{{ $exercise->payload['title'] }}</span><span class="font-mono text-xs text-neutral-500">{{ $exercise->code }} · r{{ $exercise->revision }}</span></td>
                                    <td class="p-3">{{ __('admin.exercise_templates.'.$exercise->payload['template_type']) }}</td>
                                    <td class="p-3 font-mono text-sm">{{ $exercise->parent_code }}</td>
                                    <td class="p-3">{{ $exercise->prompt_count }}</td>
                                    <td class="p-3">{{ $exercise->archived_at ? __('admin.archived') : __('admin.'.$draft->status->value) }}</td>
                                    <td class="p-3"><a href="{{ route($routePrefix.'.curriculum-drafts.exercises.edit', [$draft, $exercise]) }}" class="font-semibold text-indigo-700 underline">{{ __('admin.edit') }}</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="p-5 text-neutral-600">{{ __('admin.no_draft_exercises') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mt-6" aria-labelledby="exercise-templates-heading">
                <h2 id="exercise-templates-heading" class="text-2xl font-bold text-neutral-950">{{ __('admin.choose_exercise_template') }}</h2>
                @unless($editable)<p class="mt-2 text-neutral-700">{{ __('admin.workspace_is_read_only') }}</p>@endunless
                <div class="mt-4 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @foreach($templates as $type => $definition)
                        <article class="rounded-xl bg-white p-5 shadow">
                            <div class="flex items-start justify-between gap-3"><h3 class="text-lg font-bold text-neutral-950">{{ __('admin.exercise_templates.'.$type) }}</h3><span class="rounded-full px-2 py-1 text-xs font-semibold {{ $definition['enabled'] ? 'bg-emerald-100 text-emerald-900' : 'bg-amber-100 text-amber-950' }}">{{ $definition['enabled'] ? __('admin.enabled') : __('admin.unavailable') }}</span></div>
                            <p class="mt-2 text-sm text-neutral-700">{{ __('admin.exercise_template_help.'.$type) }}</p>
                            @unless($definition['enabled'])<p class="mt-3 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm font-semibold text-amber-950">{{ __('admin.exercise_unavailable_reasons.'.$definition['unavailable_reason']) }}</p>@endunless
                            <dl class="mt-4 grid grid-cols-2 gap-2 text-sm"><div><dt class="font-semibold">{{ __('admin.response') }}</dt><dd>{{ str_replace('_', ' ', $definition['response_form']) }}</dd></div><div><dt class="font-semibold">{{ __('admin.scoring') }}</dt><dd>{{ str_replace('_', ' ', $definition['scoring_mode']) }}</dd></div><div><dt class="font-semibold">{{ __('admin.cardinality') }}</dt><dd>{{ $definition['cardinality']['minimum'] }}–{{ $definition['cardinality']['maximum'] }}</dd></div><div><dt class="font-semibold">{{ __('admin.renderer') }}</dt><dd>{{ $definition['renderer'] }}</dd></div></dl>
                            @if($editable && $definition['enabled'])<a href="{{ route($routePrefix.'.curriculum-drafts.exercises.create', [$draft, 'template' => $type]) }}" class="mt-5 inline-block rounded-md bg-indigo-700 px-4 py-2 font-semibold text-white hover:bg-indigo-800">{{ __('admin.use_template') }}</a>@endif
                        </article>
                    @endforeach
                </div>
            </section>
        </main>
    </div>
@endsection

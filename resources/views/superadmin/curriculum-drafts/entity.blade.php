@extends('layouts.app')

@section('title'){{ $entity->code }} - {{ $draft->title }}@endsection
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @php($legacyCurriculumReadOnly = true)
    @php($editable = $draft->status === \App\Enums\CurriculumDraftStatus::Draft)
    <div class="flex min-h-screen flex-col bg-neutral-100 md:flex-row">
        @include('superadmin.sidebar')
        <main class="min-w-0 flex-1 p-6 md:p-10">
            <x-back-control :href="route($routePrefix.'.curriculum-drafts.show', $draft)" :label="__('admin.return_to_draft')" />
            <header class="mt-4 rounded-xl bg-white p-6 shadow">
                <div class="flex flex-wrap gap-2"><span class="rounded-full bg-indigo-100 px-3 py-1 font-semibold text-indigo-800">{{ $entity->entity_type }}</span>@if($entity->archived_at)<span class="rounded-full bg-amber-100 px-3 py-1 font-semibold text-amber-900">{{ __('admin.archived') }}</span>@endif</div>
                <h1 class="mt-3 break-words text-3xl font-bold text-neutral-950">{{ $entity->payload['title'] ?? $entity->payload['statement'] ?? $entity->code }}</h1>
                <p class="mt-2 font-mono text-sm text-neutral-600">{{ $entity->code }} &middot; {{ __('admin.revision') }} {{ $entity->revision }} &middot; {{ $entity->source_path }}</p>
            </header>

            @foreach(['success' => 'border-green-300 bg-green-50 text-green-950', 'warning' => 'border-amber-300 bg-amber-50 text-amber-950'] as $key => $style)
                @if(session($key))<div class="mt-5 rounded-lg border p-4 {{ $style }}" role="status">{{ session($key) }}</div>@endif
            @endforeach
            @if($errors->any())<div class="mt-5 rounded-lg border border-red-300 bg-red-50 p-4 text-red-950" role="alert"><ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

            @if($editable)
                <section class="mt-6 rounded-xl bg-white p-6 shadow" aria-labelledby="edit-entity-heading">
                    <h2 id="edit-entity-heading" class="text-xl font-bold text-neutral-950">{{ __('admin.save') }}</h2>
                    <form method="POST" action="{{ route($routePrefix.'.curriculum-drafts.entities.update', [$draft, $entity]) }}" class="mt-4 grid gap-4 md:grid-cols-2">
                        @csrf @method('PATCH')<input type="hidden" name="draft_revision" value="{{ $draft->revision }}"><input type="hidden" name="entity_revision" value="{{ $entity->revision }}">
                        @if(in_array($entity->entity_type, ['chapter', 'lesson-section'], true))
                            <label class="block text-sm font-semibold">{{ __('admin.title') }}<input name="title" required maxlength="240" value="{{ $entity->payload['title'] }}" class="mt-1 block w-full rounded-md border-neutral-300"></label>
                        @endif
                        @if($entity->entity_type === 'lesson-section')
                            <label class="block text-sm font-semibold">{{ __('admin.parent_module') }}<select name="parent_code" required class="mt-1 block w-full rounded-md border-neutral-300">@foreach($chapters as $chapter)<option value="{{ $chapter->code }}" @selected($chapter->code === $entity->parent_code)>{{ $chapter->code }} &middot; {{ $chapter->payload['title'] }}</option>@endforeach</select></label>
                        @endif
                        <label class="block text-sm font-semibold">{{ __('admin.position') }}<input name="position" type="number" min="1" max="999" required value="{{ $entity->position }}" class="mt-1 block w-full rounded-md border-neutral-300"></label>
                        @if($entity->entity_type === 'outcome')
                            <label class="block text-sm font-semibold">{{ __('admin.parent_module') }}<input name="module" type="number" min="1" max="7" required value="{{ $entity->payload['module'] }}" class="mt-1 block w-full rounded-md border-neutral-300"></label>
                            <label class="block text-sm font-semibold md:col-span-2">{{ __('admin.statement') }}<textarea name="statement" required maxlength="2000" rows="4" class="mt-1 block w-full rounded-md border-neutral-300">{{ $entity->payload['statement'] }}</textarea></label>
                            <label class="block text-sm font-semibold">{{ __('admin.outcome_type') }}<select name="outcome_type" required class="mt-1 block w-full rounded-md border-neutral-300">@foreach(['knowledge','performance','reflection'] as $type)<option value="{{ $type }}" @selected($entity->payload['type'] === $type)>{{ $type }}</option>@endforeach</select></label>
                            <label class="block text-sm font-semibold">{{ __('admin.provisional_band') }}<input name="provisional_band" required maxlength="30" value="{{ $entity->payload['provisional_band'] }}" class="mt-1 block w-full rounded-md border-neutral-300"></label>
                        @endif
                        <div class="md:col-span-2"><button class="rounded-md bg-indigo-700 px-5 py-2 font-semibold text-white">{{ __('admin.save') }}</button></div>
                    </form>

                    <div class="mt-6 flex flex-wrap gap-4 border-t border-neutral-200 pt-5">
                        <form method="POST" action="{{ route($routePrefix.'.curriculum-drafts.entities.move', [$draft, $entity]) }}" class="flex items-end gap-2">@csrf<input type="hidden" name="draft_revision" value="{{ $draft->revision }}"><input type="hidden" name="entity_revision" value="{{ $entity->revision }}"><label class="text-sm font-semibold">{{ __('admin.move_to_position') }}<input name="position" type="number" min="1" max="999" required value="{{ $entity->position }}" class="mt-1 block w-28 rounded-md border-neutral-300"></label><button class="rounded-md border border-indigo-600 px-4 py-2 font-semibold text-indigo-800">{{ __('admin.save') }}</button></form>
                        <form method="POST" action="{{ route($routePrefix.'.curriculum-drafts.entities.'.($entity->archived_at ? 'restore' : 'archive'), [$draft, $entity]) }}" class="self-end">@csrf<input type="hidden" name="draft_revision" value="{{ $draft->revision }}"><input type="hidden" name="entity_revision" value="{{ $entity->revision }}"><button class="rounded-md border border-amber-600 px-4 py-2 font-semibold text-amber-900">{{ $entity->archived_at ? __('admin.restore') : __('admin.archive') }}</button></form>
                    </div>
                </section>
            @else
                <div class="mt-6 rounded-lg border border-neutral-300 bg-white p-4 text-neutral-700">{{ __('admin.workspace_is_read_only') }}</div>
            @endif

            @if($entity->entity_type === 'lesson-section')
                <section class="mt-6 rounded-xl bg-white p-6 shadow" aria-labelledby="blocks-heading">
                    <h2 id="blocks-heading" class="text-xl font-bold text-neutral-950">{{ __('admin.typed_blocks') }}</h2>
                    <p class="mt-2 text-sm text-neutral-600">{{ __('admin.block_payload_help') }}</p>

                    @if($editable && !$entity->archived_at)
                        <div class="mt-5 space-y-3">@foreach($blockTypes as $type)<details class="rounded-lg border border-neutral-200 p-4"><summary class="cursor-pointer font-semibold text-indigo-800">{{ __('admin.add_block') }}: {{ $type->value }}</summary><form method="POST" action="{{ route($routePrefix.'.curriculum-drafts.blocks.store', [$draft, $entity]) }}" class="mt-4 grid gap-4">@csrf<input type="hidden" name="draft_revision" value="{{ $draft->revision }}"><input type="hidden" name="entity_revision" value="{{ $entity->revision }}"><input type="hidden" name="block_type" value="{{ $type->value }}">@include('superadmin.curriculum-drafts.partials.block-fields', ['type' => $type->value, 'payload' => [], 'selectedAssetId' => null])<button class="justify-self-start rounded-md bg-indigo-700 px-5 py-2 font-semibold text-white">{{ __('admin.add_block') }}</button></form></details>@endforeach</div>
                    @endif

                    <ol class="mt-6 space-y-4">
                        @foreach($entity->blocks as $block)
                            @php($editablePayload = collect($block->payload)->except(['id','type','order','language','provenance_kind','source_locator'])->all())
                            <li class="rounded-lg border {{ $block->archived_at ? 'border-amber-300 bg-amber-50' : 'border-neutral-200' }} p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2"><p class="font-semibold"><span class="font-mono text-indigo-700">{{ $block->position }}.</span> {{ $block->block_type }}</p><span class="font-mono text-xs text-neutral-500">{{ $block->block_uuid }} &middot; r{{ $block->revision }}</span></div>
                                @if($editable)
                                    <form method="POST" action="{{ route($routePrefix.'.curriculum-drafts.blocks.update', [$draft, $block]) }}" class="mt-3 grid gap-4">@csrf @method('PATCH')<input type="hidden" name="draft_revision" value="{{ $draft->revision }}"><input type="hidden" name="block_revision" value="{{ $block->revision }}">@include('superadmin.curriculum-drafts.partials.block-fields', ['type' => $block->block_type, 'payload' => $editablePayload, 'selectedAssetId' => $block->asset?->public_id])<button class="justify-self-start rounded-md bg-indigo-700 px-4 py-2 font-semibold text-white">{{ __('admin.save') }}</button></form>
                                    <div class="mt-3 flex flex-wrap gap-3">
                                        <form method="POST" action="{{ route($routePrefix.'.curriculum-drafts.blocks.move', [$draft, $block]) }}" class="flex items-end gap-2">@csrf<input type="hidden" name="draft_revision" value="{{ $draft->revision }}"><input type="hidden" name="block_revision" value="{{ $block->revision }}"><label class="text-sm font-semibold">{{ __('admin.position') }}<input name="position" type="number" min="1" max="999" value="{{ $block->position }}" class="mt-1 block w-24 rounded-md border-neutral-300"></label><button class="rounded-md border border-indigo-600 px-3 py-2 font-semibold text-indigo-800">{{ __('admin.save') }}</button></form>
                                        <form method="POST" action="{{ route($routePrefix.'.curriculum-drafts.blocks.'.($block->archived_at ? 'restore' : 'archive'), [$draft, $block]) }}" class="self-end">@csrf<input type="hidden" name="draft_revision" value="{{ $draft->revision }}"><input type="hidden" name="block_revision" value="{{ $block->revision }}"><button class="rounded-md border border-amber-600 px-3 py-2 font-semibold text-amber-900">{{ $block->archived_at ? __('admin.restore') : __('admin.archive') }}</button></form>
                                    </div>
                                @else
                                    <pre class="mt-3 overflow-auto whitespace-pre-wrap rounded-md bg-neutral-950 p-3 text-xs text-neutral-100">{{ json_encode($editablePayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif
        </main>
    </div>
@endsection

@extends('layouts.app')

@section('title'){{ $draft->title }} - {{ __('admin.canonical_drafts') }}@endsection
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @php($legacyCurriculumReadOnly = true)
    @php($editable = $draft->status === \App\Enums\CurriculumDraftStatus::Draft)
    <div class="flex min-h-screen flex-col bg-neutral-100 md:flex-row">
        @include('superadmin.sidebar')

        <main class="min-w-0 flex-1 p-6 md:p-10">
            <a href="{{ route($routePrefix.'.curriculum-drafts.index') }}" class="font-semibold text-indigo-700 underline">&larr; {{ __('admin.canonical_drafts') }}</a>
            <header class="mt-4 flex flex-wrap items-start justify-between gap-4 rounded-xl bg-white p-6 shadow">
                <div>
                    <div class="flex flex-wrap gap-2"><span class="rounded-full bg-indigo-100 px-3 py-1 font-semibold text-indigo-800">{{ __('admin.'.$draft->status->value) }}</span><span class="rounded-full bg-neutral-100 px-3 py-1 font-mono text-neutral-700">{{ $draft->content_version }}</span></div>
                    <h1 class="mt-3 text-3xl font-bold text-neutral-950">{{ $draft->title }}</h1>
                    <p class="mt-2 font-mono text-xs text-neutral-500">{{ $draft->public_id }} &middot; {{ __('admin.revision') }} {{ $draft->revision }}</p>
                    <p class="mt-2 text-sm text-neutral-600">{{ __('admin.base_version') }}: {{ $draft->basePackage?->content_version ?? __('admin.not_available') }}</p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="{{ route($routePrefix.'.curriculum-drafts.exercises.index', $draft) }}" class="rounded-md bg-indigo-700 px-5 py-2 font-semibold text-white hover:bg-indigo-800">{{ __('admin.exercise_builder') }}</a>
                    <a href="{{ route($routePrefix.'.curriculum-drafts.preview.index', $draft) }}" class="rounded-md border border-indigo-700 bg-white px-5 py-2 font-semibold text-indigo-800 hover:bg-indigo-50">{{ __('admin.preview_draft') }}</a>
                </div>
            </header>

            @foreach(['success' => 'border-green-300 bg-green-50 text-green-950', 'warning' => 'border-amber-300 bg-amber-50 text-amber-950'] as $key => $style)
                @if(session($key))<div class="mt-5 rounded-lg border p-4 {{ $style }}" role="status">{{ session($key) }}</div>@endif
            @endforeach
            @if($errors->any())
                <div class="mt-5 rounded-lg border border-red-300 bg-red-50 p-4 text-red-950" role="alert"><ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
            @endif

            <section class="mt-6 rounded-xl bg-white p-6 shadow" aria-labelledby="lifecycle-actions-heading">
                <h2 id="lifecycle-actions-heading" class="text-xl font-bold text-neutral-950">{{ __('admin.status') }}</h2>
                <div class="mt-4 flex flex-wrap gap-4">
                    @if($editable)
                        <form method="POST" action="{{ route($routePrefix.'.curriculum-drafts.validate', $draft) }}">@csrf<input type="hidden" name="draft_revision" value="{{ $draft->revision }}"><button class="rounded-md bg-indigo-700 px-5 py-2 font-semibold text-white hover:bg-indigo-800">{{ __('admin.validate_and_submit') }}</button></form>
                    @elseif(in_array($draft->status, [\App\Enums\CurriculumDraftStatus::InReview, \App\Enums\CurriculumDraftStatus::Approved], true))
                        <form method="POST" action="{{ route($routePrefix.'.curriculum-drafts.request-changes', $draft) }}" class="flex flex-wrap items-end gap-3">@csrf<input type="hidden" name="draft_revision" value="{{ $draft->revision }}"><label class="text-sm font-semibold">{{ __('admin.review_reason') }}<input name="reason" required minlength="10" maxlength="1000" class="mt-1 block rounded-md border-neutral-300"></label><button class="rounded-md border border-amber-600 px-5 py-2 font-semibold text-amber-900">{{ __('admin.request_changes') }}</button></form>
                    @endif

                    @if(Auth::user()->isSuperAdmin() && $draft->status === \App\Enums\CurriculumDraftStatus::InReview)
                        <form method="POST" action="{{ route('superadmin.curriculum-drafts.approve', $draft) }}" class="flex flex-wrap items-end gap-3">@csrf<input type="hidden" name="draft_revision" value="{{ $draft->revision }}"><label class="text-sm font-semibold">{{ __('admin.review_reason') }}<input name="reason" required minlength="10" maxlength="1000" class="mt-1 block rounded-md border-neutral-300"></label><button class="rounded-md bg-emerald-700 px-5 py-2 font-semibold text-white">{{ __('admin.approve_draft') }}</button></form>
                    @endif
                    @if(Auth::user()->isSuperAdmin() && $draft->status === \App\Enums\CurriculumDraftStatus::Approved)
                        <form method="POST" action="{{ route('superadmin.curriculum-drafts.publish', $draft) }}">@csrf<input type="hidden" name="draft_revision" value="{{ $draft->revision }}"><button class="rounded-md bg-red-700 px-5 py-2 font-semibold text-white">{{ __('admin.publish_version') }}</button></form>
                    @endif
                    @if(Auth::user()->isSuperAdmin() && $draft->status === \App\Enums\CurriculumDraftStatus::Published && $draft->publication_run_id && $draft->publishedPackage?->is_active)
                        <form method="POST" action="{{ route('superadmin.curriculum-drafts.rollback', $draft) }}">@csrf<input type="hidden" name="draft_revision" value="{{ $draft->revision }}"><button class="rounded-md border border-red-700 px-5 py-2 font-semibold text-red-800">{{ __('admin.rollback_publication') }}</button></form>
                    @endif
                </div>
                @unless($editable)<p class="mt-4 text-sm text-neutral-600">{{ __('admin.workspace_is_read_only') }}</p>@endunless
            </section>

            @if($editable)
                <section class="mt-6 grid gap-5 xl:grid-cols-3" aria-label="{{ __('admin.content_management') }}">
                    @foreach([
                        ['chapter', 'create_chapter', ['title' => true]],
                        ['lesson-section', 'create_section', ['title' => true, 'parent' => true]],
                        ['outcome', 'create_outcome', ['outcome' => true]],
                    ] as [$type, $heading, $fields])
                        <form method="POST" action="{{ route($routePrefix.'.curriculum-drafts.entities.store', $draft) }}" class="rounded-xl bg-white p-5 shadow">
                            @csrf<input type="hidden" name="draft_revision" value="{{ $draft->revision }}"><input type="hidden" name="entity_type" value="{{ $type }}">
                            <h2 class="text-lg font-bold text-neutral-950">{{ __('admin.'.$heading) }}</h2>
                            <label class="mt-3 block text-sm font-semibold">{{ __('admin.code') }}<input name="code" required maxlength="120" pattern="[A-Z0-9]+(?:-[A-Z0-9]+)*" class="mt-1 block w-full rounded-md border-neutral-300"></label>
                            @if($fields['title'] ?? false)<label class="mt-3 block text-sm font-semibold">{{ __('admin.title') }}<input name="title" required maxlength="240" class="mt-1 block w-full rounded-md border-neutral-300"></label>@endif
                            @if($fields['parent'] ?? false)<label class="mt-3 block text-sm font-semibold">{{ __('admin.parent_module') }}<select name="parent_code" required class="mt-1 block w-full rounded-md border-neutral-300">@foreach($chapters->whereNull('archived_at') as $chapter)<option value="{{ $chapter->code }}">{{ $chapter->code }} &middot; {{ $chapter->payload['title'] }}</option>@endforeach</select></label>@endif
                            <label class="mt-3 block text-sm font-semibold">{{ __('admin.position') }}<input name="position" type="number" min="1" max="999" required value="1" class="mt-1 block w-full rounded-md border-neutral-300"></label>
                            @if($fields['outcome'] ?? false)
                                <label class="mt-3 block text-sm font-semibold">{{ __('admin.parent_module') }}<input name="module" type="number" min="1" max="7" required value="1" class="mt-1 block w-full rounded-md border-neutral-300"></label>
                                <label class="mt-3 block text-sm font-semibold">{{ __('admin.statement') }}<textarea name="statement" required maxlength="2000" rows="3" class="mt-1 block w-full rounded-md border-neutral-300"></textarea></label>
                                <label class="mt-3 block text-sm font-semibold">{{ __('admin.outcome_type') }}<select name="outcome_type" required class="mt-1 block w-full rounded-md border-neutral-300"><option value="knowledge">knowledge</option><option value="performance">performance</option><option value="reflection">reflection</option></select></label>
                                <label class="mt-3 block text-sm font-semibold">{{ __('admin.provisional_band') }}<input name="provisional_band" required maxlength="30" value="local" class="mt-1 block w-full rounded-md border-neutral-300"></label>
                            @endif
                            <button class="mt-4 rounded-md bg-indigo-700 px-4 py-2 font-semibold text-white">{{ __('admin.save') }}</button>
                        </form>
                    @endforeach
                </section>
            @endif

            <div class="mt-6 grid gap-6 xl:grid-cols-2">
                <section class="rounded-xl bg-white p-6 shadow" aria-labelledby="docx-import-heading">
                    <h2 id="docx-import-heading" class="text-xl font-bold text-neutral-950">{{ __('admin.docx_import') }}</h2>
                    <p class="mt-2 text-sm text-neutral-600">{{ __('admin.docx_import_help') }}</p>
                    @if($editable)
                        <form method="POST" enctype="multipart/form-data" action="{{ route($routePrefix.'.curriculum-drafts.imports.store', $draft) }}" class="mt-4 space-y-3">
                            @csrf<input type="hidden" name="draft_revision" value="{{ $draft->revision }}">
                            <label class="block text-sm font-semibold">{{ __('admin.docx_source') }}<input type="file" name="source" required accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" class="mt-1 block w-full rounded-md border border-neutral-300 p-2"></label>
                            <label class="block text-sm font-semibold">{{ __('admin.declared_purpose') }}<textarea name="declared_purpose" required minlength="10" maxlength="500" rows="3" class="mt-1 block w-full rounded-md border-neutral-300">{{ old('declared_purpose') }}</textarea></label>
                            <button class="rounded-md bg-indigo-700 px-4 py-2 font-semibold text-white">{{ __('admin.queue_private_dry_run') }}</button>
                        </form>
                    @endif
                    <ol class="mt-5 space-y-3">
                        @forelse($draft->imports as $import)
                            <li class="rounded-lg border border-neutral-200 p-4">
                                <div class="flex flex-wrap items-center justify-between gap-2"><span class="font-semibold">{{ $import->source_original_name }}</span><span class="rounded-full bg-neutral-100 px-2 py-1 font-mono text-xs">{{ $import->status->value }}</span></div>
                                <p class="mt-2 break-all font-mono text-xs text-neutral-600">SHA-256 {{ $import->source_sha256 }} &middot; {{ number_format($import->source_bytes) }} bytes &middot; r{{ $import->revision }}</p>
                                @if($import->diff_report)
                                    <div class="mt-3 flex flex-wrap gap-2">@foreach($import->diff_report['summary'] as $change => $count)<span class="rounded-full bg-indigo-50 px-2 py-1 text-xs font-semibold text-indigo-900">{{ $change }}: {{ $count }}</span>@endforeach</div>
                                @endif
                                @if($import->error_report)<p class="mt-3 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-950">{{ $import->error_report['message'] }}</p>@endif
                                @if($editable && $import->status === \App\Enums\CurriculumImportStatus::Ready)
                                    <form method="POST" action="{{ route($routePrefix.'.curriculum-drafts.imports.accept', [$draft, $import]) }}" class="mt-4 flex flex-wrap items-end gap-3">
                                        @csrf<input type="hidden" name="draft_revision" value="{{ $draft->revision }}"><input type="hidden" name="import_revision" value="{{ $import->revision }}">
                                        <label class="text-sm font-semibold">{{ __('admin.replace_draft_confirmation') }}<input name="confirmation" required autocomplete="off" class="mt-1 block rounded-md border-neutral-300" placeholder="REPLACE DRAFT"></label>
                                        <button class="rounded-md border border-red-700 px-4 py-2 font-semibold text-red-800">{{ __('admin.accept_import_into_draft') }}</button>
                                    </form>
                                @endif
                            </li>
                        @empty
                            <li class="text-sm text-neutral-500">{{ __('admin.no_docx_imports') }}</li>
                        @endforelse
                    </ol>
                </section>

                <section class="rounded-xl bg-white p-6 shadow" aria-labelledby="asset-library-heading">
                    <h2 id="asset-library-heading" class="text-xl font-bold text-neutral-950">{{ __('admin.asset_library') }}</h2>
                    <p class="mt-2 text-sm text-neutral-600">{{ __('admin.asset_library_help') }}</p>
                    @if($editable)
                        <form method="POST" enctype="multipart/form-data" action="{{ route($routePrefix.'.curriculum-drafts.assets.store', $draft) }}" class="mt-4 grid gap-3">
                            @csrf<input type="hidden" name="draft_revision" value="{{ $draft->revision }}">
                            <label class="text-sm font-semibold">{{ __('admin.asset_file') }}<input type="file" name="asset" required accept=".jpg,.jpeg,.png,.webp,.mp3,.wav,image/jpeg,image/png,image/webp,audio/mpeg,audio/wav" class="mt-1 block w-full rounded-md border border-neutral-300 p-2"></label>
                            <label class="text-sm font-semibold">{{ __('admin.display_name') }}<input name="display_name" required minlength="2" maxlength="160" class="mt-1 block w-full rounded-md border-neutral-300"></label>
                            <label class="text-sm font-semibold">{{ __('admin.accessibility_text') }}<textarea name="accessibility_text" required minlength="3" maxlength="2000" rows="2" class="mt-1 block w-full rounded-md border-neutral-300"></textarea></label>
                            <label class="text-sm font-semibold">{{ __('admin.rights_basis') }}<textarea name="rights_basis" required minlength="3" maxlength="2000" rows="2" class="mt-1 block w-full rounded-md border-neutral-300"></textarea></label>
                            <label class="text-sm font-semibold">{{ __('admin.source_url_optional') }}<input name="source_url" type="url" maxlength="2048" class="mt-1 block w-full rounded-md border-neutral-300"></label>
                            <button class="justify-self-start rounded-md bg-indigo-700 px-4 py-2 font-semibold text-white">{{ __('admin.upload_private_asset') }}</button>
                        </form>
                    @endif
                    <ul class="mt-5 space-y-3">
                        @forelse($draft->assets as $asset)
                            <li class="rounded-lg border border-neutral-200 p-3"><div class="flex flex-wrap items-center justify-between gap-2"><a class="font-semibold text-indigo-700 underline" href="{{ route($routePrefix.'.curriculum-drafts.assets.show', [$draft, $asset]) }}" target="_blank" rel="noopener">{{ $asset->display_name }}</a><span class="text-xs font-semibold">{{ $asset->kind->value }}</span></div><p class="mt-1 text-sm text-neutral-700">{{ $asset->accessibility_text }}</p><p class="mt-1 break-all font-mono text-xs text-neutral-500">{{ $asset->blob->sha256 }} &middot; {{ number_format($asset->blob->bytes) }} bytes</p></li>
                        @empty<li class="text-sm text-neutral-500">{{ __('admin.no_assets') }}</li>@endforelse
                    </ul>
                </section>
            </div>

            <section class="mt-6 rounded-xl bg-white p-6 shadow" aria-labelledby="draft-content-heading">
                <h2 id="draft-content-heading" class="text-xl font-bold text-neutral-950">{{ __('admin.canonical_content_counts') }}</h2>
                <div class="mt-5 space-y-5">
                    @foreach($chapters as $chapter)
                        <article class="rounded-lg border {{ $chapter->archived_at ? 'border-amber-300 bg-amber-50' : 'border-neutral-200' }} p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3"><div><span class="font-mono text-xs text-indigo-700">{{ $chapter->code }}</span><h3 class="text-lg font-bold text-neutral-950">{{ $chapter->payload['module'] ?? $chapter->position }}. {{ $chapter->payload['title'] }}</h3></div><a href="{{ route($routePrefix.'.curriculum-drafts.entities.edit', [$draft, $chapter]) }}" class="font-semibold text-indigo-700 underline">{{ __('admin.edit_module') }}</a></div>
                            <ul class="mt-3 grid gap-2 md:grid-cols-2">
                                @forelse(($sectionsByChapter[$chapter->code] ?? collect())->sortBy('position') as $section)
                                    <li class="rounded-md border border-neutral-200 bg-white p-3"><a href="{{ route($routePrefix.'.curriculum-drafts.entities.edit', [$draft, $section]) }}" class="font-semibold text-indigo-700 underline">{{ $section->position }}. {{ $section->payload['title'] }}</a><span class="mt-1 block font-mono text-xs text-neutral-500">{{ $section->code }} &middot; {{ $section->available_blocks_count }}/{{ $section->blocks_count }} {{ __('admin.typed_blocks') }}@if($section->archived_at) &middot; {{ __('admin.archived') }}@endif</span></li>
                                @empty<li class="text-sm text-neutral-500">{{ __('admin.no_lessons') }}</li>@endforelse
                            </ul>
                        </article>
                    @endforeach
                </div>
                <h3 class="mt-7 text-lg font-bold text-neutral-950">{{ __('admin.create_outcome') }}</h3>
                <ul class="mt-3 grid gap-2 lg:grid-cols-2">
                    @foreach($outcomes as $outcome)<li class="rounded-md border border-neutral-200 p-3"><a href="{{ route($routePrefix.'.curriculum-drafts.entities.edit', [$draft, $outcome]) }}" class="font-mono font-semibold text-indigo-700 underline">{{ $outcome->code }}</a><p class="mt-1 text-sm text-neutral-700">{{ $outcome->payload['statement'] }}</p>@if($outcome->archived_at)<span class="text-xs font-semibold text-amber-800">{{ __('admin.archived') }}</span>@endif</li>@endforeach
                </ul>
            </section>

            <div class="mt-6 grid gap-6 xl:grid-cols-2">
                <section class="rounded-xl bg-white p-6 shadow"><h2 class="text-xl font-bold">{{ __('admin.validation_report') }}</h2>@if($draft->validation_report)<pre class="mt-4 max-h-96 overflow-auto whitespace-pre-wrap rounded-lg bg-neutral-950 p-4 text-xs text-neutral-100">{{ json_encode($draft->validation_report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>@else<p class="mt-3 text-neutral-600">{{ __('admin.not_available') }}</p>@endif</section>
                <section class="rounded-xl bg-white p-6 shadow"><h2 class="text-xl font-bold">{{ __('admin.source_aware_diff') }}</h2>@if($draft->diff_report)<div class="mt-3 flex flex-wrap gap-2">@foreach($draft->diff_report['summary'] as $change => $count)<span class="rounded-full bg-neutral-100 px-3 py-1 text-sm font-semibold">{{ $change }}: {{ $count }}</span>@endforeach</div><ul class="mt-4 max-h-80 space-y-2 overflow-auto text-sm">@foreach($draft->diff_report['items'] as $item)@if($item['change'] !== 'unchanged')<li><span class="font-semibold">{{ $item['change'] }}</span> &middot; <span class="font-mono">{{ $item['code'] ?? $item['source_path'] }}</span> &middot; {{ $item['entity_type'] }}</li>@endif @endforeach</ul>@else<p class="mt-3 text-neutral-600">{{ __('admin.not_available') }}</p>@endif</section>
            </div>

            <section class="mt-6 rounded-xl bg-white p-6 shadow"><h2 class="text-xl font-bold">{{ __('admin.lifecycle_events') }}</h2><div class="mt-4 overflow-x-auto"><table class="min-w-full text-left text-sm"><thead><tr class="border-b"><th class="p-2">{{ __('admin.event') }}</th><th class="p-2">{{ __('admin.status') }}</th><th class="p-2">{{ __('admin.actor') }}</th><th class="p-2">{{ __('admin.revision') }}</th><th class="p-2">{{ __('admin.reason') }}</th><th class="p-2">{{ __('admin.time') }}</th></tr></thead><tbody class="divide-y">@foreach($draft->events as $event)<tr><td class="p-2">{{ str_replace('_', ' ', $event->event_type) }}</td><td class="p-2">{{ $event->from_status }}@if($event->to_status) &rarr; {{ $event->to_status }}@endif</td><td class="p-2">{{ $event->actor?->name ?? __('admin.not_available') }}</td><td class="p-2">{{ $event->revision }}</td><td class="p-2">{{ $event->reason }}</td><td class="p-2">{{ $event->created_at?->format('Y-m-d H:i:s') }}</td></tr>@endforeach</tbody></table></div></section>
        </main>
    </div>
@endsection

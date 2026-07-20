@extends('layouts.app')

@section('title', __('admin.canonical_drafts_title'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @php($legacyCurriculumReadOnly = true)
    <div class="flex min-h-screen flex-col bg-neutral-100 md:flex-row">
        @include('superadmin.sidebar')

        <main class="min-w-0 flex-1 p-6 md:p-10">
            <header class="mb-8">
                <h1 class="text-3xl font-bold text-neutral-950">{{ __('admin.canonical_drafts') }}</h1>
                <p class="mt-2 max-w-4xl text-neutral-600">{{ __('admin.canonical_drafts_description') }}</p>
            </header>

            @if($errors->any())
                <div class="mb-5 rounded-lg border border-red-300 bg-red-50 p-4 text-red-950" role="alert">
                    <ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <section class="mb-8 rounded-xl bg-white p-6 shadow" aria-labelledby="new-draft-heading">
                <h2 id="new-draft-heading" class="text-xl font-bold text-neutral-950">{{ __('admin.new_draft') }}</h2>
                <form method="POST" action="{{ route($routePrefix.'.curriculum-drafts.store') }}" class="mt-5 grid gap-4 lg:grid-cols-3">
                    @csrf
                    <label class="block text-sm font-semibold text-neutral-800">{{ __('admin.draft_title') }}
                        <input name="title" required maxlength="160" value="{{ old('title') }}" class="mt-1 block w-full rounded-md border-neutral-300">
                    </label>
                    <label class="block text-sm font-semibold text-neutral-800">{{ __('admin.content_version') }}
                        <input name="content_version" required maxlength="50" pattern="\d+\.\d+\.\d+" value="{{ old('content_version') }}" placeholder="0.4.1" class="mt-1 block w-full rounded-md border-neutral-300">
                        <span class="mt-1 block font-normal text-neutral-600">{{ __('admin.content_version_help') }}</span>
                    </label>
                    <fieldset class="text-sm text-neutral-800">
                        <legend class="font-semibold">{{ __('admin.base_version') }}</legend>
                        <label class="mt-2 flex gap-2"><input type="radio" name="source" value="clone" class="shrink-0" @checked(old('source', 'clone') === 'clone')> <span>{{ __('admin.clone_active') }}</span></label>
                        <label class="mt-2 flex gap-2"><input type="radio" name="source" value="empty" class="shrink-0" @checked(old('source') === 'empty')> <span>{{ __('admin.start_empty') }}</span></label>
                    </fieldset>
                    <div class="lg:col-span-3"><button class="rounded-md bg-indigo-700 px-5 py-2 font-semibold text-white hover:bg-indigo-800">{{ __('admin.create_draft') }}</button></div>
                </form>
            </section>

            <div class="overflow-x-auto rounded-xl bg-white shadow">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-neutral-50 text-xs uppercase text-neutral-600"><tr><th class="p-4">{{ __('admin.draft_title') }}</th><th class="p-4">{{ __('admin.status') }}</th><th class="p-4">{{ __('admin.base_version') }}</th><th class="p-4">{{ __('admin.content_version') }}</th><th class="p-4">{{ __('admin.revision') }}</th><th class="p-4">{{ __('admin.updated') }}</th><th class="p-4">{{ __('admin.actions') }}</th></tr></thead>
                    <tbody class="divide-y divide-neutral-200">
                        @forelse($drafts as $draft)
                            <tr>
                                <th scope="row" class="p-4 font-semibold text-neutral-950">{{ $draft->title }}<span class="mt-1 block font-mono text-xs font-normal text-neutral-500">{{ $draft->public_id }}</span></th>
                                <td class="p-4"><span class="rounded-full bg-indigo-100 px-3 py-1 font-semibold text-indigo-800">{{ __('admin.'.$draft->status->value) }}</span></td>
                                <td class="p-4">{{ $draft->basePackage?->content_version ?? __('admin.not_available') }}</td>
                                <td class="p-4 font-mono">{{ $draft->content_version }}</td>
                                <td class="p-4">{{ $draft->revision }}</td>
                                <td class="p-4">{{ $draft->updated_at?->format('Y-m-d H:i') }}</td>
                                <td class="p-4"><a href="{{ route($routePrefix.'.curriculum-drafts.show', $draft) }}" class="font-semibold text-indigo-700 underline">{{ __('admin.open_draft') }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="p-8 text-center text-neutral-600">{{ __('admin.no_canonical_drafts') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-5">{{ $drafts->links() }}</div>
        </main>
    </div>
@endsection

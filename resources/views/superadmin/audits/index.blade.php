@extends('layouts.app')

@section('title', __('admin.administration_audit_title'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    <main class="container mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
        <header class="mb-8">
                <h1 class="text-3xl font-bold text-neutral-950">{{ __('admin.administration_audit') }}</h1>
                <p class="mt-2 max-w-4xl text-neutral-700">{{ __('admin.administration_audit_description') }}</p>
            </header>

            <aside class="mb-6 rounded-lg border border-blue-300 bg-blue-50 p-4 text-blue-950" aria-labelledby="audit-boundary-title">
                <h2 id="audit-boundary-title" class="font-bold">{{ __('admin.audit_privacy_boundary') }}</h2>
                <p class="mt-1 text-sm">{{ __('admin.audit_privacy_boundary_description') }}</p>
            </aside>

            @if($errors->any())
                <div class="mb-5 rounded-lg border border-red-300 bg-red-50 p-4 text-red-950" role="alert">
                    <ul class="list-disc space-y-1 pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <form method="GET" action="{{ route('superadmin.audit.index') }}" class="mb-6 grid gap-4 rounded-xl bg-white p-5 shadow md:grid-cols-2 xl:grid-cols-5" aria-label="{{ __('admin.filter_audit') }}">
                <div>
                    <label for="audit-category" class="block text-sm font-semibold text-neutral-800">{{ __('admin.audit_category') }}</label>
                    <select id="audit-category" name="category" class="mt-1 block w-full rounded-md border-neutral-300">
                        <option value="">{{ __('admin.all_audit_categories') }}</option>
                        <option value="identity" @selected(($filters['category'] ?? '') === 'identity')>{{ __('admin.audit_categories.identity') }}</option>
                        <option value="curriculum" @selected(($filters['category'] ?? '') === 'curriculum')>{{ __('admin.audit_categories.curriculum') }}</option>
                    </select>
                </div>
                <div>
                    <label for="audit-event" class="block text-sm font-semibold text-neutral-800">{{ __('admin.audit_event') }}</label>
                    <select id="audit-event" name="event" class="mt-1 block w-full rounded-md border-neutral-300">
                        <option value="">{{ __('admin.all_audit_events') }}</option>
                        @foreach($eventOptions as $event)<option value="{{ $event }}" @selected(($filters['event'] ?? '') === $event)>{{ $event }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label for="audit-actor" class="block text-sm font-semibold text-neutral-800">{{ __('admin.audit_actor') }}</label>
                    <input id="audit-actor" name="actor" type="search" maxlength="100" value="{{ $filters['actor'] ?? '' }}" class="mt-1 block w-full rounded-md border-neutral-300">
                </div>
                <div>
                    <label for="audit-from" class="block text-sm font-semibold text-neutral-800">{{ __('admin.from_date') }}</label>
                    <input id="audit-from" name="from" type="date" value="{{ $filters['from'] ?? '' }}" class="mt-1 block w-full rounded-md border-neutral-300">
                </div>
                <div>
                    <label for="audit-to" class="block text-sm font-semibold text-neutral-800">{{ __('admin.to_date') }}</label>
                    <input id="audit-to" name="to" type="date" value="{{ $filters['to'] ?? '' }}" class="mt-1 block w-full rounded-md border-neutral-300">
                </div>
                <div class="flex flex-wrap items-end gap-3 md:col-span-2 xl:col-span-5">
                    <button type="submit" class="rounded-md bg-indigo-700 px-5 py-2 font-semibold text-white hover:bg-indigo-800">{{ __('admin.apply_filters') }}</button>
                    <a href="{{ route('superadmin.audit.index') }}" class="rounded-md border border-neutral-300 px-5 py-2 font-semibold text-neutral-800 hover:bg-neutral-50">{{ __('admin.clear_filters') }}</a>
                </div>
            </form>

            <div class="overflow-x-auto rounded-xl bg-white shadow">
                <table class="w-full min-w-[980px] text-left text-sm">
                    <caption class="sr-only">{{ __('admin.administration_audit_table') }}</caption>
                    <thead class="bg-neutral-50 text-xs uppercase text-neutral-600">
                        <tr>
                            <th scope="col" class="p-4">{{ __('admin.occurred_at') }}</th>
                            <th scope="col" class="p-4">{{ __('admin.audit_event') }}</th>
                            <th scope="col" class="p-4">{{ __('admin.audit_actor') }}</th>
                            <th scope="col" class="p-4">{{ __('admin.audit_subject') }}</th>
                            <th scope="col" class="p-4">{{ __('admin.audit_transition') }}</th>
                            <th scope="col" class="p-4">{{ __('admin.audit_details') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200">
                        @forelse($entries as $entry)
                            <tr class="align-top">
                                <td class="p-4 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($entry['occurred_at'])->format('Y-m-d H:i:s') }}</td>
                                <td class="p-4">
                                    <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $entry['category'] === 'identity' ? 'bg-purple-100 text-purple-900' : 'bg-indigo-100 text-indigo-900' }}">{{ __('admin.audit_categories.'.$entry['category']) }}</span>
                                    <code class="mt-2 block text-xs text-neutral-800">{{ $entry['event'] }}</code>
                                </td>
                                <td class="p-4">{{ $entry['actor']?->name ?? __('admin.system_or_deleted_actor') }}@if($entry['actor'])<span class="block text-xs text-neutral-500">#{{ $entry['actor']->id }}</span>@endif</td>
                                <td class="p-4">
                                    @if($entry['subject_type'] === 'user')
                                        {{ $entry['target']?->name ?? __('admin.deleted_or_unavailable_user') }}
                                    @elseif($entry['subject'])
                                        <a href="{{ route('superadmin.curriculum-drafts.show', $entry['subject']) }}" class="font-semibold text-indigo-700 underline">{{ $entry['subject']->title }}</a>
                                        <span class="block font-mono text-xs text-neutral-500">{{ $entry['subject']->content_version }}</span>
                                    @else
                                        {{ __('admin.deleted_or_unavailable_draft') }}
                                    @endif
                                </td>
                                <td class="p-4">@if($entry['from_state'] || $entry['to_state'])<span class="font-mono text-xs">{{ $entry['from_state'] ?? __('admin.not_available') }} &rarr; {{ $entry['to_state'] ?? __('admin.not_available') }}</span>@else<span class="text-neutral-500">{{ __('admin.not_available') }}</span>@endif</td>
                                <td class="p-4">
                                    @if($entry['reason'])<p class="max-w-md whitespace-pre-line text-neutral-800">{{ $entry['reason'] }}</p>@endif
                                    @if($entry['metadata'] || $entry['ip_address'] || $entry['user_agent'])
                                        <details class="mt-2 max-w-lg">
                                            <summary class="cursor-pointer font-semibold text-indigo-700">{{ __('admin.show_audit_metadata') }}</summary>
                                            <dl class="mt-2 space-y-1 rounded-md bg-neutral-50 p-3 text-xs">
                                                @foreach($entry['metadata'] ?? [] as $key => $value)
                                                    <div><dt class="inline font-semibold">{{ $key }}:</dt> <dd class="inline break-all">{{ is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : var_export($value, true) }}</dd></div>
                                                @endforeach
                                                @if($entry['ip_address'])<div><dt class="inline font-semibold">{{ __('admin.ip_address') }}:</dt> <dd class="inline">{{ $entry['ip_address'] }}</dd></div>@endif
                                                @if($entry['user_agent'])<div><dt class="inline font-semibold">{{ __('admin.user_agent') }}:</dt> <dd class="inline break-all">{{ $entry['user_agent'] }}</dd></div>@endif
                                            </dl>
                                        </details>
                                    @elseif(! $entry['reason'])
                                        <span class="text-neutral-500">{{ __('admin.no_additional_audit_details') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-8 text-center text-neutral-600">{{ __('admin.no_audit_events') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            <div class="mt-5">{{ $entries->links() }}</div>
        </main>
@endsection

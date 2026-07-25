@extends('layouts.app')

@section('title', __('admin.canonical_exercises_title'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    <main class="container mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
        <header class="mb-8">
                <h1 class="text-3xl font-bold text-neutral-950">{{ __('admin.canonical_exercises') }}</h1>
                <p class="mt-2 max-w-4xl text-neutral-700">{{ __('admin.canonical_exercises_description') }}</p>
            </header>

            <div class="overflow-x-auto rounded-xl bg-white shadow">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <caption class="sr-only">{{ __('admin.canonical_exercise_workspaces_table') }}</caption>
                    <thead class="bg-neutral-50 text-xs uppercase text-neutral-600">
                        <tr>
                            <th scope="col" class="p-4">{{ __('admin.draft_title') }}</th>
                            <th scope="col" class="p-4">{{ __('admin.status') }}</th>
                            <th scope="col" class="p-4">{{ __('admin.content_version') }}</th>
                            <th scope="col" class="p-4">{{ __('admin.draft_exercises') }}</th>
                            <th scope="col" class="p-4">{{ __('admin.updated') }}</th>
                            <th scope="col" class="p-4">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200">
                        @forelse($drafts as $draft)
                            <tr>
                                <th scope="row" class="p-4 font-semibold text-neutral-950">{{ $draft->title }}<span class="mt-1 block font-mono text-xs font-normal text-neutral-500">{{ $draft->public_id }}</span></th>
                                <td class="p-4">{{ __('admin.'.$draft->status->value) }}</td>
                                <td class="p-4 font-mono">{{ $draft->content_version }}</td>
                                <td class="p-4 tabular-nums">{{ $draft->exercise_count }}</td>
                                <td class="p-4">{{ $draft->updated_at?->format('Y-m-d H:i') }}</td>
                                <td class="p-4"><a href="{{ route($routePrefix.'.curriculum-drafts.exercises.index', $draft) }}" class="font-semibold text-indigo-700 underline">{{ __('admin.manage_canonical_exercises') }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="p-8 text-center text-neutral-600">{{ __('admin.no_exercise_workspaces') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-5">{{ $drafts->links() }}</div>
        </main>
@endsection

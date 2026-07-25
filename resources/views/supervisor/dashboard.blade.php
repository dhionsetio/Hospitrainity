@extends('layouts.app')

@section('title', __('Supervisor Dashboard - Hospitrainity'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    <main class="container mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
        <header class="mb-8">
                <h1 class="text-3xl font-bold text-neutral-800">{{ __('admin.team_progress') }}</h1>
                <p class="text-neutral-500">{{ __('admin.monitor_institution_progress') }}</p>
                @include('partials.next-action', ['nextAction' => $nextAction])
            </header>

            <section class="rounded-lg bg-white shadow-md" aria-labelledby="learner-progress-heading">
                <h2 id="learner-progress-heading" class="p-5 text-xl font-bold text-neutral-900">{{ __('Learner progress') }}</h2>
                <div class="space-y-3 px-4 pb-4 md:hidden">
                    @forelse($users as $user)
                        <article class="rounded-lg border border-neutral-300 p-4">
                            <h3 class="font-bold text-neutral-950">{{ $user->name }}</h3>
                            <p class="mt-1 text-sm text-neutral-700">{{ $user->email }}</p>
                            <div class="mt-4 flex items-center gap-3">
                                <progress class="hsp-progress hsp-progress-blue" value="{{ $user->overall_progress }}" max="100" aria-label="{{ __('Progress for :name', ['name' => $user->name]) }}">{{ $user->overall_progress }}%</progress>
                                <span class="font-semibold">{{ $user->overall_progress }}%</span>
                            </div>
                            <a href="{{ route('supervisor.progress.learners.show', $user) }}" class="mt-4 inline-flex min-h-11 items-center rounded-lg border border-indigo-700 px-4 py-2 font-semibold text-indigo-800">{{ __('admin.view_progress_detail') }}</a>
                        </article>
                    @empty
                        <p class="rounded-lg border border-dashed border-neutral-400 p-6 text-center text-neutral-600">{{ __('admin.no_institution_users') }}</p>
                    @endforelse
                </div>
                <div class="hidden overflow-x-auto md:block">
                <table class="w-full text-sm text-left text-neutral-600">
                    <caption class="sr-only">{{ __('admin.institution_learner_progress_table') }}</caption>
                    <thead class="text-xs text-neutral-500 uppercase">
                        <tr class="border-b">
                            <th scope="col" class="px-6 py-3">{{ __('admin.user_name') }}</th>
                            <th scope="col" class="px-6 py-3">{{ __('Email address') }}</th>
                            <th scope="col" class="px-6 py-3">{{ __('admin.overall_progress') }}</th>
                            <th scope="col" class="px-6 py-3">{{ __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($users as $user)
                        <tr>
                            <td class="px-6 py-4 font-semibold text-neutral-900">{{ $user->name }}</td>
                            <td class="px-6 py-4">{{ $user->email }}</td>
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-4">
                                    <progress class="hsp-progress hsp-progress-blue" value="{{ $user->overall_progress }}" max="100" aria-label="{{ __('Progress for :name', ['name' => $user->name]) }}">{{ $user->overall_progress }}%</progress>
                                    <span class="font-medium text-neutral-700">{{ $user->overall_progress }}%</span>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('supervisor.progress.learners.show', $user) }}" class="font-semibold text-indigo-700 hover:text-indigo-900">
                                    {{ __('admin.view_progress_detail') }}
                                </a>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-6 py-4 text-center">{{ __('admin.no_institution_users') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
                <div class="p-4">{{ $users->links() }}</div>
            </section>
        </main>
@endsection

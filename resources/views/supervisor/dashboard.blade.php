@extends('layouts.app')

@section('title', __('Supervisor Dashboard - Hospitrainity'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    <div class="flex min-h-screen flex-col bg-neutral-100 md:flex-row">
        @include('supervisor.sidebar')

        <!-- Main Content -->
        <main class="min-w-0 flex-1 p-6 md:p-10">
            <header class="mb-8">
                <h1 class="text-3xl font-bold text-neutral-800">{{ __('admin.team_progress') }}</h1>
                <p class="text-neutral-500">{{ __('admin.monitor_institution_progress') }}</p>
            </header>

            <div class="bg-white rounded-lg shadow-md overflow-x-auto">
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
                <div class="p-4">{{ $users->links() }}</div>
            </div>
        </main>
    </div>
@endsection

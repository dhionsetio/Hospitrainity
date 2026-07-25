@extends('layouts.app')

@section('title', __('Institution staff roles'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    <main class="container mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8 space-y-8">
        <header>
                <h1 class="text-3xl font-bold text-neutral-900">{{ __('Institution staff roles') }}</h1>
                <p class="mt-2 max-w-3xl text-neutral-600">{{ __('Institution Admins may manage Instructor access in this institution. Only System Admin may manage Institution Admin access.') }}</p>
            </header>

            @if(session('status'))
                <div class="rounded-md border border-green-300 bg-green-50 px-4 py-3 text-green-950" role="status">{{ session('status') }}</div>
            @endif

            @if($institutions->count() > 1)
                <form method="POST" action="{{ route($routePrefix.'.invitations.institution') }}" class="max-w-xl rounded-lg bg-white p-5 shadow">
                    @csrf
                    <label for="role-institution" class="block text-sm font-medium text-neutral-700">{{ __('Active institution') }}</label>
                    <div class="mt-2 flex gap-3">
                        <select id="role-institution" name="institution_id" class="min-w-0 flex-1 rounded-md border border-neutral-300 bg-white px-3 py-2">
                            @foreach($institutions as $option)
                                <option value="{{ $option->id }}" @selected($option->is($institution))>{{ $option->displayName(app()->getLocale()) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="rounded-md border border-indigo-600 px-4 py-2 font-semibold text-indigo-700">{{ __('Switch') }}</button>
                    </div>
                </form>
            @endif

            <section class="overflow-x-auto rounded-lg bg-white shadow" aria-labelledby="institution-roles-heading">
                <h2 id="institution-roles-heading" class="p-6 text-xl font-bold text-neutral-900">{{ $institution->displayName(app()->getLocale()) }}</h2>
                <table class="w-full text-left text-sm text-neutral-700">
                    <thead class="border-y bg-neutral-50 text-xs uppercase text-neutral-600">
                        <tr>
                            <th class="px-6 py-3">{{ __('Member') }}</th>
                            <th class="px-6 py-3">{{ __('Current staff roles') }}</th>
                            <th class="px-6 py-3">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @forelse($members as $member)
                            @php
                                $activeRoles = $member->roleAssignments->pluck('role')->map(fn ($role) => $role->value);
                                $isSelf = $member->user_id === auth()->id();
                            @endphp
                            <tr>
                                <td class="px-6 py-4">
                                    <span class="block font-semibold text-neutral-900">{{ $member->user->name }}</span>
                                    <span class="text-neutral-600">{{ $member->user->email }}</span>
                                </td>
                                <td class="px-6 py-4">
                                    {{ $activeRoles->contains('institution_admin') ? __('Institution Admin') : '' }}
                                    @if($activeRoles->contains('institution_admin') && $activeRoles->contains('instructor'))<span aria-hidden="true">, </span>@endif
                                    {{ $activeRoles->contains('instructor') ? __('Instructor') : '' }}
                                    @unless($activeRoles->contains('institution_admin') || $activeRoles->contains('instructor'))
                                        <span class="text-neutral-500">{{ __('No staff role') }}</span>
                                    @endunless
                                </td>
                                <td class="px-6 py-4">
                                    @if($isSelf)
                                        <span class="text-neutral-500">{{ __('Self-management is disabled') }}</span>
                                    @else
                                        <div class="flex flex-wrap gap-3">
                                            <form method="POST" action="{{ route($routePrefix.'.institution-roles.update', $member) }}">
                                                @csrf @method('PATCH')
                                                <input type="hidden" name="role" value="instructor">
                                                <input type="hidden" name="action" value="{{ $activeRoles->contains('instructor') ? 'revoke' : 'grant' }}">
                                                <button type="submit" class="font-semibold {{ $activeRoles->contains('instructor') ? 'text-red-700 hover:text-red-900' : 'text-indigo-700 hover:text-indigo-900' }}">
                                                    {{ $activeRoles->contains('instructor') ? __('Revoke Instructor') : __('Grant Instructor') }}
                                                </button>
                                            </form>
                                            @if($isSystemAdmin)
                                                <form method="POST" action="{{ route($routePrefix.'.institution-roles.update', $member) }}">
                                                    @csrf @method('PATCH')
                                                    <input type="hidden" name="role" value="institution_admin">
                                                    <input type="hidden" name="action" value="{{ $activeRoles->contains('institution_admin') ? 'revoke' : 'grant' }}">
                                                    <button type="submit" class="font-semibold {{ $activeRoles->contains('institution_admin') ? 'text-red-700 hover:text-red-900' : 'text-indigo-700 hover:text-indigo-900' }}">
                                                        {{ $activeRoles->contains('institution_admin') ? __('Revoke Institution Admin') : __('Grant Institution Admin') }}
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-6 py-8 text-center text-neutral-500">{{ __('No active institution members.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </main>
@endsection

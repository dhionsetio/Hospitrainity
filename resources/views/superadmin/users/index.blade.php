@extends('layouts.app')

@section('title', __('admin.user_administration_title'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @php($legacyCurriculumReadOnly = true)
    <div class="flex min-h-screen flex-col bg-neutral-100 md:flex-row">
        @include('superadmin.sidebar')

        <main class="min-w-0 flex-1 p-6 md:p-10">
            <header class="mb-8">
                <h1 class="text-3xl font-bold text-neutral-900">{{ __('admin.user_administration') }}</h1>
                <p class="mt-2 max-w-3xl text-neutral-600">{{ __('admin.user_administration_description') }}</p>
            </header>

            @foreach([
                'success' => 'border-green-300 bg-green-50 text-green-900',
                'warning' => 'border-amber-300 bg-amber-50 text-amber-900',
                'status' => 'border-blue-300 bg-blue-50 text-blue-900',
            ] as $flashKey => $flashStyle)
                @if(session($flashKey))
                    <div class="mb-5 rounded-md border px-4 py-3 {{ $flashStyle }}" role="status">
                        {{ session($flashKey) }}
                    </div>
                @endif
            @endforeach

            @if($errors->any())
                <div class="mb-5 rounded-md border border-red-300 bg-red-50 px-4 py-3 text-red-900" role="alert">
                    <p class="font-semibold">{{ __('admin.role_change_not_saved') }}</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="GET" action="{{ route('superadmin.users.index') }}" class="mb-6 grid gap-4 rounded-lg bg-white p-5 shadow md:grid-cols-2 2xl:grid-cols-5" aria-label="{{ __('admin.filter_users') }}">
                <div class="2xl:col-span-2">
                    <label for="q" class="block text-sm font-medium text-neutral-700">{{ __('admin.search_users') }}</label>
                    <input id="q" name="q" type="search" maxlength="100" value="{{ $filters['q'] ?? '' }}" placeholder="{{ __('admin.search_users_placeholder') }}"
                        class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div>
                    <label for="role" class="block text-sm font-medium text-neutral-700">{{ __('admin.role') }}</label>
                    <select id="role" name="role" class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('admin.all_roles') }}</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->value }}" @selected(($filters['role'] ?? '') === $role->value)>{{ __('admin.roles.'.$role->value) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="institution" class="block text-sm font-medium text-neutral-700">{{ __('admin.institution') }}</label>
                    <select id="institution" name="institution" class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('admin.all_institutions') }}</option>
                        @foreach($institutions as $institution)
                            <option value="{{ $institution }}" @selected(($filters['institution'] ?? '') === $institution)>{{ $institution }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="verification" class="block text-sm font-medium text-neutral-700">{{ __('admin.verification') }}</label>
                    <select id="verification" name="verification" class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2 focus:border-indigo-500 focus:ring-indigo-500">
                        <option value="">{{ __('admin.all_verification_states') }}</option>
                        <option value="verified" @selected(($filters['verification'] ?? '') === 'verified')>{{ __('admin.verified') }}</option>
                        <option value="unverified" @selected(($filters['verification'] ?? '') === 'unverified')>{{ __('admin.unverified') }}</option>
                    </select>
                </div>
                <div class="flex items-end gap-3 md:col-span-2 2xl:col-span-5">
                    <button type="submit" class="rounded-md bg-indigo-600 px-5 py-2 font-semibold text-white hover:bg-indigo-700">{{ __('admin.apply_filters') }}</button>
                    <a href="{{ route('superadmin.users.index') }}" class="rounded-md border border-neutral-300 px-5 py-2 font-semibold text-neutral-700 hover:bg-neutral-50">{{ __('admin.clear_filters') }}</a>
                </div>
            </form>

            <div class="overflow-x-auto rounded-lg bg-white shadow">
                <table class="w-full min-w-[1050px] text-left text-sm text-neutral-700">
                    <thead class="bg-neutral-50 text-xs uppercase text-neutral-600">
                        <tr>
                            <th scope="col" class="px-5 py-3">{{ __('admin.account') }}</th>
                            <th scope="col" class="px-5 py-3">{{ __('admin.institution') }}</th>
                            <th scope="col" class="px-5 py-3">{{ __('admin.verification') }}</th>
                            <th scope="col" class="px-5 py-3">{{ __('admin.current_role') }}</th>
                            <th scope="col" class="px-5 py-3">{{ __('admin.role_administration') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200">
                        @forelse($users as $managedUser)
                            <tr class="align-top">
                                <td class="px-5 py-4">
                                    <div class="font-semibold text-neutral-900">{{ $managedUser->name }}</div>
                                    <div class="mt-1 text-neutral-600">{{ $managedUser->email }}</div>
                                </td>
                                <td class="px-5 py-4">{{ $managedUser->instansi }}</td>
                                <td class="px-5 py-4">
                                    @if($managedUser->hasVerifiedEmail())
                                        <span class="rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-800">{{ __('admin.verified') }}</span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-900">{{ __('admin.unverified') }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-4">
                                    <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-xs font-semibold text-indigo-800">{{ __('admin.roles.'.$managedUser->role->value) }}</span>
                                </td>
                                <td class="px-5 py-4">
                                    @if(Auth::id() === $managedUser->id)
                                        <p class="max-w-sm text-sm text-neutral-500">{{ __('admin.self_role_change_blocked') }}</p>
                                    @else
                                        <details class="max-w-xl rounded-md border border-neutral-200 p-3">
                                            <summary class="cursor-pointer font-semibold text-indigo-700">{{ __('admin.change_role') }}</summary>
                                            <form method="POST" action="{{ route('superadmin.users.role.update', $managedUser) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="expected_role" value="{{ $managedUser->role->value }}">
                                                <div>
                                                    <label for="role-{{ $managedUser->id }}" class="block text-xs font-semibold text-neutral-700">{{ __('admin.new_role') }}</label>
                                                    <select id="role-{{ $managedUser->id }}" name="role" required class="mt-1 block w-full rounded-md border border-neutral-300 bg-white px-3 py-2">
                                                        @foreach($assignableRoles as $assignableRole)
                                                            <option value="{{ $assignableRole->value }}" @selected($managedUser->role === $assignableRole) @disabled($assignableRole->isElevated() && ! $managedUser->hasVerifiedEmail())>
                                                                {{ __('admin.roles.'.$assignableRole->value) }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <label for="reason-{{ $managedUser->id }}" class="block text-xs font-semibold text-neutral-700">{{ __('admin.reason') }}</label>
                                                    <textarea id="reason-{{ $managedUser->id }}" name="reason" minlength="10" maxlength="500" rows="2" required class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2" placeholder="{{ __('admin.role_change_reason_placeholder') }}"></textarea>
                                                </div>
                                                <div class="sm:col-span-2">
                                                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">{{ __('admin.save_role_change') }}</button>
                                                </div>
                                            </form>
                                        </details>

                                        @if(! $managedUser->isSuperAdmin() && $managedUser->hasVerifiedEmail())
                                            <details class="mt-3 max-w-xl rounded-md border border-red-200 bg-red-50 p-3">
                                                <summary class="cursor-pointer font-semibold text-red-800">{{ __('admin.promote_to_superadmin') }}</summary>
                                                <p class="mt-2 text-xs text-red-800">{{ __('admin.superadmin_promotion_warning') }}</p>
                                                <form method="POST" action="{{ route('superadmin.users.promote-superadmin', $managedUser) }}" class="mt-4 grid gap-3 sm:grid-cols-2">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="expected_role" value="{{ $managedUser->role->value }}">
                                                    <div>
                                                        <label for="confirmation-email-{{ $managedUser->id }}" class="block text-xs font-semibold text-neutral-700">{{ __('admin.type_target_email') }}</label>
                                                        <input id="confirmation-email-{{ $managedUser->id }}" name="confirmation_email" type="email" required autocomplete="off" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2">
                                                    </div>
                                                    <div>
                                                        <label for="confirmation-role-{{ $managedUser->id }}" class="block text-xs font-semibold text-neutral-700">{{ __('admin.type_superadmin') }}</label>
                                                        <input id="confirmation-role-{{ $managedUser->id }}" name="confirmation_role" type="text" required autocomplete="off" class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2" placeholder="superadmin">
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <label for="promotion-reason-{{ $managedUser->id }}" class="block text-xs font-semibold text-neutral-700">{{ __('admin.reason') }}</label>
                                                        <textarea id="promotion-reason-{{ $managedUser->id }}" name="reason" minlength="10" maxlength="500" rows="2" required class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-2" placeholder="{{ __('admin.role_change_reason_placeholder') }}"></textarea>
                                                    </div>
                                                    <div class="sm:col-span-2">
                                                        <button type="submit" class="rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800">{{ __('admin.confirm_superadmin_promotion') }}</button>
                                                    </div>
                                                </form>
                                            </details>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-8 text-center text-neutral-500">{{ __('admin.no_users_match') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-5">{{ $users->links() }}</div>
        </main>
    </div>
@endsection

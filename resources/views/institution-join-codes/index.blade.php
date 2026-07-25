@extends('layouts.app')

@section('title', __('Classroom codes and join requests'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    <main class="container mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8 space-y-8">
        <header>
                <h1 class="text-3xl font-bold text-neutral-900">{{ __('Classroom codes and join requests') }}</h1>
                <p class="mt-2 max-w-3xl text-neutral-600">{{ __('Choose each code duration from one second up to 30 days. Redeeming a code creates a pending learner request and never grants staff authority.') }}</p>
            </header>

            @if(session('status'))
                <div class="rounded-md border border-green-300 bg-green-50 px-4 py-3 text-green-950" role="status">{{ session('status') }}</div>
            @endif
            @if(session('issued_join_code'))
                <div class="rounded-md border border-amber-300 bg-amber-50 p-5 text-amber-950" role="status">
                    <p class="font-bold">{{ __('Copy this code now') }}</p>
                    <code class="mt-2 block break-all text-xl font-bold tracking-wider">{{ session('issued_join_code') }}</code>
                    <p class="mt-2 text-sm">{{ __('This is the only time the full code will be shown.') }}</p>
                </div>
            @endif

            @if($canManageStaff)
                <p>
                    <a href="{{ route($routePrefix.'.institution-roles.index') }}" class="inline-flex rounded-md border border-indigo-600 px-4 py-2 font-semibold text-indigo-700 hover:bg-indigo-50">
                        {{ __('Manage institution staff roles') }}
                    </a>
                </p>
            @endif

            @if($institutions->count() > 1)
                <form method="POST" action="{{ route($routePrefix.'.invitations.institution') }}" class="max-w-xl rounded-lg bg-white p-5 shadow">
                    @csrf
                    <label for="join-code-institution" class="block text-sm font-medium text-neutral-700">{{ __('Active institution') }}</label>
                    <div class="mt-2 flex gap-3">
                        <select id="join-code-institution" name="institution_id" class="min-w-0 flex-1 rounded-md border border-neutral-300 bg-white px-3 py-2">
                            @foreach($institutions as $option)
                                <option value="{{ $option->id }}" @selected($option->is($institution))>{{ $option->displayName(app()->getLocale()) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="rounded-md border border-indigo-600 px-4 py-2 font-semibold text-indigo-700">{{ __('Switch') }}</button>
                    </div>
                </form>
            @endif

            <section class="max-w-xl rounded-lg bg-white p-6 shadow" aria-labelledby="create-code-heading">
                <h2 id="create-code-heading" class="text-xl font-bold text-neutral-900">{{ __('Create a classroom code') }}</h2>
                <form method="POST" action="{{ route($routePrefix.'.join-codes.store') }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <label for="use_limit" class="block text-sm font-medium text-neutral-700">{{ __('Maximum learners') }}</label>
                        <input id="use_limit" name="use_limit" type="number" min="1" max="500" required value="{{ old('use_limit', 100) }}"
                            @error('use_limit') aria-invalid="true" aria-describedby="use-limit-error" @enderror
                            class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-3">
                        @error('use_limit')<p id="use-limit-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                    </div>
                    <fieldset>
                        <legend class="text-sm font-medium text-neutral-700">{{ __('Code duration') }}</legend>
                        <p id="duration-help" class="mt-1 text-sm text-neutral-600">{{ __('Set days, hours, minutes, and seconds. The total must be between one second and 30 days.') }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                            @foreach([
                                'duration_days' => ['label' => __('Days'), 'max' => 30, 'default' => 0],
                                'duration_hours' => ['label' => __('Hours'), 'max' => 23, 'default' => 1],
                                'duration_minutes' => ['label' => __('Minutes'), 'max' => 59, 'default' => 0],
                                'duration_seconds' => ['label' => __('Seconds'), 'max' => 59, 'default' => 0],
                            ] as $field => $definition)
                                <div>
                                    <label for="{{ $field }}" class="block text-sm font-medium text-neutral-700">{{ $definition['label'] }}</label>
                                    <input id="{{ $field }}" name="{{ $field }}" type="number" inputmode="numeric" min="0" max="{{ $definition['max'] }}" required
                                        value="{{ old($field, $definition['default']) }}" aria-describedby="duration-help{{ $errors->has($field) ? ' '.$field.'-error' : '' }}{{ $errors->has('duration') ? ' duration-error' : '' }}"
                                        @error($field) aria-invalid="true" @enderror
                                        class="mt-1 block w-full rounded-md border border-neutral-300 px-3 py-3">
                                    @error($field)<p id="{{ $field }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>@enderror
                                </div>
                            @endforeach
                        </div>
                        @error('duration')<p id="duration-error" class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                    </fieldset>
                    <button type="submit" class="rounded-md bg-indigo-600 px-5 py-3 font-semibold text-white hover:bg-indigo-700">{{ __('Create code') }}</button>
                </form>
            </section>

            <section class="overflow-x-auto rounded-lg bg-white shadow" aria-labelledby="active-codes-heading">
                <h2 id="active-codes-heading" class="p-6 text-xl font-bold text-neutral-900">{{ __('Classroom-code history') }}</h2>
                <table class="w-full text-left text-sm text-neutral-700">
                    <thead class="border-y bg-neutral-50 text-xs uppercase text-neutral-600"><tr><th class="px-6 py-3">{{ __('Code ending') }}</th><th class="px-6 py-3">{{ __('Usage') }}</th><th class="px-6 py-3">{{ __('Expires') }}</th><th class="px-6 py-3">{{ __('Status') }}</th><th class="px-6 py-3">{{ __('Actions') }}</th></tr></thead>
                    <tbody class="divide-y">
                        @forelse($codes as $code)
                            @php($available = $code->isRedeemable())
                            <tr>
                                <td class="px-6 py-4 font-mono">••••-{{ $code->display_suffix }}</td>
                                <td class="px-6 py-4">{{ $code->use_count }} / {{ $code->use_limit }}</td>
                                <td class="px-6 py-4">{{ $code->expires_at->toDayDateTimeString() }}</td>
                                <td class="px-6 py-4">{{ $code->revoked_at ? __('Revoked') : ($code->expires_at->isPast() ? __('Expired') : ($code->use_count >= $code->use_limit ? __('Full') : __('Active'))) }}</td>
                                <td class="px-6 py-4">
                                    @if($available)
                                        <form method="POST" action="{{ route($routePrefix.'.join-codes.destroy', $code) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="font-semibold text-red-700 hover:text-red-900">{{ __('Revoke') }}</button>
                                        </form>
                                    @else
                                        <span class="text-neutral-500">{{ __('None') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-6 py-8 text-center text-neutral-500">{{ __('No classroom codes yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-4">{{ $codes->links() }}</div>
            </section>

            <section class="overflow-x-auto rounded-lg bg-white shadow" aria-labelledby="membership-requests-heading">
                <h2 id="membership-requests-heading" class="p-6 text-xl font-bold text-neutral-900">{{ __('Membership requests') }}</h2>
                <table class="w-full text-left text-sm text-neutral-700">
                    <thead class="border-y bg-neutral-50 text-xs uppercase text-neutral-600"><tr><th class="px-6 py-3">{{ __('Learner') }}</th><th class="px-6 py-3">{{ __('Requested') }}</th><th class="px-6 py-3">{{ __('Status') }}</th><th class="px-6 py-3">{{ __('Actions') }}</th></tr></thead>
                    <tbody class="divide-y">
                        @forelse($requests as $joinRequest)
                            <tr>
                                <td class="px-6 py-4"><span class="block font-semibold text-neutral-900">{{ $joinRequest->user->name }}</span><span class="text-neutral-600">{{ $joinRequest->user->email }}</span></td>
                                <td class="px-6 py-4">{{ $joinRequest->requested_at->toDayDateTimeString() }}</td>
                                <td class="px-6 py-4">{{ __(ucfirst($joinRequest->status->value)) }}</td>
                                <td class="px-6 py-4">
                                    @if($joinRequest->status === \App\Enums\InstitutionJoinRequestStatus::Pending)
                                        <div class="flex flex-wrap gap-3">
                                            <form method="POST" action="{{ route($routePrefix.'.join-requests.update', $joinRequest) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="approve"><button type="submit" class="font-semibold text-green-700 hover:text-green-900">{{ __('Approve') }}</button></form>
                                            <form method="POST" action="{{ route($routePrefix.'.join-requests.update', $joinRequest) }}">@csrf @method('PATCH')<input type="hidden" name="decision" value="reject"><button type="submit" class="font-semibold text-red-700 hover:text-red-900">{{ __('Reject') }}</button></form>
                                        </div>
                                    @else
                                        <span class="text-neutral-500">{{ __('None') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-6 py-8 text-center text-neutral-500">{{ __('No membership requests yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-4">{{ $requests->links() }}</div>
            </section>

        </main>
@endsection

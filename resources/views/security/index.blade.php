@extends('layouts.guest')

@section('title', __('Account security'))

@section('content')
<main id="main-content" class="mx-auto w-full max-w-5xl space-y-8 px-4 py-8 sm:px-6">
    <div>
        <a href="{{ url('/') }}" class="text-sm font-semibold text-indigo-700">&larr; {{ __('Return to Hospitrainity') }}</a>
        <h1 class="mt-3 text-3xl font-bold text-neutral-950">{{ __('Account security') }}</h1>
        <p class="mt-2 text-neutral-700">{{ __('Manage phishing-resistant passkeys, authenticator-app fallback, recovery codes, password, and signed-in sessions.') }}</p>
    </div>

    @if(session('status'))<div role="status" class="rounded-md border border-green-300 bg-green-50 p-4 text-green-950">{{ session('status') }}</div>@endif
    @if(session('warning'))<div role="alert" class="rounded-md border border-amber-300 bg-amber-50 p-4 text-amber-950">{{ session('warning') }}</div>@endif
    @if($errors->any())<div role="alert" class="rounded-md border border-red-300 bg-red-50 p-4 text-red-950"><p class="font-semibold">{{ __('Security change not completed') }}</p><ul class="mt-2 list-disc pl-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    @if(!$passwordFresh)
        <section class="rounded-lg border border-indigo-300 bg-indigo-50 p-5" aria-labelledby="unlock-heading">
            <h2 id="unlock-heading" class="text-xl font-bold text-neutral-950">{{ __('Unlock security changes') }}</h2>
            <p class="mt-2 text-neutral-700">{{ __('Confirm your password before adding or removing authenticators. You can also confirm with an existing passkey.') }}</p>
            <div class="mt-4 flex flex-wrap gap-3">
                <a href="{{ route('security.confirm') }}" class="rounded-md bg-indigo-700 px-4 py-3 font-semibold text-white">{{ __('Confirm password') }}</a>
                @if($passkeys->isNotEmpty())<button type="button" data-passkey-confirm class="rounded-md border border-indigo-700 px-4 py-3 font-semibold text-indigo-800">{{ __('Confirm with passkey') }}</button>@endif
            </div>
        </section>
    @endif

    <p data-passkey-status role="status" aria-live="polite" class="min-h-6 text-sm text-neutral-700"></p>

    <section class="rounded-lg border border-neutral-300 bg-white p-5" aria-labelledby="passkeys-heading">
        <h2 id="passkeys-heading" class="text-xl font-bold text-neutral-950">{{ __('Passkeys and security keys') }}</h2>
        <p class="mt-2 text-neutral-700">{{ __('Recommended. Passkeys use Windows Hello, Touch ID, Face ID, Android screen lock, a password manager, or a compatible hardware security key. Private keys never reach Hospitrainity.') }}</p>
        @if($passwordFresh)
            <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                <label class="flex-1"><span class="block text-sm font-medium">{{ __('Passkey name') }}</span><input data-passkey-name maxlength="100" value="{{ __('My device') }}" class="mt-1 w-full rounded-md border border-neutral-400 px-3 py-3"></label>
                <button type="button" data-passkey-register class="self-end rounded-md bg-indigo-700 px-4 py-3 font-semibold text-white">{{ __('Add passkey') }}</button>
            </div>
        @endif
        <ul class="mt-5 divide-y divide-neutral-200">
            @forelse($passkeys as $passkey)
                <li class="flex flex-wrap items-center justify-between gap-3 py-3"><div><p class="font-semibold">{{ $passkey->name }}</p><p class="text-sm text-neutral-600">{{ $passkey->authenticator ?? __('Passkey') }} &middot; {{ __('Last used') }}: {{ $passkey->last_used_at?->diffForHumans() ?? __('Never') }}</p></div>
                @if($passwordFresh)<form method="POST" action="{{ route('passkey.destroy', $passkey) }}">@csrf @method('DELETE')<button class="rounded-md border border-red-700 px-3 py-2 text-sm font-semibold text-red-800">{{ __('Remove') }}</button></form>@endif</li>
            @empty
                <li class="py-3 text-neutral-700">{{ __('No passkey is registered yet.') }}</li>
            @endforelse
        </ul>
    </section>

    <section class="rounded-lg border border-neutral-300 bg-white p-5" aria-labelledby="totp-heading">
        <h2 id="totp-heading" class="text-xl font-bold text-neutral-950">{{ __('Authenticator app (TOTP fallback)') }}</h2>
        @if(auth()->user()->hasConfirmedTotp())
            <p class="mt-2 text-green-800">{{ __('Active. A six-digit authenticator code is required after password sign-in.') }}</p>
            @if($passwordFresh)
                <div class="mt-4 flex flex-wrap gap-3"><form method="POST" action="{{ route('security.recovery-codes.regenerate') }}">@csrf<button class="rounded-md border border-indigo-700 px-4 py-3 font-semibold text-indigo-800">{{ __('Replace recovery codes') }}</button></form></div>
                <form method="POST" action="{{ route('security.totp.destroy') }}" class="mt-4 max-w-md space-y-3">@csrf @method('DELETE')<label class="block"><span class="font-medium">{{ __('Current authenticator or recovery code') }}</span><input name="code" required autocomplete="one-time-code" class="mt-1 w-full rounded-md border border-neutral-400 px-3 py-3"></label><button class="rounded-md border border-red-700 px-4 py-3 font-semibold text-red-800">{{ __('Disable authenticator app') }}</button></form>
            @endif
        @elseif($totpPending)
            <p class="mt-2 text-amber-900">{{ __('Setup is not active until a valid code is confirmed.') }}</p>
            <p class="mt-3 text-sm">{{ __('Manual setup key') }}: <code class="break-all rounded bg-neutral-100 px-2 py-1">{{ $totpSecret }}</code></p>
            <details class="mt-2"><summary class="cursor-pointer font-medium text-indigo-800">{{ __('Show authenticator URI') }}</summary><code class="mt-2 block break-all rounded bg-neutral-100 p-3 text-sm">{{ $totpUri }}</code></details>
            <form method="POST" action="{{ route('security.totp.confirm') }}" class="mt-4 flex max-w-md flex-col gap-3 sm:flex-row">@csrf<label class="flex-1"><span class="block font-medium">{{ __('Six-digit code') }}</span><input name="code" required inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" class="mt-1 w-full rounded-md border border-neutral-400 px-3 py-3"></label><button class="self-end rounded-md bg-indigo-700 px-4 py-3 font-semibold text-white">{{ __('Confirm setup') }}</button></form>
        @elseif($passwordFresh)
            <p class="mt-2 text-neutral-700">{{ __('Use a standards-compatible authenticator app when a passkey is unavailable.') }}</p>
            <form method="POST" action="{{ route('security.totp.begin') }}" class="mt-4">@csrf<button class="rounded-md border border-indigo-700 px-4 py-3 font-semibold text-indigo-800">{{ __('Set up authenticator app') }}</button></form>
        @else
            <p class="mt-2 text-neutral-700">{{ __('Confirm your password above to start setup.') }}</p>
        @endif

        @if($recoveryCodes !== [])
            <div class="mt-5 rounded-md border-2 border-amber-500 bg-amber-50 p-4" role="status"><h3 class="font-bold">{{ __('Save these one-use recovery codes now') }}</h3><p class="mt-1 text-sm">{{ __('They are shown only once. Each stored copy is hashed, and using one revokes it.') }}</p><ul class="mt-3 grid gap-2 font-mono sm:grid-cols-2">@foreach($recoveryCodes as $code)<li><code>{{ $code }}</code></li>@endforeach</ul></div>
        @endif
    </section>

    <section class="rounded-lg border border-neutral-300 bg-white p-5" aria-labelledby="password-heading">
        <h2 id="password-heading" class="text-xl font-bold text-neutral-950">{{ __('Change password') }}</h2>
        <p class="mt-2 text-neutral-700">{{ __('Use 15–128 characters. Pasting and password managers are supported; composition rules and periodic changes are not required.') }}</p>
        <form method="POST" action="{{ route('security.password.update') }}" class="mt-4 grid max-w-xl gap-4">@csrf @method('PATCH')
            <label><span class="font-medium">{{ __('Current password') }}</span><input type="password" name="current_password" required autocomplete="current-password" class="mt-1 w-full rounded-md border border-neutral-400 px-3 py-3"></label>
            <label><span class="font-medium">{{ __('New password') }}</span><input type="password" name="password" required autocomplete="new-password" minlength="15" maxlength="128" class="mt-1 w-full rounded-md border border-neutral-400 px-3 py-3"></label>
            <label><span class="font-medium">{{ __('Confirm new password') }}</span><input type="password" name="password_confirmation" required autocomplete="new-password" minlength="15" maxlength="128" class="mt-1 w-full rounded-md border border-neutral-400 px-3 py-3"></label>
            <button class="justify-self-start rounded-md bg-indigo-700 px-4 py-3 font-semibold text-white">{{ __('Change password') }}</button>
        </form>
    </section>

    <section class="rounded-lg border border-neutral-300 bg-white p-5" aria-labelledby="sessions-heading">
        <h2 id="sessions-heading" class="text-xl font-bold text-neutral-950">{{ __('Signed-in sessions') }}</h2>
        <p class="mt-2 text-neutral-700">{{ __('Review recent devices and revoke any session you do not recognize.') }}</p>
        <ul class="mt-4 divide-y divide-neutral-200">
            @forelse($sessions as $session)
                <li class="flex flex-wrap items-center justify-between gap-3 py-3"><div><p class="font-semibold">{{ hash_equals($currentSessionId, $session->id) ? __('This session') : __('Other session') }}</p><p class="max-w-2xl break-words text-sm text-neutral-600">{{ \Illuminate\Support\Str::limit($session->user_agent ?: __('Unknown device'), 120) }} &middot; {{ $session->ip_address ?: __('Unknown address') }} &middot; {{ \Carbon\CarbonImmutable::createFromTimestamp($session->last_activity)->diffForHumans() }}</p></div>@unless(hash_equals($currentSessionId, $session->id))<form method="POST" action="{{ route('security.sessions.destroy', $session->id) }}">@csrf @method('DELETE')<button class="rounded-md border border-red-700 px-3 py-2 text-sm font-semibold text-red-800">{{ __('Revoke') }}</button></form>@endunless</li>
            @empty<li class="py-3 text-neutral-700">{{ __('No database-backed session record is available.') }}</li>@endforelse
        </ul>
        <form method="POST" action="{{ route('security.sessions.destroy-others') }}" class="mt-4">@csrf @method('DELETE')<button class="rounded-md border border-red-700 px-4 py-3 font-semibold text-red-800">{{ __('Revoke all other sessions') }}</button></form>
    </section>
</main>
@endsection

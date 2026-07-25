<form class="mt-6 space-y-6" action="{{ route('login') }}" method="POST">
    @csrf
    @error('email')
    <div id="email-error" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
        <span class="block sm:inline">{{ $message }}</span>
    </div>
    @enderror
    <div class="rounded-md shadow-sm -space-y-px flex flex-col gap-4">
        <div>
            <label for="email-address" class="sr-only">{{ __('Email address') }}</label>
            <input id="email-address" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required class="appearance-none rounded-md relative block w-full px-3 py-3 border border-neutral-300 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white placeholder-neutral-500 text-neutral-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="{{ __('Email address') }}" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
        </div>
        <div>
            <label for="password" class="sr-only">{{ __('Password') }}</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required class="appearance-none rounded-md relative block w-full px-3 py-3 border border-neutral-300 dark:border-neutral-700 dark:bg-neutral-800 dark:text-white placeholder-neutral-500 text-neutral-900 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 focus:z-10 sm:text-sm" placeholder="{{ __('Password') }}">
        </div>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center">
            <input id="remember-me" name="remember" type="checkbox" value="1" @checked(old('remember')) class="h-4 w-4 shrink-0 text-indigo-600 focus:ring-indigo-500 border-neutral-300 rounded">
            <label for="remember-me" class="ml-2 block text-sm text-neutral-900 dark:text-neutral-300">
                {{ __('Remember me') }}
            </label>
        </div>

        <div class="text-sm">
            <a href="{{ route('password.request') }}" class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                {{ __('Forgot your password?') }}
            </a>
        </div>
    </div>

    <div class="flex flex-col gap-4">
        <x-button type="submit" variant="primary" class="w-full justify-center py-3">
            {{ __('Sign in') }}
        </x-button>
        <x-button type="button" variant="outline" data-passkey-login hidden class="w-full justify-center py-3">
            {{ __('Sign in with a passkey') }}
        </x-button>
        <p data-passkey-status role="status" aria-live="polite" class="min-h-5 text-center text-sm text-neutral-700 dark:text-neutral-300"></p>
        <p class="text-center text-sm text-neutral-600 dark:text-neutral-400">
            {{ __('Need an account?') }}
            <a href="{{ route('register') }}" class="font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400">
                {{ __('Create account') }}
            </a>
        </p>
    </div>
</form>

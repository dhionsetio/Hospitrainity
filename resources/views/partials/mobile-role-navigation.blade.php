<header class="sticky top-0 z-40 flex w-full items-center justify-between gap-3 border-b border-neutral-300 bg-white px-4 py-3 md:hidden">
    <div class="min-w-0">
        <p class="truncate font-bold text-neutral-950">Hospitrainity</p>
        <p class="truncate text-sm text-neutral-700">{{ $roleLabel }}@if($contextLabel) <span aria-hidden="true">&middot;</span> {{ $contextLabel }}@endif</p>
    </div>
    <button type="button" class="hsp-touch-target inline-flex shrink-0 items-center gap-2 rounded-lg border border-indigo-700 px-3 py-2 font-semibold text-indigo-800" aria-controls="{{ $drawerId }}" data-shell-drawer-open>
        <i class="fas fa-bars" aria-hidden="true"></i>
        <span>{{ __('Menu') }}</span>
    </button>
</header>

<dialog id="{{ $drawerId }}" data-shell-drawer aria-labelledby="{{ $drawerId }}-title">
    <div class="flex min-h-full flex-col p-4">
        <div class="flex items-start justify-between gap-3 border-b border-neutral-200 pb-4">
            <div>
                <h2 id="{{ $drawerId }}-title" class="text-xl font-bold text-neutral-950">{{ __('Navigation') }}</h2>
                <p class="mt-1 text-sm text-neutral-700">{{ $roleLabel }}@if($contextLabel) <span aria-hidden="true">&middot;</span> {{ $contextLabel }}@endif</p>
            </div>
            <button type="button" class="inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg border border-neutral-500" data-shell-drawer-close aria-label="{{ __('Close navigation') }}">
                <i class="fas fa-xmark" aria-hidden="true"></i>
            </button>
        </div>

        <nav class="mt-4" aria-label="{{ $navigationLabel }}">
            <ul class="space-y-2">
                @foreach($navigationItems as $item)
                    <li>
                        <a href="{{ $item['url'] }}" @if($item['active']) aria-current="page" @endif class="flex min-h-11 items-center gap-3 rounded-lg px-4 py-3 {{ $item['active'] ? 'bg-indigo-700 font-semibold text-white' : 'text-neutral-800 hover:bg-neutral-100' }}">
                            <i class="fas {{ $item['icon'] }} fa-fw" aria-hidden="true"></i>
                            <span>{{ $item['label'] }}</span>
                            @if(isset($item['badge']))<span class="ml-auto rounded-full border px-2 py-0.5 text-xs font-semibold">{{ $item['badge'] }}</span>@endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <nav class="mt-auto space-y-2 border-t border-neutral-200 pt-4" aria-label="{{ __('Account and help') }}">
            <a href="{{ route('security.index') }}" class="flex min-h-11 items-center gap-3 rounded-lg px-4 py-3 text-neutral-800 hover:bg-neutral-100"><i class="fas fa-shield-halved fa-fw" aria-hidden="true"></i>{{ __('Account security') }}</a>
            <a href="{{ route('preferences.edit') }}" class="flex min-h-11 items-center gap-3 rounded-lg px-4 py-3 text-neutral-800 hover:bg-neutral-100"><i class="fas fa-universal-access fa-fw" aria-hidden="true"></i>{{ __('Display preferences') }}</a>
            @if($hasAlternativeRole)
                <a href="{{ route('work-context.index') }}" class="flex min-h-11 items-center gap-3 rounded-lg px-4 py-3 text-neutral-800 hover:bg-neutral-100"><i class="fas fa-repeat fa-fw" aria-hidden="true"></i>{{ __('Switch role') }}</a>
            @endif
            <a href="{{ route('policies.show', ['type' => 'support']) }}" class="flex min-h-11 items-center gap-3 rounded-lg px-4 py-3 text-neutral-800 hover:bg-neutral-100"><i class="fas fa-circle-question fa-fw" aria-hidden="true"></i>{{ __('Help') }}</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="flex w-full min-h-11 items-center gap-3 rounded-lg px-4 py-3 text-neutral-800 hover:bg-neutral-100"><i class="fas fa-sign-out-alt fa-fw" aria-hidden="true"></i>{{ __('Logout') }}</button>
            </form>
        </nav>
    </div>
</dialog>

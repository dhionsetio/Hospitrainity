@extends('layouts.app')

@section('title', __('Notifications') . ' - Hospitrainity')
@section('bodyClass', 'bg-neutral-100')

@section('content')
    <main class="container mx-auto max-w-4xl px-4 py-6 sm:px-6 sm:py-8">
        <x-back-control
            :href="route('dashboard')"
            :label="__('Return to dashboard')"
        />

        <div class="mt-4 flex flex-wrap items-center justify-between gap-4 rounded-xl bg-white p-6 shadow-sm">
            <div>
                <h1 class="text-2xl font-bold text-neutral-900">{{ __('Notifications') }}</h1>
                <p class="mt-1 text-sm text-neutral-600">{{ __('Stay updated on content changes, invitations, role updates, and requests.') }}</p>
            </div>
            @if($notifications->total() > 0)
                <form method="POST" action="{{ route('notifications.readAll') }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="inline-flex min-h-11 items-center gap-2 rounded-lg border border-neutral-300 bg-white px-4 py-2 text-xs font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                        <i class="fa-solid fa-check-double" aria-hidden="true"></i>
                        <span>{{ __('Mark all as read') }}</span>
                    </button>
                </form>
            @endif
        </div>

        <div class="mt-6 space-y-3">
            @forelse($notifications as $notification)
                @php
                    $isUnread = $notification->read_at === null;
                    $title = $notification->data['title'] ?? __('Notification');
                    $body = $notification->data['body'] ?? '';
                    $url = $notification->data['url'] ?? null;
                @endphp
                <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border p-4 shadow-sm transition-colors {{ $isUnread ? 'border-indigo-200 bg-indigo-50/40' : 'border-neutral-200 bg-white' }}">
                    <div class="flex-1 min-w-[240px] space-y-1">
                        <div class="flex items-center gap-2">
                            @if($isUnread)
                                <span class="h-2 w-2 rounded-full bg-indigo-600" title="{{ __('Unread') }}"></span>
                            @endif
                            <h2 class="text-sm font-bold text-neutral-900">{{ $title }}</h2>
                            <span class="text-xs text-neutral-500">&middot; {{ $notification->created_at->diffForHumans() }}</span>
                        </div>
                        @if($body)
                            <p class="text-xs text-neutral-700">{{ $body }}</p>
                        @endif
                        @if($url)
                            <a href="{{ $url }}" class="inline-block text-xs font-semibold text-indigo-700 hover:underline mt-1">{{ __('View details') }} &rarr;</a>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        @if($isUnread)
                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="inline-flex min-h-10 items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-3 py-1.5 text-xs font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                                    <i class="fa-solid fa-check text-emerald-600" aria-hidden="true"></i>
                                    <span>{{ __('Mark as read') }}</span>
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('notifications.destroy', $notification->id) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex min-h-10 items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-neutral-600 shadow-sm hover:bg-neutral-50" title="{{ __('Delete notification') }}">
                                <i class="fa-solid fa-trash-can text-neutral-500" aria-hidden="true"></i>
                                <span class="sr-only">{{ __('Delete') }}</span>
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-neutral-200 bg-white p-8 text-center shadow-sm">
                    <i class="fa-solid fa-bell-slash text-3xl text-neutral-400 mb-3" aria-hidden="true"></i>
                    <p class="text-sm font-semibold text-neutral-800">{{ __('No notifications yet') }}</p>
                    <p class="mt-1 text-xs text-neutral-500">{{ __('You will see updates here when content is published or requests arrive.') }}</p>
                </div>
            @endforelse
        </div>

        @if($notifications->hasPages())
            <div class="mt-6">
                {{ $notifications->links() }}
            </div>
        @endif
    </main>
@endsection

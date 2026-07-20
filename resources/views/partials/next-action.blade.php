@if($nextAction)
    <section class="mt-5 rounded-xl border border-indigo-300 bg-indigo-50 p-5" aria-labelledby="next-action-title">
        <p class="text-sm font-semibold uppercase tracking-wide text-indigo-800">{{ $nextAction['eyebrow'] }}</p>
        <h2 id="next-action-title" class="mt-1 text-2xl font-bold text-neutral-950">{{ $nextAction['title'] }}</h2>
        <p class="mt-2 max-w-3xl text-neutral-800">{{ $nextAction['description'] }}</p>
        <a href="{{ $nextAction['url'] }}" class="mt-4 inline-flex min-h-11 items-center rounded-lg bg-indigo-700 px-5 py-3 font-semibold text-white">{{ $nextAction['label'] }}</a>
    </section>
@else
    <section class="mt-5 rounded-xl border border-dashed border-neutral-400 bg-white p-5" aria-labelledby="next-action-empty-title">
        <h2 id="next-action-empty-title" class="text-xl font-bold text-neutral-950">{{ __('No published learning is available yet') }}</h2>
        <p class="mt-2 text-neutral-700">{{ __('There is no authorized learner action to show. Use Help for current access and institution guidance.') }}</p>
        <a href="{{ route('help.index') }}" class="mt-4 inline-flex min-h-11 items-center font-semibold text-indigo-800 underline">{{ __('Open Help') }}</a>
    </section>
@endif

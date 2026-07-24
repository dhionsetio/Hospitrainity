{{-- Dedicated Warm-Up Section Partial --}}
<div class="space-y-6">
    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8">
        <div class="space-y-4">
            <p class="text-sm font-medium text-neutral-600">
                {{ __('Learning step :step of :total_steps', ['step' => $step['number'] ?? $step['index'] ?? 1, 'total_steps' => $step['total'] ?? $step['total_steps'] ?? 1]) }}
                <span class="mx-1">&middot;</span>
                {{ __('Part :part of :total_parts in this step', ['part' => $step['position'] ?? $step['part_index'] ?? 1, 'total_parts' => $step['count'] ?? $step['part_count'] ?? 1]) }}
            </p>

            <div class="flex items-center gap-2" aria-label="{{ __('Step progress') }}">
                @foreach(range(1, $step['count'] ?? $step['part_count'] ?? 1) as $partNum)
                    <div class="flex items-center">
                        <span class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold {{ $partNum === ($step['position'] ?? $step['part_index'] ?? 1) ? 'bg-indigo-600 text-white' : 'bg-neutral-100 text-neutral-600 border border-neutral-300' }}">
                            {{ $partNum }}
                        </span>
                        @if(!$loop->last)
                            <div class="h-0.5 w-6 bg-neutral-200"></div>
                        @endif
                    </div>
                @endforeach
            </div>

            <h1 class="text-3xl font-bold tracking-tight text-neutral-900 sm:text-4xl">{{ $title }}</h1>

            <div class="flex flex-wrap items-center gap-3 pt-2">
                @if(!empty($audioUrl))
                    <button type="button" data-media-play="{{ $audioUrl }}" class="inline-flex min-h-11 items-center gap-2 rounded-md border border-neutral-300 bg-white px-4 py-2.5 text-sm font-semibold text-neutral-700 hover:bg-neutral-50 shadow-sm">
                        <i class="fas fa-volume-high text-indigo-600" aria-hidden="true"></i>
                        <span>{{ __('Listen to this lesson') }}</span>
                    </button>
                @endif

                @if(!empty($activity))
                    <a href="{{ route('curriculum.activities.show', $activity['code']) }}" class="inline-flex min-h-11 items-center gap-2 rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 shadow-sm">
                        <span>{{ __('Start Activity') }}</span>
                        <i class="fas fa-arrow-right text-xs" aria-hidden="true"></i>
                    </a>
                @elseif(!empty($navigation['next']))
                    <a href="{{ route('curriculum.sections.show', $navigation['next']['code']) }}" class="inline-flex min-h-11 items-center gap-2 rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 shadow-sm">
                        <span>{{ __('Next part') }}</span>
                        <i class="fas fa-arrow-right text-xs" aria-hidden="true"></i>
                    </a>
                @endif
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm sm:p-8 space-y-6">
        <p class="text-base text-neutral-700 leading-relaxed">
            {{ __('Every chapter opens with two or three easy, non-technical questions. There are no wrong answers. Their purpose is simply to help you settle in before the practice begins. Try today\'s:') }}
        </p>

        <div class="space-y-4">
            @foreach($blocks as $block)
                @if(($block['type'] ?? '') === 'list_item')
                    <div class="flex items-start gap-3 text-neutral-800">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-indigo-600"></span>
                        <p class="text-base leading-relaxed font-medium">{{ $block['text'] ?? '' }}</p>
                    </div>
                @elseif(($block['type'] ?? '') === 'instruction')
                    <p class="text-base leading-relaxed text-neutral-800 font-medium">{{ $block['text'] ?? '' }}</p>
                @elseif(($block['type'] ?? '') === 'callout')
                    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-amber-950 text-sm">
                        {{ $block['text'] ?? '' }}
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    @if(!empty($navigation['next']))
        <nav class="mt-6 grid gap-3 sm:grid-cols-2" aria-label="{{ __('Lesson section navigation') }}">
            <span></span>
            <a rel="next" href="{{ route('curriculum.sections.show', $navigation['next']['code']) }}" class="hsp-section-nav hsp-section-nav--next">
                <span><span>{{ __('Next part') }}</span>{{ $navigation['next']['title'] }}</span>
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        </nav>
    @endif
</div>

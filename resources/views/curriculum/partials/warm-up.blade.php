{{-- Dedicated Warm-Up Section Partial with Reflection Answering System --}}
@php
    $sectionCode = $sectionCode ?? ($curriculumSection['code'] ?? '');
    $existingReflections = $existingReflections ?? collect();
    $promptCounter = 0;
@endphp

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
                        <span>{{ __('reflections.listen_to_lesson') }}</span>
                    </button>
                @endif

                @if(!empty($activity))
                    <a href="{{ route('curriculum.activities.show', $activity['code']) }}" class="inline-flex min-h-11 items-center gap-2 rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 shadow-sm">
                        <span>{{ __('reflections.start_activity') }}</span>
                        <i class="fas fa-arrow-right text-xs" aria-hidden="true"></i>
                    </a>
                @elseif(!empty($navigation['next']))
                    <a href="{{ route('curriculum.sections.show', $navigation['next']['code']) }}" class="inline-flex min-h-11 items-center gap-2 rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 shadow-sm">
                        <span>{{ __('reflections.next_part') }}</span>
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

        @if(session('status'))
            <div role="status" class="rounded-lg border border-emerald-300 bg-emerald-50 p-4 text-sm font-medium text-emerald-900">
                <i class="fa-solid fa-circle-check text-emerald-600 mr-2" aria-hidden="true"></i>
                {{ session('status') }}
            </div>
        @endif

        <div class="space-y-8">
            @foreach($blocks as $block)
                @if(($block['type'] ?? '') === 'list_item')
                    @php
                        $promptIndex = $promptCounter++;
                        $existingReflection = $existingReflections->get($promptIndex);
                        $isSubmitted = $existingReflection && $existingReflection->state === 'submitted';
                        $promptText = $block['text'] ?? '';
                    @endphp

                    <div class="rounded-lg border border-neutral-200 bg-neutral-50 p-5 space-y-4">
                        <div class="flex items-start gap-3 text-neutral-900">
                            <span class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white">{{ $promptIndex + 1 }}</span>
                            <p class="text-base font-semibold leading-relaxed">{{ $promptText }}</p>
                        </div>

                        @if($isSubmitted)
                            <div class="rounded-md border border-indigo-200 bg-white p-4 space-y-3">
                                <div class="flex items-center justify-between text-xs font-semibold text-indigo-900">
                                    <span class="inline-flex items-center gap-1.5 text-emerald-700">
                                        <i class="fa-solid fa-check-circle" aria-hidden="true"></i>
                                        {{ __('Submitted') }}
                                    </span>
                                    <span>{{ $existingReflection->submitted_at?->diffForHumans() }}</span>
                                </div>
                                @if(!empty($existingReflection->body))
                                    <div class="text-sm text-neutral-800 whitespace-pre-wrap leading-relaxed border-t border-neutral-100 pt-2">
                                        {{ $existingReflection->body }}
                                    </div>
                                @endif
                                @if($existingReflection->attachments->isNotEmpty())
                                    <div class="border-t border-neutral-100 pt-2 space-y-2">
                                        <p class="text-xs font-semibold text-neutral-600">{{ __('Attached Media:') }}</p>
                                        @foreach($existingReflection->attachments as $att)
                                            <div class="flex items-center gap-3 text-sm">
                                                @if(str_starts_with($att->detected_mime, 'audio/'))
                                                    <audio controls class="h-9 w-full max-w-md">
                                                        <source src="{{ route('curriculum.reflections.attachments.show', [$existingReflection->id, $att->id]) }}" type="{{ $att->detected_mime }}">
                                                    </audio>
                                                @elseif(str_starts_with($att->detected_mime, 'video/'))
                                                    <video controls class="max-h-48 rounded border border-neutral-200">
                                                        <source src="{{ route('curriculum.reflections.attachments.show', [$existingReflection->id, $att->id]) }}" type="{{ $att->detected_mime }}">
                                                    </video>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @else
                            <form method="POST" enctype="multipart/form-data" action="{{ route('curriculum.reflections.store', [$sectionCode, $promptIndex]) }}" class="space-y-4" x-data="{ bodyText: '{{ addslashes($existingReflection->body ?? '') }}' }">
                                @csrf
                                <input type="hidden" name="response_key" value="{{ $existingReflection->response_key ?? \Illuminate\Support\Str::uuid() }}">
                                <input type="hidden" name="section_code" value="{{ $sectionCode }}">
                                <input type="hidden" name="prompt_index" value="{{ $promptIndex }}">

                                @if($errors->has('body'))
                                    <div role="alert" class="text-sm text-red-600 font-medium">
                                        {{ $errors->first('body') }}
                                    </div>
                                @endif

                                <div>
                                    <label for="reflection-body-{{ $promptIndex }}" class="block text-sm font-semibold text-neutral-700 mb-1">
                                        {{ __('reflections.type_your_reflection') }}
                                    </label>
                                    <textarea
                                        id="reflection-body-{{ $promptIndex }}"
                                        name="body"
                                        rows="3"
                                        maxlength="6000"
                                        x-model="bodyText"
                                        placeholder="{{ __('Type your reflection here...') }}"
                                        class="block w-full rounded-md border border-neutral-300 bg-white p-3 text-sm text-neutral-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    ></textarea>
                                    <div class="mt-1 flex justify-end text-xs text-neutral-500">
                                        <span x-text="bodyText ? bodyText.length : 0">0</span>/6000
                                    </div>
                                </div>

                                {{-- Voice Recorder Container --}}
                                <div data-reflection-recorder
                                     data-string-recording-started="{{ __('reflections.recording_started') }}"
                                     data-string-recording-finished="{{ __('reflections.recording_finished') }}"
                                     data-string-recording-discarded="{{ __('reflections.recording_discarded') }}"
                                     data-string-mic-unavailable="{{ __('reflections.mic_unavailable') }}"
                                     class="rounded-md border border-neutral-200 bg-white p-4 space-y-3">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div class="flex items-center gap-2">
                                            <button type="button" data-mic-record class="inline-flex min-h-11 items-center gap-2 rounded-md border border-neutral-300 bg-white px-3.5 py-2 text-xs font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                                                <i class="fa-solid fa-microphone text-red-600" aria-hidden="true"></i>
                                                <span>{{ __('reflections.record_voice') }}</span>
                                            </button>
                                            <button type="button" data-mic-stop class="hidden inline-flex min-h-11 items-center gap-2 rounded-md bg-red-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-red-700">
                                                <i class="fa-solid fa-square" aria-hidden="true"></i>
                                                <span>{{ __('reflections.stop_recording') }}</span>
                                            </button>
                                            <button type="button" data-mic-discard class="hidden inline-flex min-h-11 items-center gap-2 rounded-md border border-neutral-300 bg-white px-3 py-2 text-xs font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                                                <i class="fa-solid fa-trash-can text-neutral-500" aria-hidden="true"></i>
                                                <span>{{ __('reflections.discard_recording') }}</span>
                                            </button>
                                        </div>
                                        <div data-mic-status role="status" aria-live="polite" class="text-xs text-neutral-600 font-medium"></div>
                                    </div>

                                    <div data-mic-notice class="hidden text-xs text-amber-800 bg-amber-50 p-2.5 rounded-md border border-amber-200">
                                        {{ __('reflections.mic_unavailable') }}
                                    </div>

                                    <audio data-mic-preview controls class="hidden w-full h-10 mt-2"></audio>
                                    <input type="file" data-mic-file-input name="attachments[]" accept="audio/*,video/*" class="hidden">
                                </div>

                                @if($existingReflection && $existingReflection->attachments->isNotEmpty())
                                    <div class="rounded-md border border-neutral-200 bg-white p-3 space-y-2">
                                        <p class="text-xs font-semibold text-neutral-700">{{ __('Draft Attachments:') }}</p>
                                        @foreach($existingReflection->attachments as $draftAtt)
                                            <div class="flex items-center justify-between text-xs text-neutral-700 bg-neutral-50 p-2 rounded">
                                                <span class="truncate max-w-xs">{{ $draftAtt->original_name }}</span>
                                                <label class="inline-flex items-center gap-1.5 text-red-600 font-semibold cursor-pointer">
                                                    <input type="checkbox" name="remove_attachments[]" class="shrink-0 rounded border-neutral-300 text-red-600 focus:ring-red-500" value="{{ $draftAtt->id }}">
                                                    <span>{{ __('reflections.remove_attachment') }}</span>
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                {{-- File Upload Input --}}
                                <div>
                                    <label for="reflection-attachments-{{ $promptIndex }}" class="block text-sm font-semibold text-neutral-700 mb-1">
                                        {{ __('reflections.attach_files') }}
                                    </label>
                                    <input
                                        id="reflection-attachments-{{ $promptIndex }}"
                                        type="file"
                                        name="attachments[]"
                                        accept="audio/*,video/*"
                                        multiple
                                        class="block w-full text-xs text-neutral-600 border border-neutral-300 rounded-md p-2 bg-white"
                                    >
                                    <p class="mt-1 text-xs text-neutral-500">{{ __('Up to 3 audio/video files, max 50 MB each.') }}</p>
                                </div>

                                <div class="flex items-center gap-3 pt-2">
                                    <button type="submit" name="intent" value="save" class="inline-flex min-h-11 items-center gap-2 rounded-md border border-neutral-300 bg-white px-4 py-2 text-xs font-semibold text-neutral-700 shadow-sm hover:bg-neutral-50">
                                        <i class="fa-solid fa-floppy-disk text-neutral-500" aria-hidden="true"></i>
                                        <span>{{ __('reflections.save_draft') }}</span>
                                    </button>
                                    <button type="submit" name="intent" value="submit" class="inline-flex min-h-11 items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700">
                                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
                                        <span>{{ __('reflections.submit_reflection') }}</span>
                                    </button>
                                </div>
                            </form>
                        @endif
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
                <span><span>{{ __('reflections.next_part') }}</span>{{ $navigation['next']['title'] }}</span>
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
            </a>
        </nav>
    @endif
</div>

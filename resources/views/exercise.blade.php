@extends('layouts.app')

@section('title'){{ $lesson->title }} - {{ __('Exercise Practice - Hospitrainity') }}@endsection
@section('bodyClass', 'bg-neutral-100')

@section('content')
@php
$exerciseI18n = [
    'correct' => __('Correct!'),
    'tryAgain' => __('Try again!'),
    'correctAnswerLabel' => __('Correct answer:'),
    'checkLabel' => __('Check'),
    'retryAnswer' => __('Try Again'),
    'spellingPrompt' => __('Listen and type what you hear.'),
    'spellingPlaceholder' => __('Type here...'),
    'matchingPrompt' => __('Match the corresponding items.'),
    'fillPrompt' => __('Fill in the blank.'),
    'listeningPrompt' => __('Listen and choose the correct answer.'),
    'scramblePrompt' => __('Arrange the following into a correct sentence.'),
    'translationPrompt' => __('Translate the following word:'),
    'fillOptionsPrompt' => __('Choose the right word to complete the sentence.'),
    'fillMultiplePrompt' => __('Complete the following sentence.'),
    'checkAgain' => __('Please check your answers again.'),
    'silentLetterPrompt' => __('Click on a word to find the silent letter.'),
    'pronunciationPrompt' => __('Tap the button to hear an example pronunciation, then say it aloud. Tap again to repeat.'),
    'speakingHint' => __('Listen, imitate, then press "Next".'),
    'soundSortingPrompt' => __('Sort each word into the correct sound group. Tap a word, then tap a group. Tap a word to hear it.'),
    'soundSortingRetry' => __('Some words are still in the wrong group — the red ones need to be moved.'),
    'sequencingPrompt' => __('Arrange the following steps in the correct order.'),
    'sequencingHint' => __('Use the up/down buttons to reorder, then press Check.'),
    'sequencingRetry' => __('Not quite — adjust the rows marked in red.'),
    'notImplemented' => __('This exercise type \':type\' has not been implemented yet.'),
    'noExercises' => __('No exercises for this lesson yet.'),
    'ttsUnsupported' => __('Sorry, your browser does not support the voice feature.'),
    'mediaUnavailable' => __('Audio playback is unavailable in this browser.'),
    'playingAudio' => __('Playing audio...'),
    'audioFallback' => __('The audio file was unavailable. Using browser speech instead.'),
    'audioFinished' => __('Audio finished.'),
    'mediaFailureContinue' => __('Unable to play this audio. You can still answer or continue.'),
    'playSpellingPrompt' => __('Play spelling prompt'),
    'spellingAnswerLabel' => __('Spelling answer'),
    'blankAnswerLabel' => __('Answer for the blank'),
    'blankNumberLabel' => __('Answer for blank :number'),
    'playListeningPrompt' => __('Play listening prompt'),
    'playPronunciationPrompt' => __('Play pronunciation example'),
    'selectSoundWord' => __('Select and listen to :word'),
    'placeInSoundGroup' => __('Place selected word in :group'),
    'moveStepUp' => __('Move step :number up'),
    'moveStepDown' => __('Move step :number down'),
    'contentError' => __('This exercise could not be loaded.'),
    'nextLabel' => __('Next'),
    'doneLabel' => __('Done'),
    'savingProgress' => __('Saving progress...'),
    'progressSaveError' => __('Unable to save your progress. Check your connection and try again.'),
];
@endphp

    <div id="exercises-data" data-i18n='@json($exerciseI18n)' data-exercises='@json($exercises)' data-return-url="{{ route('lessons.show', $lesson) }}" data-progress-url="{{ route('progress.store') }}" class="hidden"></div>

    <main class="flex flex-col md:flex-row h-screen antialiased">
        <aside class="w-full md:w-24 bg-white shadow-lg md:shadow-md flex md:flex-col items-center p-2 md:py-6 no-scrollbar shrink-0">
            <a href="{{ route('lessons.show', $lesson) }}" aria-label="{{ __('Back to Lesson') }}" class="hidden md:block mb-6 text-neutral-500 hover:text-indigo-600" title="{{ __('Back to Lesson') }}"><i aria-hidden="true" class="fas fa-times fa-2x"></i></a>
            <div id="side-navigation" class="flex flex-row md:flex-col items-center gap-2 md:gap-3 w-full overflow-x-auto md:overflow-y-auto no-scrollbar">
                @foreach($exercises as $index => $exercise)
                <button type="button" aria-label="{{ __('Open exercise :number', ['number' => $index + 1]) }}" class="side-nav-item w-10 h-10 md:w-12 md:h-12 flex items-center justify-center rounded-full text-neutral-600 bg-neutral-200 transition-all duration-200 shrink-0" data-index="{{ $index }}">{{ $index + 1 }}</button>
                @endforeach
            </div>
        </aside>

        <div class="flex-1 flex flex-col p-4 md:p-8 overflow-y-auto">
            <div class="flex items-center gap-4 mb-4 md:mb-8">
                <progress id="progress-bar" class="hsp-progress h-4" value="0" max="100" aria-label="{{ __('Exercise progress') }}">0%</progress>
                <a href="{{ route('lessons.show', $lesson) }}" aria-label="{{ __('Back to Lesson') }}" class="md:hidden text-neutral-500 hover:text-indigo-600" title="{{ __('Back to Lesson') }}"><i aria-hidden="true" class="fas fa-times fa-2x"></i></a>
            </div>

            <div class="flex-1 flex flex-col items-center justify-center">
                <h2 id="exercise-title" class="text-xl font-bold text-neutral-700 mb-6"></h2>
                <div id="game-container" class="w-full max-w-lg"></div>
                <div id="exercise-media-status" role="status" aria-live="polite" aria-atomic="true" class="mt-4 min-h-6 text-center text-sm font-medium text-neutral-600"></div>
            </div>

            <div id="progress-save-error" class="hidden my-4 rounded-lg border border-red-300 bg-red-50 p-4 text-red-800" role="alert" aria-live="assertive" tabindex="-1">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p id="progress-save-error-message" class="font-medium"></p>
                    <button id="progress-retry-button" type="button" class="rounded-md bg-red-700 px-4 py-2 font-semibold text-white hover:bg-red-800">
                        {{ __('Retry') }}
                    </button>
                </div>
            </div>

            <div id="footer-container" class="border-t-2 pt-6 mt-8">
                <footer id="feedback-footer" class="border-t-4 transition-colors duration-300 -mt-2 -mx-6 mb-4">
                    <div class="container mx-auto px-6 py-4 flex justify-between items-center">
                        <div id="feedback-text" role="status" aria-live="polite" aria-atomic="true" class="text-lg font-semibold"></div>
                    </div>
                </footer>
                <div class="flex justify-between items-center">
                    <button id="prev-button" type="button" class="py-3 px-4 sm:px-6 bg-white text-neutral-700 rounded-lg shadow font-semibold hover:bg-neutral-50 disabled:opacity-50 text-sm sm:text-base"><i aria-hidden="true" class="fas fa-chevron-left mr-2"></i> {{ __('Previous') }}</button>
                    <p id="item-counter" role="status" aria-live="polite" class="text-neutral-500 font-medium text-xs sm:text-sm"></p>
                    <div class="flex flex-wrap justify-end gap-2">
                        <button id="answer-retry-button" type="button" class="hidden py-3 px-6 bg-white text-indigo-700 border border-indigo-300 rounded-lg shadow font-semibold hover:bg-indigo-50 text-sm sm:text-base">{{ __('Try Again') }}</button>
                        <button id="check-button" type="button" class="py-3 px-8 bg-indigo-600 text-white rounded-lg shadow font-semibold hover:bg-indigo-700 text-sm sm:text-base">{{ __('Check') }}</button>
                        <button id="next-button" type="button" class="hidden py-3 px-8 bg-indigo-600 text-white rounded-lg shadow font-semibold hover:bg-indigo-700 text-sm sm:text-base">{{ __('Next') }} <i aria-hidden="true" class="fas fa-chevron-right ml-2"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    @push('scripts')
        @vite('resources/js/exercises/index.js')
    @endpush
@endsection

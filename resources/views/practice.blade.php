@php
// Ambil semua item dari kategori vocabulary yang dipilih.
$allItems = $vocabulary->items;
@endphp

@extends('layouts.app')

@section('title'){{ $vocabulary->category }} - {{ __('Vocabulary Practice - Hospitrainity') }}@endsection
@section('bodyClass', 'bg-neutral-100')

@section('content')
    <div id="lesson-data"
         data-items='@json($allItems)'
         data-return-url="{{ route('lessons.show', $lesson) }}"
         data-progress-url="{{ route('progress.store') }}"
         class="hidden"></div>

    <main class="flex flex-col md:flex-row h-screen antialiased">
        <!-- Side Navigation (Desktop) / Top Navigation (Mobile) -->
        <aside class="w-full md:w-24 bg-white shadow-lg md:shadow-md flex md:flex-col items-center p-2 md:py-6 no-scrollbar shrink-0">
            <!-- Close button for Desktop -->
            <a href="{{ route('lessons.show', $lesson) }}" class="hidden md:block mb-6 text-neutral-500 hover:text-indigo-600" title="{{ __('Back to Lesson') }}">
                <i class="fas fa-times fa-2x"></i>
            </a>
            <!-- Nav items container -->
            <div id="side-navigation" class="flex flex-row md:flex-col items-center gap-2 md:gap-3 w-full no-scrollbar">
                @foreach($allItems as $index => $item)
                <button type="button" aria-label="{{ __('Open vocabulary item :number', ['number' => $index + 1]) }}" class="side-nav-item w-10 h-10 md:w-12 md:h-12 flex items-center justify-center rounded-full text-neutral-600 bg-neutral-200 transition-all duration-200 shrink-0" data-index="{{ $index }}">
                    {{ $index + 1 }}
                </button>
                @endforeach
            </div>
        </aside>

        <!-- Konten Utama Pelajaran -->
        <div class="flex-1 flex flex-col p-4 md:p-8 overflow-y-auto">
            <!-- Top bar for Mobile with close button and progress -->
            <div class="flex items-center gap-4 mb-4 md:mb-8">
                <progress id="progress-bar" class="hsp-progress h-4" value="0" max="100" aria-label="{{ __('Vocabulary progress') }}">0%</progress>
                <a href="{{ route('lessons.show', $lesson) }}" class="md:hidden text-neutral-500 hover:text-indigo-600" title="{{ __('Back to Lesson') }}">
                    <i class="fas fa-times fa-2x"></i>
                </a>
            </div>

            <!-- Konten Interaktif -->
            <div class="flex-1 flex flex-col items-center justify-center">
                <div class="w-full max-w-2xl text-center">
                    <p class="text-base sm:text-lg text-neutral-500 mb-2">{{ __('Term:') }}</p>

                    <div id="term-container" class="flex items-center justify-center gap-2 sm:gap-4 text-3xl sm:text-4xl md:text-5xl font-bold text-neutral-800">
                        <!-- Konten akan diisi oleh JavaScript -->
                    </div>
                    <div id="media-status" role="status" aria-live="polite" aria-atomic="true" class="mt-3 min-h-6 text-sm font-medium text-neutral-600"></div>

                    <div class="mt-6 sm:mt-8 p-4 sm:p-6 bg-white rounded-lg shadow-inner">
                        <p class="text-base sm:text-lg text-neutral-500 mb-2">{{ __('Details:') }}</p>
                        <p id="item-details" class="text-lg sm:text-xl text-neutral-700"></p>
                    </div>
                </div>
            </div>

            <div id="progress-save-error" class="hidden my-4 rounded-lg border border-red-300 bg-red-50 p-4 text-red-800" role="alert" aria-live="assertive" tabindex="-1">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p id="progress-save-error-message" class="font-medium"></p>
                    <button id="progress-retry-button" type="button" class="rounded-md bg-red-700 px-4 py-2 font-semibold text-white hover:bg-red-800">
                        {{ __('Retry') }}
                    </button>
                </div>
            </div>

            <!-- Tombol Navigasi -->
            <div class="border-t-2 pt-6 mt-8 flex justify-between items-center">
                <button id="prev-button" type="button" class="py-3 px-4 sm:px-6 bg-white text-neutral-700 rounded-lg shadow font-semibold hover:bg-neutral-50 disabled:opacity-50 disabled:cursor-not-allowed text-sm sm:text-base">
                    <i class="fas fa-chevron-left mr-2"></i> {{ __('Previous') }}
                </button>
                <p id="item-counter" class="text-neutral-500 font-medium text-xs sm:text-sm"></p>
                <button id="next-button" type="button" class="py-3 px-4 sm:px-6 bg-indigo-600 text-white rounded-lg shadow font-semibold hover:bg-indigo-700 text-sm sm:text-base">
                    {{ __('Next') }} <i class="fas fa-chevron-right ml-2"></i>
                </button>
            </div>
        </div>
    </main>

    <script nonce="{{ Vite::cspNonce() }}">
        const lessonDataElement = document.getElementById('lesson-data');
        const allItems = JSON.parse(lessonDataElement.dataset.items);
        const returnUrl = lessonDataElement.dataset.returnUrl;
        const progressUrl = lessonDataElement.dataset.progressUrl;
        let currentItemIndex = 0;
        let progressSavePending = false;
        let retryProgressAction = null;
        let mediaController = null;

        const termContainer = document.getElementById('term-container');
        const mediaStatus = document.getElementById('media-status');
        const detailsElement = document.getElementById('item-details');
        const prevButton = document.getElementById('prev-button');
        const nextButton = document.getElementById('next-button');
        const progressBar = document.getElementById('progress-bar');
        const itemCounter = document.getElementById('item-counter');
        const sideNavItems = document.querySelectorAll('.side-nav-item');
        const progressError = document.getElementById('progress-save-error');
        const progressErrorMessage = document.getElementById('progress-save-error-message');
        const progressRetryButton = document.getElementById('progress-retry-button');
        const mainContent = document.querySelector('.flex-1.flex.flex-col.items-center.justify-center');
        function getMediaController() {
            if (!mediaController && window.HospitrainityMedia) {
                mediaController = window.HospitrainityMedia.createMediaController();
            }
            return mediaController;
        }

        function setMediaStatus(message = '', isError = false) {
            mediaStatus.textContent = message;
            mediaStatus.classList.toggle('text-red-700', isError);
            mediaStatus.classList.toggle('text-neutral-600', !isError);
        }

        async function playTerm(button, promptText, audioUrl = null) {
            const controller = getMediaController();
            if (!controller) {
                setMediaStatus('{{ __('Pronunciation audio is unavailable in this browser.') }}', true);
                return;
            }

            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            setMediaStatus('{{ __('Playing pronunciation...') }}');

            try {
                await controller.playPrompt({
                    audioUrl,
                    promptText,
                    onFallback: () => setMediaStatus('{{ __('The recorded media was unavailable. Using browser speech instead.') }}'),
                });
                setMediaStatus('{{ __('Pronunciation finished.') }}');
            } catch (error) {
                if (!window.HospitrainityMedia.isPlaybackCancellation(error)) {
                    console.error('Vocabulary pronunciation failed:', error);
                    setMediaStatus('{{ __('Unable to play pronunciation. You can continue to the next item.') }}', true);
                }
            } finally {
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }
        }

        function renderItem(index) {
            if (index < 0 || index >= allItems.length) return;

            const item = allItems[index];
            getMediaController()?.stop();
            termContainer.replaceChildren();
            setMediaStatus();
            detailsElement.textContent = item.details || '';
            currentItemIndex = index;

            const speakButton = document.createElement('button');
            speakButton.type = 'button';
            speakButton.title = "{{ __('Listen to Pronunciation') }}";
            speakButton.setAttribute('aria-label', `{{ __('Listen to Pronunciation') }}: ${item.term}`);
            speakButton.setAttribute('aria-controls', 'media-status');
            const speakIcon = document.createElement('i');
            speakIcon.className = 'fas fa-volume-up fa-lg';
            speakIcon.setAttribute('aria-hidden', 'true');
            speakButton.appendChild(speakIcon);

            if (item.term.includes(' vs. ')) {
                const words = item.term.split(' vs. ');
                words.forEach((word, wordIndex) => {
                    const wordButton = document.createElement('button');
                    wordButton.type = 'button';
                    wordButton.textContent = word;
                    wordButton.classList.add('clickable-word');
                    wordButton.title = `{{ __('Listen to') }} "${word}"`;
                    wordButton.setAttribute('aria-controls', 'media-status');
                    wordButton.addEventListener('click', () => playTerm(wordButton, word));
                    termContainer.appendChild(wordButton);

                    if (wordIndex < words.length - 1) {
                        const vsSpan = document.createElement('span');
                        vsSpan.textContent = ' vs. ';
                        vsSpan.classList.add('text-neutral-400', 'mx-1', 'sm:mx-2', 'text-2xl', 'sm:text-3xl');
                        termContainer.appendChild(vsSpan);
                    }
                });
            } else {
                const termText = document.createElement('h1');
                termText.textContent = item.term;
                termContainer.appendChild(termText);
            }

            speakButton.addEventListener('click', () => playTerm(speakButton, item.term, item.media_url));
            termContainer.appendChild(speakButton);
            updateUI();
        }

        function setNextButtonLabel(label, showIcon = false) {
            nextButton.replaceChildren(document.createTextNode(label));
            if (showIcon) {
                const nextIcon = document.createElement('i');
                nextIcon.className = 'fas fa-chevron-right ml-2';
                nextIcon.setAttribute('aria-hidden', 'true');
                nextButton.appendChild(nextIcon);
            }
        }

        function updateUI() {
            const progressPercentage = ((currentItemIndex + 1) / allItems.length) * 100;
            progressBar.value = Math.round(progressPercentage);
            progressBar.textContent = `${Math.round(progressPercentage)}%`;
            itemCounter.textContent = `${currentItemIndex + 1} / ${allItems.length}`;
            prevButton.disabled = currentItemIndex === 0 || progressSavePending;
            nextButton.disabled = progressSavePending;

            if (currentItemIndex === allItems.length - 1) {
                setNextButtonLabel('{{ __('Done') }}');
                nextButton.classList.add('bg-green-600', 'hover:bg-green-700');
                nextButton.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
            } else {
                setNextButtonLabel('{{ __('Next') }}', true);
                nextButton.classList.remove('bg-green-600', 'hover:bg-green-700');
                nextButton.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
            }
            sideNavItems.forEach((navItem, idx) => {
                navItem.disabled = progressSavePending;
                navItem.classList.remove('active');
                if (idx === currentItemIndex) {
                    navItem.classList.add('active');
                    navItem.setAttribute('aria-current', 'step');
                    navItem.scrollIntoView({
                        behavior: 'smooth',
                        block: 'nearest',
                        inline: 'center'
                    });
                } else navItem.removeAttribute('aria-current');
            });
        }

        nextButton.addEventListener('click', () => {
            if (progressSavePending) return;
            saveCurrentItem({
                itemId: allItems[currentItemIndex].id,
                nextIndex: currentItemIndex < allItems.length - 1 ? currentItemIndex + 1 : null,
                returnAfterSave: currentItemIndex === allItems.length - 1
            });
        });
        progressRetryButton.addEventListener('click', () => {
            if (retryProgressAction) saveCurrentItem(retryProgressAction);
        });
        prevButton.addEventListener('click', () => {
            if (!progressSavePending && currentItemIndex > 0) renderItem(currentItemIndex - 1);
        });
        sideNavItems.forEach(navItem => {
            navItem.addEventListener('click', () => {
                if (progressSavePending) return;
                const index = parseInt(navItem.dataset.index, 10);
                renderItem(index);
            });
        });

        if (allItems.length > 0) {
            renderItem(0);
        } else {
            const emptyMessage = document.createElement('p');
            emptyMessage.className = 'text-neutral-500 text-xl';
            emptyMessage.textContent = '{{ __('No vocabulary for this category.') }}';
            mainContent.replaceChildren(emptyMessage);
            prevButton.parentElement.classList.add('hidden');
        }

        async function saveCurrentItem(action) {
            if (progressSavePending) return;

            retryProgressAction = action;
            progressSavePending = true;
            progressError.classList.add('hidden');
            updateUI();
            setNextButtonLabel('{{ __('Saving progress...') }}');

            try {
                const progressClient = window.HospitrainityProgress;
                if (!progressClient) throw new Error('Progress client is unavailable.');

                await progressClient.saveProgress({
                    url: progressUrl,
                    items: [action.itemId],
                    type: 'VocabularyItem'
                });

                if (action.returnAfterSave) {
                    window.location.assign(returnUrl);
                    return;
                }

                progressSavePending = false;
                retryProgressAction = null;
                renderItem(action.nextIndex);
            } catch (error) {
                console.error('Failed to save vocabulary progress:', error);
                progressSavePending = false;
                updateUI();
                progressErrorMessage.textContent = '{{ __('Unable to save your progress. Check your connection and try again.') }}';
                progressError.classList.remove('hidden');
                progressError.focus();
            }
        }
    </script>
@endsection

@php
$allItems = $material->items;
@endphp

@extends('layouts.app')

@section('title'){{ $material->type }} - {{ __('Learning Material - Hospitrainity') }}@endsection
@section('bodyClass', 'bg-neutral-100')

@section('content')
    <div id="material-data"
         data-items='@json($allItems)'
         data-type="{{ $material->type }}"
         data-return-url="{{ route('lessons.show', $lesson) }}"
         data-progress-url="{{ route('progress.store') }}"
         class="hidden"></div>

    <main class="flex flex-col md:flex-row h-screen antialiased">
        <!-- Navigasi Samping (Desktop) / Atas (Mobile) -->
        <aside class="w-full md:w-24 bg-white shadow-lg md:shadow-md flex md:flex-col items-center p-2 md:py-6 no-scrollbar shrink-0">
            <a href="{{ route('lessons.show', $lesson) }}" class="hidden md:block mb-6 text-neutral-500 hover:text-indigo-600" title="{{ __('Back to Lesson') }}">
                <i class="fas fa-times fa-2x"></i>
            </a>
            <div id="side-navigation" class="flex flex-row md:flex-col items-center gap-2 md:gap-3 w-full overflow-x-auto md:overflow-y-auto no-scrollbar">
                @foreach($allItems as $index => $item)
                <button type="button" aria-label="{{ __('Open material item :number', ['number' => $index + 1]) }}" class="side-nav-item w-10 h-10 md:w-12 md:h-12 flex items-center justify-center rounded-full text-neutral-600 bg-neutral-200 transition-all duration-200 shrink-0" data-index="{{ $index }}">
                    {{ $index + 1 }}
                </button>
                @endforeach
            </div>
        </aside>

        <!-- Konten Utama Materi -->
        <div class="flex-1 flex flex-col p-4 md:p-8 overflow-y-auto">
            <div class="flex items-center gap-4 mb-4 md:mb-8">
                <progress id="progress-bar" class="hsp-progress h-4" value="0" max="100" aria-label="{{ __('Material progress') }}">0%</progress>
                <a href="{{ route('lessons.show', $lesson) }}" class="md:hidden text-neutral-500 hover:text-indigo-600" title="{{ __('Back to Lesson') }}"><i class="fas fa-times fa-2x"></i></a>
            </div>

            <div class="flex-1 flex flex-col items-center justify-center">
                <div class="w-full max-w-3xl text-center">
                    <h1 id="item-title" class="text-2xl md:text-3xl font-bold text-neutral-800 mb-4"></h1>
                    <div id="media-container" class="my-6"></div>
                    <div id="media-status" role="status" aria-live="polite" aria-atomic="true" class="min-h-6 text-sm font-medium text-neutral-600"></div>
                    <div id="description-container" class="mt-4 p-6 bg-white rounded-lg shadow-inner prose max-w-none">
                        <p id="item-description"></p>
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

            <div class="border-t-2 pt-6 mt-8 flex justify-between items-center">
                <button id="prev-button" type="button" class="py-3 px-4 sm:px-6 bg-white text-neutral-700 rounded-lg shadow font-semibold hover:bg-neutral-50 disabled:opacity-50 text-sm sm:text-base"><i class="fas fa-chevron-left mr-2"></i> {{ __('Previous') }}</button>
                <p id="item-counter" class="text-neutral-500 font-medium text-xs sm:text-sm"></p>
                <button id="next-button" type="button" class="py-3 px-4 sm:px-6 bg-indigo-600 text-white rounded-lg shadow font-semibold hover:bg-indigo-700 text-sm sm:text-base">{{ __('Next') }} <i class="fas fa-chevron-right ml-2"></i></button>
            </div>
        </div>
    </main>

    <script nonce="{{ Vite::cspNonce() }}">
        const dataElement = document.getElementById('material-data');
        const allItems = JSON.parse(dataElement.dataset.items);
        const materialType = dataElement.dataset.type;
        const returnUrl = dataElement.dataset.returnUrl;
        const progressUrl = dataElement.dataset.progressUrl;
        let currentIndex = 0;
        let progressSavePending = false;
        let retryProgressAction = null;
        let mediaController = null;

        const ui = {
            title: document.getElementById('item-title'),
            media: document.getElementById('media-container'),
            mediaStatus: document.getElementById('media-status'),
            description: document.getElementById('item-description'),
            prevBtn: document.getElementById('prev-button'),
            nextBtn: document.getElementById('next-button'),
            progressBar: document.getElementById('progress-bar'),
            counter: document.getElementById('item-counter'),
            sideNav: document.querySelectorAll('.side-nav-item'),
            mainContent: document.querySelector('.flex-1.flex.flex-col.items-center.justify-center'),
            footer: document.querySelector('.border-t-2.pt-6.mt-8'),
            progressError: document.getElementById('progress-save-error'),
            progressErrorMessage: document.getElementById('progress-save-error-message'),
            progressRetryButton: document.getElementById('progress-retry-button')
        };

        function getMediaController() {
            if (!mediaController && window.HospitrainityMedia) {
                mediaController = window.HospitrainityMedia.createMediaController();
            }
            return mediaController;
        }

        function setMediaStatus(message = '', isError = false) {
            ui.mediaStatus.textContent = message;
            ui.mediaStatus.classList.toggle('text-red-700', isError);
            ui.mediaStatus.classList.toggle('text-neutral-600', !isError);
        }

        async function playPrompt(button, { audioUrl = null, promptText = '' } = {}) {
            const controller = getMediaController();
            if (!controller) {
                setMediaStatus('{{ __('Audio playback is unavailable in this browser.') }}', true);
                return;
            }

            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
            setMediaStatus('{{ __('Playing audio...') }}');

            try {
                await controller.playPrompt({
                    audioUrl,
                    promptText,
                    onFallback: () => setMediaStatus('{{ __('The audio file was unavailable. Using browser speech instead.') }}'),
                });
                setMediaStatus('{{ __('Audio finished.') }}');
            } catch (error) {
                if (!window.HospitrainityMedia.isPlaybackCancellation(error)) {
                    console.error('Material media playback failed:', error);
                    setMediaStatus('{{ __('Unable to play this audio. You can continue to the next item.') }}', true);
                }
            } finally {
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }
        }

        function icon(className) {
            const node = document.createElement('i');
            node.className = className;
            node.setAttribute('aria-hidden', 'true');
            return node;
        }

        function setNextButtonLabel(label, showIcon = false) {
            ui.nextBtn.replaceChildren(document.createTextNode(label));
            if (showIcon) ui.nextBtn.appendChild(icon('fas fa-chevron-right ml-2'));
        }

        function createAudioButton(label, options) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'flex items-center gap-2 px-4 py-2 bg-indigo-100 text-indigo-700 rounded-full hover:bg-indigo-200 transition-colors';
            button.setAttribute('aria-controls', 'media-status');
            const text = document.createElement('span');
            text.className = 'font-semibold';
            text.textContent = label;
            button.append(icon('fas fa-volume-up'), text);
            button.addEventListener('click', () => playPrompt(button, options));
            return button;
        }

        function createImage(item) {
            const image = document.createElement('img');
            image.src = item.url;
            image.alt = item.description || item.title || '';
            image.className = 'max-w-full h-80 rounded-lg shadow-sm';
            image.addEventListener('error', () => {
                setMediaStatus('{{ __('Unable to load this image. You can continue to the next item.') }}', true);
            });
            return image;
        }

        const MATERIAL_RENDERERS = {
            Teks(item) {
                if (item.audio_url) {
                    ui.media.appendChild(createAudioButton('{{ __('Listen') }}', {
                        audioUrl: item.audio_url,
                        promptText: item.title || item.description || '',
                    }));
                }
            },
            Audio(item) {
                if (!item.url) {
                    setMediaStatus('{{ __('This audio item has no media file.') }}', true);
                    return;
                }
                const audio = document.createElement('audio');
                audio.controls = true;
                audio.preload = 'metadata';
                audio.src = item.url;
                audio.className = 'w-full';
                audio.setAttribute('aria-label', item.title || '{{ __('Listen') }}');
                audio.addEventListener('error', () => {
                    setMediaStatus('{{ __('Unable to play this audio. You can continue to the next item.') }}', true);
                });
                ui.media.appendChild(audio);
            },
            Gambar(item) {
                if (!item.url) {
                    setMediaStatus('{{ __('This image item has no image file.') }}', true);
                    return;
                }
                const container = document.createElement('div');
                container.className = 'flex flex-col items-center gap-4';
                container.appendChild(createImage(item));
                if (item.audio_url) {
                    container.appendChild(createAudioButton('{{ __('Listen') }}', {
                        audioUrl: item.audio_url,
                        promptText: item.title || item.description || '',
                    }));
                }
                ui.media.appendChild(container);
            },
            Video(item) {
                const match = typeof item.url === 'string'
                    ? item.url.match(/^https:\/\/www\.youtube\.com\/embed\/([A-Za-z0-9_-]{11})$/)
                    : null;
                if (!match) {
                    setMediaStatus('{{ __('This video URL is invalid or unsupported.') }}', true);
                    return;
                }
                const frameContainer = document.createElement('div');
                frameContainer.className = 'aspect-video';
                const iframe = document.createElement('iframe');
                iframe.src = `https://www.youtube.com/embed/${match[1]}`;
                iframe.className = 'w-full min-h-80 rounded-lg shadow-sm';
                iframe.title = item.title || '{{ __('Watch Video') }}';
                iframe.loading = 'lazy';
                iframe.referrerPolicy = 'strict-origin-when-cross-origin';
                iframe.allowFullscreen = true;
                iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
                frameContainer.appendChild(iframe);
                ui.media.appendChild(frameContainer);
            },
        };

        function renderItem(index) {
            if (index < 0 || index >= allItems.length) return;

            const item = allItems[index];
            getMediaController()?.stop();
            ui.title.textContent = item.title || '';
            ui.description.textContent = item.description || '';
            ui.media.replaceChildren();
            setMediaStatus();

            const renderer = MATERIAL_RENDERERS[materialType];
            if (renderer) renderer(item);
            else setMediaStatus('{{ __('This material type is unsupported.') }}', true);

            currentIndex = index;
            updateUI();
        }

        function updateUI() {
            const progress = ((currentIndex + 1) / allItems.length) * 100;
            ui.progressBar.value = Math.round(progress);
            ui.progressBar.textContent = `${Math.round(progress)}%`;
            ui.counter.textContent = `${currentIndex + 1} / ${allItems.length}`;
            ui.prevBtn.disabled = currentIndex === 0 || progressSavePending;
            ui.nextBtn.disabled = progressSavePending;

            if (currentIndex === allItems.length - 1) {
                setNextButtonLabel('{{ __('Done') }}');
                ui.nextBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                ui.nextBtn.classList.remove('bg-indigo-600', 'hover:bg-indigo-700');
            } else {
                setNextButtonLabel('{{ __('Next') }}', true);
                ui.nextBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                ui.nextBtn.classList.add('bg-indigo-600', 'hover:bg-indigo-700');
            }

            ui.sideNav.forEach((navItem, idx) => {
                navItem.disabled = progressSavePending;
                navItem.classList.remove('active');
                if (idx === currentIndex) {
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

        ui.nextBtn.addEventListener('click', () => {
            if (progressSavePending) return;
            saveCurrentItem({
                itemId: allItems[currentIndex].id,
                nextIndex: currentIndex < allItems.length - 1 ? currentIndex + 1 : null,
                returnAfterSave: currentIndex === allItems.length - 1
            });
        });
        ui.progressRetryButton.addEventListener('click', () => {
            if (retryProgressAction) saveCurrentItem(retryProgressAction);
        });

        ui.prevBtn.addEventListener('click', () => {
            if (!progressSavePending && currentIndex > 0) renderItem(currentIndex - 1);
        });

        ui.sideNav.forEach(navItem => {
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
            emptyMessage.textContent = '{{ __('No items for this material yet.') }}';
            ui.mainContent.replaceChildren(emptyMessage);
            ui.footer.classList.add('hidden');
        }

        async function saveCurrentItem(action) {
            if (progressSavePending) return;

            retryProgressAction = action;
            progressSavePending = true;
            ui.progressError.classList.add('hidden');
            updateUI();
            setNextButtonLabel('{{ __('Saving progress...') }}');

            try {
                const progressClient = window.HospitrainityProgress;
                if (!progressClient) throw new Error('Progress client is unavailable.');

                await progressClient.saveProgress({
                    url: progressUrl,
                    items: [action.itemId],
                    type: 'MaterialItem'
                });

                if (action.returnAfterSave) {
                    window.location.assign(returnUrl);
                    return;
                }

                progressSavePending = false;
                retryProgressAction = null;
                renderItem(action.nextIndex);
            } catch (error) {
                console.error('Failed to save material progress:', error);
                progressSavePending = false;
                updateUI();
                ui.progressErrorMessage.textContent = '{{ __('Unable to save your progress. Check your connection and try again.') }}';
                ui.progressError.classList.remove('hidden');
                ui.progressError.focus();
            }
        }
    </script>
@endsection

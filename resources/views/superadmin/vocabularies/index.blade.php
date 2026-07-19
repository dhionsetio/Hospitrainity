@extends('layouts.app')

@section('title', Auth::user()->isAdmin() || \App\Models\CurriculumPackage::query()->where('is_active', true)->exists() ? __('admin.legacy_vocabulary_title') : __('admin.manage_vocabulary_title'))
@section('bodyClass', 'bg-neutral-100 font-sans')

@section('content')
    @php
        $legacyCurriculumReadOnly = Auth::user()->isAdmin() || \App\Models\CurriculumPackage::active() !== null;
        $vocabularyEditMode = old('_form_mode') === 'edit' && (int) old('_record_id') > 0;
        $vocabularyInitial = $failedVocabularyState ?? [
            'id' => $vocabularyEditMode ? (int) old('_record_id') : null,
            'lesson_id' => old('lesson_id', ''),
            'category' => old('category', ''),
            'order' => old('order', 0),
            'items' => is_array(old('items')) ? old('items') : [['term' => '', 'details' => '', 'media_url' => null, 'order' => 0]],
        ];
        $vocabularyAdminState = base64_encode(json_encode([
            'isModalOpen' => ! $legacyCurriculumReadOnly && $errors->any(),
            'isEditMode' => $vocabularyEditMode,
            'modalTitle' => $vocabularyEditMode ? __('admin.edit_vocabulary') : __('admin.create_vocabulary'),
            'createTitle' => __('admin.create_vocabulary'),
            'editTitle' => __('admin.edit_vocabulary'),
            'formAction' => $vocabularyEditMode ? route('superadmin.vocabularies.update', (int) old('_record_id')) : route('superadmin.vocabularies.store'),
            'createAction' => route('superadmin.vocabularies.store'),
            'entity' => $vocabularyInitial,
        ], JSON_THROW_ON_ERROR));
    @endphp
    <div x-data="vocabularyAdmin" data-admin-state="{{ $vocabularyAdminState }}">
        <div class="flex min-h-screen flex-col md:h-screen md:flex-row">
            @include('superadmin.sidebar')
            <main class="min-w-0 flex-1 p-6 md:overflow-y-auto md:p-10">
                @include('superadmin.canonical-curriculum-notice')
                <header class="mb-8 flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold">{{ $legacyCurriculumReadOnly ? __('admin.legacy_vocabulary_evidence') : __('admin.vocabulary_management') }}</h1>
                        <p class="text-neutral-500">{{ $legacyCurriculumReadOnly ? __('admin.legacy_evidence_description') : __('admin.vocabulary_management_description') }}</p>
                    </div>
                    @unless($legacyCurriculumReadOnly)
                        <button type="button" @click="openCreate" class="bg-indigo-600 text-white py-2 px-4 rounded-lg shadow font-semibold hover:bg-indigo-700">
                            <i class="fas fa-plus mr-2" aria-hidden="true"></i> {{ __('admin.create_vocabulary') }}
                        </button>
                    @endunless
                </header>

                @if(session('success'))
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                    <p>{{ session('success') }}</p>
                </div>
                @endif

                <div class="bg-white rounded-lg shadow-md overflow-x-auto">
                    <table class="w-full text-sm text-left text-neutral-500">
                        <thead class="text-xs text-neutral-500 uppercase bg-neutral-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 font-medium">{{ __('admin.category') }}</th>
                                <th scope="col" class="px-6 py-3 font-medium">{{ __('admin.parent_lesson') }}</th>
                                <th scope="col" class="px-6 py-3 font-medium">{{ __('admin.order') }}</th>
                                <th scope="col" class="px-6 py-3 font-medium">{{ __('admin.item_count') }}</th>
                                <th scope="col" class="px-6 py-3 font-medium">{{ $legacyCurriculumReadOnly ? __('Evidence status') : __('admin.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            @forelse($vocabularies as $vocabulary)
                            <tr>
                                <td class="px-6 py-4 font-semibold text-neutral-900">{{ $vocabulary->category }}</td>
                                <td class="px-6 py-4">{{ $vocabulary->lesson->title ?? __('admin.not_available') }}</td>
                                <td class="px-6 py-4">{{ $vocabulary->order }}</td>
                                <td class="px-6 py-4">{{ $vocabulary->items->count() }}</td>
                                <td class="px-6 py-4 flex items-center gap-3">
                                    @if($legacyCurriculumReadOnly)
                                        <span class="text-xs font-medium text-neutral-500">{{ __('Read-only evidence') }}</span>
                                    @else
                                        <button type="button" @click="openEdit" data-record="{{ base64_encode($vocabulary->toJson()) }}" data-action="{{ route('superadmin.vocabularies.update', $vocabulary) }}" class="font-medium text-blue-600 hover:underline" aria-label="{{ __('admin.edit_named', ['name' => $vocabulary->category]) }}"><i class="fas fa-edit" aria-hidden="true"></i></button>
                                        <form action="{{ route('superadmin.vocabularies.destroy', $vocabulary) }}" method="POST" data-confirm-submit="{{ __('admin.confirm_delete_named', ['name' => $vocabulary->category]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="font-medium text-red-600 hover:underline" aria-label="{{ __('admin.delete_named', ['name' => $vocabulary->category]) }}"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center">{{ __('admin.no_vocabulary') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="p-4">{{ $vocabularies->links() }}</div>
                </div>
            </main>
        </div>

        <!-- Modal Form -->
        @unless($legacyCurriculumReadOnly)
        <template x-if="isModalOpen">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/20">
            <div @click.away="closeModal" @keydown="handleDialogKeydown" role="dialog" aria-modal="true" aria-labelledby="vocabulary-modal-title" tabindex="-1" class="bg-white rounded-lg shadow-xl w-full max-w-4xl p-8 m-4 max-h-[90vh] flex flex-col">
                <div class="mb-6 flex items-center justify-between">
                    <h2 id="vocabulary-modal-title" data-dialog-initial-focus tabindex="-1" class="text-2xl font-bold text-neutral-800" x-text="modalTitle"></h2>
                    <button type="button" @click="closeModal" class="text-neutral-500 hover:text-neutral-800" aria-label="{{ __('admin.close_dialog') }}">&times;</button>
                </div>

                <form :action="formAction" method="POST" enctype="multipart/form-data" class="flex-1 overflow-y-auto pr-2">
                    @csrf
                    <input type="hidden" name="_form_mode" :value="isEditMode ? 'edit' : 'create'">
                    <input type="hidden" name="_record_id" :value="isEditMode ? vocab.id : ''">
                    <template x-if="isEditMode">@method('PUT')</template>

                    @include('superadmin.partials.form-errors')

                    <!-- Bagian Informasi Utama -->
                    <div class="p-4 bg-neutral-50 rounded-lg border mb-6">
                        <h3 class="text-lg font-semibold text-neutral-800 mb-4">{{ __('admin.category_information') }}</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="lesson_id" class="block text-sm font-medium text-neutral-700">{{ __('admin.parent_lesson') }}</label>
                                <select name="lesson_id" id="lesson_id" x-model="vocab.lesson_id" required class="mt-1 block w-full px-3 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                    <option value="">{{ __('admin.select_lesson') }}</option>
                                    @foreach($lessons as $lesson)<option :value="{{ $lesson->id }}">{{ $lesson->title }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label for="category" class="block text-sm font-medium text-neutral-700">{{ __('admin.category_name') }}</label>
                                <input type="text" name="category" id="category" x-model="vocab.category" required class="mt-1 block w-full px-3 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            <div>
                                <label for="vocabulary_order" class="block text-sm font-medium text-neutral-700">{{ __('admin.category_order') }}</label>
                                <input type="number" name="order" id="vocabulary_order" x-model.number="vocab.order" min="0" max="1000000" class="mt-1 block w-full px-3 py-2 border border-neutral-300 rounded-md">
                            </div>
                        </div>
                    </div>

                    <!-- Bagian Item-item -->
                    <div id="items-container" class="space-y-4">
                        <h3 class="text-lg font-semibold text-neutral-800 border-b pb-2">{{ __('admin.vocabulary_items') }}</h3>
                        <template x-for="(item, index) in vocab.items" :key="index">
                            <div class="item-group grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-2 border-t pt-4">
                                <template x-if="isEditMode"><input type="hidden" :name="itemName(index, 'id')" x-model="item.id"></template>
                                <div>
                                    <label :for="itemElementId('vocabulary_term', index)" class="block text-sm font-medium text-neutral-700">{{ __('admin.term') }}</label>
                                    <input type="text" :id="itemElementId('vocabulary_term', index)" :name="itemName(index, 'term')" x-model="item.term" required class="mt-1 w-full px-3 py-2 border border-neutral-300 rounded-md">
                                </div>
                                <div>
                                    <label :for="itemElementId('vocabulary_details', index)" class="block text-sm font-medium text-neutral-700">{{ __('admin.details') }}</label>
                                    <input type="text" :id="itemElementId('vocabulary_details', index)" :name="itemName(index, 'details')" x-model="item.details" class="mt-1 w-full px-3 py-2 border border-neutral-300 rounded-md">
                                </div>
                                <div>
                                    <label :for="itemElementId('vocabulary_item_order', index)" class="block text-sm font-medium text-neutral-700">{{ __('admin.item_order') }}</label>
                                    <input type="number" :name="itemName(index, 'order')" :id="itemElementId('vocabulary_item_order', index)" x-model.number="item.order" min="0" max="1000000" class="mt-1 w-full px-3 py-2 border border-neutral-300 rounded-md">
                                </div>
                                <div class="md:col-span-2">
                                    <label :for="itemElementId('vocabulary_media', index)" class="block text-sm font-medium text-neutral-700">{{ __('admin.optional_audio_video') }}</label>
                                    <template x-if="isEditMode && item.media_url">
                                        <p class="text-xs text-neutral-500 mb-1">{{ __('admin.current_file') }}: <a :href="item.media_url" target="_blank" rel="noopener noreferrer" class="text-indigo-600" x-text="fileName(item.media_url)"></a></p>
                                    </template>
                                    <input type="file" :id="itemElementId('vocabulary_media', index)" :name="itemName(index, 'media')" class="w-full text-sm text-neutral-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-violet-50 file:text-violet-700 hover:file:bg-violet-100">
                                </div>
                                <div class="text-right md:col-span-2"><button type="button" @click="removeItem(index)" class="text-sm font-medium text-red-600 hover:text-red-800">{{ __('admin.remove_item') }}</button></div>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="addItem" class="mt-4 bg-green-500 text-white py-2 px-4 rounded-lg text-sm font-semibold hover:bg-green-600"><i class="fas fa-plus mr-2" aria-hidden="true"></i>{{ __('admin.add_item') }}</button>

                    <div class="mt-8 border-t pt-5 flex justify-end gap-3"><button type="button" @click="closeModal" class="bg-neutral-200 py-2 px-6 rounded-lg">{{ __('admin.cancel') }}</button><button type="submit" class="bg-indigo-600 text-white py-2 px-6 rounded-lg">{{ __('admin.save') }}</button></div>
                </form>
            </div>
        </div>
        </template>
        @endunless
    </div>
@endsection

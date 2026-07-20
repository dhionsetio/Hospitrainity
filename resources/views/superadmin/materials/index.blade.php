@extends('layouts.app')

@section('title', Auth::user()->isAdmin() || \App\Models\CurriculumPackage::query()->where('is_active', true)->exists() ? __('admin.legacy_materials_title') : __('admin.manage_materials_title'))
@section('bodyClass', 'bg-neutral-100 font-sans')

@section('content')
    @php
        $legacyCurriculumReadOnly = Auth::user()->isAdmin() || \App\Models\CurriculumPackage::active() !== null;
        $materialEditMode = old('_form_mode') === 'edit' && (int) old('_record_id') > 0;
        $materialInitial = $failedMaterialState ?? [
            'id' => $materialEditMode ? (int) old('_record_id') : null,
            'lesson_id' => old('lesson_id', ''),
            'type' => old('type', 'Teks'),
            'order' => old('order', 0),
            'items' => is_array(old('items')) ? old('items') : [['title' => '', 'description' => '', 'url' => null, 'audio_url' => null, 'order' => 0]],
        ];
        $materialAdminState = base64_encode(json_encode([
            'isModalOpen' => ! $legacyCurriculumReadOnly && $errors->any(),
            'isEditMode' => $materialEditMode,
            'modalTitle' => $materialEditMode ? __('admin.edit_material') : __('admin.create_material'),
            'createTitle' => __('admin.create_material'),
            'editTitle' => __('admin.edit_material'),
            'formAction' => $materialEditMode ? route('superadmin.materials.update', (int) old('_record_id')) : route('superadmin.materials.store'),
            'createAction' => route('superadmin.materials.store'),
            'entity' => $materialInitial,
        ], JSON_THROW_ON_ERROR));
    @endphp
    <div x-data="materialAdmin" data-admin-state="{{ $materialAdminState }}">
        <div class="flex min-h-screen flex-col md:h-screen md:flex-row">
            @include('superadmin.sidebar')
            <main class="min-w-0 flex-1 p-6 md:overflow-y-auto md:p-10">
                @include('superadmin.canonical-curriculum-notice')
                <header class="mb-8 flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold">{{ $legacyCurriculumReadOnly ? __('admin.legacy_material_evidence') : __('admin.material_management') }}</h1>
                        <p class="text-neutral-500">{{ $legacyCurriculumReadOnly ? __('admin.legacy_evidence_description') : __('admin.material_management_description') }}</p>
                    </div>
                    @unless($legacyCurriculumReadOnly)
                        <button type="button" @click="openCreate" class="bg-indigo-600 text-white py-2 px-4 rounded-lg shadow font-semibold hover:bg-indigo-700">
                            <i class="fas fa-plus mr-2" aria-hidden="true"></i> {{ __('admin.create_material') }}
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
                                <th scope="col" class="px-6 py-3 font-medium">{{ __('admin.material_type') }}</th>
                                <th scope="col" class="px-6 py-3 font-medium">{{ __('admin.parent_lesson') }}</th>
                                <th scope="col" class="px-6 py-3 font-medium">{{ __('admin.order') }}</th>
                                <th scope="col" class="px-6 py-3 font-medium">{{ __('admin.item_count') }}</th>
                                <th scope="col" class="px-6 py-3 font-medium">{{ $legacyCurriculumReadOnly ? __('Evidence status') : __('admin.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            @forelse($materials as $mat)
                            <tr>
                                <td class="px-6 py-4 font-semibold text-neutral-900">{{ $mat->type }}</td>
                                <td class="px-6 py-4">{{ $mat->lesson->title ?? __('admin.not_available') }}</td>
                                <td class="px-6 py-4">{{ $mat->order }}</td>
                                <td class="px-6 py-4">{{ $mat->items->count() }}</td>
                                <td class="px-6 py-4 flex items-center gap-3">
                                    @if($legacyCurriculumReadOnly)
                                        <span class="text-xs font-semibold text-neutral-700">{{ __('Read-only evidence') }}</span>
                                    @else
                                        <button type="button" @click="openEdit" data-record="{{ base64_encode($mat->toJson()) }}" data-action="{{ route('superadmin.materials.update', $mat) }}" class="font-medium text-blue-600 hover:underline" aria-label="{{ __('admin.edit_named', ['name' => $mat->type]) }}"><i class="fas fa-edit" aria-hidden="true"></i></button>
                                        <form action="{{ route('superadmin.materials.destroy', $mat) }}" method="POST" data-confirm-submit="{{ __('admin.confirm_delete_named', ['name' => $mat->type]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="font-medium text-red-600 hover:underline" aria-label="{{ __('admin.delete_named', ['name' => $mat->type]) }}"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @if($legacyCurriculumReadOnly)
                                <tr class="bg-neutral-50/70">
                                    <td colspan="5" class="px-6 py-3">
                                        <details>
                                            <summary class="cursor-pointer font-semibold text-indigo-700 underline">{{ __('View stored details') }}</summary>
                                            <div class="mt-4 rounded-md border border-neutral-200 bg-white p-4">
                                                @include('superadmin.partials.legacy-structured-value', ['value' => [
                                                    'record_id' => $mat->id,
                                                    'type' => $mat->type,
                                                    'parent_lesson' => $mat->lesson->title ?? null,
                                                    'display_order' => $mat->order,
                                                    'items' => $mat->items->map(fn ($item) => [
                                                        'title' => $item->title,
                                                        'description' => $item->description,
                                                        'content_url' => $item->url,
                                                        'audio_url' => $item->audio_url,
                                                        'display_order' => $item->order,
                                                    ])->all(),
                                                ]])
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @endif
                            @empty
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center">{{ __('admin.no_materials') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="p-4">{{ $materials->links() }}</div>
                </div>
            </main>
        </div>

        <!-- Modal Form -->
        @unless($legacyCurriculumReadOnly)
        <template x-if="isModalOpen">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/20">
            <div @click.away="closeModal" @keydown="handleDialogKeydown" role="dialog" aria-modal="true" aria-labelledby="material-modal-title" tabindex="-1" class="bg-white rounded-lg shadow-xl w-full max-w-4xl p-8 m-4 max-h-[90vh] flex flex-col">
                <div class="mb-6 flex items-center justify-between">
                    <h2 id="material-modal-title" data-dialog-initial-focus tabindex="-1" class="text-2xl font-bold text-neutral-800" x-text="modalTitle"></h2>
                    <button type="button" @click="closeModal" class="text-neutral-500 hover:text-neutral-800" aria-label="{{ __('admin.close_dialog') }}">&times;</button>
                </div>

                <form :action="formAction" method="POST" enctype="multipart/form-data" class="flex-1 overflow-y-auto pr-2">
                    @csrf
                    <input type="hidden" name="_form_mode" :value="isEditMode ? 'edit' : 'create'">
                    <input type="hidden" name="_record_id" :value="isEditMode ? material.id : ''">
                    <template x-if="isEditMode">@method('PUT')</template>

                    @include('superadmin.partials.form-errors')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="lesson_id" class="block text-sm font-medium">{{ __('admin.parent_lesson') }}</label>
                            <select name="lesson_id" id="lesson_id" x-model="material.lesson_id" required class="mt-1 block w-full border-neutral-300 rounded-md shadow-sm">
                                <option value="">{{ __('admin.select_lesson') }}</option>
                                @foreach($lessons as $lesson)<option value="{{ $lesson->id }}">{{ $lesson->title }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label for="type-selector" class="block text-sm font-medium">{{ __('admin.material_type') }}</label>
                            <select :name="isEditMode ? null : 'type'" id="type-selector" x-model="material.type" required :disabled="isEditMode" class="mt-1 block w-full border-neutral-300 rounded-md shadow-sm disabled:bg-neutral-100">
                                <option value="Teks">{{ __('admin.text_only') }}</option>
                                <option value="Audio">{{ __('admin.audio') }}</option>
                                <option value="Gambar">{{ __('admin.image') }}</option>
                                <option value="Video">{{ __('admin.video_youtube') }}</option>
                            </select>
                            <template x-if="isEditMode"><input type="hidden" name="type" x-model="material.type"></template>
                        </div>
                        <div>
                            <label for="material_order" class="block text-sm font-medium">{{ __('admin.category_order') }}</label>
                            <input type="number" name="order" id="material_order" x-model.number="material.order" min="0" max="1000000" class="mt-1 block w-full border-neutral-300 rounded-md shadow-sm">
                        </div>
                    </div>

                    <div id="items-container" class="space-y-4">
                        <h3 class="text-lg font-medium text-neutral-800 pb-2">{{ __('admin.material_items') }}</h3>
                        <template x-for="(item, index) in material.items" :key="index">
                            <div class="item-group grid grid-cols-1 md:grid-cols-2 gap-4 items-start pt-4">
                                <template x-if="isEditMode"><input type="hidden" :name="itemName(index, 'id')" x-model="item.id"></template>
                                <div>
                                    <label :for="itemElementId('item_title', index)" class="block text-sm font-medium">{{ __('admin.optional_title') }}</label>
                                    <input type="text" :name="itemName(index, 'title')" :id="itemElementId('item_title', index)" x-model="item.title" class="mt-1 w-full border-neutral-300 border-1 rounded-md">
                                </div>
                                <div class="md:row-span-2">
                                    <label :for="itemElementId('item_desc', index)" class="block text-sm font-medium">{{ __('admin.description') }}</label>
                                    <textarea :name="itemName(index, 'description')" :id="itemElementId('item_desc', index)" x-model="item.description" required rows="5" class="mt-1 w-full border-neutral-300 border-1 rounded-md"></textarea>
                                </div>
                                <div>
                                    <label :for="itemElementId('material_item_order', index)" class="block text-sm font-medium">{{ __('admin.item_order') }}</label>
                                    <input type="number" :name="itemName(index, 'order')" :id="itemElementId('material_item_order', index)" x-model.number="item.order" min="0" max="1000000" class="mt-1 w-full border-neutral-300 rounded-md">
                                </div>
                                <div class="md:col-span-2 space-y-4">
                                    <template x-if="material.type === 'Gambar'">
                                        <div class="space-y-2">
                                            <template x-if="item.url"><p class="text-xs text-neutral-500">{{ __('admin.current_image') }}: <a :href="item.url" target="_blank" rel="noopener noreferrer" class="text-indigo-600" x-text="fileName(item.url)"></a></p></template>
                                            <label :for="itemElementId('material_image', index)" class="block text-sm font-medium">{{ __('admin.upload_image') }} <template x-if="!item.url"><span>({{ __('admin.required') }})</span></template></label>
                                            <input type="file" :id="itemElementId('material_image', index)" accept="image/jpeg,image/png,image/gif,image/webp" :name="itemName(index, 'file')" :required="!item.url" class="w-full text-sm">
                                            <label :for="itemElementId('material_image_audio', index)" class="block text-sm font-medium mt-2">{{ __('admin.upload_optional_audio') }}</label>
                                            <input type="file" :id="itemElementId('material_image_audio', index)" accept="audio/mpeg,audio/wav" :name="itemName(index, 'audio_file')" class="w-full text-sm">
                                        </div>
                                    </template>
                                    <template x-if="material.type === 'Teks'">
                                        <div class="space-y-2">
                                            <label :for="itemElementId('material_text_audio', index)" class="block text-sm font-medium">{{ __('admin.upload_optional_audio') }}</label>
                                            <input type="file" :id="itemElementId('material_text_audio', index)" accept="audio/mpeg,audio/wav" :name="itemName(index, 'audio_file')" class="w-full text-sm">
                                        </div>
                                    </template>
                                    <template x-if="material.type === 'Audio'">
                                        <div class="space-y-2">
                                            <template x-if="item.url"><p class="text-xs text-neutral-500">{{ __('admin.current_audio') }}: <a :href="item.url" target="_blank" rel="noopener noreferrer" class="text-indigo-600" x-text="fileName(item.url)"></a></p></template>
                                            <label :for="itemElementId('material_audio', index)" class="block text-sm font-medium">{{ __('admin.upload_audio') }} <template x-if="!item.url"><span>({{ __('admin.required') }})</span></template></label>
                                            <input type="file" :id="itemElementId('material_audio', index)" accept="audio/mpeg,audio/wav" :name="itemName(index, 'file')" :required="!item.url" class="w-full text-sm">
                                        </div>
                                    </template>
                                    <template x-if="material.type === 'Video'">
                                        <div class="space-y-2">
                                            <label :for="itemElementId('material_video_url', index)" class="block text-sm font-medium">{{ __('admin.video_url') }}</label>
                                            <input type="url" :id="itemElementId('material_video_url', index)" :name="itemName(index, 'url')" x-model="item.url" required placeholder="https://www.youtube.com/watch?v=..." class="mt-1 w-full border-neutral-300 rounded-md">
                                            <p class="text-xs text-neutral-500">{{ __('admin.youtube_https_only') }}</p>
                                        </div>
                                    </template>
                                </div>
                                <div class="text-right md:col-span-2">
                                    <button type="button" @click="removeItem(index)" class="text-red-500 hover:text-red-700 text-sm font-medium">{{ __('admin.remove_item') }}</button>
                                </div>
                            </div>
                        </template>
                    </div>
                    <button type="button" @click="addItem" class="mt-4 bg-green-500 text-white py-2 px-4 rounded-lg text-sm font-semibold hover:bg-green-600"><i class="fas fa-plus mr-2" aria-hidden="true"></i>{{ __('admin.add_item') }}</button>

                    <div class="mt-8 border-t pt-5 flex justify-end gap-3">
                        <button type="button" @click="closeModal" class="bg-neutral-200 text-neutral-800 py-2 px-6 rounded-lg font-semibold hover:bg-neutral-300">{{ __('admin.cancel') }}</button>
                        <button type="submit" class="bg-indigo-600 text-white py-2 px-6 rounded-lg shadow font-semibold hover:bg-indigo-700">{{ __('admin.save') }}</button>
                    </div>
                </form>
            </div>
        </div>
        </template>
        @endunless
    </div>
@endsection

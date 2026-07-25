@extends('layouts.app')

@section('title', Auth::user()->isAdmin() || \App\Models\CurriculumPackage::query()->where('is_active', true)->exists() ? __('admin.legacy_modules_title') : __('admin.manage_modules_title'))
@section('bodyClass', 'bg-neutral-100')

@section('content')
    @php
        $legacyCurriculumReadOnly = Auth::user()->isAdmin() || \App\Models\CurriculumPackage::active() !== null;
        $moduleEditMode = old('_form_mode') === 'edit' && (int) old('_record_id') > 0;
        $moduleInitial = [
            'id' => $moduleEditMode ? (int) old('_record_id') : null,
            'title' => old('title', ''),
            'description' => old('description', ''),
            'level' => old('level', 'beginner'),
            'order' => old('order', 0),
            'is_published' => old('is_published', false),
        ];
        $moduleAdminState = base64_encode(json_encode([
            'isModalOpen' => ! $legacyCurriculumReadOnly && $errors->any(),
            'isEditMode' => $moduleEditMode,
            'modalTitle' => $moduleEditMode ? __('admin.edit_module') : __('admin.create_module'),
            'createTitle' => __('admin.create_module'),
            'editTitle' => __('admin.edit_module'),
            'formAction' => $moduleEditMode ? route('superadmin.modules.update', (int) old('_record_id')) : route('superadmin.modules.store'),
            'createAction' => route('superadmin.modules.store'),
            'entity' => $moduleInitial,
        ], JSON_THROW_ON_ERROR));
    @endphp
    <div x-data="moduleAdmin" data-admin-state="{{ $moduleAdminState }}" class="flex min-h-screen flex-col bg-neutral-100 md:flex-row">

        @include('superadmin.sidebar')

        <!-- Main Content -->
        <main class="min-w-0 flex-1 p-6 md:p-10">
            @include('superadmin.canonical-curriculum-notice')
            <header class="mb-8 flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-neutral-800">{{ $legacyCurriculumReadOnly ? __('admin.legacy_module_evidence') : __('admin.module_management') }}</h1>
                    <p class="text-neutral-500">{{ $legacyCurriculumReadOnly ? __('admin.legacy_evidence_description') : __('admin.module_management_description') }}</p>
                </div>
                @unless($legacyCurriculumReadOnly)
                    <button type="button" @click="openCreate" class="bg-indigo-600 text-white py-2 px-4 rounded-lg shadow font-semibold hover:bg-indigo-700 transition-colors">
                        <i class="fas fa-plus mr-2" aria-hidden="true"></i> {{ __('admin.create_module') }}
                    </button>
                @endunless
            </header>

            @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert">
                <p>{{ session('success') }}</p>
            </div>
            @endif

            <!-- Tabel Daftar Modul -->
            <div class="bg-white rounded-lg shadow-md overflow-x-auto">
                <table class="w-full text-sm text-left text-neutral-500">
                    <thead class="text-xs text-neutral-500 uppercase bg-neutral-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 font-medium">{{ __('admin.title') }}</th>
                            <th scope="col" class="px-6 py-3 font-medium">{{ __('admin.level') }}</th>
                            <th scope="col" class="px-6 py-3 font-medium">{{ __('admin.order') }}</th>
                            <th scope="col" class="px-6 py-3 font-medium">{{ __('admin.lesson_count') }}</th>
                            <th scope="col" class="px-6 py-3 font-medium">{{ __('admin.status') }}</th>
                            <th scope="col" class="px-6 py-3 font-medium">{{ $legacyCurriculumReadOnly ? __('Evidence status') : __('admin.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-200">
                        @forelse($modules as $m)
                        <tr class="hover:bg-neutral-50">
                            <th scope="row" class="px-6 py-4 font-semibold text-neutral-900 whitespace-nowrap">{{ $m->title }}</th>
                            <td class="px-6 py-4"><span class="text-xs font-semibold bg-blue-100 text-blue-700 px-2.5 py-0.5 rounded-full">{{ ucfirst($m->level) }}</span></td>
                            <td class="px-6 py-4">{{ $m->order }}</td>
                            <td class="px-6 py-4">{{ $m->lessons_count }}</td>
                            <td class="px-6 py-4">@if($m->is_published) <span class="text-xs font-semibold bg-green-100 text-green-700 px-2.5 py-0.5 rounded-full">{{ __('admin.published') }}</span> @else <span class="text-xs font-semibold bg-neutral-200 text-neutral-700 px-2.5 py-0.5 rounded-full">{{ __('admin.draft') }}</span> @endif</td>
                            <td class="px-6 py-4 flex items-center gap-3">
                                @if($legacyCurriculumReadOnly)
                                    <span class="text-xs font-semibold text-neutral-700">{{ __('Read-only evidence') }}</span>
                                @else
                                    <button type="button" @click="openEdit" data-record="{{ base64_encode($m->toJson()) }}" data-action="{{ route('superadmin.modules.update', $m) }}" class="font-medium text-blue-600 hover:underline" aria-label="{{ __('admin.edit_named', ['name' => $m->title]) }}"><i class="fas fa-edit" aria-hidden="true"></i></button>
                                    <form action="{{ route('superadmin.modules.destroy', $m) }}" method="POST" data-confirm-submit="{{ __('admin.confirm_delete_named', ['name' => $m->title]) }}">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="font-medium text-red-600 hover:underline" aria-label="{{ __('admin.delete_named', ['name' => $m->title]) }}"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                        @if($legacyCurriculumReadOnly)
                            <tr class="bg-neutral-50/70">
                                <td colspan="6" class="px-6 py-3">
                                    <details>
                                        <summary class="cursor-pointer font-semibold text-indigo-700 underline">{{ __('View stored details') }}</summary>
                                        <div class="mt-4 rounded-md border border-neutral-200 bg-white p-4">
                                            @include('superadmin.partials.legacy-structured-value', ['value' => [
                                                'record_id' => $m->id,
                                                'slug' => $m->slug,
                                                'description' => $m->description,
                                                'level' => $m->level,
                                                'display_order' => $m->order,
                                                'published' => (bool) $m->is_published,
                                                'lesson_count' => $m->lessons_count,
                                            ]])
                                        </div>
                                    </details>
                                </td>
                            </tr>
                        @endif
                        @empty
                        <tr>
                            <td colspan="6" class="px-6 py-4 text-center text-neutral-500">{{ __('admin.no_modules') }}</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="p-4">{{ $modules->links() }}</div>
            </div>
        </main>

        <!-- Modal Form (Create/Edit) -->
        @unless($legacyCurriculumReadOnly)
        <template x-if="isModalOpen">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/20">
            <div @click.away="closeModal" @keydown="handleDialogKeydown" role="dialog" aria-modal="true" aria-labelledby="module-modal-title" tabindex="-1" class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-6 md:p-8">
                <div class="flex justify-between items-center mb-6">
                    <h2 id="module-modal-title" data-dialog-initial-focus tabindex="-1" class="text-2xl font-bold text-neutral-800" x-text="modalTitle"></h2>
                    <button type="button" @click="closeModal" class="text-neutral-500 hover:text-neutral-800" aria-label="{{ __('admin.close_dialog') }}">&times;</button>
                </div>

                <form :action="formAction" method="POST">
                    @csrf
                    <input type="hidden" name="_form_mode" :value="isEditMode ? 'edit' : 'create'">
                    <input type="hidden" name="_record_id" :value="isEditMode ? module.id : ''">
                    <!-- Jika mode edit, tambahkan method PUT -->
                    <template x-if="isEditMode">
                        @method('PUT')
                    </template>

                    @include('superadmin.partials.form-errors')

                    <div class="space-y-6">
                        <div>
                            <label for="title" class="block text-sm font-medium text-neutral-700">{{ __('admin.module_title') }}</label>
                            <input type="text" name="title" id="title" x-model="module.title" required class="mt-1 block w-full px-3 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label for="description" class="block text-sm font-medium text-neutral-700">{{ __('admin.description') }}</label>
                            <textarea name="description" id="description" rows="4" x-model="module.description" required class="mt-1 block w-full px-3 py-2 border border-neutral-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                        </div>
                        <div>
                            <label for="level" class="block text-sm font-medium text-neutral-700">{{ __('admin.level') }}</label>
                            <select name="level" id="level" x-model="module.level" class="mt-1 block w-full px-3 py-2 border border-neutral-300 bg-white rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="beginner">{{ __('admin.beginner') }}</option>
                                <option value="intermediate">{{ __('admin.intermediate') }}</option>
                                <option value="advanced">{{ __('admin.advanced') }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="module_order" class="block text-sm font-medium text-neutral-700">{{ __('admin.display_order') }}</label>
                            <input type="number" name="order" id="module_order" x-model.number="module.order" min="0" max="1000000" class="mt-1 block w-full px-3 py-2 border border-neutral-300 rounded-md shadow-sm">
                            <p class="mt-1 text-xs text-neutral-500">{{ __('admin.order_help_global') }}</p>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" name="is_published" id="is_published" value="1" x-model="module.is_published" class="h-4 w-4 shrink-0 text-indigo-600 border-neutral-300 rounded focus:ring-indigo-500">
                            <label for="is_published" class="ml-2 block text-sm text-neutral-900">{{ __('admin.publish_module') }}</label>
                        </div>
                    </div>
                    <div class="mt-8 pt-5 flex justify-end gap-3">
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

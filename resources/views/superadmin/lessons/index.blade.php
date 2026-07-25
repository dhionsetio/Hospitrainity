@extends('layouts.app')

@section('title', Auth::user()->isAdmin() || \App\Models\CurriculumPackage::query()->where('is_active', true)->exists() ? __('admin.legacy_lessons_title') : __('admin.manage_lessons_title'))
@section('bodyClass', 'bg-neutral-100 font-sans')

@section('content')
    @php
        $legacyCurriculumReadOnly = Auth::user()->isAdmin() || \App\Models\CurriculumPackage::active() !== null;
        $lessonEditMode = old('_form_mode') === 'edit' && (int) old('_record_id') > 0;
        $lessonInitial = [
            'id' => $lessonEditMode ? (int) old('_record_id') : null,
            'module_id' => old('module_id', ''),
            'title' => old('title', ''),
            'slug' => old('slug', ''),
            'order' => old('order', 0),
        ];
        $lessonAdminState = base64_encode(json_encode([
            'isModalOpen' => ! $legacyCurriculumReadOnly && $errors->any(),
            'isEditMode' => $lessonEditMode,
            'modalTitle' => $lessonEditMode ? __('admin.edit_lesson') : __('admin.create_lesson'),
            'createTitle' => __('admin.create_lesson'),
            'editTitle' => __('admin.edit_lesson'),
            'formAction' => $lessonEditMode ? route('superadmin.lessons.update', (int) old('_record_id')) : route('superadmin.lessons.store'),
            'createAction' => route('superadmin.lessons.store'),
            'entity' => $lessonInitial,
        ], JSON_THROW_ON_ERROR));
    @endphp
    <div x-data="lessonAdmin" data-admin-state="{{ $lessonAdminState }}">
        <div class="flex min-h-screen flex-col md:flex-row">
            @include('superadmin.sidebar')
            <main class="min-w-0 flex-1 p-6 md:p-10">
                @include('superadmin.canonical-curriculum-notice')
                <header class="mb-8 flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold">{{ $legacyCurriculumReadOnly ? __('admin.legacy_lesson_evidence') : __('admin.lesson_management') }}</h1>
                        <p class="text-neutral-500">{{ $legacyCurriculumReadOnly ? __('admin.legacy_evidence_description') : __('admin.lesson_management_description') }}</p>
                    </div>
                    @unless($legacyCurriculumReadOnly)
                        <button type="button" @click="openCreate" class="bg-indigo-600 text-white py-2 px-4 rounded-lg shadow font-semibold hover:bg-indigo-700">
                            <i class="fas fa-plus mr-2" aria-hidden="true"></i> {{ __('admin.create_lesson') }}
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
                                <th scope="col" class="px-6 py-3 font-medium">{{ __('admin.lesson_title') }}</th>
                                <th scope="col" class="px-6 py-3 font-medium">{{ __('admin.parent_module') }}</th>
                                <th scope="col" class="px-6 py-3 font-medium">{{ __('admin.order') }}</th>
                                <th scope="col" class="px-6 py-3 font-medium">{{ $legacyCurriculumReadOnly ? __('Evidence status') : __('admin.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-200">
                            @forelse($lessons as $l)
                            <tr>
                                <td class="px-6 py-4 font-semibold">{{ $l->title }}</td>
                                <td class="px-6 py-4">{{ $l->module->title ?? __('admin.not_available') }}</td>
                                <td class="px-6 py-4">{{ $l->order }}</td>
                                <td class="px-6 py-4 flex items-center gap-3">
                                    @if($legacyCurriculumReadOnly)
                                        <span class="text-xs font-semibold text-neutral-700">{{ __('Read-only evidence') }}</span>
                                    @else
                                        <button type="button" @click="openEdit" data-record="{{ base64_encode($l->toJson()) }}" data-action="{{ route('superadmin.lessons.update', $l) }}" class="font-medium text-blue-600 hover:underline" aria-label="{{ __('admin.edit_named', ['name' => $l->title]) }}"><i class="fas fa-edit" aria-hidden="true"></i></button>
                                        <form action="{{ route('superadmin.lessons.destroy', $l) }}" method="POST" data-confirm-submit="{{ __('admin.confirm_delete_named', ['name' => $l->title]) }}">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="font-medium text-red-600 hover:underline" aria-label="{{ __('admin.delete_named', ['name' => $l->title]) }}"><i class="fas fa-trash" aria-hidden="true"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                            @if($legacyCurriculumReadOnly)
                                <tr class="bg-neutral-50/70">
                                    <td colspan="4" class="px-6 py-3">
                                        <details>
                                            <summary class="cursor-pointer font-semibold text-indigo-700 underline">{{ __('View stored details') }}</summary>
                                            <div class="mt-4 rounded-md border border-neutral-200 bg-white p-4">
                                                @include('superadmin.partials.legacy-structured-value', ['value' => [
                                                    'record_id' => $l->id,
                                                    'slug' => $l->slug,
                                                    'parent_module' => $l->module->title ?? null,
                                                    'display_order' => $l->order,
                                                ]])
                                            </div>
                                        </details>
                                    </td>
                                </tr>
                            @endif
                            @empty
                            <tr>
                                <td colspan="4" class="px-6 py-4 text-center">{{ __('admin.no_lessons') }}</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="p-4">{{ $lessons->links() }}</div>
                </div>
            </main>
        </div>

        <!-- Modal Form -->
        @unless($legacyCurriculumReadOnly)
        <template x-if="isModalOpen">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/20">
            <div @click.away="closeModal" @keydown="handleDialogKeydown" role="dialog" aria-modal="true" aria-labelledby="lesson-modal-title" tabindex="-1" class="bg-white rounded-lg shadow-xl w-full max-w-2xl p-8 m-4">
                <div class="mb-6 flex items-center justify-between">
                    <h2 id="lesson-modal-title" data-dialog-initial-focus tabindex="-1" class="text-2xl font-bold text-neutral-800" x-text="modalTitle"></h2>
                    <button type="button" @click="closeModal" class="text-neutral-500 hover:text-neutral-800" aria-label="{{ __('admin.close_dialog') }}">&times;</button>
                </div>
                <form :action="formAction" method="POST">
                    @csrf
                    <input type="hidden" name="_form_mode" :value="isEditMode ? 'edit' : 'create'">
                    <input type="hidden" name="_record_id" :value="isEditMode ? lesson.id : ''">
                    <template x-if="isEditMode">@method('PUT')</template>

                    @include('superadmin.partials.form-errors')

                    <div class="space-y-6">
                        <div>
                            <label for="module_id" class="block text-sm font-medium">{{ __('admin.parent_module') }}</label>
                            <select name="module_id" id="module_id" x-model="lesson.module_id" required class="mt-1 block w-full border-neutral-300 rounded-md shadow-sm">
                                <option value="">{{ __('admin.select_module') }}</option>
                                @foreach($modules as $module)<option value="{{ $module->id }}">{{ $module->title }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label for="title" class="block text-sm font-medium">{{ __('admin.lesson_title') }}</label>
                            <input type="text" name="title" id="title" x-model="lesson.title" required class="mt-1 block w-full border-neutral-300 rounded-md">
                        </div>
                        <div>
                            <label for="slug" class="block text-sm font-medium">{{ __('admin.slug') }}</label>
                            <input type="text" name="slug" id="slug" x-model="lesson.slug" required class="mt-1 block w-full border-neutral-300 rounded-md">
                        </div>
                        <div>
                            <label for="lesson_order" class="block text-sm font-medium">{{ __('admin.display_order') }}</label>
                            <input type="number" name="order" id="lesson_order" x-model.number="lesson.order" min="0" max="1000000" class="mt-1 block w-full border-neutral-300 rounded-md">
                            <p class="mt-1 text-xs text-neutral-500">{{ __('admin.order_help_module') }}</p>
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

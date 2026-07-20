@extends('layouts.app')

@section('title', Auth::user()->isAdmin() || \App\Models\CurriculumPackage::query()->where('is_active', true)->exists() ? __('admin.legacy_exercises_title') : __('admin.manage_exercises_title'))
@section('bodyClass', 'bg-neutral-100 font-sans')

@section('content')
    @php
        $legacyCurriculumReadOnly = Auth::user()->isAdmin() || \App\Models\CurriculumPackage::active() !== null;
        $exerciseEditMode = old('_form_mode') === 'edit' && (int) old('_record_id') > 0;
        $exerciseInitial = [
            'id' => $exerciseEditMode ? (int) old('_record_id') : null,
            'lesson_id' => old('lesson_id', ''),
            'title' => old('title', ''),
            'type' => old('type', 'matching_game'),
            'content' => is_array(old('content')) ? old('content') : ['pairs' => [['question' => '', 'answer' => '']]],
            'order' => old('order', 0),
        ];
        $exerciseTypeLabels = [
            'spelling_quiz' => __('admin.exercise_types.spelling_quiz'),
            'matching_game' => __('admin.exercise_types.matching_game'),
            'fill_in_the_blank' => __('admin.exercise_types.fill_in_the_blank'),
            'listening_task' => __('admin.exercise_types.listening_task'),
            'speaking_practice' => __('admin.exercise_types.speaking_practice'),
            'sentence_scramble' => __('admin.exercise_types.sentence_scramble'),
            'translation_match' => __('admin.exercise_types.translation_match'),
            'fill_with_options' => __('admin.exercise_types.fill_with_options'),
            'multiple_choice_quiz' => __('admin.exercise_types.multiple_choice_quiz'),
            'fill_multiple_blanks' => __('admin.exercise_types.fill_multiple_blanks'),
            'silent_letter_hunt' => __('admin.exercise_types.silent_letter_hunt'),
            'pronunciation_drill' => __('admin.exercise_types.pronunciation_drill'),
            'sound_sorting' => __('admin.exercise_types.sound_sorting'),
            'sequencing' => __('admin.exercise_types.sequencing'),
        ];
        $exerciseAdminState = base64_encode(json_encode([
            'isModalOpen' => ! $legacyCurriculumReadOnly && $errors->any(),
            'isEditMode' => $exerciseEditMode,
            'modalTitle' => $exerciseEditMode ? __('admin.edit_exercise') : __('admin.create_exercise'),
            'createTitle' => __('admin.create_exercise'),
            'editTitle' => __('admin.edit_exercise'),
            'formAction' => $exerciseEditMode ? route('superadmin.exercises.update', (int) old('_record_id')) : route('superadmin.exercises.store'),
            'createAction' => route('superadmin.exercises.store'),
            'entity' => $exerciseInitial,
        ], JSON_THROW_ON_ERROR));
    @endphp

    <div x-data="exerciseAdmin" data-admin-state="{{ $exerciseAdminState }}">
        <div class="flex min-h-screen flex-col md:h-screen md:flex-row">
            @include('superadmin.sidebar')
            <main class="min-w-0 flex-1 p-6 md:overflow-y-auto md:p-10">
                @include('superadmin.canonical-curriculum-notice')
                <header class="mb-8 flex justify-between items-center">
                    <div>
                        <h1 class="text-3xl font-bold">{{ $legacyCurriculumReadOnly ? __('admin.legacy_exercise_evidence') : __('admin.exercise_management') }}</h1>
                        <p class="text-neutral-500">{{ $legacyCurriculumReadOnly ? __('admin.legacy_evidence_description') : __('admin.exercise_management_description') }}</p>
                    </div>
                    @unless($legacyCurriculumReadOnly)
                        <button type="button" @click="openCreate" class="bg-indigo-600 text-white py-2 px-4 rounded-lg shadow font-semibold hover:bg-indigo-700">
                            <i class="fas fa-plus mr-2" aria-hidden="true"></i> {{ __('admin.create_exercise') }}
                        </button>
                    @endunless
                </header>

                @if(session('success'))
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-4" role="alert"><p>{{ session('success') }}</p></div>
                @endif

                <div class="bg-white rounded-lg shadow-md overflow-x-auto">
                    <table class="w-full text-sm text-left">
                        <thead class="text-xs text-neutral-500 uppercase">
                            <tr class="border-b"><th scope="col" class="px-6 py-3">{{ __('admin.exercise_title') }}</th><th scope="col" class="px-6 py-3">{{ __('admin.type') }}</th><th scope="col" class="px-6 py-3">{{ __('admin.parent_lesson') }}</th><th scope="col" class="px-6 py-3">{{ __('admin.order') }}</th><th scope="col" class="px-6 py-3">{{ $legacyCurriculumReadOnly ? __('Evidence status') : __('admin.actions') }}</th></tr>
                        </thead>
                        <tbody class="divide-y">
                            @forelse($exercises as $exercise)
                                <tr>
                                    <td class="px-6 py-4 font-semibold">{{ $exercise->title }}</td>
                                    <td class="px-6 py-4"><span class="font-mono text-xs bg-neutral-200 text-neutral-700 px-2 py-1 rounded">{{ $exercise->type }}</span></td>
                                    <td class="px-6 py-4">{{ $exercise->lesson->title ?? __('admin.not_available') }}</td>
                                    <td class="px-6 py-4">{{ $exercise->order }}</td>
                                    <td class="px-6 py-4 flex items-center gap-3">
                                        @if($legacyCurriculumReadOnly)
                                            <span class="text-xs font-semibold text-neutral-700">{{ __('Read-only evidence') }}</span>
                                        @else
                                            <button type="button" @click="openEdit" data-record="{{ base64_encode($exercise->toJson()) }}" data-action="{{ route('superadmin.exercises.update', $exercise) }}" class="font-medium text-blue-600 hover:underline" aria-label="{{ __('admin.edit_named', ['name' => $exercise->title]) }}"><i class="fas fa-edit" aria-hidden="true"></i></button>
                                            <form action="{{ route('superadmin.exercises.destroy', $exercise) }}" method="POST" data-confirm-submit="{{ __('admin.confirm_delete_named', ['name' => $exercise->title]) }}">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="font-medium text-red-600 hover:underline" aria-label="{{ __('admin.delete_named', ['name' => $exercise->title]) }}"><i class="fas fa-trash" aria-hidden="true"></i></button>
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
                                                        'record_id' => $exercise->id,
                                                        'title' => $exercise->title,
                                                        'type' => $exercise->type,
                                                        'parent_lesson' => $exercise->lesson->title ?? null,
                                                        'display_order' => $exercise->order,
                                                        'content' => $exercise->content,
                                                    ]])
                                                </div>
                                            </details>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr><td colspan="5" class="px-6 py-4 text-center">{{ __('admin.no_exercises') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="p-4">{{ $exercises->links() }}</div>
                </div>
            </main>
        </div>

        @unless($legacyCurriculumReadOnly)
        <template x-if="isModalOpen">
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-neutral-900/20">
            <div @click.away="closeModal" @keydown="handleDialogKeydown" role="dialog" aria-modal="true" aria-labelledby="exercise-modal-title" tabindex="-1" class="bg-white rounded-lg shadow-xl w-full max-w-3xl p-8 m-4 max-h-[90vh] flex flex-col">
                <div class="mb-6 flex items-center justify-between">
                    <h2 id="exercise-modal-title" data-dialog-initial-focus tabindex="-1" class="text-2xl font-bold text-neutral-800" x-text="modalTitle"></h2>
                    <button type="button" @click="closeModal" class="text-neutral-500 hover:text-neutral-800" aria-label="{{ __('admin.close_dialog') }}">&times;</button>
                </div>

                <form :action="formAction" method="POST" class="flex-1 overflow-y-auto pr-2">
                    @csrf
                    <input type="hidden" name="_form_mode" :value="isEditMode ? 'edit' : 'create'">
                    <input type="hidden" name="_record_id" :value="isEditMode ? exercise.id : ''">
                    <template x-if="isEditMode">@method('PUT')</template>
                    <template x-if="isEditMode"><input type="hidden" name="type" x-model="exercise.type"></template>

                    @include('superadmin.partials.form-errors')

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="exercise_lesson_id" class="block text-sm font-medium">{{ __('admin.parent_lesson') }}</label>
                            <select id="exercise_lesson_id" name="lesson_id" x-model="exercise.lesson_id" required class="mt-1 block w-full border-neutral-300 rounded-md">
                                <option value="">{{ __('admin.select_lesson') }}</option>
                                @foreach($lessons as $lesson)<option value="{{ $lesson->id }}">{{ $lesson->title }}</option>@endforeach
                            </select>
                        </div>
                        <div>
                            <label for="exercise_title" class="block text-sm font-medium">{{ __('admin.exercise_title') }}</label>
                            <input id="exercise_title" type="text" name="title" x-model="exercise.title" required maxlength="255" class="mt-1 block w-full border-neutral-300 rounded-md">
                        </div>
                        <div>
                            <label for="exercise_type" class="block text-sm font-medium">{{ __('admin.exercise_type') }}</label>
                            <select id="exercise_type" :name="isEditMode ? null : 'type'" x-model="exercise.type" @change="resetContent" :disabled="isEditMode" required class="mt-1 block w-full border-neutral-300 rounded-md disabled:bg-neutral-100">
                                @foreach($exerciseTypeLabels as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                            </select>
                            <template x-if="isEditMode"><p class="mt-1 text-xs text-neutral-500">{{ __('admin.type_immutable') }}</p></template>
                        </div>
                        <div>
                            <label for="exercise_order" class="block text-sm font-medium">{{ __('admin.order') }}</label>
                            <input id="exercise_order" type="number" name="order" x-model.number="exercise.order" min="0" class="mt-1 block w-full border-neutral-300 rounded-md">
                        </div>
                    </div>

                    <div class="mt-6 border-t pt-5 space-y-4">
                        <h3 class="text-lg font-medium text-neutral-800">{{ __('admin.exercise_content') }}</h3>

                        <template x-if="exercise.type === 'matching_game'">
                            <div class="space-y-3">
                                <template x-for="(pair, index) in exercise.content.pairs" :key="index"><div class="grid grid-cols-[1fr_1fr_auto] gap-2"><input required type="text" :name="contentObjectName('pairs', index, 'question')" x-model="pair.question" placeholder="{{ __('admin.question') }}" aria-label="{{ __('admin.question') }}" class="rounded-md border-neutral-300"><input required type="text" :name="contentObjectName('pairs', index, 'answer')" x-model="pair.answer" placeholder="{{ __('admin.answer') }}" aria-label="{{ __('admin.answer') }}" class="rounded-md border-neutral-300"><button type="button" @click="removeAt('pairs', index)" :disabled="exercise.content.pairs.length === 1" aria-label="{{ __('admin.remove_pair') }}" class="text-red-600 disabled:opacity-40">&times;</button></div></template>
                                <button type="button" @click="addPair" class="text-sm text-indigo-600">+ {{ __('admin.add_pair') }}</button>
                            </div>
                        </template>

                        <template x-if="exercise.type === 'spelling_quiz'"><div class="space-y-3"><label class="block text-sm">{{ __('admin.correct_answer') }}<input required type="text" name="content[correct_answer]" x-model="exercise.content.correct_answer" class="mt-1 w-full rounded-md border-neutral-300"></label><label class="block text-sm">{{ __('admin.spoken_text') }}<input required type="text" name="content[prompt_text]" x-model="exercise.content.prompt_text" class="mt-1 w-full rounded-md border-neutral-300"></label><label class="block text-sm">{{ __('admin.optional_public_audio_url') }}<input type="text" name="content[audio_url]" x-model="exercise.content.audio_url" placeholder="/storage/curriculum/exercises/example.mp3" class="mt-1 w-full rounded-md border-neutral-300"><span class="mt-1 block text-xs text-neutral-500">{{ __('admin.audio_url_help') }}</span></label></div></template>

                        <template x-if="exercise.type === 'fill_in_the_blank'"><div class="space-y-3"><label class="block text-sm">{{ __('admin.sentence_template') }}<input required type="text" name="content[sentence_template]" x-model="exercise.content.sentence_template" class="mt-1 w-full rounded-md border-neutral-300"></label><label class="block text-sm">{{ __('admin.correct_answer') }}<input required type="text" name="content[correct_answer]" x-model="exercise.content.correct_answer" class="mt-1 w-full rounded-md border-neutral-300"></label></div></template>

                        <template x-if="exercise.type === 'listening_task'"><div class="space-y-3"><label class="block text-sm">{{ __('admin.instruction') }}<input type="text" name="content[instruction]" x-model="exercise.content.instruction" class="mt-1 w-full rounded-md border-neutral-300"></label><div><p class="text-sm font-medium">{{ __('admin.options') }}</p><template x-for="(option, index) in exercise.content.options" :key="index"><div class="mt-2 flex gap-2"><input required type="text" :name="contentListName('options', index)" x-model="exercise.content.options[index]" aria-label="{{ __('admin.option') }}" class="flex-1 rounded-md border-neutral-300"><button type="button" @click="removeAt('options', index)" :disabled="exercise.content.options.length <= 2" aria-label="{{ __('admin.remove_option') }}" class="text-red-600 disabled:opacity-40">&times;</button></div></template><button type="button" @click="addString('options')" class="mt-2 text-sm text-indigo-600">+ {{ __('admin.add_option') }}</button></div><label class="block text-sm">{{ __('admin.correct_answer') }}<input required type="text" name="content[correct_answer]" x-model="exercise.content.correct_answer" class="mt-1 w-full rounded-md border-neutral-300"></label></div></template>

                        <template x-if="usesPromptText"><label class="block text-sm">{{ __('admin.text_prompt') }}<textarea required name="content[prompt_text]" x-model="exercise.content.prompt_text" rows="3" class="mt-1 w-full rounded-md border-neutral-300"></textarea></label></template>

                        <template x-if="exercise.type === 'sentence_scramble'"><label class="block text-sm">{{ __('admin.correct_sentence') }}<input required type="text" name="content[sentence]" x-model="exercise.content.sentence" class="mt-1 w-full rounded-md border-neutral-300"></label></template>

                        <template x-if="exercise.type === 'translation_match'"><div class="space-y-3"><label class="block text-sm">{{ __('admin.word_or_question') }}<input required type="text" name="content[question_word]" x-model="exercise.content.question_word" class="mt-1 w-full rounded-md border-neutral-300"></label><div><p class="text-sm font-medium">{{ __('admin.options') }}</p><template x-for="(option, index) in exercise.content.options" :key="index"><div class="mt-2 flex gap-2"><input required type="text" :name="contentListName('options', index)" x-model="exercise.content.options[index]" aria-label="{{ __('admin.option') }}" class="flex-1 rounded-md border-neutral-300"><button type="button" @click="removeAt('options', index)" :disabled="exercise.content.options.length <= 2" aria-label="{{ __('admin.remove_option') }}" class="text-red-600 disabled:opacity-40">&times;</button></div></template><button type="button" @click="addString('options')" class="mt-2 text-sm text-indigo-600">+ {{ __('admin.add_option') }}</button></div><label class="block text-sm">{{ __('admin.correct_answer') }}<input required type="text" name="content[correct_answer]" x-model="exercise.content.correct_answer" class="mt-1 w-full rounded-md border-neutral-300"></label></div></template>

                        <template x-if="exercise.type === 'fill_with_options'"><div class="space-y-3"><div><p class="text-sm font-medium">{{ __('admin.sentence_parts') }}</p><template x-for="(part, index) in exercise.content.sentence_parts" :key="index"><div class="mt-2 flex gap-2"><input type="text" :name="contentListName('sentence_parts', index)" x-model="exercise.content.sentence_parts[index]" aria-label="{{ __('admin.sentence_part') }}" class="flex-1 rounded-md border-neutral-300"><button type="button" @click="removeAt('sentence_parts', index)" :disabled="exercise.content.sentence_parts.length <= 2" aria-label="{{ __('admin.remove_sentence_part') }}" class="text-red-600 disabled:opacity-40">&times;</button></div></template><button type="button" @click="addString('sentence_parts')" class="mt-2 text-sm text-indigo-600">+ {{ __('admin.add_sentence_part') }}</button></div><div><p class="text-sm font-medium">{{ __('admin.options') }}</p><template x-for="(option, index) in exercise.content.options" :key="index"><div class="mt-2 flex gap-2"><input required type="text" :name="contentListName('options', index)" x-model="exercise.content.options[index]" aria-label="{{ __('admin.option') }}" class="flex-1 rounded-md border-neutral-300"><button type="button" @click="removeAt('options', index)" :disabled="exercise.content.options.length <= 2" aria-label="{{ __('admin.remove_option') }}" class="text-red-600 disabled:opacity-40">&times;</button></div></template><button type="button" @click="addString('options')" class="mt-2 text-sm text-indigo-600">+ {{ __('admin.add_option') }}</button></div><label class="block text-sm">{{ __('admin.correct_answer') }}<input required type="text" name="content[correct_answer]" x-model="exercise.content.correct_answer" class="mt-1 w-full rounded-md border-neutral-300"></label></div></template>

                        <template x-if="exercise.type === 'multiple_choice_quiz'"><div class="space-y-3"><label class="block text-sm">{{ __('admin.question') }}<input required type="text" name="content[question_text]" x-model="exercise.content.question_text" class="mt-1 w-full rounded-md border-neutral-300"></label><div><p class="text-sm font-medium">{{ __('admin.options') }}</p><template x-for="(option, index) in exercise.content.options" :key="index"><div class="mt-2 flex gap-2"><input required type="text" :name="contentListName('options', index)" x-model="exercise.content.options[index]" aria-label="{{ __('admin.option') }}" class="flex-1 rounded-md border-neutral-300"><button type="button" @click="removeAt('options', index)" :disabled="exercise.content.options.length <= 2" aria-label="{{ __('admin.remove_option') }}" class="text-red-600 disabled:opacity-40">&times;</button></div></template><button type="button" @click="addString('options')" class="mt-2 text-sm text-indigo-600">+ {{ __('admin.add_option') }}</button></div><label class="block text-sm">{{ __('admin.correct_answer') }}<input required type="text" name="content[correct_answer]" x-model="exercise.content.correct_answer" class="mt-1 w-full rounded-md border-neutral-300"></label></div></template>

                        <template x-if="exercise.type === 'fill_multiple_blanks'"><div class="space-y-4"><div><p class="text-sm font-medium">{{ __('admin.sentence_parts') }}</p><template x-for="(part, index) in exercise.content.sentence_parts" :key="index"><div class="mt-2 flex gap-2"><input type="text" :name="contentListName('sentence_parts', index)" x-model="exercise.content.sentence_parts[index]" aria-label="{{ __('admin.sentence_part') }}" class="flex-1 rounded-md border-neutral-300"><button type="button" @click="removeAt('sentence_parts', index)" :disabled="exercise.content.sentence_parts.length <= 1" aria-label="{{ __('admin.remove_sentence_part') }}" class="text-red-600 disabled:opacity-40">&times;</button></div></template><button type="button" @click="addString('sentence_parts')" class="mt-2 text-sm text-indigo-600">+ {{ __('admin.add_sentence_part') }}</button></div><div><p class="text-sm font-medium">{{ __('admin.answers') }}</p><template x-for="(answer, index) in exercise.content.correct_answers" :key="index"><div class="mt-2 flex gap-2"><input required type="text" :name="contentListName('correct_answers', index)" x-model="exercise.content.correct_answers[index]" aria-label="{{ __('admin.answer') }}" class="flex-1 rounded-md border-neutral-300"><button type="button" @click="removeAt('correct_answers', index)" :disabled="exercise.content.correct_answers.length <= 1" aria-label="{{ __('admin.remove_answer') }}" class="text-red-600 disabled:opacity-40">&times;</button></div></template><button type="button" @click="addString('correct_answers')" class="mt-2 text-sm text-indigo-600">+ {{ __('admin.add_answer') }}</button></div></div></template>

                        <template x-if="exercise.type === 'silent_letter_hunt'"><div class="space-y-3"><label class="block text-sm">{{ __('admin.sentence') }}<input required type="text" name="content[sentence]" x-model="exercise.content.sentence" class="mt-1 w-full rounded-md border-neutral-300"></label><template x-for="(word, index) in exercise.content.words" :key="index"><div class="grid grid-cols-[1fr_10rem_auto] gap-2"><input required type="text" :name="contentObjectName('words', index, 'word')" x-model="word.word" placeholder="{{ __('admin.word') }}" aria-label="{{ __('admin.word') }}" class="rounded-md border-neutral-300"><input required type="number" min="0" :name="contentObjectName('words', index, 'silent_letter_index')" x-model.number="word.silent_letter_index" aria-label="{{ __('admin.silent_letter_index') }}" class="rounded-md border-neutral-300"><button type="button" @click="removeAt('words', index)" :disabled="exercise.content.words.length === 1" aria-label="{{ __('admin.remove_word') }}" class="text-red-600 disabled:opacity-40">&times;</button></div></template><button type="button" @click="addSilentWord" class="text-sm text-indigo-600">+ {{ __('admin.add_word') }}</button></div></template>

                        <template x-if="exercise.type === 'sound_sorting'"><div class="space-y-5"><div><p class="text-sm font-medium">{{ __('admin.categories') }}</p><template x-for="(category, index) in exercise.content.categories" :key="index"><div class="mt-2 grid grid-cols-[1fr_1fr_auto] gap-2"><input required type="text" :name="contentObjectName('categories', index, 'id')" x-model="category.id" placeholder="{{ __('admin.unique_id') }}" aria-label="{{ __('admin.unique_id') }}" class="rounded-md border-neutral-300"><input required type="text" :name="contentObjectName('categories', index, 'name')" x-model="category.name" placeholder="{{ __('Name') }}" aria-label="{{ __('admin.category_name') }}" class="rounded-md border-neutral-300"><button type="button" @click="removeAt('categories', index)" :disabled="exercise.content.categories.length === 1" aria-label="{{ __('admin.remove_category') }}" class="text-red-600 disabled:opacity-40">&times;</button></div></template><button type="button" @click="addSoundCategory" class="mt-2 text-sm text-indigo-600">+ {{ __('admin.add_category') }}</button></div><div><p class="text-sm font-medium">{{ __('admin.words') }}</p><template x-for="(word, index) in exercise.content.words" :key="index"><div class="mt-2 grid grid-cols-[1fr_1fr_auto] gap-2"><input required type="text" :name="contentObjectName('words', index, 'word')" x-model="word.word" placeholder="{{ __('admin.word') }}" aria-label="{{ __('admin.word') }}" class="rounded-md border-neutral-300"><select required :name="contentObjectName('words', index, 'category_id')" x-model="word.category_id" aria-label="{{ __('admin.category') }}" class="rounded-md border-neutral-300"><option value="">{{ __('admin.select_category') }}</option><template x-for="category in exercise.content.categories" :key="category.id"><option :value="category.id" x-text="category.name || category.id"></option></template></select><button type="button" @click="removeAt('words', index)" :disabled="exercise.content.words.length === 1" aria-label="{{ __('admin.remove_word') }}" class="text-red-600 disabled:opacity-40">&times;</button></div></template><button type="button" @click="addSoundWord" class="mt-2 text-sm text-indigo-600">+ {{ __('admin.add_word') }}</button></div></div></template>

                        <template x-if="exercise.type === 'sequencing'"><div><p class="text-sm font-medium">{{ __('admin.steps_in_order') }}</p><template x-for="(step, index) in exercise.content.steps" :key="index"><div class="mt-2 flex gap-2"><input required type="text" :name="contentListName('steps', index)" x-model="exercise.content.steps[index]" aria-label="{{ __('admin.step') }}" class="flex-1 rounded-md border-neutral-300"><button type="button" @click="removeAt('steps', index)" :disabled="exercise.content.steps.length <= 2" aria-label="{{ __('admin.remove_step') }}" class="text-red-600 disabled:opacity-40">&times;</button></div></template><button type="button" @click="addString('steps')" class="mt-2 text-sm text-indigo-600">+ {{ __('admin.add_step') }}</button></div></template>
                    </div>

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

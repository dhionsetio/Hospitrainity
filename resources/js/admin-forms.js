function decodeJson(value) {
    const bytes = Uint8Array.from(atob(value), (character) => character.charCodeAt(0));

    return JSON.parse(new TextDecoder().decode(bytes));
}

function clone(value) {
    return JSON.parse(JSON.stringify(value));
}

function adminComponent(entityKey, createTitle, editTitle, emptyEntity) {
    return () => ({
        isModalOpen: false,
        isEditMode: false,
        modalTitle: createTitle,
        createTitle,
        editTitle,
        formAction: '',
        createAction: '',
        returnFocusElement: null,
        [entityKey]: emptyEntity(),

        init() {
            const state = decodeJson(this.$root.dataset.adminState);
            this.isModalOpen = state.isModalOpen;
            this.isEditMode = state.isEditMode;
            this.modalTitle = state.modalTitle;
            this.createTitle = state.createTitle || createTitle;
            this.editTitle = state.editTitle || editTitle;
            this.formAction = state.formAction;
            this.createAction = state.createAction;
            this[entityKey] = clone(state.entity);

            if (this.isModalOpen) {
                this.focusDialog();
            }
        },

        openCreate(event) {
            this.returnFocusElement = event?.currentTarget ?? document.activeElement;
            this.isEditMode = false;
            this.modalTitle = this.createTitle;
            this.formAction = this.createAction;
            this[entityKey] = emptyEntity();
            this.isModalOpen = true;
            this.focusDialog();
        },

        openEdit(event) {
            this.returnFocusElement = event.currentTarget;
            this.isEditMode = true;
            this.modalTitle = this.editTitle;
            this.formAction = event.currentTarget.dataset.action;
            this[entityKey] = decodeJson(event.currentTarget.dataset.record);
            this.isModalOpen = true;
            this.focusDialog();
        },

        closeModal() {
            this.isModalOpen = false;
            const returnTarget = this.returnFocusElement;
            this.$nextTick(() => {
                if (returnTarget instanceof HTMLElement && returnTarget.isConnected) {
                    returnTarget.focus();
                }
            });
        },

        focusDialog() {
            this.$nextTick(() => {
                const dialog = this.$root.querySelector('[role="dialog"]');
                const target = dialog?.querySelector('#admin-form-errors, [data-dialog-initial-focus]');
                target?.focus();
            });
        },

        handleDialogKeydown(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                event.stopPropagation();
                this.closeModal();

                return;
            }

            if (event.key !== 'Tab') return;

            const dialog = event.currentTarget;
            const focusable = Array.from(dialog.querySelectorAll(
                'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
            )).filter((element) => element instanceof HTMLElement && !element.hidden);

            if (focusable.length === 0) {
                event.preventDefault();
                dialog.focus();

                return;
            }

            const first = focusable[0];
            const last = focusable[focusable.length - 1];
            const activeElement = document.activeElement;

            if (event.shiftKey && (activeElement === first || !focusable.includes(activeElement))) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        },

        itemName(index, field) {
            return `items[${index}][${field}]`;
        },

        itemElementId(prefix, index) {
            return `${prefix}_${index}`;
        },

        fileName(url) {
            return typeof url === 'string' ? url.split('/').pop() : '';
        },
    });
}

const emptyModule = () => ({
    id: null,
    title: '',
    description: '',
    level: 'beginner',
    order: 0,
    is_published: false,
});

const emptyLesson = () => ({
    id: null,
    module_id: '',
    title: '',
    slug: '',
    order: 0,
});

const emptyVocabulary = () => ({
    id: null,
    lesson_id: '',
    category: '',
    order: 0,
    items: [{ term: '', details: '', media_url: null, order: 0 }],
});

const emptyMaterial = () => ({
    id: null,
    lesson_id: '',
    type: 'Teks',
    order: 0,
    items: [{ title: '', description: '', url: null, audio_url: null, order: 0 }],
});

function vocabularyAdminComponent() {
    const component = adminComponent(
        'vocab',
        'Create vocabulary',
        'Edit vocabulary',
        emptyVocabulary,
    )();

    return {
        ...component,

        addItem() {
            this.vocab.items.push({
                term: '',
                details: '',
                media_url: null,
                order: this.vocab.items.length,
            });
        },

        removeItem(index) {
            this.vocab.items.splice(index, 1);
        },
    };
}

function materialAdminComponent() {
    const component = adminComponent(
        'material',
        'Create material',
        'Edit material',
        emptyMaterial,
    )();

    return {
        ...component,

        addItem() {
            this.material.items.push({
                title: '',
                description: '',
                url: null,
                audio_url: null,
                order: this.material.items.length,
            });
        },

        removeItem(index) {
            this.material.items.splice(index, 1);
        },
    };
}

const exerciseDefaults = {
    spelling_quiz: { correct_answer: '', prompt_text: '', audio_url: '' },
    matching_game: { pairs: [{ question: '', answer: '' }] },
    fill_in_the_blank: { sentence_template: '___', correct_answer: '' },
    listening_task: { instruction: '', options: ['', ''], correct_answer: '' },
    speaking_practice: { prompt_text: '' },
    sentence_scramble: { sentence: '' },
    translation_match: { question_word: '', options: ['', ''], correct_answer: '' },
    fill_with_options: { sentence_parts: ['', ''], options: ['', ''], correct_answer: '' },
    multiple_choice_quiz: { question_text: '', options: ['', ''], correct_answer: '' },
    fill_multiple_blanks: { sentence_parts: ['', ''], correct_answers: [''] },
    silent_letter_hunt: { sentence: '', words: [{ word: '', silent_letter_index: 0 }] },
    pronunciation_drill: { prompt_text: '' },
    sound_sorting: {
        categories: [{ id: 'group-1', name: '' }],
        words: [{ word: '', category_id: 'group-1' }],
    },
    sequencing: { steps: ['', ''] },
};

function defaultExerciseContent(type) {
    return clone(exerciseDefaults[type] || {});
}

function emptyExercise() {
    return {
        id: null,
        lesson_id: '',
        title: '',
        type: 'matching_game',
        content: defaultExerciseContent('matching_game'),
        order: 0,
    };
}

function exerciseAdminComponent() {
    const component = adminComponent(
        'exercise',
        'Create exercise',
        'Edit exercise',
        emptyExercise,
    )();

    return {
        ...component,

        openEdit(event) {
            component.openEdit.call(this, event);
            if (!this.exercise.content || typeof this.exercise.content !== 'object') {
                this.exercise.content = defaultExerciseContent(this.exercise.type);
            }
        },

        resetContent() {
            this.exercise.content = defaultExerciseContent(this.exercise.type);
        },

        addString(field) {
            this.exercise.content[field].push('');
        },

        removeAt(field, index) {
            this.exercise.content[field].splice(index, 1);
        },

        contentListName(field, index) {
            return `content[${field}][${index}]`;
        },

        contentObjectName(field, index, property) {
            return `content[${field}][${index}][${property}]`;
        },

        get usesPromptText() {
            return ['speaking_practice', 'pronunciation_drill'].includes(this.exercise.type);
        },

        addPair() {
            this.exercise.content.pairs.push({ question: '', answer: '' });
        },

        addSilentWord() {
            this.exercise.content.words.push({ word: '', silent_letter_index: 0 });
        },

        addSoundCategory() {
            this.exercise.content.categories.push({
                id: `group-${this.exercise.content.categories.length + 1}`,
                name: '',
            });
        },

        addSoundWord() {
            this.exercise.content.words.push({
                word: '',
                category_id: this.exercise.content.categories[0]?.id || '',
            });
        },
    };
}

export function registerAdminComponents(Alpine) {
    Alpine.data('moduleAdmin', adminComponent('module', 'Create module', 'Edit module', emptyModule));
    Alpine.data('lessonAdmin', adminComponent('lesson', 'Create lesson', 'Edit lesson', emptyLesson));
    Alpine.data('vocabularyAdmin', vocabularyAdminComponent);
    Alpine.data('materialAdmin', materialAdminComponent);
    Alpine.data('exerciseAdmin', exerciseAdminComponent);
}

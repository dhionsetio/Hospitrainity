# ADM-4 canonical exercise-template contract

**Decision date:** 19 July 2026  
**Registry version:** `1.0.0`  
**Implementation:** `app/Services/Curriculum/CanonicalExerciseTemplateRegistry.php`  
**Status:** 14 legacy names inventoried; 12 enabled; 2 explicitly unavailable

## Decision

The 14 retained legacy exercise names are authoring presets, not learner renderers and not a second curriculum model. An enabled preset compiles to versioned canonical `activity`, `prompt-item`, `answer-model`, `feedback-model`, and optional `rubric` entities. Laravel draft preview and active delivery both render `resources/views/curriculum/activity.blade.php`; publication still passes through `CanonicalPackageReader`.

Correctness is authoritative on the server. The browser submits a choice ID, ordered token IDs, a bounded closed response, or an explicit open-response self-check. It never submits or computes a trusted score. Choice/token UUIDs and prompt codes are server-owned and remain separate from editable labels.

## Complete 14-type disposition

| Legacy authoring name | Status | Canonical response form | Canonical scoring policy | Authoring interpretation |
|---|---|---|---|---|
| `spelling_quiz` | Unavailable | `short_text` | `objective_normalized_closed` | Mapped and implemented behind the registry, but not exposed until an equivalent audio-only alternative/accommodation can be approved without revealing the spelling answer. |
| `matching_game` | Enabled | `selection` | `objective_choice` / exact | Each left-side item becomes a prompt; all reviewed right-side answers become stable-ID choices. |
| `fill_in_the_blank` | Enabled | `short_text` | `objective_normalized_closed` | One or more reviewed answers per prompt; normalization is lowercase, Unicode letter/number aware, punctuation-insensitive, and whitespace-collapsed. |
| `listening_task` | Unavailable | `selection` | `objective_choice` / exact | Mapped and implemented behind the registry, but not exposed for the same unresolved audio-equivalent issue as spelling. |
| `speaking_practice` | Enabled | `role_play` | `rubric_self_assessment` | Open language is never auto-graded. The learner rehearses/types transient text, confirms self-check, and compares a model plus required rubric. |
| `sentence_scramble` | Enabled | `ordering` | `objective_ordered` | Author enters correct line order; learner receives a non-answer order and submits a stable token permutation. |
| `translation_match` | Enabled | `selection` | `objective_choice` / exact | Each source expression becomes a prompt with the shared reviewed translation set as stable-ID choices. |
| `fill_with_options` | Enabled | `selection` | `objective_choice` / exact | Each answer must match one submitted allowed option before a correct-choice ID is stored. |
| `multiple_choice_quiz` | Enabled | `selection` | `objective_choice` / exact | Two to ten distinct options; exactly one correct option ID. |
| `fill_multiple_blanks` | Enabled | `short_text` | `objective_normalized_closed` | Each blank is a separate prompt/result so one answer cannot conceal another blank's state. |
| `silent_letter_hunt` | Enabled | `selection` | `objective_choice` / exact | Choices are derived from the submitted word's distinct Unicode letters; the correct letter must actually occur in the word. |
| `pronunciation_drill` | Enabled | `role_play` | `model_self_check` | No recording and no speech-recognition score. Raw rehearsal text is not persisted. |
| `sound_sorting` | Enabled | `selection` | `objective_choice` / exact | Each word is a prompt and the distinct reviewed category names become stable-ID choices. |
| `sequencing` | Enabled | `ordering` | `objective_ordered` | Service steps/statements are stored as stable tokens and checked as an exact server-side permutation. |

## Shared scoring policies

The registry declares six policies even though the 14 retained presets do not use every policy:

| Canonical mode | Policy | Server score | Persisted learner response |
|---|---|---:|---|
| `objective_choice` | Exact choice | Yes | Stable choice ID |
| `objective_normalized_closed` | Reviewed normalized closed response | Yes | Normalized response only |
| `objective_ordered` | Ordered response | Yes | Stable token IDs |
| `model_self_check` | Model-based self-check | No | Completion/self-check metadata only |
| `rubric_self_assessment` | Rubric-based self-assessment | No | Completion/self-check metadata only |
| `unscored_self_report` | Unscored confidence | No | Bounded rating |

An answer example never turns open language into an objective item. Raw role-play/pronunciation/free-text responses and audio recordings remain unpersisted.

## Validation and identity rules

- Nested request arrays allow only `code`, `stem`, `answer`, `options`, `tokens`, `model_answer`, and `feedback`; unknown fields are rejected.
- Item cardinality is declared once in the registry and enforced by the request, compiler workspace, package reader, and authoring UI.
- Submitted existing prompt codes must belong to that draft/activity. New prompt codes and all choice/token UUIDs are generated by the server.
- Correct choice IDs must reference submitted choices. Correct orders must be a complete permutation of submitted token IDs. Closed accepted answers must be non-empty and unique after normalization.
- Stable IDs are preserved across label edits and item reordering. Duplication produces a new activity, prompt, answer, feedback, rubric, choice, and token identity set.
- A canonical learner section accepts one activity in the current view model. Creation/duplication therefore requires a section without another non-archived activity; the UI and transaction both enforce it.
- Draft and activity revisions use optimistic locking. Published entities are never edited in place: a new draft clones the published version, while attempts retain the package/content version the learner saw.
- Draft preview posts to a policy-protected, throttled evaluator that consumes the same request rules, scorer, normalization, model, and feedback assembly as active delivery but never creates attempts, responses, events, or progress.
- Audio asset references, should the unavailable types later be approved, are private/digest-addressed and checked against the same draft. Descriptions that contain an accepted answer are rejected, but that safeguard alone does not resolve the equivalent-alternative requirement.

## Accessibility and renderer contract

All enabled templates reduce to the canonical native-control renderer: radio groups, text inputs/textareas, `<select>`-based ordering with Move up/Move down buttons, explicit self-check checkboxes, and semantic rubric tables. Item authoring also uses named buttons, a polite live region, focus retention, minimum/maximum controls, and server error summaries. No drag-only path is required.

`spelling_quiz` and `listening_task` are deliberately not enabled. WCAG 2.2 SC 1.2.1 requires equivalent information for prerecorded audio-only content (unless it is clearly labeled media-equivalent text). A brief description is not an equivalent transcript; a transcript shown before answering can disclose the assessed answer. The product owner needs an approved accommodation/equivalent-alternative design and human accessibility review before changing `enabled` to `true`.

Automated DOM/feature checks do not constitute an independent assistive-technology audit. Screen-reader/browser combinations and the semantic quality of every authored prompt/model/rubric remain human release gates.

## Primary sources used

- Laravel 12 validation: nested arrays and explicit allowed keys — https://laravel.com/docs/12.x/validation#validating-nested-array-input
- OWASP Input Validation Cheat Sheet: allowlist and server-side validation — https://cheatsheetseries.owasp.org/cheatsheets/Input_Validation_Cheat_Sheet.html
- 1EdTech QTI 3 guide and information model: response identifiers and correct-response references — https://developers.imsglobal.org/spec/qti/v3p0/guide and https://www.imsglobal.org/sites/default/files/spec/qti/v3/info/index.html
- WCAG 2.2 normative recommendation — https://www.w3.org/TR/WCAG22/
- W3C Understanding SC 1.2.1, prerecorded audio-only alternatives — https://www.w3.org/WAI/WCAG22/Understanding/audio-only-and-video-only-prerecorded.html
- W3C Understanding SC 1.3.3, instructions that do not rely only on sensory characteristics — https://www.w3.org/WAI/WCAG22/Understanding/sensory-characteristics.html
- W3C Understanding SC 3.3.1, identified input errors — https://www.w3.org/WAI/WCAG22/Understanding/error-identification.html

## Reproducible verification

Run:

```powershell
C:\php\php.exe artisan test tests/Feature/CurriculumExerciseAuthoringTest.php
npm run test:js
npm run lint:js
npm run build
C:\php\php.exe artisan test
C:\php\php.exe vendor/bin/pint --test
```

The focused PHP test proves the 14-name inventory, both unavailable gates, all 12 enabled create/validate/preview paths, cross-role authorization, stable IDs, server answer references, reorder, duplicate, publication, exact Blade-view parity, and versioned attempt preservation. The Node test proves keyboard-button reordering/add/remove and submitted-name renumbering.

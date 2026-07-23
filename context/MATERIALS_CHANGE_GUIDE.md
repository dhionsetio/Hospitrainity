# Materials Change Guide

## Product goal

Hospitrainity helps learners practise workplace English for hospitality while keeping personal learning evidence separate from institution-attributed evidence. Learning content is canonical, versioned, reviewable, and projected into both the Laravel learner experience and an offline standalone artifact.

## Content hierarchy

The canonical hierarchy is:

```text
curriculum package
└── chapter
    ├── outcome references
    └── lesson section
        ├── ordered learning blocks
        └── activity
            ├── prompt items
            ├── answer/feedback models
            └── optional rubric
```

The ZIP includes only navigation-level chapter, section, and activity metadata. Request or attach the exact authoritative target content before rewriting its wording.

## Constraints a revision must preserve

- Keep stable identifiers unless the owner explicitly authorizes a migration.
- Preserve chapter/section/activity hierarchy and intentional ordering.
- Keep source English faithful; add Indonesian interface translation only when it is approved and semantically equivalent.
- Use the registered response and scoring contract. Objective responses are server-validated; open language is model/rubric self-check, not objective auto-grading.
- Keep choices, tokens, and prompts keyboard operable and understandable without color alone.
- Audio-dependent exercises remain unavailable until an approved equivalent-audio and accommodation policy exists.
- Do not introduce timed dependency, forced recording, or retention of raw open writing/speech responses without an explicit reviewed policy change.
- Keep completion language distinct from mastery or proficiency claims.
- Preserve hospitality workplace relevance, polite register, and clear learner instructions.
- Treat CEFR/accessibility/source-rights claims as requiring their recorded human review; do not infer approval from a machine-readable field.
- Change canonical material through a new reviewed draft/version. Never hand-edit generated standalone output or database projections.

## Recommended workflow

1. Locate the target in `curriculum.json` and record its code and parent context.
2. Obtain the exact current target material from the owner or authoritative source.
3. Confirm the intended learner audience, skill, hospitality situation, and desired difficulty.
4. State whether the change affects only wording or also response form, scoring, media, outcomes, timing, or navigation.
5. Draft the smallest coherent change with before/after text.
6. Check answer/feedback alignment and distractor validity when objective assessment is involved.
7. Check accessibility, privacy, localization, and source/rights consequences.
8. Recommend validation in the isolated curriculum draft preview.
9. Require the normal validation, review, approval, and publication gates before learner delivery.

## What to request when context is missing

Ask only for what the change needs, such as:

- exact chapter/section/activity/prompt code;
- current learner-facing text;
- choices, correct-answer model, feedback, or rubric if affected;
- intended learner level and hospitality role/situation;
- approved source or authority constraints;
- required English/Indonesian handling;
- media and accessibility requirements;
- acceptance criteria.

Never fabricate missing current material.
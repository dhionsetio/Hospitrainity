# Canonical package layout

The canonical curriculum package is the reviewed, version-controlled source of
truth for compiled outputs. It lives at:

```
curriculum/hospitrainity/<content_version>/
	package.json          # release manifest: package, schema_version, content_version, namespace, checksums
	framework/            # competencies.json, outcome-alignments.json, cefr-references.json (CP-02, verbatim)
	chapters/             # one entity per DOCX chapter — filled CP-04
	activities/           # prompts, answers, feedback, rubrics — filled CP-05
	assets/               # media + manifests — filled CP-07
	provenance/           # source locators + legacy-content-map — filled CP-04
	generated/            # DISPOSABLE build output (Laravel seed + standalone projections)
```

## Rules
- Only `framework/`, `chapters/`, `activities/`, `assets/`, `provenance/`, schemas and the manifest are reviewed and committed as source.
- `generated/` is fully reproducible from source by the CP-09 compiler and is **never hand-edited**.
- `schema_version` (structure contract) and `content_version` (curriculum content) are **independent and explicit**.
- Every learning entity (chapter, lesson-section, activity, prompt-item, answer-model, feedback-model, rubric) MUST carry a valid `source_locator` pointing into `Hospitrainity.docx`. This is what blocks non-DOCX content from entering a build (DEC-001).
- The framework files are the CP-02 support layer and are stored verbatim; they are re-canonicalized under the ID system in CP-04 (see `id-policy.md`).

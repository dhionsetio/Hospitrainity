# Decomposition specification (CP-04)

Deterministic rules used to decompose `Hospitrainity.docx` into entities. Re-running
the extractor on the same DOCX yields byte-identical output.

## Chapter detection
- A chapter starts at a paragraph whose text matches `^Chapter (\d+)\.\s*(.+)$` **and** is fully bold.
- The capture groups give the module number (1–7) and the chapter title.
- This excludes the plain (non-bold) Table-of-Contents listing of the same titles.

## Lesson-section detection
Within a chapter's paragraph range, a lesson-section starts at a fully bold
paragraph matching one of the manuscript's own canonical markers:
`Step N.`, `Warm-Up`, `Why this matters`, `How this book works`,
`What customer care means`, `Three ideas...`, `The one skill...`,
`Where you are starting...`, `What is inside...`, `Common mistakes to avoid`,
`Take it further`, `Further reading`.
Other bold lines (e.g. phrase-group labels inside `Step 3`) are body text of the
enclosing section, not separate sections.

## Normalization + hashing
- Normalized text = non-empty paragraph texts in the entity's range, each with
  internal whitespace collapsed to single spaces, joined by `\n`.
- `normalized_text_sha256` = SHA-256 (hex) of that normalized string.
- These hashes are recorded in every `source_locator` and mirrored in
  `provenance/docx-digest.json` for offline verification.

## Result
| Chapter | Module | Title | Sections |
|---|---|---|---|
| HSP-C01 | 1 | Welcome and Introduction to Customer Care | 7 |
| HSP-C02 | 2 | Front Desk and Check-In | 13 |
| HSP-C03 | 3 | Dealing with Customers on the Phone | 13 |
| HSP-C04 | 4 | The Guest-Services Hotline | 13 |
| HSP-C05 | 5 | Customer Care Through Writing | 13 |
| HSP-C06 | 6 | Online and Social Media Customer Service | 13 |
| HSP-C07 | 7 | Dealing with Problems and Complaints | 13 |

Total: 7 chapters, 85 lesson-sections.

## Scope boundary
CP-04 establishes the **structure + provenance** only. Interactive assessment
detail (prompts, answer models, feedback, rubrics, scoring, and the interaction
taxonomy on `activity` entities) is authored in CP-05 (assessment) and CP-11
(module authoring). No `activity` entity is emitted yet, to avoid premature or
unsourced classification.

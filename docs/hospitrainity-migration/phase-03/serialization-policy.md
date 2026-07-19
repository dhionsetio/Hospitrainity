# Canonical serialization & checksums

## Canonical JSON form
- UTF-8, LF line endings, no trailing whitespace.
- Object keys sorted lexicographically (recursively).
- 2-space indentation.
- Exactly one trailing newline.
- Numbers in shortest round-trip form; no `NaN`/`Infinity`.

Same input → byte-identical output. This is the prerequisite for the deterministic
compiler in CP-09 and for reviewable diffs.

## Checksums
- Every learning entity records `source_locator.normalized_text_sha256` (SHA-256 of the normalized DOCX excerpt).
- `package.json` records a SHA-256 for each framework file.
- `source-manifest.json` records input/output SHA-256 for each checkpoint (matching the CP-02 convention).

## Scope note (honest limitation)
Canonical serialization is enforced by `hsp-validate serialize` on **CP-03-authored**
JSON (schemas, registry, vocabulary, fixtures, manifest, package.json). The three
CP-02 framework files are intentionally preserved **verbatim** (byte-identical to
their approved form) and are therefore excluded from the canonical re-emit check;
they are re-canonicalized under the ID system during CP-04 migration, which will
be an explicit, reviewable change.

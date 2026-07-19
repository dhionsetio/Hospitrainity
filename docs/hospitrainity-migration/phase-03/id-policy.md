# Identifier policy

## Two coordinates per entity
Every entity has a **human-readable editorial code** (immutable) and a
**deterministic UUIDv5** derived from that code. The UUID is the machine key; the
code is the review/authoring key.

## UUIDv5 namespace (deterministic)
- Base namespace: RFC 4122 URL namespace `6ba7b811-9dad-11d1-80b4-00c04fd430c8`.
- Project namespace = `uuidv5(URL_NS, "https://hospitrainity.curriculum/id")` = `cc9dd546-ebaf-51c7-b86f-307d16a22f42`.
- Entity UUID = `uuidv5(project_namespace, editorial_code)`.
- Because generation is deterministic, regenerating the registry always yields identical UUIDs, so rebuilds are stable and diffable.

## Editorial-code grammar
```
HSP-C<chapter>-<type>-<seq>       e.g. HSP-C02-RP-001   (Chapter 2, role-play #1)
HSP-C<chapter>                    e.g. HSP-C02           (chapter)
HSP-C<chapter>-LS-<seq>           e.g. HSP-C02-LS-001    (lesson section)
```

## Ratified legacy codes (DEC-011)
The CP-02 framework already uses stable, meaning-bearing codes. To avoid a
re-approval churn and to keep the CP-02 files byte-identical, those codes are
**ratified as canonical** and registered as-is:
- Competencies: `COMP-*` (e.g. `COMP-PROBLEM-SOLVING`)
- Local outcomes: `OUT-M0x-xx`
- CEFR references: `CEFR-<level>-<scale>-<seq>`
New content families introduced from CP-04 onward use the `HSP-` grammar above.

## Immutability rules (roadmap §5)
- Identity is **never** derived from a title, display order, database auto-increment or array index.
- Editorial change (typo/format) → keep id + code, bump patch version.
- Substantive change (meaning changes) → mint a **new** id + code, set `replaces`/`replaced_by`, bump at least minor version.
- A **published** id may never be edited in place or repointed (enforced by `hsp-validate ids`).
- Retired entities remain addressable; they are never silently deleted.
- Laravel integer primary keys may remain internal, but every content row exposes its stable public code + UUID.

## Registry
`id-registry.json` holds 52 entries (framework + 7 competencies + 21 outcomes + 23 CEFR references) with code, UUIDv5, entity_type, status and origin. It validates against `schemas/id-registry.schema.json`.

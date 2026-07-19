# Phase 3 — Schema, IDs, Lifecycle & Validation Contracts (CP-03)

This phase defines the machine-validatable contract layer for the Hospitrainity
canonical curriculum package. It adds **no learner-facing content** — it is
documentation + offline tooling only.

## Contents
- `package-layout.md` — canonical package directory contract.
- `schemas/` — 17 JSON Schema (Draft 2020-12) files (`_meta` + framework + content entities).
- `controlled-vocabularies.json` — single source of enumerations shared by schemas, validator and renderers.
- `id-policy.md` + `id-registry.json` — UUIDv5 namespace, editorial-code grammar, immutable registry (52 entries).
- `lifecycle-policy.md` — status machine, change classification, retire/replace, SemVer.
- `serialization-policy.md` — canonical JSON + checksum rules.
- `validator-cli-spec.md` + `tools/hsp-validate.mjs` — validator contract + runnable Node implementation.
- `fixtures/` — valid + deliberately-invalid conformance fixtures and their manifest.
- `checkpoint-report.md`, `source-manifest.json` — CP-03 report + checksums.

## Run the validator (offline, no PHP/Composer/network)
```
node docs/hospitrainity-migration/phase-03/tools/hsp-validate.mjs all --strict
```

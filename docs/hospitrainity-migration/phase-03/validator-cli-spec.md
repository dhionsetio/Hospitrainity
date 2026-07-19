# hsp-validate — validator CLI contract

Runtime: **Node + Ajv (JSON Schema Draft 2020-12)** with `ajv-formats`. No PHP,
Composer, or network required (DEC-014).

```
node tools/hsp-validate.mjs <command> [--strict]
```

| Command | Checks | Must-fail conditions |
|---|---|---|
| `schema` | Every file validates against its Draft 2020-12 schema, incl. the CP-02 originals unchanged | any schema violation |
| `ids` | Code + UUID uniqueness; UUIDv5 determinism; published-id immutability | duplicate id/code, non-deterministic uuid, mutated published id |
| `refs` | All outcome→competency and outcome→CEFR references resolve; no dangling edges | broken reference |
| `lifecycle` | Only legal status transitions; no in-place published edits | illegal transition / status |
| `provenance` | Every learning entity carries a valid `source_locator` | missing/invalid source_locator |
| `serialize` | CP-03-authored JSON is byte-identical to canonical form | non-canonical serialization |
| `vocab` | Schema enums equal `controlled-vocabularies.json` | enum drift |
| `fixtures` | Valid fixtures pass; invalid fixtures fail on the expected check | any fixture behaving unexpectedly |
| `all` | Runs every check | any of the above |

## Exit codes
- `0` — all requested checks passed.
- `1` — one or more checks failed.
- `2` — unknown command.

The CP-04+ CI job runs `hsp-validate all --strict` on every commit to the
canonical package.

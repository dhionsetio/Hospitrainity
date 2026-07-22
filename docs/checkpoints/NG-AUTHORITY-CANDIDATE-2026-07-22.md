# NG learning-authority candidate checkpoint

- Saved: 2026-07-22 (Asia/Jakarta)
- State: deterministic candidate compilation passed; authority activation remains open
- Last completed domain checkpoint: B06-C
- Data mutation: none

## Owner direction recorded

The owner supplied `Hospitrainity New.docx` as an update to the existing `Hospitrainity.docx`, authorized one consolidated tester update, waived routine questionnaire pauses, accepted the roadmap recommendations, and asked to implement the supplied research and CEFR claims despite the lack of review. The project may use those claims for this tester release, but no record may describe them as independently validated.

## Authority state

| Source | SHA-256 | State |
|---|---|---|
| Thesis authority | `BA64B2EB69673E84FC83E21D4617CD111691EE6340FC2F4331948FE6AF5EC132` | Active and unchanged |
| Existing `Hospitrainity.docx` | `7F8A2C62DB6884498F7A1C2DE56E79E39609262944A685FE7EB97F702444CBB4` | Active learning authority and active v0.4 package source |
| `Hospitrainity New.docx` v0.8.0-draft | `5DE098DCDCC6405093004F6253A1DEF2F5DC4D85BC1CCACF1416376F3A77569B` | Validated replacement candidate, not active |

`config/authority-sources.json` remains on the existing hash. The active `curriculum/hospitrainity/0.4.0-draft` package remains intact.

## Candidate compiler

The existing compiler remains unchanged for the v0.4 source. The new isolated path consists of:

- `scripts/curriculum/NextGenerationSourceCompiler.php`
- `scripts/curriculum/compile_next.php`
- `scripts/curriculum/verify_next.php`

The compiler validates the exact hash, version marker, seven chapters, source inventory, stable codes, and learner-facing em-dash rule. It separates learner blocks from build metadata and compiles 88 source sections, 28 outcomes, 151 activity or response records, and 13 vocabulary or phrase nodes. It records private raw warm-up text, saved assessment responses, separate confidence ratings, TTS/listen intent, spaced-review intent, and the source CEFR claims without activating runtime behavior.

The compiler preserves 18 external relationship targets as unmapped resources. The DOCX body contains no hyperlink anchors, so assigning those targets to visible labels would require guessing.

## Verification evidence

```powershell
& 'C:\php\php.exe' scripts\curriculum\verify_next.php --source 'C:\Users\dhion\Downloads\Hospitrainity New.docx'
```

- Status: verified.
- Byte-identical files: 103.
- Deterministic tree SHA-256: `1239C67BB86CC4CE610988A143BE3D341B8299D70A477A2D69713F88A50B3401`.
- Changed-hash probe: passed.
- Missing-chapter probe: passed.
- Duplicate-code probe: passed.
- Learner-facing em-dash probe: passed.
- PHP syntax checks passed for all three compiler files.

No browser, application regression, database, production, or specialist test is claimed by this checkpoint.

## Known limitations

- The candidate bundle is not yet the active `CanonicalPackageReader` contract and is not rendered by the application.
- The relationship targets remain unmapped because the DOCX lacks hyperlink anchors.
- Runtime TTS, spaced review, streak, raw-text, saved-response, and chatbot behavior remain owned by the applicable application work.
- Qualified academic, CEFR, hospitality, accessibility, legal, and security review has not occurred.
- The raw source DOCX was not copied into the workspace, in line with the protected-authority handling policy.

## Exact external write still required

The authoritative file outside the writable workspace still contains the old bytes:

`C:\Users\dhion\Desktop\Documents\000 - Thesis Dhion Setio\Revisi 30 Juni 2026\Learning Materials - Fixed\Hospitrainity.docx`

Completing the requested replacement requires approval to preserve a recoverable copy of that file and replace it with the exact bytes from:

`C:\Users\dhion\Downloads\Hospitrainity New.docx`

After the write, recalculate the destination hash and require `5DE098DCDCC6405093004F6253A1DEF2F5DC4D85BC1CCACF1416376F3A77569B` before changing `config/authority-sources.json`.

## Resume instructions

1. Obtain approval for the exact external authority replacement above, or have the owner perform it.
2. Recalculate the destination hash and stop on any mismatch.
3. Update `config/authority-sources.json` only after the destination hash matches.
4. Transform the candidate bundle into a new active canonical package without editing v0.4 in place.
5. Run canonical, application, role, browser, and final regression gates before activation.
6. Keep unreviewed claims and unmapped-link limitations in technical evidence, not ordinary learner UI.

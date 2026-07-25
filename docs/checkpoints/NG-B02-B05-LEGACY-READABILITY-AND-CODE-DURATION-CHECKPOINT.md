# NG-B02/B05 Legacy Readability and Classroom-Code Duration Checkpoint

Date: 2026-07-20  
Scope: owner-requested correction following the B02/B05 architecture checkpoint

## Confirmed defect and correction

The retained legacy indexes queried the complete records, but read-only mode removed the edit dialogs that were the only place nested vocabulary items, material contents/media references, exercise payloads, and some module/lesson fields could be inspected. The write lock was intentional; the resulting inability to read the retained evidence was not.

All five retained indexes now expose escaped, non-editable, keyboard-native `details` disclosures. No legacy write route, policy, or middleware was relaxed. Existing canonical-active write-failure tests continue to pass.

## Superseding duration decision

The fixed one-hour classroom-code duration is superseded. An authorized Instructor or Institution Admin selects days, hours, minutes, and seconds. Server-side validation and the issuance service both enforce a total from 1 second through exactly 30 days; the interface defaults to 1 hour. The chosen TTL is recorded in `join_code.issued` audit metadata. Existing issued codes are not rewritten.

## Reproducible evidence

- `tests/Feature/LegacyEvidenceReadabilityTest.php` proves a Content Admin can read nested retained records without create controls.
- `tests/Feature/InstitutionJoinCodeEnrollmentTest.php` proves compound duration calculation, exact boundaries, rejection outside the range, service defense, and audit TTL evidence.
- Focused verification on 2026-07-20: 8 tests passed, 72 assertions.

No records, legacy evidence, accounts, institutions, or existing codes were deleted.

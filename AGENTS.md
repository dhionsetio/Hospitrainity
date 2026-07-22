# Hospitrainity agent instructions

These instructions apply to every task in this repository.

## Evidence and correctness

1. When uncertain, stop and verify the claim through the current authoritative project files, official documentation, specifications, code, or reproducible tests. Do not fill gaps with plausible text.
2. Do not fabricate or assume production details, secrets, endpoints, configuration, schemas, versions, user data, research results, academic claims, or test results.
3. Prefer the safest current approach recommended by primary official sources. Research unstable or unfamiliar technical decisions before implementing them. Distinguish confirmed facts, inferences, preferences, and unresolved evidence.
4. Double-check every change. Review the diff, test the narrow behavior, run proportionate regression checks, and correct detected defects before reporting completion.
5. Provide a reproducible validation path for material claims. Report tests and browser evidence exactly; never present source inspection or automation as human, accessibility, security, academic, legal, production, or device validation.

## Required skill routing

Read and follow every applicable skill before acting. User instructions override a skill when they conflict.

- Major UI design or redesign: `.agents/skills/frontend-design/SKILL.md`
- Frontend implementation and reusable interaction patterns: `.agents/skills/frontend-design-vipul/SKILL.md`
- Existing-interface critique, refinement, accessibility, responsiveness, or polish: `.agents/skills/impeccable/SKILL.md`
- User-facing prose, documentation, and UI copy: `.agents/skills/stop-slop/SKILL.md`
- Browser-level local web application testing and visual verification: `.agents/skills/webapp-testing/SKILL.md`

Use the minimal set required for the task, but do not skip an applicable skill. Backend-only work does not require a frontend skill unless it defines a contract for an imminent interface phase. Static review is not a substitute for browser testing when rendered behavior or visual correctness is part of the acceptance criteria.

## Project safety

- Treat the current thesis, learning-material document, accepted decision records, and governing roadmap as authority in their documented precedence.
- Recalculate authority hashes at required batch gates and stop on mismatch until the changed source is re-audited.
- Preserve unrelated working-tree changes. Do not delete files, records, retained evidence, or user data without the owner's explicit permission for the exact target.
- Use isolated disposable databases and test artifacts for mutations whenever possible. Do not test destructive behavior against the user's main database.
- Hidden UI is not authorization. Enforce tenant, role, resource, and lifecycle boundaries on the server and test denial paths.

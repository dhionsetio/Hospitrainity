# NG-B05 decisions — navigation, onboarding, help, and role direction

- Decision date: 2026-07-20 (Asia/Jakarta)
- Batch: B05
- Accountable owner: Dhion Setio
- Status: B05 technical implementation and authoritative local migration complete; moderated first-use execution remains B17

## Accepted direction

1. Prioritize Learners, Instructors/Institution Supervisors, and Educational Institutions. Research evaluators use a separate evidence/about destination. Do not advertise an employer audience without employer capabilities.
2. Current public actions are How invitations work, Sign in, Explore how learning works, and Help/support after ownership is configured. Defer unsupported Create account/Join class/demo/sample actions until the enrollment decision and exact content approvals exist.
3. Use the approved concise, evidence-bounded Indonesian-leading value proposition with persistent ID/EN selection. Do not claim popularity, guaranteed fluency, AI tutoring, personalized plans, community, or outcomes without evidence.
4. Defer a public sample until exact content, hospitality/ESP, accessibility, and rights approval. Later it is account-free, non-assessed, excluded from progress, and contains no answer exposure.
5. Use a short role-aware, skippable/resumable/restartable first-task flow rather than a long tour. Defer classes, goals/levels, and diagnostics to their own domains.
6. Treat baseline confidence as optional self-reflection, never a proficiency diagnostic or score.
7. Keep plain-language material status for learners, human-readable source/review status for instructors, and exact hashes/lifecycle/provenance under Advanced/Evidence for Content/System Admins.
8. Learner priority is current real work: required/overdue when it exists, Continue, review due, self-study, and progress. Hide nonexistent categories. Staff dashboards are role/task specific and hide unsupported queues.
9. Initial search covers published modules/sections, vocabulary, accessible activities, Help, and glossary. It excludes drafts/private responses and keeps user lookup in authorized administration pages. Start with bounded indexed database search.
10. Provide consistently placed searchable/versioned Help, glossary, contextual guidance, invitation/recovery instructions, and institution support first. Do not publish `dhionsetio@gmail.com` as a public support address without separate authorization.
11. Keep user-facing labels Learner, Institution Supervisor, Content Admin, and System Admin until the final tenant-role model supersedes them. Internal enum values do not change merely for wording.
12. Store onboarding state but no behavioral analytics until B02 permits a minimal first-party lifecycle scope.
13. Use two formative moderated first-use rounds: six intended learners and four instructor/supervisor/admin participants, including disabled/assistive-technology/lower-confidence users where possible. Treat results as formative rather than statistical proof.
14. The owner authorized deletion of the five obsolete translation keys from both locale files, the stale dashboard placeholder comment, and unused legacy supervisor query projection. Regression coverage prevents the obsolete claims returning.

## Approved tenant-role model

An educational institution is a tenant/entity, not a user role. Recommended hierarchy:

1. **Platform Owner / System Admin** — global Hospitrainity authority, initially Dhion Setio. Institutions do not receive this role. Additional global administrators require explicit platform-owner assignment.
2. **Institution Admin** — highest institution-scoped role; manages that institution's instructors/supervisors, learners, invitations, and settings but cannot administer other institutions or global platform security/releases.
3. **Instructor** — institution-scoped learner oversight and invitations. Content authoring is a separately assignable capability, allowing one person to be both Instructor and Content Author without granting unrelated administration.
4. **Learner** — personal learning plus approved institution memberships.

One account may hold multiple institution memberships and capabilities. The user explicitly switches active institution and active work context; the server validates every request and never combines permissions invisibly. System Admin may use a clearly bannered, audited **Preview as role** mode for testing. Local/E2E fixtures remain the preferred way to exercise destructive role journeys.

Merging Supervisor and Content Admin at the database-permission level is not recommended: learner oversight and shared curriculum publication are different trust boundaries. They can appear as one convenient **Instructor** workspace when the same account has both capabilities.

This model follows least privilege and institution relationships while avoiding a confusing role explosion. The owner explicitly approved it on 2026-07-20 and instructed that all three existing demo accounts remain enabled. Implementation is expand-first: normalized tenant assignments and explicit work context are added before the legacy four-role column can be retired. No institution may receive global System Admin authority.

The normalized assignment tables, explicit work/tenant/learning context, audited System Admin preview, learner-only classroom-code enrollment, exact-tenant staff progress, and recent-password institution-role administration are implemented and installed in the authoritative local database. Content Author no longer inherits tenant enrollment routes. Role-aware onboarding, bounded published-content search, versioned Help/glossary/About, deterministic next-action rules, evidence disclosures, and the approved study protocol are also implemented and installed. Legacy-role retirement remains a later reviewed contract step, and the moderated intended-user study itself remains B17. Evidence is recorded in `docs/checkpoints/NG-B02-B05-ARCHITECTURE-CHECKPOINT.md` and `docs/checkpoints/NG-B05-CHECKPOINT.md`.

2026-07-20 follow-up: the owner confirmed that a learner-only account must not receive a redundant work-role switcher. The control appears only when the account has another authorized work role; a direct single-role visit returns to the current dashboard. Personal/institution learning selection remains a separate Learner control.

## Evidence basis

- OWASP authorization guidance: least privilege, deny by default, validate every request, and relationship/attribute-aware authorization for multi-tenancy.
- W3C consistent navigation/help guidance.
- GOV.UK formative user-research guidance for actual/likely users and small iterative rounds.

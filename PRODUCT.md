# Hospitrainity product brief

Status: approved product context for the UI revamp, 2026-07-21  
Owner: Dhion Setio  
Authority: the current thesis and learning-material DOCX files, accepted NG decisions/checkpoints, and current tested application behavior

## Product purpose

Hospitrainity is a web-based English training platform for pre-service hospitality and tourism learners preparing for hotel customer-service work. It helps learners practise the language and service judgement used in booking, check-in and check-out, telephone service, written communication, online service, and complaint handling.

The learner experience should make authentic practice easier to start, easier to resume, and less tiring to complete. The staff experience should make authorized work clear without hiding its scope or overstating what the system can currently do.

## Primary users

1. Learners using personal self-study or an approved institution learning context.
2. Instructors and Institution Admins supporting learners inside an explicit institution scope.
3. Content Authors maintaining approved curriculum drafts.
4. System Admins operating and reviewing the platform globally.
5. Research evaluators using separate evidence-oriented views.

An institution is a tenant, not a global user role. One account may have several scoped memberships or capabilities, but one work and institution context is active at a time and every request is authorized on the server.

## Core learner jobs

- Understand what to do next without reading technical release metadata.
- Continue the most relevant incomplete learning activity.
- Browse the available hospitality-English modules.
- Work through short, clearly grouped lesson steps.
- Practise with immediate, source-faithful feedback.
- See participation progress without mistaking it for a grade, pass, mastery, or workplace performance.
- Find Help, glossary terms, account settings, and institution-connection guidance.

## Core instructor jobs

- Work inside one explicit Institution and Class context without gaining platform-wide authority.
- Create and manage assigned Classes, co-teachers, enrollment, roster status, and safe learner previews.
- Assemble or author teaching content when granted the applicable content capability.
- Validate, preview, and release approved material to authorized Classes without changing another Institution's course or the global canonical package.
- Assign required learning and approved dates, exceptions, or prerequisites.
- Review submissions, give feedback, grade approved activity types, and monitor institution-attributed progress.
- Send Class announcements and approved notifications and manage the teaching schedule.

These jobs form one coherent Instructor workspace, but its server permissions remain separate capabilities. A convenient interface must not turn an Instructor into an Institution Admin or System Admin.

## Experience principles

- Lead with the learning task, not the implementation evidence.
- Keep the source material faithful while adapting tables and long sequences into readable digital components.
- Use short local progress, such as “2 of 5 in this step,” instead of presenting all 85 sections as one burden.
- Make interactive regions obvious before hover and reinforce them on hover, focus, and press.
- Keep Help and recovery paths visible.
- Use calm encouragement. Do not invent scores, deadlines, achievements, social proof, or learning claims.
- Keep learner pages expressive and staff pages more restrained, while sharing the same design system.

## Confirmed constraints

- Canonical learning content remains English. Bahasa Indonesia is the interface translation layer unless a separately approved translation is supplied.
- Ordinary learners do not see package hashes, lifecycle codes, provenance identifiers, draft warnings, or scoring implementation details.
- A System Admin deliberately previewing the Learner context may see authorized evidence with a clear preview banner.
- Current participation completion is not correctness, a grade, a pass, mastery, CEFR evidence, or job-readiness proof.
- At the B06 start checkpoint, the current platform still has no completed B06 course/class domain, B07 assignments, B09 gradebook, B10 mastery analytics, or B14 gamification. The interface must not imply that those features exist until their implementation and validation checkpoints pass.
- AI/chatbot work remains decision-gated. The thesis still contains conflicting “integration” and “considered” wording, and Chapter IV contains no completed validation findings.
- Server-rendered HTML and native controls remain the baseline. Progressive enhancement must not block core work.
- WCAG 2.2 AA is the engineering target, with human validation still required before a conformance claim.

## Current UI-revamp outcome

Create a calm, modern hospitality-learning interface with:

- a compact desktop navigation rail and a four-item mobile bottom navigation for primary learner destinations;
- one dominant Continue action backed only by current authorized facts;
- keycard-inspired module presentation without decorative clutter;
- clear short-step lesson navigation and transition checkpoints;
- one exercise prompt at a time where the activity contract permits it;
- labeled, truthful progress summaries;
- layered light and blue-gray dark themes;
- subtle, reduced-motion-safe interaction feedback.

## Out of scope for the UI-R track

- New institution, course, class, group, assignment, grading, notification, AI, proctoring, PWA, or gamification behavior.
- Changes to canonical learning prose or answer policy.
- Production deployment claims.
- Deletion of legacy evidence, retained test artifacts, user data, or source material.

The UI-R track may present newly completed B06-B12 behavior after the owning domain checkpoint supplies its real schema, policy, lifecycle, and tests. It must not invent those contracts independently.

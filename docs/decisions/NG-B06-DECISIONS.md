# NG-B06 institution, course, class, enrollment, and instructor decisions

- Decision date: 2026-07-21 (Asia/Jakarta)
- Batch: B06
- Accountable owner: Dhion Setio
- Status: accepted; implementation authorized within this record
- Supersedes: no accepted record; extends NG-B01, NG-B02, and NG-B05
- Next checkpoint: B06-A domain, authorization, and shared-time foundation

## Owner approval used

The owner approved all recommended answers to the complete B06 questionnaire and added one controlling product outcome: a lecturer account must be fully usable for managing its students and teaching work, including content release and notifications. That outcome spans B06-B12; it does not silently grant institution staff global platform authority.

## Approved B06 decisions

1. **Domain vocabulary.** An Institution is the tenant. A Course is an institution-owned teaching definition. A Course Revision is an immutable ordered selection of approved canonical modules. A Course Offering, shown as **Class** in the interface, is a scheduled delivery of one Course Revision. Enrollment connects a learner to a Class. Teaching Assignment connects an instructor to a Class. Term/semester data is optional metadata. Groups are deferred until a concrete teaching need is approved.
2. **Curriculum association.** A Course Revision selects ordered modules from one immutable canonical curriculum package version. A Class pins one Course Revision so later publication cannot silently change an active class.
3. **Ownership and co-teaching.** An Institution owns its Courses and Classes. Institution Admins may create and administer them. Each Class has one primary instructor and may have multiple co-instructors.
4. **User-facing teaching role.** Use **Instructor** in the interface. Migrate legacy Supervisor terminology deliberately; do not create a second parallel authorization system.
5. **Administrative boundary.** Institution Admin is the highest tenant role and never gains cross-institution authority from that role. System Admin retains global platform, canonical publication, security, audit, and institution-management authority.
6. **Multiple memberships.** One person may be a learner or instructor in multiple Classes and Institutions, but only one explicit role/institution/Class context is active at a time. Every request is re-authorized on the server.
7. **Enrollment methods.** Approve individual invitations, adjustable classroom codes from one second through 30 days, manual staff enrollment, and learner-requested connection. Defer CSV roster import, verified-domain auto-enrollment, SSO, and LTI until separately approved. Connecting to an Institution creates a separate institution-attributed learning record; personal progress is not copied.
8. **Roster changes.** Institution Admins may suspend, remove, or transfer a learner within their tenant. Instructors may manage enrollment only in Classes they teach. Historical work remains attached to its original Class, learner, and content revision and is not reassigned or erased by roster changes.
9. **Groups.** Defer Group and Group Membership tables and behavior. Add them only when an approved teaching workflow needs them.
10. **Class lifecycle.** Use `draft`, `enrollment_open`, `active`, `closed`, and `archived`. Transitions are explicit and audited. Dates may assist transitions later but do not silently replace the authoritative state.
11. **Lecturer-visible roster data.** Show only fields needed for teaching operations: learner display name, institution identifier where configured, enrollment status, Class role, joined date, and approved institution-attributed learning summaries. Do not expose personal self-study progress, private responses, account-security data, or unrelated profile data.
12. **Learner preview.** Provide a synthetic or disposable preview learner scoped to the selected Course Revision or Class. Do not impersonate a real learner or reuse their session, identity, progress, submissions, or private data.
13. **Class copies.** A copy may include Course/Revision selection and approved Class settings. It must never copy learners, personal data, progress, submissions, answers, grades, join codes, audit history, or dates by default.
14. **Retention and archive.** Archive instead of deleting normal Course, Class, enrollment, and teaching history. No destructive retention period is invented. Any later erasure or retention change must follow the approved privacy process and preserve legally or academically required audit relationships.
15. **Time and timezone.** Persist instants in UTC and validate IANA timezone identifiers. The initial Institution default for Politeknik Negeri Malang is `Asia/Jakarta`; a Class may override its Institution. A user's timezone affects display only in B06. Schedule interpretation precedence is Class, Institution, then system UTC. Display precedence is user, Class, Institution, then system UTC. All later time-dependent batches must use the same injectable Clock and TimeContext.

## Complete lecturer outcome across B06-B12

The product target is one coherent **Instructor workspace**, not a collection of unrelated administrator screens. The workspace composes separately authorized, institution-scoped capabilities:

| Batch | Instructor outcome |
|---|---|
| B06 | Create or manage assigned Classes, co-teachers, enrollment, roster status, and learner preview within the active Institution. |
| B07 | Assign required learning, prerequisites, availability, due dates, and learner exceptions. |
| B08 | Assemble and author teaching content through a task-oriented Course Builder; validate, preview, and release content to authorized Classes. |
| B09 | Review submissions, grade approved activity types, give feedback, and use the gradebook. |
| B10 | Review institution-attributed progress and use actionable learner/class summaries. |
| B11 | Publish Class announcements and approved notifications to scoped audiences. |
| B12 | Manage the Class schedule, reminders, and approved time-based teaching events. |

These capabilities may appear in one workspace without being collapsed into one unrestricted database role. Institution Admins grant and revoke applicable teaching/content capabilities inside their Institution. Authorization checks actor, active context, resource relationship, lifecycle state, and capability on every request.

### Content-release boundary

“Instructor can release materials” means an authorized instructor can release validated content to Classes they are allowed to teach, subject to the Institution's B08 review policy. It does **not** mean every Instructor may publish a new global canonical curriculum package or change content used by another Institution. Global canonical publication remains System Admin-only unless a later explicit owner decision changes that boundary.

B08 must still resolve the detailed author-review-publish workflow, including whether Institution Admin approval is required, which instructors receive Content Author or Institution Publisher capability, collision handling, rollback, and release visibility. The B08 questionnaire may choose the least burdensome safe workflow, but it may not remove the lecturer's approved ability to complete ordinary teaching work.

## Accepted architecture rationale

This is consistent with current official LMS patterns: course staff roles are scoped to courses or organizations; teaching staff can manage course content and learners; higher administrators assign staff authority; content is previewed and published through explicit lifecycle controls. The authorization model also follows OWASP guidance to apply least privilege, deny by default, validate permissions on every request, and prefer resource-relationship/attribute checks over a single broad role.

Primary sources consulted:

- Open edX, course team roles: https://docs.openedx.org/en/ulmo/educators/references/course_development/course_team_roles.html
- Open edX, adding course team members: https://docs.openedx.org/en/latest/educators/how-tos/set_up_course/add_course_team_members.html
- Open edX, managing and publishing sections: https://docs.openedx.org/en/latest/educators/how-tos/course_development/manage_sections.html
- Open edX, course launch checklist: https://docs.openedx.org/en/latest/educators/concepts/releasing-course/course_launch_checklist.html
- Open edX, role-specific learner views: https://docs.openedx.org/en/latest/educators/references/roles_for_viewing.html
- Moodle, Teacher role: https://docs.moodle.org/502/en/Teacher
- OWASP, Authorization Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html

## Consequences and gates

- **Security:** all Course/Class/roster/content reads and mutations require server policies and Institution/Class query scoping. Hidden controls are not authorization.
- **Privacy:** instructors see institution-attributed teaching data only. Personal self-study and unrelated Institution data remain inaccessible.
- **Accessibility:** Course Builder, roster, context switching, preview, and lifecycle controls target WCAG 2.2 AA and require keyboard, zoom/reflow, reduced-motion, screen-reader, and real-device evidence before a conformance claim.
- **Source fidelity:** a Class pins an immutable canonical version. Authoring must preserve source/provenance distinctions and may not invent learning content.
- **Migration:** use expand/contract migrations. Preserve legacy Supervisor and institution fields as provenance until every active relationship is mapped and verified.
- **Operations:** production mail, push delivery, queues, scheduling, backup/restore, monitoring, and responsible operators remain unverified production gates.
- **Specialist review:** academic/content, accessibility, privacy/legal, and independent security approvals remain required where the governing roadmap names them.

## Validation and rollback boundary

B06 acceptance requires isolated migration/rollback tests, policy tests for direct-ID and cross-Institution/Class access, lifecycle and historical-link tests, frozen-time and timezone/DST tests, browser checks for context switching and core instructor/learner flows, and the full regression suite.

Rollback is expand/contract: new UI can be feature-disabled while new records remain intact; legacy mappings remain readable until cutover is proven. Rollback must not drop or rewrite Course, Class, enrollment, teaching, progress, or audit history.

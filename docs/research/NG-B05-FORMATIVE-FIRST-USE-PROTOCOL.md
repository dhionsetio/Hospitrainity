# NG-B05 formative first-use protocol

- Owner-approved design date: 2026-07-20 (Asia/Jakarta)
- Accountable owner: Dhion Setio
- Study type: two moderated, formative rounds
- Planned participants: six intended learners and four instructor/supervisor/admin participants across both rounds
- Execution batch: B17, after the required supervisor/ethics process
- Current status: protocol approved and implementation-ready; no participant sessions or results are claimed

## Purpose and evidence boundary

This study will find first-use navigation, comprehension, recovery, and support problems in the implemented Hospitrainity journeys. It is not powered to estimate population rates, prove usability, establish learning effectiveness, or support a claim that the website is engaging or accessible for everyone. Automated tests establish deterministic software behavior only; observed human evidence must be recorded separately.

The study will answer:

1. Can an intended learner begin or resume learning, understand personal versus institution learning, find Help, and search published material without creator intervention?
2. Can an instructor, institution supervisor/admin, or content/system administrator identify their active role and institution context, find their available work, invite a user where authorized, and recover through Help?
3. Which labels, orderings, empty states, and technical details cause hesitation, error, or reliance on the moderator?
4. Are skip, resume, restart, search-empty, unauthorized, and help-recovery paths understandable and operable?

## Participants and recruitment

Recruit six people who match the intended learner audience and four people who plausibly perform instructor, supervisor, educational-institution administration, or content/system-administration work. Do not substitute developers who built the feature for intended participants.

Use two rounds so that severe findings from round one can be corrected and retested in round two. Aim for a balanced distribution rather than assigning statistical meaning to the small sample. Where recruitment permits, include participants with lower digital confidence and disabled or assistive-technology users. Record the actual mix and any recruitment gaps; do not claim inclusion that did not occur.

Recruitment must state that participation is voluntary, the website is a prototype, performance of the participant is not being assessed, withdrawal is allowed without penalty, and participation does not affect grades, employment, or institution standing. Supervisor/ethics approval and any required institution permission must be recorded before recruitment begins.

## Consent and data minimization

Before each session:

- provide the approved information and consent form in an understandable language;
- obtain explicit consent separately for participation, note-taking, screen recording, and audio recording;
- allow participation when optional recording is declined if the approved study process permits it;
- use disposable study accounts and non-sensitive fixture data;
- never ask participants to disclose a real password, classroom code, private response, disability diagnosis, or unrelated personal information;
- identify records with participant codes, not names, in analysis files;
- store raw notes/recordings only in the approved restricted location and apply the approved retention/deletion schedule.

The application itself must not add behavioral analytics for this study. Moderator notes and separately consented recordings are research records, not product analytics.

## Session setup

Record the participant code, approved role, device, operating-system version, browser/version, input method, viewport or display size, language, and any voluntarily disclosed assistive technology relevant to the session. Also record the application commit, database fixture/version, test URL, start time, facilitator, observer, and whether recording consent was granted.

Use the supported target matrix where practical: Windows or macOS for staff tasks and Windows, macOS, Android, or iOS for learner tasks; cover Chrome, Safari, Edge, Firefox, or Opera according to platform availability. Engine emulation is not a substitute for recording the real environment.

Give only the task scenario at first. Ask the participant to think aloud without teaching the interface. The moderator may clarify the scenario but must record every hint that changes what the participant knows. Stop a task if continuing risks privacy, distress, or an unintended data change.

## Learner task set

Use only tasks supported by the installed build and the participant's authorized fixture account:

1. From the public page, explain in the participant's own words what the website offers and who it appears to serve; find the learning explanation, Help, privacy, and accessibility destinations.
2. Sign in, identify the page's main purpose, and use or skip the role-aware getting-started flow. Later, resume or restart it.
3. Find the next recommended learning action and explain why it appears. If a prepared incomplete activity exists, resume it; otherwise confirm that the empty state does not promise unavailable work.
4. Search for a supplied published topic, open an appropriate result, and explain what information was returned. Then try a supplied no-result query and recover without moderator navigation.
5. Find a glossary definition and the invitation/institution-connection guidance.
6. If the fixture permits it, distinguish personal learning from institution-attributed learning. Do not create or approve a real institution membership during the study.
7. Change language or a display preference relevant to the participant and return to the current learning task.

## Staff task set

Assign only the subset authorized for the participant's fixture role:

1. Sign in, identify the active role and institution context, and state what the dashboard expects the participant to do next.
2. Use or skip getting started, then resume or restart it.
3. Find an existing learner-progress, invitation, content, or evidence destination appropriate to the role. Confirm that unavailable queues are not presented as actionable work.
4. For an Instructor or Institution Admin fixture, begin the invitation workflow and stop before sending unless the study script explicitly authorizes a disposable invitation.
5. Search for published learning material and Help; confirm that learner records, drafts, private responses, and answer models are not presented in global search.
6. Find the human-readable status first, then locate authorized technical evidence through its Advanced/Evidence disclosure without needing to interpret a raw hash for the ordinary task.
7. If the account has multiple authorized contexts, switch context and explain the visible change. Do not test destructive role changes against authoritative accounts.

## Measures and observation codes

For every task, record:

- outcome: completed, completed with assistance, not completed, or stopped;
- time on task as descriptive session context, not a population benchmark;
- wrong turns and recoveries;
- moderator assistance using the scale below;
- participant explanation of labels and system state;
- observed keyboard, touch, zoom, language, or assistive-technology barriers;
- direct participant comments, paraphrased by default and quoted only under approved consent;
- severity and proposed follow-up, kept separate from the raw observation.

Assistance scale:

0. No assistance.
1. Neutral prompt such as “What would you do next?”
2. Restatement of the scenario or goal.
3. Interface-specific hint naming an area or control.
4. Moderator demonstrates or completes the action.

Classify a finding as critical when it blocks a required safe task, causes unauthorized disclosure/action, or creates a serious accessibility barrier. Classify it as high when the task cannot be completed without interface-specific assistance or repeated failure. Medium and low findings cover recoverable confusion and polish respectively. Security, privacy, and accessibility harm overrides frequency.

## Round gates and analysis

After round one, preserve the raw permitted evidence and create a finding log linking each observation to participant code, task, environment, assistance level, severity, and evidence location. Separate observed facts from interpretation. Fix critical and high product defects that can be addressed without changing owner-controlled visual or feature preferences, rerun automated regression checks, and record the new commit before round two.

Round two retests the changed journeys and samples any unresolved task. A finding is not considered resolved merely because one later participant succeeds. Record whether it was reproduced, not reproduced, changed, or remains untested.

The B17 report must include all adverse findings, withdrawals or stopped tasks, assistance given, missing participant groups, device/browser gaps, deviations from this protocol, unresolved issues, and limits on generalization. Do not convert the ten planned sessions into a statistical pass rate or a claim of WCAG conformance.

## Technical prerequisites

Before each round, verify and retain:

- the exact application commit and clean deployment result;
- a fresh disposable database with no production identity or private response data;
- one non-empty active published-content search generation;
- role/scope fixtures for every assigned task;
- Help, glossary, skip/resume/restart, search empty state, and recovery links;
- the current automated Laravel, JavaScript, accessibility-markup, and supported-engine browser results;
- a tested recovery method for resetting disposable accounts between sessions.

## Primary methodological references

- [GOV.UK Service Manual — Planning user research](https://www.gov.uk/service-manual/user-research/plan-round-of-user-research): short research rounds, appropriate participants, task planning, and recording research needs.
- [GOV.UK Service Manual — Writing a user research discussion guide](https://www.gov.uk/service-manual/user-research/write-discussion-guide): scenario-led, non-leading facilitation and structured observation.
- [W3C WCAG 2.2 — Consistent Help](https://www.w3.org/WAI/WCAG22/Understanding/consistent-help.html): consistent placement of available help mechanisms.
- [W3C — Involving Users in Evaluating Web Accessibility](https://www.w3.org/WAI/test-evaluate/involving-users/): user evaluation complements, but does not replace, standards-based accessibility evaluation.

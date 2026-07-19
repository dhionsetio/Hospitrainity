# Phase 07 — Accessibility & UDL Requirements Specification

**Status:** Requirements only. CP-07 defines the accessibility, Universal Design for Learning
(UDL), and media obligations that the future Hospitrainity renderer / authoring platform
(CP-08 onward) must satisfy. It changes no learner content and mutates no package entity.

**Conformance target:** **WCAG 2.2, Level AA** (W3C Recommendation, 5 October 2023) and
**CAST UDL Guidelines 3.0** (2024). See `phase-06/references.md` for full citations.

## 1. Accessibility affordance vocabulary

The `activity.accessibility` controlled vocabulary already carries five affordance flags. This
spec binds each flag to the specific WCAG 2.2 success criteria it discharges, so the flags become
verifiable renderer requirements rather than labels.

| Flag | WCAG 2.2 success criteria | UDL principle |
|---|---|---|
| `text_alt` | 1.1.1 Non-text Content (A) | Representation |
| `keyboard_path` | 2.1.1 Keyboard (A), 2.1.2 No Keyboard Trap (A) | Action & Expression |
| `non_color_cue` | 1.4.1 Use of Color (A) | Representation |
| `no_timing_dependency` | 2.2.1 Timing Adjustable (A) | Engagement |
| `transcript` | 1.2.1 Audio-only (A), 1.2.2 Captions (A), 1.2.3 Audio Description or Media Alternative (A) | Representation |

## 2. Baseline requirement (every activity)

Every interactive learner activity, regardless of channel, MUST provide the four baseline
affordances: `text_alt`, `keyboard_path`, `non_color_cue`, `no_timing_dependency`. These map to
the global WCAG 2.2 AA obligations that apply to all interactive content (perceivable non-text
content, keyboard operability, colour-independent cues, and no timing traps).

## 3. Conditional requirement (time-based media)

A `transcript` / media alternative is REQUIRED when, and only when, an activity ships
pre-recorded audio or video — formally: `channel ∈ {spoken_ftf, telecommunications}` **and**
`response_form ∈ {role_play, spoken_rehearsal}`. Text-only channels
(`written_correspondence`, `social_public_reply`) and non-audio response forms do not incur a
transcript obligation unless media is later attached.

## 4. WCAG 2.2 AA success criteria applicable to the renderer

Beyond the per-activity flags, the renderer as a whole MUST meet these additional WCAG 2.2 AA
criteria (they are platform-level and cannot be expressed as per-activity content flags):

- **1.3.1 Info and Relationships / 1.3.2 Meaningful Sequence** — semantic headings, lists, and
  programmatic reading order for every screen.
- **1.4.3 Contrast (Minimum)** — ≥ 4.5:1 for text, ≥ 3:1 for large text and UI components (1.4.11).
- **1.4.4 Resize Text / 1.4.10 Reflow** — usable at 200% zoom and 320 CSS px without loss.
- **2.4.3 Focus Order / 2.4.7 Focus Visible / 2.4.11 Focus Not Obscured (Minimum, new in 2.2)**.
- **2.5.7 Dragging Movements** and **2.5.8 Target Size (Minimum, 24×24 CSS px)** — both new in 2.2;
  relevant to the ordering / drag prompt items.
- **3.2.6 Consistent Help** and **3.3.7 Redundant Entry** — new in 2.2; applies to the learner UI.
- **3.3.8 Accessible Authentication (Minimum)** — new in 2.2; applies to any sign-in.
- **4.1.2 Name, Role, Value** — all custom controls expose correct ARIA semantics.

## 5. UDL 3.0 alignment

- **Multiple means of representation** — `text_alt`, `transcript`, and `non_color_cue` guarantee
  content is perceivable in more than one modality.
- **Multiple means of action & expression** — `keyboard_path` plus the existing response-form range
  (selection, ordering, short_text, spoken_rehearsal, role_play, service_artifact, self_rating)
  give learners varied ways to respond.
- **Multiple means of engagement** — `no_timing_dependency` and the reflection / confidence-check
  activities support self-regulation and reduce anxiety-driven barriers.

## 6. Verification

`../phase-03/tools/hsp-a11y.mjs` derives the required affordances per activity and checks them
against the declared flags. Current result: **24 activities, 24 conform, 0 gaps** (see
`a11y-conformance.json`). The full derived requirement matrix is in `a11y-requirements.json`.
These requirements become binding acceptance criteria for the renderer phases (CP-08–CP-12).

## 7. Limitations

- Platform-level criteria in §4 cannot be auto-verified from the static package; they require an
  audit of the actual renderer (deferred to CP-10/CP-12).
- No media assets exist yet; §3 obligations are conditional and become active when audio/video is
  authored. See `media-requirements.md`.
- This spec is not a substitute for testing with assistive technology and disabled users, which the
  QA/pilot phase (CP-12) must include.

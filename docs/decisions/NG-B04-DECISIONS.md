# NG-B04 decisions — accessibility and responsive interaction

- Decision date: 2026-07-20 (Asia/Jakarta)
- Batch: B04
- Accountable owner/self-reviewer: Dhion Setio
- Status: accepted for implementation; no independent conformance claim

## Accepted direction

1. Engineer complete workflows toward WCAG 2.2 AA. Dhion performs pre-release owner self-review. A lecturer/expert review during thesis defense is post-release evaluation, not pre-release independent evidence. Hospitrainity must not claim independent WCAG conformance without qualified review and assistive-technology-user evidence.
2. Test current and previous supported target browsers where available: Chrome, Edge, Firefox, Opera, and Safari across Windows, macOS, Android, and iOS. Include NVDA/Firefox and NVDA/Chrome, NVDA/Edge smoke, VoiceOver/Safari on macOS/iOS, TalkBack/Chrome on Android, keyboard-only desktop checks, and exact version records. Physical baseline is a Windows laptop, Mac, mid-range Android phone, and iPhone when available.
3. Use a compact sticky mobile header and accessible top drawer with role/institution context, Escape, focus return, inert background, and content-first reading order.
4. Put role/institution, title, critical status/error, and one page-specific non-destructive primary action above the mobile fold.
5. Use 24×24 CSS px as the absolute target floor with documented WCAG exceptions and 44×44 for primary/touch controls; labels enlarge radio/checkbox targets.
6. Organize long learning pages around summary, Resume/Next action, and anchored outline; progress around summary, filters, and pagination; secondary explanation in accessible accordions; action records as responsive cards. Tabs are not the default.
7. Preserve semantic scrollable tables for true comparisons and use cards/details for user, invitation, and content records on narrow screens.
8. The owner prefers flags. Implement flags only as decorative engagement cues alongside visible `ID` and `EN` text and accessible language names. Flag-only selection is rejected because countries and languages are not one-to-one and the control must remain understandable without color/image recognition.
9. Use medium density, 16px body text, supporting text at least 14px, accessible light theme, forced-colour support, and visible two-colour focus.
10. Add persisted per-user display preferences:
    - theme: `System`, `Light`, `Dark`;
    - motion: `System`, `Reduce`, `Full` while respecting the OS setting by default;
    - text: `Default`, `Large`, `Larger`, without blocking browser zoom or 200% reflow;
    - stronger contrast/no-audio where implemented.
11. Semantic reading order, keyboard operation, accessible names, error recovery, captions/transcripts, and non-drag alternatives are always-on requirements, not optional modes. Later timed work may add extra-time/deadline overrides.
12. Place a consistent Help text link in public/authentication footers and authenticated header/drawer, plus contextual help beside complex controls; no obstructive floating widget.

## Evidence boundary

Automated tests, Playwright engines/emulation, and owner self-review are useful evidence but do not prove branded-browser, physical-device, screen-reader, or WCAG conformance. Any public claim must state exactly what was tested.

## Evidence basis

- W3C WCAG 2.2, including language identification, resize/reflow, target size, motion, consistent navigation/help, and complete-process conformance.
- W3C guidance that authoring must not prevent browser text scaling and content/functionality must remain available at 200%.

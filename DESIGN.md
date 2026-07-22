# Hospitrainity design system brief

Status: approved direction, implementation begins with UI-R01  
Last updated: 2026-07-21  
Register: product interface; learner surfaces are expressive, staff surfaces are restrained

## Visual direction

Hospitrainity should feel calm, capable, and welcoming: a modern hospitality-learning product rather than a generic admin template, an e-book, or a game. Blue carries trust and wayfinding. Warm yellow marks selected moments, useful guidance, and completion accents. Hospitality appears through restrained line motifs such as a reception bell, keycard, room key, phone, or envelope—not through hotel stock-art decoration on every screen.

The approved north-star comp is [`docs/design/hospitrainity-ui-r-north-star.png`](docs/design/hospitrainity-ui-r-north-star.png). It is a hierarchy and composition reference, not a screenshot to trace. Its unsupported “Today’s plan,” quiz-pass count, and average-score content must not be implemented.

Carry forward:

- compact left navigation;
- dominant Continue panel;
- keycard-like modules;
- labeled progress;
- visible Help;
- coherent line icons;
- moderate radii, light borders, and low-noise surfaces.

Do not carry forward:

- invented scores, deadlines, plans, assignments, or passed-quiz metrics;
- identical card grids for every section;
- purple accents, glass effects, gradients, heavy shadows, cartoon mascots, coins, stars, trophies, XP, streaks, or leaderboards;
- decorative photography that competes with learning.

## Color strategy

The interface uses primitive palette values through semantic aliases. Components consume semantic aliases only. Dark mode is not an inverted light theme; it uses lighter surfaces for elevation and a cyan primary with dark text.

### Light theme aliases

| Role | Value | Use |
|---|---:|---|
| Canvas | `#F7FAFC` | Page background |
| Surface | `#FFFFFF` | Primary panels and controls |
| Surface raised | `#EAF6FC` | Selected/featured areas |
| Surface strong | `#D9ECF5` | Track, quiet emphasis |
| Text | `#102A43` | Headings and body |
| Text secondary | `#486274` | Supporting text; 6.12:1 on canvas |
| Border subtle | `#BED2DE` | Decorative separation |
| Border control | `#758F9F` | Meaningful control boundary; 3.40:1 on white |
| Primary | `#006BBB` | Primary action; white text 5.50:1 |
| Primary hover | `#005A9E` | Hover/pressed emphasis |
| Primary soft | `#DFF2FB` | Selected backgrounds |
| Warm accent | `#FFC872` | Guidance and completion accent |
| Warm soft | `#FFE3B3` | Low-emphasis warm surface |
| Focus | `#006BBB` | Light-theme keyboard focus |

### Dark theme aliases

| Role | Value | Use |
|---|---:|---|
| Canvas | `#16232C` | Page background |
| Surface | `#1E2D37` | Primary panels |
| Surface raised | `#283A45` | Elevated/selected panels |
| Surface strong | `#344A56` | Track and stronger separation |
| Text | `#F4FAFC` | Headings and body |
| Text secondary | `#BDD0D9` | Supporting text; 8.88:1 on surface |
| Border subtle | `#4A6370` | Decorative separation |
| Border control | `#758F9F` | Meaningful boundary; 3.47:1 on raised surface |
| Primary | `#00B4D8` | Primary action with `#03045E` text; 7.20:1 |
| Primary strong | `#0077B6` | Strong blue surface; light text 4.62:1 |
| Primary soft | `#173E50` | Selected backgrounds |
| Warm accent | `#FFC872` | Guidance and focus |
| Warm soft | `#4B3B21` | Low-emphasis warm surface |
| Focus | `#FFC872` | Dark-theme keyboard focus |

Success, warning, danger, and information colors remain semantic. They are paired with text or icons and are never used as decoration or as the only status signal.

During UI-R01, existing Tailwind `bg-indigo-*` utilities map to the dark theme's strong blue with light text so legacy `text-white` buttons remain readable. New `.hsp-button--primary` controls use cyan with navy text. Later phases replace the compatibility utilities component by component; neither pairing may be mixed.

## Typography

Primary family: Plus Jakarta Sans, self-hosted under SIL Open Font License 1.1. Use one variable upright font file where browser and asset verification pass. Keep the system sans stack as the fallback and use `font-display: swap`.

Use at most four weights: 400 body, 500 supporting emphasis, 700 actions/headings, 800 rare display emphasis.

| Role | Size | Line height | Weight |
|---|---:|---:|---:|
| Caption | `0.75rem` | 1.4 | 700 |
| Supporting | `0.875rem` | 1.5 | 500 |
| Body | `1rem` | 1.6 light / 1.65 dark | 400 |
| Lead | `1.125rem` | 1.55 | 400–500 |
| Section title | `1.5rem` | 1.25 | 700 |
| Page title | `2rem` | 1.15 | 700–800 |
| Learner display | `2.5rem` | 1.1 | 800; only where space supports it |

Body copy is never smaller than `1rem`. Long reading blocks use a maximum measure of about `65ch`. App typography uses fixed `rem` sizes; it does not scale continuously with viewport width.

## Spacing and geometry

Use a 4px-based semantic scale:

- `2xs` 4px
- `xs` 8px
- `sm` 12px
- `md` 16px
- `lg` 24px
- `xl` 32px
- `2xl` 48px
- `3xl` 64px
- `4xl` 96px

Related controls sit 8–12px apart. Sections usually separate by 32–48px. Use `gap` for sibling spacing. Use cards only for distinct, actionable objects; do not wrap every group or nest cards inside cards.

Radii:

- controls: 10px;
- cards: 14px;
- feature panels: 18px;
- pill radius only for compact status chips, avatars, and progress tracks.

Depth is quiet. Light mode may use one soft shadow for raised navigation or overlays. Dark mode uses surface lightness instead of large shadows.

## Navigation

Desktop learner shell:

- persistent compact left rail;
- destinations: Dashboard, Learn, Progress, Help;
- secondary/account destinations stay in the account menu;
- active destination uses color, icon, label weight, and an indicator—not color alone;
- current learning/institution context remains explicit when available.

Mobile learner shell:

- four-item bottom navigation: Home, Learn, Progress, Help;
- account and secondary actions remain in the top bar/menu;
- safe-area padding is required;
- no feature depends on hover.

Back navigation uses the existing compact arrow control with a visible accessible name and a tooltip label on hover/focus. Every page in a nested flow must expose a deterministic parent destination.

## Component behavior

Every action has default, hover, focus-visible, pressed, disabled, loading, error, and success behavior where the state is applicable.

- Primary buttons: solid semantic primary, clear label, at least 44px high.
- Secondary buttons: surface plus meaningful boundary; never plain ambiguous text.
- Card links: the whole distinct card is the link when there is one destination.
- Current state: label as “Current” or “Active”; do not make status text look clickable unless it performs an action.
- Loading: preserve label context and prevent duplicate submission.
- Focus: at least a 3px visible ring with offset; never remove the native indication without a replacement.
- Press feedback: 120–150ms transform/brightness response.
- Hover/state transitions: 180–240ms with non-bouncy easing.
- Reduced motion: remove translations and non-essential transitions while preserving state changes.

## Learning patterns

- Dashboard: one primary Continue action, honest module progress, browseable modules, and Help.
- Module overview: learning outcomes plus grouped steps, not a single 85-section queue.
- Lesson section: show local step position and a short progress indicator; tables may become vocabulary cards, comparison pairs, dialogue turns, or another source-faithful structure.
- Exercise: one prompt per screen when compatible with the activity contract; summary only after the final prompt.
- Step transition: clearly mark completion, recap what was covered, and offer one next action. Celebration is subtle and motion-safe.

## Responsive and accessibility rules

- Start from 320px and add complexity at content-driven breakpoints.
- 44px is the project target for primary controls; WCAG 2.2’s 24×24 minimum remains the floor for other pointer targets subject to its exceptions.
- Support touch, pointer, and keyboard concurrently.
- Keep core actions in the DOM and usable without hover.
- Preserve 200% text zoom and 400% page zoom/reflow.
- Use native HTML before custom ARIA widgets.
- Keep links and buttons semantically distinct.
- Contrast checks cover text, component boundaries, focus, hover, selected, disabled, dark, and high-contrast states.

## Asset queue

No custom image blocks UI-R01 through UI-R03. Later phases may use:

1. A final Hospitrainity logo mark or wordmark lockup, if the owner wants more than the text wordmark.
2. Rights-cleared authentic hospitality photographs for at most a few contextual lesson or empty-state moments. Required metadata: source, license/permission, attribution, subject consent where applicable, and approved alt text.
3. Optional completion artwork only after its tone and use are approved. Trophy, star, badge, mascot, or reward art is not required and must not imply B14 gamification.

Use the installed Font Awesome line-icon family for current interface icons. Do not generate raster icons that duplicate it.

## Verification contract

Each UI-R phase ends with:

- manual visual review before the deterministic scan;
- design detector for layout/type where supported;
- token/contrast inventory and changed component states;
- Laravel, JavaScript, lint, build, and relevant E2E tests;
- rendered desktop and mobile inspection in light and dark themes;
- keyboard and reduced-motion checks;
- no production, physical-device, screen-reader, or WCAG conformance claim without the required external evidence.

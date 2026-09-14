---
name: Sentria
description: Order of Business — precision-technical UI for a Philippine LGU legislative record system
colors:
  shell: "#e6eaee"
  canvas: "#f2f5f8"
  canvas-sunk: "#e9eef3"
  surface: "#ffffff"
  surface-alt: "#f7f9fb"
  surface-raised: "#ffffff"
  ink: "#0f1419"
  ink-muted: "#4a5561"
  ink-subtle: "#667281"
  ink-faint: "#8b95a3"
  ink-inverse: "#ffffff"
  line: "#e3e7ec"
  line-strong: "#cdd4dc"
  line-control: "#8792a1"
  accent: "#0038a8"
  accent-hover: "#002d8a"
  accent-ink: "#0b3f9e"
  accent-soft: "#e8eefb"
  accent-line: "#b3c6ea"
  accent-on: "#ffffff"
  live: "#ce1126"
  live-hover: "#ad0e20"
  live-ink: "#a8121f"
  live-soft: "#fdeaec"
  live-line: "#f0b9c0"
  live-on: "#ffffff"
  success: "#0f7a45"
  success-soft: "#e6f4ec"
  success-line: "#a8d6bd"
  warning: "#8a5a00"
  warning-soft: "#fbf1de"
  warning-line: "#e0c489"
  critical: "#a8181f"
  critical-soft: "#fceceb"
  critical-line: "#ecb5b2"
  critical-on: "#ffffff"
  info: "#1d5b8f"
  info-soft: "#e8f1f8"
  info-line: "#aecbe2"
  machine-surface: "#f1f4f8"
  machine-line: "#c3cddb"
  machine-ink: "#3d566f"
  focus: "#0038a8"
  focus-halo: "#ffffff"
  scrim: "rgb(15 20 25 / 0.55)"
  scrim-strong: "rgb(15 20 25 / 0.6)"
  chart-1: "#0038a8"
  chart-2: "#3d7fc1"
  chart-3: "#0f7a45"
  chart-4: "#c08a1e"
  chart-5: "#94a1b0"
  chart-grid: "#e3e7ec"
  chart-track: "#eceff3"
typography:
  body:
    fontFamily: "Space Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.9375rem"
    fontWeight: 400
    lineHeight: 1.4375rem
  title:
    fontFamily: "Space Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1.4375rem"
    fontWeight: 600
    lineHeight: 1.8125rem
    letterSpacing: "-0.011em"
  panel-title:
    fontFamily: "Space Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 600
    lineHeight: 1.25rem
  label:
    fontFamily: "Space Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.6875rem"
    fontWeight: 600
    lineHeight: 1rem
    letterSpacing: "0.06em"
  mono:
    fontFamily: "Space Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 400
  figure:
    fontFamily: "Space Grotesk, ui-sans-serif, system-ui, sans-serif"
    fontSize: "2.25rem"
    fontWeight: 500
    lineHeight: 1
    letterSpacing: "-0.02em"
rounded:
  xs: "4px"
  sm: "6px"
  md: "10px"
  lg: "14px"
  xl: "20px"
  2xl: "24px"
spacing:
  base: "4px"
  gutter: "20px"
  section: "32px"
components:
  button-primary:
    backgroundColor: "{colors.accent}"
    textColor: "{colors.accent-on}"
    rounded: "{rounded.md}"
    height: "36px"
    padding: "0 14px"
  button-primary-hover:
    backgroundColor: "{colors.accent-hover}"
    textColor: "{colors.accent-on}"
  button-secondary:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    height: "36px"
  button-ghost:
    backgroundColor: "transparent"
    textColor: "{colors.ink-muted}"
    rounded: "{rounded.md}"
  button-live:
    backgroundColor: "{colors.live}"
    textColor: "{colors.live-on}"
    rounded: "{rounded.md}"
  button-danger:
    backgroundColor: "{colors.critical}"
    textColor: "{colors.critical-on}"
    rounded: "{rounded.md}"
  button-floor:
    height: "48px"
    padding: "0 20px"
  panel:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.lg}"
    padding: "20px"
  app-sheet:
    backgroundColor: "{colors.canvas}"
    rounded: "{rounded.xl}"
  stat-card:
    backgroundColor: "{colors.surface}"
    rounded: "{rounded.lg}"
  status-chip:
    rounded: "{rounded.xs}"
    padding: "2px 8px"
  input:
    backgroundColor: "{colors.surface}"
    textColor: "{colors.ink}"
    rounded: "{rounded.md}"
    height: "36px"
  nav-item:
    backgroundColor: "transparent"
    textColor: "{colors.ink-muted}"
    rounded: "{rounded.md}"
    height: "36px"
---

# Design System: Sentria

## Overview

**Creative North Star: "Order of Business"**

Sentria is a precision instrument for the legislative record of a Philippine LGU body. It is neither an archival costume nor a generic government SaaS admin. Authority is earned by typographic discipline, alignment, density, and unambiguous state — graphite and white on cool neutrals, hairline separation, one grotesque for every word. The only two saturated colours in the product are the two colours of the Philippine flag, each with exactly one job.

The binding register from product truth is professional, restrained, and document-focused. Anti-references are explicit: the discarded Transparency Board paper/filing-tab world, and the category default of white cards on grey with a blue primary and four KPI tiles. Staff desks stay dense and operable; the public portal is quieter and more readable; the session floor is larger, calmer, and tablet-first. Dark mode is the chamber under ambient light — the same system with lifted flag colours and ink-based scrims and shadows.

**Key Characteristics:**
- One grotesque (Space Grotesk) for every word — headings, body, identity, and measurement
- National blue marks where you act; national red marks what is happening now
- Content sits on a floating sheet inside a recessed shell; cards stand on that sheet as objects
- Panels and registers carry records; widget cards carry counted facts, and each one has to earn its place
- Full light/dark parity motivated by the dim chamber
- Tokens only — missing values are added in `resources/css/app.css`, never invented in components
- The Advance (~260ms wipe + accent rule) is the single authored moment when a record moves in its state machine

**Component base:** shadcn/ui (new-york) on Radix and Tailwind v4, configured in `components.json`. Upstream components are pulled in unmodified where they fit and reconciled where this product already had an opinion. See "Two vocabularies" below.

## Colors

Cool neutrals carry the product. Saturated colour is rationed.

### Primary
- **National Blue** (#0038A8): Where you act — primary buttons, current nav location, focus rings, the single accent on a surface. Filled controls use accent-on (#FFFFFF) for the label. Soft (#E8EEFB) and line (#B3C6EA) support chips and underlines.

### Secondary
- **National Red / Live** (#CE1126): What is happening now — in-session, voting open, quorum shortfall *status*, offline floor, irreversible floor actions (`live` buttons). Soft (#FDEAEC) and line (#F0B9C0) frame live chips and panel edges. Never decoration; never the digits of a tally or clock.

### Neutral
- **Shell** (#E6EAEE): The recessed frame the application sheet floats on — the only tone that touches the edge of the viewport.
- **Canvas** (#F2F5F8): Ground inside the sheet.
- **Canvas sunk** (#E9EEF3): Recessed strips, row hover, inactive segmented-control track.
- **Surface** (#FFFFFF): Cards, panels, registers, dialogs, and the nav rail; surface-alt (#F7F9FB) for sunk heads and feet.
- **Ink** (#0F1419): Body and headings; muted (#4A5561) / subtle (#667281) / faint (#8B95A3) for secondary copy.
- **Line / Line strong / Line control** (#E3E7EC / #CDD4DC / #8792A1): Separation vs interactive control borders (control clears 3:1 against its surface).
- **Scrim** (`rgb(15 20 25 / 0.55)`, strong `0.6`): Drawer and dialog overlays — ink at opacity, same base as elevation shadows. Dark mode uses canvas (`rgb(11 14 17 / …)`).

### Semantic
- **Success** (#0F7A45), **Warning** (#8A5A00), **Critical** (#A8181F), **Info** (#1D5B8F) — each with soft and line companions for chips, notices, and diffs. Critical-on fills danger button labels. Confidentiality reuses this palette: public→success, internal→ink-faint, restricted→warning, confidential→critical.

### Machine
- **Machine surface / line / ink** (#F1F4F8 / #C3CDDB / #3D566F): AI output is separated by material (cooler ground + diagonal hatch + permanent badge), never by a fifth brand hue.

### Data series
- **chart-1…5** (#0038A8 / #3D7FC1 / #0F7A45 / #C08A1E / #94A1B0), plus **chart-grid** (#E3E7EC) and **chart-track** (#ECEFF3). The ramp opens on the accent because the first series is almost always what the page is about, then walks away through the semantic hues into neutral. In a chart these are categories, not states: a warm bar is a ballot option, not an alarm.

### Two vocabularies, one set of values
Sentria names (`canvas`, `ink`, `line`, `accent`, `surface`) are the authored language of this product. shadcn names (`background`, `foreground`, `border`, `primary`, `card`, `sidebar-*`) are declared in the same `@theme` block as references to them, so upstream components drop in unmodified and follow dark mode for free. Overriding a Sentria token in `.dark` moves both. One deliberate exception: shadcn's `accent` is *not* the national blue — upstream uses `bg-accent` for hover tints, and in this product exactly one thing on a surface may be blue.

### Named Rules
**The Two-Colour Rule.** Only accent and live may be saturated. Everything else earns its place through type, alignment, and state. The chart ramp is the one sanctioned exception, and only inside a plot area.

**The Live Ration Rule.** Red appears only for facts that are live or urgent. Tallies and clocks stay ink. Unmet quorum may announce itself in live *copy*; the counted digits remain ink.

## Typography

**Body / UI Font:** Space Grotesk (ui-sans-serif, system-ui)  
**Mono / figure Font:** Space Grotesk (IDs, hashes, ULIDs, tallies, clocks, reference numbers)

**Character:** One family carries headings, labels, body, and data. No serif for “official,” no condensed caps costume, no second display face.

### Hierarchy
- **Title** (600, 1.4375rem, −0.011em): Page `h1` via `PageHeader`.
- **Panel / card title** (600, 0.875rem): Section heads inside panels and widget cards.
- **Body** (400, 0.9375rem / 1.4375rem): Default UI copy; record prose (`record-prose`) max ~68ch at 1rem / 1.75rem.
- **Label eyebrow** (600, 0.6875rem, uppercase, 0.06em tracked): Column heads, widget labels, and provenance labels only — never stacked above a page heading.
- **Figure** (mono 500, 1.5 / 2.25 / 3 / 4rem, tabular): Quorum, tallies, elapsed time, the headline number on a widget card — always ink.

The base size was relaxed one notch (0.875rem → 0.9375rem) when the shell opened up. The register did not follow: it earns its density back through row height and alignment rather than small type.

### Named Rules
**The One Family Rule.** Product UI does not need a display/body pairing.  
**The Mono Measurement Rule.** Mono is for identity and measurement, never as a “technical” costume.

## Layout

- **Staff shell:** A recessed shell-toned frame holds a transparent 16rem sidebar and a floating application sheet (`radius-xl`, `shadow-sheet`) carrying a sticky top bar and the content. Built on shadcn `SidebarProvider` with `collapsible="icon"`: the rail collapses to icons on desktop and becomes a `Sheet` below `lg`. Content runs to `max-w-[92rem]`.
- **Widget grid:** Dashboards and index pages use a 12-column grid at `lg` (stacking to one column on small screens), gutter 20px. Widgets are rendered only when the server sent their data, so an eight-role permission matrix needs no per-role page.
- **Index pattern:** Stat strip, filter bar, register in a card, pagination — established by `Documents/Index` and copied by every other list page.
- **Session floor:** Tablet-first, `max-w-6xl`, floor controls at 48px. One dominant live panel above a card grid of counted facts. The floor may show three or four figure cards, but each is a distinct question and the live panel still leads.
- **Portal:** Wider reading measure, quieter density, same tokens.
- **Density:** Index registers target many visible rows; 4px spacing rhythm; gutters 20px; section gaps 32px; card padding 20px.
- **PageHeader:** Title + optional status + actions on one band; provenance below. No eyebrow above the `h1`.
- **Focus:** 2px accent outline, 2px offset, `radius-sm` — one treatment everywhere.

## Elevation & Depth

Hybrid: tonal layering (shell vs canvas vs surface vs surface-alt) plus a short, wide, faint shadow scale. No hard-offset or neobrutalist block shadows. Light shadows use ink (`rgb(15 20 25 / …)`); dark shadows use near-black (`rgb(3 5 7 / …)`).

### Shadow Vocabulary
- **xs** (`0 1px 2px … / 0.04`): Resting cards, panels, registers.
- **sm**: Slightly raised controls.
- **md**: Raised or live-emphasis panels, dropdowns.
- **lg**: Dialogs and drawers.
- **sheet**: The application sheet itself — one shadow, cast once, at the outermost frame.

### Named Rules
**The Flat-By-Default Rule.** Surfaces rest nearly flat. Shadow answers state (raised live panel, overlay), not decoration.

**The One Sheet Rule.** Depth is spent on the frame, not repeated inside it. A card on the sheet gets a hairline and shadow-xs; nothing stacks a second sheet shadow within the content.

## Shapes

Softened modern geometry: 4 / 6 / 10 / 14 / 20 / 24px (`radius-xs` → `2xl`). Cards read as objects you could pick up rather than cells in a spreadsheet. No pills except the live pulse dot and the avatar. Status chips use `radius-xs` with a 1.5px square marker (round only when live). Cards, panels, and stat cards use `radius-lg`; buttons, inputs, and nav pills use `radius-md`; the application sheet uses `radius-xl`. The bare `--radius` that upstream shadcn components reference resolves to `0.875rem`. Confidentiality panel edges are a 2px left border — colour never the sole carrier.

## Components

### Buttons
- **Shape:** Softly squared (10px); floor size is 48px tall for chamber tablets.
- **Primary:** Accent fill + accent-on label; at most one per surface.
- **Secondary:** Surface fill, line-control border, ink label — the default.
- **Ghost:** Transparent; muted ink; sinks on hover.
- **Live:** National red fill for irreversible floor acts only (open voting, adjourn).
- **Danger:** Critical fill + critical-on label.
- **Link:** Accent text with accent-line underline.
- **Focus:** Shared accent outline; disabled at 45% opacity.
- **Aliases:** shadcn's `default`, `outline`, and `destructive` map onto primary, secondary, and danger so upstream blocks paste in unchanged. New code uses the Sentria names.

### Status chips
- **Style:** Soft fill + matching line + ink tone; `radius-xs`; 2px × 8px padding.
- **Marker:** Hollow = still in motion; filled = settled; live pulses as a round dot.
- **Tones:** draft, moving, review, live, final, closed, blocked — mapped from state-machine slugs via `toneForState`.
- **Advance:** Optional wipe animation when the record has just moved.

### Panels / containers
- **Card:** The bare shadcn container — 14px corner, hairline border, shadow-xs, 20px padding. Used for dashboard widgets and anything composed from upstream blocks.
- **Panel:** `Card` plus the states a legislative record needs. Same corner and hairline; shadow-md when raised.
- **Live:** Optional top live rule (advance animation) and live-line border.
- **Internal:** Head / body / foot / section — sunk heads use surface-alt. Do not nest panels.

### Inputs / fields
- **Style:** 36px tall, 10px radius, line-control border, surface fill.
- **Focus:** Accent border + shared focus ring.
- **Invalid:** Critical border *and* critical-soft fill — never colour alone.
- **Select / Textarea / Checkbox:** Same vocabulary; checkbox uses `radius-xs` and accent when checked.

### Navigation
- **Staff rail:** Transparent over the shell, 16rem, collapsing to an icon rail. Items are 36px pills at `radius-md`; the current location is an accent-soft pill with accent ink and an accent marker. Groups: chamber / record / assist / oversight.
- **Top bar:** Sits inside the sheet — sidebar trigger, page context, theme toggle, user menu.
- **Floor tabs:** Larger hit targets; accent underline for current view.
- **Portal:** Quieter top chrome; same tokens.

### Widgets
- **StatCard:** Label, one big ink figure, optional delta, optional detail line, optional action. One counted fact per card.
- **MetricDelta:** Signed percentage with a directional caret. Direction is not sentiment — an intent prop decides whether up is good, because more overdue referrals is not an improvement.
- **ChartCard:** Title, description, optional action, chart, optional summary line beneath.
- **QuickList:** Icon tile + two-line row + right-aligned figure. Referral queues, recent activity.
- **ActionTile:** Permission-filtered quick actions in a row of tiles.
- **RangeToggle:** Segmented 7d / 30d / 90d control that re-requests the page through an Inertia partial reload.
- **Gauge:** Radial SVG arc with a threshold tick, for quorum on the floor.

### Charts
Recharts through shadcn's `ChartContainer`, rendered only after mount so the SSR pass never touches a browser API. Series colours come from the chart ramp, the grid from `chart-grid`, and every chart is decorative in the strict sense: the figures it visualises are always printed beside it, so a chart that never draws costs no information. Charts are `aria-hidden` where the underlying numbers are already in the accessibility tree.

### Signature components
- **Register:** Dense table — sticky head, hairlines, no vertical rules, no zebra; hover tint only; primary cell in ink; numeric cells in mono; empty via `RegisterEmpty` + `EmptyState`.
- **Figure / FigureRow:** Counted facts in ink mono, hairline-separated columns in one panel.
- **QuorumMeter / VoteBoard:** Chamber-readable seat markers and tally columns; digits stay ink. On a floor surface the meter adds a radial arc and the board an optional proportional bar, both additions to the counted figures rather than replacements.
- **Flash:** Inertia flash messages are dispatched as `sonner` toasts from `FlashRegion`; standing constraints stay as `Notice`.
- **Confidentiality:** Dot (register), Mark (detail), Legend (index), optional 2px panel edge.
- **Notice:** Standing domain constraints; `role="alert"` for danger/live, `role="status"` otherwise.
- **AiContent:** Machine surface + hatch + permanent badge and verify copy.
- **Provenance:** Who / when / reference in mono.
- **Toolbar:** Workflow actions for a record or session.
- **PageHeader:** Single h1 band — title, status, actions, provenance.

## Do's and Don'ts

### Do
- **Do** spend accent on the one action or current location that matters.
- **Do** keep Filipino string length in mind — wrap, never clip tabs or cells.
- **Do** maintain light and dark parity from tokens.
- **Do** put workflow actions in a `Toolbar`.
- **Do** pass every user-facing string through `t()` (en + fil).
- **Do** add missing values in `resources/css/app.css` before inventing a local colour.
- **Do** print the figures a chart is drawn from, so the surface still works if the chart never renders.
- **Do** gate every widget server-side on the permission its data requires, and render it only when its prop arrives.

### Don't
- **Don't** reintroduce cream paper, filing tabs, stamp double-rules, or serif “record” type.
- **Don't** ship a row of undifferentiated KPI tiles. A widget states one fact someone acts on; four boxes counting the same table four ways is decoration.
- **Don't** let a chart be the only place a number appears.
- **Don't** nest panels or cards inside cards.
- **Don't** use red for numbers that are not live (tallies, clocks, quorum counts).
- **Don't** offer dead `/users` or `/roles` destinations (routes unimplemented).
- **Don't** invent LGU seals, photography, or certification claims.
- **Don't** stack a label-eyebrow above a page `h1`.

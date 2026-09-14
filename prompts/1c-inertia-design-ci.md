# 1c — Inertia Shell, Design System, i18n, CI

## Prerequisite

- Slice `1b` must-pass complete
- Load [00-shared-constraints.md](00-shared-constraints.md)

## In scope

- Base Inertia + React layout, navigation driven by permissions/roles
- Design system foundations: color tokens, typography, spacing, elevation, component variants, AI-content badge/container style
- English + Filipino i18n wiring (no hard-coded user-facing strings in new components)
- Dashboard framework (empty/role-aware shell)
- GitHub Actions CI: Pint (check), Larastan (project target level 8), ESLint, Prettier (check), Pest, frontend build
- Apply design skills: re-read shared design rules; light Refero only if establishing the overall government/document visual direction for the first time

## Out of scope

- Full module UIs (documents, sessions, voting)
- Reverb, AI features, public portal SSR pages
- Deep Refero research for every page

## Implementation prompt

```text
CONTINUE THE SAME PROJECT. Load 00-shared-constraints.md.

Before writing frontend code, read the installed design skills (refero-design) enough to establish a restrained government document-focused design system. Do not over-research every screen.

Implement:
1. Base Inertia React layouts (desktop-first admin/secretariat; structure ready for tablet session mode later).
2. Design tokens and base shadcn/ui usage; AI-generated content visual treatment (badge + container) defined now even if unused.
3. Navigation that respects roles/permissions from 1b.
4. Dashboard framework per role (can be sparse).
5. i18n: Laravel lang + frontend translation layer shared with Inertia; English default + Filipino.
6. GitHub Actions workflow on every push: Pint check, Larastan level 8, ESLint, Prettier check, Pest, frontend build.
7. WCAG 2.1 AA basics on shell: focus states, semantic landmarks, contrast via tokens.

Do not implement document/session business modules yet.
Do not implement AI.
CI must pass before this slice is complete.
```

## Must-pass

- [ ] Each seeded role sees role-appropriate navigation after login
- [ ] UI uses design system tokens; no hard-coded colors/spacing in new components
- [ ] AI-content visual treatment exists in the design system
- [ ] User-facing strings in new UI go through i18n
- [ ] CI pipeline passes (Pint, Larastan level 8, ESLint, Prettier, Pest, frontend build)

## Should-pass

- [ ] Basic axe/accessibility smoke check documented
- [ ] Dark-mode avoided unless organizationally required (prefer restrained light government look)

## Report format

Files created; how to run app + CI locally; screenshots optional; debt for Part 2.

## Next

[2a-documents-committees.md](2a-documents-committees.md)

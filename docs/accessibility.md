# Accessibility smoke check (slice 1c)

Shell targets WCAG 2.1 AA basics:

- Semantic landmarks (`header`, `nav`, `main`, skip link)
- Visible `:focus-visible` rings via design tokens
- Contrast through the restrained government palette in `resources/css/app.css`
- Form labels on auth screens

**Manual axe check:** sign in as `secretariat@sentria.test`, open `/dashboard`, run the axe DevTools browser extension. No automated axe gate in CI yet.

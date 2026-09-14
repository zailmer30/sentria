# 5a — Public Legislative Portal and Publication Workflow

## Prerequisite

- Slice `4c` must-pass complete
- Load [00-shared-constraints.md](00-shared-constraints.md)

## In scope

- Public-facing portal with Inertia SSR (indexable pages, titles, meta, Open Graph)
- Public search of published ordinances, resolutions, approved legislation, public minutes/agendas, schedules, legislative history
- Publication workflow state machine; only explicitly PUBLIC documents appear
- Unpublished documents return **404** (not 403) for anonymous users
- Focused Refero research for public government portal surfaces

## Out of scope

- Full security audit pass (`5b`)
- PWA offline / backup / deploy manuals (`5c`)

## Implementation prompt

```text
CONTINUE THE SAME PROJECT. Load 00-shared-constraints.md.

Before frontend work, research public government / legislative search portal references (refero-design), then implement with design-system discipline. Public portal must be fast, WCAG 2.1 AA, readable on any device.

PUBLIC PORTAL (SSR):
Public users may search published ordinances, resolutions, approved legislation, public minutes, public agendas, session schedules, legislative history.

Public users must NOT see drafts, confidential/internal committee docs, private notes, internal AI conversations, restricted docs, audit logs, or sensitive personal information.
Only documents explicitly marked PUBLIC may display.

PUBLIC SEARCH filters: type, year, author, committee, status, keyword, date.
Keyword, full-text, and semantic search where appropriate — still only over public corpus.

PUBLICATION WORKFLOW (state machine — names must match 00-shared-constraints.md):
Internal Document → Secretariat Review → Publication Review → Mark Public → Publish → Public Portal.
Never expose internal documents merely because they exist in the DB.

Tests: anonymous can read published ordinance with SSR content + meta tags; unpublished returns 404 not 403 (IDs not confirmable); no leakage of AI/internal fields.
```

## Must-pass

- [ ] Anonymous user can find and read a published ordinance; view-source shows content with correct meta tags
- [ ] Anonymous user gets 404 (not 403) for any unpublished document
- [ ] Internal AI conversations and audit logs are unreachable on public routes
- [ ] CI still passes

## Should-pass

- [ ] Open Graph previews verified with external debugger
- [ ] Public semantic search quality eval set

## Report format

Public routes, publication states, SEO notes, tests, debt for `5b`.

## Next

[5b-security-privacy-hardening.md](5b-security-privacy-hardening.md)

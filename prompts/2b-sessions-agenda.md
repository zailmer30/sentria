# 2b — Sessions, Agenda, Attendance, Paperless Session UI

## Prerequisite

- Slice `2a` must-pass complete
- Load [00-shared-constraints.md](00-shared-constraints.md)

## In scope

- Session management (Regular, Special, Committee Hearing, Public Hearing) with session state machine
- Electronic agenda (tablet-friendly) linking documents
- Attendance statuses and recording
- Paperless Session Mode UI: Board Member, Secretariat, Presiding Officer surfaces + Session Dashboard shell
- Quorum status display from configurable `system_settings` (humans decide; AI must not decide quorum)
- Bookmarks, private notes (owner-only), related docs / version history links
- Deep Refero research allowed once for the **session floor** surface family

## Out of scope

- Motions, electronic voting integrity, Reverb live updates (slice `2c`) — UI placeholders OK
- AI summaries/search (placeholder only)
- Transcription, public portal

## Implementation prompt

```text
CONTINUE THE SAME PROJECT. Load 00-shared-constraints.md.

Before frontend work on session surfaces, re-read design skills; do focused refero-design research for tablet paperless session / agenda reading interfaces, then implement with that discipline.

SESSION MANAGEMENT:
Types: Regular Session, Special Session, Committee Hearing, Public Hearing.
Fields: Session Number, Type, Date, Start/End Time, Venue, Presiding Officer, Secretariat, Status (state machine), Quorum display, Agenda, Attendance, attachments.
All status changes go through the session state machine; permission-checked and audit-logged.

ELECTRONIC AGENDA (tablet-friendly example structure):
Call to Order, Roll Call, Approval of Previous Minutes, Communications, Committee Reports, Proposed Ordinances, Proposed Resolutions, Unfinished Business, New Business, Other Matters, Adjournment.
Each item links to documents. Users can open/search documents, bookmark, add private notes, view related documents and version history. AI summary = placeholder.

ATTENDANCE:
Present, Absent, Excused, Late, Official Business — with user, session, time in/out, remarks.

PAPERLESS SESSION MODE (tablet-first, large touch targets, distraction-free reading):
- Board Member: current session/item, open document, placeholders for AI summary, related docs, history, attachments, my notes
- Secretariat: start/pause/resume/next item/adjourn controls (motions/voting controls may be stubbed until 2c)
- Presiding Officer: current item, status, attendance, quorum display, next item
- Session Dashboard: current session/item, members present, quorum status, elapsed time

Quorum: compute/display from system_settings threshold; never auto-block legislative process without human action.

Tests: session transitions, attendance permissions, Board Member cannot hit Secretariat control endpoints, private notes isolation.
```

## Must-pass

- [ ] Create session → prepare agenda → start session → advance agenda item → adjourn via state machine
- [ ] Tablet-oriented session UI usable for Board Member and Secretariat roles
- [ ] Board Member cannot reach Secretariat session controls (URL/API, not just hidden buttons)
- [ ] Private notes are owner-only
- [ ] CI still passes

## Should-pass

- [ ] PDF viewer performance polished for large documents
- [ ] Full motion/vote UI (required in `2c`)

## Report format

Inertia pages, session flows, tests, debt for `2c`.

## Next

[2c-voting-realtime-audit.md](2c-voting-realtime-audit.md)

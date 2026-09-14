# 2a — Documents, Versions, Ordinances, Committees

## Prerequisite

- Slice `1c` must-pass complete
- Load [00-shared-constraints.md](00-shared-constraints.md)

## In scope

- Document management (PDF, DOCX, XLSX, images, scanned): upload, view, download (permissioned), search/filter, metadata edit, archive, soft delete, restore
- Versioning: every modification creates a new version; never overwrite an official version; basic text comparison highlighting
- Document ACL: role + confidentiality + `document_grants`; audit log events for upload/view/download/modify/delete
- Malware-scan architecture: interface + stub adapter
- Ordinances and resolutions modules
- Committees, members, chairs, referrals, reports, recommendations
- Legislative workflow transitions for documents through committee stages (as applicable)

## Out of scope

- Live session UI, attendance, motions, voting, Reverb (slices `2b`/`2c`)
- OCR/embeddings/AI summaries (Part 3) — AI summary UI may be a labeled placeholder only
- Public publication portal (Part 5)

## Implementation prompt

```text
CONTINUE THE SAME PROJECT. Load 00-shared-constraints.md.

Implement Legislative Document and Committee modules.

DOCUMENT MANAGEMENT:
Support PDF, DOCX, XLSX, images, and scanned documents.

Metadata includes: Document ID, Document Number, Document Type, Title, Description, Author, Originating Office, Date Filed, Date Received, Committee, Session, Status (state machine), Confidentiality Level, Version, Created By, Updated By, timestamps.

Document types: Ordinance, Resolution, Committee Report, Communication, Memorandum, Agenda, Minutes, Endorsement, Proposal, Attachment, Supporting Document.

Upload, permissioned view/download, search, filtering, metadata editing, versioning, archive, soft delete, restore, audit logging.

DOCUMENT VERSIONING:
Every modification creates a new version. Never overwrite an official version.
Example: Ordinance 2026-015 — v1 Initial Draft, v2 Committee Amendments, v3 Second Reading, v4 Final.
Implement comparison highlighting added/removed/changed text, amounts, dates, sections where feasible.

ACL:
Enforce role + confidentiality level + explicit document_grants. Private notes (if any early) are owner-only.

Malware scan: pluggable interface with no-op/stub in development.

COMMITTEES / ORDINANCES / RESOLUTIONS:
Committees, members, chairs, referrals, hearings fields, reports, recommendations.
Ordinance and resolution fields per shared domain (number, year, title, authors, committee, status, dates, final document).

Tests: authorization on view/download, version immutability of prior versions, workflow transition guards for document states touched here.

Do not implement Reverb voting or AI pipelines yet.
AI summary on document view may be a placeholder labeled for later.
```

## Must-pass

- [ ] Upload and version a document; prior versions remain readable and unchanged
- [ ] User without grant/role cannot view or download a restricted document (URL and policy level)
- [ ] Ordinance and resolution CRUD works for authorized roles with workflow state machine
- [ ] Committee referral → report path works with seeded data
- [ ] CI still passes

## Should-pass

- [ ] Rich DOCX/PDF textual diff quality beyond plain extracted text
- [ ] Real ClamAV adapter (stub is enough for must-pass)

## Report format

Modules, migrations/pages, permission notes, tests, debt for `2b`.

## Next

[2b-sessions-agenda.md](2b-sessions-agenda.md)

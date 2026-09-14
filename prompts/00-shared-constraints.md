# Shared Constraints — Load With Every Slice

> Every agent session must load this file **plus exactly one slice**. Do not proceed without these invariants.

## Product

Build a production-quality AI-powered Paperless Legislative Session and Legislative Management Information System (LMIS) for a Philippine government legislative office (Sangguniang Panlalawigan / Panlungsod / Bayan).

Digitize the flow from document submission through committee review, agenda, paperless sessions, voting, minutes, approval, archiving, and public publication.

- Do not create a simple demo or mockup.
- AI assists government personnel but **never** replaces official legislative decision-making.
- Use fictional/demo data during development.
- **Continue the same project.** Do not rebuild from scratch between slices.

## Technology (pinned — do not substitute)

| Layer | Choice |
| --- | --- |
| Backend | Laravel 13 (newest stable `laravel/framework`) on PHP 8.4 |
| Frontend | React 19 + TypeScript (strict) via Inertia.js v2, SSR enabled |
| Build | Vite |
| Styling | Tailwind CSS v4 + shadcn/ui |
| Real-time | Laravel Reverb + Laravel Echo (no polling for live session surfaces) |
| Database | PostgreSQL 17 |
| Cache/Queue | Redis + Laravel Horizon |
| Vector search | PostgreSQL + pgvector |
| Storage | S3-compatible or secure local/NAS via Laravel filesystem |
| Search | PostgreSQL full-text initially |
| Auth | Laravel Fortify (session) for Inertia; Sanctum tokens only for external API consumers |
| RBAC | `spatie/laravel-permission` |
| Auditing | `owen-it/laravel-auditing` **plus** custom append-only `audit_logs` |
| Workflow | `spatie/laravel-model-states` (never bare status strings) |
| Testing | Pest |
| Quality | Laravel Pint, Larastan (target level 8 by slice `1c`), ESLint, Prettier |

Do not hand-roll RBAC, state machines, or auditing when these packages solve the problem.

Prefer Laravel 13 first-party AI / vector features **only where they fit**. Keep named service interfaces (`DocumentSummarizationService`, `RAGService`, `LegislativeSearchService`, `DocumentComparisonService`, `RelatedDocumentService`, `ConsistencyCheckService`, `MinutesGenerationService`, `TranscriptionService`) so providers stay swappable. Never hard-code API keys; never expose them to the frontend.

## Product defaults (locked)

| Decision | Default |
| --- | --- |
| Tenancy | Single LGU / single organization (no multi-tenant SaaS) |
| Document ACL | Role + confidentiality level + explicit document grants; private notes always owner-only |
| Quorum | Configurable threshold in `system_settings` (default: majority of seated members). UI shows status; **humans** decide whether to proceed |
| Malware scan | Pluggable interface + no-op/stub adapter in development; ClamAV (or equivalent) adapter documented for production |
| AI chat/embeddings | OpenAI-compatible provider via config |
| OCR | Pluggable; Tesseract and/or cloud OCR adapter |
| Speech-to-text | Whisper-compatible (or equivalent) via config |
| Records retention | Official legislative records: soft-archive only. Hard delete disabled unless dual-admin confirmation + audit |

## Primary keys — ULIDs

- Use ULIDs (not UUIDs, not sequential integers) as primary keys for all tables.
- `HasUlids` on models; `$table->ulid('id')->primary()` and `$table->foreignUlid()`.
- Store as `CHAR(26)` in PostgreSQL with B-tree indexes.
- Never expose sequential integer IDs in URLs or API responses.
- Password reset tokens, signed URLs, invitation tokens, and session tokens must use cryptographically random values — **not** ULIDs.

## Internationalization

- Backend: Laravel lang files.
- Frontend: all UI strings through a translation layer shared with Inertia.
- Languages: English (default) and Filipino.
- No hard-coded user-facing strings in components.

## User roles (canonical)

1. System Administrator
2. Secretariat
3. Presiding Officer
4. Board Member
5. Committee Chair
6. Committee Member
7. Legal/Technical Reviewer
8. Public User

AI inherits the authenticated user's permissions. Never bypass RBAC for AI.

## Core modules (canonical list)

Dashboard, Users, Roles, Permissions, Sessions, Agenda, Documents, Document Versions, Ordinances, Resolutions, Committees, Committee Referrals, Committee Reports, Attendance, Motions, Voting, Minutes, Transcripts, Notifications, Audit Logs, AI Assistant, AI Conversations, AI Citations, Publications, System Settings, Backup/Recovery.

## Session statuses (state machine)

`Draft` → `Scheduled` → `Agenda Prepared` → `Documents Distributed` → `In Session` ↔ (`Suspended` / `Resumed`) → `Adjourned` → `Minutes for Review` → `Finalized` → `Archived`

Every transition: permission-checked and audit-logged. Invalid transitions throw, are tested, and are audit-logged when attempted.

## Legislative document workflow (state machine — configurable IRP)

`Document Submitted` → `Secretariat Review` → `Registered` → `Committee Referral` → `Committee Review` → `Committee Report` → `Agenda Inclusion` → `Reading/Deliberation` → `Amendments` → `Voting` → `Approved` / `Rejected` → `Final Document` → `Transmittal` → `Archive` → `Public Publication`

Do not hard-code Philippine procedure assumptions. Transitions live in the state machine, never scattered across controllers.

## Minutes workflow (state machine)

`Session Completed` → `AI Draft` → `Secretariat Review` → `Edit` → `Review` → `Approval` → `Final Minutes` → `Archive`

AI must never automatically finalize minutes.

## Publication workflow (state machine)

`Internal Document` → `Secretariat Review` → `Publication Review` → `Mark Public` → `Publish` → `Public Portal`

Only explicitly public documents appear on the public portal. Unpublished documents return **404** (not 403) for anonymous users.

## Canonical tables (minimum)

`users`, `roles`, `permissions`, `sessions`, `session_attendance`, `agenda_items`, `documents`, `document_versions`, `document_metadata`, `document_grants`, `private_notes`, `bookmarks`, `committees`, `committee_members`, `committee_referrals`, `committee_reports`, `ordinances`, `resolutions`, `motions`, `votes`, `minutes`, `transcripts`, `notifications`, `audit_logs`, `ai_conversations`, `ai_messages`, `ai_citations`, `document_embeddings`, `publications`, `system_settings`

## AI principles (non-negotiable)

AI must **not**:

- Vote, approve/reject legislation, or make legislative decisions
- Modify official records automatically
- Bypass permissions
- Provide unauthorized legal conclusions
- Finalize minutes, determine quorum, or determine vote results from audio when official voting data exists
- Silently modify or decide official government records

Humans remain responsible for legislative decisions, official minutes, voting, approvals, legal interpretation, and official records.

Every AI surface must show a clear AI-generated / verify-against-original treatment defined in the design system.

## Security invariants (non-negotiable)

- Authentication, authorization, RBAC, CSRF, validation, rate limiting, secure uploads, session timeout.
- `votes` and `audit_logs` are append-only at the application **and** database layer (triggers or revoked privileges).
- Vote submission is idempotent: unique constraint on `(session_id, agenda_item_id, voting_round, user_id)`.
- Audit rows hash-chain: each stores hash of (content + previous hash); artisan command verifies the chain.
- RAG: authorization filter is part of the vector/SQL retrieval query — **not** a post-filter on chunks.
- Public portal: only explicitly public documents; unpublished documents return **404**, not 403 (see Publication workflow).
- Uploaded documents must never override system instructions (prompt injection / indirect injection defenses).
- Electronic voting is **not** automatically legally binding — configurable per organization rules.
- Do not claim automatic legal compliance with RA 10173 or other statutes; produce checklists for ICT / legal / records / DPO review.

## Design rules

- Professional government-style UI: authoritative, restrained, document-focused.
- WCAG 2.1 AA: keyboard nav, visible focus, contrast, screen-reader labels, semantic HTML.
- Tablet-first for Paperless Session Mode; desktop-first for admin/secretariat.
- Design system tokens only — no ad-hoc colors/spacing in components.
- Re-read shared design rules once per slice. Deep Refero research only for **net-new surface families** (session floor, public portal, live transcript) — not every micro-page.
- Skills live under `.cursor/skills/` or `.claude/skills/`: `refero-design`.

## Testing & CI

- Every slice must keep CI green: Pint (check), Larastan, ESLint, Prettier (check), Pest, frontend build.
- Do not skip tests for in-scope behavior.
- Prefer feature tests for authorization and state transitions.

## End-of-slice report

Always report: what was created, files touched, how to run/verify, remaining should-pass debt, and the next slice ID.

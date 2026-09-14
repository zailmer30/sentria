# Product

## Platform

web

## Users

Eight canonical roles (`app/Enums/UserRole.php`), seeded in `database/seeders/PermissionMatrixSeeder.php`. Three distinct physical scenes:

**The chamber (tablet, live, public, time-pressured).** During a session the room is occupied by:

- **Board Member** — seated at a bench with an assigned tablet. Reads the agenda and the measure under deliberation, moves and seconds motions, casts Yes / No / Abstain / Inhibit, keeps owner-only private notes. Tablet-first is a pinned constraint.
- **Presiding Officer** — presides from the rostrum. Advances the agenda, suspends/resumes/adjourns, rules on motions, opens and closes voting, and reads the quorum indicator (the system computes it; the officer alone decides whether to proceed). Also reviews, approves, and finalizes minutes.
- **Committee Chair / Committee Member** — seated members in plenary, using the same member floor view; at a desk for committee work.

**The desk (desktop, deliberate, records-grade).**

- **Secretariat** — the operational center of the product. Registers documents, builds and orders the agenda, records attendance, opens/closes voting when directed, drafts and reviews minutes, runs the publication workflow.
- **Legal/Technical Reviewer** — legal form and consistency review, document review, audit-log reading. No voting or session control.
- **System Administrator** — users, roles, backups, monitoring, queue health. Not a seated member.

**The public (any browser, unauthenticated).**

- **Public User** — browses published ordinances, resolutions, minutes, and the session schedule on `/portal`. Never sees the session floor.

Seated members (count toward quorum, may vote): Presiding Officer, Board Member, Committee Chair (`UserRole::isSeatedMember()`).

## Product Purpose

Sentria digitizes the full legislative record for a single Philippine LGU legislative body (Sangguniang Panlalawigan / Panlungsod / Bayan), end to end:

document submission → secretariat review → registration → committee referral → committee review → committee report → agenda inclusion → reading/deliberation → amendments → voting → approval → final document → transmittal → archive → public publication

It replaces paper agenda packets, manual roll-call and vote tallying, and disconnected document/minutes/publication handoffs with an electronic agenda, floor tablets, append-only electronic ballots, and a publication pipeline that feeds a public portal.

Success: staff run the entire session lifecycle in-system with permission-checked, audit-logged state transitions; members read materials and vote from the floor even when the network drops; the secretariat produces human-approved minutes; published records reach the portal while unpublished records are indistinguishable from nonexistent ones. Humans remain accountable for quorum, vote declarations, minutes approval, legal interpretation, and publication.

## Positioning

Mechanisms that exist in code and that a neighboring records system could not truthfully claim:

- **Hash-chained append-only audit trail.** Every `audit_logs` row stores a hash of its content plus the previous row's hash; `php artisan audit:verify-chain` walks the chain. Tampering is detectable, not merely discouraged.
- **Append-only ballots with database-level idempotency.** Unique `(session_id, agenda_item_id, voting_round, user_id)`; `VotePolicy` forbids update and delete; append-only enforced at the application *and* database layer via triggers/revoked privileges.
- **Authorization inside the vector query.** RAG filters on `document_embeddings.confidentiality`, `is_public`, and explicit grants within the SQL/vector retrieval itself, never as a post-filter on returned chunks.
- **Offline-tolerant floor voting.** A service worker caches the agenda and linked documents for `/sessions/{id}/floor/*`; votes cast offline queue in localStorage and flush idempotently on reconnect.
- **Guarded state machines, never bare status strings.** `spatie/laravel-model-states` for sessions, documents, minutes, and publications; every transition permission-checked and audit-logged, invalid transitions throw and are logged when attempted.
- **Human-in-the-loop AI by construction.** AI inherits the caller's permissions and may never vote, finalize minutes, determine quorum, derive vote results from audio when official voting data exists, or publish.
- **404, not 403, for unpublished records** on the public portal.

## Operating Context

**Session ritual.** A session runs: Call to Order → Roll Call and Determination of Quorum → measure items in agenda order → Adjournment. Motions move through proposed → seconded → ruled/carried. Voting happens in numbered rounds; choices are Yes, No, Abstain, Inhibit. Adjourning automatically dispatches `GenerateMinutesDraftJob`. Session types: Regular Session, Special Session, Committee Hearing, Public Hearing.

**Session states** (`app/States/Session/SessionStatus.php`): `draft` → `agenda-prepared` → `scheduled` → `in-session` ↔ `suspended` → `adjourned` → `minutes-for-review` → `finalized` → `archived`.

**Document states** (`app/States/Document/DocumentWorkflowStatus.php`): `submitted` → `secretariat-review` → `registered` → `committee-referral` → `committee-review` → `committee-report` → `agenda-inclusion` → `reading-deliberation` → `amendments` ↔ `reading-deliberation` → `voting` → `approved`/`rejected` → `final-document` → `transmittal` → `archive` → `public-publication`.

**Minutes states** (`app/States/Minutes/MinutesStatus.php`): `session-completed` → `ai-draft` → `secretariat-review` → `edit` ↔ `review` → `approval` → `final-minutes` → `archive`. AI may reach `ai-draft` only. Official vote tallies come from the `votes` table, never from transcription.

**Publication states** (`app/States/Publication/PublicationWorkflowStatus.php`): `internal-document` → `secretariat-review` → `publication-review` → `mark-public` → `published`. Only `published` reaches the portal.

**Committee workflow.** Referrals carry `pending` / `in-review` / `reported`; committee reports carry `draft` / `submitted` / `adopted`.

**Realtime.** Laravel Reverb + Echo on private channels `session.{id}` (`SessionStateChanged`, `AttendanceUpdated`, `MotionRecorded`, `VotingOpened`, `VoteCast`, `VotingClosed`, `AgendaItemChanged`, `HallDisplayChanged`, `HallDisplayViewChanged`) and `session-transcript.{id}` (`TranscriptSegmentReceived`, `TranscriptUpdated`). No polling on live surfaces.

**Confidentiality levels** on documents: public, internal, restricted, confidential — plus explicit `document_grants`. Private notes are always owner-only.

## Capabilities and Constraints

**Confirmed functionality.** Fortify session auth with no public self-registration; full RBAC matrix; document ACL by role + confidentiality + grants; document versions, comparison, archive/restore; committees, referrals, reports; ordinances and resolutions linked to documents; sessions, agenda CRUD/reorder/advance, attendance; four floor views (member, secretariat, presiding, dashboard); motions; electronic voting; live transcript upload/search/correction; a session AI assistant; global AI Q&A with citations; document comparison and consistency review; legislative history timelines; publications workflow and SSR public portal; audit log viewer; admin monitoring; documented backup/restore commands; PWA for session-floor paths.

**Constraints.**

- **Single organization.** No multi-tenancy.
- **Pinned stack, not substitutable:** Laravel 13 / PHP 8.4, React 19 + TypeScript strict via Inertia v2 with SSR enabled, Vite, Tailwind CSS v4 + shadcn/ui, Reverb + Echo, PostgreSQL 17 + pgvector (1536-dim), Redis + Horizon, Pest.
- **No hard-coded user-facing strings.** All UI copy passes through a translation layer shared over Inertia; English and Filipino must both be maintained (`lang/en.json`, `lang/fil.json`).
- **Design-system tokens only** — no ad-hoc colors or spacing in components.
- **Tablet-first** for Paperless Session Mode; **desktop-first** for admin/secretariat.
- **AI is off by default** (`AI_ENABLED=false`); OCR and transcription drivers default to null/stub. Every AI surface must carry a visible AI-generated / verify-against-original treatment.
- **Electronic voting is not automatically legally binding** (`SENTRIA_ELECTRONIC_VOTING_BINDING=false` by default); the UI must say so.
- Records are soft-archived; hard delete requires dual-admin confirmation plus audit.
- Rate limits: login 5/min, portal search 60/min per IP, AI ask 20/min per user.
- ULID primary keys; sequential integer IDs never appear in URLs.

**Known limitations** (`docs/known-limitations.md`). Not certified against RA 10173, e-document rules, or electronic-voting statutes, and must not claim to be. PWA scope covers only the session floor; offline document caching is best-effort and auth cookies may expire. Realtime tallies pause while offline. `/settings` is an index of links, not full CRUD. **`/users` and `/roles` are linked in navigation but have no routes implemented.** No frontend test suite for the offline queue. Full database restore is not exercised in CI.

## Brand Commitments

- **Name:** Sentria. Tagline in Filipino: *Sistemang Paperless para sa Sesyong Pambatas*.
- **Stated design intent, pinned in `prompts/00-shared-constraints.md`:** "Professional government-style UI: authoritative, restrained, document-focused." This is a binding register, recorded here as the user set it.
- **Visual world (confirmed):** **Order of Business** — precision-technical register documented in `DESIGN.md`. Graphite/white surfaces, Space Grotesk throughout, Philippine national blue for action and national red for live/urgent facts only. Replaces the earlier Transparency Board paper/tab direction.
- **Register commitment:** institutional gravitas on administrative and public surfaces, shifting to a dense operations register on the live session floor. Full light and dark parity, because the chamber is dimmer than the office.
- Organization identity is configuration, not a fixed asset: `config/sentria.php` supplies name, short name, and locality, and the UI must render whatever it is given.

## Evidence on Hand

**Real and usable.** A complete demo corpus: 24 documents, 6 ordinances, 8 resolutions, 8 standing committees, and three session scenarios — one past and adjourned, one live and in-session, one upcoming and scheduled (`database/seeders/LegislativeContentSeeder.php`, `CommitteeSeeder.php`). Fourteen seated members with Filipino names on `@sentria.test` addresses (`database/seeders/UserSeeder.php`). A confidential document plus an explicit grant, for ACL demonstration. Eight demo logins, password `password` (README). Complete English and Filipino string catalogs.

**Explicitly absent — must not be fabricated.**

- No real LGU name, seal, coat of arms, or logo. The only image asset is `public/favicon.ico`. Organization values are placeholders: name "Sangguniang Panlalawigan", short name "SP", locality "Province of Demo".
- No real elected officials, addresses, or ordinance/resolution text; seeded titles are faker-generated.
- No real embeddings (demo vectors are random unit vectors) and no production AI provider configured.
- No qualified digital-signature integration; configuration stub only.
- No legal or compliance certification of any kind.

## Product Principles

1. **The record is the product.** Every surface should make provenance, version, state, and authorship legible, because the artifact being produced is an official government record that outlives the software.
2. **The human decides; the system evidences.** Quorum, vote declarations, minutes approval, and publication are human acts. Interfaces present evidence and consequence, never a recommendation dressed as a verdict.
3. **AI output is always visibly separable from the record.** A reader must never have to guess whether they are looking at a machine draft or an approved fact.
4. **The floor cannot afford ambiguity.** During a live session, state, timing, and the single available action must be readable across a bench at a glance, under time pressure, offline, and by a first-time user.
5. **Authority is earned through precision, not decoration.** Restraint is a requirement of the domain, but plainness is not restraint; craft shows in typography, alignment, state clarity, and density discipline.

## Accessibility & Inclusion

WCAG 2.1 AA is the stated target (`docs/accessibility.md`). Required: semantic landmarks, a skip link, visible `:focus-visible` rings from tokens, token-driven contrast, labeled form fields, keyboard navigation, screen-reader labels. Verification is currently manual axe DevTools on `/dashboard`; there is no automated axe gate in CI. Bilingual English/Filipino support is a product requirement, so layouts must tolerate longer Filipino strings without breaking.

# AI-Powered Paperless Legislative Session System

## Revised 5-Part Prompt for Cursor / Claude Code / Gemini

> **How to use:** Complete PART 0 (human setup) first, then run the
> prompts in order. Each part assumes the previous part has already been
> completed and its acceptance criteria have passed. Do not restart the
> project between parts.

------------------------------------------------------------------------

# PART 0 --- Human Setup (Do This Before Running Any Prompt)

These are steps **you** perform, not the coding agent.

1. **Install the frontend skills into the project** so the agent can
   load them. All three have official installers — run these from the
   project root:

   ``` bash
   # Impeccable (design guardrails + audit/polish commands)
   npx impeccable install          # then run /impeccable init inside your AI tool

   # UI UX Pro Max (UI styles, palettes, font pairings, UX rules)
   npm install -g ui-ux-pro-max-cli
   uipro init --ai cursor          # or --ai claude for Claude Code

   # Refero (research-first design methodology, real product references)
   npx skills add https://github.com/referodesign/refero_skill --skill refero-design
   ```

   The installers write into `.cursor/skills/` (Cursor) or
   `.claude/skills/` (Claude Code): `impeccable/`, `ui-ux-pro-max/`,
   and `refero-design/`. Each skill folder must contain its `SKILL.md`.
   Verify the agent can see the skills before starting (ask it to list
   available skills), and commit the skill folders to git so cloud
   agents get them too.

   **Optional but recommended — Refero MCP** for live design research
   against 150,000+ real product screens and user flows (requires a
   Refero Pro subscription; the skill still works without it). For
   Cursor, add to `.cursor/mcp.json`:

   ``` json
   {
     "mcpServers": {
       "refero": {
         "url": "https://api.refero.design/mcp",
         "headers": { "Authorization": "Bearer <token>" }
       }
     }
   }
   ```

   For Claude Code:
   `claude mcp add --transport http refero https://api.refero.design/mcp --header "Authorization: Bearer <token>"`

2. **Prepare the environment:** PHP 8.4, Composer, Node 22 LTS,
   PostgreSQL 17 with the `pgvector` extension available, and Redis.

3. **Create an empty git repository** and commit after every part so
   each stage is reviewable and revertible.

------------------------------------------------------------------------

# PART 1 --- System Architecture, Requirements & Project Foundation

``` text
You are a senior software architect, Laravel developer, React developer, database architect, UI/UX designer, cybersecurity engineer, and AI/RAG engineer.

Build a production-ready AI-powered Paperless Legislative Session and Legislative Management Information System (LMIS) for a Philippine government legislative office such as a Sangguniang Panlalawigan, Sangguniang Panlungsod, or Sangguniang Bayan.

The system will digitize the legislative process from document submission through committee review, agenda preparation, paperless sessions, voting, minutes, approval, archiving, and public publication.

IMPORTANT:
- Do not create a simple demo or mockup.
- Build clean, maintainable, modular production-quality code.
- AI assists government personnel but NEVER replaces official legislative decision-making.
- Use fictional/demo data during development.

TECHNOLOGY (pinned — do not substitute):
Backend: Laravel 12 on PHP 8.4
Frontend: React 19 + TypeScript (strict mode) via Inertia.js v2, with SSR enabled (required later for the public portal)
Build tool: Vite
Styling/components: Tailwind CSS v4 + shadcn/ui
Real-time: Laravel Reverb (WebSockets) + Laravel Echo
Database: PostgreSQL 17
Cache/Queue: Redis (Laravel Horizon for queue monitoring)
Vector Search: PostgreSQL + pgvector
Storage: S3-compatible storage or secure local storage/NAS via Laravel filesystem abstraction
Search: PostgreSQL full-text search initially
AI: Provider-independent AI service layer
Authentication: Laravel session authentication (Fortify) for the Inertia app; Laravel Sanctum tokens only for external API consumers
RBAC: spatie/laravel-permission
Model auditing: owen-it/laravel-auditing plus a custom append-only audit_logs table
Workflow states: spatie/laravel-model-states (explicit state machines, never bare status strings)
Testing: Pest
Code quality: Laravel Pint, Larastan (level 8), ESLint, Prettier

Do not hand-roll RBAC, state machines, or auditing when these packages solve the problem.

PRIMARY KEYS — ULIDs:
Use ULIDs (not UUIDs, not sequential integers) as primary keys for all tables.
- Use Laravel's HasUlids trait on models.
- Migrations: $table->ulid('id')->primary() and $table->foreignUlid() for foreign keys.
- Store as CHAR(26) in PostgreSQL with standard B-tree indexes.
- Never expose sequential integer IDs in URLs or API responses.
- Rationale: ULIDs are time-ordered, so inserts append to the B-tree instead of landing on random pages, which matters for high-write tables (audit_logs, ai_messages, document_embeddings, votes, transcripts). Sorting by primary key equals sorting by creation time.
- Caveat: ULIDs embed a creation timestamp. That is acceptable for legislative records (creation time is public information), but NEVER use ULIDs where creation time is sensitive or where unguessability is the security property — password reset tokens, signed URLs, invitation tokens, and session tokens must use cryptographically random values.

INTERNATIONALIZATION:
Set up i18n from the start (painful to retrofit):
- Backend: Laravel lang files.
- Frontend: all UI strings through a translation layer shared with Inertia.
- Languages: English (default) and Filipino.
- No hard-coded user-facing strings in components.

USER ROLES:
1. System Administrator
2. Secretariat
3. Presiding Officer
4. Board Member
5. Committee Chair
6. Committee Member
7. Legal/Technical Reviewer
8. Public User

Create granular RBAC using spatie/laravel-permission. AI must inherit the authenticated user's permissions.

CORE MODULES:
Dashboard
Users
Roles
Permissions
Sessions
Agenda
Documents
Document Versions
Ordinances
Resolutions
Committees
Committee Referrals
Committee Reports
Attendance
Motions
Voting
Minutes
Transcripts
Notifications
Audit Logs
AI Assistant
AI Conversations
AI Citations
Publications
System Settings
Backup/Recovery

SESSION STATUSES (implement as a spatie/laravel-model-states state machine with guarded transitions):
Draft
Scheduled
Agenda Prepared
Documents Distributed
In Session
Suspended
Resumed
Adjourned
Minutes for Review
Finalized
Archived

Every state transition must be permission-checked and written to the audit log.

CONFIGURABLE LEGISLATIVE WORKFLOW (also a real state machine, not a status string column):
Document Submitted
→ Secretariat Review
→ Registered
→ Committee Referral
→ Committee Review
→ Committee Report
→ Agenda Inclusion
→ Reading/Deliberation
→ Amendments
→ Voting
→ Approved/Rejected
→ Final Document
→ Transmittal
→ Archive
→ Public Publication

Do not hard-code assumptions about Philippine legislative procedure. Make the workflow configurable according to the organization's approved Internal Rules of Procedure. Transitions live in one place (the state machine), never scattered across controllers. Invalid transitions must throw, be tested, and be audit-logged when attempted.

DATABASE:
Create a normalized PostgreSQL database with at least:
users
roles
permissions
sessions
session_attendance
agenda_items
documents
document_versions
document_metadata
committees
committee_members
committee_referrals
committee_reports
ordinances
resolutions
motions
votes
minutes
transcripts
notifications
audit_logs
ai_conversations
ai_messages
ai_citations
document_embeddings
publications
system_settings

Use ULID primary keys as specified above, foreign keys, indexes, timestamps, soft deletes where appropriate, audit fields, and proper constraints.

Create factories and seeders for every model with realistic fictional demo data, including one seeded user per role.

SECURITY:
Implement authentication, authorization, RBAC, CSRF protection, validation, rate limiting, secure file uploads, file validation, malware-scan architecture, audit logs, secure API authentication, secure password handling, session timeout, and permission checks.

Never expose AI/API keys in frontend code.

UI/UX:
Before writing any frontend code, read and follow the design skills installed in this project (.cursor/skills/ or .claude/skills/), in this order:
1. "refero-design" — research first: ground the visual direction in real product references (government portals, document-heavy tools, dashboards) before implementing anything. If Refero MCP is available, use it for style/screen/flow research; record the chosen references and stick to them.
2. "impeccable" and "ui-ux-pro-max" — apply during implementation for design-system discipline, typography, color, spacing, and anti-pattern avoidance.

Apply them to every page, component, and layout you create.

Design requirements:
- Define a design system first: color tokens, typography scale, spacing scale, elevation, and component variants. Reuse it everywhere; no ad-hoc styles.
- Professional government-style interface: authoritative, restrained, document-focused. No decorative noise.
- WCAG 2.1 AA accessibility (government requirement): full keyboard navigation, visible focus states, sufficient contrast, screen-reader labels, semantic HTML.
- Tablet-first for the Paperless Session Mode surfaces; desktop-first for admin/secretariat surfaces.
- Large touch targets and a distraction-free document-reading layout during sessions.
- Clear navigation and status indicators driven by the state machines.
- AI-generated content must be visually distinct from official records (dedicated badge + container style defined in the design system).

CONTINUOUS INTEGRATION:
Create a GitHub Actions workflow that runs on every push: Pint (check mode), Larastan, ESLint, Prettier (check mode), Pest with coverage, and the frontend build. The pipeline must pass before any part is considered complete.

FIRST TASK:
Do not immediately build everything.

First:
1. Analyze requirements.
2. Propose complete system architecture.
3. Propose project/folder structure.
4. Create database ERD.
5. Create database schema.
6. Define roles and permissions.
7. Define API architecture (Inertia pages + the small set of JSON endpoints needed for real-time features).
8. Define frontend architecture.
9. Define security architecture.
10. Define high-level AI architecture.

Then initialize the project and create:
- migrations
- models
- factories and seeders
- authentication
- roles/permissions
- state machine foundations (session status + legislative workflow)
- base Inertia + React layout
- design system foundations (tokens, base components)
- navigation
- dashboard framework
- CI pipeline

Do not implement AI yet.

Do not skip tests.

At the end, report:
- What was created
- Files created/modified
- Database tables
- How to run the project
- Remaining tasks

Continue using this same project in future prompts. Do not recreate it from scratch.
```

**Acceptance criteria before proceeding to Part 2:**

- [ ] `composer install`, `npm install`, migrations, and seeders run
      clean on a fresh database.
- [ ] All primary keys are ULIDs (spot-check migrations and a few
      created records).
- [ ] Login works for one seeded user of every role; each sees
      role-appropriate navigation.
- [ ] Invalid state transitions throw and are covered by tests.
- [ ] CI pipeline passes (Pint, Larastan, ESLint, Prettier, Pest,
      frontend build).
- [ ] UI uses the design system tokens; no hard-coded colors/spacing in
      components.

------------------------------------------------------------------------

# PART 2 --- Legislative Management & Paperless Session

``` text
CONTINUE BUILDING THE SAME PROJECT. Do NOT restart it.

Before writing any frontend code in this part, re-read and apply the "refero-design", "impeccable", and "ui-ux-pro-max" skills — research references for session/agenda/reading interfaces first (refero-design), then implement with the other two. The Paperless Session Mode is the most important UI in the system: tablet-first, large touch targets, distraction-free reading layout.

Implement the complete Legislative Management and Paperless Session modules.

DOCUMENT MANAGEMENT:
Support PDF, DOCX, XLSX, images, and scanned documents.

Metadata:
Document ID
Document Number
Document Type
Title
Description
Author
Originating Office
Date Filed
Date Received
Committee
Session
Status
Confidentiality Level
Version
Created By
Updated By
Created At
Updated At

Document types:
Ordinance
Resolution
Committee Report
Communication
Memorandum
Agenda
Minutes
Endorsement
Proposal
Attachment
Supporting Document

Implement upload, view, download based on permission, search, filtering, metadata editing, versioning, archive, soft delete, restore, and audit logging.

DOCUMENT VERSIONING:
Every modification creates a new version.
Never overwrite an official version.

Example:
Ordinance 2026-015
Version 1 — Initial Draft
Version 2 — Committee Amendments
Version 3 — Second Reading
Version 4 — Final

Implement comparison highlighting added content, removed content, changed text, amounts, dates, and sections.

SESSION MANAGEMENT:
Support:
Regular Session
Special Session
Committee Hearing
Public Hearing

Each session:
Session Number
Session Type
Date
Start Time
End Time
Venue
Presiding Officer
Secretariat
Status
Quorum
Agenda
Attendance
Motions
Votes
Minutes
Attachments

All session status changes go through the state machine from Part 1. Every transition is permission-checked and audit-logged.

REAL-TIME (Laravel Reverb + Echo — do not use polling):
Broadcast events for:
- Session state changes (start, pause, resume, next agenda item, adjourn)
- Attendance and quorum changes
- Motion recorded
- Voting opened / vote cast (aggregate counts) / voting closed
- Agenda item changes

All connected session screens (Board Member, Secretariat, Presiding Officer, Session Dashboard) must update live without refresh. Broadcast channels must be authorized per user permission.

ELECTRONIC AGENDA:
Create a tablet-friendly agenda.

Example:
SESSION NO. 35
1. Call to Order
2. Roll Call
3. Approval of Previous Minutes
4. Communications
5. Committee Reports
6. Proposed Ordinances
7. Proposed Resolutions
8. Unfinished Business
9. New Business
10. Other Matters
11. Adjournment

Each agenda item links to its documents.

Users can:
- Open documents
- Search documents
- Bookmark
- Add private notes
- View related documents
- View version history
- View AI summary placeholder

COMMITTEE MANAGEMENT:
Implement committees, members, chairs, referrals, hearings, reports, and recommendations.

Track:
Date referred
Referred by
Committee
Status
Date reviewed
Committee report
Recommendation

ORDINANCES:
Implement ordinance management:
Ordinance Number
Year
Title
Description
Author
Co-authors
Committee
Status
Date Filed
Date Approved
Date Transmitted
Final Document

RESOLUTIONS:
Implement equivalent resolution management.

ATTENDANCE:
Statuses:
Present
Absent
Excused
Late
Official Business

Record:
User
Session
Status
Time In
Time Out
Remarks

MOTIONS:
Record:
Motion
Mover
Seconder
Agenda Item
Timestamp
Result
Related Vote
Remarks

ELECTRONIC VOTING:
Options:
YES
NO
ABSTAIN

Record:
User
Session
Agenda Item
Vote
Timestamp
Voting Round

Generate vote results.

VOTE INTEGRITY (non-negotiable for a government system):
- The votes table is append-only: no UPDATE or DELETE at the application layer, enforced at the database level with triggers (or revoked privileges). A correction is a new record in a new voting round, never an edit.
- Vote submission must be idempotent: a unique constraint on (session, agenda item, voting round, user). Double-submission from a flaky tablet connection must not create duplicates or errors visible to the user.
- Every vote cast is audit-logged.

IMPORTANT:
Electronic voting must not automatically be considered legally binding. Make it configurable according to the organization's rules.

PAPERLESS SESSION MODE:
Create a dedicated session interface.

Board Member:
Current Session
Current Agenda Item
Open Document
AI Summary
Related Documents
Document History
Attachments
My Notes

Secretariat:
Start Session
Pause
Resume
Next Agenda Item
Record Motion
Start Voting
End Voting
Adjourn

Presiding Officer:
Current Agenda Item
Session Status
Attendance
Quorum
Motions
Voting
Next Item

SESSION DASHBOARD:
Current Session
Current Agenda Item
Members Present
Quorum Status
Pending Motions
Voting Status
Elapsed Session Time

All of the above driven live by Reverb broadcasts.

MINUTES:
Allow Secretariat to create, edit, review, finalize, and archive minutes.
Prepare for future AI-generated draft minutes, but do not implement AI yet.

AUDIT LOG:
Log document upload/view/download/modification/deletion, session creation, agenda modification, attendance, motions, votes, approvals, publication, and permission changes.

AUDIT LOG INTEGRITY:
- audit_logs is append-only, enforced at the database level (triggers or revoked privileges).
- Hash-chain the rows: each audit record stores a hash of (its own content + the previous record's hash), so any tampering with historical records is detectable. Provide an artisan command that verifies the chain.

TESTING:
Create unit, feature, API, authorization, document upload, voting (including idempotency and append-only enforcement), broadcast authorization, and session tests.

At the end report:
1. Modules completed
2. Migrations
3. API endpoints / Inertia pages
4. Frontend pages
5. Permission matrix
6. Tests
7. Setup instructions
8. Remaining issues

Continue using the existing project. Do not rebuild from scratch.
```

**Acceptance criteria before proceeding to Part 3:**

- [ ] Full walkthrough with seeded users: create session → prepare
      agenda → start session → record motion → open voting → cast votes
      from two different role accounts → close voting → adjourn.
- [ ] Two browser windows on the same session update live (no manual
      refresh) for agenda changes, motions, and vote counts.
- [ ] Attempting to UPDATE or DELETE a vote or audit log row at the
      database level fails.
- [ ] Submitting the same vote twice results in exactly one record.
- [ ] Audit chain verification command passes; manually corrupting a
      row makes it fail.
- [ ] A Board Member account cannot reach Secretariat session controls
      (URL and API level, not just hidden buttons).
- [ ] CI pipeline still passes.

------------------------------------------------------------------------

# PART 3 --- AI, RAG, OCR & Legislative Intelligence

``` text
CONTINUE BUILDING THE SAME PROJECT.

Implement the AI layer for the Paperless Legislative System.

AI PRINCIPLE:
AI is an assistant.

AI must NOT:
- Vote
- Approve legislation
- Reject legislation
- Modify official records automatically
- Make legislative decisions
- Bypass permissions
- Provide unauthorized legal conclusions

Humans remain responsible for official records and decisions.

AI SERVICE ARCHITECTURE:
Create provider-independent services:
DocumentSummarizationService
RAGService
LegislativeSearchService
DocumentComparisonService
RelatedDocumentService
ConsistencyCheckService
MinutesGenerationService
TranscriptionService

AI provider must be configurable.
Never hard-code API keys.

DOCUMENT PROCESSING (each step is a queued job on Redis, monitored via Horizon, with Processing/Completed/Failed status visible per document):
Document Upload
→ File Validation
→ Malware Scan Architecture
→ OCR if required
→ Text Extraction
→ Metadata Extraction
→ Text Chunking
→ Embedding Generation
→ Vector Storage
→ Search Index

VECTOR SEARCH CONFIGURATION (PostgreSQL + pgvector):
- Use an HNSW index (not IVFFlat) with cosine distance.
- Embedding dimension must be configurable per provider (do not hard-code 1536).
- Chunking strategy: legislative documents are section-structured, so chunk boundaries must respect section headings where detectable. Target roughly 500–800 tokens per chunk with 10–15% overlap. Store chunk metadata: document, version, page, section number/heading, character offsets — this is what makes precise citations possible.

Use PostgreSQL + pgvector unless there is a strong technical reason not to.

OCR:
Detect whether PDFs contain selectable text.
If not:
Run OCR
→ Extract text
→ Store OCR text
→ Preserve original PDF

Never replace the original document with OCR output.

AI DOCUMENT SUMMARY:
Generate:
- Executive summary
- Purpose
- Key provisions
- Important dates
- Financial information if identifiable
- Affected offices/entities
- Related documents
- Potential issues

Display:
"AI GENERATED — VERIFY AGAINST ORIGINAL DOCUMENT"

Always provide source access.

ASK LEGISLATIVE AI:
Create an AI chat interface called "Ask Legislative AI".

Example:
"What previous ordinances are related to this proposed ordinance?"

Return potentially related records with:
Document
Reason for relevance
Page/section
Open Document

RAG:
User Question
→ Permission Check
→ Query Understanding
→ Semantic Search
→ Metadata Filtering
→ Retrieve Authorized Documents
→ Relevant Chunks
→ AI Generation
→ Source Citations
→ Response

CRITICAL:
The AI must never retrieve documents the user is not authorized to access.
Apply authorization BEFORE retrieval: the permission filter is part of the vector search query itself (filter on document-level access at the SQL level), not a post-filter on retrieved chunks.

SOURCE CITATIONS:
Every factual answer based on legislative records must cite sources, using the chunk metadata (document, version, page, section).

Example:
Source:
Ordinance No. 2022-015
Page 4
Section 6
[Open Document]

Never fabricate citations.

If evidence cannot be found:
"I could not find sufficient information in the available legislative records."

SEMANTIC SEARCH:
Support natural-language searches such as:
"Find previous legislation about provincial hospital funding."
"Show ordinances related to solid waste management."
"Find resolutions concerning disaster preparedness."

Filters:
Year
Document Type
Committee
Author
Status
Date

RELATED DOCUMENTS:
Identify potentially related ordinances, resolutions, committee reports, previous versions, communications, and supporting documents.

Display:
High relevance
Medium relevance
Low relevance

Clearly label results as AI suggestions.

DOCUMENT COMPARISON:
Allow Document A vs Document B.
Identify:
Changed sections
Added provisions
Removed provisions
Changed amounts
Changed dates
Changed penalties
Changed definitions

CONSISTENCY CHECKER:
Detect possible:
Missing sections
Broken references
Duplicate provisions
Numbering errors
Inconsistent terminology
Inconsistent dates
Inconsistent amounts
Undefined terms

Example:
"Section 8 refers to Section 12, but Section 12 was not detected."

Label:
"AI-assisted review. Human verification required."

Do not call this legal advice.

AI PERMISSION SECURITY:
If a user cannot access Document X, the user must not be able to ask AI about Document X.
Implement document-level permission filtering before RAG retrieval.
Test this explicitly.

AI AUDIT LOGGING:
Record:
User
Question
Timestamp
Retrieved documents
Sources
AI model/provider
Response
Token/usage information where available

AI UI:
Add:
AI Summary
Ask Legislative AI
Related Documents
Compare Documents
AI Review

Use the AI-content visual treatment defined in the Part 1 design system: all AI content is visually distinct from official records.

TEST:
RAG retrieval
Permission filtering (a user provably cannot get AI answers about a document they cannot open — test at the retrieval layer, not just the UI)
Citation generation
Hallucination handling
Unauthorized document protection
Document comparison
Summary generation
OCR pipeline
Queue job failure and retry behavior

At the end provide:
AI architecture
AI services
Database changes
Vector configuration
RAG pipeline
AI integration
Prompt templates
Security implementation
Tests
Configuration instructions

Continue using the existing project. Do not rebuild from scratch.
```

**Acceptance criteria before proceeding to Part 4:**

- [ ] Upload a scanned PDF and a text PDF; both end up chunked,
      embedded, and searchable; pipeline statuses visible in Horizon.
- [ ] Ask Legislative AI answers a question with citations that resolve
      to the correct page/section when opened.
- [ ] Permission isolation test: user without access to Document X asks
      about its contents — AI response contains nothing from it, and the
      audit log confirms it was never retrieved.
- [ ] Question with no supporting records returns the "could not find
      sufficient information" response, not a fabricated answer.
- [ ] AI content is visually distinct on every surface where it appears.
- [ ] CI pipeline still passes.

------------------------------------------------------------------------

# PART 4 --- AI Session Assistant, Transcription & Minutes

``` text
CONTINUE BUILDING THE SAME PROJECT.

Before writing any frontend code in this part, re-read and apply the "refero-design", "impeccable", and "ui-ux-pro-max" skills — research references for live-transcript and assistant-panel interfaces first (refero-design), then implement with the other two. Session-floor surfaces are tablet-first.

Implement the AI-powered Session Assistant.

AI must never become the official decision-maker.

SESSION AI ASSISTANT:
During an active session provide:
Current Agenda Item
Document Summary
Related Legislation
Previous Similar Legislation
Document History
AI Search
Transcript Search
Amendment Comparison

SPEECH-TO-TEXT:
Implement optional session transcription.

Workflow:
Session Audio
→ Speech-to-Text
→ Timestamped Transcript
→ Agenda Association
→ Searchable Transcript

Transcript fields:
Timestamp
Speaker if identifiable
Text
Agenda Item
Session

Do not assume speaker identification is always accurate.
Allow manual correction.

LIVE TRANSCRIPTION:
If technically practical, provide:
LIVE TRANSCRIPT

Stream transcript segments to authorized session screens over Laravel Reverb (the same broadcasting infrastructure from Part 2). Channel authorization applies: only users permitted to view the session transcript may subscribe.

Allow authorized users to search live transcript.

SESSION SEARCH:
Example:
"What did we discuss about the hospital budget?"

Return:
Relevant timestamp
Speaker
Text
Agenda Item
[Jump to Transcript]

AI DRAFT MINUTES:
After session completion use:
Session Metadata
Attendance
Agenda
Motions
Voting Results
Transcript
Secretariat Notes

Generate:
DRAFT MINUTES

Clearly display:
"AI-GENERATED DRAFT — REQUIRES SECRETARIAT REVIEW"

MINUTES WORKFLOW (implement as a state machine, consistent with Part 1):
Session Completed
→ AI Draft
→ Secretariat Review
→ Edit
→ Review
→ Approval
→ Final Minutes
→ Archive

AI must never automatically finalize minutes.

MOTION DETECTION:
AI may suggest:
Motions
Seconders
Agenda items
Results

Label extracted information:
"AI Suggested"

Secretariat must confirm.

VOTING:
Official electronic voting records are authoritative.
AI must not determine vote results from audio if official voting data exists.

SESSION SUMMARY:
Generate a draft containing:
Major agenda items
Motions
Decisions
Voting results
Legislative documents discussed
Pending matters
Follow-up actions

Mark as AI-generated.

ACTION ITEMS:
AI may identify potential follow-up actions, but Secretariat must confirm them before they become official tasks.

LEGISLATIVE HISTORY:
Create a visual timeline:
Document
→ Committee
→ Sessions
→ Amendments
→ Votes
→ Approval
→ Final Version

SESSION DASHBOARD:
LIVE SESSION
Session No.
Current Agenda Item
Members Present
Quorum
Current Motion
Voting Status
AI Assistant
Transcript
Documents

All live elements driven by Reverb broadcasts, not polling.

SECURITY:
Apply RBAC, permission checks, audit logging, secure storage, retention policies, and encryption where appropriate.

Do not automatically publish recordings or transcripts.

AI must not:
- Decide whether quorum exists
- Decide whether a vote passed
- Approve minutes
- Approve ordinances
- Create official legislative decisions
- Change official voting records

Use official system records wherever available.

TEST:
Transcription
Transcript search
AI draft minutes
Permission controls
Session AI
Official vote integration (AI draft minutes must reflect the official vote records, byte-for-byte counts)
Broadcast channel authorization for live transcripts
Audit logging

Continue using the existing project. Do not rebuild from scratch.
```

**Acceptance criteria before proceeding to Part 5:**

- [ ] Run a full simulated session with audio: transcript is
      timestamped, associated to agenda items, searchable, and manually
      correctable.
- [ ] Live transcript appears on an authorized user's screen without
      refresh; an unauthorized user cannot subscribe to the channel.
- [ ] AI draft minutes generate after adjournment, carry the required
      AI-draft banner, and cannot be finalized by AI or by a
      non-Secretariat role.
- [ ] Where official voting records exist, draft minutes match them
      exactly.
- [ ] CI pipeline still passes.

------------------------------------------------------------------------

# PART 5 --- Security, Public Portal, Deployment & Production

``` text
CONTINUE BUILDING THE SAME PROJECT.

Before writing any frontend code in this part, re-read and apply the "refero-design", "impeccable", and "ui-ux-pro-max" skills — research references for public government portals and legislative search sites first (refero-design), then implement with the other two. The public portal is the public face of the institution: it must be fast, accessible (WCAG 2.1 AA), and readable on any device.

Prepare the system for production deployment as a secure government legislative information system.

PUBLIC LEGISLATIVE PORTAL:
Create a separate public-facing portal.

Use the Inertia SSR setup from Part 1 so public pages are server-rendered: indexable by search engines, with correct titles, meta descriptions, and Open Graph tags per published document.

Public users may search:
- Published ordinances
- Published resolutions
- Approved legislation
- Public minutes
- Public agendas
- Session schedules
- Legislative history

Public users must NOT see:
- Draft confidential documents
- Internal committee documents
- Private notes
- Internal AI conversations
- Restricted documents
- Internal audit logs
- Sensitive personal information

Only documents explicitly marked PUBLIC may be displayed.

PUBLIC SEARCH:
Filters:
Document Type
Year
Author
Committee
Status
Keyword
Date

Support keyword, full-text, and semantic search where appropriate.

PUBLICATION WORKFLOW (state machine, consistent with Parts 1–2):
Internal Document
→ Secretariat Review
→ Publication Review
→ Mark Public
→ Publish
→ Public Portal

Never expose internal documents simply because they exist in the database.

SECURITY AUDIT:
Review:
Authentication
Authorization
RBAC
API security
File uploads
SQL injection
XSS
CSRF
IDOR
Session security
Rate limiting
Password security
Secrets management
AI prompt injection
RAG access control
Document permissions
Audit logs

Fix identified vulnerabilities.

AI SECURITY:
Protect against:
Prompt injection
Indirect prompt injection from documents
Data leakage
Unauthorized retrieval
Sensitive information exposure
Malicious uploaded files
AI hallucination

Uploaded documents must never override system instructions.

AUDIT LOGGING:
Record:
User
Action
Object
Timestamp
IP
Device
Result

Examples:
Login
Logout
Document View
Document Upload
Document Download
Document Edit
Document Delete
Document Approval
Document Publication
Vote
AI Query
AI Retrieval
Permission Change

Protect audit logs from ordinary users.

Verify the Part 2 audit-log integrity guarantees still hold: append-only enforcement at the database level and a passing hash-chain verification.

DATA PRIVACY:
Design with Philippine government privacy requirements in mind, including the Data Privacy Act of 2012 (RA 10173), applicable National Privacy Commission guidance, government ICT/security policies, records-management requirements, Internal Rules of Procedure, and electronic-document requirements.

Do not claim automatic legal compliance.
Create a Data Privacy/Security Configuration Checklist for review by the organization's ICT office, legal office, records officer, and Data Protection Officer.

ELECTRONIC DOCUMENTS AND SIGNATURES:
Provide architecture capable of supporting:
Electronic approval
Digital signatures
Document integrity verification
Signature metadata

Do not assume an uploaded image of a signature is equivalent to a legally valid digital signature.
Make signature functionality configurable.

BACKUP:
Implement:
Database backup
Document backup
Configuration backup
Versioned backup
Offsite backup

Use the 3-2-1 principle where practical.

Create Backup Dashboard showing:
Last Backup
Backup Status
Backup Size
Next Backup
Last Restore Test

DISASTER RECOVERY:
Document procedures for:
Database failure
Server failure
Storage failure
Network failure
Cybersecurity incident
Accidental deletion
Ransomware
AI provider outage

Define configurable RPO and RTO.

LOCAL NETWORK / OFFLINE SESSION:
Support operation within a government LAN.

Architecture should allow:
LAN
→ Application Server
→ Database
→ Document Storage

Critical session documents should remain accessible if external internet temporarily fails.

TABLET OFFLINE RESILIENCE (PWA):
Make the Paperless Session Mode a Progressive Web App:
- Service worker caches the current session's agenda and its linked documents on the tablet when the session starts.
- A Wi-Fi blip mid-session must not blank a board member's screen: cached agenda and documents remain readable, with a clear offline indicator.
- Actions requiring the server (votes, motions) queue or clearly indicate unavailability while offline — never silently drop a vote. Votes are safe to retry thanks to the Part 2 idempotency constraint.

Use external internet only where required, such as external AI APIs or remote notifications.

PERFORMANCE:
Optimize:
Database queries
Indexes
Document loading
PDF viewing
AI retrieval
Vector search (verify HNSW index usage with EXPLAIN)
API response
Frontend rendering

Use:
Caching
Queues
Background jobs
Pagination
Lazy loading

QUEUE SYSTEM:
Use background jobs for:
OCR
AI summarization
Embedding generation
Document indexing
Transcription
Email notifications
Large file processing

Provide:
Processing
Completed
Failed

statuses, monitored through Laravel Horizon.

SYSTEM MONITORING:
Create admin monitoring dashboard showing:
System status
Database status
Storage
Queue status
AI service status
Failed jobs
Recent errors
Login activity
Backup status

DOCUMENT RETENTION:
Create configurable retention policies.
Do not automatically delete official legislative records.
Allow administrators to configure retention according to approved records-management policies.

ADMINISTRATION:
Create settings for:
Users
Roles
Permissions
Committees
Document Types
Session Types
Workflow
AI Configuration
Storage
Notifications
Backup
System Settings
Public Portal

DEPLOYMENT:
Create deployment documentation covering:
Development
Testing
Production

Document:
Server requirements
PHP 8.4
Node 22 LTS
PostgreSQL 17 (+ pgvector)
Redis
Reverb (WebSocket server) deployment and reverse-proxy configuration
Storage
SSL/TLS
Environment variables
Queue workers (Horizon)
Scheduler
Database migration
Backup
Restore

PRODUCTION:
Ensure:
Debug disabled
Secure cookies
HTTPS
Security headers
Rate limiting
Proper logging
Secrets outside source code
Protected database credentials
Protected AI keys
Protected file storage

TESTING:
Create:
Unit tests
Feature tests
API tests
Authorization tests
Security tests
Document tests
Session tests
Voting tests
AI tests
RAG permission tests
Public portal tests (including that unpublished documents 404 for anonymous users)
Backup/restore tests
Offline/PWA behavior tests

DOCUMENTATION:
Create manuals for:
Administrator
Secretariat
Presiding Officer
Board Member
Committee Chair
Committee Member
Public User

Explain:
Login
Dashboard
Sessions
Documents
Agenda
Paperless Session
Voting
AI Assistant
Search
Minutes
Public Portal

FINAL REVIEW:
Verify:
- No module bypasses authorization
- AI cannot access unauthorized records
- Official records cannot be silently modified
- Voting records are protected (append-only + idempotency verified)
- Audit log hash chain verifies
- Document versions are preserved
- Public users cannot access internal data
- Audit logs work
- Backups work
- AI citations work
- OCR works
- Transcription works
- Paperless session works, including offline resilience
- Tablet UI works
- Database relationships are correct
- Tests pass

FINAL DELIVERABLE:
1. Complete architecture
2. Database ERD
3. API documentation
4. Security architecture
5. AI architecture
6. Deployment guide
7. Backup/restore guide
8. Administrator manual
9. User manual
10. Testing report
11. Known limitations
12. Recommended future improvements

MOST IMPORTANT:
This is a government legislative information system.

AI is an ASSISTANT.

Humans remain responsible for:
- Legislative decisions
- Official minutes
- Voting
- Approvals
- Legal interpretation
- Official records

Never allow AI to silently modify or decide official government records.

Continue using the existing project.
Do not rebuild the application from scratch.

Before declaring the system ready for actual government use, identify all items requiring validation by the organization's ICT office, legal office, records officer, and Data Protection Officer.
```

**Acceptance criteria for the final system:**

- [ ] Anonymous user can find and read a published ordinance; the page
      is server-rendered (view source shows content) with correct meta
      tags.
- [ ] Anonymous user gets 404 (not 403) for any unpublished document —
      internal document IDs must not be confirmable.
- [ ] Tablet in session mode survives a Wi-Fi disconnect: agenda and
      open document stay readable, offline state is indicated, and a
      vote retried after reconnect records exactly once.
- [ ] Backup and restore tested end-to-end on a copy of the database
      and document store.
- [ ] Full test suite and CI pipeline pass.
- [ ] The validation checklist for ICT office, legal office, records
      officer, and Data Protection Officer is produced.

------------------------------------------------------------------------

## Suggested Project Prompt File Usage

Save this file as:

`PAPERLESS_LEGISLATIVE_AI_PROMPTS.md`

Recommended execution:

1.  `PART 0` → Install skills, prepare environment (human steps)
2.  `PART 1` → Build foundation
3.  Verify Part 1 acceptance criteria
4.  `PART 2` → Build legislative/session modules
5.  Verify Part 2 acceptance criteria
6.  `PART 3` → Build AI/RAG
7.  Verify Part 3 acceptance criteria (especially permission isolation)
8.  `PART 4` → Build AI session assistant
9.  Verify Part 4 acceptance criteria
10. `PART 5` → Harden and prepare for production
11. Verify final acceptance criteria

**Do not ask the coding agent to implement all five parts in one shot.**
**Do not proceed to the next part until the current part's acceptance
criteria pass.**

# Sentria — System Architecture

Production-quality AI-assisted Paperless Legislative Session / LMIS for a single Philippine LGU legislative body. Not a multi-tenant SaaS.

## Goals

- Digitize document submission → committee → agenda → paperless session → voting → minutes → archive → public publication.
- AI assists personnel; humans remain responsible for every official decision and record.
- Single organization, ULID primary keys, append-only `votes` and `audit_logs`.

## Stack

| Layer | Choice |
| --- | --- |
| Backend | Laravel 13 / PHP 8.4 |
| Frontend | React 19 + TypeScript (strict) via Inertia.js v2, SSR |
| Build | Vite |
| Styling | Tailwind CSS v4 + shadcn/ui |
| Real-time | Laravel Reverb + Echo on `session.{id}` and `session-transcript.{id}` |
| Database | PostgreSQL 17 + pgvector |
| Cache / Queue | Redis + Horizon |
| Auth | Fortify (session) for Inertia; Sanctum for external API only |
| RBAC | `spatie/laravel-permission` |
| Workflow | `spatie/laravel-model-states` |
| Auditing | `owen-it/laravel-auditing` + hash-chained `audit_logs` |

Local development on this host may use PostgreSQL 16 + pgvector when Docker is unavailable. CI and `docker-compose.yml` pin PostgreSQL 17.

## Folder structure (domain-oriented)

```
app/
  Enums/                 Canonical role, confidentiality, document type, votes
  Models/                Eloquent models (ULID PKs)
  States/                Session + document + minutes + publication state machines
  Services/              Named AI / OCR / malware interfaces (swappable)
  Http/Controllers/      Thin Inertia controllers
  Policies/              Model authorization
  Notifications/
database/
  migrations/            Canonical tables
  factories/             One factory per model
  seeders/               Roles, permissions, demo LGU data
resources/js/            Inertia React + TypeScript
docs/                    Architecture, ERD, process flows
```

## Security outline

- Session auth (Fortify) with CSRF, idle timeout, rate-limited login.
- RBAC matrix for eight canonical roles; AI inherits the caller’s permissions.
- Document ACL = role + confidentiality + `document_grants`; private notes are owner-only.
- `votes` / `audit_logs` append-only at app and DB (triggers).
- Vote idempotency: unique `(session_id, agenda_item_id, voting_round, user_id)`.
- Public portal: unpublished docs return **404**, not 403.
- Uploaded documents never override system prompts (injection defenses).

See [security-architecture.md](security-architecture.md).

## AI outline

Named services (providers swappable; keys never on the frontend):

- `DocumentSummarizationService`, `RAGService`, `LegislativeSearchService`
- `DocumentComparisonService`, `RelatedDocumentService`, `ConsistencyCheckService`
- `MinutesGenerationService`, `TranscriptionService`
- Malware / OCR / STT drivers via config (`null` in development)

RAG authorization filters run **inside** the vector/SQL query using denormalized `document_embeddings.confidentiality` / `is_public`. AI is off by default (`AI_ENABLED=false`). AI may draft minutes and answer questions; it may not vote, finalize minutes, declare quorum, or publish.

See [ai-architecture.md](ai-architecture.md).

## Data model

See [erd.mmd](erd.mmd). All domain PKs are ULIDs (`CHAR(26)`). HTTP sessions use `http_sessions` so the canonical `sessions` table holds legislative sessions.

## Process flows

See [business-process.mmd](business-process.mmd) for a mermaid.live overview, and [process-flow.md](process-flow.md) for the as-implemented lifecycle, session floor ritual, document IRP, committee, minutes, publication, and vote-cast diagrams.

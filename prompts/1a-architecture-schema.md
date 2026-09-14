# 1a — Architecture, Schema, Models, Seed Data

## Prerequisite

- [01-human-setup.md](01-human-setup.md) must-pass complete
- Load [00-shared-constraints.md](00-shared-constraints.md)

## In scope

- Requirements analysis and system architecture proposal
- Project/folder structure; initialize Laravel 13 + Inertia/React/Vite skeleton if not present
- Database ERD and migrations for the canonical tables
- Models with `HasUlids`, relationships, factories, seeders (fictional demo data; one user per role)
- `docker-compose.yml` for PostgreSQL 17 + pgvector + Redis if missing
- High-level notes only for API / frontend / security / AI architecture (detail comes in later slices)

## Out of scope

- Full Fortify login UI wiring and permission matrices (slice `1b`)
- State machine transition classes beyond schema/status foundations if needed as stubs (full machines in `1b`)
- Design system tokens, navigation, dashboard UI (slice `1c`)
- Documents/sessions business logic, Reverb, AI, public portal

## Implementation prompt

```text
You are a senior software architect and Laravel developer.

CONTINUE OR INITIALIZE this project per 00-shared-constraints.md. Do not create a demo toy app.

FIRST:
1. Analyze requirements for a Philippine LGU paperless legislative / LMIS system.
2. Propose system architecture, folder structure, ERD, security outline, and high-level AI outline.
3. Initialize Laravel 13 (verify newest stable) with Inertia.js v2 + React 19 + TypeScript + Vite + Tailwind CSS v4 if the app does not already exist.
4. Add docker-compose for PostgreSQL 17 + pgvector + Redis if missing.

THEN create:
- Migrations for all canonical tables in 00-shared-constraints.md (ULID PKs, FKs, indexes, timestamps, soft deletes where appropriate, proper constraints).
- Include document_grants for explicit ACL.
- Models with HasUlids and relationships.
- Factories and seeders for every model with realistic fictional data, including one seeded user per canonical role.
- Package installs needed for later slices may be declared in composer.json now (spatie/laravel-permission, spatie/laravel-model-states, owen-it/laravel-auditing, etc.) even if wiring is incomplete.

Do not implement AI pipelines.
Do not skip factories/seeders.
Do not expose sequential integer IDs.

At the end report architecture summary, tables, how to migrate/seed, and remaining tasks for 1b/1c.
```

## Must-pass

- [ ] `composer install`, `npm install`, migrations, and seeders run clean on a fresh database
- [ ] All primary keys are ULIDs (spot-check migrations and created records)
- [ ] Canonical tables exist (including `document_grants`, `private_notes`, `bookmarks`, `audit_logs`, `votes`, `document_embeddings`)
- [ ] One seeded user record exists per canonical role (auth login may wait for `1b`)
- [ ] `docker-compose.yml` (or documented equivalent) provides PostgreSQL 17 + pgvector + Redis

## Should-pass

- [ ] Written ERD artifact (Mermaid or image) checked into the repo
- [ ] Larastan/Pint baseline config present even if CI lands in `1c`

## Report format

What was created; files; tables; how to run migrate/seed; debt for `1b`.

## Next

[1b-auth-rbac-states.md](1b-auth-rbac-states.md)

# Sentria

AI-assisted Paperless Legislative Session and Legislative Management Information System (LMIS) for a single Philippine LGU legislative body.

See [docs/FINAL-DELIVERABLES.md](docs/FINAL-DELIVERABLES.md), [docs/architecture.md](docs/architecture.md), [docs/security-architecture.md](docs/security-architecture.md), [docs/validation-checklist-ict-legal-records-dpo.md](docs/validation-checklist-ict-legal-records-dpo.md), and [prompts/00-shared-constraints.md](prompts/00-shared-constraints.md).

## Stack

Laravel 13 · PHP 8.4 · Inertia v2 · React 19 · TypeScript · Vite · Tailwind CSS v4 · PostgreSQL 17 + pgvector · Redis · Fortify · Spatie Permission / Model States

## Local setup

```bash
# Preferred: Docker services (PostgreSQL 17 + pgvector, Redis)
docker compose up -d

cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan migrate --seed
npm run build
composer run dev
```

Without Docker on this host, PostgreSQL 16 + pgvector and Redis from apt are acceptable for local development. CI and `docker-compose.yml` remain pinned to PostgreSQL 17.

### Email (Mailtrap)

Session schedule notices send mail through Mailtrap Email Testing (API). In `.env` set:

```
MAIL_MAILER=mailtrap-sdk
MAILTRAP_HOST=sandbox.api.mailtrap.io
MAILTRAP_API_KEY=
MAILTRAP_INBOX_ID=
MAIL_FROM_ADDRESS="no-reply@sentria.test"
```

Create a sandbox inbox, then paste its API token and inbox ID. Messages appear in the Mailtrap inbox rather than at `@sentria.test` addresses. Restart `composer run dev` after changing mail config so the queue worker picks up the mailer. Tests use the `array` mailer and do not call Mailtrap.

### Demo accounts

Password for all: `password`

| Role | Email |
| --- | --- |
| System Administrator | admin@sentria.test |
| Secretariat | secretariat@sentria.test |
| Presiding Officer | presiding@sentria.test |
| Board Member | member@sentria.test |
| Committee Chair | chair@sentria.test |
| Committee Member | committee@sentria.test |
| Legal/Technical Reviewer | legal@sentria.test |
| Public User | public@sentria.test |

## Quality / CI

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse --memory-limit=2G
npm run lint
npm run format:check
npm run types
php artisan test
npm run build
```

GitHub Actions runs the same gates on every push.

## Accessibility

Shell targets WCAG 2.1 AA basics (landmarks, visible focus, skip link, contrast via tokens). Optional axe smoke: open `/dashboard` authenticated and run the browser axe extension.

## Slice status

- **1a** Architecture, ULID schema, models, factories, seeders
- **1b** Fortify auth, RBAC matrix, guarded session/document state machines
- **1c** Inertia shell, design tokens, i18n (en/fil), CI
- **2a–2c** Documents, committees, sessions, voting, realtime, audit
- **3a–3c** Document ingest, RAG, compare, consistency
- **4a–4c** Session assistant, live transcription, draft minutes, history
- **5a** Public portal (404 semantics for unpublished records)
- **5b** Security hardening, monitoring dashboard, validation checklists
- **5c** PWA offline session floor, backup/restore, deployment docs, role manuals — **prompt pack complete**

Final deliverables: [docs/FINAL-DELIVERABLES.md](docs/FINAL-DELIVERABLES.md)

## Queue workers

Document ingest jobs run on Redis. Local development starts a worker via `composer run dev` (`queue:listen`). For Horizon monitoring (recommended when `QUEUE_CONNECTION=redis`):

```bash
php artisan horizon
```

Horizon dashboard: `/horizon` (System Administrator and Secretariat roles).

Alternatively: `php artisan queue:work redis --tries=3`

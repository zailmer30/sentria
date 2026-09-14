# Paperless Legislative Prompt Pack

Agent-sized prompts for building the AI-powered Paperless Legislative Session / LMIS system.

**Source of truth:** this `prompts/` directory. The root [PAPERLESS_LEGISLATIVE_AI_PROMPTS.md](../PAPERLESS_LEGISLATIVE_AI_PROMPTS.md) is only an index.

## How to run

1. Complete [01-human-setup.md](01-human-setup.md) yourself (skills, Docker/infra, empty git repo).
2. For each coding slice, open a **new** agent session with:
   - [00-shared-constraints.md](00-shared-constraints.md)
   - **Exactly one** next slice file
3. Verify **Must-pass** acceptance for that slice.
4. Commit (human confirms) before starting the next slice.
5. Stop if must-pass fails. Do not continue.

## Ordered checklist

| Order | File | Focus |
| --- | --- | --- |
| 0 | [01-human-setup.md](01-human-setup.md) | Skills, env, docker-compose (human) |
| 1 | [1a-architecture-schema.md](1a-architecture-schema.md) | Architecture, ERD, migrations, models, factories/seeders |
| 2 | [1b-auth-rbac-states.md](1b-auth-rbac-states.md) | Fortify auth, RBAC, session + legislative state machines |
| 3 | [1c-inertia-design-ci.md](1c-inertia-design-ci.md) | Inertia/React shell, design tokens, i18n, CI |
| 4 | [2a-documents-committees.md](2a-documents-committees.md) | Documents, versions, ordinances/resolutions, committees |
| 5 | [2b-sessions-agenda.md](2b-sessions-agenda.md) | Sessions, agenda, attendance, paperless session UI |
| 6 | [2c-voting-realtime-audit.md](2c-voting-realtime-audit.md) | Motions, voting integrity, Reverb, audit hash-chain |
| 7 | [3a-ingest-ocr-embeddings.md](3a-ingest-ocr-embeddings.md) | Ingest pipeline, OCR, chunking, embeddings, Horizon |
| 8 | [3b-rag-ask-ai.md](3b-rag-ask-ai.md) | Ask Legislative AI, RAG with SQL-level auth filter |
| 9 | [3c-compare-consistency.md](3c-compare-consistency.md) | Related docs, comparison, consistency checker |
| 10 | [4a-session-assistant.md](4a-session-assistant.md) | Live session AI assistant panel |
| 11 | [4b-transcription-live.md](4b-transcription-live.md) | STT, live transcript over Reverb |
| 12 | [4c-draft-minutes-history.md](4c-draft-minutes-history.md) | AI draft minutes, legislative history timeline |
| 13 | [5a-public-portal.md](5a-public-portal.md) | Public SSR portal + publication workflow |
| 14 | [5b-security-privacy-hardening.md](5b-security-privacy-hardening.md) | Security audit, AI/privacy hardening, checklists |
| 15 | [5c-pwa-offline-backup-deploy.md](5c-pwa-offline-backup-deploy.md) | PWA offline, backup/DR, deploy docs, manuals |

## Agent do-nots

- Do **not** paste all slices into one chat.
- Do **not** start the next slice until must-pass passes.
- Do **not** rebuild the project between slices.
- Do **not** implement AI before slice `3a` (placeholders only earlier).
- Do **not** weaken append-only votes/audit logs, RAG pre-filter auth, or public 404-for-unpublished rules.
- Do **not** claim legal compliance; produce review checklists instead.

## Slice file template

Every coding slice uses:

1. Prerequisite
2. In scope
3. Out of scope
4. Implementation prompt
5. Must-pass acceptance
6. Should-pass (tracked debt)
7. Report format

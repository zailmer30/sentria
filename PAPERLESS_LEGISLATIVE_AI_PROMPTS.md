# AI-Powered Paperless Legislative Session System

## Prompt Pack Index

This file is an **index only**. The executable, agent-sized prompts live in [`prompts/`](prompts/).

Do not treat the old single mega-prompt as source of truth — it was split and tightened so each coding session loads shared constraints plus one slice.

### How to use

1. Complete human setup: [`prompts/01-human-setup.md`](prompts/01-human-setup.md)
2. For every coding session, load:
   - [`prompts/00-shared-constraints.md`](prompts/00-shared-constraints.md)
   - **Exactly one** next slice from the list below
3. Pass that slice’s **Must-pass** checks, commit, then continue
4. Never run all parts in one shot

Full runbook: [`prompts/README.md`](prompts/README.md)

### Slice map

| Order | Slice | File |
| --- | --- | --- |
| 0 | Human setup | [prompts/01-human-setup.md](prompts/01-human-setup.md) |
| 1 | Architecture & schema | [prompts/1a-architecture-schema.md](prompts/1a-architecture-schema.md) |
| 2 | Auth, RBAC, state machines | [prompts/1b-auth-rbac-states.md](prompts/1b-auth-rbac-states.md) |
| 3 | Inertia shell, design system, CI | [prompts/1c-inertia-design-ci.md](prompts/1c-inertia-design-ci.md) |
| 4 | Documents & committees | [prompts/2a-documents-committees.md](prompts/2a-documents-committees.md) |
| 5 | Sessions & paperless UI | [prompts/2b-sessions-agenda.md](prompts/2b-sessions-agenda.md) |
| 6 | Voting, Reverb, audit chain | [prompts/2c-voting-realtime-audit.md](prompts/2c-voting-realtime-audit.md) |
| 7 | Ingest, OCR, embeddings | [prompts/3a-ingest-ocr-embeddings.md](prompts/3a-ingest-ocr-embeddings.md) |
| 8 | Ask Legislative AI / RAG | [prompts/3b-rag-ask-ai.md](prompts/3b-rag-ask-ai.md) |
| 9 | Compare & consistency | [prompts/3c-compare-consistency.md](prompts/3c-compare-consistency.md) |
| 10 | Session AI assistant | [prompts/4a-session-assistant.md](prompts/4a-session-assistant.md) |
| 11 | Transcription / live transcript | [prompts/4b-transcription-live.md](prompts/4b-transcription-live.md) |
| 12 | Draft minutes & history | [prompts/4c-draft-minutes-history.md](prompts/4c-draft-minutes-history.md) |
| 13 | Public portal | [prompts/5a-public-portal.md](prompts/5a-public-portal.md) |
| 14 | Security & privacy hardening | [prompts/5b-security-privacy-hardening.md](prompts/5b-security-privacy-hardening.md) |
| 15 | PWA, backup, deploy, manuals | [prompts/5c-pwa-offline-backup-deploy.md](prompts/5c-pwa-offline-backup-deploy.md) |

### What changed vs the original mega-prompt

- Split Parts 1–5 into agent-sized slices with explicit in/out of scope
- Centralized stack, roles, tables, AI/security invariants in `00-shared-constraints.md`
- Locked product defaults (single LGU, ACL model, quorum config, malware stub, retention)
- Required docker-compose (Postgres 17 + pgvector + Redis) in human setup / `1a`
- Split acceptance into **Must-pass** (gates) vs **Should-pass** (tracked debt)
- Deep Refero research limited to net-new surface families (session floor, public portal, live transcript)

### Non-negotiables (unchanged)

AI is an assistant. Humans remain responsible for legislative decisions, official minutes, voting, approvals, legal interpretation, and official records. Never allow AI to silently modify or decide official government records.

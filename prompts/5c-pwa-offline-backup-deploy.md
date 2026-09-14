# 5c — PWA Offline, Backup/DR, Deployment, Manuals

## Prerequisite

- Slice `5b` must-pass complete
- Load [00-shared-constraints.md](00-shared-constraints.md)

## In scope

- Paperless Session Mode as PWA: cache agenda + linked docs; offline indicator; queue/block votes while offline; idempotent retry
- LAN-friendly architecture notes (critical session docs without external internet)
- Backup/restore (DB, documents, config), 3-2-1 where practical, Backup Dashboard, restore test
- Disaster recovery procedures doc (RPO/RTO configurable)
- Retention policies (no auto hard-delete of official records)
- Deployment docs (dev/test/prod), production hardening checklist
- Role manuals + final deliverable pack
- Performance pass: indexes, caching, pagination, verify HNSW via EXPLAIN where relevant

## Out of scope

- Changing core domain rules from shared constraints
- Claiming production legal certification

## Implementation prompt

```text
CONTINUE THE SAME PROJECT. Load 00-shared-constraints.md.

LOCAL NETWORK / OFFLINE SESSION:
Support government LAN topology. Critical session documents remain accessible if external internet fails. External net only for external AI APIs / remote notifications when configured.

TABLET OFFLINE RESILIENCE (PWA):
Service worker caches current session agenda + linked documents when session starts.
Wi-Fi blip must not blank the board member screen; show offline indicator.
Votes/motions requiring server: queue or clearly indicate unavailable — never silently drop a vote. Retry safe via Part 2 idempotency.

BACKUP:
DB, documents, configuration; versioned; offsite where practical; 3-2-1.
Backup Dashboard: last backup, status, size, next backup, last restore test.
Test backup and restore end-to-end on a copy.

DISASTER RECOVERY doc:
DB/server/storage/network failure, cyber incident, accidental deletion, ransomware, AI provider outage. Configurable RPO/RTO.

RETENTION:
Configurable policies; do not automatically delete official legislative records; hard delete only dual-admin + audit per shared defaults.

ADMIN SETTINGS completeness for users/roles/committees/types/workflow/AI/storage/notifications/backup/portal.

DEPLOYMENT DOCUMENTATION:
Server requirements, exact Laravel 13 patch, PHP 8.4, Node 22, PostgreSQL 17 + pgvector, Redis, Reverb + reverse proxy, storage, TLS, env vars, Horizon, scheduler, migrate, backup/restore.

PRODUCTION:
Debug off, secure cookies, HTTPS, security headers, rate limiting, logging, secrets outside source, protected storage/AI keys.

DOCUMENTATION manuals: Administrator, Secretariat, Presiding Officer, Board Member, Committee Chair, Committee Member, Public User.

FINAL DELIVERABLE pack:
Architecture, ERD, API docs, security architecture, AI architecture, deployment guide, backup/restore guide, admin/user manuals, testing report, known limitations, future improvements, validation checklist for ICT/legal/records/DPO.

FINAL REVIEW verify list from original Part 5 (authz, AI isolation, append-only votes, audit chain, versions, public isolation, backups, citations, OCR, transcription, offline session, tablet UI, relationships, tests).

Tests: public portal regressions, backup/restore, offline/PWA behavior, voting idempotent retry after reconnect.
```

## Must-pass

- [ ] Tablet session mode survives Wi-Fi disconnect: agenda/open doc readable, offline indicated; vote retried after reconnect records exactly once
- [ ] Backup and restore tested end-to-end on a copy of DB + document store
- [ ] Full test suite and CI pipeline pass
- [ ] Validation checklist for ICT / legal / records / DPO is produced
- [ ] Deployment + role manuals exist

## Should-pass

- [ ] Live ransomware/restore drill with ops team
- [ ] Multi-day offline LAN exercise

## Report format

Final deliverable index paths; known limitations; recommended future improvements; confirmation humans remain responsible for official decisions.

## Done

Prompt pack execution complete when this slice’s must-pass is green.

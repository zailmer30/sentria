# Disaster Recovery

Configurable **RPO** (Recovery Point Objective) and **RTO** (Recovery Time Objective) defaults live in `config/sentria.php` (`SENTRIA_RPO_MINUTES`, `SENTRIA_RTO_MINUTES`). Adjust to match your LGU ICT policy.

## Scenario matrix

| Scenario | Impact | Response | RPO/RTO notes |
| --- | --- | --- | --- |
| **Database failure** | No login, sessions, votes | Restore latest `db/database.sql` from backup to new PostgreSQL 17 instance; run migrations if needed | RPO = backup interval; RTO = DB restore + validation |
| **Application server loss** | UI unavailable | Redeploy code from git tag; point to surviving DB/storage; restart Horizon/Reverb | RTO = deploy time + smoke tests |
| **Document storage loss** | Files missing; DB metadata orphaned | Restore `documents/` from backup; verify checksums/sample downloads | RPO = backup interval |
| **Network / LAN outage** | Live updates pause; external AI unavailable | Session floor PWA serves cached agenda/docs; votes queue offline | Session continuity on LAN if app host reachable |
| **External internet loss** | Cloud STT (Groq, ElevenLabs) unavailable; local Qwen3-ASR continues if it is in the failover chain | Core session, voting, documents on LAN continue; AI surfaces show unavailable when every STT hop fails | No automatic legislative decisions during outage |
| **Cyber incident / ransomware** | Integrity compromise suspected | Isolate hosts; restore from **offline/immutable** backup; rotate secrets; verify audit chain | Prefer clean restore over in-place decrypt |
| **Accidental deletion** | Records or files removed | Restore from backup snapshot; use soft-archive recovery where applicable | Votes/audit are append-only — application delete blocked |
| **AI provider outage** | Ask AI, embeddings, transcription degraded | Failover tries the next STT provider; stub/fail if the chain is exhausted. Humans continue manual workflow | AI never blocks official voting/minutes |

## Recovery order

1. **Contain** — isolate affected systems; preserve audit logs.
2. **Assess** — identify last known-good backup (`manifest.json`).
3. **Restore infrastructure** — PostgreSQL, Redis, storage, TLS certs.
4. **Restore data** — `sentria:backup-restore {timestamp} --force` on staging first.
5. **Verify** — `audit:verify-chain`, sample votes read-only, portal 404 semantics for unpublished docs.
6. **Communicate** — ICT, Secretariat, Presiding Officer; document incident per LGU policy.
7. **Resume** — production cutover; schedule post-incident review.

## Human authority during DR

During any incident, **humans** decide whether to proceed with session business, declare results, approve minutes, or publish records. Sentria provides tools and audit trails; it does not make legislative decisions.

## Related

- [backup-restore.md](backup-restore.md)
- [validation-checklist-ict-legal-records-dpo.md](validation-checklist-ict-legal-records-dpo.md)

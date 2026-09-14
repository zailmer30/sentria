# Data Privacy & Security Validation Checklist

**ICT Office · Legal Office · Records Officer · Data Protection Officer**

> **Important:** This checklist supports organizational review aligned with RA 10173 (Data Privacy Act), NPC guidance, ICT security policies, records management, IRP, and e-document considerations. **Completing this checklist does not mean Sentria is compliant.** Legal and DPO sign-off remain the responsibility of the deploying LGU.

## How to use

1. Assign each section to the responsible office.
2. Mark each item: **Done** | **N/A** | **Pending** | **Waived** (with written rationale).
3. Attach evidence (screenshots, configs, policies, training records) in your records management system.
4. Re-run after major upgrades or before go-live.

---

## A. ICT / Information Security

| # | Item | Done | Notes |
| --- | --- | --- | --- |
| A1 | HTTPS/TLS termination configured for all user-facing endpoints | | |
| A2 | `SESSION_SECURE_COOKIE=true` in production | | See `.env.example` |
| A3 | Session timeout (`SESSION_LIFETIME`) approved by ICT policy | | Default 60 minutes |
| A4 | Redis/PostgreSQL credentials rotated and stored in secrets manager | | |
| A5 | `MALWARE_SCANNER=clamav` (or equivalent) enabled on upload path | | |
| A6 | Security headers verified (`SecurityHeaders` middleware) | | See `docs/security-architecture.md` |
| A7 | Rate limits reviewed for portal search, login, AI endpoints | | |
| A8 | Horizon/queue workers monitored; failed jobs alert configured | | `/admin/monitoring` |
| A9 | Backup schedule and restore drill documented | | See [backup-restore.md](backup-restore.md), `sentria:backup` |
| A10 | Vulnerability scanning / patch cadence for OS, PHP, Node, PostgreSQL | | |

---

## B. Legal / Legislative Affairs

| # | Item | Done | Notes |
| --- | --- | --- | --- |
| B1 | Rules of procedure acknowledge electronic voting is **not automatically binding** unless local rules say otherwise | | `sentria.voting.electronic_is_binding` |
| B2 | Electronic signature policy distinguishes image acknowledgement from qualified digital signature | | `electronic_signatures` config |
| B3 | AI use policy: AI assists staff; humans decide votes, minutes, publication | | |
| B4 | Publication workflow legally reviewed before public portal go-live | | |
| B5 | Confidential / executive session documents classified per local rules | | Confidentiality enum |
| B6 | Legal review of retention and archive policy (soft-archive default) | | |
| B7 | Terms of use / privacy notice for public portal drafted and published | | |

---

## C. Records Management

| # | Item | Done | Notes |
| --- | --- | --- | --- |
| C1 | Document workflow states match approved IRP / records disposition schedule | | State machines |
| C2 | Official minutes finalized only by authorized roles (not AI) | | `MinutesPolicy` |
| C3 | Append-only votes and audit logs verified (`audit:verify-chain`) | | |
| C4 | Public records separated from internal drafts (portal 404 semantics) | | |
| C5 | File naming, reference numbers, and version history meet records standards | | |
| C6 | Export / transmittal procedures for approved ordinances and resolutions | | |
| C7 | Disaster recovery and off-site backup retention approved | | Slice 5c |

---

## D. Data Protection Officer (RA 10173–aligned review)

| # | Item | Done | Notes |
| --- | --- | --- | --- |
| D1 | Privacy Impact Assessment (PIA) conducted for Sentria deployment | | |
| D2 | Personal data inventory: users, attendance, auth logs, audit trail | | |
| D3 | Lawful basis documented for each processing purpose | | |
| D4 | Role-based access reviewed; least privilege confirmed | | Permission matrix |
| D5 | Data subject rights procedure (access, correction, objection) established | | |
| D6 | Breach notification procedure aligned with NPC requirements | | |
| D7 | AI processing documented: inherits user permissions; no automated decisions on official records | | |
| D8 | Cross-border transfer assessment if AI provider processes outside PH | | OpenAI-compatible config |
| D9 | Retention periods for logs, transcripts, and AI conversations defined | | |
| D10 | DPO sign-off recorded before production processing of personal data | | |

---

## E. Sign-off block (do not auto-fill)

| Role | Name | Date | Signature |
| --- | --- | --- | --- |
| ICT Officer | | | |
| Legal Officer | | | |
| Records Officer | | | |
| Data Protection Officer | | | |
| Head of Agency / SP Secretary | | | |

---

## References in repository

- [security-architecture.md](security-architecture.md)
- [architecture.md](architecture.md)
- [../config/sentria.php](../config/sentria.php)
- [../prompts/00-shared-constraints.md](../prompts/00-shared-constraints.md)

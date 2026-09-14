# 5b — Security, Privacy, AI Hardening, Validation Checklists

## Prerequisite

- Slice `5a` must-pass complete
- Load [00-shared-constraints.md](00-shared-constraints.md)

## In scope

- Security review and fixes: authz, IDOR, uploads, XSS/CSRF/SQLi, session, rate limits, secrets, AI prompt injection, RAG access control
- Verify vote/audit append-only + hash-chain still hold
- Data privacy design notes aligned to RA 10173 / NPC guidance **without claiming compliance**
- ICT / legal / records / DPO validation checklist
- Electronic approval / digital signature **architecture** (configurable; image ≠ legal signature)
- Admin monitoring dashboard foundations (status, queues, AI, errors, logins, backups placeholder)

## Out of scope

- PWA offline voting resilience and full backup/DR drills (`5c`)
- Full end-user manuals (`5c`)

## Implementation prompt

```text
CONTINUE THE SAME PROJECT. Load 00-shared-constraints.md.

SECURITY AUDIT — review and fix:
Authentication, authorization, RBAC, API security, file uploads, SQLi, XSS, CSRF, IDOR, session security, rate limiting, password security, secrets management, AI prompt injection, RAG access control, document permissions, audit logs.

AI SECURITY:
Protect against prompt injection, indirect injection from documents, data leakage, unauthorized retrieval, sensitive exposure, malicious uploads, hallucination.
Uploaded documents must never override system instructions.

AUDIT:
Confirm append-only DB enforcement and hash-chain verification still pass.
Protect audit logs from ordinary users.

DATA PRIVACY:
Design with Philippine government privacy requirements in mind (RA 10173, NPC guidance, ICT/security policies, records-management, IRP, e-document requirements).
Do NOT claim automatic legal compliance.
Create a Data Privacy/Security Configuration Checklist for ICT office, legal office, records officer, and Data Protection Officer.

E-SIGNATURES:
Architecture for electronic approval, digital signatures, integrity verification, signature metadata.
Do not treat uploaded signature images as legally valid digital signatures. Make configurable.

ADMIN MONITORING dashboard:
System/DB/storage/queue/AI status, failed jobs, recent errors, login activity, backup status placeholder.

Tests: security/feature tests for IDOR, public 404, RAG isolation regression, prompt-injection fixture where practical.
```

## Must-pass

- [ ] Security findings from the review are fixed or explicitly waived with rationale in the checklist
- [ ] Audit chain verify passes; votes remain append-only
- [ ] RAG permission isolation regression test still passes
- [ ] ICT/legal/records/DPO validation checklist document exists in repo
- [ ] CI still passes

## Should-pass

- [ ] External penetration test (human process)
- [ ] Full digital signature vendor integration

## Report format

Security architecture notes, checklist path, remaining risks, debt for `5c`.

## Next

[5c-pwa-offline-backup-deploy.md](5c-pwa-offline-backup-deploy.md)

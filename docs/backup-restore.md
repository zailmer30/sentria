# Backup & Restore

Sentria backups capture the **database**, **document store**, and a **sanitized configuration snapshot** (`.env.example` copy — never live secrets).

## 3-2-1 guidance

| Rule | Sentria practice |
| --- | --- |
| **3** copies | Production DB + documents, on-server backup directory, offsite replica |
| **2** media | e.g. NAS/local disk + object storage or tape |
| **1** offsite | Replicate `storage/app/backups/` to a separate facility or cloud bucket |

Configure organizational RPO/RTO via `SENTRIA_RPO_MINUTES` and `SENTRIA_RTO_MINUTES` (defaults: 60 / 240 minutes). These are **targets** for ICT planning — adjust to match SP/SB policy.

## Commands

### Create backup

```bash
php artisan sentria:backup
```

Output directory: `storage/app/backups/{Y-m-d_His}/`

Contents:

- `db/database.sql` — `pg_dump` when available, otherwise PHP-generated SQL
- `documents/` — copy of `storage/app/private`
- `config/env.example.snapshot` — non-secret config reference
- `manifest.json` — sizes, timestamps, component list

### Restore (destructive)

```bash
php artisan sentria:backup-restore 2026-08-10_143000 --force
```

Options:

- `--force` — required; restore overwrites live data
- `--documents-only` — restore files without applying database SQL (useful for drills)

**Always restore to a staging copy first** before production cutover.

### Verify indexes (performance)

```bash
php artisan sentria:check-indexes
```

## Restore test procedure

1. Take a fresh backup on production (or staging mirror).
2. Provision an isolated staging VM/database.
3. Deploy Sentria code matching the backup era.
4. Run `sentria:backup-restore {timestamp} --force` on staging.
5. Verify login, sample document download, audit chain (`php artisan audit:verify-chain`), and a test vote on a sandbox session.
6. Record the drill timestamp — shown on `/admin/monitoring` as **Last restore test**.

Automated tests (`tests/Feature/Backup/BackupArtifactTest.php`) verify manifest integrity and document round-trip without full production DB restore in CI.

## Retention policy

Official legislative records use **soft archive** only. Sentria does **not** automatically hard-delete ordinances, resolutions, minutes, votes, or audit logs.

Hard delete (when explicitly enabled via `SENTRIA_ALLOW_HARD_DELETE`) requires **dual administrator confirmation** and audit logging per shared product defaults. Backup retention (`SENTRIA_BACKUP_RETENTION_DAYS`, default 30) applies to backup **artifacts**, not to live legislative records.

## Monitoring

The **Backup & recovery** panel on `/admin/monitoring` shows:

- Last backup time and size
- Last restore test time
- RPO/RTO placeholders
- Command hint for manual/scheduled backups

## Related documents

- [disaster-recovery.md](disaster-recovery.md)
- [validation-checklist-ict-legal-records-dpo.md](validation-checklist-ict-legal-records-dpo.md)

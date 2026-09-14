# Administrator Manual

## Role purpose

Full control over users, roles, system integrity, backups, and monitoring.

## Daily tasks

- Review `/admin/monitoring` — failed jobs, backup status, recent sign-ins.
- Open `/horizon` for queue health when using Redis.
- Respond to failed document ingest or malware scan alerts.

## User & access management

- Create/deactivate users; assign roles via Spatie Permission matrix.
- Ensure seated-member flags are correct before sessions.
- Never share administrator credentials; enforce session timeout policy.

## Backup & recovery

```bash
php artisan sentria:backup
php artisan sentria:backup-restore {timestamp} --force   # staging only until verified
php artisan audit:verify-chain
php artisan sentria:check-indexes
```

Schedule nightly backups and quarterly restore drills. Record drill dates for auditors.

## Settings index

Visit `/settings` for links to users, roles, committees, AI, storage, backup, portal, monitoring, and chamber microphones.

Capture mode: default is mixer mix (secretariat assigns speakers). Optional per-seat mapping: wire every dedicated mic into **one** multi-channel interface, then map channel → member at `/settings/chamber-channels`. Step-by-step: [chamber-microphones.md](chamber-microphones.md).

## Security

- Complete [validation-checklist-ict-legal-records-dpo.md](../validation-checklist-ict-legal-records-dpo.md) before go-live.
- Keep `APP_DEBUG=false` in production; rotate secrets after staff changes.
- Hard delete of official records requires dual-admin confirmation (disabled by default).

## You do NOT

- Cast votes on behalf of members unless your organization explicitly allows a separate procedure outside Sentria.
- Finalize minutes or declare vote results — those are human secretariat/presiding officer duties.

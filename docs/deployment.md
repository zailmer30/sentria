# Deployment Guide

Sentria is a single-organization Laravel 13 LMIS for paperless legislative sessions. This guide covers development, staging, and production deployment on a government LAN or datacenter.

## Server requirements

| Component | Version |
| --- | --- |
| PHP | 8.4+ (extensions: `pdo_pgsql`, `redis`, `mbstring`, `xml`, `curl`, `gd` or `imagick`, `zip`) |
| Laravel | 13.x (`laravel/framework` newest stable patch at deploy time) |
| Node.js | 22 LTS (build only) |
| PostgreSQL | 17 with **pgvector** |
| Redis | 7+ |
| Web server | Nginx or Apache with TLS termination |
| Process manager | systemd or Supervisor for Horizon, Reverb, scheduler |

Optional production adapters:

- ClamAV (`MALWARE_SCANNER=clamav`)
- S3-compatible object storage (`FILESYSTEM_DISK=s3`)
- OpenAI-compatible AI API (`AI_ENABLED=true`)
- Chamber capture daemon on a PC attached to a multi-channel audio interface ([chamber-capture.md](chamber-capture.md))

## LAN architecture

Government session floors often run on a **local network** that must remain usable when external internet fails.

```
[Tablets / PO laptop] ──Wi-Fi/LAN──► [App + Reverb + Redis]
                                           │
                                           ├── PostgreSQL (session data, votes, audit)
                                           └── NAS / local disk (document store)
External internet (optional): AI APIs, remote email, offsite backup replication
```

**Design intent:**

- Agenda, linked session documents, and cached floor JSON remain readable offline via the session-floor service worker (`public/sw.js`).
- Votes queue locally and retry through the idempotent cast endpoint when connectivity returns.
- Real-time updates (Reverb/Echo) require LAN reachability to the Reverb host; cached content covers read-only continuity during brief outages.
- External internet is required only for cloud AI, remote notifications, and offsite backup targets when configured.

## Environment variables

Copy `.env.example` to `.env`. Critical production values:

| Variable | Purpose |
| --- | --- |
| `APP_ENV=production` | Production mode |
| `APP_DEBUG=false` | Never enable in production |
| `APP_URL` | Canonical HTTPS URL |
| `SESSION_SECURE_COOKIE=true` | Secure session cookies |
| `DB_*` | PostgreSQL 17 connection |
| `REDIS_*` | Cache, queue, sessions (recommended) |
| `QUEUE_CONNECTION=redis` | Horizon workers |
| `BROADCAST_CONNECTION=reverb` | Live session surfaces |
| `REVERB_*` | WebSocket server (behind reverse proxy) |
| `FILESYSTEM_DISK` | `local` (NAS path) or `s3` |
| `AI_*` | Optional; keep keys server-side only |
| `SENTRIA_RPO_MINUTES` / `SENTRIA_RTO_MINUTES` | Organizational DR targets |

See also `config/sentria.php` for quorum, voting binding, retention, and backup defaults.

## Install steps

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan key:generate   # first deploy only
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Background services

### Horizon (queues)

```bash
php artisan horizon
```

Dashboard: `/horizon` (System Administrator, Secretariat).

### Scheduler

Add to crontab:

```cron
* * * * * cd /var/www/sentria && php artisan schedule:run >> /dev/null 2>&1
```

Recommended scheduled tasks:

- `sentria:backup` (nightly)
- `audit:verify-chain` (weekly)

### Reverb (WebSockets)

Run behind TLS-terminating reverse proxy:

```bash
php artisan reverb:start
```

Proxy WebSocket traffic to Reverb. Tablets on the session floor must reach this host on the LAN.

## Production hardening checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] HTTPS everywhere; HSTS at reverse proxy
- [ ] `SecurityHeaders` middleware active (see `docs/security-architecture.md`)
- [ ] Rate limits on login, portal search, AI endpoints
- [ ] Secrets in environment / vault — never in git
- [ ] Document storage outside web root (`storage/app/private`)
- [ ] PostgreSQL least-privilege DB user; vote/audit append-only triggers in place
- [ ] Redis password; bind to private network
- [ ] Log aggregation and failed-job alerting
- [ ] Backup schedule + restore drill (see [backup-restore.md](backup-restore.md))
- [ ] Validation checklist completed ([validation-checklist-ict-legal-records-dpo.md](validation-checklist-ict-legal-records-dpo.md))

## Performance notes

- **pgvector HNSW** index on `document_embeddings.embedding` — verify with `php artisan sentria:check-indexes`.
- Use **Redis** for cache/session in production (`CACHE_STORE=redis`).
- Public portal search and admin indexes use pagination — do not disable.

## Cache configuration (production)

```env
CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
```

After changing config:

```bash
php artisan config:cache
php artisan optimize
```

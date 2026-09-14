# API Overview

Sentria is primarily an **Inertia.js** web application (Fortify session auth). External integrations use **Laravel Sanctum** token auth on `routes/api.php`.

## Authentication

| Consumer | Mechanism |
| --- | --- |
| Web UI (Inertia) | Fortify session cookies + CSRF |
| External API | Sanctum personal access tokens |

Do not expose AI keys or storage credentials to the frontend.

## Key web routes (Inertia)

| Area | Prefix / examples |
| --- | --- |
| Dashboard | `GET /dashboard` |
| Documents | `GET/POST /documents`, versions, grants, transitions |
| Sessions | `GET/POST /sessions`, floor modes, voting |
| Session floor cache (PWA) | `GET /sessions/{id}/floor/cache` (JSON) |
| Voting | `POST /sessions/{id}/voting/cast` (idempotent) |
| Public portal | `GET /portal/*` (404 for unpublished) |
| Admin | `GET /admin/monitoring`, `GET /settings` |
| AI | `POST /ai/ask`, session assistant endpoints |

Named routes are available via Ziggy (`route()` in PHP, `@/lib` Ziggy on frontend).

## Voting API contract

`POST /sessions/{session}/voting/cast`

```json
{
  "agenda_item_id": "01J…",
  "choice": "yes|no|abstain|inhibit",
  "voting_round": 1
}
```

Idempotent on `(session_id, agenda_item_id, voting_round, user_id)`. Duplicate submissions return HTTP 200 with the existing ballot.

Offline clients queue votes in `localStorage` (`resources/js/lib/offlineVoteQueue.ts`) and flush on reconnect.

## Real-time

Laravel Reverb + Echo channels for session floor, voting, transcripts. Broadcast auth follows session view permissions.

## Chamber capture API

Sanctum ability `chamber:capture` (machine user; cannot sign in to the Inertia app).

| Method | Path | Purpose |
| --- | --- | --- |
| GET | `/api/chamber/recording` | Live session, capture mode, channel map, session clock |
| POST | `/api/chamber/heartbeat` | Device health and per-channel RMS |
| POST | `/api/sessions/{session}/chamber/chunks` | Concurrent per-channel audio uploads |

Chunks are accepted only while the session is `in-session` and `recording_enabled`. Overlapping speech from two seats is two independent uploads. Recordings are never auto-published.

Hardware and daemon setup: [chamber-capture.md](chamber-capture.md).

See `routes/api.php` for Sanctum-protected endpoints. Rate limiting applies per route group.

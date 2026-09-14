# Sentria — Security Architecture (Slice 5b)

Design notes for ICT, legal, records, and DPO review. **This document does not claim legal compliance** with RA 10173, NPC issuances, or any statute.

## Authentication and session

- **Fortify** session cookies for the Inertia app; Sanctum tokens for external API consumers only.
- CSRF protection on state-changing web routes.
- Login rate limit: 5 attempts/minute per email+IP (`FortifyServiceProvider`).
- Session cookies: `SESSION_ENCRYPT=true`; production should set `SESSION_SECURE_COOKIE=true` over HTTPS and evaluate `SESSION_SAME_SITE=strict` (see `.env.example`).
- Successful sign-ins are append-only audit events (`auth.login`).

## Authorization (RBAC + document ACL)

- Eight canonical roles via `spatie/laravel-permission`; permissions enforced in policies and form requests.
- Document access: role + confidentiality + explicit `document_grants`.
- **Private notes**: owner-only (`PrivateNotePolicy`, `ownedBy` scope in session payloads).
- **AI conversations**: scoped to owning user in `AiController::show`.
- **Minutes drafts**: internal routes require `minutes.view` + session visibility; public portal returns **404** for non-final minutes (`PublicPortal::findPublicMinutesOrAbort404`).

## Public portal (404 semantics)

- Unpublished or withdrawn publications abort **404**, never 403 (`PublicPortal` gate).
- Internal fields (review notes, AI metadata, private notes) are not exposed on portal Inertia props.
- Portal search rate limit: 60 requests/minute per IP.

## Append-only votes and audit

- `votes` and `audit_logs` are append-only at application and database layers.
- Vote idempotency: unique `(session_id, agenda_item_id, voting_round, user_id)`.
- Audit rows are hash-chained; verify with `php artisan audit:verify-chain`.
- Ordinary users cannot access `/audit` without `audit.viewAny`.

## RAG and AI security

- Authorization filter is part of the **SQL/vector retrieval query** (`VectorLegislativeSearchService`), not a post-filter.
- AI inherits the authenticated user's permissions; no privilege elevation for AI routes.
- System prompts (`resources/prompts/rag_system.md`, `summarization_system.md`) instruct models to **ignore embedded document instructions**.
- Offline `HashChatCompletionService` refuses secret-exfiltration attempts when chunks contain injection patterns.
- AI ask endpoints: 20 requests/minute per user (`ai-ask` rate limiter).
- Protected response strings (`sentria.ai.protected_response_strings`) must never appear in model output.

## HTTP security headers

`App\Http\Middleware\SecurityHeaders` (web stack):

| Header | Value |
| --- | --- |
| Content-Security-Policy | Restrictive defaults for Inertia/Vite; `unsafe-inline` for built assets. In `local`+`debug`, Vite `:5173` is allowlisted in `script-src`/`style-src`/`connect-src` so HMR assets can load (otherwise the SPA stays blank). |
| X-Frame-Options | DENY |
| X-Content-Type-Options | nosniff |
| Referrer-Policy | strict-origin-when-cross-origin |
| Permissions-Policy | Disables camera, geolocation, payment, USB. Allows microphone for this origin only (`microphone=(self)`) so Chamber microphones can list inputs on this PC. |
| Strict-Transport-Security | Only when request is HTTPS |

## Upload hardening

- MIME allowlist in `config/sentria.php` enforced in `DocumentVersionService`.
- Double extensions in original filename rejected (e.g. `report.pdf.exe` basename).
- Pluggable malware scanner (`MALWARE_SCANNER`); production should use ClamAV or equivalent.

## Electronic signatures (architecture stub)

Configured in `config/sentria.php` → `electronic_signatures`:

- **Disabled by default** (`SENTRIA_ELECTRONIC_SIGNATURES_ENABLED=false`).
- Drivers: `none` | `image_ack` | `pkcs7_placeholder`.
- **`image_is_legal_signature` is always false** — uploaded signature images are acknowledgements only, not qualified digital signatures.

## Admin monitoring

- `/admin/monitoring` requires `settings.viewAny` (System Administrator).
- Shows environment, queue driver, failed job count, AI flag, backup placeholder, recent sign-ins.
- **No secrets** (API keys, DB passwords) on the dashboard.

## Security review — findings fixed or waived

| ID | Finding | Status | Rationale |
| --- | --- | --- | --- |
| SEC-01 | Missing security headers | **Fixed** | `SecurityHeaders` middleware on web stack |
| SEC-02 | Portal search abuse | **Fixed** | 60/min IP throttle on `/portal/search` |
| SEC-03 | AI ask abuse | **Fixed** | 20/min user throttle on `ai.ask` and session assistant ask |
| SEC-04 | Double-extension uploads | **Fixed** | Rejected in `DocumentVersionService` |
| SEC-05 | Guest access to `/ai`, `/audit` | **Fixed** | Auth middleware + redirect to login; tests |
| SEC-06 | IDOR on private notes / AI conversations | **Fixed** | Policies + owner checks; feature tests |
| SEC-07 | Draft minutes on public portal | **Waived** | Already 404 via `PublicPortal`; regression tests in Part 5a |
| SEC-08 | Prompt injection via documents | **Mitigated** | System prompts + HashChat fixture test; full LLM guardrails vendor-dependent |
| SEC-09 | CSP `unsafe-inline` for Vite | **Waived** | Required for Inertia/Vite asset model; tighten with nonces in a future hardening pass |
| SEC-10 | External penetration test | **Waived** | Human process; out of scope for automated slice |
| SEC-11 | Qualified digital signature vendor | **Waived** | Architecture stub only; integration deferred |

## Remaining risks (for 5c and operations)

- PWA offline voting resilience and backup/DR drills not yet implemented.
- Production malware scanning adapter must be configured and tested.
- HSTS preload and CSP nonces not yet applied.
- Human penetration test and organizational DPO sign-off still required before production.

## Related documents

- [validation-checklist-ict-legal-records-dpo.md](validation-checklist-ict-legal-records-dpo.md)
- [architecture.md](architecture.md)
- [../prompts/00-shared-constraints.md](../prompts/00-shared-constraints.md)

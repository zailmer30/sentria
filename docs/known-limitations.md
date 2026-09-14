# Known Limitations

Sentria is production-quality LMIS software for a **single LGU**. The following limitations are intentional or pending organizational adoption.

## Legal & compliance

- **Not certified** for automatic compliance with RA 10173, e-document rules, or electronic voting statutes. Use the [validation checklist](validation-checklist-ict-legal-records-dpo.md).
- Electronic voting binding is **configurable** and off by default (`SENTRIA_ELECTRONIC_VOTING_BINDING`).

## Offline / PWA

- Service worker scope is limited to session floor usage; full admin UI requires connectivity.
- Document downloads cache **best-effort** (auth cookies may expire during long offline periods).
- Real-time quorum/vote tallies pause offline; cached agenda remains readable.
- No Vitest suite for frontend queue module — contract documented in PHP tests + manual QA checklist.

## Backup

- Full database restore in CI is not executed (artifact integrity + document round-trip tested).
- PHP SQL fallback dump is slower than `pg_dump` for large databases.
- `.env` secrets are **not** included in backups by design.

## AI

- Requires external provider for production-quality embeddings/chat (optional).
- OCR/transcription drivers default to no-op stubs in development.
- Per-seat live transcription requires a multi-channel chamber interface. Mixer mix is supported as the default feed; overlapping speech on a mix becomes one unassigned chunk for the secretariat to assign and edit. Unsynchronized USB mics are not supported. Visayan/Cebuano STT is weaker than English and Tagalog unless a Cebuano-capable hop (for example ElevenLabs Scribe) is in the failover chain and the DPO has approved cloud audio.

## Settings UI

- `/settings` is an index with links; full CRUD for every setting category is not implemented in a single module.

## Multi-tenancy

- Single organization only — no SaaS multi-tenant mode.

## Humans remain responsible

Legislative decisions, official minutes, vote declarations, legal interpretation, and publication approval are **always** human responsibilities.

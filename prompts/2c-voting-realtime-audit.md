# 2c — Motions, Voting Integrity, Reverb, Audit Hash-Chain

## Prerequisite

- Slice `2b` must-pass complete
- Load [00-shared-constraints.md](00-shared-constraints.md)

## In scope

- Motions recording
- Electronic voting (YES / NO / ABSTAIN) with append-only table, DB enforcement, idempotent unique constraint
- Laravel Reverb + Echo live updates for session state, attendance/quorum, motions, voting open/cast aggregates/close, agenda changes
- Authorized broadcast channels
- Append-only `audit_logs` with hash-chain + artisan verify command; DB-level append-only for `votes` and `audit_logs`
- Wire Secretariat/Presiding Officer voting controls; Session Dashboard live panels
- Configurable “electronic vote not automatically legally binding” setting

## Out of scope

- AI, OCR, RAG, transcription
- Public portal, PWA offline (Part 5)
- Minutes AI draft (Part 4) — manual minutes scaffolding OK

## Implementation prompt

```text
CONTINUE THE SAME PROJECT. Load 00-shared-constraints.md.

MOTIONS:
Record motion, mover, seconder, agenda item, timestamp, result, related vote, remarks.

ELECTRONIC VOTING:
Options YES / NO / ABSTAIN. Record user, session, agenda item, vote, timestamp, voting round. Generate results.

VOTE INTEGRITY (non-negotiable):
- votes table append-only: no UPDATE/DELETE at app layer; enforce at DB (triggers or revoked privileges). Corrections = new record in a new voting round.
- Idempotent submit: unique (session_id, agenda_item_id, voting_round, user_id). Double-submit from flaky tablets must not duplicate or error visibly.
- Every cast vote audit-logged.
- Electronic voting is not automatically legally binding — configurable.

REAL-TIME (Reverb + Echo — no polling):
Broadcast: session state changes, attendance/quorum, motion recorded, voting opened / aggregate counts / closed, agenda item changes.
All session screens update live. Channels authorized per permission.

AUDIT LOG INTEGRITY:
- audit_logs append-only at DB level.
- Hash-chain each row (content + previous hash). Artisan command verifies; corruption fails verification.
- Log document/session/agenda/attendance/motion/vote/permission events as applicable.

MINUTES:
Allow Secretariat to create/edit/review/finalize/archive minutes manually; prepare hooks for future AI draft but do not implement AI.

Tests: voting idempotency, append-only enforcement, broadcast auth, full walkthrough create session → agenda → start → motion → open voting → two roles vote → close → adjourn with live updates.
```

## Must-pass

- [ ] Full walkthrough with seeded users: session → agenda → start → motion → open voting → cast from two role accounts → close → adjourn
- [ ] Two browser windows on same session update live (no refresh) for agenda, motions, vote counts
- [ ] UPDATE/DELETE on vote or audit_log at DB level fails
- [ ] Duplicate vote submit → exactly one record
- [ ] Audit chain verify command passes; manual corruption makes it fail
- [ ] CI still passes

## Should-pass

- [ ] Horizon dashboard linked from admin for future AI jobs
- [ ] Exhaustive audit coverage of every document view event at scale (sample coverage OK)

## Report format

Endpoints/events, permission matrix updates, tests, setup for Reverb, debt for Part 3.

## Next

[3a-ingest-ocr-embeddings.md](3a-ingest-ocr-embeddings.md)

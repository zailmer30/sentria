# 4a — Session AI Assistant Panel

## Prerequisite

- Slice `3c` must-pass complete
- Load [00-shared-constraints.md](00-shared-constraints.md)

## In scope

- In-session AI assistant panel for active sessions
- Current agenda item context: document summary, related legislation, previous similar legislation, document history, AI search
- Wire into Paperless Session Mode without making AI a decision-maker
- Focused design pass for assistant panel (tablet-first)

## Out of scope

- Speech-to-text / live transcript (`4b`)
- AI draft minutes / legislative history timeline (`4c`)
- Motion detection from audio

## Implementation prompt

```text
CONTINUE THE SAME PROJECT. Load 00-shared-constraints.md.

Re-read design skills for an assistant-panel pattern on tablet session floors; keep AI visually distinct.

SESSION AI ASSISTANT (during active session):
Provide: Current Agenda Item, Document Summary, Related Legislation, Previous Similar Legislation, Document History, AI Search.
(Transcript Search arrives in 4b; Amendment Comparison can reuse 3c compare where versions exist.)

AI must never: decide quorum, decide whether a vote passed, approve minutes/ordinances, change official voting records, or create official decisions.
Use official system records wherever available.
Apply RBAC; users only see AI context for documents they can access.
Audit AI queries from the session floor.

Tests: permission controls on session AI; assistant does not expose restricted docs; panel loads for In Session state only (or as designed with auth).
```

## Must-pass

- [ ] Authorized user in an active session sees assistant context for current agenda item
- [ ] Unauthorized document content never appears in assistant answers
- [ ] AI panels use the AI visual treatment and verify banners where summaries appear
- [ ] CI still passes

## Should-pass

- [ ] Persisted per-session assistant conversation threads
- [ ] Amendment comparison deep-linked from agenda item versions

## Report format

UI integration points, permissions, tests, debt for `4b`.

## Next

[4b-transcription-live.md](4b-transcription-live.md)

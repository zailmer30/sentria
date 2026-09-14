# 4c — AI Draft Minutes, Motions Suggestions, Legislative History

## Prerequisite

- Slice `4b` must-pass complete
- Load [00-shared-constraints.md](00-shared-constraints.md)

## In scope

- AI draft minutes after adjournment from metadata, attendance, agenda, motions, official votes, transcript, secretariat notes
- Minutes workflow state machine; AI never auto-finalizes
- AI-suggested motions/seconders/agenda/results labeled “AI Suggested”; Secretariat confirms
- Session summary + action items requiring Secretariat confirmation
- Legislative history visual timeline
- Official electronic voting records are authoritative in drafts (byte-for-byte counts)

## Out of scope

- Public portal, PWA offline, production hardening (Part 5)

## Implementation prompt

```text
CONTINUE THE SAME PROJECT. Load 00-shared-constraints.md.

AI DRAFT MINUTES:
After session completion, generate DRAFT MINUTES from session metadata, attendance, agenda, motions, voting results, transcript, secretariat notes.
Banner: "AI-GENERATED DRAFT — REQUIRES SECRETARIAT REVIEW".

MINUTES WORKFLOW (state machine — names must match 00-shared-constraints.md):
Session Completed → AI Draft → Secretariat Review → Edit → Review → Approval → Final Minutes → Archive.
AI must never automatically finalize minutes. Non-Secretariat roles cannot finalize.

MOTION DETECTION:
AI may suggest motions, seconders, agenda items, results — label "AI Suggested". Secretariat confirms.

VOTING:
Official electronic voting records are authoritative. AI must not determine vote results from audio if official voting data exists. Draft minutes must reflect official vote records exactly.

SESSION SUMMARY + ACTION ITEMS:
Draft major items, motions, decisions, votes, documents discussed, pending matters, follow-ups. Action items need Secretariat confirmation before becoming official tasks. Mark AI-generated.

LEGISLATIVE HISTORY timeline:
Document → Committee → Sessions → Amendments → Votes → Approval → Final Version.

Tests: draft generation after adjournment; finalize permissions; vote counts match official records; audit logging.
```

## Must-pass

- [ ] AI draft minutes generate after adjournment with required banner; cannot be finalized by AI or non-Secretariat
- [ ] Where official votes exist, draft minutes match them exactly
- [ ] Legislative history timeline renders for a seeded ordinance path
- [ ] CI still passes

## Should-pass

- [ ] Motion suggestions precision on noisy transcripts
- [ ] Rich timeline filtering/export

## Report format

Minutes states, services, tests, debt for Part 5.

## Next

[5a-public-portal.md](5a-public-portal.md)

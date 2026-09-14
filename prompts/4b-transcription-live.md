# 4b — Transcription and Live Transcript

## Prerequisite

- Slice `4a` must-pass complete
- Load [00-shared-constraints.md](00-shared-constraints.md)

## In scope

- Optional session speech-to-text pipeline (Whisper-compatible or configured provider)
- Timestamped transcripts with optional speaker, agenda association, searchable text
- Manual correction of transcript segments
- Live transcript streaming to authorized session screens via Reverb
- Transcript search (“What did we discuss about…”) with jump-to-timestamp
- Do not auto-publish recordings/transcripts

## Out of scope

- AI draft minutes finalization workflow (`4c`)
- Using audio to override official vote records (forbidden)

## Implementation prompt

```text
CONTINUE THE SAME PROJECT. Load 00-shared-constraints.md.

Focused design research allowed for live-transcript surfaces.

SPEECH-TO-TEXT workflow:
Session Audio → STT → Timestamped Transcript → Agenda Association → Searchable Transcript.

Fields: timestamp, speaker if identifiable, text, agenda item, session.
Do not assume speaker identification is always accurate; allow manual correction.

LIVE TRANSCRIPTION (if practical with chosen provider):
Stream segments over Reverb. Channel auth: only users permitted to view the session transcript may subscribe.
Authorized users can search live transcript.

SESSION SEARCH:
Return relevant timestamp, speaker, text, agenda item, jump link.

SECURITY:
Do not automatically publish recordings or transcripts.
AI must not determine vote results from audio if official voting data exists.

Tests: transcription job, search, manual correction, broadcast channel authorization, permission denials.
```

## Must-pass

- [ ] Simulated session with audio produces timestamped, agenda-associated, searchable, manually correctable transcript
- [ ] Live transcript appears for authorized user without refresh; unauthorized user cannot subscribe
- [ ] CI still passes

## Should-pass

- [ ] High-accuracy speaker diarization
- [ ] Continuous multi-hour session stability soak test

## Report format

STT config, channels, tests, debt for `4c`.

## Next

[4c-draft-minutes-history.md](4c-draft-minutes-history.md)

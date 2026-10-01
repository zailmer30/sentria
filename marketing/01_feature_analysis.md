# Sentria feature analysis for the marketing film

Sentria is a paperless legislative session and records system for one Philippine LGU body. This film is for the secretariat and the presiding officer, who buy it. Board members are the moment of use. The public portal is the outcome.

Tagline, used only in Filipino: **Sistemang Paperless para sa Sesyong Pambatas**.

Narration is English. Tone is institutional and restrained. Organization on screen is the placeholder body: Sangguniang Panlalawigan, short name SP, locality Province of Demo. No real seal, no real officials.

## Roles in this film

| Role | Where they appear | Not in this film |
| --- | --- | --- |
| Board member (`member@sentria.test`, Rafael Dizon) | Member floor. Reads, marks, notes, raises a motion, votes. | |
| Presiding officer (`presiding@sentria.test`, Teresita Villanueva) | Same member floor. Named in the header. Later approves minutes, off the floor. | No separate presiding console. `/floor/presiding` redirects to `/floor/member`. |
| Secretariat (`secretariat@sentria.test`, Joselito Fernandez) | Floor console: roll, agenda, voting, motion wording, hall PDF, transcript, draft minutes. | |
| System administrator | Not seated. Not a scene. | User admin and backups are out. |
| Public visitor | Signed-out `/portal`, once, in the proof. | |
| Committee chair, committee member, legal reviewer | | Do not log in as them and do not mention them. |

## What the seeded marketing demo can show

`MarketingDemoSeeder` (database name must contain `marketing`). Floor shots are the **38th Regular Session** (in session). Transcript proof, draft minutes, and the approval action are the **37th Regular Session** (adjourned). The edit hides session titles and dates so the two sittings read as one. Session ULIDs are created at seed time and are not known until capture.

- Live item: scholarship ordinance, second reading. Real PDF. Member Dizon already has a private note: “Section 7: twenty-slot floor for the smaller municipalities. Ask the sponsor whether the stipend is per semester.”
- Quorum required is a simple majority of the seated count (8 of 14). Before Dizon opens the floor, present-plus-late is 7. On-official-business does not count. Quorum is not declared.
- No motion is pending, so “Raise a motion” happens on camera. The member has no text form. The secretariat types the spoken wording.
- Live transcript status is `processing`: attributed debate, three unassigned mixer lines, one Gallery line, one low-confidence line (0.34). The 37th transcript is `completed`, with a Gallery line, a low-confidence line, and one secretariat correction (“as I mended” → “as amended”).
- 37th minutes are status `ai-draft`, body banner “AI-GENERATED DRAFT — REQUIRES SECRETARIAT REVIEW”, chip label **AI Draft**. Official tallies match the ballots: tricycle 9-2-1, commendation 11-0-1. Approval is an action during capture, not a second seeded record.
- Three published measures on `/portal`: DRRM plan ordinance, sports-facility fees ordinance, and the PDRRMO commendation resolution. Unpublished records 404.
- Audit register intro: “Append-only hash-chained audit trail.” A short linked trail is seeded (adjournment, vote closed 9-2-1, AI draft, publication).
- Electronic voting copy on the floor: “Electronic voting is for deliberation support only and is not automatically legally binding under this organization's rules.”
- Offline banner: “Offline — cached agenda and documents remain available.” A queued ballot reads “:count ballot(s) waiting to sync.”

## Highlights, ranked, with a showable benefit

1. **Member floor** (`/sessions/{id}/floor/member`, `resources/js/pages/Sessions/Floor/BoardMember.tsx`). Benefit: the measure, the motion, and the ballot are on the bench.
2. **Reading and private notes** (`MemberReadingPane`, `MemberActionRail`). Benefit: read it, mark a passage, keep a note that never reaches the hall board or the portal.
3. **Hall board** (`/sessions/{id}/floor/dashboard`, `Dashboard.tsx`, `ChamberDocument`). Benefit: the official PDF fills the wall, then the tally replaces it. A vote outranks a projected document.
4. **Secretariat console** (`Secretariat.tsx`, `console.tsx`). Benefit: roll, agenda, voting open/close, spoken motion text, and “View document” are one desk.
5. **Quorum indicator** (`QuorumCard.tsx`). Benefit: the count updates when a member opens the floor. The screen never says proceed. The officer decides. Only `present` and `late` count.
6. **Raise a motion** (`FloorRecognitionDock.tsx`). Benefit: one tap. The dock appears on the member floor, the secretariat console, and the hall board.
7. **Live vote**. Benefit: ballots tick on the wall. Tallies are advisory unless binding mode is on. This film does not turn binding mode on.
8. **Transcript** (`/sessions/{id}/transcript`, `Transcript.tsx`). Benefit: the debate is on the record before adjournment. It is not the vote and not the minutes.
9. **Session assistant** (`SessionAssistantPanel.tsx`). Benefit: context for the current item, with the subtitle “Verify all AI output against official records.” Do not record `/sessions/{id}/assistant` (JSON only).
10. **Draft minutes** (`Minutes/Show.tsx`). Benefit: a labeled draft a person accepts, edits, and approves. AI never finalizes. Numbers come from `votes`, not audio.
11. **Compare** (`/ai/compare`). Benefit: two measures side by side, with the instruction to verify against the record. Chosen over Ask AI because no cited conversation is seeded, and this film does not call a live model.
12. **Offline ballot** (`OfflineIndicator.tsx`, `useOfflineVoteQueue.ts`). Benefit: the ballot is not lost. Do not say it always works offline. PWA scope is the session floor only.
13. **Audit log** (`/audit`). Benefit: the register says entries are hash-chained and cannot be edited in the application.
14. **Portal** (`/portal`). Benefit: a published ordinance is public. An unpublished one is absent.

## Look

Order of Business (`DESIGN.md`): Space Grotesk, graphite and white, national blue `#0038a8` for action, national red `#ce1126` for live and urgent facts only. Light and dark parity. The hall board is captured in both.

## Claim limits

Do not claim time saved, RA 10173, e-document certification, legally binding votes, a real province, real officials, or real ordinance text. Do not say AI votes, declares quorum, writes official minutes, or publishes. Do not say transcription is binding, perfectly accurate, or a substitute for the roll or the ballots.

`.env` on this machine has `AI_ENABLED=true` and `TRANSCRIPTION_DRIVER=whisper`. `.env.example` has `TRANSCRIPTION_DRIVER=null`. Providers were not tested. The transcript on screen is seeded text, not a live capture from the microphone. The session assistant panel uses stored text or an excerpt of the measure, not a generated verdict. Do not press “Regenerate AI draft” or Ask AI during capture.

## Capture constraints

`marketing/demo-env.sh` exports a different body: Sangguniang Bayan, short name SB, locality Municipality of Malalag. The film and `MarketingDemoContent` use Sangguniang Panlalawigan, SP, Province of Demo. After sourcing that file, override the three `SENTRIA_ORG_*` variables before seeding and before `php artisan serve`, or the chrome and the PDFs will name the other body.

Session ULIDs are assigned at seed time and are not in the repo. When `marketing/capture_snapshots.js` is run, it writes them to `marketing/snapshots/session-ids.json`. Until then every floor, transcript, and minutes route in the shot list uses `{live}` for the 38th Regular Session and `{past}` for the 37th.

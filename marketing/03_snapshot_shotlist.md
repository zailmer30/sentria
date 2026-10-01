# Snapshot and recording shot list

Capture has not been run. Session ULIDs are created by `MarketingDemoSeeder` and are not in the repo.

When the script runs it writes the ids it used to `marketing/snapshots/session-ids.json`:

| Sitting | Title | Route token | Id |
| --- | --- | --- | --- |
| Live floor | 38th Regular Session | `{live}` | pending capture |
| Adjourned record | 37th Regular Session | `{past}` | pending capture |

App base: `http://localhost:8001` after `source marketing/demo-env.sh`, with the organization override in the capture-script header. Password for every demo account is `password`.

Do not capture `/sessions/{live}/floor/presiding`. It redirects to the member floor.

Theme is light unless the row says dark. Hall rows exist in both.

Footage tags:

- **Edit** — used directly in the cut.
- **Fallback frame** — Flow “Frames to Video” start frame if the recording cannot be used.
- **Ingredient** — not used. B-roll keeps screens out of focus, so no tablet frame is fed to Flow as a readable screen.

| # | File | Route | Role | Already on screen | Theme | Still or recording | Highlight or zoom | Tag |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | `snapshots/quorum-short.png` | `/sessions/{live}/floor/secretariat` | secretariat | 38th. Quorum not met. Present plus late is one short. Dizon has not opened the floor. On-official-business does not count. | light | still, before member login | Quorum card | Edit, fallback frame for Scene 8 |
| 2 | `recordings/rec-quorum-checkin.webm` | same, while member opens `/sessions/{live}/floor/member` | secretariat watching, member acts | Chip changes to Quorum met. No declare control is pressed. | light | recording | The chip and the count | Edit |
| 3 | `snapshots/quorum-met.png` | `/sessions/{live}/floor/secretariat` | secretariat | Quorum met, still undeclared by an officer | light | still | Quorum card | Edit |
| 4 | `snapshots/member-floor-clean.png` | `/sessions/{live}/floor/member` | member | Scholarship ordinance in the center pane. Agenda rail. Yes / No / Abstain / Inhibit. Presiding officer named in the header. | light | still | Wide floor, then the live item | Edit, fallback frame for Scenes 5 and 6 |
| 5 | `recordings/rec-member-highlight.webm` | same | member | Highlight drawn on a passage in EmbedPDF. Private to this member. | light | recording | The stroke landing, then the saved state | Edit |
| 6 | `snapshots/member-floor-highlight.png` | same | member | Highlight visible | light | still | The marked passage | Edit, fallback frame for Scene 7 |
| 7 | `recordings/rec-member-notes.webm` | same | member | My notes opens. Seeded note: “Section 7: twenty-slot floor for the smaller municipalities. Ask the sponsor whether the stipend is per semester.” | light | recording | Notes panel beside the PDF | Edit |
| 8 | `snapshots/member-notes.png` | same | member | Note open | light | still | The note, not the hall | Edit, fallback frame for Scene 7 |
| 9 | `snapshots/secretariat-console.png` | `/sessions/{live}/floor/secretariat` | secretariat | Roll, current item, Open voting, View document | light | still | Upper controls and the current item | Edit, fallback frame for Scene 6 |
| 10 | `snapshots/secretariat-pdf-sent.png` | same | secretariat | After View document. “On screen” for the hall document. | light | still | View document control | Edit |
| 11 | `recordings/rec-hall-document.webm` | `/sessions/{live}/floor/dashboard` | hall is signed in as secretariat (the board is not a second account) | Official scholarship PDF fills the wall. No member annotations. | light | recording | Page arrival | Edit |
| 12 | `snapshots/hall-document-light.png` | same | secretariat | Document stage | light | still | The projected PDF | Edit, fallback frame for Scenes 6 and 9 |
| 13 | `snapshots/hall-document-dark.png` | same | secretariat | Document stage | dark | still | The projected PDF | Edit, spare for the hall |
| 14 | `recordings/rec-member-motion.webm` | `/sessions/{live}/floor/member` | member | Raise a motion. No text form. | light | recording | The button, then the dock | Edit, pre-roll |
| 15 | `snapshots/member-motion-dock.png` | same | member | Dock: member seeks the floor | light | still | The dock | Edit |
| 16 | `recordings/rec-hall-motion.webm` | `/sessions/{live}/floor/dashboard` | secretariat session, member has tapped | Dock on the wall over the PDF | light | recording | Dock appearing | Edit |
| 17 | `snapshots/hall-motion-light.png` | same | secretariat | Motion stage | light | still | Dock | Edit, fallback frame for Scene 10 |
| 18 | `snapshots/hall-motion-dark.png` | same | secretariat | Motion stage | dark | still | Dock | Edit |
| 19 | `snapshots/secretariat-motion-form.png` | `/sessions/{live}/floor/secretariat` | secretariat | “Record the motion as spoken.” Typed line, not submitted in the still; the recording submits it. | light | still | The spoken-motion field | Edit |
| 20 | `recordings/rec-offline-sync.webm` | `/sessions/{live}/floor/member` | member, voting already opened by secretariat | Offline banner, queued Yes, then sync | light | recording | Banner and the Yes control | Edit |
| 21 | `snapshots/member-offline-queued.png` | same | member | “Offline — cached agenda and documents remain available.” “1 ballot(s) waiting to sync.” | light | still | Banner and queued Yes | Edit, fallback frame for Scene 18 |
| 22 | `snapshots/member-synced.png` | same | member | Banner clear, ballot recorded | light | still | Vote bar | Edit |
| 23 | `recordings/rec-hall-vote.webm` | `/sessions/{live}/floor/dashboard` | secretariat | PDF yields to the tally. Yes steps when the ballot syncs. | light | recording | The stage change and the Yes figure | Edit |
| 24 | `snapshots/hall-vote-light.png` | same | secretariat | Vote stage | light | still | Tally | Edit, fallback frame for Scene 11 |
| 25 | `snapshots/hall-vote-dark.png` | same | secretariat | Vote stage | dark | still | Tally | Edit |
| 26 | `snapshots/transcript-live-feed.png` | `/sessions/{live}/transcript` | secretariat | Processing. English debate. Unassigned mixer lines, one Gallery line, one low-confidence line. | light | still | The feed, titles cropped in the edit | Edit, fallback frame for Scene 12 |
| 27 | `snapshots/transcript-unassigned.png` | same | secretariat | “Will the sponsor yield…” with speaker not identified | light | still | That row | Edit |
| 28 | `recordings/rec-transcript-assign.webm` | same | secretariat | Assign that line to a seated member. Wait until the name replaces “Speaker not identified.” | light | recording | The row | Edit |
| 29 | `snapshots/transcript-assigned.png` | same | secretariat | Speaker name on that row | light | still | The name | Edit |
| 30 | `snapshots/transcript-low-confidence.png` | same | secretariat | Funding question, low confidence, unverified | light | still | The unverified mark | Edit |
| 31 | `snapshots/transcript-37-correction.png` | `/sessions/{past}/transcript` | secretariat | Completed. Corrections tab. “as I mended” to “as amended.” Gallery line and a low-confidence line also exist. | light | still | The correction | Edit, fallback frame for Scene 14 |
| 32 | `recordings/rec-transcript-correct.webm` | same | secretariat | Open that corrected segment so the before and after are on screen | light | recording | The edit history | Edit |
| 33 | `recordings/rec-assistant-open.webm` | `/sessions/{live}/floor/member` | member | Session assistant panel opens. Do not record `/sessions/{live}/assistant`. Do not submit search. | light | recording | Panel opening | Edit |
| 34 | `snapshots/member-assistant.png` | same | member | Subtitle “Verify all AI output against official records.” Current item visible. Search empty. | light | still | Subtitle and current item | Edit, fallback frame for Scene 15 |
| 35 | `snapshots/minutes-draft.png` | `/minutes/{minutesId}` | secretariat | 37th minutes, chip AI Draft, banner, tricycle 9-2-1, commendation 11-0-1 | light | still | Banner and the two tally rows | Edit, fallback frame for Scene 16 |
| 36 | `recordings/rec-minutes-approve.webm` | same, then edit, then presiding officer approves | secretariat, then presiding | Accept draft, save an edit, presiding presses Approve. Do not press Finalize or Regenerate AI draft. | light | recording | Status chip through Approval | Edit |
| 37 | `snapshots/minutes-approved.png` | `/minutes/{minutesId}` | presiding | Status Approval. Same ballot numbers. | light | still | Chip and tallies | Edit, fallback frame for Scenes 16 and 20 |
| 38 | `recordings/rec-ai-compare.webm` | `/ai/compare` then `/ai/compare/result` | secretariat | Scholarship ordinance against the tricycle ordinance | light | recording | The result columns | Edit |
| 39 | `snapshots/ai-compare.png` | `/ai/compare/result` | secretariat | Section or line changes, verify subtitle | light | still | The two columns | Edit, fallback frame for Scene 17. If the result page has no diff, mark this row **NEEDS DEMO CONTENT** |
| 40 | `snapshots/audit-chain.png` | `/audit` | secretariat | Intro “Append-only hash-chained audit trail.” Short linked trail. | light | still | The intro and the first rows | Edit, fallback frame for Scene 19 |
| 41 | `snapshots/portal-home.png` | `/portal` | signed out | Three published measures: DRRM plan ordinance, sports-facility fees ordinance, PDRRMO commendation | light | still | Recently published | Edit, fallback frame for Scene 21 |
| 42 | `snapshots/portal-ordinance.png` | `/portal` document | signed out | One published ordinance open | light | still | Title and abstract | Edit, fallback frame for Scene 21 |

Presiding officer footage for the recap is the member floor after `presiding@sentria.test` signs in (`snapshots/presiding-same-floor.png`, same route as row 4). It is the same page. Capture it. Do not open `/floor/presiding` as its own shot.

## Order of operations

The script follows this order because each step changes the database.

1. Secretariat finds the 38th and the 37th and writes their ids.
2. Quorum still short, then the member opens the floor.
3. Clean PDF, highlight, My notes, assistant panel.
4. Secretariat sends the PDF. Hall, light and dark.
5. Member raises a motion. Hall dock, light and dark. Secretariat types the spoken line and submits it.
6. Secretariat opens voting. Member goes offline, queues Yes, returns, syncs. Hall records the tally, light and dark.
7. Transcript assignment on the 38th. Correction proof on the 37th.
8. Minutes draft, accept, edit, approve. No finalize. No regenerate.
9. Compare. Audit. Signed-out portal.

## NEEDS DEMO CONTENT

Nothing in the seed is missing for the rows above. Two captures are conditional:

- **Highlight.** Annotations are not seeded. The script tries the EmbedPDF highlight control. If that control is not in the page, `member-floor-highlight.png` is still saved from the clean page and the report flags **NEEDS DEMO CONTENT** for the stroke itself.
- **Compare result.** If `/ai/compare/result` renders without a diff, flag `ai-compare.png` as **NEEDS DEMO CONTENT**. Do not fall back to Ask AI. No cited conversation is seeded, and the providers were not tested.

Do not submit the assistant search. Do not press Regenerate AI draft.

## Assumptions for the edit

The 38th is the floor. The 37th is the transcript correction and the minutes. Close-ups must exclude session titles and dates so they read as one sitting. The live wall shows one ballot moving, not 9–2–1. Those figures appear only on the minutes page.

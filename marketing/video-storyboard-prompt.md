# Sentria marketing video prompt

Ready-to-run prompt for a 4–6 minute marketing film. Paste everything below the line into an agent that can read this repo and write the output files.

---

## Role

You are a senior product marketer, video storyboard artist, and prompt engineer for AI video tools. You are working inside the Sentria codebase. Analyze the product, then produce a hook-driven storyboard for a marketing video.

Production is split in two:

- **Chamber b-roll** (people, paper, tablets in hands, the hall) is generated in Google Flow (Veo). Every b-roll clip gets a ready-to-paste Flow prompt.
- **Product UI** is real footage: Playwright screen recordings and screenshots of the running app, with pans and zooms added in the edit. Veo does not reliably preserve UI text, and this film depends on exact tallies, names, and labels. Each UI clip still gets a Flow prompt, marked as a fallback only.

## Product context (already filled — do not invent a different product)

- **System name:** Sentria
- **What it does:** Paperless legislative session and records system for one Philippine LGU legislative body (Sangguniang Panlalawigan / Panlungsod / Bayan).
- **Tagline (Filipino, use only this):** Sistemang Paperless para sa Sesyong Pambatas
- **Target audience:** The secretariat and the presiding officer are the buyers. Board members on the floor are the moment of use. The public portal is the outcome.
- **Biggest pain point:** Paper agenda packets, a shouted roll call, vote tallies on a whiteboard, and minutes plus publication handled as separate handoffs after the session.
- **Tone:** Authoritative, restrained, precise. Institutional gravitas. Not startup-energetic.
- **Video language:** English narration. Filipino only for the existing tagline.
- **Aspect ratio:** 16:9, 1920×1080, for a landing page or YouTube.
- **Call to action:** Request a demo. No public URL is defined in the repo — use a clearly marked placeholder and list it under assumptions.
- **Visual world:** Order of Business. Graphite and white surfaces, Space Grotesk, Philippine national blue (`#0038a8`) for action, national red (`#ce1126`) for live and urgent facts only. Light and dark parity. See `DESIGN.md`.
- **Organization on screen:** Placeholders only — name "Sangguniang Panlalawigan", short name "SP", locality "Province of Demo". No real LGU seal, coat of arms, or elected officials.

## Claim limits (hard)

Quantify only what the seeded demo can show on screen. Do not invent ROI ("save 10 hours a week"), compliance, or legal effect.

Do not claim:

- RA 10173 certification, e-document certification, or any legal compliance badge
- Legally binding electronic voting (`SENTRIA_ELECTRONIC_VOTING_BINDING` defaults to false; the UI says tallies are advisory unless binding mode is on)
- A real province, real officials, or a real ordinance text
- That AI votes, declares quorum, writes official minutes, or publishes
- That transcription is legally binding, perfectly accurate, or a substitute for the roll call or the ballots

If `AI_ENABLED` is false, or `TRANSCRIPTION_DRIVER` is `null` (the `.env.example` default), show the real screen and say "when transcription is enabled." Do not fake a live provider.

## Must highlight

These are not optional. If the scene budget is tight, cut document-admin screens before cutting any of these.

The **session floor** is the spine of the video, not one feature card. Shoot three product screens. The presiding officer uses the same member floor as a board member: `/sessions/{id}/floor/presiding` redirects to `/sessions/{id}/floor/member`. Do not storyboard a separate presiding console. The officer still appears as a person in the chamber and as the name in the header. Their distinct later action is approving minutes, off the floor.

- Board member tablet: `/sessions/{id}/floor/member` — agenda rail, live item, PDF in the center, sticky Yes / No / Abstain / Inhibit bar, Raise a motion. File: `resources/js/pages/Sessions/Floor/BoardMember.tsx`. Logging in as `presiding@sentria.test` lands on this same page. The only extra detail is the named vote roll. That is not its own scene.
- Secretariat console: `/sessions/{id}/floor/secretariat` — roll call, agenda advance, voting open/close, motion wording, and sending the official PDF to the hall board. File: `resources/js/pages/Sessions/Floor/Secretariat.tsx` and `resources/js/pages/Sessions/Floor/console.tsx`.
- Hall board: `/sessions/{id}/floor/dashboard` — a projector for the whole chamber, not a second tablet. File: `resources/js/pages/Sessions/Floor/Dashboard.tsx`.

**Member reading**, on the member tablet only. One scene, two beats:

- The measure opens in the center pane (EmbedPDF). Show a highlight landing on a passage. Markup is private to that member and saves automatically.
- Then **My notes**, owner-only, beside the PDF. One short note. It is not the transcript, not the minutes, and not visible on the hall board or the portal.
- Benefit: "Read it, mark it, and keep a private note, without leaving the floor."

**Hall board**, three stages on `/sessions/{id}/floor/dashboard`, in this order:

1. Document stage: the secretariat sends the measure, and the official PDF fills the wall (`ChamberDocument`).
2. Motion: a member raises a motion, and the recognition dock appears on the same board.
3. Vote: the tally replaces the PDF. A vote outranks a projected document, so the PDF is the shot before the tally, not during it.

The projected PDF is the official measure, never a member's annotations or private notes.

**Live transcription** is its own scene pair, tied to the floor:

- Route: `/sessions/{id}/transcript` — `resources/js/pages/Sessions/Transcript.tsx`
- What to show: lines appearing during debate; an unassigned mixer line; secretariat assigning the speaker to a member or to Gallery; a low-confidence line marked unverified; a human correcting one segment (`PATCH /sessions/{session}/transcript/{transcript}/segments/{segmentIndex}`).
- Benefit line: "The debate is on the record before adjournment."
- Hard limit: the transcript is not the vote and not the minutes. Official tallies come from the ballots.

**AI** is a chapter, always with a human in the loop. Show three beats, in this order:

1. Session assistant — the `SessionAssistantPanel` (`resources/js/components/session/SessionAssistantPanel.tsx`), opened from the member action rail on the member floor, or from the secretariat console. It shows context for the current agenda item, a search over this session's records, and the subtitle "Verify all AI output against official records." `/sessions/{id}/assistant` is a JSON endpoint behind the panel — do not screenshot or record it directly.
2. Draft minutes after adjournment — `POST /minutes/{minute}/generate-draft`, shown on the Minutes pages (`resources/js/pages/Minutes/`). Label it DRAFT. A person edits, then approves. AI never finalizes. Official vote numbers in the minutes match the `votes` table, not the audio.
3. One supporting beat only: Ask AI at `/ai` (`resources/js/pages/Ai/Index.tsx`) with a citation, or compare at `/ai/compare` (`resources/js/pages/Ai/Compare.tsx`). Do not demo every AI tool.

Also give one full scene each to:

- **Official PDF on the hall board.** The document stage, before the vote. Member buttons are not this shot.
- **Live vote on the hall board.** Member buttons are the cause; the dashboard tally replacing the PDF is the picture.
- **Quorum without a declaration.** The indicator updates when a member opens the floor. On-screen text stays "The officer decides." Only `present` and `late` count. `on-official-business` does not.
- **Raise a motion.** A member taps once. The dock appears on the member floor, the secretariat console, and the hall board. The secretariat types what was spoken. There is no member text form.
- **Offline ballot.** The banner, a queued Yes, then one sync when the network returns. Say "the ballot is not lost," not "it always works offline." PWA scope is the session floor only.

The **public portal** appears once, in the Proof section, not as its own feature scene. Only a published ordinance or resolution on `/portal`. Unpublished records are simply absent (404, not a locked page).

Skip user admin (`/users`, `/roles`), backups, and the full document state machine. Those screens exist but are not part of this story — do not shoot them.

## Where each highlight sits

| Moment | What the viewer sees | Why it is there |
| --- | --- | --- |
| Hook and problem | Paper packets, a shouted roll call, a tally on a whiteboard | Sets up the floor without showing the product |
| Turning point | Match cut into the member tablet as the session goes live | First product frame is the floor, not a logo |
| Chapter: On the floor | Member tablet: highlight a passage, then a private note. Secretariat sends the official PDF to the hall. Motion dock, then the tally replaces the PDF | Biggest wow, first in the showcase |
| Re-hook (2:40) | "The vote is cast. The debate still has to be captured." | Bridges into transcription |
| Chapter: The record writes itself | Transcript lines landing, speaker assigned, one correction | Second re-engagement, around the midpoint |
| Chapter: A person still decides | Session assistant, then the draft minutes a secretary accepts | AI climax in the last third, after the human result is obvious |
| Chapter: Still there when the network isn't | Offline ballot queue, then the hash-chained audit log | Short supporting beats, one scene each |
| Proof | Quick recap of the sitting, then approved minutes and a published item on `/portal` | One session, end to end |

## Runtime (critical)

- Total video length must be between 4:00 and 6:00. Target 5:04.
- Every clip is exactly 8 seconds, so every section and scene boundary must land on a multiple of 8 seconds.
- **Before writing any scene prose, publish a clip budget table** that sums to exactly **38 clips × 8 seconds = 5:04**. Columns: scene, chapter, clip count, start, end. Do not start writing prompts until that table sums correctly.
- Group clips into scenes (1–3 clips per scene).
- Include a running-time tally in the storyboard and confirm the total is 5:04.

Section budget (fixed — distribute scenes inside these):

| Section | Start | End | Clips |
| --- | --- | --- | --- |
| Hook | 0:00 | 0:16 | 2 |
| Problem | 0:16 | 0:48 | 4 |
| Turning point / reveal | 0:48 | 1:12 | 3 |
| Quick overview | 1:12 | 1:36 | 3 |
| Showcase — On the floor | 1:36 | 2:40 | 8 |
| Showcase — The record writes itself | 2:40 | 3:12 | 4 |
| Showcase — A person still decides | 3:12 | 3:52 | 5 |
| Showcase — Still there when the network isn't | 3:52 | 4:16 | 3 |
| Journey and proof | 4:16 | 4:48 | 4 |
| CTA | 4:48 | 5:04 | 2 |
| **Total** | | **5:04** | **38** |

Suggested split inside "On the floor" (8 clips): member reading 2, quorum 1, official PDF on the hall board 2, raise a motion 1, live vote 2.

## Step 1 — Analyze the system

Scan routes, pages, components, models, `README.md`, `PRODUCT.md`, and `DESIGN.md`. Identify:

1. The core modules and the roles that appear in this film: administrator, secretariat, presiding officer, and board member. The public portal is an anonymous visitor, not a logged-in role. Committee chair, committee member, and legal/technical reviewer exist in the product and have nothing to show here — do not mention them, log in as them, or give them a scene.
2. The highlight list above, ranked for the film. Do not add features that the seeded demo cannot show.
3. The key screens that best show each highlight.
4. The UI look and feel so scenes stay consistent with the real product.

Save a short summary to `marketing/01_feature_analysis.md`. Translate each feature into a plain-language benefit. Benefits must be showable (a tally updating, a line attributed, a draft labeled DRAFT), not invented time-savings.

## Step 2 — Build the storyboard

Create `marketing/02_storyboard.md` using this narrative and the section budget above.

- **HOOK (0:00–0:16):** A pattern-interrupt. Use the chamber before: paper packets, a roll call that stalls, a whiteboard tally. No logo. No intro fluff. The first 3 seconds must feel like "that's our session."
- **PROBLEM (0:16–0:48):** Two or three mini-situations: lost page in the packet, a disputed count, minutes still unfinished the next day.
- **TURNING POINT / REVEAL (0:48–1:12):** Match cut to the member tablet on `/sessions/{id}/floor/member`. Name Sentria. One-line promise: the sitting, the vote, and the record in one system.
- **QUICK OVERVIEW (1:12–1:36):** Fast montage: member floor, secretariat console, hall dashboard with the official PDF on the wall. Three screens, not four roles.
- **FEATURE SHOWCASE (1:36–4:16):** Chapters in this order. The first clip of each chapter is its mini-hook. Do not spend a separate scene on it.
  1. **On the floor (1:36–2:40)** — member tablet (PDF highlight, then My notes), quorum, official PDF on the hall board, raise a motion, live vote as the tally replaces that PDF.
  2. **The record writes itself (2:40–3:12)** — live transcription, speaker assignment, human correction. Open with the re-hook "The vote is cast. The debate still has to be captured."
  3. **A person still decides (3:12–3:52)** — session assistant, draft minutes a secretary accepts, one Ask AI or compare beat. This is the climax in the last third.
  4. **Still there when the network isn't (3:52–4:16)** — one offline-ballot scene, then a short hash-chain audit beat.
- **JOURNEY AND PROOF (4:16–4:48):** One fast recap clip of the sitting, reusing footage already shot: a board member marks the PDF, the secretariat puts the official PDF on the hall board, the vote closes on the tally, the presiding officer at the rostrum on the same member floor. Then the result: an approved minutes record and a published item on `/portal`. Before state is paper and a whiteboard. After state is the member tablet, the secretariat console, the hall board, and the portal. No fake metrics. Private notes and PDF markup stay off the portal.
- **CTA (4:48–5:04):** Logo wordmark "Sentria", the Filipino tagline, "Request a demo", placeholder URL, one reinforcing line: the officer decides, the record remains.

Retention rules:

- Insert a re-hook every 45–60 seconds: a curiosity line or a teaser. Keep the register restrained. No "you won't believe this."
- Alternate chamber b-roll and UI frames so it never feels like a screen recording.
- The hall tally is the first wow. Transcription is the midpoint re-hook. The human accepting draft minutes is the climax.

For each scene, output this exact format:

```text
---
SCENE #: [title]   |   Chapter: [chapter name]
Timestamp: [start-end] | Running total: [mm:ss]
Clips: [number of 8s clips, 1-3]
Purpose: [hook / problem / reveal / feature / re-hook / journey / proof / CTA]
Feature highlighted: [feature and the benefit it delivers]
Production: [Flow b-roll / screen recording / screenshot + edit move], per clip
Visual description: [what the viewer sees: camera, setting, people, UI on screen]
System snapshot to use: [exact page/screen/component from this codebase, with file path and route]
On-screen text: [max 6 words, big and punchy]
Voiceover: [max 20 words per clip, conversational English]
Sound/Music cue: [e.g., beat drop, whoosh, click sound]
Transition to next scene: [e.g., match cut, screen zoom-in, swipe]
FLOW PROMPT (Clip A): [b-roll: full prompt. UI: fallback only — see Flow rules]
FLOW PROMPT (Clip B/C): [only if the scene has more than 1 clip]
---
```

## Step 3 — Capture the product footage

So the video shows the real product:

1. List every shot needed, in order, in `marketing/03_snapshot_shotlist.md` (route, user role, sample data already on screen, light or dark theme, still or recording, and what to highlight or zoom into). Plan multiple states for the member PDF (clean, then highlighted, then My notes open), the hall board (document stage, motion dock, vote stage), the vote, and the transcript. Do not capture `/sessions/{id}/floor/presiding` as its own shot; it redirects to the member floor. Record the actual session IDs you used at the top of the file.
2. Create a Playwright script at `marketing/capture_snapshots.js` that logs in with the existing demo accounts and produces:
   - 1920×1080 screenshots in `marketing/snapshots/`
   - 1920×1080 video recordings of each state change (tally ticking, transcript line landing, motion dock appearing, offline banner and sync) in `marketing/recordings/`, using a browser context with `recordVideo`
3. Use the seeded corpus from `MarketingDemoSeeder` (not `LegislativeContentSeeder`) and `UserSeeder`. Do not invent a second dataset and do not use real personal data. Password for every demo account is `password`:

   | Role | Email |
   | --- | --- |
   | System Administrator | admin@sentria.test |
   | Secretariat | secretariat@sentria.test |
   | Presiding Officer | presiding@sentria.test |
   | Board Member | member@sentria.test |

   Do not log in as `chair@sentria.test`, `committee@sentria.test`, or `legal@sentria.test`. Those roles are out of this film. The portal screenshots are signed out.

4. The app is Laravel Fortify plus Inertia. The script must submit the real login form, then wait for the Inertia page.
5. The floor, hall board, and transcript update over broadcast channels. Before each capture, wait for the realtime change to appear in the DOM (the tally number, the new transcript line, the dock), not just for page load.
6. Drive state through the real UI, with one browser context per role: the secretariat sends the PDF and opens voting, the member raises a motion and votes, the hall board watches. This changes the database, so the script's header comment must tell the operator to run it against a freshly seeded marketing database (`source marketing/demo-env.sh` then `php artisan migrate:fresh --seeder=MarketingDemoSeeder --force`).
7. Write the capture script, but do not run it, and do not install Playwright. Put the exact setup and run commands in the script's header comment and in the final chat report.
8. Never run `migrate:fresh`, `migrate:refresh`, `db:wipe`, `db:seed`, or any other command that writes to or resets the database. The dev database may be in active use.
9. Capture the hall board in both light and dark themes. It is projected in a hall.
10. Know what `MarketingDemoSeeder` puts on screen:
   - Floor screens use the live sitting titled **38th Regular Session**. The current item is the scholarship ordinance. Quorum is one member short and undeclared until `member@sentria.test` opens the floor. No motion is pending, so raise-a-motion happens on camera.
   - Every measure has a real PDF (DomPDF) and extracted text. Organization on screen is Sangguniang Panlalawigan / SP / Province of Demo.
   - The live transcript is `processing` with English debate lines: three unassigned mixer lines, one Gallery line, and one low-confidence line. The adjourned sitting (**37th Regular Session**) has a completed transcript with a Gallery line, a low-confidence line, and one secretariat correction.
   - The 37th sitting has AI draft minutes labeled DRAFT. Official tallies in that draft match the ballots: tricycle 9-2-1, commendation 11-0-1. Proof of *approved* minutes is an action during capture (accept / approve), not a second seeded record.
   - `member@sentria.test` already has a private note on the scholarship ordinance. PDF highlights are drawn during capture; they are not seeded (EmbedPDF native format).
   - Three enacted measures are published on `/portal`. The hash-chained audit register has a short linked trail.
   - Transcript, draft-minutes, and approved-minutes shots come from the 37th sitting; floor shots come from the 38th. In the edit it has to read as one sitting, so keep session titles and dates out of close-ups, and list this under assumptions.

   Do not create additional seeders. If a shot is still unreadable, mark it `NEEDS DEMO CONTENT` in the shotlist and report it as a blocker.

11. Note which footage is a Flow "Frames to Video" start frame or an "Ingredient" (for b-roll that includes a tablet screen) and which is used directly in the edit.

## Step 4 — Flow-ready export

Generate these files:

- `marketing/04_flow_prompts.txt` — all FLOW PROMPTS numbered in order (Scene 1 Clip A, Scene 1 Clip B, …), plain text, one per block. Prefix each with `[B-ROLL]` or `[UI FALLBACK]`.
- `marketing/05_flow_prompts.json` — array of `{scene, clip, timestamp, duration, source, prompt, snapshot_file, recording_file, voiceover, on_screen_text}`, where `source` is `"flow"` or `"screen_recording"`.
- `marketing/06_consistency_guide.md` — one reusable style block (character description, color palette, lighting, camera style, UI look). This block is prepended in Flow. It is not repeated inside every clip prompt.
- `marketing/07_voiceover_script.md` — the full voiceover as one continuous script with timestamps. Target 550–650 words for 5:04. That leaves room for silence under the hook, the tally, and the approval. Plus 3 alternative hook lines to A/B test.
- `marketing/08_edit_timeline.md` — clip-by-clip assembly order with timestamps, source (Flow or recording), transitions, pan and zoom moves on UI footage, music cues, and chapter title cards.

## Hook rules

- The first 3 seconds must create a "that's our session" reaction.
- Lead every feature scene with the benefit, then the screen.
- Use only outcomes the UI can show. No invented hours saved.
- A visual change every 2–3 seconds. No static screens. On UI footage, the change can come from the recording itself or from an edit move (push-in, pan, highlight).
- Prefer UI in motion (a cursor, a tally tick, a transcript line appearing) over talking.
- Every scene must earn its place. Do not drop below 4:00 or exceed 6:00. The budget target is 5:04.

## Google Flow constraints

- Each clip prompt stays under ~150 words, cinematic and concrete.
- One clear action per clip, max 8 seconds.
- **Chamber b-roll** is live-action: people, paper, tablets, the hall. Any screen visible in b-roll is out of focus or angled away. No readable UI generated from scratch. No real brand logos, no celebrities, no copyrighted music.
- **UI fallback prompts** describe motion only: camera move, cursor, highlight glow, light. Do not describe button labels, headings, or other readable UI text. Start each with: "Use the attached product frame as the locked image. Do not redraw the interface." These are used only if the screen recording cannot be used for that clip.
- Do not paste the consistency guide into each prompt. Keep continuity by prepending `06_consistency_guide.md` once per Flow job.
- People are unnamed Filipino adults in a provincial session hall and a secretariat office. No likeness of a real official.

## Final output in chat

After creating the files, report:

1. A summary table: Scene # | Chapter | Feature | Hook/Benefit | Clips | Timestamp | Source | Snapshot or recording file.
2. Confirmation of the total runtime and clip count (must be 38 clips, 5:04).
3. The commands to set up and run `capture_snapshots.js`, since it has not been run.
4. Assumptions and missing info: demo URL, CTA URL, whether `.env` enables AI and transcription (and that the providers were not tested), the session IDs used, which shots are marked `NEEDS DEMO CONTENT`, and where footage from different seeded sessions was edited to read as one sitting.

Do not ask questions before starting. Use this context. State assumptions at the end.

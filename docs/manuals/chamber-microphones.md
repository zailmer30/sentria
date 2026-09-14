# Chamber microphones — setup manual

Sentria records a sitting from the capture PC. **Mixer mix** is the usual setup: the hall mix goes into that PC, speech is split when the voice changes, and the secretariat names the speaker. **Per-seat** mapping is optional when ICT has a dedicated microphone line for every member.

This page is the operator walkthrough. Hardware notes and capture-daemon environment also live in [chamber-capture.md](../chamber-capture.md).

## Who does what

| Role | Work |
| --- | --- |
| ICT | Wire the mixer mix (or per-seat interface), identify channel numbers for per-seat, run the capture PC daemon |
| Secretariat or System Administrator | Choose the default capture mode, create seated-member accounts, fill `/settings/chamber-channels` when using per-seat, enable recording on the sitting, assign unassigned transcript lines |
| Presiding officer | Start / suspend / adjourn the sitting as usual |

Board members cannot open the mapping page.

## What you need

- A dedicated always-on capture PC on the same LAN as Sentria.
- **Mixer mix:** the mixer’s USB or analog main mix into that PC.
- **Per-seat:** one multi-channel audio interface the capture PC sees as a **single device** with a **shared clock**, plus a **direct out** from each seat mic.
- Active users marked as seated members (for the Member dropdown and the speaker picker).
- A recording starter from **Set up recording computer** on the mapping page (creates the capture key). The artisan command `php artisan sentria:chamber-token` is a fallback for ICT.
- Queue workers running on the Sentria server so transcript chunks process.

## What will not work

- A pile of separate USB microphones (clocks drift).
- Per-seat mapping while the PC only receives a mixed PA feed (every voice lands on one channel; use Mixer mix instead).
- Expecting mixer mix to produce two clean transcripts when two people talk at once. That overlap is one unassigned chunk; the secretariat assigns a speaker and can edit the text.
- Running `capture.py` inside WSL while the interface is plugged into Windows — WSL typically has no ALSA card and cannot see the device.

---

## Mixer mix (usual)

1. Plug the mixer mix into the capture PC.
2. On **Settings → Chamber microphones**, set **Default capture mode** to Mixer mix.
3. Download **Set up recording computer** and start the program on that PC.
4. On the session page, leave capture mode on Mixer mix, enable recording, and start the sitting.
5. Unassigned lines appear on the secretariat discussion inbox. Assign each line to a seated member or Gallery. Floor screens show “Speaker not identified” until then.

---

## Per-seat (optional)

Map each dedicated seat microphone to one channel on a single multi-channel audio interface, then to one seated member in Sentria. When two members speak at once, the transcript must show two speakers.

### Step 1 — Connect every seat mic to one interface

1. Confirm each board-member mic has a **direct out** (analog) or a **Dante/AES67 channel**. The hall mix alone is not enough.
2. Plug those outputs into **one** interface. Do not split seats across two USB boxes.
3. Connect that interface to the capture PC with a single USB/Thunderbolt/Dante Virtual Soundcard session.
4. Label the physical jacks: Seat 1 → interface input 1, Seat 2 → input 2, and so on. Keep one spare input for **gallery / resource / public hearing**.
5. Acceptance at the mixer: two people talk at once; **two different channels must meter independently**. If they do not, stop here — Sentria cannot split a mix.

## Step 2 — Find which number belongs to which seat

On the Chamber microphones page, click **Check this computer**. Allow the browser to use the microphone. Sentria lists audio inputs on the PC you are signed in on — you do not run a terminal command.

If the audio box is on the dedicated recording computer, start the capture program there. The same page shows that computer’s box and how many inputs it has.

Ignore headphones, HDMI TVs, and Bluetooth headsets. Prefer the device with many inputs. Choose it in the **Device** dropdown, then use **Test** on a row while you tap that seat.

## Step 3 — Ensure seated members exist

1. Open **Users**. Each person who will be attributed on a seat must be **active** and **seated member**.
2. The mapping page Member list is only those users. If the list is empty, create or update accounts first.

## Step 4 — Map channels in Sentria

Sign in as Secretariat or System Administrator. Open **Settings → Chamber microphones** (`/settings/chamber-channels`).

1. Click **Add channel** once per physical input you will transcribe.
2. Fill the row:

| Field | What to enter |
| --- | --- |
| **Channel** | Integer 1–128. Must match the interface input from Step 2. Each row unique. |
| **Device** | The audio box on the recording computer. Every row uses the same box. Start the recording program so the list appears, then pick it. |
| **Member** | The seated member for that mic. Leave blank for gallery / resource / public table. |
| **Label** | Optional display name, e.g. `Seat 1`, `Presiding`, `Gallery`. |
| **Active** | On for seats to capture this term. Off to keep the row without sending audio. |

3. Add a gallery row with member blank if hearings use a resource table.
4. Click **Save mapping**.

The map is global. Starting a sitting **snapshots** the active map onto that session’s transcript. Later edits do not rewrite speakers already recorded on an earlier sitting.

## Step 5 — Give the recording computer access

On **Settings → Chamber microphones**, click **Set up recording computer**. Sentria creates a recording key (the previous key of the same name stops working) and downloads `sentria-chamber-recording.zip`.

Copy that folder only onto the chamber PC. Do not email or print it. The key cannot sign in to the website.

ICT can still rotate a key from the server with `php artisan sentria:chamber-token` if the zip download is unavailable.

## Step 6 — Start the recording program

On the capture PC:

1. Unzip the starter. Double-click `start.bat` (Windows) or run `./start.sh` (Linux).
2. On Windows, the first run downloads a portable Python if this PC does not already have a real one (the Microsoft Store shortcut does not count). That needs internet once.
3. Leave that window open. The mapping page **Recording computer** column fills in once the program is heartbeating.

The process should print the device name and channel count. It polls `GET /api/chamber/recording` every few seconds and switches to the **Device** chosen on Chamber microphones.

Optional: `CHAMBER_DEVICE` (device name or index, used only when Settings has no device selected) and `CHAMBER_VAD_RMS` (default `0.012`) can be set in the start script if a neighbour mic still triggers.

Manual equivalent from a Sentria checkout (only if you did not use the zip):

```powershell
cd services/chamber-capture
python -m venv .venv
.\.venv\Scripts\Activate.ps1
pip install -r requirements.txt

$env:SENTRIA_URL = "https://sentria.lgu.example"
$env:SENTRIA_CHAMBER_TOKEN = "<token>"
python capture.py
```

## Step 7 — Enable recording and start the sitting

1. On the session page, set capture mode to **Per-seat**, then **Start recording**.
2. Start the sitting (in-session). Capture starts only when status is in-session **and** recording is on.
3. Keep Sentria queue workers running (`php artisan queue:listen` or Horizon) so chunks transcribe.
4. Suspend pauses capture (chunks return 409). Adjourn stops it. Disable recording to keep the sitting live without per-seat audio.

Speech-to-text is configured on the **server** (`TRANSCRIPTION_DRIVER` in `.env`), not on this mapping page. Language is auto-detected (English / Tagalog / Cebuano). Secretariat correction remains required. Recordings are never auto-published.

## Step 8 — Prove barge-in

1. Member 1 speaks and keeps speaking.
2. Member 2 starts. Both channels should upload; the live transcript should name **two** mapped members, not one mixed speaker.
3. Speak only on seat 1: seat 2 should not produce segments (raise `CHAMBER_VAD_RMS` if it does).
4. Confirm suspend / adjourn / disable recording behave as in Step 7.

## If something fails

| Symptom | Likely cause |
| --- | --- |
| No devices / only headphones | Interface not attached to this OS; WSL cannot see Windows USB audio |
| Both voices one speaker | Mixed PA feed, or both seats wired to the same input |
| Empty Member dropdown | No active seated-member users |
| Forbidden on this page | Signed in as board member |
| Capture stays idle | Sitting not in-session, or recording disabled |
| Chunks 409 | Suspended, adjourned, or recording off |
| Token 403 | Wrong ability; download a new starter from **Set up recording computer** |
| Audio files vanish, text remains | Daily prune of raw `sessions/{id}/chamber/` after `CHAMBER_AUDIO_RETENTION_DAYS` (default 30) |

## Related

- [chamber-capture.md](../chamber-capture.md) — daemon, STT drivers, retention
- [deployment.md](../deployment.md) — LAN architecture
- [administrator.md](administrator.md) / [secretariat.md](secretariat.md)

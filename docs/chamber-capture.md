# Chamber capture

Sentria records a sitting in one of two feeds. The default is the **mixer mix**: the capture PC takes the hall mix, speech-to-text splits when the voice changes, and the secretariat assigns who spoke. **Per-seat** capture remains available when ICT wires a dedicated mic per member into one multi-channel interface.

A pile of USB mics will drift off a shared clock. Do not use that.

## Mixer mix (default)

1. Feed the mixer’s main mix (USB stereo/mono, or analog into the capture interface) into the dedicated capture PC.
2. Leave capture mode on **Mixer mix** (Chamber microphones default, or the sitting’s recording controls).
3. Start the sitting with recording enabled. The daemon mixdowns the device to one stream, runs voice-activity detection, and uploads chunks as channel 1.
4. ElevenLabs Scribe diarization (batch on those chunks) splits turns when the speaker changes. Whisper/Qwen hops do not diarize; each silence gap is one unassigned utterance.
5. Lines land as **Unassigned** on authorized floor screens. The secretariat picks a seated member or Gallery. The name updates live.

**Overlap:** two people talking at once on a mixed bus become **one** chunk. Diarization does not unmix voices. The clerk assigns a speaker and can edit the text.

## Per-seat (optional)

1. Confirm each board member mic has a **direct out** (analog) or a **Dante/AES67 channel** — not only the hall mix.
2. Connect those outputs to **one** interface the capture PC sees as a single N-channel device (ALSA / ASIO / Core Audio / Dante Virtual Soundcard).
3. Label **channel index ↔ physical seat**. That map is entered in Sentria at `/settings/chamber-channels`. Full walkthrough: [manuals/chamber-microphones.md](manuals/chamber-microphones.md).
4. Keep a **gallery / resource** channel for public and committee hearings.
5. Set the sitting (or the chamber default) to **Per-seat**.

Acceptance test for per-seat: two people talk at once; two different channels must meter independently; the transcript names two mapped members.

## Capture PC

Dedicated always-on machine in the chamber, wired to Sentria. Two producers can feed it; both cut the same chunks and post to the same endpoint, so the transcript is identical either way.

### Browser capture (preferred)

Open **the sitting → Recording** (`/sessions/{session}/capture`) on the chamber PC, pick the mixer feed, press **Start recording**, and leave the tab open. No install, no token, no zip: the operator is already signed in, and an AudioWorklet runs the voice-activity detector in the page.

- Requires a secure context. Serve Sentria over `https://`, or reach it as `http://localhost:8000` on the capture PC itself. A LAN IP over plain HTTP will not get microphone access.
- The **browser window** is the recorder, not the page. Capture is held outside React, so the clerk can move between the console, minutes and transcript without interrupting it; a banner on every screen shows it running and can stop it. Closing the tab, reloading, or letting the machine sleep does stop it. The page holds a screen wake lock and warns before unload, but the PC still needs its sleep timers off.
- One recording at a time. Opening the console for a second sitting while one is running shows its status rather than retargeting the microphone.
- Only mixer mix. Browsers do not reliably expose more than two input channels, so per-seat capture stays on the daemon.
- The sequence cursor lives on the server, so a reload — or an operator taking over mid-sitting — resumes without replaying chunks.
- Detector settings are served to the page from `sentria.chamber.*`, so the browser and the daemon stay on one source of truth. The worklet itself is `public/audio/chamber-vad.worklet.js`.

### Capture daemon (per-seat, or headless)

Still supported, and still the only option for per-seat. Prefer **Settings → Chamber microphones → Set up recording computer** (downloads a zip with `start.bat` / `start.sh` and a fresh token). On Windows, the first `start.bat` run fetches a portable Python if the PC does not already have one. Manual equivalent:

```bash
cd services/chamber-capture
python3 -m venv .venv
. .venv/bin/activate
pip install -r requirements.txt

export SENTRIA_URL=https://sentria.lgu.example
export SENTRIA_CHAMBER_TOKEN=...   # from the starter, or php artisan sentria:chamber-token
export CHAMBER_DEVICE=             # optional fallback if Settings has no device selected
python capture.py
```

Issue a token (machine user cannot log into the Inertia app):

```bash
php artisan sentria:chamber-token
```

Browser capture uses the session-cookie equivalents of the same three calls — `GET /sessions/{session}/capture/state`, `POST /sessions/{session}/capture/heartbeat` and `POST /sessions/{session}/capture/chunks` — all gated by the `manageRecording` policy. The heartbeat feeds the same level meter in Chamber microphones, so the settings page shows the browser exactly as it shows the daemon.

The daemon **polls** `GET /api/chamber/recording`. The payload includes `feed` (`mixer_mix` or `per_seat`), `device` (`{ index, name }` or `null` for the default input), and `capture` (`idle` / `record` / `pause` / `disabled`). The daemon opens the selected device and switches if Settings changes it. Capture starts when the session is `in-session` and `recording_enabled` is true, pauses on `suspended`, and stops on adjournment.

In mixer mode the daemon mixdowns all device channels to mono and runs one voice-activity detector. In per-seat mode each mapped channel has its own detector so barge-in uploads in parallel. Neighbor-mic bleed is gated by RMS (`CHAMBER_VAD_RMS`).

Speech-to-text uses auto language detection (English / Tagalog / Cebuano). Do not force the UI locale. Visayan accuracy is weaker; secretariat correction remains required.

Speech-to-text drivers (server-side only; do not force the UI locale):

| Driver | Role |
| --- | --- |
| `qwen3` | Self-hosted Qwen3-ASR via OpenAI-compatible `/audio/transcriptions` (`TRANSCRIPTION_QWEN3_*`). Short timeout so a down GPU fails over quickly. |
| `elevenlabs` | Cloud Scribe v2 (`TRANSCRIPTION_ELEVENLABS_*`). Filipino and Cebuano listed; DPO review required. |
| `whisper` | Cloud OpenAI-compatible STT, typically Groq (`TRANSCRIPTION_BASE_URL=https://api.groq.com/openai/v1`, `whisper-large-v3` or `whisper-large-v3-turbo`). Do not use Groq’s translations endpoint. |

Failover: set `TRANSCRIPTION_DRIVER=qwen3,whisper` (or `qwen3,elevenlabs,whisper`). The first provider that returns a transcript wins; after three failures a provider is skipped for `TRANSCRIPTION_FAILOVER_COOLDOWN` seconds. Leave `AI_BASE_URL` on the chat provider. Unconfigured providers (empty API key) are omitted from the chain.

ElevenLabs Scribe **audio-event tags apply to file upload only**. Diarization runs on file upload and on **mixer live chunks** (`source=chamber_mix`). Per-seat live chunks keep diarization off so the seat map remains the speaker. Scribe’s realtime stream does not diarize; live capture already uploads VAD chunks to the batch endpoint. Segments below `TRANSCRIPTION_LOW_CONFIDENCE` (default 0.4) are marked unverified in the transcript UI.

Default feed: `CHAMBER_DEFAULT_CAPTURE_MODE` (config) and the `chamber.default_capture_mode` system setting. New sittings inherit that value; a sitting can override it.

## Retention

`php artisan sentria:prune-chamber-audio` (scheduled daily) deletes raw `sessions/{id}/chamber/` files older than `CHAMBER_AUDIO_RETENTION_DAYS` (default 30). Transcript text is kept. Recordings are never auto-published.

## Related

- [Chamber microphones setup manual](manuals/chamber-microphones.md)
- [deployment.md](deployment.md)
- [ai-architecture.md](ai-architecture.md)

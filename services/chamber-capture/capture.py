"""
Chamber capture daemon — mixer mix (default) or per-seat multi-channel.

Mixer mix: mix the interface down to one stream, VAD on the mix, upload as
channel 1. Secretariat assigns speakers in Sentria.

Per-seat: one multi-channel device with a fixed channel index per seat.

Environment:
  SENTRIA_URL                 Base URL of the Sentria install
  SENTRIA_CHAMBER_TOKEN       Sanctum token from Settings → Set up recording computer
                              (or `php artisan sentria:chamber-token`)
  CHAMBER_DEVICE              PortAudio/sounddevice name or index (optional fallback
                              when Settings has no recording device selected)
  CHAMBER_SAMPLE_RATE         Default 16000
  CHAMBER_VAD_RMS             RMS gate; below this is treated as bleed/silence (default 0.012)
  CHAMBER_POLL_SECONDS        Default 3
"""

from __future__ import annotations

import io
import os
import sys
import time
import wave
from dataclasses import dataclass, field
from typing import Any
from urllib.parse import urljoin

try:
    import numpy as np
    import requests
    import sounddevice as sd
except ImportError as exc:  # pragma: no cover
    print(
        "Install dependencies: pip install -r services/chamber-capture/requirements.txt",
        file=sys.stderr,
    )
    raise SystemExit(1) from exc


SAMPLE_RATE = int(os.environ.get("CHAMBER_SAMPLE_RATE", "16000"))
POLL_SECONDS = float(os.environ.get("CHAMBER_POLL_SECONDS", "3"))
HEARTBEAT_SECONDS = float(os.environ.get("CHAMBER_HEARTBEAT_SECONDS", "1"))
VAD_RMS = float(os.environ.get("CHAMBER_VAD_RMS", "0.012"))
MIN_SPEECH_MS = int(os.environ.get("CHAMBER_MIN_SPEECH_MS", "400"))
SILENCE_END_MS = int(os.environ.get("CHAMBER_SILENCE_END_MS", "600"))
BLOCK_MS = 30
MAX_UTTERANCE_MS = int(os.environ.get("CHAMBER_MAX_UTTERANCE_MS", "25000"))


@dataclass
class ChannelState:
    index: int
    speaking: bool = False
    started_at_ms: int = 0
    seq: int = 0
    frames: list[np.ndarray] = field(default_factory=list)
    silence_ms: int = 0
    speech_ms: int = 0


def wav_bytes(frames: list[np.ndarray], sample_rate: int) -> bytes:
    audio = np.concatenate(frames)
    pcm = np.clip(audio * 32767.0, -32768, 32767).astype(np.int16)
    buffer = io.BytesIO()
    with wave.open(buffer, "wb") as handle:
        handle.setnchannels(1)
        handle.setsampwidth(2)
        handle.setframerate(sample_rate)
        handle.writeframes(pcm.tobytes())
    return buffer.getvalue()


def rms(block: np.ndarray) -> float:
    if block.size == 0:
        return 0.0
    return float(np.sqrt(np.mean(np.square(block.astype(np.float64)))))


def list_input_devices() -> list[dict[str, Any]]:
    try:
        default_input = sd.default.device[0] if sd.default.device is not None else None
    except (TypeError, IndexError, ValueError):
        default_input = None

    rows: list[dict[str, Any]] = []
    for index, info in enumerate(sd.query_devices()):
        inputs = int(info.get("max_input_channels") or 0)
        if inputs < 1:
            continue
        rows.append(
            {
                "index": index,
                "name": str(info.get("name") or f"Device {index}"),
                "input_count": inputs,
                "is_default": default_input == index,
            }
        )
    return rows


def env_device() -> int | str | None:
    device = os.environ.get("CHAMBER_DEVICE", "").strip()
    if not device:
        return None
    return int(device) if device.isdigit() else device


_NAME_PREFIXES = ("default - ", "communications - ")
_NAME_SUFFIXES = (
    " (bluetooth)",
    " (usb)",
    " (analog)",
    " (mme)",
    " (windows directsound)",
    " (windows wasapi)",
)


def normalize_device_name(name: str) -> str:
    text = name.strip()
    lowered = text.lower()
    for prefix in _NAME_PREFIXES:
        if lowered.startswith(prefix):
            text = text[len(prefix) :].strip()
            lowered = text.lower()
            break
    for suffix in _NAME_SUFFIXES:
        if lowered.endswith(suffix):
            text = text[: -len(suffix)].strip()
            lowered = text.lower()
            break
    return text


def wanted_device(state: dict[str, Any]) -> int | str | None:
    payload = state.get("device")
    if isinstance(payload, dict):
        name = str(payload.get("name") or "").strip()
        index = payload.get("index")
        if name:
            return name
        if isinstance(index, int) or (isinstance(index, str) and index.isdigit()):
            return int(index)
    return env_device()


def resolve_device(wanted: int | str | None, devices: list[dict[str, Any]] | None = None) -> int | str | None:
    if wanted is None:
        return None

    rows = devices if devices is not None else list_input_devices()
    if isinstance(wanted, int):
        for row in rows:
            if row["index"] == wanted:
                return wanted
        return wanted

    name = str(wanted)
    if name.isdigit():
        return resolve_device(int(name), rows)

    exact = [row for row in rows if row["name"] == name]
    if exact:
        return exact[0]["index"]

    wanted_key = normalize_device_name(name).lower()
    if not wanted_key:
        return name

    ranked: list[tuple[int, int, dict[str, Any]]] = []
    for row in rows:
        candidate = normalize_device_name(str(row["name"])).lower()
        if not candidate:
            continue
        if wanted_key == candidate:
            ranked.append((2, len(candidate), row))
        elif wanted_key in candidate or candidate in wanted_key:
            ranked.append((1, min(len(wanted_key), len(candidate)), row))

    if ranked:
        ranked.sort(key=lambda item: (item[0], item[1]), reverse=True)
        return ranked[0][2]["index"]

    return name


class ChamberClient:
    def __init__(self, base_url: str, token: str) -> None:
        self.base_url = base_url.rstrip("/") + "/"
        self.session = requests.Session()
        self.session.headers["Authorization"] = f"Bearer {token}"
        self.session.headers["Accept"] = "application/json"

    def get_state(self) -> dict[str, Any]:
        response = self.session.get(urljoin(self.base_url, "api/chamber/recording"), timeout=15)
        response.raise_for_status()
        return response.json()

    def heartbeat(
        self,
        device: str,
        rms_by_channel: dict[int, float],
        disk_free: int | None,
        devices: list[dict[str, Any]] | None = None,
        listening_inputs: int | None = None,
    ) -> None:
        self.session.post(
            urljoin(self.base_url, "api/chamber/heartbeat"),
            json={
                "device": device,
                "channel_rms": {str(index): value for index, value in rms_by_channel.items()},
                "disk_free_bytes": disk_free,
                "devices": devices or [],
                "listening_inputs": listening_inputs,
            },
            timeout=15,
        )

    def upload_chunk(
        self,
        session_id: str,
        channel_index: int,
        seq: int,
        started_at_ms: int,
        ended_at_ms: int,
        payload: bytes,
    ) -> None:
        files = {"audio": ("chunk.wav", payload, "audio/wav")}
        data = {
            "channel_index": str(channel_index),
            "seq": str(seq),
            "started_at_ms": str(started_at_ms),
            "ended_at_ms": str(ended_at_ms),
        }
        url = urljoin(self.base_url, f"api/sessions/{session_id}/chamber/chunks")
        for attempt in range(5):
            try:
                response = self.session.post(url, data=data, files=files, timeout=60)
                if response.status_code in {202, 200}:
                    skipped = False
                    reason = None
                    try:
                        body = response.json()
                        skipped = bool(body.get("skipped"))
                        reason = body.get("reason")
                    except ValueError:
                        pass
                    if skipped:
                        print(
                            f"chunk skipped channel={channel_index} seq={seq} reason={reason}",
                            flush=True,
                        )
                    return
                if response.status_code >= 500:
                    time.sleep(2 ** attempt)
                    continue
                response.raise_for_status()
                return
            except requests.RequestException:
                time.sleep(2 ** attempt)


def mapped_indexes(state: dict[str, Any]) -> list[int]:
    feed = str(state.get("feed") or "mixer_mix")
    if feed == "mixer_mix":
        return [1]
    channels = state.get("channels") or []
    indexes = [int(row["channel_index"]) for row in channels if row.get("is_active", True)]
    return indexes or [1]


def next_seq_for(state: dict[str, Any], channel_index: int) -> int:
    for row in state.get("channels") or []:
        try:
            if int(row.get("channel_index") or 0) != channel_index:
                continue
            return max(0, int(row.get("next_seq") or 0))
        except (TypeError, ValueError):
            continue
    return 0


def mixdown(indata: np.ndarray) -> np.ndarray:
    if indata.ndim < 2 or indata.shape[1] <= 1:
        return indata[:, 0] if indata.ndim > 1 else indata
    return np.mean(indata, axis=1)


def run() -> None:
    base_url = os.environ.get("SENTRIA_URL", "http://127.0.0.1:8000")
    token = os.environ.get("SENTRIA_CHAMBER_TOKEN", "")
    if not token:
        raise SystemExit("SENTRIA_CHAMBER_TOKEN is required")

    client = ChamberClient(base_url, token)
    states: dict[int, ChannelState] = {}
    last_poll = 0.0
    last_heartbeat = 0.0
    capture_state: dict[str, Any] = {"capture": "idle", "session_id": None, "epoch_ms": 0}

    stream: Any = None
    current_key: object = object()
    info: Any = {}
    max_channels = 1

    def session_clock_ms() -> int:
        epoch = int(capture_state.get("epoch_ms") or 0)
        if epoch <= 0:
            return 0
        return max(0, int(time.time() * 1000) - epoch)

    def flush(channel: ChannelState, session_id: str, ended_at_ms: int) -> None:
        if not channel.frames or channel.speech_ms < MIN_SPEECH_MS:
            channel.frames.clear()
            channel.speaking = False
            channel.silence_ms = 0
            channel.speech_ms = 0
            return
        payload = wav_bytes(channel.frames, SAMPLE_RATE)
        client.upload_chunk(
            session_id,
            channel.index,
            channel.seq,
            channel.started_at_ms,
            ended_at_ms,
            payload,
        )
        channel.seq += 1
        channel.frames.clear()
        channel.speaking = False
        channel.silence_ms = 0
        channel.speech_ms = 0

    def callback(indata: np.ndarray, frames: int, time_info: Any, status: Any) -> None:
        del frames, time_info, status
        feed = str(capture_state.get("feed") or "mixer_mix")
        channel_count = indata.shape[1] if indata.ndim > 1 else 1
        rms_map: dict[int, float] = {}

        if feed == "mixer_mix":
            mixed = mixdown(indata)
            rms_map[1] = rms(mixed)
            capture_state["_rms"] = rms_map

            if capture_state.get("capture") != "record" or not capture_state.get("session_id"):
                return

            session_id = str(capture_state["session_id"])
            now_ms = session_clock_ms()
            block_ms = int(1000 * indata.shape[0] / SAMPLE_RATE)
            if 1 not in states:
                return
            channel = states[1]
            energy = rms_map[1]
            is_speech = energy >= VAD_RMS
            if is_speech:
                if not channel.speaking:
                    channel.speaking = True
                    channel.started_at_ms = now_ms
                    channel.frames = []
                    channel.silence_ms = 0
                    channel.speech_ms = 0
                channel.frames.append(mixed.copy())
                channel.speech_ms += block_ms
                channel.silence_ms = 0
                if channel.speech_ms >= MAX_UTTERANCE_MS:
                    flush(channel, session_id, now_ms)
            elif channel.speaking:
                channel.frames.append(mixed.copy())
                channel.silence_ms += block_ms
                if channel.silence_ms >= SILENCE_END_MS:
                    flush(channel, session_id, now_ms)
            return

        for device_channel in range(channel_count):
            logical = device_channel + 1
            block = indata[:, device_channel] if indata.ndim > 1 else indata[:, 0]
            rms_map[logical] = rms(block)
        capture_state["_rms"] = rms_map

        if capture_state.get("capture") != "record" or not capture_state.get("session_id"):
            return

        session_id = str(capture_state["session_id"])
        now_ms = session_clock_ms()
        block_ms = int(1000 * indata.shape[0] / SAMPLE_RATE)
        for logical in list(states.keys()):
            # Interface channel index is 1-based in Sentria; sounddevice is 0-based.
            device_channel = logical - 1
            if device_channel < 0 or device_channel >= channel_count:
                continue
            energy = rms_map.get(logical, 0.0)
            channel = states[logical]
            is_speech = energy >= VAD_RMS
            if is_speech:
                if not channel.speaking:
                    channel.speaking = True
                    channel.started_at_ms = now_ms
                    channel.frames = []
                    channel.silence_ms = 0
                    channel.speech_ms = 0
                block = indata[:, device_channel] if indata.ndim > 1 else indata[:, 0]
                channel.frames.append(block.copy())
                channel.speech_ms += block_ms
                channel.silence_ms = 0
                if channel.speech_ms >= MAX_UTTERANCE_MS:
                    flush(channel, session_id, now_ms)
            elif channel.speaking:
                block = indata[:, device_channel] if indata.ndim > 1 else indata[:, 0]
                channel.frames.append(block.copy())
                channel.silence_ms += block_ms
                if channel.silence_ms >= SILENCE_END_MS:
                    flush(channel, session_id, now_ms)

    def close_stream() -> None:
        nonlocal stream
        if stream is None:
            return
        try:
            stream.stop()
        except Exception:
            pass
        try:
            stream.close()
        except Exception:
            pass
        stream = None

    def ensure_stream(wanted: int | str | None) -> None:
        nonlocal stream, current_key, info, max_channels
        device_id = resolve_device(wanted)
        key: object = "default" if device_id is None else device_id
        if stream is not None and key == current_key:
            return

        close_stream()
        for channel in states.values():
            channel.frames.clear()
            channel.speaking = False
            channel.silence_ms = 0
            channel.speech_ms = 0

        try:
            info = (
                sd.query_devices(device_id, "input")
                if device_id is not None
                else sd.query_devices(kind="input")
            )
            max_channels = int(info.get("max_input_channels") or 1)
            opened = sd.InputStream(
                device=device_id,
                channels=max_channels,
                samplerate=SAMPLE_RATE,
                blocksize=int(SAMPLE_RATE * BLOCK_MS / 1000),
                dtype="float32",
                callback=callback,
            )
            opened.start()
            stream = opened
            current_key = key
            print(f"Listening on {info.get('name')} ({max_channels} channels)", flush=True)
        except Exception as exc:
            current_key = object()
            info = {}
            max_channels = 1
            print(f"Could not open audio device {device_id!r}: {exc}", file=sys.stderr, flush=True)

    ensure_stream(env_device())

    try:
        while True:
            now = time.time()
            if now - last_poll >= POLL_SECONDS:
                last_poll = now
                try:
                    capture_state = {**client.get_state(), "_rms": capture_state.get("_rms", {})}
                except requests.RequestException as exc:
                    print(f"state poll failed: {exc}", file=sys.stderr, flush=True)
                    time.sleep(POLL_SECONDS)
                    continue

                ensure_stream(wanted_device(capture_state))

                wanted = mapped_indexes(capture_state)
                for index in wanted:
                    channel = states.setdefault(index, ChannelState(index=index))
                    server_seq = next_seq_for(capture_state, index)
                    if server_seq > channel.seq:
                        channel.seq = server_seq
                for index in list(states.keys()):
                    if index not in wanted:
                        del states[index]

                if capture_state.get("capture") != "record":
                    for channel in states.values():
                        channel.frames.clear()
                        channel.speaking = False

            if now - last_heartbeat >= HEARTBEAT_SECONDS:
                last_heartbeat = now
                try:
                    client.heartbeat(
                        str(info.get("name") or "audio-interface"),
                        capture_state.get("_rms") or {},
                        None,
                        list_input_devices(),
                        max_channels if stream is not None else None,
                    )
                except requests.RequestException:
                    pass

            time.sleep(0.05)
    finally:
        close_stream()


if __name__ == "__main__":
    run()

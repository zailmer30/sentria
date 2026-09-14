<?php

namespace App\Services\Sessions;

use App\Enums\ChamberFeed;
use App\Jobs\Sessions\ProcessChamberChunkJob;
use App\Models\LegislativeSession;
use App\States\Session\Adjourned;
use App\States\Session\InSession;
use App\States\Session\Suspended;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class ChamberRecordingService
{
    public function __construct(
        private readonly ChamberChannelService $channels,
        private readonly TranscriptService $transcripts,
        private readonly ChamberCaptureSettings $captureSettings,
    ) {}

    public function liveSession(): ?LegislativeSession
    {
        return LegislativeSession::query()
            ->whereIn('status', [InSession::$name, Suspended::$name])
            ->orderByDesc('actual_start_at')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    public function daemonState(): array
    {
        return $this->stateFor($this->liveSession());
    }

    /**
     * Browser capture is pinned to the sitting whose page is open, rather than
     * resolving whichever session happens to be live.
     *
     * @return array<string, mixed>
     */
    public function captureState(LegislativeSession $session): array
    {
        return $this->stateFor($session);
    }

    /**
     * @return array<string, mixed>
     */
    private function stateFor(?LegislativeSession $session): array
    {
        $feed = $session?->chamberFeed() ?? $this->captureSettings->defaultFeed();

        if ($session === null) {
            return [
                'session_id' => null,
                'status' => 'idle',
                'capture' => 'idle',
                'feed' => $feed->value,
                'device' => $this->captureSettings->device(),
                'recording_enabled' => null,
                'epoch_ms' => null,
                'channels' => $this->channelsForFeed($feed, null),
            ];
        }

        $transcript = $this->transcripts->chamberForSession($session);
        $origin = $session->actual_start_at ?? $session->created_at;
        $capture = $this->captureMode($session);
        $snapshot = is_array($transcript?->channel_map_snapshot) && $transcript->channel_map_snapshot !== []
            ? $transcript->channel_map_snapshot
            : null;

        return [
            'session_id' => $session->getKey(),
            'status' => $session->status->getValue(),
            'status_label' => $session->status->label(),
            'capture' => $capture,
            'feed' => $feed->value,
            'device' => $this->captureSettings->device(),
            'recording_enabled' => (bool) $session->recording_enabled,
            'epoch_ms' => $origin !== null ? ((int) $origin->getTimestamp()) * 1000 : null,
            'transcript_id' => $transcript?->getKey(),
            'channels' => $this->withSeqCursors(
                $session->getKey(),
                $this->channelsForFeed($feed, $snapshot),
            ),
        ];
    }

    public function captureMode(LegislativeSession $session): string
    {
        if (! $session->recording_enabled) {
            return 'disabled';
        }

        if ($session->status instanceof InSession) {
            return 'record';
        }

        if ($session->status instanceof Suspended) {
            return 'pause';
        }

        return 'idle';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordHeartbeat(array $payload): void
    {
        Cache::put(
            'chamber.heartbeat',
            [
                'device' => $payload['device'] ?? null,
                'channel_rms' => $payload['channel_rms'] ?? [],
                'disk_free_bytes' => $payload['disk_free_bytes'] ?? null,
                'listening_inputs' => $payload['listening_inputs'] ?? null,
                'devices' => $payload['devices'] ?? [],
                'received_at' => now()->toIso8601String(),
            ],
            now()->addSeconds((int) config('sentria.chamber.heartbeat_ttl_seconds', 90)),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function lastHeartbeat(): ?array
    {
        $value = Cache::get('chamber.heartbeat');

        return is_array($value) ? $value : null;
    }

    /**
     * @return array{skipped: bool, reason?: string, queued?: bool}
     */
    public function acceptChunk(
        LegislativeSession $session,
        int $channelIndex,
        int $seq,
        int $startedAtMs,
        int $endedAtMs,
        UploadedFile $audio,
    ): array {
        $this->assertAcceptsChunks($session);

        $transcript = $this->transcripts->chamberForSession($session);

        if ($transcript === null) {
            $transcript = $this->channels->ensureChamberTranscript($session);
        }

        $feed = $session->chamberFeed();

        if ($feed === ChamberFeed::PerSeat) {
            $map = is_array($transcript->channel_map_snapshot) ? $transcript->channel_map_snapshot : null;
            $channel = $this->channels->resolveChannel($map, $channelIndex);

            if ($channel === null) {
                abort(422, 'Unknown chamber channel.');
            }
        }

        $durationMs = max(0, $endedAtMs - $startedAtMs);
        $minDuration = (int) config('sentria.chamber.min_duration_ms', 400);
        $minBytes = (int) config('sentria.chamber.min_chunk_bytes', 2048);

        if ($durationMs < $minDuration || $audio->getSize() < $minBytes) {
            return ['skipped' => true, 'reason' => 'below_energy_or_duration'];
        }

        $dedupeKey = sprintf(
            'chamber.chunk.%s.%d.%d.%d',
            $session->getKey(),
            $channelIndex,
            $seq,
            $startedAtMs,
        );

        if (! Cache::add($dedupeKey, 1, now()->addDay())) {
            return ['skipped' => true, 'reason' => 'duplicate'];
        }

        $extension = $audio->getClientOriginalExtension() ?: 'wav';
        $path = sprintf(
            'sessions/%s/chamber/%d/%d-%d.%s',
            $session->getKey(),
            $channelIndex,
            $seq,
            $startedAtMs,
            $extension,
        );

        Storage::disk('local')->putFileAs(dirname($path), $audio, basename($path));
        $this->rememberAcceptedSeq($session->getKey(), $channelIndex, $seq);

        ProcessChamberChunkJob::dispatch(
            $transcript->getKey(),
            $channelIndex,
            $seq,
            $startedAtMs,
            $path,
            'local',
        );

        return ['skipped' => false, 'queued' => true];
    }

    public function setRecordingEnabled(LegislativeSession $session, bool $enabled): LegislativeSession
    {
        $session->update(['recording_enabled' => $enabled]);

        return $session->refresh();
    }

    public function setCaptureMode(LegislativeSession $session, ChamberFeed $feed): LegislativeSession
    {
        $session->update(['capture_mode' => $feed]);

        return $session->refresh();
    }

    public function markCaptureStopped(LegislativeSession $session): void
    {
        $transcript = $this->transcripts->chamberForSession($session);

        if ($transcript === null || $transcript->status === 'failed') {
            return;
        }

        $transcript->update([
            'status' => $transcript->full_text ? 'completed' : 'completed',
            'ended_at' => now(),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>|null  $snapshot
     * @return list<array<string, mixed>>
     */
    public function channelsForFeed(ChamberFeed $feed, ?array $snapshot): array
    {
        if ($feed === ChamberFeed::MixerMix) {
            return [
                [
                    'channel_index' => 1,
                    'user_id' => null,
                    'label' => 'Mix',
                    'is_active' => true,
                    'display_name' => null,
                    'speaker' => null,
                ],
            ];
        }

        if ($snapshot !== null && $snapshot !== []) {
            return $snapshot;
        }

        return $this->channels->snapshotActive();
    }

    /**
     * @param  list<array<string, mixed>>  $channels
     * @return list<array<string, mixed>>
     */
    private function withSeqCursors(string $sessionId, array $channels): array
    {
        return array_values(array_map(function (array $channel) use ($sessionId): array {
            $index = (int) ($channel['channel_index'] ?? 0);
            $channel['next_seq'] = $index > 0 ? $this->nextSeq($sessionId, $index) : 0;

            return $channel;
        }, $channels));
    }

    private function nextSeq(string $sessionId, int $channelIndex): int
    {
        return (int) Cache::get($this->seqCursorKey($sessionId, $channelIndex), 0);
    }

    private function rememberAcceptedSeq(string $sessionId, int $channelIndex, int $seq): void
    {
        $key = $this->seqCursorKey($sessionId, $channelIndex);
        $next = $seq + 1;

        if ($next > $this->nextSeq($sessionId, $channelIndex)) {
            Cache::put($key, $next, now()->addDays(7));
        }
    }

    private function seqCursorKey(string $sessionId, int $channelIndex): string
    {
        return sprintf('chamber.seq.%s.%d', $sessionId, $channelIndex);
    }

    private function assertAcceptsChunks(LegislativeSession $session): void
    {
        if ($session->status instanceof Adjourned) {
            abort(409, 'Session has adjourned.');
        }

        if ($session->status instanceof Suspended) {
            abort(409, 'Session is suspended.');
        }

        if (! $session->status instanceof InSession) {
            abort(409, 'Session is not in progress.');
        }

        if (! $session->recording_enabled) {
            abort(409, 'Chamber recording is disabled.');
        }
    }
}

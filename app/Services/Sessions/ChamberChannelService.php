<?php

namespace App\Services\Sessions;

use App\Models\ChamberChannel;
use App\Models\LegislativeSession;
use App\Models\Transcript;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChamberChannelService
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function snapshotActive(): array
    {
        return ChamberChannel::query()
            ->active()
            ->with('member')
            ->get()
            ->map(fn (ChamberChannel $channel): array => $this->serialize($channel))
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function sync(array $rows): void
    {
        $indexes = array_map(fn (array $row): int => (int) ($row['channel_index'] ?? 0), $rows);

        if (count($indexes) !== count(array_unique($indexes))) {
            throw ValidationException::withMessages([
                'channels' => __('chamber.duplicate_index'),
            ]);
        }

        DB::transaction(function () use ($rows): void {
            $keep = [];

            foreach ($rows as $row) {
                $index = (int) ($row['channel_index'] ?? 0);
                $id = isset($row['id']) && is_string($row['id']) && $row['id'] !== '' ? $row['id'] : null;

                $channel = $id !== null
                    ? ChamberChannel::query()->find($id)
                    : ChamberChannel::query()->where('channel_index', $index)->first();

                $payload = [
                    'channel_index' => $index,
                    'user_id' => $row['user_id'] ?? null,
                    'label' => isset($row['label']) && is_string($row['label']) && trim($row['label']) !== ''
                        ? trim($row['label'])
                        : null,
                    'is_active' => (bool) ($row['is_active'] ?? true),
                ];

                if ($channel === null) {
                    $channel = ChamberChannel::query()->create($payload);
                } else {
                    $channel->update($payload);
                }

                $keep[] = $channel->getKey();
            }

            ChamberChannel::query()->whereKeyNot($keep)->delete();
        });
    }

    public function ensureChamberTranscript(LegislativeSession $session, ?User $actor = null): Transcript
    {
        $existing = Transcript::query()
            ->where('session_id', $session->getKey())
            ->where('source', 'chamber_channels')
            ->latest('created_at')
            ->first();

        $snapshot = $this->snapshotActive();

        if ($existing !== null) {
            if ($existing->channel_map_snapshot === null || $existing->channel_map_snapshot === []) {
                $existing->update(['channel_map_snapshot' => $snapshot]);
            }

            if (in_array($existing->status, ['pending', 'failed', 'completed'], true) && $session->status->getValue() === 'in-session') {
                $existing->update([
                    'status' => 'processing',
                    'ended_at' => null,
                ]);
            }

            return $existing->refresh();
        }

        return Transcript::query()->create([
            'session_id' => $session->getKey(),
            'source' => 'chamber_channels',
            'status' => 'processing',
            'language' => 'und',
            'channel_map_snapshot' => $snapshot,
            'started_at' => $session->actual_start_at ?? now(),
            'created_by' => $actor?->getKey(),
            'segments' => [],
            'full_text' => '',
        ]);
    }

    /**
     * @param  list<array<string, mixed>>|null  $snapshot
     * @return array{channel_index: int, user_id: string|null, label: string, speaker: string}|null
     */
    public function resolveChannel(?array $snapshot, int $channelIndex): ?array
    {
        $rows = $snapshot ?? $this->snapshotActive();

        foreach ($rows as $row) {
            if ((int) ($row['channel_index'] ?? 0) !== $channelIndex) {
                continue;
            }

            $label = (string) ($row['label'] ?? '');
            $speaker = (string) ($row['display_name'] ?? $row['speaker'] ?? $label);

            if ($speaker === '') {
                $speaker = $label !== '' ? $label : 'Channel '.$channelIndex;
            }

            return [
                'channel_index' => $channelIndex,
                'user_id' => isset($row['user_id']) && is_string($row['user_id']) ? $row['user_id'] : null,
                'label' => $label !== '' ? $label : $speaker,
                'speaker' => $speaker,
            ];
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(ChamberChannel $channel): array
    {
        $member = $channel->member;

        return [
            'id' => $channel->getKey(),
            'channel_index' => $channel->channel_index,
            'user_id' => $channel->user_id,
            'label' => $channel->label,
            'is_active' => $channel->is_active,
            'display_name' => $member?->display_name,
            'speaker' => $channel->displayLabel(),
        ];
    }
}

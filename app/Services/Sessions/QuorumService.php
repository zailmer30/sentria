<?php

namespace App\Services\Sessions;

use App\Enums\AttendanceStatus;
use App\Models\LegislativeSession;
use App\Models\SessionAttendance;
use App\Models\SystemSetting;
use App\Models\User;

class QuorumService
{
    public function forSession(LegislativeSession $session): QuorumDisplayDto
    {
        $seatedCount = $this->resolveSeatedCount($session);
        $present = $session->attendance()
            ->whereIn('status', [
                AttendanceStatus::Present->value,
                AttendanceStatus::Late->value,
            ])
            ->with('user')
            ->orderBy('created_at')
            ->get();
        $presentCount = $present->count();

        // on-official-business (including members abroad) is seated but not
        // present. It does not count toward quorum.

        [$required, $rule] = $this->resolveRequiredQuorum($session, $seatedCount);

        return new QuorumDisplayDto(
            seatedCount: $seatedCount,
            presentCount: $presentCount,
            required: $required,
            met: $presentCount >= $required,
            rule: $rule,
            presentMembers: $present->map(fn (SessionAttendance $record): array => [
                'id' => (string) $record->user_id,
                'display_name' => $record->user?->display_name,
                'avatar_url' => $record->user?->avatarUrl(),
            ])->values()->all(),
        );
    }

    private function resolveSeatedCount(LegislativeSession $session): int
    {
        if ($session->seated_member_count !== null && $session->seated_member_count > 0) {
            return $session->seated_member_count;
        }

        return User::query()->where('is_seated_member', true)->where('is_active', true)->count();
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function resolveRequiredQuorum(LegislativeSession $session, int $seatedCount): array
    {
        if ($session->quorum_required !== null && $session->quorum_required > 0) {
            return [$session->quorum_required, 'fixed'];
        }

        $rule = (string) ($this->settingScalar('quorum.rule') ?? config('sentria.quorum.rule', 'majority_of_seated'));

        return match ($rule) {
            'two_thirds_of_seated' => [(int) ceil($seatedCount * 2 / 3), $rule],
            'fixed' => [max(1, (int) ($this->settingScalar('quorum.fixed_threshold')
                ?? config('sentria.quorum.fixed_threshold')
                ?? 1)), $rule],
            default => [intdiv(max($seatedCount, 1), 2) + 1, 'majority_of_seated'],
        };
    }

    private function settingScalar(string $key): mixed
    {
        $setting = SystemSetting::query()->where('key', $key)->first();

        if ($setting === null) {
            return null;
        }

        $value = $setting->getRawOriginal('value');

        if ($value === null) {
            return null;
        }

        $decoded = json_decode((string) $value, true);

        return $decoded;
    }
}

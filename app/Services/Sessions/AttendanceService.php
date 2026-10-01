<?php

namespace App\Services\Sessions;

use App\Enums\AttendanceStatus;
use App\Models\LegislativeSession;
use App\Models\SessionAttendance;
use App\Models\User;
use App\States\Session\InSession;
use App\States\Session\Suspended;
use Illuminate\Support\Collection;

class AttendanceService
{
    /**
     * @return Collection<int, SessionAttendance>
     */
    public function roster(LegislativeSession $session): Collection
    {
        return $session->attendance()
            ->with('user')
            ->orderBy('created_at')
            ->get();
    }

    /**
     * @param  array{status: string, remarks?: string|null, checked_in_at?: string|null}  $data
     */
    public function record(
        LegislativeSession $session,
        User $member,
        User $recorder,
        array $data,
    ): SessionAttendance {
        $status = AttendanceStatus::from($data['status']);

        $values = [
            'status' => $status->value,
            'checked_in_at' => $status->countsTowardQuorum() ? now() : null,
            'checked_out_at' => $status->countsTowardQuorum() ? null : now(),
            'check_in_method' => $status->countsTowardQuorum() ? 'manual' : null,
            'recorded_by' => $recorder->getKey(),
        ];

        if (! $status->takesRemarks()) {
            $values['remarks'] = null;
        } elseif (array_key_exists('remarks', $data)) {
            $values['remarks'] = self::normalizeRemarks($data['remarks'] ?? null);
        }

        return SessionAttendance::query()->updateOrCreate(
            [
                'session_id' => $session->getKey(),
                'user_id' => $member->getKey(),
            ],
            $values,
        );
    }

    /**
     * @return Collection<int, SessionAttendance>
     */
    public function ensureRoster(LegislativeSession $session): Collection
    {
        $members = User::query()
            ->where('is_seated_member', true)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        foreach ($members as $member) {
            SessionAttendance::query()->firstOrCreate(
                [
                    'session_id' => $session->getKey(),
                    'user_id' => $member->getKey(),
                ],
                [
                    'status' => AttendanceStatus::Absent->value,
                ],
            );
        }

        return $this->roster($session);
    }

    /**
     * Mark a seated member present when they open the live paperless floor.
     * Absent (or a missing row) is promoted; excused, official business, late,
     * and already-present are left alone so Echo reloads cannot loop.
     */
    public function checkInFromFloor(LegislativeSession $session, User $member): bool
    {
        if (! $session->status instanceof InSession && ! $session->status instanceof Suspended) {
            return false;
        }

        if (! $member->is_seated_member || ! $member->is_active) {
            return false;
        }

        $record = SessionAttendance::query()
            ->where('session_id', $session->getKey())
            ->where('user_id', $member->getKey())
            ->first();

        if ($record !== null && $record->status !== AttendanceStatus::Absent->value) {
            return false;
        }

        SessionAttendance::query()->updateOrCreate(
            [
                'session_id' => $session->getKey(),
                'user_id' => $member->getKey(),
            ],
            [
                'status' => AttendanceStatus::Present->value,
                'checked_in_at' => now(),
                'checked_out_at' => null,
                'check_in_method' => 'tablet',
                'recorded_by' => $member->getKey(),
                'remarks' => null,
            ],
        );

        return true;
    }

    private static function normalizeRemarks(?string $remarks): ?string
    {
        $trimmed = preg_replace('/\s+/u', ' ', trim((string) $remarks)) ?? '';

        return $trimmed === '' ? null : $trimmed;
    }
}

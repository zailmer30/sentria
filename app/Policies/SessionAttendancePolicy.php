<?php

namespace App\Policies;

use App\Models\LegislativeSession;
use App\Models\SessionAttendance;
use App\Models\User;

class SessionAttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('attendance.viewAny');
    }

    public function view(User $user, SessionAttendance $attendance): bool
    {
        return $user->can('attendance.viewAny')
            && $user->can('view', $attendance->session);
    }

    public function update(User $user, LegislativeSession $session): bool
    {
        return $user->can('attendance.record');
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\SessionGuestStatus;
use App\Events\AttendanceUpdated;
use App\Http\Requests\Sessions\UpdateAttendanceRequest;
use App\Http\Resources\SessionResource;
use App\Models\LegislativeSession;
use App\Models\SessionGuest;
use App\Models\User;
use App\Services\Sessions\AttendanceService;
use App\Services\Sessions\QuorumService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendance,
        private readonly QuorumService $quorum,
    ) {}

    public function index(Request $request, LegislativeSession $session): Response
    {
        $this->authorize('view', $session);

        $roster = $this->attendance->ensureRoster($session);

        return Inertia::render('Sessions/Attendance', [
            'session' => SessionResource::summary($session),
            'attendance' => $roster->map(fn ($record): array => SessionResource::attendance($record))->values()->all(),
            'quorum' => $this->quorum->forSession($session)->toArray(),
            'can' => [
                'record' => $request->user()?->can('recordAttendance', $session) ?? false,
            ],
            'statuses' => collect(AttendanceStatus::cases())->map(fn ($s): array => [
                'value' => $s->value,
                'label' => $s->label(),
            ])->values()->all(),
            'guests' => $session->guests()->get()->map(fn (SessionGuest $guest): array => SessionResource::guest($guest))->values()->all(),
            'guest_statuses' => collect(SessionGuestStatus::cases())->map(fn ($status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ])->values()->all(),
        ]);
    }

    public function update(UpdateAttendanceRequest $request, LegislativeSession $session): RedirectResponse
    {
        $actor = $this->requireUser($request);

        foreach ($request->validated('records') as $record) {
            $member = User::query()->whereKey($record['user_id'])->firstOrFail();
            $this->attendance->record($session, $member, $actor, $record);
        }

        $session->refresh();
        event(new AttendanceUpdated($session, $this->quorum->forSession($session)));

        return back()->with('success', 'sessions.attendance_updated');
    }
}

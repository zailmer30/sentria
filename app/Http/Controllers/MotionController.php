<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sessions\StoreMotionRequest;
use App\Models\LegislativeSession;
use App\Models\Motion;
use App\Models\User;
use App\Services\Sessions\FloorRecognitionService;
use App\Services\Sessions\MotionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MotionController extends Controller
{
    public function __construct(
        private readonly MotionService $motions,
        private readonly FloorRecognitionService $recognition,
    ) {}

    public function store(StoreMotionRequest $request, LegislativeSession $session): RedirectResponse
    {
        $agendaItem = $this->agendaItemForSession($session, $request->validated('agenda_item_id'));
        $actor = $this->requireUser($request);
        $mover = $this->moverForRecord($request, $session, $actor);

        $this->motions->record(
            $session,
            $agendaItem,
            $mover,
            $request->validated('text'),
            $request->validated('type') ?? 'main',
        );

        $this->recognition->resolveForMover($session, $mover);

        return back()->with('success', 'sessions.motion_recorded');
    }

    public function second(Request $request, LegislativeSession $session, Motion $motion): RedirectResponse
    {
        abort_unless($motion->session_id === $session->getKey(), 404);
        $this->authorize('second', $motion);

        $this->motions->second($motion, $this->requireUser($request));

        return back()->with('success', 'sessions.motion_seconded');
    }

    public function withdraw(Request $request, LegislativeSession $session, Motion $motion): RedirectResponse
    {
        abort_unless($motion->session_id === $session->getKey(), 404);
        $this->authorize('withdraw', $motion);

        $this->motions->withdraw($motion, $this->requireUser($request));

        return back()->with('success', 'sessions.motion_withdrawn');
    }

    public function rule(Request $request, LegislativeSession $session, Motion $motion): RedirectResponse
    {
        abort_unless($motion->session_id === $session->getKey(), 404);
        $this->authorize('rule', $motion);

        $validated = $request->validate([
            'disposition' => ['required', 'string', 'in:carried,lost,ruled_out,referred'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->motions->rule(
            $motion,
            $this->requireUser($request),
            $validated['disposition'],
            $validated['notes'] ?? null,
        );

        return back()->with('success', 'sessions.motion_ruled');
    }

    private function moverForRecord(StoreMotionRequest $request, LegislativeSession $session, User $actor): User
    {
        $movedBy = $request->validated('moved_by');

        if (! is_string($movedBy) || $movedBy === '') {
            abort_unless($actor->can('create', Motion::class), 403);

            return $actor;
        }

        $recognized = $this->recognition->openRecognized($session);
        abort_unless($recognized !== null && $recognized->user_id === $movedBy, 422);
        abort_unless(
            $actor->can('create', Motion::class) || $actor->can('agenda.manage'),
            403,
        );

        $mover = User::query()->whereKey($movedBy)->first();
        abort_unless($mover instanceof User, 422);

        return $mover;
    }
}

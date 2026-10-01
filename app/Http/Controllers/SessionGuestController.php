<?php

namespace App\Http\Controllers;

use App\Events\SessionGuestsUpdated;
use App\Http\Requests\Sessions\DestroySessionGuestRequest;
use App\Http\Requests\Sessions\StoreSessionGuestRequest;
use App\Http\Requests\Sessions\UpdateSessionGuestRequest;
use App\Models\LegislativeSession;
use App\Models\SessionGuest;
use App\Services\Sessions\SessionGuestService;
use Illuminate\Http\RedirectResponse;

class SessionGuestController extends Controller
{
    public function __construct(
        private readonly SessionGuestService $guests,
    ) {}

    public function store(StoreSessionGuestRequest $request, LegislativeSession $session): RedirectResponse
    {
        $data = $request->validated();

        $this->guests->add(
            $session,
            $this->requireUser($request),
            $data['name'] ?? null,
            $data['organization'] ?? null,
            $data['speaking_topic'] ?? null,
        );
        event(new SessionGuestsUpdated($session));

        return back()->with('success', 'sessions.guests_added');
    }

    public function update(
        UpdateSessionGuestRequest $request,
        LegislativeSession $session,
        SessionGuest $sessionGuest,
    ): RedirectResponse {
        abort_unless($sessionGuest->session_id === $session->getKey(), 404);

        $data = $request->validated();

        $this->guests->update(
            $sessionGuest,
            $this->requireUser($request),
            $data['name'] ?? null,
            $data['organization'] ?? null,
            $data['speaking_topic'] ?? null,
            $data['status'] ?? null,
        );
        event(new SessionGuestsUpdated($session));

        return back()->with('success', 'sessions.guests_updated');
    }

    public function destroy(
        DestroySessionGuestRequest $request,
        LegislativeSession $session,
        SessionGuest $sessionGuest,
    ): RedirectResponse {
        abort_unless($sessionGuest->session_id === $session->getKey(), 404);

        $this->guests->remove($sessionGuest);
        event(new SessionGuestsUpdated($session));

        return back()->with('success', 'sessions.guests_removed');
    }
}

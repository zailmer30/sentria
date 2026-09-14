<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sessions\ReferFloorItemRequest;
use App\Models\LegislativeSession;
use App\Services\Sessions\FloorReferralService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class SessionFloorReferralController extends Controller
{
    public function __construct(private readonly FloorReferralService $referrals) {}

    public function __invoke(ReferFloorItemRequest $request, LegislativeSession $session): RedirectResponse
    {
        $this->authorize('view', $session);

        $item = $this->agendaItemForSession($session, $request->validated('agenda_item_id'));

        try {
            $this->referrals->refer(
                $session,
                $item,
                $request->validated('committee_id'),
                $this->requireUser($request),
            );
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return redirect()
            ->route('sessions.floor.secretariat', $session)
            ->with('success', 'sessions.floor.referred');
    }
}

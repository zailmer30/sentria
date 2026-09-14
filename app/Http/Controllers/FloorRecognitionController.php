<?php

namespace App\Http\Controllers;

use App\Models\FloorRecognitionRequest;
use App\Models\LegislativeSession;
use App\Services\Sessions\AgendaService;
use App\Services\Sessions\FloorRecognitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class FloorRecognitionController extends Controller
{
    public function __construct(
        private readonly FloorRecognitionService $recognition,
        private readonly AgendaService $agenda,
    ) {}

    public function store(Request $request, LegislativeSession $session): RedirectResponse
    {
        $this->authorize('create', [FloorRecognitionRequest::class, $session]);

        $current = $this->agenda->currentItem($session);
        abort_unless($current !== null, 422, 'Advance to an agenda item before seeking the floor.');

        try {
            $this->recognition->raise($session, $current, $this->requireUser($request));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'sessions.recognition_raised');
    }

    public function cancel(
        Request $request,
        LegislativeSession $session,
        FloorRecognitionRequest $recognition,
    ): RedirectResponse {
        abort_unless($recognition->session_id === $session->getKey(), 404);
        $this->authorize('cancel', $recognition);

        try {
            $this->recognition->cancel($recognition, $this->requireUser($request));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'sessions.recognition_cancelled');
    }

    public function recognize(
        Request $request,
        LegislativeSession $session,
        FloorRecognitionRequest $recognition,
    ): RedirectResponse {
        abort_unless($recognition->session_id === $session->getKey(), 404);
        $this->authorize('recognize', $recognition);

        try {
            $this->recognition->recognize($recognition, $this->requireUser($request));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'sessions.recognition_granted');
    }

    public function dismiss(
        Request $request,
        LegislativeSession $session,
        FloorRecognitionRequest $recognition,
    ): RedirectResponse {
        abort_unless($recognition->session_id === $session->getKey(), 404);
        $this->authorize('dismiss', $recognition);

        try {
            $this->recognition->dismiss($recognition, $this->requireUser($request));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'sessions.recognition_dismissed');
    }
}

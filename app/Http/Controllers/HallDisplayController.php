<?php

namespace App\Http\Controllers;

use App\Models\LegislativeSession;
use App\Services\Sessions\HallDisplayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class HallDisplayController extends Controller
{
    public function __construct(private readonly HallDisplayService $hall) {}

    public function showDocument(Request $request, LegislativeSession $session): RedirectResponse
    {
        $this->authorize('controlHallDisplay', $session);

        $validated = $request->validate([
            'agenda_item_id' => ['required', 'ulid', 'exists:agenda_items,id'],
        ]);

        $agendaItem = $this->agendaItemForSession($session, $validated['agenda_item_id']);

        try {
            $this->hall->showDocument($session, $agendaItem, $this->requireUser($request));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'sessions.hall.document_projected');
    }

    public function showReport(Request $request, LegislativeSession $session): RedirectResponse
    {
        $this->authorize('controlHallDisplay', $session);

        $validated = $request->validate([
            'agenda_item_id' => ['required', 'ulid', 'exists:agenda_items,id'],
        ]);

        $agendaItem = $this->agendaItemForSession($session, $validated['agenda_item_id']);

        try {
            $this->hall->showReport($session, $agendaItem, $this->requireUser($request));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'sessions.hall.report_projected');
    }

    public function showItem(Request $request, LegislativeSession $session): RedirectResponse
    {
        $this->authorize('controlHallDisplay', $session);

        $this->hall->showItem($session, $this->requireUser($request));

        return back()->with('success', 'sessions.hall.item_restored');
    }

    public function showResults(Request $request, LegislativeSession $session): RedirectResponse
    {
        $this->authorize('controlHallDisplay', $session);

        $validated = $request->validate([
            'agenda_item_id' => ['required', 'ulid', 'exists:agenda_items,id'],
        ]);

        $agendaItem = $this->agendaItemForSession($session, $validated['agenda_item_id']);

        try {
            $this->hall->showResults($session, $agendaItem, $this->requireUser($request));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'sessions.hall.results_projected');
    }

    public function updateView(Request $request, LegislativeSession $session): JsonResponse
    {
        $this->authorize('controlHallDisplay', $session);

        $validated = $request->validate([
            'zoom' => ['required', 'numeric', 'min:0.1', 'max:10'],
            'page' => ['required', 'integer', 'min:1'],
            'relative_x' => ['required', 'numeric', 'min:0', 'max:1'],
            'relative_y' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        try {
            $view = $this->hall->updateView($session, $validated);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['view' => $view]);
    }
}

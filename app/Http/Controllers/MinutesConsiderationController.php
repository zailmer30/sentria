<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sessions\StoreMinutesCorrectionRequest;
use App\Http\Requests\Sessions\UpdateMinutesCorrectionRequest;
use App\Http\Requests\Sessions\UploadAgendaMinutesRequest;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\MinutesCorrection;
use App\Services\Sessions\MinutesConsiderationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

class MinutesConsiderationController extends Controller
{
    public function __construct(
        private readonly MinutesConsiderationService $minutes,
    ) {}

    public function upload(
        UploadAgendaMinutesRequest $request,
        LegislativeSession $session,
        AgendaItem $agendaItem,
    ): RedirectResponse {
        abort_unless($agendaItem->session_id === $session->getKey(), 404);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        try {
            $this->minutes->uploadAndBind(
                $session,
                $agendaItem,
                $this->requireUser($request),
                $file,
                $request->validated('title'),
                $request->validated('of_session_id'),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'sessions.agenda_minutes_uploaded');
    }

    public function storeCorrection(
        StoreMinutesCorrectionRequest $request,
        LegislativeSession $session,
        AgendaItem $agendaItem,
    ): RedirectResponse {
        abort_unless($agendaItem->session_id === $session->getKey(), 404);

        try {
            $this->minutes->recordCorrection(
                $agendaItem,
                $this->requireUser($request),
                $request->safe()->only(['as_written', 'should_read', 'page_number']),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'sessions.minutes_corrections_saved');
    }

    public function updateCorrection(
        UpdateMinutesCorrectionRequest $request,
        LegislativeSession $session,
        AgendaItem $agendaItem,
        MinutesCorrection $correction,
    ): RedirectResponse {
        $this->assertCorrectionOnItem($session, $agendaItem, $correction);

        try {
            $this->minutes->updateCorrection(
                $correction,
                $this->requireUser($request),
                $request->safe()->only(['as_written', 'should_read', 'page_number']),
            );
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'sessions.minutes_corrections_saved');
    }

    public function destroyCorrection(
        Request $request,
        LegislativeSession $session,
        AgendaItem $agendaItem,
        MinutesCorrection $correction,
    ): RedirectResponse {
        $this->assertCorrectionOnItem($session, $agendaItem, $correction);
        $this->authorize('delete', $correction);

        try {
            $this->minutes->deleteCorrection($correction, $this->requireUser($request));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'sessions.minutes_corrections_deleted');
    }

    public function applyCorrection(
        Request $request,
        LegislativeSession $session,
        AgendaItem $agendaItem,
        MinutesCorrection $correction,
    ): RedirectResponse {
        $this->assertCorrectionOnItem($session, $agendaItem, $correction);
        $this->authorize('apply', $correction);

        $applied = $request->boolean('applied', true);

        try {
            $this->minutes->setApplied($correction, $this->requireUser($request), $applied);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', $applied
            ? 'sessions.minutes_corrections_marked_applied'
            : 'sessions.minutes_corrections_unapplied');
    }

    private function assertCorrectionOnItem(
        LegislativeSession $session,
        AgendaItem $agendaItem,
        MinutesCorrection $correction,
    ): void {
        abort_unless($agendaItem->session_id === $session->getKey(), 404);
        abort_unless($correction->session_id === $session->getKey(), 404);
        abort_unless($correction->agenda_item_id === $agendaItem->getKey(), 404);
    }
}

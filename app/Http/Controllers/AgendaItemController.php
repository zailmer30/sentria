<?php

namespace App\Http\Controllers;

use App\Events\AgendaItemChanged;
use App\Exceptions\TranslatedArgumentException;
use App\Http\Requests\Sessions\BindAgendaDocumentsRequest;
use App\Http\Requests\Sessions\BulkCalendarRouteRequest;
use App\Http\Requests\Sessions\StoreAgendaItemRequest;
use App\Http\Requests\Sessions\UpdateAgendaItemRequest;
use App\Models\AgendaItem;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Services\Sessions\AgendaService;
use App\Services\Sessions\CalendarRoutingService;
use App\Services\Sessions\CommitteeHourService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class AgendaItemController extends Controller
{
    public function __construct(
        private readonly AgendaService $agenda,
        private readonly CalendarRoutingService $calendar,
        private readonly CommitteeHourService $committeeHour,
    ) {}

    public function store(StoreAgendaItemRequest $request, LegislativeSession $session): RedirectResponse
    {
        $this->authorize('create', AgendaItem::class);

        if ($redirect = $this->rejectBlockedPlenaryDocument($session, $request->validated('document_id'))) {
            return $redirect;
        }

        $this->agenda->createItem($session, $request->validated());

        return back()->with('success', 'sessions.agenda_item_created');
    }

    public function update(UpdateAgendaItemRequest $request, LegislativeSession $session, AgendaItem $agendaItem): RedirectResponse
    {
        abort_unless($agendaItem->session_id === $session->getKey(), 404);

        if ($redirect = $this->rejectBlockedPlenaryDocument(
            $session,
            $request->validated('document_id'),
            $agendaItem->document_id,
        )) {
            return $redirect;
        }

        $agendaItem->update($request->validated());

        return back()->with('success', 'sessions.agenda_item_updated');
    }

    public function bindDocuments(BindAgendaDocumentsRequest $request, LegislativeSession $session, AgendaItem $agendaItem): RedirectResponse
    {
        abort_unless($agendaItem->session_id === $session->getKey(), 404);

        try {
            /** @var list<string> $documentIds */
            $documentIds = $request->validated('document_ids');

            $this->agenda->bindDocuments(
                $session,
                $agendaItem,
                $documentIds,
                $this->requireUser($request),
            );
        } catch (TranslatedArgumentException $exception) {
            return back()
                ->with('error', $exception->getMessage())
                ->with('error_replacements', $exception->replacements);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return back()->with('success', 'sessions.agenda_documents_bound');
    }

    public function destroy(Request $request, LegislativeSession $session, AgendaItem $agendaItem): RedirectResponse
    {
        abort_unless($agendaItem->session_id === $session->getKey(), 404);

        $this->authorize('delete', $agendaItem);
        $agendaItem->delete();

        return back()->with('success', 'sessions.agenda_item_deleted');
    }

    public function reorder(Request $request, LegislativeSession $session): RedirectResponse
    {
        $this->authorize('reorder', AgendaItem::class);

        /** @var list<string> $orderedIds */
        $orderedIds = $request->validate([
            'ordered_ids' => ['required', 'array', 'min:1'],
            'ordered_ids.*' => ['required', 'ulid'],
        ])['ordered_ids'];

        $this->agenda->reorder($session, $orderedIds);

        return back()->with('success', 'sessions.agenda_reordered');
    }

    public function advance(Request $request, LegislativeSession $session): RedirectResponse
    {
        $current = $session->agendaItems()->where('status', 'in-progress')->orderBy('position')->first()
            ?? $session->agendaItems()->orderBy('position')->first();

        abort_unless($current instanceof AgendaItem, 422);

        $this->authorize('advance', $current);

        try {
            $this->agenda->advance($session);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $session->refresh();
        $current = $this->agenda->currentItem($session);
        $next = $this->agenda->nextPendingItem($session);
        event(new AgendaItemChanged($session, $current, $next));

        return back()->with('success', 'sessions.agenda_advanced');
    }

    public function recordCommitteeHourMotion(Request $request, LegislativeSession $session, AgendaItem $agendaItem): RedirectResponse
    {
        abort_unless($agendaItem->session_id === $session->getKey(), 404);

        $this->authorize('create', AgendaItem::class);

        try {
            $this->committeeHour->recordChairMotion($session, $agendaItem, $this->requireUser($request));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'sessions.committee_hour.recorded');
    }

    public function retreat(Request $request, LegislativeSession $session): RedirectResponse
    {
        $subject = $this->agenda->currentItem($session)
            ?? $this->agenda->previousCompletedItem($session);

        abort_unless($subject instanceof AgendaItem, 422);

        $this->authorize('retreat', $subject);

        try {
            $this->agenda->retreat($session);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $session->refresh();
        $current = $this->agenda->currentItem($session);
        $next = $this->agenda->nextPendingItem($session);
        event(new AgendaItemChanged($session, $current, $next));

        return back()->with('success', 'sessions.agenda_retreated');
    }

    public function beginHeadingVotes(Request $request, LegislativeSession $session): RedirectResponse
    {
        $current = $this->agenda->currentItem($session);

        abort_unless($current instanceof AgendaItem, 422);

        $this->authorize('beginHeadingVotes', $current);

        try {
            $this->agenda->beginHeadingVotes($session);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $session->refresh();
        $current = $this->agenda->currentItem($session);
        $next = $this->agenda->nextPendingItem($session);
        event(new AgendaItemChanged($session, $current, $next));

        return back()->with('success', 'sessions.heading_votes_begun');
    }

    public function calendarSecondReading(Request $request, LegislativeSession $session, AgendaItem $agendaItem): RedirectResponse
    {
        abort_unless($agendaItem->session_id === $session->getKey(), 404);
        $this->authorize('calendarSecondReading', $agendaItem);

        try {
            $this->calendar->calendarSecondReading($session, $agendaItem, $this->requireUser($request));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'sessions.calendar.second_reading_done');
    }

    public function postpone(Request $request, LegislativeSession $session, AgendaItem $agendaItem): RedirectResponse
    {
        abort_unless($agendaItem->session_id === $session->getKey(), 404);
        $this->authorize('postpone', $agendaItem);

        try {
            $this->calendar->postpone($session, $agendaItem, $this->requireUser($request));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'sessions.calendar.postponed');
    }

    public function undoPostpone(Request $request, LegislativeSession $session, AgendaItem $agendaItem): RedirectResponse
    {
        abort_unless($agendaItem->session_id === $session->getKey(), 404);
        $this->authorize('undoPostpone', $agendaItem);

        try {
            $this->calendar->undoPostpone($session, $agendaItem, $this->requireUser($request));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'sessions.calendar.undo_done');
    }

    public function calendarThirdReading(Request $request, LegislativeSession $session, AgendaItem $agendaItem): RedirectResponse
    {
        abort_unless($agendaItem->session_id === $session->getKey(), 404);
        $this->authorize('calendarThirdReading', $agendaItem);

        try {
            $this->calendar->calendarThirdReading($session, $agendaItem, $this->requireUser($request));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'sessions.calendar.third_reading_done');
    }

    public function calendarSecondReadingDocument(Request $request, LegislativeSession $session): RedirectResponse
    {
        $this->authorize('create', AgendaItem::class);

        $document = $this->calendarDocument($request);

        try {
            $this->calendar->placeDocumentOnSecondReading($session, $document, $this->requireUser($request));
        } catch (InvalidArgumentException $exception) {
            return $this->argumentError($exception);
        }

        return back()->with('success', 'sessions.calendar.second_reading_done');
    }

    public function postponeDocument(Request $request, LegislativeSession $session): RedirectResponse
    {
        $this->authorize('create', AgendaItem::class);

        $document = $this->calendarDocument($request);

        try {
            $this->calendar->postponeDocument($session, $document, $this->requireUser($request));
        } catch (InvalidArgumentException $exception) {
            return $this->argumentError($exception);
        }

        return back()->with('success', 'sessions.calendar.postponed');
    }

    public function calendarThirdReadingDocument(Request $request, LegislativeSession $session): RedirectResponse
    {
        $this->authorize('create', AgendaItem::class);

        $document = $this->calendarDocument($request);

        try {
            $this->calendar->placeDocumentOnThirdReading($session, $document, $this->requireUser($request));
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'sessions.calendar.third_reading_done');
    }

    public function bulkCalendarRoute(BulkCalendarRouteRequest $request, LegislativeSession $session): RedirectResponse
    {
        /** @var 'second-reading'|'postpone'|'third-reading'|'undo' $action */
        $action = $request->validated('action');
        /** @var list<array{agenda_item_id?: string|null, document_id?: string|null}> $items */
        $items = $request->validated('items');

        try {
            $this->calendar->bulkRoute(
                $session,
                $action,
                $items,
                $this->requireUser($request),
            );
        } catch (InvalidArgumentException $exception) {
            $redirect = back()->with('error', $exception->getMessage());

            if ($exception instanceof TranslatedArgumentException) {
                $redirect->with('error_replacements', $exception->replacements);
            }

            return $redirect;
        }

        return back()->with('success', match ($action) {
            'second-reading' => 'sessions.calendar.bulk_second_reading_done',
            'postpone' => 'sessions.calendar.bulk_postponed',
            'third-reading' => 'sessions.calendar.bulk_third_reading_done',
            'undo' => 'sessions.calendar.bulk_undo_done',
        });
    }

    private function rejectBlockedPlenaryDocument(LegislativeSession $session, mixed $documentId, mixed $currentDocumentId = null): ?RedirectResponse
    {
        if (! is_string($documentId) || $documentId === '' || $documentId === $currentDocumentId) {
            return null;
        }

        $document = Document::query()->find($documentId);

        if (! $document instanceof Document) {
            return null;
        }

        try {
            $this->agenda->assertManualPlenaryPlacement($session, $document);
        } catch (TranslatedArgumentException $exception) {
            return $this->argumentError($exception);
        }

        return null;
    }

    private function argumentError(InvalidArgumentException $exception): RedirectResponse
    {
        $redirect = back()->with('error', $exception->getMessage());

        if ($exception instanceof TranslatedArgumentException) {
            $redirect->with('error_replacements', $exception->replacements);
        }

        return $redirect;
    }

    private function calendarDocument(Request $request): Document
    {
        $id = $request->validate([
            'document_id' => ['required', 'ulid', 'exists:documents,id'],
        ])['document_id'];

        $document = Document::query()->find($id);
        abort_unless($document instanceof Document, 404);

        return $document;
    }
}

<?php

namespace App\Services\Sessions;

use App\Events\HallDisplayChanged;
use App\Events\HallDisplayViewChanged;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentAccessService;
use InvalidArgumentException;

class HallDisplayService
{
    public const STAGE_ITEM = 'item';

    public const STAGE_DOCUMENT = 'document';

    public const STAGE_RESULTS = 'results';

    /** @var array{zoom: float, page: int, relative_x: float, relative_y: float} */
    public const DEFAULT_VIEW = [
        'zoom' => 1.0,
        'page' => 1,
        'relative_x' => 0.0,
        'relative_y' => 0.0,
    ];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly DocumentAccessService $access,
    ) {}

    public function showDocument(LegislativeSession $session, AgendaItem $agendaItem, User $actor): LegislativeSession
    {
        abort_unless($agendaItem->session_id === $session->getKey(), 422);

        $agendaItem->loadMissing('document.currentVersion');
        $document = $agendaItem->document;

        if ($document === null) {
            throw new InvalidArgumentException('This agenda item has no document to project.');
        }

        if (! $this->access->userCanView($actor, $document)) {
            throw new InvalidArgumentException('You cannot project this document to the hall.');
        }

        $version = $document->currentVersion;

        if (
            $version === null
            || ! $this->access->userCanDownload($actor, $document)
            || ! $version->isSafeToServe()
            || $version->mime_type !== 'application/pdf'
        ) {
            throw new InvalidArgumentException('This document cannot be shown on the hall display.');
        }

        return $this->persist(
            $session,
            self::STAGE_DOCUMENT,
            $agendaItem->getKey(),
            self::DEFAULT_VIEW,
            $actor,
            'hall.document_shown',
        );
    }

    public function showItem(LegislativeSession $session, User $actor): LegislativeSession
    {
        return $this->persist($session, self::STAGE_ITEM, null, null, $actor, 'hall.item_shown');
    }

    /**
     * Pin the latest closed roll of the current item on the hall board.
     */
    public function showResults(LegislativeSession $session, AgendaItem $agendaItem, User $actor): LegislativeSession
    {
        abort_unless($agendaItem->session_id === $session->getKey(), 422);

        if ($agendaItem->status !== 'in-progress') {
            throw new InvalidArgumentException('Only the current agenda item can show a previous voting result.');
        }

        if ($agendaItem->voting_open_at !== null) {
            throw new InvalidArgumentException('Voting is open. The live ballot already owns the hall board.');
        }

        $round = app(VotingService::class)->latestClosedRoundWithBallots($session, $agendaItem);

        if ($round === null) {
            throw new InvalidArgumentException('This agenda item has no previous voting result to show.');
        }

        return $this->persist(
            $session,
            self::STAGE_RESULTS,
            $agendaItem->getKey(),
            null,
            $actor,
            'hall.results_shown',
        );
    }

    /**
     * Drop a pinned result when a new vote opens or closes. Document
     * projection stays; only the results pin is a vote-stage overlay.
     */
    public function clearPinnedResults(LegislativeSession $session): void
    {
        $current = $session->hallDisplayState();

        if ($current['stage'] !== self::STAGE_RESULTS) {
            return;
        }

        $session->forceFill([
            'hall_display_stage' => self::STAGE_ITEM,
            'hall_display_agenda_item_id' => null,
            'hall_display_view' => null,
        ])->save();

        $refreshed = $session->fresh() ?? $session;

        event(new HallDisplayChanged($refreshed, self::STAGE_ITEM, null, null));
    }

    /**
     * @param  array{zoom?: mixed, page?: mixed, relative_x?: mixed, relative_y?: mixed}  $view
     * @return array{zoom: float, page: int, relative_x: float, relative_y: float}
     */
    public function updateView(LegislativeSession $session, array $view): array
    {
        $current = $session->hallDisplayState();

        if ($current['stage'] !== self::STAGE_DOCUMENT) {
            throw new InvalidArgumentException('No document is projected to the hall.');
        }

        $normalized = $this->normalizeView($view);

        $session->forceFill([
            'hall_display_view' => $normalized,
        ])->save();

        $refreshed = $session->fresh() ?? $session;

        event(new HallDisplayViewChanged($refreshed, $normalized));

        return $normalized;
    }

    public function clearForAgendaAdvance(LegislativeSession $session): void
    {
        $current = $session->hallDisplayState();

        if ($current['stage'] === self::STAGE_ITEM && $current['agenda_item_id'] === null) {
            return;
        }

        $session->forceFill([
            'hall_display_stage' => self::STAGE_ITEM,
            'hall_display_agenda_item_id' => null,
            'hall_display_view' => null,
        ])->save();

        $refreshed = $session->fresh() ?? $session;

        event(new HallDisplayChanged($refreshed, self::STAGE_ITEM, null, null));
    }

    /**
     * @return array{stage: string, agenda_item_id: string|null, view: array{zoom: float, page: int, relative_x: float, relative_y: float}|null}
     */
    public function payload(LegislativeSession $session): array
    {
        return $session->hallDisplayState();
    }

    /**
     * @param  array{zoom: float, page: int, relative_x: float, relative_y: float}|null  $view
     */
    private function persist(
        LegislativeSession $session,
        string $stage,
        ?string $agendaItemId,
        ?array $view,
        User $actor,
        string $auditEvent,
    ): LegislativeSession {
        $old = $session->hallDisplayState();

        $session->forceFill([
            'hall_display_stage' => $stage,
            'hall_display_agenda_item_id' => $agendaItemId,
            'hall_display_view' => $view,
        ])->save();

        $refreshed = $session->fresh() ?? $session;

        $this->audit->record(
            event: $auditEvent,
            category: 'session',
            auditable: $refreshed,
            actor: $actor,
            old: [
                'hall_display_stage' => $old['stage'],
                'hall_display_agenda_item_id' => $old['agenda_item_id'],
            ],
            new: [
                'hall_display_stage' => $stage,
                'hall_display_agenda_item_id' => $agendaItemId,
            ],
            message: match ($stage) {
                self::STAGE_DOCUMENT => 'Document projected to the hall display.',
                self::STAGE_RESULTS => 'Previous voting result projected to the hall display.',
                default => 'Hall display returned to the agenda item.',
            },
        );

        event(new HallDisplayChanged($refreshed, $stage, $agendaItemId, $view));

        return $refreshed;
    }

    /**
     * @param  array{zoom?: mixed, page?: mixed, relative_x?: mixed, relative_y?: mixed}  $view
     * @return array{zoom: float, page: int, relative_x: float, relative_y: float}
     */
    private function normalizeView(array $view): array
    {
        $zoom = is_numeric($view['zoom'] ?? null) ? (float) $view['zoom'] : self::DEFAULT_VIEW['zoom'];
        $page = is_numeric($view['page'] ?? null) ? (int) $view['page'] : self::DEFAULT_VIEW['page'];
        $relativeX = is_numeric($view['relative_x'] ?? null) ? (float) $view['relative_x'] : 0.0;
        $relativeY = is_numeric($view['relative_y'] ?? null) ? (float) $view['relative_y'] : 0.0;

        return [
            'zoom' => max(0.1, min(10.0, $zoom)),
            'page' => max(1, $page),
            'relative_x' => max(0.0, min(1.0, $relativeX)),
            'relative_y' => max(0.0, min(1.0, $relativeY)),
        ];
    }
}

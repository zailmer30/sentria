<?php

namespace App\Services\Sessions;

use App\Events\AgendaItemChanged;
use App\Events\MotionRecorded;
use App\Models\AgendaItem;
use App\Models\CommitteeReport;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\Motion;
use App\Models\User;
use App\Notifications\DocumentWorkflowOutcome;
use App\Services\Notifications\InAppNotifier;
use App\Services\Workflow\GuardedStateTransition;
use App\States\Document\Archive;
use App\States\Document\CommitteeReport as CommitteeReportState;
use App\States\Session\InSession;
use App\States\Session\Suspended;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CommitteeHourService
{
    public function __construct(
        private readonly AgendaService $agenda,
        private readonly CalendarRoutingService $calendar,
        private readonly GuardedStateTransition $transitions,
        private readonly InAppNotifier $notifier,
    ) {}

    /**
     * @return array{
     *     can_record: bool,
     *     action: 'second-reading'|'archive'|null,
     *     recommendation: string|null,
     *     report_id: string|null
     * }
     */
    public function disposition(LegislativeSession $session, AgendaItem $item, User $viewer): array
    {
        $empty = [
            'can_record' => false,
            'action' => null,
            'recommendation' => null,
            'report_id' => null,
        ];

        if ($item->session_id !== $session->getKey() || $item->status !== 'in-progress') {
            return $empty;
        }

        if (! $this->isCommitteeReportsItem($session, $item)) {
            return $empty;
        }

        $report = $this->plenaryReport($item, ['submitted']);

        if (! $report instanceof CommitteeReport) {
            return $empty;
        }

        $action = $report->routesToSecondReading()
            ? 'second-reading'
            : ($report->routesToArchive() ? 'archive' : null);

        if ($action === null) {
            return $empty;
        }

        $live = $session->status instanceof InSession || $session->status instanceof Suspended;

        return [
            'can_record' => $live && $viewer->can('agenda.manage'),
            'action' => $action,
            'recommendation' => $report->recommendation,
            'report_id' => $report->getKey(),
        ];
    }

    public function recordChairMotion(LegislativeSession $session, AgendaItem $item, User $actor): void
    {
        if (! $actor->can('agenda.manage')) {
            throw new AuthorizationException('Missing permission [agenda.manage] for this action.');
        }

        if ($item->session_id !== $session->getKey()) {
            throw new InvalidArgumentException('sessions.committee_hour.not_current');
        }

        if ($item->status !== 'in-progress') {
            throw new InvalidArgumentException('sessions.committee_hour.not_current');
        }

        if (! $this->isCommitteeReportsItem($session, $item)) {
            throw new InvalidArgumentException('sessions.committee_hour.not_reports');
        }

        $report = $this->plenaryReport($item, ['submitted']);

        if (! $report instanceof CommitteeReport) {
            throw new InvalidArgumentException('sessions.committee_hour.no_report');
        }

        $document = $item->document;

        if (! $document instanceof Document) {
            throw new InvalidArgumentException('sessions.committee_hour.no_report');
        }

        DB::transaction(function () use ($session, $item, $actor, $report, $document): void {
            $report->update([
                'status' => 'adopted',
                'adopted_at' => now(),
            ]);

            if ($report->routesToSecondReading()) {
                $this->calendar->placeAfterCommitteeHour($session, $document, $actor);
            } elseif ($report->routesToArchive()) {
                $this->archiveFromCommitteeHour($document, $actor);
            } else {
                throw new InvalidArgumentException('sessions.committee_hour.no_report');
            }

            $this->recordFloorMotion($session, $item, $actor, $report);

            $item->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
        });

        try {
            $this->agenda->advance($session);
        } catch (InvalidArgumentException) {
            // The report is already disposed; the clerk can advance separately.
        }

        $session->refresh();
        $current = $this->agenda->currentItem($session);
        $next = $this->agenda->nextPendingItem($session);
        event(new AgendaItemChanged($session, $current, $next));
    }

    private function archiveFromCommitteeHour(Document $document, User $actor): void
    {
        $document->refresh();

        if ($document->status instanceof CommitteeReportState
            && $document->status->canTransitionTo(Archive::class)
        ) {
            $this->transitions->transition($document, Archive::class, $actor);
        }

        $fresh = $document->fresh() ?? $document;
        $fresh->loadMissing('author');

        $this->notifier->send(
            $fresh->author,
            new DocumentWorkflowOutcome($fresh, Archive::$name),
            $actor,
        );
    }

    private function recordFloorMotion(
        LegislativeSession $session,
        AgendaItem $item,
        User $actor,
        CommitteeReport $report,
    ): void {
        $text = $report->routesToSecondReading()
            ? 'The committee chair moved to adopt the committee report and refer the measure to second reading.'
            : 'The committee chair moved to adopt the committee report and archive the measure.';

        $motion = Motion::query()->create([
            'session_id' => $session->getKey(),
            'agenda_item_id' => $item->getKey(),
            'type' => 'main',
            'text' => $text,
            'status' => 'carried',
            'moved_by' => $actor->getKey(),
            'moved_at' => now(),
            'disposed_at' => now(),
            'requires_vote' => false,
        ]);

        event(new MotionRecorded($session, $motion));
    }

    /**
     * The plenary report on this Committee Hour item, whether still awaiting
     * adoption or already adopted this sitting.
     */
    public function floorReport(LegislativeSession $session, AgendaItem $item): ?CommitteeReport
    {
        if ($item->session_id !== $session->getKey()) {
            return null;
        }

        if (! $this->isCommitteeReportsItem($session, $item)) {
            return null;
        }

        return $this->plenaryReport($item, ['submitted', 'adopted']);
    }

    /**
     * @param  list<string>  $statuses
     */
    private function plenaryReport(AgendaItem $item, array $statuses): ?CommitteeReport
    {
        $document = $item->document;

        if (! $document instanceof Document) {
            return null;
        }

        $document->loadMissing(['subjectReports.submitter']);

        return $document->subjectReports
            ->sortByDesc(fn (CommitteeReport $report): int => $report->submitted_at?->getTimestamp() ?? 0)
            ->first(function (CommitteeReport $report) use ($statuses): bool {
                return in_array($report->status, $statuses, true)
                    && $report->isPlenaryRecommendation();
            });
    }

    private function isCommitteeReportsItem(LegislativeSession $session, AgendaItem $item): bool
    {
        if ($item->category === 'committee-reports' && $item->document_id !== null) {
            return true;
        }

        $heading = $this->agenda->attachableHeading($session, 'committee-reports');

        return $heading instanceof AgendaItem && $item->parent_id === $heading->getKey();
    }
}

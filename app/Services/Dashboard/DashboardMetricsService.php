<?php

namespace App\Services\Dashboard;

use App\Enums\Confidentiality;
use App\Enums\DashboardRange;
use App\Enums\DocumentType;
use App\Enums\ProcessingStatus;
use App\Enums\SessionType;
use App\Models\AuditLog;
use App\Models\CommitteeReferral;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\LegislativeSession;
use App\Models\Minutes;
use App\Models\Publication;
use App\Models\User;
use App\Services\Documents\DocumentAccessService;
use App\Services\Sessions\QuorumService;
use App\States\Document\AgendaInclusion;
use App\States\Document\Amendments;
use App\States\Document\Approved;
use App\States\Document\Archive;
use App\States\Document\CommitteeReferral as CommitteeReferralState;
use App\States\Document\CommitteeReport;
use App\States\Document\CommitteeReview;
use App\States\Document\DocumentWorkflowStatus;
use App\States\Document\FinalDocument;
use App\States\Document\PublicPublication;
use App\States\Document\ReadingDeliberation;
use App\States\Document\Registered;
use App\States\Document\Rejected;
use App\States\Document\ReturnedForRevision;
use App\States\Document\SecretariatReview;
use App\States\Document\Submitted;
use App\States\Document\Transmittal;
use App\States\Document\Voting;
use App\States\Minutes\FinalMinutes;
use App\States\Session\DocumentsDistributed;
use App\States\Session\InSession;
use App\States\Session\Scheduled;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The figures behind the dashboard.
 *
 * Two rules hold everywhere in here. Every widget is gated on a permission
 * before its query runs, and returns null when the user lacks it — the shape of
 * the payload tells the frontend what to render, so no widget has to be
 * hidden client-side after the fact. And document counts always pass through
 * {@see DocumentAccessService::scopeVisibleTo()}, because a count is a
 * disclosure: "14 confidential documents in committee review" is information
 * about records the reader may not open.
 */
class DashboardMetricsService
{
    /**
     * The document workflow collapsed to the stages a secretariat actually
     * tracks. Sixteen states is the truth of the machine; seven is the truth of
     * the work.
     *
     * @var array<string, list<class-string<DocumentWorkflowStatus>>>
     */
    private const PIPELINE = [
        'intake' => [Submitted::class, ReturnedForRevision::class, SecretariatReview::class],
        'registered' => [Registered::class],
        'committee' => [CommitteeReferralState::class, CommitteeReview::class, CommitteeReport::class],
        'floor' => [AgendaInclusion::class, ReadingDeliberation::class, Amendments::class, Voting::class],
        'enacted' => [Approved::class, FinalDocument::class, Transmittal::class],
        'rejected' => [Rejected::class],
        'archived' => [Archive::class, PublicPublication::class],
    ];

    /**
     * Four bars on the desk, not seven. Registered still counts as intake;
     * enacted and archived are already on their way to the portal.
     *
     * @var array<string, list<string>>
     */
    private const STANDINGS = [
        'intake' => ['intake', 'registered'],
        'committee' => ['committee'],
        'floor' => ['floor'],
        'publication' => ['enacted', 'archived'],
    ];

    /**
     * @var array<string, list<DocumentType>>
     */
    private const COMPOSITION = [
        'ordinances' => [DocumentType::ProposedOrdinance, DocumentType::Ordinance],
        'resolutions' => [DocumentType::ProposedResolution, DocumentType::Resolution],
        'minutes' => [DocumentType::Minutes],
        'committee_reports' => [DocumentType::CommitteeReport],
    ];

    /** Stages where a record is waiting on a person rather than on a process. */
    private const AWAITING_ACTION = ['intake', 'committee'];

    /** @var list<class-string<DocumentWorkflowStatus>> */
    private const IN_REVIEW = [
        SecretariatReview::class,
        ReturnedForRevision::class,
        CommitteeReferralState::class,
        CommitteeReview::class,
        CommitteeReport::class,
    ];

    /** @var list<class-string<DocumentWorkflowStatus>> */
    private const ENACTED = [
        Approved::class,
        FinalDocument::class,
        Transmittal::class,
    ];

    public function __construct(
        private readonly DocumentAccessService $documentAccess,
        private readonly QuorumService $quorum,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user, DashboardRange $range): array
    {
        return array_filter([
            'documents' => $this->documents($user, $range),
            'session' => $this->session($user),
            'sittings' => $this->sittings($user),
            'minutes' => $this->minutes($user),
            'publications' => $this->publications($user),
            'referrals' => $this->referrals($user),
            'processing' => $this->processing($user),
            'activity' => $this->activity($user),
        ], static fn (mixed $widget): bool => $widget !== null);
    }

    /**
     * @return array{
     *     total: int,
     *     in_range: int,
     *     delta: float|null,
     *     month_added: int,
     *     awaiting_action: int,
     *     due_this_week: int,
     *     enacted_ytd: int,
     *     enacted_prior_year: int,
     *     restricted: int,
     *     in_review: int,
     *     pipeline: list<array{key: string, count: int}>,
     *     standings: list<array{key: string, count: int}>,
     *     composition: list<array{key: string, count: int}>,
     *     recent: list<array{id: string, slug: string|null, reference_number: string|null, title: string, status: string, status_label: string, submitted_at: string|null}>,
     *     intake: list<array{bucket: string, count: int, filed: int, published: int}>
     * }|null
     */
    private function documents(User $user, DashboardRange $range): ?array
    {
        if (! $user->can('documents.viewAny')) {
            return null;
        }

        $counts = $this->visibleDocuments($user)
            ->getQuery()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $byStage = [];
        $awaiting = 0;
        $inReview = 0;

        foreach (self::PIPELINE as $stage => $states) {
            $total = 0;

            foreach ($states as $state) {
                $total += (int) $counts->get($state::$name, 0);
            }

            $byStage[] = ['key' => $stage, 'count' => $total];

            if (in_array($stage, self::AWAITING_ACTION, true)) {
                $awaiting += $total;
            }
        }

        foreach (self::IN_REVIEW as $state) {
            $inReview += (int) $counts->get($state::$name, 0);
        }

        $stageIndex = [];

        foreach ($byStage as $stage) {
            $stageIndex[$stage['key']] = $stage['count'];
        }

        $standings = [];

        foreach (self::STANDINGS as $key => $parts) {
            $standings[] = [
                'key' => $key,
                'count' => array_sum(array_map(
                    static fn (string $part): int => $stageIndex[$part] ?? 0,
                    $parts,
                )),
            ];
        }

        $year = (int) CarbonImmutable::now()->year;
        $since = $range->since();
        $inRange = $this->visibleDocuments($user)->where('submitted_at', '>=', $since)->count();
        $previous = $this->visibleDocuments($user)
            ->whereBetween('submitted_at', [$range->previousSince(), $since])
            ->count();

        $enactedNames = array_map(
            static fn (string $state): string => $state::$name,
            self::ENACTED,
        );

        return [
            'total' => (int) $counts->sum(),
            'in_range' => $inRange,
            'delta' => $this->delta($inRange, $previous),
            'month_added' => $this->visibleDocuments($user)
                ->where('submitted_at', '>=', CarbonImmutable::now()->startOfMonth())
                ->count(),
            'awaiting_action' => $awaiting,
            'due_this_week' => $user->can('referrals.viewAny')
                ? CommitteeReferral::query()
                    ->whereNull('completed_at')
                    ->whereNotNull('due_at')
                    ->whereBetween('due_at', [
                        CarbonImmutable::now()->startOfWeek(),
                        CarbonImmutable::now()->endOfWeek(),
                    ])
                    ->count()
                : 0,
            'enacted_ytd' => $this->visibleDocuments($user)
                ->whereIn('status', $enactedNames)
                ->whereYear('submitted_at', $year)
                ->count(),
            'enacted_prior_year' => $this->visibleDocuments($user)
                ->whereIn('status', $enactedNames)
                ->whereYear('submitted_at', $year - 1)
                ->count(),
            'restricted' => $this->visibleDocuments($user)
                ->whereIn('confidentiality', [
                    Confidentiality::Restricted->value,
                    Confidentiality::Confidential->value,
                ])
                ->count(),
            'in_review' => $inReview,
            'pipeline' => $byStage,
            'standings' => $standings,
            'composition' => $this->composition($user),
            'recent' => $this->recentFilings($user),
            'intake' => $this->intakeSeries($user, $range),
        ];
    }

    /**
     * @return list<array{key: string, count: int}>
     */
    private function composition(User $user): array
    {
        $counts = $this->visibleDocuments($user)
            ->getQuery()
            ->select('document_type', DB::raw('count(*) as aggregate'))
            ->groupBy('document_type')
            ->pluck('aggregate', 'document_type');

        $series = [];

        foreach (self::COMPOSITION as $key => $types) {
            $total = 0;

            foreach ($types as $type) {
                $total += (int) $counts->get($type->value, 0);
            }

            $series[] = ['key' => $key, 'count' => $total];
        }

        return $series;
    }

    /**
     * @return list<array{id: string, slug: string|null, reference_number: string|null, title: string, status: string, status_label: string, submitted_at: string|null}>
     */
    private function recentFilings(User $user): array
    {
        return $this->visibleDocuments($user)
            ->latest('submitted_at')
            ->limit(4)
            ->get(['id', 'slug', 'title', 'reference_number', 'status', 'submitted_at'])
            ->map(function (Document $document): array {
                $status = $document->status;

                return [
                    'id' => (string) $document->getKey(),
                    'slug' => $document->slug,
                    'reference_number' => $document->reference_number,
                    'title' => (string) $document->title,
                    'status' => $status instanceof DocumentWorkflowStatus ? $status::getMorphClass() : (string) $status,
                    'status_label' => $status instanceof DocumentWorkflowStatus ? $status->label() : (string) $status,
                    'submitted_at' => $document->submitted_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * Filed documents per bucket across the window. Empty buckets are emitted
     * as zero: a gap in a chart has to mean "nothing was filed", not
     * "nothing was recorded". Published is the second series so the area chart
     * can show filings against what actually reached the portal.
     *
     * @return list<array{bucket: string, count: int, filed: int, published: int}>
     */
    private function intakeSeries(User $user, DashboardRange $range): array
    {
        $unit = $range->bucket();
        $since = $range->since();

        $filedRows = $this->bucketedCounts($user, 'submitted_at', $unit, $since);
        $publishedRows = $this->bucketedCounts($user, 'published_at', $unit, $since);

        $series = [];
        $cursor = match ($unit) {
            'week' => $since->startOfWeek(),
            'month' => $since->startOfMonth(),
            default => $since,
        };
        $end = CarbonImmutable::now()->endOfDay();

        while ($cursor <= $end) {
            $key = $cursor->format('Y-m-d');
            $filed = (int) ($filedRows->get($key) ?? 0);

            $series[] = [
                'bucket' => $key,
                'count' => $filed,
                'filed' => $filed,
                'published' => (int) ($publishedRows->get($key) ?? 0),
            ];

            $cursor = match ($unit) {
                'week' => $cursor->addWeek(),
                'month' => $cursor->addMonth(),
                default => $cursor->addDay(),
            };
        }

        return $series;
    }

    /**
     * @return Collection<string, int|string>
     */
    private function bucketedCounts(User $user, string $column, string $unit, CarbonImmutable $since): Collection
    {
        $truncated = match ($unit) {
            'week' => DB::raw("date_trunc('week', {$column})::date as bucket"),
            'month' => DB::raw("date_trunc('month', {$column})::date as bucket"),
            default => DB::raw("date_trunc('day', {$column})::date as bucket"),
        };

        return $this->visibleDocuments($user)
            ->getQuery()
            ->whereNotNull($column)
            ->where($column, '>=', $since)
            ->select(
                $truncated,
                DB::raw('count(*) as aggregate'),
            )
            ->groupBy('bucket')
            ->pluck('aggregate', 'bucket');
    }

    /**
     * @return array{
     *     live: bool,
     *     id: string,
     *     title: string,
     *     session_number: string|null,
     *     status: string,
     *     starts_at: string|null,
     *     venue: string|null,
     *     quorum: array{seated_count: int, present_count: int, required: int, met: bool}|null,
     *     agenda_count: int
     * }|null
     */
    private function session(User $user): ?array
    {
        if (! $user->can('sessions.viewAny')) {
            return null;
        }

        $session = $this->featuredSession();

        if ($session === null) {
            return null;
        }

        $live = $session->status instanceof InSession
            || $session->status::getMorphClass() === InSession::$name;

        $quorum = $live && $user->can('attendance.viewAny')
            ? $this->quorum->forSession($session)->toArray()
            : null;

        return [
            'live' => $live,
            'id' => (string) $session->getKey(),
            'title' => (string) $session->title,
            'session_number' => $session->session_number,
            'status' => $session->status::getMorphClass(),
            'starts_at' => ($live ? $session->actual_start_at : $session->scheduled_start_at)?->toIso8601String(),
            'venue' => $session->venue,
            'quorum' => $quorum,
            'agenda_count' => (int) ($session->agenda_items_count ?? 0),
        ];
    }

    /**
     * The next few sittings on the calendar, live first. The hero already
     * names the featured session; this list is the week ahead.
     *
     * @return list<array{id: string, title: string, type: string, type_label: string, starts_at: string|null}>|null
     */
    private function sittings(User $user): ?array
    {
        if (! $user->can('sessions.viewAny')) {
            return null;
        }

        return $this->upcomingSessions()
            ->withCount('agendaItems')
            ->limit(3)
            ->get()
            ->map(function (LegislativeSession $session): array {
                $type = SessionType::tryFrom((string) $session->type);

                return [
                    'id' => (string) $session->getKey(),
                    'title' => (string) $session->title,
                    'type' => (string) $session->type,
                    'type_label' => $type?->label() ?? (string) $session->type,
                    'starts_at' => $session->scheduled_start_at?->toIso8601String(),
                ];
            })
            ->all();
    }

    /**
     * @return array{awaiting: int, finalized: int, total: int}|null
     */
    private function minutes(User $user): ?array
    {
        if (! $user->can('minutes.viewAny')) {
            return null;
        }

        $counts = Minutes::query()
            ->select('status', DB::raw('count(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $settled = (int) $counts->get(FinalMinutes::$name, 0)
            + (int) $counts->get(\App\States\Minutes\Archive::$name, 0);

        return [
            'awaiting' => (int) $counts->sum() - $settled,
            'finalized' => $settled,
            'total' => (int) $counts->sum(),
        ];
    }

    /**
     * @return array{live: int, in_review: int}|null
     */
    private function publications(User $user): ?array
    {
        if (! $user->can('publications.viewAny')) {
            return null;
        }

        return [
            'live' => Publication::query()->live()->count(),
            'in_review' => Publication::query()
                ->whereIn('status', ['secretariat-review', 'publication-review', 'mark-public'])
                ->count(),
        ];
    }

    /**
     * @return array{open: int, overdue: int, items: list<array{
     *     id: string,
     *     document_title: string,
     *     document_slug: string|null,
     *     committee: string|null,
     *     due_at: string|null,
     *     overdue: bool
     * }>}|null
     */
    private function referrals(User $user): ?array
    {
        if (! $user->can('referrals.viewAny')) {
            return null;
        }

        // Open is defined by the absence of a completion timestamp rather than
        // by a status string: the timestamp is what the overdue check reads,
        // and the two must not be able to disagree.
        $open = CommitteeReferral::query()->whereNull('completed_at');

        $overdue = (clone $open)
            ->whereNotNull('due_at')
            ->where('due_at', '<', CarbonImmutable::now());

        // The list shows what is closest to biting: overdue first, then the
        // soonest deadline. A referral with no due date cannot be late.
        $soonest = (clone $open)
            ->with(['document:id,title,slug', 'committee:id,name'])
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->limit(5)
            ->get();

        $items = [];

        foreach ($soonest as $referral) {
            $document = $referral->document;

            if (! $document instanceof Document) {
                continue;
            }

            $items[] = [
                'id' => (string) $referral->getKey(),
                'document_title' => $document->title,
                'document_slug' => $document->slug,
                'committee' => $referral->committee?->name,
                'due_at' => $referral->due_at?->toIso8601String(),
                'overdue' => $referral->isOverdue(),
            ];
        }

        return [
            'open' => $open->count(),
            'overdue' => $overdue->count(),
            'items' => $items,
        ];
    }

    /**
     * How far the three back-office queues have got: OCR on the current file,
     * referrals that have come back, publications that have gone live.
     *
     * @return list<array{key: string, done: int, total: int, percent: int}>|null
     */
    private function processing(User $user): ?array
    {
        $items = [];

        if ($user->can('documents.viewAny')) {
            $versions = DocumentVersion::query()
                ->where('is_current', true)
                ->whereIn('document_id', $this->visibleDocuments($user)->select('documents.id'));

            $total = (clone $versions)->count();
            $done = (clone $versions)
                ->where('processing_status', ProcessingStatus::Completed)
                ->count();

            $items[] = $this->queueItem('ocr', $done, $total);
        }

        if ($user->can('referrals.viewAny')) {
            $total = CommitteeReferral::query()->count();
            $done = CommitteeReferral::query()->whereNotNull('completed_at')->count();
            $items[] = $this->queueItem('committee', $done, $total);
        }

        if ($user->can('publications.viewAny')) {
            $total = Publication::query()->whereNull('unpublished_at')->count();
            $done = Publication::query()->live()->count();
            $items[] = $this->queueItem('publication', $done, $total);
        }

        return $items === [] ? null : $items;
    }

    /**
     * @return array{key: string, done: int, total: int, percent: int}
     */
    private function queueItem(string $key, int $done, int $total): array
    {
        return [
            'key' => $key,
            'done' => $done,
            'total' => $total,
            'percent' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
        ];
    }

    /**
     * @return list<array{
     *     id: string,
     *     event: string|null,
     *     category: string|null,
     *     message: string|null,
     *     actor: string|null,
     *     is_ai_actor: bool,
     *     occurred_at: string|null
     * }>|null
     */
    private function activity(User $user): ?array
    {
        if (! $user->can('audit.viewAny')) {
            return null;
        }

        return array_values(AuditLog::query()
            ->latest('sequence')
            ->limit(4)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => (string) $log->getKey(),
                'event' => $log->event,
                'category' => $log->category,
                'message' => $log->message,
                'actor' => $log->actor_label,
                'is_ai_actor' => (bool) $log->is_ai_actor,
                'occurred_at' => $log->occurred_at?->toIso8601String(),
            ])
            ->all());
    }

    private function featuredSession(): ?LegislativeSession
    {
        $live = LegislativeSession::query()
            ->withCount('agendaItems')
            ->where('status', InSession::$name)
            ->orderByDesc('actual_start_at')
            ->first();

        if ($live !== null) {
            return $live;
        }

        return $this->upcomingSessions()
            ->withCount('agendaItems')
            ->first();
    }

    /** @return Builder<LegislativeSession> */
    private function upcomingSessions(): Builder
    {
        return LegislativeSession::query()
            ->where(function (Builder $query): void {
                $query->where('status', InSession::$name)
                    ->orWhere(function (Builder $query): void {
                        $query->whereIn('status', [
                            Scheduled::$name,
                            DocumentsDistributed::$name,
                        ])->where('scheduled_start_at', '>=', CarbonImmutable::now()->startOfDay());
                    });
            })
            ->orderByRaw('case when status = ? then 0 else 1 end', [InSession::$name])
            ->orderBy('scheduled_start_at');
    }

    /** @return Builder<Document> */
    private function visibleDocuments(User $user): Builder
    {
        return $this->documentAccess->scopeVisibleTo(Document::query(), $user);
    }

    /**
     * Percentage change, rounded to one place. Null when the previous window
     * was empty, because "up from nothing" is not a percentage and rendering it
     * as +100% would overstate what happened.
     */
    private function delta(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}

<?php

namespace App\Http\Resources;

use App\Enums\SessionType;
use App\Models\AgendaItem;
use App\Models\Bookmark;
use App\Models\Committee;
use App\Models\CommitteeReport;
use App\Models\Document;
use App\Models\FloorRecognitionRequest;
use App\Models\LegislativeSession;
use App\Models\MinutesCorrection;
use App\Models\Motion;
use App\Models\PrivateNote;
use App\Models\SessionAttendance;
use App\Models\SessionGuest;
use App\Models\User;
use App\Models\Vote;
use App\Services\Documents\DocumentAccessService;
use App\Services\Sessions\AgendaService;
use App\Services\Sessions\AttendanceService;
use App\Services\Sessions\CalendarRoutingService;
use App\Services\Sessions\CommitteeHourService;
use App\Services\Sessions\FloorRecognitionService;
use App\Services\Sessions\FloorReferralService;
use App\Services\Sessions\QuorumService;
use App\Services\Sessions\VotingService;
use App\States\Session\DocumentsDistributed;
use App\States\Session\InSession;
use App\States\Session\Scheduled;
use App\States\Session\Suspended;
use Illuminate\Support\Carbon;

class SessionResource
{
    /**
     * @return array<string, mixed>
     */
    public static function summary(LegislativeSession $session): array
    {
        $session->loadMissing(['presidingOfficer', 'secretary']);

        return [
            'id' => $session->getKey(),
            'session_number' => $session->session_number,
            'title' => $session->title,
            'type' => $session->type,
            'type_label' => SessionType::tryFrom((string) $session->type)?->label() ?? $session->type,
            'status' => $session->status->getValue(),
            'status_label' => $session->status->label(),
            'scheduled_start_at' => $session->scheduled_start_at?->toIso8601String(),
            'scheduled_end_at' => $session->scheduled_end_at?->toIso8601String(),
            'venue' => $session->venue,
            'presiding_officer' => $session->presidingOfficer?->display_name,
            'secretary' => $session->secretary?->display_name,
            'attached_document_count' => (int) ($session->attached_document_count ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(LegislativeSession $session, ?User $viewer = null): array
    {
        $session->loadMissing([
            'agendaItems.document.currentVersion',
            'agendaItems.minutesCorrections.recorder',
            'presidingOfficer',
            'secretary',
        ]);

        return [
            ...self::summary($session),
            'legislative_year' => $session->legislative_year,
            'actual_start_at' => $session->actual_start_at?->toIso8601String(),
            'actual_end_at' => $session->actual_end_at?->toIso8601String(),
            'adjourned_at' => $session->adjourned_at?->toIso8601String(),
            'recess_ends_at' => $session->recess_ends_at?->toIso8601String(),
            'recess_remaining_seconds' => $session->recessRemainingSeconds(),
            'agenda_locked_at' => $session->agenda_locked_at?->toIso8601String(),
            'documents_distributed_at' => $session->documents_distributed_at?->toIso8601String(),
            'seated_member_count' => $session->seated_member_count,
            'quorum_required' => $session->quorum_required,
            'is_public' => $session->is_public,
            'recording_enabled' => (bool) $session->recording_enabled,
            'defer_heading_votes' => (bool) $session->defer_heading_votes,
            'capture_mode' => $session->chamberFeed()->value,
            'notes' => $session->notes,
            'secretariat_minutes' => $session->secretariat_minutes,
            'presiding_officer_id' => $session->presiding_officer_id,
            'secretary_id' => $session->secretary_id,
            'advance_blocked_reason' => app(AgendaService::class)->advanceBlockedReason($session),
            'agenda_items' => $session->agendaItems->map(function (AgendaItem $item) use ($session, $viewer): array {
                return self::agendaItem($item, $session, $viewer instanceof User ? $viewer : null);
            })->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function agendaItem(AgendaItem $item, ?LegislativeSession $session = null, ?User $viewer = null): array
    {
        $item->loadMissing('document.currentVersion');

        $payload = [
            'id' => $item->getKey(),
            'parent_id' => $item->parent_id,
            'position' => $item->position,
            'item_number' => $item->item_number,
            'title' => $item->title,
            'description' => $item->description,
            'category' => $item->category,
            'status' => $item->status,
            'document_id' => $item->document_id,
            'reading_number' => $item->reading_number,
            'document' => $item->document ? [
                'id' => $item->document->getKey(),
                'slug' => $item->document->slug,
                'title' => $item->document->title,
                ...self::documentPreview($item->document, $viewer),
            ] : null,
            'requires_vote' => $item->requires_vote,
            'voting_round' => (int) ($item->voting_round ?? 0),
            'voting_open' => $item->voting_open_at !== null,
            'started_at' => $item->started_at?->toIso8601String(),
            'completed_at' => $item->completed_at?->toIso8601String(),
            'can_second_reading' => false,
            'can_postpone' => false,
            'can_undo' => false,
            'can_third_reading' => false,
            'placed_on_third_reading' => false,
            'carried_to' => null,
            'committee_hour_action' => null,
            'can_record_committee_hour_motion' => false,
            'committee_hour_recommendation' => null,
            'committee_report' => null,
            'minutes_corrections' => $item->relationLoaded('minutesCorrections')
                ? $item->minutesCorrections->map(fn (MinutesCorrection $row): array => self::minutesCorrection($row))->values()->all()
                : [],
        ];

        if ($session instanceof LegislativeSession && $viewer instanceof User) {
            $flags = app(CalendarRoutingService::class)->actionFlags($session, $item, $viewer);
            $payload['can_second_reading'] = $flags['can_second_reading'];
            $payload['can_postpone'] = $flags['can_postpone'];
            $payload['can_undo'] = $flags['can_undo'];
            $payload['can_third_reading'] = $flags['can_third_reading'];
            $payload['placed_on_third_reading'] = $flags['placed_on_third_reading'];
            $payload['carried_to'] = $flags['carried_to'];
            $hour = app(CommitteeHourService::class)->disposition($session, $item, $viewer);
            $payload['committee_hour_action'] = $hour['action'];
            $payload['can_record_committee_hour_motion'] = $hour['can_record'];
            $payload['committee_hour_recommendation'] = $hour['recommendation'];
            $payload['committee_report'] = self::committeeHourReport($session, $item, $viewer);
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public static function minutesCorrection(MinutesCorrection $correction): array
    {
        return [
            'id' => $correction->getKey(),
            'agenda_item_id' => $correction->agenda_item_id,
            'as_written' => $correction->as_written,
            'should_read' => $correction->should_read,
            'page_number' => $correction->page_number,
            'recorded_by' => $correction->recorder?->display_name,
            'applied_at' => $correction->applied_at?->toIso8601String(),
            'created_at' => $correction->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function attendance(SessionAttendance $record): array
    {
        $record->loadMissing('user');

        return [
            'id' => $record->getKey(),
            'user_id' => $record->user_id,
            'display_name' => $record->user?->display_name,
            'avatar_url' => $record->user?->avatarUrl(),
            'position_title' => $record->user?->position_title,
            'district' => $record->user?->district,
            'status' => $record->status,
            'checked_in_at' => $record->checked_in_at?->toIso8601String(),
            'checked_out_at' => $record->checked_out_at?->toIso8601String(),
            'remarks' => $record->remarks,
        ];
    }

    /**
     * @return array{id: string, name: string, organization: string|null, speaking_topic: string|null, status: string}
     */
    public static function guest(SessionGuest $guest): array
    {
        return [
            'id' => (string) $guest->getKey(),
            'name' => $guest->name,
            'organization' => $guest->organization,
            'speaking_topic' => $guest->speaking_topic,
            'status' => $guest->status,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function privateNote(PrivateNote $note): array
    {
        return [
            'id' => $note->getKey(),
            'body' => $note->body,
            'page_number' => $note->page_number,
            'notable_type' => $note->notable_type,
            'notable_id' => $note->notable_id,
            'updated_at' => $note->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function bookmark(Bookmark $bookmark): array
    {
        return [
            'id' => $bookmark->getKey(),
            'label' => $bookmark->label,
            'bookmarkable_type' => $bookmark->bookmarkable_type,
            'bookmarkable_id' => $bookmark->bookmarkable_id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function floor(
        LegislativeSession $session,
        User $viewer,
        QuorumService $quorum,
        AgendaService $agenda,
        DocumentAccessService $access,
        VotingService $voting,
        ?AttendanceService $attendance = null,
    ): array {
        $session->load([
            'agendaItems.document.currentVersion',
            'agendaItems.document.author',
            'agendaItems.document.committee',
            'agendaItems.document.referrals.committee',
            'agendaItems.document.subjectReports.submitter',
            'agendaItems.minutesCorrections.recorder',
            'presidingOfficer',
            'secretary',
            'attendance.user',
            'motions.mover',
            'motions.seconder',
            'floorRecognitionRequests.member',
            'floorRecognitionRequests.agendaItem',
        ]);

        // Chamber roll call needs a roster. Seed seated members as absent when
        // the session is live and nobody has been checked in yet — otherwise
        // the dashboard has tallies with an empty member board.
        if (
            $attendance !== null
            && ($session->status instanceof InSession || $session->status instanceof Suspended)
        ) {
            $session->setRelation('attendance', $attendance->ensureRoster($session));
        }

        $current = $agenda->currentItem($session);
        $next = $agenda->nextItemAfterAdvance($session, $current);
        $previous = $agenda->previousCompletedItem($session);

        $documentIds = $session->agendaItems->pluck('document_id')->filter()->values();

        $notes = PrivateNote::query()
            ->ownedBy($viewer)
            ->where(function ($query) use ($session, $documentIds): void {
                $query->where(function ($inner) use ($session): void {
                    $inner->where('notable_type', LegislativeSession::class)
                        ->where('notable_id', $session->getKey());
                });

                if ($documentIds->isNotEmpty()) {
                    $query->orWhere(function ($inner) use ($documentIds): void {
                        $inner->where('notable_type', Document::class)
                            ->whereIn('notable_id', $documentIds);
                    });
                }
            })
            ->latest()
            ->limit(20)
            ->get();

        $bookmarks = Bookmark::query()
            ->where('user_id', $viewer->getKey())
            ->where(function ($query) use ($session, $documentIds): void {
                $query->where(function ($inner) use ($session): void {
                    $inner->where('bookmarkable_type', LegislativeSession::class)
                        ->where('bookmarkable_id', $session->getKey());
                });

                if ($documentIds->isNotEmpty()) {
                    $query->orWhere(function ($inner) use ($documentIds): void {
                        $inner->where('bookmarkable_type', Document::class)
                            ->whereIn('bookmarkable_id', $documentIds);
                    });
                }
            })
            ->get();

        $documentLink = null;

        if ($current?->document && $access->userCanView($viewer, $current->document)) {
            $documentLink = [
                'slug' => $current->document->slug,
                'title' => $current->document->title,
            ];
        }

        $elapsedSeconds = $session->actual_start_at
            ? (int) $session->actual_start_at->diffInSeconds(now(), false)
            : null;

        $activeRound = $current && $current->voting_open_at !== null ? (int) $current->voting_round : 0;
        $tallies = $current && $activeRound > 0
            ? $voting->tallies($session, $current, $activeRound)
            : ['yes' => 0, 'no' => 0, 'abstain' => 0, 'inhibit' => 0, 'total' => 0];

        $namedRoll = $viewer->can('openVoting', $session) || $viewer->can('closeVoting', $session);
        $liveSilent = $current !== null && $activeRound > 0 && $current->isSilentVotingRound($activeRound);
        $liveMembers = $current !== null && $activeRound > 0
            ? self::votingMembers($session, $current, $activeRound)
            : [];

        $previousRound = $current !== null
            ? $voting->latestClosedRoundWithBallots($session, $current)
            : null;
        $previousSilent = $previousRound !== null && $current !== null
            ? $current->isSilentVotingRound($previousRound)
            : false;
        $previousMembers = $previousRound !== null && $current !== null
            ? self::votingMembers($session, $current, $previousRound)
            : [];
        $previousResult = $previousRound !== null && $current !== null
            ? [
                'round' => $previousRound,
                'tallies' => $voting->tallies($session, $current, $previousRound),
                'silent' => $previousSilent,
                'members' => ($previousSilent && ! $namedRoll) ? [] : $previousMembers,
            ]
            : null;

        $userVote = null;
        if ($current && $activeRound > 0) {
            $ballot = Vote::query()
                ->where('session_id', $session->getKey())
                ->where('agenda_item_id', $current->getKey())
                ->where('voting_round', $activeRound)
                ->where('user_id', $viewer->getKey())
                ->orderByDesc('cast_at')
                ->orderByDesc('id')
                ->first();

            $userVote = $ballot?->choice;
        }

        $recognition = app(FloorRecognitionService::class)->snapshot($session, $viewer);
        $recognized = $recognition['recognized'];

        return [
            'session' => self::detail($session),
            'current_item' => $current ? self::agendaItem($current, $session, $viewer) : null,
            'next_item' => $next ? self::agendaItem($next, $session, $viewer) : null,
            'previous_item' => $previous ? self::agendaItem($previous, $session, $viewer) : null,
            'quorum' => $quorum->forSession($session)->toArray(),
            'attendance' => $session->attendance->map(fn (SessionAttendance $a): array => self::attendance($a))->values()->all(),
            'private_notes' => $notes->map(fn (PrivateNote $n): array => self::privateNote($n))->values()->all(),
            'bookmarks' => $bookmarks->map(fn (Bookmark $b): array => self::bookmark($b))->values()->all(),
            'document_link' => $documentLink,
            'reading_pack' => $session->agendaItems
                ->sortBy('position')
                ->values()
                ->map(fn (AgendaItem $item): array => self::readingPackItem($item, $viewer, $access, $session))
                ->all(),
            'minutes_corrections' => $session->agendaItems
                ->flatMap(fn (AgendaItem $item) => $item->minutesCorrections)
                ->sortBy('created_at')
                ->values()
                ->map(fn (MinutesCorrection $row): array => self::minutesCorrection($row))
                ->all(),
            'committees' => $viewer->can('documents.refer')
                ? Committee::query()
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Committee $committee): array => [
                        'id' => $committee->getKey(),
                        'name' => $committee->name,
                    ])
                    ->values()
                    ->all()
                : [],
            'hall_display' => $session->hallDisplayState(),
            'elapsed_seconds' => $elapsedSeconds !== null ? max(0, $elapsedSeconds) : null,
            'voting' => [
                'open' => $activeRound > 0,
                'round' => $activeRound,
                'silent' => $liveSilent,
                'tallies' => $tallies,
                'user_vote' => $userVote,
                'electronic_is_binding' => $voting->electronicIsBinding(),
                'awaiting_count' => self::awaitingBallotCount($liveMembers),
                'members' => ($liveSilent && ! $namedRoll) ? [] : $liveMembers,
                'previous' => $previousResult,
            ],
            'motions' => $session->motions
                ->sortByDesc('moved_at')
                ->take(10)
                ->map(fn (Motion $motion): array => self::motion($motion, $viewer))
                ->values()
                ->all(),
            'recognition' => $recognition,
            'calendar_docket' => app(CalendarRoutingService::class)->docket($session, $viewer),
            'advance_blocked_reason' => app(AgendaService::class)->advanceBlockedReason($session, $current),
            'can' => [
                'manage_agenda' => $viewer->can('agenda.manage')
                    && ($session->status instanceof InSession || $session->status instanceof Suspended),
                'start' => $viewer->can('start', $session)
                    && ($session->status instanceof Scheduled || $session->status instanceof DocumentsDistributed),
                'suspend' => $viewer->can('suspend', $session)
                    && $session->status instanceof InSession,
                'resume' => $viewer->can('resume', $session)
                    && $session->status instanceof Suspended,
                'adjourn' => $viewer->can('adjourn', $session)
                    && ($session->status instanceof InSession || $session->status instanceof Suspended),
                'record_attendance' => $viewer->can('recordAttendance', $session),
                'create_motion' => $viewer->can('create', Motion::class)
                    && ($session->status instanceof InSession || $session->status instanceof Suspended),
                'open_voting' => $viewer->can('openVoting', $session)
                    && $session->status instanceof InSession,
                'close_voting' => $viewer->can('closeVoting', $session)
                    && $session->status instanceof InSession,
                'cast_vote' => $viewer->can('castVote', $session)
                    && $session->status instanceof InSession,
                'update_voting_mode' => $viewer->can('updateVotingMode', $session)
                    && ($session->status instanceof InSession || $session->status instanceof Suspended),
                'begin_heading_votes' => $viewer->can('agenda.manage')
                    && ($session->status instanceof InSession || $session->status instanceof Suspended)
                    && $agenda->canBeginHeadingVotes($session, $current),
                'control_hall_display' => $viewer->can('controlHallDisplay', $session),
                'refer' => $viewer->can('documents.refer')
                    && $session->status instanceof InSession,
                'seek_recognition' => $viewer->can('create', [FloorRecognitionRequest::class, $session]),
                'record_spoken_motion' => (
                    $viewer->can('create', Motion::class) || $viewer->can('agenda.manage')
                ) && $recognized !== null
                    && ($session->status instanceof InSession || $session->status instanceof Suspended),
                'record_minutes' => $viewer->can('recordMinutes', $session),
                'record_minutes_corrections' => $viewer->can('agenda.manage')
                    && ($session->status instanceof InSession || $session->status instanceof Suspended),
                'apply_minutes_corrections' => $viewer->can('agenda.manage'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function recognitionRequest(FloorRecognitionRequest $request, ?User $viewer = null): array
    {
        $member = $request->member;
        $item = $request->agendaItem;

        $canCancel = false;
        $canRecognize = false;
        $canDismiss = false;

        if ($viewer !== null) {
            $canCancel = $viewer->can('cancel', $request);
            $canRecognize = $viewer->can('recognize', $request);
            $canDismiss = $viewer->can('dismiss', $request);
        }

        return [
            'id' => $request->getKey(),
            'user_id' => $request->user_id,
            'display_name' => $member?->display_name,
            'avatar_url' => $member?->avatarUrl(),
            'agenda_item_id' => $request->agenda_item_id,
            'item_number' => $item?->item_number,
            'item_title' => $item?->title,
            'status' => $request->status,
            'raised_at' => $request->raised_at instanceof Carbon
                ? $request->raised_at->toIso8601String()
                : null,
            'can' => [
                'cancel' => $canCancel,
                'recognize' => $canRecognize,
                'dismiss' => $canDismiss,
            ],
        ];
    }

    /**
     * Agenda row for the member floor reader: ACL-filtered preview URLs, no annotation JSON.
     *
     * @return array<string, mixed>
     */
    public static function readingPackItem(
        AgendaItem $item,
        User $viewer,
        DocumentAccessService $access,
        ?LegislativeSession $session = null,
    ): array {
        $item->loadMissing([
            'document.currentVersion',
            'document.author',
            'document.committee',
            'document.referrals.committee',
            'document.subjectReports.submitter',
        ]);

        $documentPayload = null;
        $document = $item->document;

        if ($document !== null && $access->userCanView($viewer, $document)) {
            $documentPayload = [
                'id' => $document->getKey(),
                'slug' => $document->slug,
                'title' => $document->title,
                'reference_number' => $document->reference_number,
                'document_type' => $document->document_type->value,
                'document_type_label' => $document->document_type->label(),
                'status' => $document->status->getValue(),
                'status_label' => $document->status->label(),
                'author' => $document->authorName(),
                'abstract' => $document->abstract,
                'committee_id' => $document->committee_id,
                'committee' => $document->committee?->name,
                'open_referral' => DocumentResource::openReferral($document),
                ...self::documentPreview($document, $viewer, $access),
            ];
        }

        $payload = [
            'id' => $item->getKey(),
            'parent_id' => $item->parent_id,
            'position' => $item->position,
            'item_number' => $item->item_number,
            'title' => $item->title,
            'description' => $item->description,
            'category' => $item->category,
            'status' => $item->status,
            'requires_vote' => $item->requires_vote,
            'voting_open' => $item->voting_open_at !== null,
            'reading_number' => $item->reading_number,
            'title_only' => $item->reading_number === 1,
            'can_refer' => $session !== null && app(FloorReferralService::class)->allowsRefer($session, $item, $viewer),
            'can_edit_referral' => $session !== null && app(FloorReferralService::class)->allowsEdit($session, $item, $viewer),
            'can_second_reading' => false,
            'can_postpone' => false,
            'can_undo' => false,
            'can_third_reading' => false,
            'placed_on_third_reading' => false,
            'carried_to' => null,
            'committee_hour_action' => null,
            'can_record_committee_hour_motion' => false,
            'committee_hour_recommendation' => null,
            'committee_report' => $session !== null && $documentPayload !== null
                ? self::committeeHourReport($session, $item, $viewer)
                : null,
            'document' => $documentPayload,
        ];

        if ($session !== null) {
            $flags = app(CalendarRoutingService::class)->actionFlags($session, $item, $viewer);
            $payload['can_second_reading'] = $flags['can_second_reading'];
            $payload['can_postpone'] = $flags['can_postpone'];
            $payload['can_undo'] = $flags['can_undo'];
            $payload['can_third_reading'] = $flags['can_third_reading'];
            $payload['placed_on_third_reading'] = $flags['placed_on_third_reading'];
            $payload['carried_to'] = $flags['carried_to'];
            $hour = app(CommitteeHourService::class)->disposition($session, $item, $viewer);
            $payload['committee_hour_action'] = $hour['action'];
            $payload['can_record_committee_hour_motion'] = $hour['can_record'];
            $payload['committee_hour_recommendation'] = $hour['recommendation'];
        }

        return $payload;
    }

    /**
     * Unique documents bound to this sitting's agenda, ACL-filtered for the viewer.
     *
     * @return list<array<string, mixed>>
     */
    public static function attachedDocuments(
        LegislativeSession $session,
        User $viewer,
        DocumentAccessService $access,
    ): array {
        $session->loadMissing([
            'agendaItems.document.currentVersion',
            'agendaItems.document.author',
            'agendaItems.document.committee',
        ]);

        $rows = [];
        $seen = [];

        foreach ($session->agendaItems as $item) {
            $document = $item->document;

            if ($document === null || isset($seen[$document->getKey()])) {
                continue;
            }

            if (! $access->userCanView($viewer, $document)) {
                continue;
            }

            $seen[$document->getKey()] = true;
            $rows[] = [
                ...DocumentResource::summary($document),
                ...self::documentPreview($document, $viewer, $access),
                'item_number' => $item->item_number,
                'item_title' => $item->title,
            ];
        }

        return $rows;
    }

    /**
     * Preview URLs are issued only when the viewer may download a scanned PDF.
     *
     * @return array{
     *     version_id: string|null,
     *     mime_type: string|null,
     *     can_preview: bool,
     *     preview_url: string|null,
     *     annotations_url: string|null
     * }
     */
    public static function documentPreview(
        Document $document,
        ?User $viewer = null,
        ?DocumentAccessService $access = null,
    ): array {
        $document->loadMissing('currentVersion');
        $version = $document->currentVersion;
        $gate = $access ?? ($viewer instanceof User ? app(DocumentAccessService::class) : null);
        $canPreview = $viewer instanceof User
            && $gate instanceof DocumentAccessService
            && $version !== null
            && $gate->userCanDownload($viewer, $document)
            && $version->isSafeToServe()
            && $version->mime_type === 'application/pdf';

        return [
            'version_id' => $version?->getKey(),
            'mime_type' => $version?->mime_type,
            'can_preview' => $canPreview,
            'preview_url' => $canPreview
                ? route('documents.versions.preview', [$document, $version])
                : null,
            'annotations_url' => $canPreview
                ? route('documents.versions.annotations.show', [$document, $version])
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function motion(Motion $motion, ?User $viewer = null): array
    {
        $canSecond = false;
        $canWithdraw = false;
        $canRule = false;

        if ($viewer !== null) {
            $canSecond = $viewer->can('second', $motion);
            $canWithdraw = $viewer->can('withdraw', $motion)
                && ! in_array($motion->status, ['withdrawn', 'carried', 'lost', 'ruled_out', 'referred'], true);
            $canRule = $viewer->can('rule', $motion)
                && in_array($motion->status, ['proposed', 'seconded'], true);
        }

        return [
            'id' => $motion->getKey(),
            'text' => $motion->text,
            'type' => $motion->type,
            'status' => $motion->status,
            'agenda_item_id' => $motion->agenda_item_id,
            'mover' => $motion->mover?->display_name,
            'seconder' => $motion->seconder?->display_name,
            'moved_at' => $motion->moved_at instanceof Carbon
                ? $motion->moved_at->toIso8601String()
                : null,
            'voting_round' => $motion->voting_round,
            'can' => [
                'second' => $canSecond,
                'withdraw' => $canWithdraw,
                'rule' => $canRule,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function committeeHourReport(LegislativeSession $session, AgendaItem $item, User $viewer): ?array
    {
        $report = app(CommitteeHourService::class)->floorReport($session, $item);

        if (! $report instanceof CommitteeReport || ! $viewer->can('view', $report)) {
            return null;
        }

        return DocumentResource::floorReport($report);
    }

    /**
     * Chamber roll for a voting round: every attendance row, with the cast
     * choice when present. Absentees appear as pending so the board matches
     * the room. Used for the live ballot and for a pinned previous result.
     *
     * @return list<array<string, mixed>>
     */
    private static function votingMembers(LegislativeSession $session, AgendaItem $item, int $round): array
    {
        $ballots = Vote::latestPerMember(
            Vote::query()
                ->where('session_id', $session->getKey())
                ->where('agenda_item_id', $item->getKey())
                ->where('voting_round', $round)
                ->get(['id', 'user_id', 'choice', 'cast_at']),
        )->keyBy(fn (Vote $vote): string => (string) $vote->user_id);

        $presidingId = $session->presiding_officer_id
            ? (string) $session->presiding_officer_id
            : null;

        return $session->attendance
            ->sortBy(fn (SessionAttendance $row): string => mb_strtolower((string) ($row->user?->display_name ?? '')))
            ->values()
            ->map(function (SessionAttendance $row) use ($ballots, $presidingId): array {
                $userId = (string) $row->user_id;
                /** @var Vote|null $ballot */
                $ballot = $ballots->get($userId);

                return [
                    'id' => $userId,
                    'display_name' => $row->user?->display_name,
                    'avatar_url' => $row->user?->avatarUrl(),
                    'position_title' => $row->user?->position_title,
                    'district' => $row->user?->district,
                    'status' => $row->status,
                    'has_voted' => $ballot !== null,
                    'choice' => $ballot?->choice !== null ? (string) $ballot->choice : null,
                    'cast_at' => $ballot?->cast_at?->toIso8601String(),
                    'is_presiding' => $presidingId !== null && $userId === $presidingId,
                ];
            })
            ->all();
    }

    /**
     * Seated members (present or late) who have not yet cast a ballot.
     *
     * @param  list<array<string, mixed>>  $members
     */
    private static function awaitingBallotCount(array $members): int
    {
        return collect($members)
            ->filter(function (array $member): bool {
                $status = (string) ($member['status'] ?? '');

                return ($status === 'present' || $status === 'late') && ! ($member['has_voted'] ?? false);
            })
            ->count();
    }
}

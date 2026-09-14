<?php

namespace App\Http\Controllers;

use App\Enums\ChamberFeed;
use App\Enums\SessionType;
use App\Events\SessionStateChanged;
use App\Http\Requests\Sessions\StartRecessRequest;
use App\Http\Requests\Sessions\StoreSessionRequest;
use App\Http\Requests\Sessions\UpdateSessionRecordingRequest;
use App\Http\Requests\Sessions\UpdateSessionRequest;
use App\Http\Resources\SessionResource;
use App\Jobs\Sessions\GenerateMinutesDraftJob;
use App\Models\AgendaItem;
use App\Models\Document;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentAccessService;
use App\Services\Sessions\AgendaService;
use App\Services\Sessions\CalendarRoutingService;
use App\Services\Sessions\ChamberCaptureSettings;
use App\Services\Sessions\ChamberChannelService;
use App\Services\Sessions\ChamberRecordingService;
use App\Services\Sessions\QuorumService;
use App\Services\Sessions\SessionNumberAllocator;
use App\Services\Workflow\GuardedStateTransition;
use App\States\Session\Adjourned;
use App\States\Session\AgendaPrepared;
use App\States\Session\Archived;
use App\States\Session\DocumentsDistributed;
use App\States\Session\Draft;
use App\States\Session\Finalized;
use App\States\Session\InSession;
use App\States\Session\MinutesForReview;
use App\States\Session\Scheduled;
use App\States\Session\SessionStatus;
use App\States\Session\Suspended;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SessionController extends Controller
{
    public function __construct(
        private readonly GuardedStateTransition $transitions,
        private readonly AgendaService $agenda,
        private readonly CalendarRoutingService $calendar,
        private readonly QuorumService $quorum,
        private readonly ChamberChannelService $chamberChannels,
        private readonly ChamberRecordingService $chamberRecording,
        private readonly ChamberCaptureSettings $chamberCapture,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', LegislativeSession::class);

        $query = LegislativeSession::query()
            ->with(['presidingOfficer', 'secretary']);

        $this->applyIndexFilters($query, $request);

        $query
            ->orderByRaw('case when status in (?, ?) then 0 when status in (?, ?) then 1 else 2 end', [
                InSession::$name,
                Suspended::$name,
                Draft::$name,
                AgendaPrepared::$name,
            ])
            ->latest('scheduled_start_at');

        $summary = $this->summarizeIndex($query);

        $sessions = $query
            ->withCount([
                'agendaItems as attached_document_count' => fn (Builder $items): Builder => $items->whereNotNull('document_id'),
            ])
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Sessions/Index', [
            'sessions' => $sessions->through(fn (LegislativeSession $session): array => SessionResource::summary($session)),
            'summary' => $summary,
            'filters' => [
                'search' => $request->string('search')->toString() ?: null,
                'type' => $request->string('type')->toString() ?: null,
                'phase' => $request->string('phase')->toString() ?: null,
            ],
            'sessionTypes' => collect(SessionType::cases())->map(fn (SessionType $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ])->values()->all(),
            'can' => [
                'create' => $request->user()?->can('create', LegislativeSession::class) ?? false,
            ],
        ]);
    }

    public function create(Request $request, SessionNumberAllocator $numbers): Response
    {
        $this->authorize('create', LegislativeSession::class);

        $year = (int) ($request->integer('legislative_year') ?: now()->year);

        return Inertia::render('Sessions/Create', [
            'users' => User::query()->where('is_active', true)->orderBy('display_name')->get(['id', 'display_name']),
            'sessionTypes' => $this->sessionTypeOptions($numbers, $year),
        ]);
    }

    public function store(StoreSessionRequest $request, SessionNumberAllocator $numbers): RedirectResponse
    {
        $validated = $request->validated();
        $type = SessionType::from($validated['type']);
        $year = (int) ($validated['legislative_year'] ?? now()->year);

        $session = DB::transaction(function () use ($validated, $type, $year, $numbers): LegislativeSession {
            $identity = $numbers->allocate($type, $year);

            return LegislativeSession::query()->create([
                ...$validated,
                'session_number' => $identity['session_number'],
                'title' => $identity['title'],
                'legislative_year' => $year,
                'status' => Draft::$name,
                'capture_mode' => $this->chamberCapture->defaultFeed()->value,
            ]);
        });

        return redirect()
            ->route('sessions.show', $session)
            ->with('success', 'sessions.created');
    }

    public function documents(Request $request, LegislativeSession $session, DocumentAccessService $access): Response
    {
        $this->authorize('view', $session);

        return Inertia::render('Sessions/Documents', [
            'session' => SessionResource::summary($session),
            'documents' => SessionResource::attachedDocuments($session, $this->requireUser($request), $access),
        ]);
    }

    public function show(LegislativeSession $session): Response
    {
        $this->authorize('view', $session);

        $session->load(['agendaItems.document.currentVersion', 'presidingOfficer', 'secretary', 'attendance.user']);

        $user = request()->user();
        $canManageAgenda = $user?->can('create', AgendaItem::class) ?? false;

        $agendaDocuments = [];

        if ($canManageAgenda) {
            $linkedIds = $session->agendaItems->pluck('document_id')->filter()->all();

            $agendaDocuments = Document::query()
                ->when($linkedIds !== [], fn ($query) => $query->whereKeyNot($linkedIds))
                ->whereIn('status', [
                    'committee-report',
                    'agenda-inclusion',
                    'registered',
                    'committee-referral',
                    'committee-review',
                    'final-document',
                ])
                ->orderByDesc('updated_at')
                ->limit(50)
                ->get(['id', 'title', 'reference_number', 'status', 'document_type', 'current_reading'])
                ->map(fn (Document $document): array => [
                    'id' => $document->getKey(),
                    'title' => $document->title,
                    'reference_number' => $document->reference_number,
                    'status' => $document->status->getValue(),
                    'document_type' => $document->document_type->value,
                    'current_reading' => $document->current_reading,
                ])
                ->values()
                ->all();
        }

        return Inertia::render('Sessions/Show', [
            'session' => SessionResource::detail($session),
            'quorum' => $this->quorum->forSession($session)->toArray(),
            'agenda_documents' => $agendaDocuments,
            'calendar_docket' => $user instanceof User
                ? $this->calendar->docket($session, $user)
                : [],
            'can' => [
                'update' => $user?->can('update', $session) ?? false,
                'schedule' => $user?->can('schedule', $session) ?? false,
                'prepare_agenda' => $user?->can('prepareAgenda', $session) ?? false,
                'start' => $user?->can('start', $session) ?? false,
                'suspend' => $user?->can('suspend', $session) ?? false,
                'resume' => $user?->can('resume', $session) ?? false,
                'adjourn' => $user?->can('adjourn', $session) ?? false,
                'manage_agenda' => $canManageAgenda,
                'view_transcript' => $user?->can('viewTranscript', $session) ?? false,
                'manage_recording' => $user?->can('manageRecording', $session) ?? false,
            ],
        ]);
    }

    public function edit(LegislativeSession $session): Response
    {
        $this->authorize('update', $session);

        return Inertia::render('Sessions/Edit', [
            'session' => SessionResource::detail($session),
            'users' => User::query()->where('is_active', true)->orderBy('display_name')->get(['id', 'display_name']),
        ]);
    }

    public function update(UpdateSessionRequest $request, LegislativeSession $session): RedirectResponse
    {
        $session->update($request->validated());

        return redirect()
            ->route('sessions.show', $session)
            ->with('success', 'sessions.updated');
    }

    public function schedule(Request $request, LegislativeSession $session): RedirectResponse
    {
        $this->authorize('schedule', $session);
        $this->transition($session, Scheduled::class, $request);

        return back()->with('success', 'sessions.scheduled_success');
    }

    public function prepareAgenda(Request $request, LegislativeSession $session): RedirectResponse
    {
        $this->authorize('prepareAgenda', $session);

        if (! $session->agendaItems()->exists()) {
            $this->agenda->prepareStandardTemplate($session);
        }

        $this->calendar->consumeCarryQueue($session);

        if ($session->status instanceof Draft) {
            $this->transition($session, AgendaPrepared::class, $request);
            $session->update(['agenda_locked_at' => now()]);
        }

        return back()->with('success', 'sessions.agenda_prepared');
    }

    public function start(Request $request, LegislativeSession $session): RedirectResponse
    {
        $this->authorize('start', $session);

        // Already live — treat as success so a duplicate Start click does not 500.
        if (! $session->status instanceof InSession) {
            $this->transition($session, InSession::class, $request);
        }

        $updates = [];

        if ($session->actual_start_at === null) {
            $updates['actual_start_at'] = now();
        }

        if (! $session->agendaItems()->where('status', 'in-progress')->exists()) {
            $first = $session->agendaItems()->where('status', 'pending')->orderBy('position')->first();

            if ($first !== null) {
                $first->update(['status' => 'in-progress', 'started_at' => now()]);
            }
        }

        if ($updates !== []) {
            $session->update($updates);
        }

        $session->refresh();
        $this->chamberChannels->ensureChamberTranscript($session, $this->requireUser($request));

        return back()->with('success', 'sessions.started');
    }

    public function suspend(Request $request, LegislativeSession $session): RedirectResponse
    {
        $this->authorize('suspend', $session);
        $this->transition($session, Suspended::class, $request, ['recess_ends_at' => null]);

        return back()->with('success', 'sessions.suspended');
    }

    public function recess(StartRecessRequest $request, LegislativeSession $session): RedirectResponse
    {
        $this->authorize('suspend', $session);

        if (! $session->status instanceof InSession) {
            return back()->with('error', 'sessions.recess_not_live');
        }

        if ($session->agendaItems()->whereNotNull('voting_open_at')->exists()) {
            return back()->with('error', 'sessions.recess_blocked_voting');
        }

        $this->transition($session, Suspended::class, $request, [
            'recess_ends_at' => now()->addSeconds($request->durationMinutes() * 60),
        ]);

        return back()->with('success', 'sessions.recessed');
    }

    public function resume(Request $request, LegislativeSession $session): RedirectResponse
    {
        $this->authorize('resume', $session);
        $this->transition($session, InSession::class, $request, ['recess_ends_at' => null]);

        return back()->with('success', 'sessions.resumed');
    }

    public function adjourn(Request $request, LegislativeSession $session): RedirectResponse
    {
        $this->authorize('adjourn', $session);
        $this->calendar->carryLeftoversOnAdjourn($session);
        $this->transition($session, Adjourned::class, $request, [
            'adjourned_at' => now(),
            'actual_end_at' => now(),
            'recess_ends_at' => null,
        ]);

        GenerateMinutesDraftJob::dispatch($session->getKey());
        $this->chamberRecording->markCaptureStopped($session->refresh());

        return back()->with('success', 'sessions.adjourned');
    }

    public function updateRecording(UpdateSessionRecordingRequest $request, LegislativeSession $session): RedirectResponse
    {
        $flash = 'chamber.recording_on';
        $auditNew = [];

        if ($request->exists('recording_enabled')) {
            $enabled = $request->boolean('recording_enabled');
            $this->chamberRecording->setRecordingEnabled($session, $enabled);
            $auditNew['recording_enabled'] = $enabled;
            $flash = $enabled ? 'chamber.recording_on' : 'chamber.recording_off';

            $this->audit->record(
                event: $enabled ? 'chamber.recording.enabled' : 'chamber.recording.disabled',
                category: 'session',
                auditable: $session,
                actor: $this->requireUser($request),
                new: ['recording_enabled' => $enabled],
            );
        }

        if ($request->filled('capture_mode')) {
            $feed = ChamberFeed::fromMixed($request->string('capture_mode')->toString());
            $this->chamberRecording->setCaptureMode($session, $feed);
            $auditNew['capture_mode'] = $feed->value;
            $flash = 'chamber.capture_mode_saved';

            $this->audit->record(
                event: 'chamber.capture_mode.updated',
                category: 'session',
                auditable: $session,
                actor: $this->requireUser($request),
                new: ['capture_mode' => $feed->value],
            );
        }

        unset($auditNew);

        return back()->with('success', $flash);
    }

    /**
     * @param  Builder<LegislativeSession>  $query
     */
    private function applyIndexFilters(Builder $query, Request $request): void
    {
        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        if ($request->filled('search')) {
            $term = '%'.$request->string('search')->toString().'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('title', 'ilike', $term)
                    ->orWhere('session_number', 'ilike', $term)
                    ->orWhere('venue', 'ilike', $term);
            });
        }

        $phase = $request->string('phase')->toString();

        match ($phase) {
            'live' => $query->whereIn('status', [InSession::$name, Suspended::$name]),
            'upcoming' => $query->whereIn('status', [
                Scheduled::$name,
                DocumentsDistributed::$name,
            ]),
            'draft' => $query->whereIn('status', [Draft::$name, AgendaPrepared::$name]),
            'closed' => $query->whereIn('status', [
                Adjourned::$name,
                MinutesForReview::$name,
                Finalized::$name,
                Archived::$name,
            ]),
            default => null,
        };
    }

    /**
     * Figures describe the filtered set, not the page of twenty currently shown.
     *
     * @param  Builder<LegislativeSession>  $query
     * @return array{matching: int, live: int, upcoming: int, drafts: int}
     */
    private function summarizeIndex(Builder $query): array
    {
        return [
            'matching' => (clone $query)->toBase()->getCountForPagination(),
            'live' => (clone $query)->whereIn('status', [InSession::$name, Suspended::$name])->count(),
            'upcoming' => (clone $query)->whereIn('status', [
                Scheduled::$name,
                DocumentsDistributed::$name,
            ])->count(),
            'drafts' => (clone $query)->whereIn('status', [Draft::$name, AgendaPrepared::$name])->count(),
        ];
    }

    /**
     * @return list<array{value: string, label: string, tag: string, next_session_number: string, next_title: string, next_sequence: int}>
     */
    private function sessionTypeOptions(SessionNumberAllocator $numbers, int $year): array
    {
        $previews = $numbers->previewAll($year);
        $options = [];

        foreach (SessionType::cases() as $type) {
            $preview = $previews[$type->value];

            $options[] = [
                'value' => $type->value,
                'label' => $type->label(),
                'tag' => $type->tag(),
                'next_session_number' => $preview['session_number'],
                'next_title' => $preview['title'],
                'next_sequence' => $preview['sequence'],
            ];
        }

        return $options;
    }

    /**
     * @param  class-string<SessionStatus>  $state
     * @param  array<string, mixed>  $attributes
     */
    private function transition(LegislativeSession $session, string $state, Request $request, array $attributes = []): void
    {
        $this->transitions->transition($session, $state, $this->requireUser($request));

        if ($attributes !== []) {
            $session->update($attributes);
        }

        $session->refresh();
        event(new SessionStateChanged($session, $session->status->getValue(), $session->status->label()));
    }
}

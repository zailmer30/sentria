<?php

namespace App\Http\Controllers;

use App\Contracts\AI\MinutesGenerationService;
use App\Http\Requests\Minutes\ConfirmMinutesSuggestionRequest;
use App\Http\Requests\Minutes\StoreMinutesRequest;
use App\Http\Requests\Minutes\UpdateMinutesRequest;
use App\Models\LegislativeSession;
use App\Models\Minutes;
use App\Services\AI\LegislativeMinutesGenerator;
use App\Services\Audit\AuditLogger;
use App\Services\Minutes\MinutesPdf;
use App\Services\Workflow\GuardedStateTransition;
use App\States\Minutes\AiDraft;
use App\States\Minutes\Approval;
use App\States\Minutes\Archive;
use App\States\Minutes\Edit;
use App\States\Minutes\FinalMinutes;
use App\States\Minutes\Review;
use App\States\Minutes\SecretariatReview;
use App\States\Minutes\SessionCompleted;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class MinutesController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly GuardedStateTransition $transitions,
        private readonly MinutesGenerationService $generator,
        private readonly MinutesPdf $pdfs,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Minutes::class);

        $query = Minutes::query()->with(['session', 'preparer']);

        $this->applyIndexFilters($query, $request);

        $query->latest('updated_at');

        $summary = $this->summarizeIndex($query);

        $minutes = $query->paginate(20)->withQueryString();

        return Inertia::render('Minutes/Index', [
            'minutes' => $minutes->through(fn (Minutes $record): array => $this->summary($record)),
            'summary' => $summary,
            'filters' => [
                'search' => $request->string('search')->toString() ?: null,
                'phase' => $request->string('phase')->toString() ?: null,
            ],
            'can' => [
                'create' => $request->user()?->can('create', Minutes::class) ?? false,
            ],
        ]);
    }

    public function show(Minutes $minute): Response
    {
        $this->authorize('view', $minute);
        $minute->load(['session', 'preparer', 'reviewer', 'approver']);

        return Inertia::render('Minutes/Show', [
            'minutes' => $this->detail($minute),
            'can' => $this->abilities($minute),
        ]);
    }

    public function pdf(Request $request, Minutes $minute): HttpResponse
    {
        $this->authorize('view', $minute);
        abort_unless(trim((string) $minute->content) !== '', 404);

        $this->audit->record(
            event: 'minutes.download',
            category: 'minutes',
            auditable: $minute,
            actor: $this->requireUser($request),
            message: 'Minutes PDF downloaded.',
        );

        return $this->pdfs->download($minute);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Minutes::class);

        $sessions = LegislativeSession::query()
            ->whereDoesntHave('minutes')
            ->latest('scheduled_start_at')
            ->limit(50)
            ->get(['id', 'session_number', 'title']);

        return Inertia::render('Minutes/Create', [
            'sessions' => $sessions->map(fn (LegislativeSession $s): array => [
                'id' => $s->getKey(),
                'session_number' => $s->session_number,
                'title' => $s->title,
            ])->values()->all(),
        ]);
    }

    public function store(StoreMinutesRequest $request): RedirectResponse
    {
        $actor = $this->requireUser($request);

        $minutes = Minutes::query()->create([
            'session_id' => $request->validated('session_id'),
            'status' => SessionCompleted::$name,
            'content' => $request->validated('content'),
            'prepared_by' => $actor->getKey(),
        ]);

        $this->audit->record(
            event: 'minutes.created',
            category: 'session',
            auditable: $minutes,
            actor: $actor,
            new: ['status' => SessionCompleted::$name],
            message: 'Minutes record created manually.',
        );

        return redirect()
            ->route('minutes.show', $minutes)
            ->with('success', 'minutes.created');
    }

    public function edit(Minutes $minute): Response
    {
        $this->authorize('update', $minute);

        return Inertia::render('Minutes/Edit', [
            'minutes' => $this->detail($minute),
        ]);
    }

    public function update(UpdateMinutesRequest $request, Minutes $minute): RedirectResponse
    {
        $actor = $this->requireUser($request);

        if ($minute->status instanceof SecretariatReview || $minute->status instanceof AiDraft) {
            $this->transitions->transition($minute, Edit::class, $actor);
            $minute->refresh();
        }

        $minute->update([
            ...$request->validated(),
            'revision' => $minute->revision + 1,
        ]);

        $this->audit->record(
            event: 'minutes.updated',
            category: 'session',
            auditable: $minute,
            actor: $actor,
            new: ['revision' => $minute->revision],
            message: 'Minutes content edited.',
        );

        return redirect()
            ->route('minutes.show', $minute)
            ->with('success', 'minutes.updated');
    }

    public function generateDraft(Request $request, Minutes $minute): RedirectResponse
    {
        $this->authorize('generateDraft', $minute);

        $minute->load('session');
        $session = $minute->session;
        abort_unless($session !== null, 404);

        try {
            $this->generator->draftFromSession($this->requireUser($request), $session);
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'minutes.draft_generated');
    }

    public function acceptDraft(Request $request, Minutes $minute): RedirectResponse
    {
        $this->authorize('update', $minute);

        if ($minute->status instanceof AiDraft) {
            $this->transitions->transition($minute, SecretariatReview::class, $this->requireUser($request));
        }

        return back()->with('success', 'minutes.draft_accepted');
    }

    public function review(Request $request, Minutes $minute): RedirectResponse
    {
        $this->authorize('review', $minute);
        $actor = $this->requireUser($request);

        if ($minute->status instanceof Edit || $minute->status instanceof SecretariatReview) {
            $this->transitions->transition($minute, Review::class, $actor);
        }

        $minute->update([
            'reviewed_by' => $actor->getKey(),
            'reviewed_at' => now(),
        ]);

        $this->audit->record(
            event: 'minutes.reviewed',
            category: 'session',
            auditable: $minute,
            actor: $actor,
            new: ['status' => Review::$name],
            message: 'Minutes marked as reviewed.',
        );

        return back()->with('success', 'minutes.reviewed');
    }

    public function approve(Request $request, Minutes $minute): RedirectResponse
    {
        $this->authorize('approve', $minute);
        $actor = $this->requireUser($request);

        $this->transitions->transition($minute, Approval::class, $actor);

        $minute->update([
            'approved_by' => $actor->getKey(),
            'approved_at' => now(),
        ]);

        $this->audit->record(
            event: 'minutes.approved',
            category: 'session',
            auditable: $minute,
            actor: $actor,
            new: ['status' => Approval::$name],
            message: 'Minutes approved for finalization.',
        );

        return back()->with('success', 'minutes.approved');
    }

    public function finalize(Request $request, Minutes $minute): RedirectResponse
    {
        $this->authorize('finalize', $minute);
        $actor = $this->requireUser($request);

        $this->transitions->transition($minute, FinalMinutes::class, $actor);

        $minute->update([
            'approved_by' => $minute->approved_by ?? $actor->getKey(),
            'approved_at' => $minute->approved_at ?? now(),
            'finalized_at' => now(),
        ]);

        $this->audit->record(
            event: 'minutes.finalized',
            category: 'session',
            auditable: $minute,
            actor: $actor,
            new: ['status' => FinalMinutes::$name],
            message: 'Minutes finalized as official record.',
        );

        return back()->with('success', 'minutes.finalized');
    }

    public function archive(Request $request, Minutes $minute): RedirectResponse
    {
        $this->authorize('archive', $minute);
        $actor = $this->requireUser($request);

        $this->transitions->transition($minute, Archive::class, $actor);

        $minute->update(['archived_at' => now()]);

        $this->audit->record(
            event: 'minutes.archived',
            category: 'session',
            auditable: $minute,
            actor: $actor,
            new: ['status' => Archive::$name],
            message: 'Minutes archived.',
        );

        return back()->with('success', 'minutes.archived');
    }

    public function confirmSuggestion(ConfirmMinutesSuggestionRequest $request, Minutes $minute): RedirectResponse
    {
        $this->authorize('update', $minute);

        /** @var array<string, mixed> $metadata */
        $metadata = is_array($minute->ai_metadata) ? $minute->ai_metadata : [];
        $suggestionId = $request->validated('suggestion_id');
        $type = $request->validated('type');

        if ($type === 'motion') {
            /** @var list<array<string, mixed>> $suggestions */
            $suggestions = is_array($metadata['suggestions'] ?? null) ? $metadata['suggestions'] : [];

            foreach ($suggestions as $index => $item) {
                if (($item['id'] ?? null) === $suggestionId) {
                    $suggestions[$index]['confirmed'] = true;
                    $note = "\n\n[Confirmed AI Suggested Motion] ".$item['text'];
                    $minute->update(['content' => trim((string) $minute->content).$note]);
                }
            }

            $metadata['suggestions'] = $suggestions;
        } else {
            /** @var list<array<string, mixed>> $actionItems */
            $actionItems = is_array($metadata['action_items'] ?? null) ? $metadata['action_items'] : [];

            foreach ($actionItems as $index => $item) {
                if (($item['id'] ?? null) === $suggestionId) {
                    $actionItems[$index]['confirmed'] = true;
                    $note = "\n\n[Confirmed AI Action Item] ".$item['text'];
                    $minute->update(['content' => trim((string) $minute->content).$note]);
                }
            }

            $metadata['action_items'] = $actionItems;
        }

        $minute->update(['ai_metadata' => $metadata]);

        return back()->with('success', 'minutes.suggestion_confirmed');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Minutes $minutes): array
    {
        return [
            'id' => $minutes->getKey(),
            'status' => $minutes->status->getValue(),
            'status_label' => $minutes->status->label(),
            'revision' => $minutes->revision,
            'session' => $minutes->session ? [
                'id' => $minutes->session->getKey(),
                'session_number' => $minutes->session->session_number,
                'title' => $minutes->session->title,
            ] : null,
            'preparer' => $minutes->preparer?->display_name,
            'updated_at' => $minutes->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Builder<Minutes>  $query
     */
    private function applyIndexFilters(Builder $query, Request $request): void
    {
        if ($request->filled('search')) {
            $term = '%'.$request->string('search')->toString().'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->whereHas('session', function (Builder $session) use ($term): void {
                    $session->where('title', 'ilike', $term)
                        ->orWhere('session_number', 'ilike', $term);
                });
            });
        }

        $phase = $request->string('phase')->toString();

        match ($phase) {
            'drafting' => $query->whereIn('status', [
                SessionCompleted::$name,
                AiDraft::$name,
                SecretariatReview::$name,
                Edit::$name,
            ]),
            'review' => $query->whereIn('status', [Review::$name, Approval::$name]),
            'final' => $query->whereIn('status', [FinalMinutes::$name, Archive::$name]),
            default => null,
        };
    }

    /**
     * @param  Builder<Minutes>  $query
     * @return array{matching: int, drafting: int, review: int, final: int}
     */
    private function summarizeIndex(Builder $query): array
    {
        return [
            'matching' => (clone $query)->toBase()->getCountForPagination(),
            'drafting' => (clone $query)->whereIn('status', [
                SessionCompleted::$name,
                AiDraft::$name,
                SecretariatReview::$name,
                Edit::$name,
            ])->count(),
            'review' => (clone $query)->whereIn('status', [Review::$name, Approval::$name])->count(),
            'final' => (clone $query)->whereIn('status', [FinalMinutes::$name, Archive::$name])->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Minutes $minutes): array
    {
        $metadata = $minutes->ai_metadata ?? [];

        return [
            ...$this->summary($minutes),
            'content' => $minutes->content,
            'content_html' => $minutes->content_html,
            'ai_draft' => $minutes->ai_draft,
            'ai_banner' => ($metadata['banner'] ?? null) ?: LegislativeMinutesGenerator::DRAFT_BANNER,
            'ai_metadata' => $metadata,
            'preparer' => $minutes->preparer?->display_name,
            'reviewer' => $minutes->reviewer?->display_name,
            'approver' => $minutes->approver?->display_name,
            'reviewed_at' => $minutes->reviewed_at?->toIso8601String(),
            'finalized_at' => $minutes->finalized_at?->toIso8601String(),
            'archived_at' => $minutes->archived_at?->toIso8601String(),
            'has_ai_draft' => $minutes->ai_draft !== null,
            'is_ai_draft_status' => $minutes->status instanceof AiDraft,
            'allows_draft_generation' => $minutes->allowsDraftGeneration(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function abilities(Minutes $minutes): array
    {
        $user = request()->user();
        $mayGenerate = $user?->can('generateDraft', $minutes) ?? false;
        $locked = $mayGenerate
            && ! $minutes->allowsDraftGeneration()
            && ! ($minutes->status instanceof FinalMinutes)
            && ! ($minutes->status instanceof Archive);

        return [
            'update' => $user?->can('update', $minutes) ?? false,
            'generateDraft' => $mayGenerate && $minutes->allowsDraftGeneration(),
            'draftLocked' => $locked,
            'acceptDraft' => ($user?->can('update', $minutes) ?? false) && $minutes->status instanceof AiDraft,
            'review' => $user?->can('review', $minutes) ?? false,
            'approve' => $user?->can('approve', $minutes) ?? false,
            'finalize' => $user?->can('finalize', $minutes) ?? false,
            'archive' => $user?->can('archive', $minutes) ?? false,
            'download' => trim((string) $minutes->content) !== '',
        ];
    }
}

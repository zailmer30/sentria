<?php

namespace App\Http\Controllers;

use App\Http\Requests\Transcripts\CorrectTranscriptSegmentRequest;
use App\Http\Requests\Transcripts\StoreTranscriptRequest;
use App\Models\LegislativeSession;
use App\Models\Transcript;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Sessions\TranscriptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TranscriptController extends Controller
{
    public function __construct(
        private readonly TranscriptService $transcripts,
        private readonly AuditLogger $audit,
    ) {}

    public function show(Request $request, LegislativeSession $session): Response
    {
        $this->authorize('viewTranscript', $session);

        $transcript = $this->transcripts->primaryForSession($session);
        $user = $this->requireUser($request);

        if ($transcript !== null) {
            $this->authorize('view', $transcript);
            $transcript->load('agendaItem');
        }

        $session->loadMissing('attendance.user');

        $speakers = $session->attendance->isNotEmpty()
            ? $session->attendance
                ->filter(fn ($row): bool => is_string($row->user_id) && $row->user_id !== '')
                ->map(fn ($row): array => [
                    'id' => $row->user_id,
                    'display_name' => $row->user?->display_name ?? $row->user_id,
                ])
                ->values()
                ->all()
            : User::query()
                ->where('is_seated_member', true)
                ->where('is_active', true)
                ->orderBy('display_name')
                ->get(['id', 'display_name'])
                ->map(fn (User $member): array => [
                    'id' => $member->getKey(),
                    'display_name' => $member->display_name,
                ])
                ->values()
                ->all();

        return Inertia::render('Sessions/Transcript', [
            'session' => [
                'id' => $session->getKey(),
                'session_number' => $session->session_number,
                'title' => $session->title,
                'status' => $session->status->getValue(),
                'status_label' => $session->status->label(),
                'recording_enabled' => (bool) $session->recording_enabled,
            ],
            'transcript' => $transcript ? $this->serialize($transcript) : null,
            'roster' => $speakers,
            'agenda_items' => $session->agendaItems()->orderBy('position')->get(['id', 'item_number', 'title'])->map(
                fn ($item): array => [
                    'id' => $item->getKey(),
                    'item_number' => $item->item_number,
                    'title' => $item->title,
                ],
            )->values()->all(),
            'can' => [
                'manage' => $user->can('manageTranscript', $session),
                'transcribe' => $user->can('transcribeSession', $session),
                'correct' => $transcript ? $user->can('correct', $transcript) : false,
                'view' => $user->can('viewTranscript', $session),
            ],
            'highlight_seconds' => $request->integer('t'),
        ]);
    }

    public function store(StoreTranscriptRequest $request, LegislativeSession $session): RedirectResponse
    {
        $actor = $this->requireUser($request);

        $agendaItem = null;
        if ($request->filled('agenda_item_id')) {
            $agendaItem = $this->agendaItemForSession($session, $request->string('agenda_item_id')->value());
        }

        $transcript = $this->transcripts->createFromUpload(
            $actor,
            $session,
            $request->file('audio'),
            $agendaItem,
        );

        $this->audit->record(
            event: 'transcript.uploaded',
            category: 'session',
            auditable: $transcript,
            actor: $actor,
            context: [
                'session_id' => $session->getKey(),
                'agenda_item_id' => $agendaItem?->getKey(),
            ],
        );

        return redirect()
            ->route('sessions.transcript.show', $session)
            ->with('success', 'transcripts.uploaded');
    }

    public function correct(
        CorrectTranscriptSegmentRequest $request,
        LegislativeSession $session,
        Transcript $transcript,
        int $segmentIndex,
    ): JsonResponse {
        abort_unless($transcript->session_id === $session->getKey(), 404);

        $validated = $request->validated();
        $assigning = array_key_exists('speaker_id', $validated) || $request->boolean('gallery');

        $updated = $this->transcripts->correctSegment(
            $transcript,
            $segmentIndex,
            [
                'text' => $validated['text'],
                'speaker_id' => $validated['speaker_id'] ?? null,
                'gallery' => $request->boolean('gallery'),
            ],
        );

        $this->audit->record(
            event: $assigning ? 'transcript.speaker_assigned' : 'transcript.segment_corrected',
            category: 'session',
            auditable: $updated,
            actor: $this->requireUser($request),
            context: [
                'segment_index' => $segmentIndex,
                'speaker_id' => $validated['speaker_id'] ?? null,
                'gallery' => $request->boolean('gallery'),
            ],
        );

        return response()->json([
            'transcript' => $this->serialize($updated),
        ]);
    }

    public function search(Request $request, LegislativeSession $session): JsonResponse
    {
        $transcript = $this->transcripts->primaryForSession($session);
        abort_unless($transcript instanceof Transcript, 404);

        $this->authorize('view', $transcript);

        $query = $request->string('query')->trim()->value();
        $results = $this->transcripts->search($transcript, $query);

        $this->audit->record(
            event: 'transcript.searched',
            category: 'session',
            auditable: $transcript,
            actor: $this->requireUser($request),
            context: [
                'query' => $query,
                'result_count' => count($results),
            ],
        );

        return response()->json(['results' => $results]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Transcript $transcript): array
    {
        $startedAt = $transcript->getAttribute('started_at');
        $endedAt = $transcript->getAttribute('ended_at');

        return [
            'id' => $transcript->getKey(),
            'status' => $transcript->status,
            'processing_error' => $transcript->processing_error,
            'source' => $transcript->source,
            'language' => $transcript->language,
            'full_text' => $transcript->full_text,
            'segments' => $this->transcripts->typedSegments(
                is_array($transcript->segments) ? $transcript->segments : [],
            ),
            'average_confidence' => $transcript->average_confidence,
            'duration_seconds' => $transcript->duration_seconds,
            'model' => $transcript->model,
            'provider' => $transcript->provider,
            'started_at' => $startedAt instanceof \DateTimeInterface ? $startedAt->format(\DateTimeInterface::ATOM) : null,
            'ended_at' => $endedAt instanceof \DateTimeInterface ? $endedAt->format(\DateTimeInterface::ATOM) : null,
            'agenda_item' => $transcript->agendaItem ? [
                'id' => $transcript->agendaItem->getKey(),
                'item_number' => $transcript->agendaItem->item_number,
                'title' => $transcript->agendaItem->title,
            ] : null,
        ];
    }
}

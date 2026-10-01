<?php

namespace App\Http\Controllers;

use App\Events\AttendanceUpdated;
use App\Http\Requests\Sessions\UpdateSecretariatMinutesRequest;
use App\Http\Resources\SessionResource;
use App\Models\LegislativeSession;
use App\Models\Minutes;
use App\Services\AI\LegislativeMinutesGenerator;
use App\Services\AI\SessionAssistantService;
use App\Services\Audit\AuditLogger;
use App\Services\Documents\DocumentAccessService;
use App\Services\Sessions\AgendaService;
use App\Services\Sessions\AttendanceService;
use App\Services\Sessions\QuorumService;
use App\Services\Sessions\TranscriptService;
use App\Services\Sessions\VotingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class SessionFloorController extends Controller
{
    public function __construct(
        private readonly QuorumService $quorum,
        private readonly AgendaService $agenda,
        private readonly DocumentAccessService $access,
        private readonly VotingService $voting,
        private readonly AttendanceService $attendance,
        private readonly SessionAssistantService $assistant,
        private readonly TranscriptService $transcripts,
        private readonly LegislativeMinutesGenerator $minutesGenerator,
        private readonly AuditLogger $audit,
    ) {}

    public function boardMember(Request $request, LegislativeSession $session): Response
    {
        $this->authorize('view', $session);

        $user = $this->requireUser($request);

        if ($this->attendance->checkInFromFloor($session, $user)) {
            event(new AttendanceUpdated($session, $this->quorum->forSession($session)));
        }

        return Inertia::render('Sessions/Floor/BoardMember', $this->payload($request, $session));
    }

    public function secretariat(Request $request, LegislativeSession $session): Response
    {
        $this->authorize('view', $session);

        return Inertia::render('Sessions/Floor/Secretariat', [
            ...$this->payload($request, $session),
            'workspace' => 'console',
        ]);
    }

    public function minutes(Request $request, LegislativeSession $session): Response
    {
        $this->authorize('view', $session);

        return Inertia::render('Sessions/Floor/Secretariat', [
            ...$this->payload($request, $session),
            'workspace' => 'minutes',
        ]);
    }

    public function recording(Request $request, LegislativeSession $session): Response
    {
        $this->authorize('view', $session);

        return Inertia::render('Sessions/Floor/Secretariat', [
            ...$this->payload($request, $session),
            'workspace' => 'recording',
        ]);
    }

    public function updateMinutes(UpdateSecretariatMinutesRequest $request, LegislativeSession $session): RedirectResponse
    {
        $text = trim((string) $request->validated('secretariat_minutes', ''));
        $text = $text === '' ? null : $text;

        $session->update(['secretariat_minutes' => $text]);

        $session->loadMissing('minutes');
        $minutes = $session->minutes;

        if (
            $minutes instanceof Minutes
            && $minutes->ai_draft !== null
            && $minutes->allowsDraftGeneration()
        ) {
            $merged = $this->minutesGenerator->applySecretariatMinutesToDraft($minutes->ai_draft, $text);

            $minutes->update([
                'ai_draft' => $merged,
                'content' => $merged,
            ]);
        }

        $this->audit->record(
            event: 'session.secretariat_minutes.updated',
            category: 'session',
            auditable: $session,
            actor: $this->requireUser($request),
            new: ['length' => $text === null ? 0 : mb_strlen($text)],
            message: 'Secretariat minutes notes updated from the floor.',
        );

        return back()->with('success', 'sessions.floor.minutes_saved');
    }

    public function presidingOfficer(LegislativeSession $session): RedirectResponse
    {
        $this->authorize('view', $session);

        return redirect()->route('sessions.floor.member', $session);
    }

    public function dashboard(Request $request, LegislativeSession $session): Response
    {
        $this->authorize('view', $session);

        return Inertia::render('Sessions/Floor/Dashboard', $this->payload($request, $session));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, LegislativeSession $session): array
    {
        $user = $this->requireUser($request);

        $payload = SessionResource::floor(
            $session,
            $user,
            $this->quorum,
            $this->agenda,
            $this->access,
            $this->voting,
            $this->attendance,
        );

        $payload['assistant'] = null;
        $payload['can']['use_assistant'] = $user->can('ai.use');

        if ($user->can('ai.use') && $this->assistant->isActiveSession($session)) {
            try {
                $payload['assistant'] = $this->assistant->context($user, $session)->toArray();
            } catch (AuthorizationException|InvalidArgumentException) {
                $payload['assistant'] = null;
            }
        }

        $payload['can']['view_transcript'] = $user->can('viewTranscript', $session);
        $payload['can']['transcribe'] = $user->can('transcribeSession', $session);
        $payload['can']['correct_transcript'] = false;
        $payload['transcript'] = null;

        if ($user->can('viewTranscript', $session)) {
            $transcript = $this->transcripts->primaryForSession($session);

            if ($transcript !== null) {
                $payload['can']['correct_transcript'] = $user->can('correct', $transcript);

                /** @var list<array<string, mixed>> $segments */
                $segments = is_array($transcript->segments) ? $transcript->segments : [];

                $payload['transcript'] = [
                    'id' => $transcript->getKey(),
                    'status' => $transcript->status,
                    'processing_error' => $transcript->processing_error,
                    'segments' => $this->transcripts->floorLiveSegments($segments),
                ];
            }
        }

        return $payload;
    }
}

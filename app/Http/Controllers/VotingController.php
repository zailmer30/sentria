<?php

namespace App\Http\Controllers;

use App\Enums\VoteChoice;
use App\Http\Requests\Sessions\CastVoteRequest;
use App\Http\Requests\Sessions\OpenVotingRequest;
use App\Models\LegislativeSession;
use App\Models\Vote;
use App\Services\Sessions\VotingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class VotingController extends Controller
{
    public function __construct(private readonly VotingService $voting) {}

    public function open(OpenVotingRequest $request, LegislativeSession $session): RedirectResponse
    {
        $agendaItem = $this->agendaItemForSession($session, $request->validated('agenda_item_id'));

        $motion = $request->filled('motion_id')
            ? $this->motionForSession($session, $request->validated('motion_id'))
            : null;

        $this->voting->openVoting(
            $session,
            $agendaItem,
            $this->requireUser($request),
            $motion,
            $request->boolean('silent'),
        );

        return back()->with('success', 'sessions.voting_opened');
    }

    public function cast(CastVoteRequest $request, LegislativeSession $session): JsonResponse|RedirectResponse
    {
        $agendaItem = $this->agendaItemForSession($session, $request->validated('agenda_item_id'));

        try {
            $vote = $this->voting->castVote(
                $this->requireUser($request),
                $session,
                $agendaItem,
                VoteChoice::from($request->validated('choice')),
                (int) $request->validated('voting_round'),
            );
        } catch (InvalidArgumentException $exception) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 422);
            }

            return back()->with('error', $exception->getMessage());
        }

        if ($request->expectsJson()) {
            return response()->json([
                'vote' => [
                    'id' => $vote->getKey(),
                    'choice' => $vote->choice,
                    'voting_round' => $vote->voting_round,
                ],
            ], $vote->wasRecentlyCreated ? 201 : 200);
        }

        return back()->with('success', 'sessions.vote_cast');
    }

    public function close(Request $request, LegislativeSession $session): RedirectResponse
    {
        $this->authorize('closeVoting', $session);

        $validated = $request->validate([
            'agenda_item_id' => ['required', 'ulid', 'exists:agenda_items,id'],
            'motion_id' => ['sometimes', 'nullable', 'ulid', 'exists:motions,id'],
        ]);

        $agendaItem = $this->agendaItemForSession($session, $validated['agenda_item_id']);

        $motion = ! empty($validated['motion_id'])
            ? $this->motionForSession($session, $validated['motion_id'])
            : null;

        $this->voting->closeVoting($session, $agendaItem, $this->requireUser($request), $motion);

        return back()->with('success', 'sessions.voting_closed');
    }

    public function results(Request $request, LegislativeSession $session): JsonResponse
    {
        $this->authorize('viewAny', Vote::class);

        $validated = $request->validate([
            'agenda_item_id' => ['required', 'ulid', 'exists:agenda_items,id'],
            'voting_round' => ['required', 'integer', 'min:1'],
        ]);

        $agendaItem = $this->agendaItemForSession($session, $validated['agenda_item_id']);

        return response()->json([
            'tallies' => $this->voting->tallies($session, $agendaItem, (int) $validated['voting_round']),
            'electronic_is_binding' => $this->voting->electronicIsBinding(),
        ]);
    }
}

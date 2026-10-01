<?php

namespace App\Http\Controllers;

use App\Http\Requests\Sessions\StoreSessionChatDirectRequest;
use App\Http\Requests\Sessions\StoreSessionChatGroupRequest;
use App\Http\Requests\Sessions\StoreSessionChatMessageRequest;
use App\Http\Requests\Sessions\StoreSessionChatParticipantsRequest;
use App\Models\LegislativeSession;
use App\Models\SessionConversation;
use App\Models\User;
use App\Services\Sessions\SessionChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SessionChatController extends Controller
{
    public function __construct(private readonly SessionChatService $chat) {}

    public function index(Request $request, LegislativeSession $session): JsonResponse
    {
        $this->authorize('useChat', $session);

        return response()->json($this->chat->inbox($session, $this->requireUser($request)));
    }

    public function directory(Request $request, LegislativeSession $session): JsonResponse
    {
        $this->authorize('useChat', $session);

        return response()->json($this->chat->directory($session, $this->requireUser($request)));
    }

    public function storeDirect(StoreSessionChatDirectRequest $request, LegislativeSession $session): JsonResponse
    {
        $target = User::query()->findOrFail((string) $request->validated('user_id'));

        try {
            $payload = $this->chat->findOrCreateDirect($session, $this->requireUser($request), $target);
        } catch (InvalidArgumentException $exception) {
            abort(403, $exception->getMessage());
        }

        return response()->json($payload);
    }

    public function storeGroup(StoreSessionChatGroupRequest $request, LegislativeSession $session): JsonResponse
    {
        /** @var list<string> $participantIds */
        $participantIds = $request->validated('participant_ids');

        try {
            $payload = $this->chat->createGroup(
                $session,
                $this->requireUser($request),
                (string) $request->validated('name'),
                $participantIds,
            );
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json($payload, 201);
    }

    public function show(Request $request, LegislativeSession $session, SessionConversation $conversation): JsonResponse
    {
        $this->assertConversation($session, $conversation);
        $this->authorize('view', $conversation);

        return response()->json($this->chat->show($conversation, $this->requireUser($request)));
    }

    public function storeMessage(
        StoreSessionChatMessageRequest $request,
        LegislativeSession $session,
        SessionConversation $conversation,
    ): JsonResponse {
        $this->assertConversation($session, $conversation);

        try {
            $payload = $this->chat->send(
                $conversation,
                $this->requireUser($request),
                (string) $request->validated('body'),
            );
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json($payload, 201);
    }

    public function markRead(
        Request $request,
        LegislativeSession $session,
        SessionConversation $conversation,
    ): JsonResponse {
        $this->assertConversation($session, $conversation);
        $this->authorize('view', $conversation);

        return response()->json($this->chat->markRead($conversation, $this->requireUser($request)));
    }

    public function addParticipants(
        StoreSessionChatParticipantsRequest $request,
        LegislativeSession $session,
        SessionConversation $conversation,
    ): JsonResponse {
        $this->assertConversation($session, $conversation);

        /** @var list<string> $userIds */
        $userIds = $request->validated('user_ids');

        try {
            $this->chat->addParticipants($conversation, $this->requireUser($request), $userIds);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json($this->chat->show($conversation->fresh() ?? $conversation, $this->requireUser($request)));
    }

    public function removeParticipant(
        Request $request,
        LegislativeSession $session,
        SessionConversation $conversation,
        User $user,
    ): JsonResponse {
        $this->assertConversation($session, $conversation);

        $actor = $this->requireUser($request);

        if ($user->getKey() === $actor->getKey()) {
            $this->authorize('leave', $conversation);
        } else {
            $this->authorize('updateParticipants', $conversation);
        }

        try {
            $this->chat->removeParticipant($conversation, $actor, $user);
        } catch (InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        if ($user->getKey() === $actor->getKey()) {
            return response()->json(['ok' => true]);
        }

        return response()->json($this->chat->show($conversation->fresh() ?? $conversation, $actor));
    }

    private function assertConversation(LegislativeSession $session, SessionConversation $conversation): void
    {
        abort_unless($conversation->session_id === $session->getKey(), 404);
    }
}

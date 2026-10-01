<?php

namespace App\Services\Sessions;

use App\Enums\SessionConversationType;
use App\Enums\UserRole;
use App\Events\SessionChatInboxUpdated;
use App\Events\SessionChatMessageSent;
use App\Events\SessionChatRead;
use App\Models\LegislativeSession;
use App\Models\SessionConversation;
use App\Models\SessionConversationParticipant;
use App\Models\SessionMessage;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SessionChatService
{
    public const BODY_MAX = 2000;

    public function __construct(private readonly AuditLogger $audit) {}

    public function userCanChat(User $user): bool
    {
        return $user->is_active && $user->can('session-chat.use');
    }

    /**
     * @return array{
     *     secretary_id: string|null,
     *     voting_open: bool,
     *     clerks: list<array<string, mixed>>,
     *     members: list<array<string, mixed>>
     * }
     */
    public function directory(LegislativeSession $session, User $viewer): array
    {
        $users = $this->eligibleUsers()
            ->filter(fn (User $user): bool => $user->getKey() !== $viewer->getKey())
            ->values();

        $secretaryId = $session->secretary_id;

        $clerks = $users
            ->filter(fn (User $user): bool => $user->hasRole(UserRole::Secretariat->value))
            ->sortBy(function (User $user) use ($secretaryId): string {
                $priority = $secretaryId !== null && $user->getKey() === $secretaryId ? '0' : '1';

                return $priority.$user->display_name;
            })
            ->values();

        $members = $users
            ->reject(fn (User $user): bool => $user->hasRole(UserRole::Secretariat->value))
            ->sortBy(fn (User $user): string => $user->display_name)
            ->values();

        $clerkRows = [];
        foreach ($clerks as $user) {
            $clerkRows[] = $this->person($user, $secretaryId);
        }

        $memberRows = [];
        foreach ($members as $user) {
            $memberRows[] = $this->person($user, $secretaryId);
        }

        return [
            'secretary_id' => $secretaryId,
            'voting_open' => $this->votingIsOpen($session),
            'clerks' => $clerkRows,
            'members' => $memberRows,
        ];
    }

    /**
     * @return array{voting_open: bool, unread_total: int, conversations: list<array<string, mixed>>}
     */
    public function inbox(LegislativeSession $session, User $viewer): array
    {
        $conversations = SessionConversation::query()
            ->where('session_id', $session->getKey())
            ->forParticipant($viewer)
            ->with(['currentParticipantRows.user'])
            ->get();

        $rows = [];
        foreach ($conversations as $conversation) {
            $rows[] = $this->conversationPayload($conversation, $viewer);
        }

        usort($rows, function (array $left, array $right): int {
            $unreadLeft = ((int) ($left['unread_count'] ?? 0)) > 0 ? 1 : 0;
            $unreadRight = ((int) ($right['unread_count'] ?? 0)) > 0 ? 1 : 0;

            if ($unreadLeft !== $unreadRight) {
                return $unreadRight <=> $unreadLeft;
            }

            return strcmp((string) ($right['last_message_at'] ?? ''), (string) ($left['last_message_at'] ?? ''));
        });

        $unreadTotal = 0;
        foreach ($rows as $row) {
            $unreadTotal += (int) ($row['unread_count'] ?? 0);
        }

        return [
            'voting_open' => $this->votingIsOpen($session),
            'unread_total' => $unreadTotal,
            'conversations' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(SessionConversation $conversation, User $viewer): array
    {
        $conversation->load(['currentParticipantRows.user']);

        $messages = SessionMessage::query()
            ->where('conversation_id', $conversation->getKey())
            ->with('author')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get()
            ->sortBy('created_at')
            ->values();

        $payload = $this->conversationPayload($conversation, $viewer);
        $payload['messages'] = $messages
            ->map(fn (SessionMessage $message): array => [
                'id' => $message->getKey(),
                'user_id' => $message->user_id,
                'display_name' => $message->author?->display_name,
                'body' => $message->body,
                'created_at' => $message->created_at?->toIso8601String(),
            ])
            ->all();

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function findOrCreateDirect(LegislativeSession $session, User $actor, User $target): array
    {
        $this->assertEligiblePeer($actor, $target);

        $pairKey = SessionConversation::directPairKey($actor->getKey(), $target->getKey());

        $conversation = SessionConversation::query()
            ->where('session_id', $session->getKey())
            ->where('direct_pair_key', $pairKey)
            ->first();

        if ($conversation === null) {
            $conversation = DB::transaction(function () use ($session, $actor, $target, $pairKey): SessionConversation {
                $created = SessionConversation::query()->create([
                    'session_id' => $session->getKey(),
                    'type' => SessionConversationType::Direct,
                    'name' => null,
                    'direct_pair_key' => $pairKey,
                    'created_by' => $actor->getKey(),
                ]);

                $this->addParticipant($created, $actor, now());
                $this->addParticipant($created, $target, now());

                $this->audit->record(
                    event: 'session-chat.direct-opened',
                    category: 'session',
                    auditable: $created,
                    actor: $actor,
                    new: [
                        'session_id' => $session->getKey(),
                        'participant_ids' => [$actor->getKey(), $target->getKey()],
                    ],
                    message: 'Floor chat direct thread opened.',
                );

                return $created;
            });

            $this->notifyInbox($conversation, [$target->getKey()]);
        }

        $conversation->load(['currentParticipantRows.user']);

        return $this->show($conversation, $actor);
    }

    /**
     * @param  list<string>  $participantIds
     * @return array<string, mixed>
     */
    public function createGroup(LegislativeSession $session, User $actor, string $name, array $participantIds): array
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('A group needs a name.');
        }

        $uniqueIds = array_values(array_unique([...$participantIds, $actor->getKey()]));

        if (count($uniqueIds) < 3) {
            throw new InvalidArgumentException('A group needs at least three people.');
        }

        $users = $this->eligibleUsers()->keyBy(fn (User $user): string => $user->getKey());

        foreach ($uniqueIds as $userId) {
            $user = $users->get($userId);

            if (! $user instanceof User) {
                throw new InvalidArgumentException('Every participant must be allowed on this sitting\'s floor chat.');
            }
        }

        $conversation = DB::transaction(function () use ($session, $actor, $name, $uniqueIds, $users): SessionConversation {
            $created = SessionConversation::query()->create([
                'session_id' => $session->getKey(),
                'type' => SessionConversationType::Group,
                'name' => $name,
                'direct_pair_key' => null,
                'created_by' => $actor->getKey(),
            ]);

            $joinedAt = now();

            foreach ($uniqueIds as $userId) {
                $user = $users->get($userId);
                assert($user instanceof User);
                $this->addParticipant($created, $user, $joinedAt);
            }

            $this->audit->record(
                event: 'session-chat.group-opened',
                category: 'session',
                auditable: $created,
                actor: $actor,
                new: [
                    'session_id' => $session->getKey(),
                    'participant_ids' => $uniqueIds,
                ],
                message: 'Floor chat group opened.',
            );

            return $created;
        });

        $this->notifyInbox(
            $conversation,
            array_values(array_filter($uniqueIds, fn (string $id): bool => $id !== $actor->getKey())),
        );

        $conversation->load(['currentParticipantRows.user']);

        return $this->show($conversation, $actor);
    }

    /**
     * @return array<string, mixed>
     */
    public function send(SessionConversation $conversation, User $actor, string $body): array
    {
        $body = trim($body);

        if ($body === '') {
            throw new InvalidArgumentException('Message cannot be empty.');
        }

        if (mb_strlen($body) > self::BODY_MAX) {
            throw new InvalidArgumentException('Message is too long.');
        }

        $message = DB::transaction(function () use ($conversation, $actor, $body): SessionMessage {
            $created = SessionMessage::query()->create([
                'conversation_id' => $conversation->getKey(),
                'user_id' => $actor->getKey(),
                'body' => $body,
            ]);

            $conversation->update(['last_message_at' => $created->created_at]);

            SessionConversationParticipant::query()
                ->where('conversation_id', $conversation->getKey())
                ->where('user_id', $actor->getKey())
                ->whereNull('left_at')
                ->update(['last_read_at' => $created->created_at]);

            return $created;
        });

        $message->setRelation('author', $actor);

        event(new SessionChatMessageSent($conversation, $message, $actor));

        $recipientIds = [];
        foreach (
            $conversation->currentParticipantRows()
                ->where('user_id', '!=', $actor->getKey())
                ->pluck('user_id') as $id
        ) {
            $recipientIds[] = (string) $id;
        }

        $this->notifyInbox($conversation, $recipientIds);

        return [
            'id' => $message->getKey(),
            'user_id' => $actor->getKey(),
            'display_name' => $actor->display_name,
            'body' => $message->body,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{ok: true, last_read_at: string}
     */
    public function markRead(SessionConversation $conversation, User $actor): array
    {
        $readAt = now();

        SessionConversationParticipant::query()
            ->where('conversation_id', $conversation->getKey())
            ->where('user_id', $actor->getKey())
            ->whereNull('left_at')
            ->update(['last_read_at' => $readAt]);

        event(new SessionChatRead($conversation, $actor, $readAt));

        return [
            'ok' => true,
            'last_read_at' => $readAt->toIso8601String(),
        ];
    }

    /**
     * @param  list<string>  $userIds
     */
    public function addParticipants(SessionConversation $conversation, User $actor, array $userIds): void
    {
        if (! $conversation->isGroup()) {
            throw new InvalidArgumentException('People can only be added to a group.');
        }

        $uniqueIds = array_values(array_unique($userIds));
        $eligible = $this->eligibleUsers()->keyBy(fn (User $user): string => $user->getKey());
        $currentIds = $conversation->currentParticipantRows()->pluck('user_id')->all();
        $addedIds = [];

        DB::transaction(function () use ($conversation, $uniqueIds, $eligible, $currentIds, &$addedIds): void {
            foreach ($uniqueIds as $userId) {
                if (in_array($userId, $currentIds, true)) {
                    continue;
                }

                $user = $eligible->get($userId);

                if (! $user instanceof User) {
                    throw new InvalidArgumentException('Every participant must be allowed on this sitting\'s floor chat.');
                }

                $existing = SessionConversationParticipant::query()
                    ->where('conversation_id', $conversation->getKey())
                    ->where('user_id', $userId)
                    ->first();

                if ($existing instanceof SessionConversationParticipant) {
                    $existing->update([
                        'left_at' => null,
                        'joined_at' => now(),
                        'last_read_at' => now(),
                    ]);
                } else {
                    $this->addParticipant($conversation, $user, now());
                }

                $addedIds[] = $userId;
            }
        });

        if ($addedIds !== []) {
            $this->audit->record(
                event: 'session-chat.participants-added',
                category: 'session',
                auditable: $conversation,
                actor: $actor,
                new: ['participant_ids' => $addedIds],
                message: 'Floor chat group membership updated.',
            );
            $this->notifyInbox($conversation, $addedIds);
        }
    }

    public function removeParticipant(SessionConversation $conversation, User $actor, User $target): void
    {
        if (! $conversation->isGroup()) {
            throw new InvalidArgumentException('People can only be removed from a group.');
        }

        if ($target->getKey() === $actor->getKey()) {
            $this->leave($conversation, $actor);

            return;
        }

        $this->markLeft($conversation, $target);
    }

    public function leave(SessionConversation $conversation, User $actor): void
    {
        if (! $conversation->isGroup()) {
            throw new InvalidArgumentException('A direct thread cannot be left.');
        }

        $this->markLeft($conversation, $actor);
    }

    /**
     * @return Collection<int, User>
     */
    private function eligibleUsers(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->permission('session-chat.use')
            ->orderBy('display_name')
            ->get();
    }

    private function assertEligiblePeer(User $actor, User $target): void
    {
        if ($actor->getKey() === $target->getKey()) {
            throw new InvalidArgumentException('You cannot open a chat with yourself.');
        }

        if (! $this->userCanChat($target)) {
            throw new InvalidArgumentException('That person is not on this sitting\'s floor chat.');
        }
    }

    private function addParticipant(SessionConversation $conversation, User $user, mixed $joinedAt): void
    {
        SessionConversationParticipant::query()->create([
            'conversation_id' => $conversation->getKey(),
            'user_id' => $user->getKey(),
            'joined_at' => $joinedAt,
            'last_read_at' => $joinedAt,
        ]);
    }

    private function markLeft(SessionConversation $conversation, User $user): void
    {
        SessionConversationParticipant::query()
            ->where('conversation_id', $conversation->getKey())
            ->where('user_id', $user->getKey())
            ->whereNull('left_at')
            ->update(['left_at' => now()]);
    }

    /**
     * @param  list<string>  $recipientIds
     */
    private function notifyInbox(SessionConversation $conversation, array $recipientIds): void
    {
        $ids = array_values(array_filter($recipientIds));

        if ($ids === []) {
            return;
        }

        event(new SessionChatInboxUpdated(
            $conversation->getKey(),
            $conversation->session_id,
            $ids,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function conversationPayload(SessionConversation $conversation, User $viewer): array
    {
        $participants = $conversation->currentParticipantRows
            ->map(fn (SessionConversationParticipant $row): array => [
                'id' => $row->user_id,
                'display_name' => $row->user?->display_name,
                'is_clerk' => $row->user?->hasRole(UserRole::Secretariat->value) ?? false,
                'last_read_at' => $row->last_read_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        $other = $conversation->currentParticipantRows
            ->first(fn (SessionConversationParticipant $row): bool => $row->user_id !== $viewer->getKey());

        $last = $conversation->relationLoaded('messages')
            ? $conversation->messages->sortByDesc('created_at')->first()
            : $conversation->messages()->latest('created_at')->first();

        $ownRow = $conversation->currentParticipantRows
            ->first(fn (SessionConversationParticipant $row): bool => $row->user_id === $viewer->getKey());

        $lastReadAt = $ownRow?->last_read_at;

        $unread = SessionMessage::query()
            ->where('conversation_id', $conversation->getKey())
            ->where('user_id', '!=', $viewer->getKey())
            ->when(
                $lastReadAt !== null,
                fn ($query) => $query->where('created_at', '>', $lastReadAt),
            )
            ->count();

        $title = $conversation->isGroup()
            ? (string) $conversation->name
            : (string) ($other?->user !== null ? $other->user->display_name : '');

        return [
            'id' => $conversation->getKey(),
            'type' => $conversation->type->value,
            'name' => $conversation->name,
            'title' => $title,
            'created_by' => $conversation->created_by,
            'is_creator' => $conversation->created_by === $viewer->getKey(),
            'participants' => $participants,
            'last_message' => $last instanceof SessionMessage
                ? [
                    'id' => $last->getKey(),
                    'user_id' => $last->user_id,
                    'body' => $last->body,
                    'created_at' => $last->created_at?->toIso8601String(),
                ]
                : null,
            'last_message_at' => ($conversation->last_message_at ?? $last?->created_at)?->toIso8601String(),
            'unread_count' => $unread,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function person(User $user, ?string $secretaryId): array
    {
        return [
            'id' => $user->getKey(),
            'display_name' => $user->display_name,
            'is_clerk' => $user->hasRole(UserRole::Secretariat->value),
            'is_designated_secretary' => $secretaryId !== null && $user->getKey() === $secretaryId,
        ];
    }

    private function votingIsOpen(LegislativeSession $session): bool
    {
        return $session->agendaItems()->whereNotNull('voting_open_at')->exists();
    }
}

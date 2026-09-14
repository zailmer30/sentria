<?php

namespace App\Services\Sessions;

use App\Events\FloorRecognitionUpdated;
use App\Http\Resources\SessionResource;
use App\Models\AgendaItem;
use App\Models\FloorRecognitionRequest;
use App\Models\LegislativeSession;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\States\Session\InSession;
use Illuminate\Database\UniqueConstraintViolationException;
use InvalidArgumentException;

class FloorRecognitionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function raise(
        LegislativeSession $session,
        AgendaItem $agendaItem,
        User $member,
    ): FloorRecognitionRequest {
        abort_unless($agendaItem->session_id === $session->getKey(), 422);

        if (! $session->status instanceof InSession) {
            throw new InvalidArgumentException('Recognition may only be sought while the sitting is in session.');
        }

        $existing = FloorRecognitionRequest::query()
            ->where('session_id', $session->getKey())
            ->where('user_id', $member->getKey())
            ->pending()
            ->exists();

        if ($existing) {
            throw new InvalidArgumentException('You already have a pending request for the floor.');
        }

        try {
            $request = FloorRecognitionRequest::query()->create([
                'session_id' => $session->getKey(),
                'user_id' => $member->getKey(),
                'agenda_item_id' => $agendaItem->getKey(),
                'status' => 'pending',
                'raised_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new InvalidArgumentException('You already have a pending request for the floor.');
        }

        $this->audit->record(
            event: 'floor.recognition.raised',
            category: 'session',
            auditable: $request,
            actor: $member,
            new: [
                'agenda_item_id' => $agendaItem->getKey(),
            ],
            message: 'Member sought recognition to move.',
        );

        $this->broadcast($session);

        return $request;
    }

    public function cancel(FloorRecognitionRequest $request, User $actor): FloorRecognitionRequest
    {
        if (! $request->isPending()) {
            throw new InvalidArgumentException('This request is no longer pending.');
        }

        $request->update([
            'status' => 'cancelled',
            'resolved_at' => now(),
            'resolved_by' => $actor->getKey(),
        ]);

        $this->audit->record(
            event: 'floor.recognition.cancelled',
            category: 'session',
            auditable: $request,
            actor: $actor,
            new: ['status' => 'cancelled'],
            message: 'Floor recognition request cancelled.',
        );

        $this->broadcast($request->loadMissing('session')->session);

        return $request->fresh() ?? $request;
    }

    public function recognize(FloorRecognitionRequest $request, User $officer): FloorRecognitionRequest
    {
        if (! $request->isPending()) {
            throw new InvalidArgumentException('This request is no longer pending.');
        }

        $session = $request->loadMissing('session')->session;
        if ($session === null) {
            throw new InvalidArgumentException('Session not found.');
        }

        $already = FloorRecognitionRequest::query()
            ->where('session_id', $session->getKey())
            ->openRecognized()
            ->exists();

        if ($already) {
            throw new InvalidArgumentException('Another member already has the floor.');
        }

        try {
            $request->update([
                'status' => 'recognized',
            ]);
        } catch (UniqueConstraintViolationException) {
            throw new InvalidArgumentException('Another member already has the floor.');
        }

        $this->audit->record(
            event: 'floor.recognition.recognized',
            category: 'session',
            auditable: $request,
            actor: $officer,
            new: ['status' => 'recognized'],
            message: 'Presiding officer recognized a member to move.',
        );

        $this->broadcast($session);

        return $request->fresh() ?? $request;
    }

    public function dismiss(FloorRecognitionRequest $request, User $officer): FloorRecognitionRequest
    {
        if (! $request->isPending() && ! $request->isOpenRecognized()) {
            throw new InvalidArgumentException('This request can no longer be dismissed.');
        }

        $request->update([
            'status' => 'dismissed',
            'resolved_at' => now(),
            'resolved_by' => $officer->getKey(),
        ]);

        $this->audit->record(
            event: 'floor.recognition.dismissed',
            category: 'session',
            auditable: $request,
            actor: $officer,
            new: ['status' => 'dismissed'],
            message: 'Floor recognition request dismissed.',
        );

        $session = $request->loadMissing('session')->session;
        if ($session !== null) {
            $this->broadcast($session);
        }

        return $request->fresh() ?? $request;
    }

    public function resolveForMover(LegislativeSession $session, User $mover): void
    {
        $open = FloorRecognitionRequest::query()
            ->where('session_id', $session->getKey())
            ->where('user_id', $mover->getKey())
            ->openRecognized()
            ->first();

        if ($open === null) {
            return;
        }

        $open->update([
            'resolved_at' => now(),
        ]);

        $this->broadcast($session);
    }

    public function openRecognized(LegislativeSession $session): ?FloorRecognitionRequest
    {
        return FloorRecognitionRequest::query()
            ->where('session_id', $session->getKey())
            ->openRecognized()
            ->with(['member', 'agendaItem'])
            ->first();
    }

    /**
     * @return array{pending: list<array<string, mixed>>, recognized: array<string, mixed>|null}
     */
    public function snapshot(LegislativeSession $session, ?User $viewer = null): array
    {
        $session->loadMissing(['floorRecognitionRequests.member', 'floorRecognitionRequests.agendaItem', 'floorRecognitionRequests.session']);

        $pending = $session->floorRecognitionRequests
            ->filter(fn (FloorRecognitionRequest $row): bool => $row->isPending())
            ->sortBy('raised_at')
            ->values()
            ->map(fn (FloorRecognitionRequest $row): array => SessionResource::recognitionRequest($row, $viewer))
            ->all();

        $recognizedRow = $session->floorRecognitionRequests
            ->first(fn (FloorRecognitionRequest $row): bool => $row->isOpenRecognized());

        return [
            'pending' => $pending,
            'recognized' => $recognizedRow !== null
                ? SessionResource::recognitionRequest($recognizedRow, $viewer)
                : null,
        ];
    }

    private function broadcast(LegislativeSession $session): void
    {
        $fresh = $session->fresh() ?? $session;
        $snapshot = $this->snapshot($fresh);

        event(new FloorRecognitionUpdated($fresh, $snapshot['pending'], $snapshot['recognized']));
    }
}

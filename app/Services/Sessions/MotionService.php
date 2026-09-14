<?php

namespace App\Services\Sessions;

use App\Events\MotionRecorded;
use App\Models\AgendaItem;
use App\Models\LegislativeSession;
use App\Models\Motion;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

class MotionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  'main'|'amendment'|'subsidiary'|'procedural'  $type
     */
    public function record(
        LegislativeSession $session,
        AgendaItem $agendaItem,
        User $mover,
        string $text,
        string $type = 'main',
    ): Motion {
        abort_unless($agendaItem->session_id === $session->getKey(), 422);

        $motion = Motion::query()->create([
            'session_id' => $session->getKey(),
            'agenda_item_id' => $agendaItem->getKey(),
            'type' => $type,
            'text' => $text,
            'status' => 'proposed',
            'moved_by' => $mover->getKey(),
            'moved_at' => now(),
            'requires_vote' => true,
        ]);

        $this->audit->record(
            event: 'motion.recorded',
            category: 'session',
            auditable: $motion,
            actor: $mover,
            new: [
                'text' => $text,
                'type' => $type,
                'agenda_item_id' => $agendaItem->getKey(),
            ],
            message: 'Motion recorded on the floor.',
        );

        event(new MotionRecorded($session, $motion));

        return $motion;
    }

    public function second(Motion $motion, User $seconder): Motion
    {
        if ($motion->status !== 'proposed') {
            throw new InvalidArgumentException('Only proposed motions can be seconded.');
        }

        if ($motion->moved_by === $seconder->getKey()) {
            throw new InvalidArgumentException('The mover cannot second their own motion.');
        }

        $motion->update([
            'seconded_by' => $seconder->getKey(),
            'seconded_at' => now(),
            'status' => 'seconded',
        ]);

        $this->audit->record(
            event: 'motion.seconded',
            category: 'session',
            auditable: $motion,
            actor: $seconder,
            new: ['seconded_by' => $seconder->getKey()],
            message: 'Motion seconded.',
        );

        $this->broadcastMotion($motion);

        return $motion->fresh() ?? $motion;
    }

    public function withdraw(Motion $motion, User $actor): Motion
    {
        if ($motion->moved_by !== $actor->getKey()) {
            throw new AuthorizationException('Only the mover may withdraw a motion.');
        }

        if (in_array($motion->status, ['withdrawn', 'carried', 'lost', 'ruled_out'], true)) {
            throw new InvalidArgumentException('This motion can no longer be withdrawn.');
        }

        $motion->update([
            'status' => 'withdrawn',
            'disposed_at' => now(),
        ]);

        $this->audit->record(
            event: 'motion.withdrawn',
            category: 'session',
            auditable: $motion,
            actor: $actor,
            new: ['status' => 'withdrawn'],
            message: 'Motion withdrawn.',
        );

        $this->broadcastMotion($motion);

        return $motion->fresh() ?? $motion;
    }

    /**
     * @param  'carried'|'lost'|'ruled_out'|'referred'  $disposition
     */
    public function rule(Motion $motion, User $presidingOfficer, string $disposition, ?string $notes = null): Motion
    {
        $motion->update([
            'status' => $disposition,
            'disposed_at' => now(),
            'disposition_notes' => $notes,
        ]);

        $this->audit->record(
            event: 'motion.ruled',
            category: 'session',
            auditable: $motion,
            actor: $presidingOfficer,
            new: ['status' => $disposition, 'disposition_notes' => $notes],
            message: 'Presiding officer ruled on motion.',
        );

        $this->broadcastMotion($motion);

        return $motion->fresh() ?? $motion;
    }

    private function broadcastMotion(Motion $motion): void
    {
        $motion->loadMissing('session');
        $session = $motion->session;
        if ($session === null) {
            throw new InvalidArgumentException('Motion session not found.');
        }

        event(new MotionRecorded($session, $motion->fresh() ?? $motion));
    }
}

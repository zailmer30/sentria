<?php

namespace App\States\Session;

use App\Models\LegislativeSession;
use Spatie\ModelStates\Exceptions\InvalidConfig;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Legislative session lifecycle. Transitions are declared only here — never as
 * raw status string assignments in controllers.
 *
 * Draft → Agenda Prepared → Scheduled → In Session
 *   ↔ Suspended → Adjourned → Minutes for Review → Finalized → Archived
 *
 * Documents Distributed remains only so existing rows can still open.
 *
 * @extends State<LegislativeSession>
 */
abstract class SessionStatus extends State
{
    abstract public function label(): string;

    /**
     * @throws InvalidConfig
     */
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, AgendaPrepared::class)
            ->allowTransition(AgendaPrepared::class, Scheduled::class)
            ->allowTransition(Scheduled::class, InSession::class)
            ->allowTransition(DocumentsDistributed::class, InSession::class)
            ->allowTransition(InSession::class, Suspended::class)
            ->allowTransition(Suspended::class, InSession::class)
            ->allowTransition(InSession::class, Adjourned::class)
            ->allowTransition(Suspended::class, Adjourned::class)
            ->allowTransition(Adjourned::class, MinutesForReview::class)
            ->allowTransition(MinutesForReview::class, Finalized::class)
            ->allowTransition(Finalized::class, Archived::class);
    }

    /**
     * Permission required to enter this state from another state.
     *
     * Calling to order is a secretariat act. Recess and return from recess
     * stay with the chair, so Suspended → In Session uses `sessions.suspend`.
     *
     * @param  class-string<SessionStatus>  $stateClass
     * @param  class-string<SessionStatus>|null  $fromClass
     */
    public static function permissionFor(string $stateClass, ?string $fromClass = null): ?string
    {
        if ($stateClass === InSession::class && $fromClass === Suspended::class) {
            return 'sessions.suspend';
        }

        return match ($stateClass) {
            AgendaPrepared::class => 'agenda.manage',
            Scheduled::class => 'sessions.schedule',
            InSession::class => 'sessions.start',
            Suspended::class => 'sessions.suspend',
            Adjourned::class => 'sessions.adjourn',
            MinutesForReview::class => 'minutes.edit',
            Finalized::class => 'minutes.finalize',
            Archived::class => 'sessions.archive',
            default => null,
        };
    }
}

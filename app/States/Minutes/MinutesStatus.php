<?php

namespace App\States\Minutes;

use App\Models\Minutes;
use Spatie\ModelStates\Exceptions\InvalidConfig;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Minutes workflow. AI may reach {@see AiDraft} only — never {@see FinalMinutes}.
 *
 * Session Completed → AI Draft → Secretariat Review → Edit → Review
 *   → Approval → Final Minutes → Archive
 *
 * @extends State<Minutes>
 */
abstract class MinutesStatus extends State
{
    abstract public function label(): string;

    /**
     * @throws InvalidConfig
     */
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(SessionCompleted::class)
            ->allowTransition(SessionCompleted::class, AiDraft::class)
            ->allowTransition(AiDraft::class, SecretariatReview::class)
            ->allowTransition(SecretariatReview::class, Edit::class)
            ->allowTransition(Edit::class, Review::class)
            ->allowTransition(Review::class, Edit::class)
            ->allowTransition(Review::class, Approval::class)
            ->allowTransition(Approval::class, FinalMinutes::class)
            ->allowTransition(FinalMinutes::class, Archive::class);
    }

    /**
     * Permission required to enter this state from another state.
     *
     * @param  class-string<MinutesStatus>  $stateClass
     */
    public static function permissionFor(string $stateClass): ?string
    {
        return match ($stateClass) {
            AiDraft::class => 'minutes.generateDraft',
            SecretariatReview::class, Edit::class => 'minutes.edit',
            Review::class => 'minutes.review',
            Approval::class => 'minutes.approve',
            FinalMinutes::class => 'minutes.finalize',
            Archive::class => 'minutes.finalize',
            default => null,
        };
    }
}

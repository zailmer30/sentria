<?php

namespace App\States\Publication;

use App\Models\Publication;
use Spatie\ModelStates\Exceptions\InvalidConfig;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Publication workflow for the public legislative portal.
 *
 * Internal Document → Secretariat Review → Publication Review → Mark Public
 *   → Publish (published) → visible on Public Portal via {@see Publication::scopeLive}.
 *
 * @extends State<Publication>
 */
abstract class PublicationWorkflowStatus extends State
{
    abstract public function label(): string;

    /**
     * @throws InvalidConfig
     */
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(InternalDocument::class)
            ->allowTransition(InternalDocument::class, SecretariatReview::class)
            ->allowTransition(SecretariatReview::class, PublicationReview::class)
            ->allowTransition(PublicationReview::class, MarkPublic::class)
            ->allowTransition(MarkPublic::class, Published::class);
    }

    /**
     * @param  class-string<PublicationWorkflowStatus>  $stateClass
     */
    public static function permissionFor(string $stateClass): ?string
    {
        return match ($stateClass) {
            SecretariatReview::class, PublicationReview::class => 'publications.review',
            MarkPublic::class, Published::class => 'publications.publish',
            default => null,
        };
    }
}

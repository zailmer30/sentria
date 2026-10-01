<?php

namespace App\States\Document;

use App\Models\Document;
use Spatie\ModelStates\Exceptions\InvalidConfig;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Configurable IRP document workflow. Transitions live only in this config —
 * never as scattered status string updates in controllers.
 *
 * Measure types follow first reading, then committee, then second reading.
 * Ordinances continue to third reading; resolutions are adopted or rejected
 * on the second-reading vote. Other types keep the shorter register → refer path.
 *
 * @extends State<Document>
 */
abstract class DocumentWorkflowStatus extends State
{
    abstract public function label(): string;

    /**
     * @throws InvalidConfig
     */
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Submitted::class)
            ->allowTransition(Submitted::class, SecretariatReview::class)
            ->allowTransition(Submitted::class, ReturnedForRevision::class)
            ->allowTransition(SecretariatReview::class, Registered::class)
            ->allowTransition(SecretariatReview::class, ReturnedForRevision::class)
            ->allowTransition(ReturnedForRevision::class, Submitted::class)
            ->allowTransition(Registered::class, CommitteeReferral::class)
            ->allowTransition(Registered::class, AgendaInclusion::class)
            ->allowTransition(AgendaInclusion::class, ReadingDeliberation::class)
            ->allowTransition(AgendaInclusion::class, Approved::class)
            ->allowTransition(AgendaInclusion::class, Rejected::class)
            ->allowTransition(ReadingDeliberation::class, CommitteeReferral::class)
            ->allowTransition(CommitteeReferral::class, CommitteeReview::class)
            ->allowTransition(CommitteeReferral::class, Registered::class)
            ->allowTransition(CommitteeReferral::class, AgendaInclusion::class)
            ->allowTransition(CommitteeReview::class, CommitteeReport::class)
            ->allowTransition(CommitteeReview::class, Registered::class)
            ->allowTransition(CommitteeReview::class, AgendaInclusion::class)
            ->allowTransition(CommitteeReport::class, AgendaInclusion::class)
            ->allowTransition(CommitteeReport::class, Archive::class)
            ->allowTransition(ReadingDeliberation::class, Amendments::class)
            ->allowTransition(ReadingDeliberation::class, Voting::class)
            ->allowTransition(ReadingDeliberation::class, Approved::class)
            ->allowTransition(ReadingDeliberation::class, Rejected::class)
            ->allowTransition(Amendments::class, ReadingDeliberation::class)
            ->allowTransition(Amendments::class, Voting::class)
            ->allowTransition(Voting::class, Approved::class)
            ->allowTransition(Voting::class, Rejected::class)
            ->allowTransition(Voting::class, ReadingDeliberation::class)
            ->allowTransition(Voting::class, FinalDocument::class)
            ->allowTransition(Approved::class, FinalDocument::class)
            ->allowTransition(Approved::class, Transmittal::class)
            ->allowTransition(FinalDocument::class, Transmittal::class)
            ->allowTransition(FinalDocument::class, AgendaInclusion::class)
            ->allowTransition(FinalDocument::class, Approved::class)
            ->allowTransition(FinalDocument::class, Rejected::class)
            ->allowTransition(Transmittal::class, Archive::class)
            ->allowTransition(Archive::class, PublicPublication::class)
            ->allowTransition(Rejected::class, Archive::class);
    }

    /**
     * Legal next states from this one. Keep allowed edges in {@see config()};
     * this list is the subset offered for the current document type and reading.
     *
     * @return list<class-string<DocumentWorkflowStatus>>
     */
    public function successors(): array
    {
        $document = $this->getModel();
        $isMeasure = $document instanceof Document && $document->document_type->isMeasure();
        $reading = $document instanceof Document ? $document->current_reading : null;
        $requiresThirdReading = $document instanceof Document && $document->document_type->requiresThirdReading();

        return match (static::class) {
            Submitted::class => [SecretariatReview::class, ReturnedForRevision::class],
            SecretariatReview::class => [Registered::class, ReturnedForRevision::class],
            ReturnedForRevision::class => [Submitted::class],
            Registered::class => $isMeasure
                ? [AgendaInclusion::class]
                : [CommitteeReferral::class],
            AgendaInclusion::class => [ReadingDeliberation::class],
            ReadingDeliberation::class => $this->readingDeliberationSuccessors($isMeasure, $reading),
            CommitteeReferral::class => [CommitteeReview::class],
            CommitteeReview::class => [CommitteeReport::class],
            CommitteeReport::class => [AgendaInclusion::class, Archive::class],
            Amendments::class => [ReadingDeliberation::class, Voting::class],
            Voting::class => $this->votingSuccessors($isMeasure, $reading, $requiresThirdReading),
            Approved::class => $isMeasure
                ? [Transmittal::class]
                : [FinalDocument::class],
            FinalDocument::class => $isMeasure
                ? [AgendaInclusion::class]
                : [Transmittal::class],
            Transmittal::class => [Archive::class],
            Rejected::class => [Archive::class],
            Archive::class => [PublicPublication::class],
            PublicPublication::class => [],
            default => [],
        };
    }

    /**
     * @return list<class-string<DocumentWorkflowStatus>>
     */
    private function readingDeliberationSuccessors(bool $isMeasure, ?int $reading): array
    {
        if (! $isMeasure) {
            return [Amendments::class, Voting::class];
        }

        return match ($reading) {
            1 => [CommitteeReferral::class],
            3 => [Voting::class],
            default => [Amendments::class, Voting::class],
        };
    }

    /**
     * @return list<class-string<DocumentWorkflowStatus>>
     */
    private function votingSuccessors(bool $isMeasure, ?int $reading, bool $requiresThirdReading): array
    {
        if (! $isMeasure) {
            return [Approved::class, Rejected::class];
        }

        if ($reading === 2 && $requiresThirdReading) {
            return [FinalDocument::class, ReadingDeliberation::class];
        }

        return [Approved::class, Rejected::class];
    }

    /**
     * Permissions that may take this hop. The actor needs any one of them.
     *
     * @param  class-string<DocumentWorkflowStatus>  $stateClass
     * @param  class-string<DocumentWorkflowStatus>|null  $fromClass
     * @return list<string>
     */
    public static function permissionsFor(string $stateClass, ?string $fromClass = null, ?Document $document = null): array
    {
        if (
            $stateClass === ReadingDeliberation::class
            && $fromClass === AgendaInclusion::class
            && $document !== null
            && (int) ($document->current_reading ?? 1) === 1
        ) {
            return ['sessions.start', 'documents.refer'];
        }

        $permission = self::permissionFor($stateClass, $fromClass);

        return $permission === null ? [] : [$permission];
    }

    /**
     * @param  class-string<DocumentWorkflowStatus>  $stateClass
     * @param  class-string<DocumentWorkflowStatus>|null  $fromClass
     */
    public static function permissionFor(string $stateClass, ?string $fromClass = null): ?string
    {
        if ($stateClass === Registered::class && in_array($fromClass, [CommitteeReferral::class, CommitteeReview::class], true)) {
            return 'documents.refer';
        }

        if ($stateClass === Archive::class && $fromClass === CommitteeReport::class) {
            return 'documents.archive';
        }

        if ($stateClass === FinalDocument::class && $fromClass === Voting::class) {
            return 'legislation.manage';
        }

        if ($stateClass === ReadingDeliberation::class && $fromClass === Voting::class) {
            return 'sessions.start';
        }

        if ($stateClass === Transmittal::class && $fromClass === Approved::class) {
            return 'legislation.manage';
        }

        return match ($stateClass) {
            SecretariatReview::class => 'documents.review',
            ReturnedForRevision::class => 'documents.review',
            Registered::class => 'documents.register',
            CommitteeReferral::class, CommitteeReview::class => 'documents.refer',
            CommitteeReport::class => 'reports.submit',
            AgendaInclusion::class => 'agenda.manage',
            ReadingDeliberation::class, Amendments::class, Voting::class => 'sessions.start',
            Approved::class, Rejected::class, FinalDocument::class => 'legislation.manage',
            Transmittal::class => 'legislation.manage',
            Archive::class => 'documents.archive',
            PublicPublication::class => 'publications.publish',
            default => null,
        };
    }

    /**
     * Hops the sitting takes once the filing is on an agenda. The document
     * page must not offer them — calendar and floor update status from the
     * result of that session.
     *
     * @param  class-string<DocumentWorkflowStatus>  $stateClass
     * @param  class-string<DocumentWorkflowStatus>|null  $fromClass
     */
    public static function isSessionOwnedHop(string $stateClass, ?string $fromClass = null): bool
    {
        if ($stateClass === AgendaInclusion::class) {
            // "Ready for first reading" stays a register action. Second and
            // third reading inclusion happen from the calendar.
            return $fromClass !== Registered::class;
        }

        if ($stateClass === CommitteeReferral::class) {
            return $fromClass === ReadingDeliberation::class;
        }

        return in_array($stateClass, [
            ReadingDeliberation::class,
            Amendments::class,
            Voting::class,
            Approved::class,
            Rejected::class,
            FinalDocument::class,
        ], true);
    }

    /**
     * Button copy for advancing into this state. The current status already
     * has {@see label()}; the action names the step that will be taken.
     *
     * @param  class-string<DocumentWorkflowStatus>  $stateClass
     * @param  class-string<DocumentWorkflowStatus>|null  $fromClass
     */
    public static function actionLabelFor(string $stateClass, ?string $fromClass = null, ?Document $document = null): string
    {
        if ($stateClass === AgendaInclusion::class) {
            return match ($fromClass) {
                CommitteeReferral::class, CommitteeReview::class, CommitteeReport::class => 'Ready for second reading',
                FinalDocument::class => ($document?->document_type->requiresThirdReading() ?? true)
                    ? 'Ready for third reading'
                    : 'Ready for agenda',
                default => $document?->document_type->isMeasure()
                    ? 'Ready for first reading'
                    : 'Ready for agenda',
            };
        }

        if ($stateClass === Archive::class && $fromClass === CommitteeReport::class) {
            return 'Archive';
        }

        if ($stateClass === ReadingDeliberation::class && $fromClass === Voting::class) {
            return 'Return to second reading';
        }

        if ($stateClass === FinalDocument::class && $fromClass === Voting::class) {
            return ($document?->document_type->requiresThirdReading() ?? true)
                ? 'Prepare final form'
                : 'Prepare final document';
        }

        if ($stateClass === Transmittal::class) {
            return 'Transmit to LCE / SP';
        }

        if ($stateClass === ReadingDeliberation::class) {
            $reading = $document?->current_reading;

            return match ($reading) {
                2 => 'Open second reading',
                3 => ($document?->document_type->requiresThirdReading() ?? true)
                    ? 'Open third reading'
                    : 'Open second reading',
                default => 'Open first reading',
            };
        }

        return match ($stateClass) {
            SecretariatReview::class => 'Begin secretariat review',
            ReturnedForRevision::class => 'Return for revision',
            Submitted::class => 'Resubmit to secretariat',
            Registered::class => 'Register document',
            CommitteeReferral::class => 'Refer to committee',
            CommitteeReview::class => 'Send to committee review',
            CommitteeReport::class => 'Record committee report',
            Amendments::class => 'Open amendments',
            Voting::class => 'Send to voting',
            Approved::class => 'Mark approved',
            Rejected::class => 'Mark rejected',
            FinalDocument::class => 'Prepare final document',
            Archive::class => 'Archive',
            PublicPublication::class => 'Mark for publication',
            default => $stateClass::$name,
        };
    }
}

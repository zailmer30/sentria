<?php

namespace App\Enums;

enum DocumentType: string
{
    case ProposedOrdinance = 'proposed-ordinance';
    case ProposedResolution = 'proposed-resolution';
    case Ordinance = 'ordinance';
    case Resolution = 'resolution';
    case CommitteeReport = 'committee-report';
    case Communication = 'communication';
    case Petition = 'petition';
    case ExecutiveRequest = 'executive-request';
    case Minutes = 'minutes';
    case Attachment = 'attachment';
    case Memorandum = 'memorandum';
    case Agenda = 'agenda';
    case Endorsement = 'endorsement';
    case Proposal = 'proposal';
    case SupportingDocument = 'supporting-document';

    public function label(): string
    {
        return match ($this) {
            self::ProposedOrdinance => 'Proposed Ordinance',
            self::ProposedResolution => 'Proposed Resolution',
            self::Ordinance => 'Ordinance',
            self::Resolution => 'Resolution',
            self::CommitteeReport => 'Committee Report',
            self::Communication => 'Communication',
            self::Petition => 'Petition',
            self::ExecutiveRequest => 'Executive Request',
            self::Minutes => 'Minutes',
            self::Attachment => 'Attachment',
            self::Memorandum => 'Memorandum',
            self::Agenda => 'Agenda',
            self::Endorsement => 'Endorsement',
            self::Proposal => 'Proposal',
            self::SupportingDocument => 'Supporting Document',
        };
    }

    /**
     * Series prefix for generated references (`PO-2026-00003`).
     */
    public function tag(): string
    {
        return match ($this) {
            self::ProposedOrdinance => 'PO',
            self::ProposedResolution => 'PR',
            self::Ordinance => 'ORD',
            self::Resolution => 'RES',
            self::CommitteeReport => 'CR',
            self::Communication => 'COM',
            self::Petition => 'PET',
            self::ExecutiveRequest => 'ER',
            self::Minutes => 'MIN',
            self::Attachment => 'ATT',
            self::Memorandum => 'MEM',
            self::Agenda => 'AGN',
            self::Endorsement => 'END',
            self::Proposal => 'PROP',
            self::SupportingDocument => 'SD',
        };
    }

    /**
     * Measures move through the full legislative workflow; other types stop at
     * registration and referral.
     */
    public function isMeasure(): bool
    {
        return $this->isOrdinanceMeasure() || $this->isResolutionMeasure();
    }

    public function isOrdinanceMeasure(): bool
    {
        return $this === self::ProposedOrdinance || $this === self::Ordinance;
    }

    public function isResolutionMeasure(): bool
    {
        return $this === self::ProposedResolution || $this === self::Resolution;
    }

    /**
     * Ordinances take three readings. Resolutions are adopted on second reading.
     */
    public function requiresThirdReading(): bool
    {
        return $this->isOrdinanceMeasure();
    }

    public function finalReadingNumber(): int
    {
        return $this->requiresThirdReading() ? 3 : 2;
    }

    /**
     * @return list<self>
     */
    public static function measures(): array
    {
        return [...self::ordinanceMeasures(), ...self::resolutionMeasures()];
    }

    /**
     * @return list<self>
     */
    public static function ordinanceMeasures(): array
    {
        return [self::ProposedOrdinance, self::Ordinance];
    }

    /**
     * @return list<self>
     */
    public static function resolutionMeasures(): array
    {
        return [self::ProposedResolution, self::Resolution];
    }
}

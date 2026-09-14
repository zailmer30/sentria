<?php

namespace App\Enums;

/**
 * The eight canonical roles. Role records are seeded from this enum, so the
 * enum — not the database — is the source of truth for role names.
 */
enum UserRole: string
{
    case SystemAdministrator = 'system-administrator';
    case Secretariat = 'secretariat';
    case PresidingOfficer = 'presiding-officer';
    case BoardMember = 'board-member';
    case CommitteeChair = 'committee-chair';
    case CommitteeMember = 'committee-member';
    case LegalTechnicalReviewer = 'legal-technical-reviewer';
    case PublicUser = 'public-user';

    public function label(): string
    {
        return match ($this) {
            self::SystemAdministrator => 'System Administrator',
            self::Secretariat => 'Secretariat',
            self::PresidingOfficer => 'Presiding Officer',
            self::BoardMember => 'Board Member',
            self::CommitteeChair => 'Committee Chair',
            self::CommitteeMember => 'Committee Member',
            self::LegalTechnicalReviewer => 'Legal/Technical Reviewer',
            self::PublicUser => 'Public User',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SystemAdministrator => 'Full administrative control over users, roles, settings, and system integrity tooling.',
            self::Secretariat => 'Registers documents, prepares agenda, opens sittings, records attendance and votes, and drafts minutes.',
            self::PresidingOfficer => 'Presides over sessions, rules on motions, and declares quorum and adjournment.',
            self::BoardMember => 'Sits in session, files legislative measures, moves and seconds motions, and votes.',
            self::CommitteeChair => 'Leads a committee, manages referrals, and submits committee reports.',
            self::CommitteeMember => 'Participates in committee review and deliberation.',
            self::LegalTechnicalReviewer => 'Reviews measures for legal form, consistency, and technical soundness.',
            self::PublicUser => 'Reads published legislation on the public portal.',
        };
    }

    /**
     * Lower runs first in listings; it does not imply authority to act.
     */
    public function precedence(): int
    {
        return match ($this) {
            self::SystemAdministrator => 10,
            self::PresidingOfficer => 20,
            self::BoardMember => 30,
            self::CommitteeChair => 40,
            self::CommitteeMember => 50,
            self::Secretariat => 60,
            self::LegalTechnicalReviewer => 70,
            self::PublicUser => 100,
        };
    }

    /**
     * Roles whose holders occupy a seat and may therefore vote and be counted
     * toward quorum.
     */
    public function isSeatedMember(): bool
    {
        return in_array($this, [self::PresidingOfficer, self::BoardMember, self::CommitteeChair], true);
    }

    /**
     * @return list<self>
     */
    public static function internal(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $role): bool => $role !== self::PublicUser,
        ));
    }
}

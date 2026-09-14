<?php

namespace App\Enums;

enum Confidentiality: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Restricted = 'restricted';
    case Confidential = 'confidential';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public',
            self::Internal => 'Internal',
            self::Restricted => 'Restricted',
            self::Confidential => 'Confidential',
        };
    }

    /**
     * Higher means more closely held. Used to compare a user's clearance
     * against a document inside retrieval queries.
     */
    public function level(): int
    {
        return match ($this) {
            self::Public => 0,
            self::Internal => 1,
            self::Restricted => 2,
            self::Confidential => 3,
        };
    }
}

<?php

namespace App\Enums;

enum DocumentOrigin: string
{
    case Member = 'member';
    case Executive = 'executive';
    case Citizen = 'citizen';
    case Agency = 'agency';
    case Archive = 'archive';

    public function label(): string
    {
        return match ($this) {
            self::Member => 'Member',
            self::Executive => 'Executive',
            self::Citizen => 'Citizen',
            self::Agency => 'Agency',
            self::Archive => 'Historical archive',
        };
    }
}

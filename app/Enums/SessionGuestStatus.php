<?php

namespace App\Enums;

enum SessionGuestStatus: string
{
    case Invited = 'invited';
    case Present = 'present';
    case DidNotAppear = 'did-not-appear';

    public function label(): string
    {
        return match ($this) {
            self::Invited => 'Invited',
            self::Present => 'Present',
            self::DidNotAppear => 'Did not appear',
        };
    }
}

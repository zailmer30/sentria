<?php

namespace App\Enums;

enum SessionConversationType: string
{
    case Direct = 'direct';
    case Group = 'group';

    public function label(): string
    {
        return match ($this) {
            self::Direct => 'Direct',
            self::Group => 'Group',
        };
    }
}

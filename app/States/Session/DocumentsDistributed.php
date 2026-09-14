<?php

namespace App\States\Session;

/**
 * Leftover status. New sittings no longer enter this state; existing rows may
 * still open onto the floor.
 */
class DocumentsDistributed extends SessionStatus
{
    public static string $name = 'documents-distributed';

    public function label(): string
    {
        return 'Documents Distributed';
    }
}

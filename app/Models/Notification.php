<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Notifications\DatabaseNotification;

/**
 * ULID-keyed notification record. App\Notifications\Notification assigns the
 * ULID so the framework never falls back to a UUID.
 *
 * @property string|null $category
 * @property string $priority
 * @property string|null $action_url
 */
class Notification extends DatabaseNotification
{
    use HasUlids;

    protected $table = 'notifications';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }
}

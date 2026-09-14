<?php

namespace App\Notifications\Channels;

use App\Notifications\Notification as AppNotification;
use Illuminate\Notifications\Channels\DatabaseChannel as BaseDatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Writes the extra columns on Sentria's ULID notifications table
 * (category, priority, action_url) that Laravel's stock channel ignores.
 */
class DatabaseChannel extends BaseDatabaseChannel
{
    /**
     * @param  mixed  $notifiable
     * @return array<string, mixed>
     */
    protected function buildPayload($notifiable, Notification $notification): array
    {
        $payload = parent::buildPayload($notifiable, $notification);

        if ($notification instanceof AppNotification) {
            $payload['category'] = $notification->category();
            $payload['priority'] = $notification->priority();
            $payload['action_url'] = $notification->actionUrl();
        }

        return $payload;
    }
}

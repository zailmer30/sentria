<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('notifications.viewAny');
    }

    public function view(User $user, Notification $notification): bool
    {
        return $this->owns($user, $notification) && $user->can('notifications.viewAny');
    }

    public function update(User $user, Notification $notification): bool
    {
        return $this->owns($user, $notification) && $user->can('notifications.viewAny');
    }

    private function owns(User $user, Notification $notification): bool
    {
        return $notification->notifiable_type === $user->getMorphClass()
            && (string) $notification->notifiable_id === (string) $user->getKey();
    }
}

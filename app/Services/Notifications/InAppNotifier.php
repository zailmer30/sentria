<?php

namespace App\Services\Notifications;

use App\Models\User;
use App\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Str;

/**
 * Sends in-app notifications to unique active users, excluding the actor
 * of an interactive action when one is provided.
 */
class InAppNotifier
{
    /**
     * @param  Collection<int, User>|iterable<User|null>|User|null  $recipients
     */
    public function send(iterable|User|null $recipients, Notification $notification, ?User $except = null): void
    {
        $users = $this->uniqueActive($recipients, $except);

        if ($users->isEmpty()) {
            return;
        }

        // Each recipient needs its own ULID. Laravel reuses the notification
        // instance across notifiables, which would collide on our primary key.
        foreach ($users as $user) {
            $copy = clone $notification;
            $copy->id = (string) Str::ulid();
            NotificationFacade::send($user, $copy);
        }
    }

    /**
     * @param  Collection<int, User>|iterable<User|null>|User|null  $recipients
     * @return Collection<int, User>
     */
    public function uniqueActive(iterable|User|null $recipients, ?User $except = null): Collection
    {
        if ($recipients === null) {
            return collect();
        }

        if ($recipients instanceof User) {
            $recipients = [$recipients];
        }

        $exceptId = $except?->getKey();

        return collect($recipients)
            ->filter(fn (mixed $user): bool => $user instanceof User)
            ->filter(fn (User $user): bool => (bool) $user->is_active)
            ->filter(fn (User $user): bool => $exceptId === null || $user->getKey() !== $exceptId)
            ->unique(fn (User $user): string => (string) $user->getKey())
            ->values();
    }
}

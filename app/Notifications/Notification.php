<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Str;

/**
 * Base class for every in-app notification. Assigns a ULID up front so the
 * framework's UUID fallback never reaches the ULID-keyed `notifications`
 * table. Delivers via database + broadcast; copy uses translation keys.
 */
abstract class Notification extends BaseNotification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
        $this->id = (string) Str::ulid();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    abstract public function category(): string;

    public function priority(): string
    {
        return 'normal';
    }

    abstract public function actionUrl(): ?string;

    /**
     * Translation key for the short title shown in the bell and inbox.
     */
    abstract public function titleKey(): string;

    /**
     * Translation key for the body line.
     */
    abstract public function bodyKey(): string;

    /**
     * @return array<string, string|int|float|bool|null>
     */
    public function titleParams(): array
    {
        return [];
    }

    /**
     * @return array<string, string|int|float|bool|null>
     */
    public function bodyParams(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title_key' => $this->titleKey(),
            'title_params' => $this->titleParams(),
            'body_key' => $this->bodyKey(),
            'body_params' => $this->bodyParams(),
            'category' => $this->category(),
            'priority' => $this->priority(),
            'action_url' => $this->actionUrl(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            ...$this->toArray($notifiable),
            'id' => $this->id,
            'type' => static::class,
            'read_at' => null,
            'created_at' => now()->toIso8601String(),
        ]);
    }
}

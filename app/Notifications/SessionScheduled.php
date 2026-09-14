<?php

namespace App\Notifications;

use App\Models\LegislativeSession;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Carbon;

class SessionScheduled extends Notification
{
    public function __construct(
        public LegislativeSession $session,
    ) {
        parent::__construct();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast', 'mail'];
    }

    public function category(): string
    {
        return 'sessions';
    }

    public function priority(): string
    {
        return 'normal';
    }

    public function actionUrl(): ?string
    {
        return route('sessions.show', $this->session, absolute: false);
    }

    public function titleKey(): string
    {
        return 'notifications.session_scheduled_title';
    }

    public function bodyKey(): string
    {
        return 'notifications.session_scheduled_body';
    }

    /**
     * @return array<string, string|int|null>
     */
    public function titleParams(): array
    {
        return [
            'title' => $this->session->title,
        ];
    }

    /**
     * @return array<string, string|int|null>
     */
    public function bodyParams(): array
    {
        return [
            'title' => $this->session->title,
            'number' => $this->session->session_number,
            'when' => $this->whenLabel(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $this->localeFor($notifiable);

        $mail = (new MailMessage)
            ->subject((string) __($this->titleKey(), $this->titleParams(), $locale))
            ->line((string) __($this->bodyKey(), $this->bodyParams(), $locale));

        $when = $this->whenLabel();

        if ($when !== null) {
            $mail->line((string) __('notifications.session_scheduled_when', ['when' => $when], $locale));
        }

        $venue = $this->session->venue;

        if (is_string($venue) && $venue !== '') {
            $mail->line((string) __('notifications.session_scheduled_venue', ['venue' => $venue], $locale));
        }

        return $mail->action(
            (string) __('notifications.session_scheduled_action', [], $locale),
            route('sessions.show', $this->session),
        );
    }

    public function whenLabel(): ?string
    {
        $start = $this->session->scheduled_start_at;

        if (! $start instanceof Carbon) {
            return null;
        }

        $formatted = $this->formatStamp($start);
        $end = $this->session->scheduled_end_at;

        if ($end instanceof Carbon) {
            return $formatted.' – '.$this->formatStamp($end);
        }

        return $formatted;
    }

    private function formatStamp(Carbon $at): string
    {
        return $at->copy()->timezone((string) config('app.timezone'))->format('M j, Y g:i A T');
    }

    private function localeFor(object $notifiable): string
    {
        if ($notifiable instanceof User && is_string($notifiable->locale) && $notifiable->locale !== '') {
            return $notifiable->locale;
        }

        return 'en';
    }
}

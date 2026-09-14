<?php

namespace App\Notifications;

use App\Models\Committee;

class CommitteeMemberAppointed extends Notification
{
    public function __construct(
        public Committee $committee,
        public string $position,
    ) {
        parent::__construct();
    }

    public function category(): string
    {
        return 'committees';
    }

    public function priority(): string
    {
        return 'normal';
    }

    public function actionUrl(): ?string
    {
        return route('committees.show', $this->committee, absolute: false);
    }

    public function titleKey(): string
    {
        return 'notifications.member_appointed_title';
    }

    public function bodyKey(): string
    {
        return 'notifications.member_appointed_body';
    }

    /**
     * @return array<string, string|int|null>
     */
    public function titleParams(): array
    {
        return [
            'committee' => $this->committee->name,
        ];
    }

    /**
     * @return array<string, string|int|null>
     */
    public function bodyParams(): array
    {
        return [
            'committee' => $this->committee->name,
            'position' => $this->position,
        ];
    }
}

<?php

namespace App\Notifications;

use App\Models\Committee;
use App\Models\CommitteeReferral;
use App\Models\Document;

class DocumentReferralReturned extends Notification
{
    public function __construct(
        public Document $document,
        public Committee $committee,
        public CommitteeReferral $referral,
    ) {
        parent::__construct();
    }

    public function category(): string
    {
        return 'committees';
    }

    public function priority(): string
    {
        return 'high';
    }

    public function actionUrl(): ?string
    {
        return route('documents.show', $this->document, absolute: false);
    }

    public function titleKey(): string
    {
        return $this->referral->status === 'closed'
            ? 'notifications.referral_closed_title'
            : 'notifications.referral_returned_title';
    }

    public function bodyKey(): string
    {
        return $this->referral->status === 'closed'
            ? 'notifications.referral_closed_body'
            : 'notifications.referral_returned_body';
    }

    /**
     * @return array<string, string|int|null>
     */
    public function titleParams(): array
    {
        return [
            'title' => $this->document->title,
        ];
    }

    /**
     * @return array<string, string|int|null>
     */
    public function bodyParams(): array
    {
        return [
            'title' => $this->document->title,
            'committee' => $this->committee->name,
            'reason' => $this->referral->outcome_notes,
        ];
    }
}

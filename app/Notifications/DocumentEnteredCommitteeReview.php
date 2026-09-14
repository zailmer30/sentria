<?php

namespace App\Notifications;

use App\Models\Committee;
use App\Models\Document;

class DocumentEnteredCommitteeReview extends Notification
{
    public function __construct(
        public Document $document,
        public Committee $committee,
        public string $state,
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
        return 'notifications.committee_review_title';
    }

    public function bodyKey(): string
    {
        return 'notifications.committee_review_body';
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
            'state' => $this->state,
        ];
    }
}
